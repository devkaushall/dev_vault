# Phase 3 Search Architecture

**Status:** approved implementation design, 30 August 2026. Phase 1 remains frozen and its external verification remains NOT VERIFIED. Phase 2 is the canonical content source.

## Boundaries

Phase 3 adds a reusable, read-only Property search projection and adapters. It does not add maps, favorites, saved searches, compare, agents/agencies, leads/forms, visits, imports, commerce, MLS/RESO, PDF, analytics, or advanced Elementor widgets.

```text
URL / REST / AJAX / WP-CLI
        | translate + validate
SearchCriteria -> SearchQuery -> SearchProvider
                              -> indexed SearchIndexRepository
                              -> SearchResult
Canonical Property events -> SearchIndexBuilder -> projection + cache invalidation
Diagnostics / CLI status  -> SearchIndexRepository consistency report
```

Adapters never build SQL. `SearchCriteria` is an immutable normalized value object. `SearchQuery` coordinates execution and caching. `SearchProvider` is the replaceable query port. `SearchIndexRepository` is the WordPress database adapter. Result DTOs contain data, never HTML.

## Projection tables

### `{prefix}rep_search_properties`

Purpose: one disposable row per publicly searchable Property, covering scalar ranges, sorting, common location dimensions, keyword material, and coordinates without repeated postmeta joins.

Columns:

- `post_id` bigint unsigned primary key; relationship to the canonical Property post.
- `post_modified_gmt` datetime; freshness comparison.
- `title`, `slug`; public identity/result hydration support.
- `keyword_text` text; normalized allowlisted title, excerpt, content, reference, developer, and location labels only.
- `reference`, `country`, `state`, `city`, `locality`, `neighborhood`, `postal_code`, `currency`, `developer`, `rera`, `furnishing`, `possession`, `availability`, `construction_status`.
- Numeric canonical columns: `price`, `area`, `plot_area`, `bedrooms`, `bathrooms`, `floors`, `floor`, `parking`, `latitude`, `longitude`, `project_id`.
- Boolean columns: `featured`, `verified`.
- `indexed_at` datetime.

Indexes: primary `post_id`; B-tree indexes for modified/freshness, price, area, bedrooms, bathrooms, featured, verified, project, country/state/city, developer, and common sort combinations. Coordinates are ordinary bounded numeric columns in Phase 3; no spatial or radius search is implemented.

### `{prefix}rep_search_terms`

Purpose: rebuildable bridge for only the allowlisted Phase-2 Property taxonomies.

Columns: `post_id`, `taxonomy`, `term_id`; composite primary key prevents duplicates. Secondary indexes `(taxonomy,term_id,post_id)` and `(post_id,taxonomy)` support filtering and cleanup.

Relationships are logical and rebuildable; canonical posts/terms remain authoritative. No presentation-formatted price or complete Property payload is duplicated.

## Extensible custom fields

Only `FieldRegistry` definitions marked searchable/filterable/sortable are projected. Common dimensions use typed columns. Newly approved searchable string fields contribute only to allowlisted keyword material unless a versioned migration adds a typed column. Unknown postmeta is never scanned. Arbitrary user-selected meta keys are impossible.

## Lifecycle

Synchronous single-property projection is triggered after canonical Property save/status transition and taxonomy assignment. Published, publicly searchable Properties are upserted; draft, pending, private, trash, and deleted Properties are removed. Cache generation changes after each successful projection mutation. Hooks must guard revisions, autosaves, unsupported post types, and recursion.

A rebuild truncates/reconciles only disposable projection rows, processes published Property IDs in bounded batches, upserts idempotently, removes orphan/stale rows, records failures without sensitive values, and reports totals. It never mutates canonical posts, metadata, terms, or media. Frontend requests never trigger a rebuild.

## Query semantics

Different filter dimensions combine with AND. Multiple selected terms within `property_feature` and `property_amenity` use AND (a Property must contain every requested term); multiple values for other single-choice taxonomies use OR within that dimension. Minimum and maximum bounds are inclusive. Empty optional parameters are absent. Invalid types, negative values where forbidden, inverted ranges, unknown fields, terms, sorts, orders, nested values, and excessive lengths are structured HTTP 400 errors.

Keyword priority is title, reference, developer, location labels, excerpt, then content. Initial relevance uses deterministic weighted matching supported by the repository; newest ID/date is a stable tie-breaker. No arbitrary SQL fragment is accepted.

## Public result contract

Each `SearchResult` contains: public post ID, title, slug, URL, featured-image public URL/ID as deliberately exposed, canonical numeric price/currency, normalized location summary, allowlisted taxonomy summaries, bedrooms, bathrooms, area, featured, and verified. It contains no raw metadata envelope, author/private fields, internal notes, or HTML.

Only published Properties enter or return from public search. Repository queries reassert canonical `post_type='property'` and `post_status='publish'` so a stale index cannot expose private content.

## Pagination and sorting

Pages are one-based; default `per_page` is 20 and maximum is 100. Offset is internal and bounded from page/per-page; unlimited results are prohibited. Responses contain total, total_pages, current_page, and per_page. Sort keys are a closed enum: relevance, newest, oldest, price_asc, price_desc, area_asc, area_desc, bedrooms, featured, verified.

## Cache

A small interface accepts canonical query hash, result, TTL, and generation. Public results only may use shared object cache/transients. The generation changes on index mutation/rebuild, avoiding wildcard deletion. No user-specific/private search is globally cached.

## REST and AJAX

Canonical REST namespace is `realestate-platform/v1`; endpoint is `GET /properties`. It is public but returns only published content. Route arguments are explicitly registered and validated. AJAX actions are thin adapters over the same criteria factory and query service. Public read-only AJAX uses a nonce when initiated by bundled same-origin clients; absence of a nonce does not create a separate data privilege because the same dataset is public. Rate-awareness consists of bounded input, page size, criteria count, and cache use; no permanent scheduler is introduced.

## Diagnostics and CLI

Diagnostics report table existence, schema version, indexed/published counts, missing, stale, orphaned, failed indexing-event count, and freshness as PASS/WARN/FAIL without mutation. `wp realestate search-index status` returns those values and nonzero exit on FAIL. `rebuild --batch-size=<n>` validates a bounded batch size, reports progress/failures/statistics, is restartable through idempotent upserts, and exits nonzero on failures.

## Migration

Migration `002_search_index.php` creates both tables through the frozen migration engine. It is forward-only, idempotent through `dbDelta`, checksum-recorded by existing migration controls, and contains no backfill. Rebuild is an explicit post-migration operation. Uninstall continues to preserve data by default; deliberate cleanup policy applies to disposable tables.

## Performance qualification

Benchmarks record database engine, fixture size, query count, execution time, memory, and result count for empty, scalar, range, location, taxonomy, custom field, sort, pagination, and combined searches. SQLite measurements do not establish MySQL/MariaDB scalability. A fallback/meta implementation may exist only for controlled diagnostics/compatibility comparison and is never the high-volume default.
