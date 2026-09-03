# Phase 9 performance report

**Version:** 0.9.0  
**Execution date:** 2026-09-03  
**Environment:** WordPress 6.4.10 / SQLite / PHP 8.1.34, 8.2.32, 8.3.32; 256 MiB PHP limit in Playground.

## 1. Scope and method

The Phase 9 runner creates synthetic in-memory JSON batches of 10, 100, and 1,000 Property rows and runs the complete bounded `dry_run` path, including parse, normalization, allowlist/type checks, identity planning, and report construction. It records elapsed wall time, allocator delta for each batch, the complete harness process peak, row count, and status. No persistent rows are created by these benchmark calls.

This is a contract/budget benchmark, not a production capacity claim. The acceptance threshold in the harness is a successful 1,000-row dry-run below 30 seconds. The parser hard limits remain 16 MiB source bytes, 10,000 rows, 128 columns, and 65,535 bytes per cell. Export output is capped at 32 MiB; `writeFile()` is the streaming-oriented file path, while the `content()` convenience method materializes its bounded result before checking size.

## 2. Current observations

| PHP | 10 rows | 100 rows | 1,000 rows | 1,000-row allocator delta | complete harness process peak |
|---|---:|---:|---:|---:|---:|
| 8.1 | 0.075 s | 0.711 s | 6.545 s | 6,291,456 bytes | 46,137,344 bytes |
| 8.2 | 0.069 s | 0.703 s | 6.632 s | 2,097,152 bytes | 46,137,344 bytes |
| 8.3 | 0.076 s | 0.798 s | 6.816 s | 2,097,152 bytes | 46,137,344 bytes |

All three runtimes returned **PASS** for all 10/100/1,000-row runs. The machine-readable source is `verification-results/phase9-runtime-matrix.json`; the clean package observations are in `verification-results/phase9-package-runtime-matrix.json`.

The reported process peak is `memory_get_peak_usage(true)` for the full Playground request, not a production limit. Per-batch deltas are allocator observations and can be zero because PHP reuses allocated arenas. They should not be interpreted as a precise isolated peak for every batch.

## 3. Failure behavior under load

- source over 16 MiB is rejected before parsing/planning;
- row count over 10,000, column count over 128, cell over 65,535 bytes, invalid UTF-8, malformed CSV/JSON, deep JSON, or duplicate normalized keys is rejected;
- output over 32 MiB is deleted and reported as a bounded write failure;
- any complete-plan finding prevents all execute writes and accounts for clean planned rows as skipped; and
- an execution failure is reported and the affected row is compensated where its snapshot/created media permit.

## 4. Unverified scale and environment

100,000-row import/export, production object caches, concurrent workers, native MySQL/MariaDB, external storage, and real remote-media providers were not available and remain **NOT VERIFIED**. The 10,000-row source ceiling is a safety bound, not a claim that a 10,000-row execute is a production SLO. A production deployment should add an authorized durable job/checkpoint design before accepting process-independent long-running jobs.
