# Phase 4 Performance

**PASS for PHP 8.3/WordPress 6.4.10/SQLite through 1,000 rows.** Radius, bounds and combined searches used exactly two queries at 10/100/1,000. At 1,000, timings were 0.028/0.015/0.024 seconds. Bounded marker retrieval returned at most 100 markers, used three queries after cache priming, and took 0.035 seconds. Measured memory delta was zero.

The portable haversine predicate computes distance for candidates; no native spatial extension is required. Native MySQL/MariaDB query plans, spatial performance and larger inventory remain NOT VERIFIED and are not inferred from SQLite.

Evidence: `verification-results/phase4-performance.json`.
