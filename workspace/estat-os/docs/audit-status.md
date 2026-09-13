# Status of the 2026-09-11 audit findings, as of 2.7.0

`audit/findings.md` in the repository root was written against 2.5.x. It listed
five real bugs. This is where each one stands now, checked against the code by
reading it and against behaviour by executing it (`php tests/integration/run-integration.php`, 2,038 assertions).

| Finding | Status in 2.7.0 |
|---|---|
| BUG-1 — properties cannot be deleted | **Not a product fault; the confusion is fixed.** Deletion was always reachable through the general `trash_record` action, checked per record kind (`record_actions()` on every row). What existed was a dead `trash_listing` entry in `Actions::CAPS` that made deletion *look* unrouted. The entry is gone and `it-31` now pins that no dead entries return. |
| BUG-2 — project stats confirmed drafts to strangers | **Fixed in 2.6.x, re-verified.** `get_project_stats()` returns the same 404 for unpublished records as for ones that never existed. |
| BUG-3 — the card widget could render a draft | **Fixed in 2.6.x, re-verified.** `Components::card()` refuses unpublished records unless the viewer may manage listings, so previewing still works. |
| BUG-4 — agents could self-raise "office verified" | **Fixed in 2.6.x, re-verified.** `Listings::save_meta()` skips `_estat_verification` unless `estat_verify_listings` is held; `it-15`/`it-30` assert it and it survived mutation testing in 2.6.0. |
| BUG-5 — CSV export wrote formulas | **Fixed in 2.6.x, re-verified.** The export path guards `= + - @` tab/CR; `it-26` covers it. |
| OBS-1 — visits could reference a missing enquiry | Still open (data hygiene; the API accepts `lead_id => 999999` and every screen renders). A one-line existence check in `Visits::schedule()` would close it. |
| OBS-2 — "price on request" hidden from the office's own private view | Still open (cosmetic; the figure is stored and shown in the editor). |
| Harness limits (no real MySQL, uninstall post-deletion loop, no browser run) | Unchanged. PHP 8.3 in this environment is CLI-only; the browser-facing claims in this release are checked by markup assertions, not by a person clicking. The review screen and the agent's suggest-a-change form have never been seen in a real browser — **needs one manual pass on a live site.** |

