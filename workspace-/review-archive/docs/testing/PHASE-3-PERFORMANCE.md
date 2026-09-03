# Phase 3 Final Performance Benchmark

**Result: PASS for the locally tested SQLite scope. MySQL/MariaDB and 10,000 rows are NOT VERIFIED.**

Environment: PHP 8.3.32, WordPress 6.4.10, SQLite. Deterministic datasets: 10, 100 and 1,000 published Properties. The 10,000 case was not safely run because observed fixture/rebuild cost would exceed the sandbox execution budget; no 10,000 claim is made.

Every measured search—unfiltered, keyword, city, taxonomy, price range, combined, sorting and page 2—used exactly two database queries at every size, demonstrating no result-size N+1 behavior. At 1,000 rows, timings ranged from 0.011 to 0.015 seconds with zero measured memory delta and bounded 20-row pages.

Rebuild results: 10 = 0.400 s/1 batch; 100 = 3.878 s/2 batches; 1,000 = 36.669 s/14 batches. All had zero failures and zero measured memory delta in this run. Single-row synchronization was 0.019 s. Rebuild remained explicit; searches did not trigger one.

These SQLite measurements do not establish native MySQL/MariaDB query plans, full-table-scan behavior or production SLOs. Projection indexes are defined by migration 002, but native-engine plan analysis remains external debt.

Evidence: `verification-results/phase3-performance-final.json`.
