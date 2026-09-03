# Phase 8 performance and integrity verification

**Release:** 0.8.0  
**Environment:** WordPress 6.4.10, PHP 8.3.32, SQLite Playground  
**Result:** bounded local adapter checks PASS; 100k/native production benchmark **NOT VERIFIED**

## Measured Phase 8 fixture

The fixture creates 100 published Property posts and 100 published Agents and Agencies, then exercises the bounded adapters.

| Adapter | Returned | Queries | Seconds | Memory delta | Limit |
|---|---:|---:|---:|---:|---:|
| Property canonical query | 100 | 6 | 0.056 | 2 MiB | 100 |
| Agent public query | 100 | 5 | 0.022 | 0 MiB | 100 |
| Agency public query | 100 | 5 | 0.022 | 0 MiB | 100 |

Fixture setup took approximately 10.3 seconds in constrained WASM. Memory is measured as the per-query `memory_get_usage(true)` delta in the final 8.3 run. The result is a contract/bounds check, not a production SLO or a 100,000-record benchmark.

## Controls

- SearchRequest owns canonical Property pagination and result selection.
- Query adapter inputs and per-page values are bounded; no unbounded Elementor query is accepted.
- Property IDs are unique and passed as `post__in`; failures become an empty set.
- Entity adapters force public post type/status and clamp page sizes.
- Dynamic values are resolved per context and do not precompute or cache private data.
- No adapter writes SQL, indexes, workflow tables, or documents.
- Notification delivery remains asynchronous and isolated by Phase 7.

## Regression performance

Phase 2–7 search, geo, index, profile, user-feature, and workflow performance scripts were rerun against the Phase 8 source. Existing 1,000-property/index and bounded user-feature fixtures passed; native MySQL/MariaDB and production-scale benchmarks remain **NOT VERIFIED**.

Evidence: `verification-results/phase8-runtime-8.3.json`, `verification-results/phase3-performance-final.json`, `verification-results/phase4-performance.json`, `verification-results/phase5-performance.json`, and `verification-results/phase6-performance.json`.
