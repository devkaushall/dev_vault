# Architecture

Estat.OS is a plain, boring, readable WordPress plugin. That is deliberate:
an office system has to be maintainable by whoever inherits it.

## Ground rules

* Namespace `EstatOS`. Prefix `estat_` for functions, options, hooks and
  database tables; `ESTAT_` for constants.
* Text domain `estat-os`. REST namespace `estat/v1`.
* Every PHP file starts with `declare( strict_types = 1 );` and
  `defined( 'ABSPATH' ) || exit;`.
* No Composer at runtime, no build step, no framework. A small custom
  autoloader maps `EstatOS\Foo\Bar` to `includes/Foo/Bar.php`.
* Storage is canonical and numeric. Formatting is display-only and lives in
  `Support\Format`.
* Migrations are additive only.

## Module pattern

Every module is a class with a `register()` method. `Plugin::register_modules()`
holds one explicit map — no directory scanning, no magic:

```php
$classes = array(
	'i18n'     => I18n\Language::class,
	'settings' => Settings\Settings::class,
	// …
);
$classes = (array) apply_filters( 'estat_modules', $classes );
```

`Admin\Admin` is only added when `is_admin()` is true. `estat_loaded` fires once
every module has registered.

## Directory map

| Path | Responsibility |
| --- | --- |
| `includes/Support/` | Formatting, sanitising, the vocabulary of the domain, practice mode |
| `includes/Settings/` | One option, `estat_settings`, with defaults and sanitising |
| `includes/Security/` | Two roles and nineteen `estat_*` capabilities |
| `includes/Data/` | Post types, taxonomies, meta schema, and one save/read façade per record kind |
| `includes/Install/` | Table schema, activation, deactivation, migrations |
| `includes/Search/` | The flat index table and the query builder that reads it |
| `includes/Leads/` | Enquiries and site visits |
| `includes/Forms/` | Field types, form storage, renderer, submission handling |
| `includes/Notifications/` | Every email the office sends |
| `includes/Webhooks/` | Signed outbound HTTP delivery with retries |
| `includes/Rest/` | The `estat/v1` API |
| `includes/Frontend/` | Public rendering, shortcodes, structured data, fallback templates |
| `includes/Integrations/Elementor/` | Presentation-only widgets |
| `includes/Admin/` | The office screens |
| `includes/Csv/`, `includes/Cron/`, `includes/Privacy/`, `includes/Audit/` | Supporting services |

## Data layer

Content lives in custom post types (`estat_listing`, `estat_project`,
`estat_agent`, `estat_agency`, `estat_document`) and three taxonomies
(`estat_locality`, `estat_feature`, `estat_amenity`).

**All listing writes funnel through `Data\Listings::save()`.** It validates,
sanitises against `Data\Meta::listing_schema()`, writes terms, sets the cover
image, recomputes derived values and records an audit entry. Nothing else in the
plugin writes listing meta directly. If you extend the plugin, do the same.

Operational data lives in eight custom tables because it is high-volume,
queried by shape rather than by ID, and does not belong in `postmeta`:

`estat_leads`, `estat_visits`, `estat_forms`, `estat_submissions`, `estat_index`,
`estat_audit`, `estat_webhook_log`, `estat_import_jobs`.

## The search index

`postmeta` cannot answer "three-bedroom flats under ₹1 crore in these two
localities, cheapest first" at ten thousand rows on shared hosting. So every
listing also has one flat row in `estat_index` holding price, area in square feet,
bedrooms, offer, availability, completeness, post status, a sortable price and a
comma-wrapped list of locality term IDs.

`Search\SearchIndex` keeps it in sync on save, on status change, on term change
and on delete. `Search\Query` only ever reads the index. If the two ever drift,
**Office Settings → Tools → Rebuild the search list** repairs it in bounded
batches, and an hourly cron job repairs a slice automatically.

## Statuses

Six independent axes plus WordPress's own `post_status`, stored separately:
`_estat_offer`, `_estat_availability`, `_estat_construction`, `_estat_verification`,
`_estat_featured`, `_estat_investment`. Collapsing these into one field is the
single most common mistake in property plugins.

## Completeness

`Listings::completeness()` returns `{ score, missing: [ { field, label } ] }`
out of 100: headline 10, description 10, cover photo 15, three or more photos 10,
price 15, area 10, locality 15, agent 5, bedrooms 5 (automatically passed for
plots, land and commercial units), map position 5.

## Admin

One top-level menu (`estat-today`) with an explicit slug → capability map. Screens
are static `render()` classes under `includes/Admin/Screens/`. Shared markup
lives in `Screens\Partials`.

Every write goes through one router, `Admin\Actions::handle()`, hooked to
`admin_init`. Each action has its own nonce and its own capability, and always
ends in a redirect carrying a `estat_notice` key — so refreshing a page can never
repeat an action.

## Frontend

`Frontend\Frontend` renders through theme templates when the theme provides them
and falls back to six purpose-built templates otherwise. `Frontend\Components`
holds reusable pieces. Favourites and compare are client-side only
(`localStorage`), so they work on cached pages.

## Security posture

* Capability check *and* nonce check on every write.
* All output escaped at the point of echo.
* All SQL prepared; table names interpolated only from `Schema::table()`.
* Uploaded CSV files are stored outside the web root's reach with an
  `.htaccess` deny and an `index.php` guard.
* Webhooks sign `timestamp . "." . rawBody` with HMAC-SHA256.
* Anonymous REST endpoints are rate limited; form submissions are additionally
  rate limited per visitor and de-duplicated with an idempotency key.
