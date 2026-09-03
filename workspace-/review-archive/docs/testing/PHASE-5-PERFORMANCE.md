# Phase 5 Performance

SQLite/WASM measurements are in `verification-results/phase5-performance.json`. Executed scales: 10 and 100 users. At 100 users, favorite listing took 0.488 seconds and 200 queries total (two bounded queries per user). Ten saved-search creates took 0.084 seconds/41 queries. Empty scheduler scan took 0.002 seconds/one query. Scale 1,000 is NOT VERIFIED in the constrained WASM environment. These are not native MySQL/MariaDB claims.
