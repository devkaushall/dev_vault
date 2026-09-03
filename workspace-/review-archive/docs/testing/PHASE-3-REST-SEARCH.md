# Phase 3 REST Search Verification

Executable WordPress REST dispatch verified anonymous public access, stable response shape, empty results, keyword, city, price, bedrooms, developer, featured, verified, taxonomy and combined filters; all ten approved sorts; page 1/2/last/beyond-last pagination; and explicit exclusion of draft, pending, private and trash Properties.

Negative cases returned structured 4xx responses for invalid pages/page sizes/ranges/sorts, unknown fields, wrong scalar shape, invalid booleans/terms/projects, nulls, negative numbers, nested pollution, XSS and SQL-style payloads. Before/after canonical and projection snapshots were equal.

At 100 Properties, one REST request used two database queries, returned 20 bounded results and took 0.012 seconds on PHP 8.3/WordPress 6.4.10/SQLite. This is smoke evidence, not final MySQL qualification.

Evidence: `verification-results/phase3-rest-search.json`, `phase3-rest-ajax-performance.json`.
