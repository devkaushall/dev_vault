# Phase 6 Performance

Environment: WordPress 6.4.10, PHP 8.3, SQLite/WASM.

Executed separate bounded listings after deterministic 100-record fixture creation:
- Agents, 10 returned: 3 queries; exact time/memory in `phase6-performance.json`.
- Agents, 100 returned: 3 queries.
- Agencies, 10 returned: 3 queries.
- Agencies, 100 returned: 3 queries.

Stable query counts across 10 and 100 indicate WordPress object/meta cache priming avoids an SQL N+1 pattern. Pagination is capped at 100. Results do not establish native MySQL/MariaDB performance.
