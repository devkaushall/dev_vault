# Phase 3 Search Core

Status: PASS for the executed PHP 8.3 / WordPress 6.4.10 / SQLite scope.

Evidence: `verification-results/phase3-search-core.json`.

The database provider searches the disposable property and taxonomy projections while joining canonical posts to reassert `property` and `publish`. Different dimensions combine with AND; each requested taxonomy term is required (AND). Numeric minimum/maximum bounds are inclusive. Keyword matching uses the index writer's allowlisted title, excerpt, content, searchable canonical fields, and taxonomy labels. Sorts are closed, deterministic presets. Pagination defaults to 20 and is capped at 100.

Executed checks cover published-only visibility, city, inclusive price range, combined city/bedrooms, title/reference/developer keywords, multiple taxonomy dimensions, ascending price sorting, pagination, invalid page, excessive page size, inverted range, injected sort, unknown parameters, and complete canonical read-only comparison.

Current canonical Phase-2 fields marked searchable are projected into keyword text. Filterable fields with dedicated approved projection columns are queryable. A Property-to-Project canonical relationship does not exist in Phase 2, so project filtering is not exposed rather than inventing data. No arbitrary postmeta or client-provided SQL identifier is queried.

Known qualification: formal performance, cross-PHP, PHPStan, lifecycle, REST, AJAX, CLI, diagnostics, and package gates belong to later Phase-3 checkpoints.
