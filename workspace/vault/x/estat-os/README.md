# Estat.OS — a real-estate operating system for WordPress

Estat.OS turns a plain WordPress site into the working system of a property
office: inventory, discovery, enquiries, follow-ups, agent assignment, site
visits, conversion and reporting — all in one place, in plain language.

It is written for the person running the office, not for a developer. There is
no talk of post types, taxonomies, meta keys or slugs anywhere in the interface.

* **Version:** 2.6.0
* **Licence:** GPL-2.0-or-later
* **Requires:** WordPress 6.0+, PHP 7.4+
* **Languages:** English and Hinglish, both complete
* **Tests:** 1,725 integration + 49 unit

**Full version history** — every release, what changed and why — is in
[MANUAL.md, Part 7](MANUAL.md#part-7--the-full-history). The technical
changelog is in [CHANGELOG.md](CHANGELOG.md).
* **No dependencies:** no Composer in production, no ACF, no React admin,
  no Elasticsearch, no Redis, no Node at runtime. Elementor is optional.

> **Release status.** Verified against real WordPress source with 381 automated
> assertions (49 unit, 332 integration), with no warnings or deprecations. See
> the [release readiness report](docs/release-readiness.md) and the
> [implementation matrix](docs/implementation-matrix.md).

---

## What it does

| Area | What you get |
| --- | --- |
| **Inventory** | Properties, societies/projects, team members, agencies, documents |
| **Discovery** | Fast filtered search backed by a dedicated index table, favourites, side-by-side compare |
| **Pipeline** | Enquiries → follow-ups → agent assignment → site visits → closed deals |
| **Forms** | A visual drag-and-drop form builder with per-device width controls, 25 field types, conditional logic |
| **Reporting** | Office health, pipeline, per-person performance, busiest areas, enquiry sources |
| **Data** | CSV import and export for everything, with de-duplication |
| **Integrations** | REST API (`estat/v1`), HMAC-signed webhooks, 17 Elementor widgets |
| **Care** | Audit log, cron housekeeping, privacy export/erase, practice mode, maintenance tools |
| **Languages** | English and natural Hinglish (written in normal English letters), extensible via standard `.po`/`.mo` |

---

## Installing

1. Copy the `estat-os` folder into `wp-content/plugins/`.
2. Activate **Estat.OS** in *Plugins*.
3. Open **Office → Office Settings** and fill in your office name, phone,
   currency and area unit.
4. Optional: **Office Settings → Tools → Add sample data** fills the office
   with realistic practice records so you can learn without risk. One click
   removes them all again.

On activation the plugin creates its own tables, roles and scheduled jobs. On
deactivation nothing is removed. On deletion nothing is removed either, unless
you explicitly tick *"Also erase all office data when the plugin is deleted"* in
**Office Settings → Tools**.

---

## The menu

| Screen | What it is for |
| --- | --- |
| **Today** | The morning screen: what needs attention right now |
| **Add a Home** | Nine questions, then save. It never throws you into the WordPress editor |
| **Listings** | Every property, with a three-chapter editor and a readiness score |
| **Enquiries** | The inbox: call, WhatsApp, assign, set a follow-up, book a visit |
| **Site Visits** | The diary, with an outcome for each visit |
| **Societies & Projects** | Buildings and developments you work with |
| **Team** | People, their contact details and their workload |
| **Gallery** | Your photos (the normal WordPress media library) |
| **Insights** | Articles for your website |
| **Forms** | Design forms and read the responses |
| **Spreadsheet** | Import and export CSV files |
| **Reports** | How the office is doing |
| **Office Settings** | Everything else, grouped into six plain tabs |

---

## Key ideas

### Statuses are kept separate

Real offices track several different things at once, so Estat.OS never mixes
them into one "status" dropdown:

| Concept | Example values |
| --- | --- |
| Publication | draft, live on the website |
| Availability | available, under offer, sold, rented |
| Construction | ready to move, under construction, new launch |
| Verification | not checked, checked by the office, verified |
| Offer | for sale, for rent, for lease |
| Investment | a simple yes/no flag |

### Numbers are stored plainly, formatted on display

You always type `85000000`. Visitors see `₹8.5 Cr`, `₹8.5M` or the full number,
depending on one setting. Prices marked *on request* are never exposed publicly,
including in the REST API and in structured data.

### Areas are normalised

Type in square feet, square yards or square metres — everything is converted to
square feet behind the scenes, so filters and sorting always behave.

### Readiness, not scolding

Every listing gets a score out of 100 (headline, description, cover photo, three
or more photos, price, area, locality, agent, bedrooms, map position). The editor
shows what is missing and links straight to it.

---

## For developers

See [`docs/`](docs/) for the full reference:

* [`docs/architecture.md`](docs/architecture.md) — how the plugin is put together
* [`docs/rest-api.md`](docs/rest-api.md) — every `estat/v1` route
* [`docs/webhooks.md`](docs/webhooks.md) — events, payloads, signature verification
* [`docs/hooks.md`](docs/hooks.md) — actions and filters
* [`docs/csv.md`](docs/csv.md) — spreadsheet columns
* [`docs/translating.md`](docs/translating.md) — adding a language
* [`docs/elementor.md`](docs/elementor.md) — the widgets

Quick facts: namespace `EstatOS`, prefix `estat_`/`ESTAT_`, text domain
`estat-os`, REST namespace `estat/v1`, custom PSR-4-style autoloader where
`includes/` maps one-to-one to sub-namespaces. Every module is a class with a
`register()` method, instantiated from an explicit map in `Plugin` that you can
filter with `estat_modules`.

## Scale

Designed for roughly ten thousand listings on ordinary shared hosting. Searching
and sorting never touch `postmeta`: a dedicated `estat_index` table holds one flat,
indexed row per listing and is kept in sync automatically. Rebuilding it is a
safe, batched operation available in **Office Settings → Tools**.

## Out of scope

Content-copy protection (disabling right-click, blocking text selection) is
deliberately not included. It does not work, it harms accessibility and it hurts
search rankings.

## Contributing

Issues and pull requests are welcome. The project follows semantic versioning and
WordPress coding standards. Migrations are additive only — no release will ever
drop or rewrite a column that holds your data.
