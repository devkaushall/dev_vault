# Phase 3 AJAX Search Verification

The registered authenticated and anonymous AJAX actions use `SearchAjaxController`, which verifies the `realestate_platform_search` nonce then delegates to the shared `SearchRequest`. Missing/invalid nonces return structured 403 errors; malformed criteria return the shared structured 400 error. No SQL or rendering exists in the adapter.

Valid filtering/pagination, missing nonce, invalid nonce, malicious sort handling, read-only snapshots, and exact REST/AJAX transport-equivalent payloads passed. At 100 Properties, AJAX used two database queries, returned 20 bounded results and took 0.007 seconds on SQLite.

Evidence: `verification-results/phase3-ajax-search.json`, `phase3-rest-ajax-performance.json`.
