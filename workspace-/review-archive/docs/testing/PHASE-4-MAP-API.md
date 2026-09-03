# Phase 4 Map API Verification

**PASS.** `GET /realestate-platform/v1/properties/map` requires radius or complete viewport, uses the shared search engine, caps markers at 100, primes caches to avoid N+1, applies property/global coordinate privacy, excludes hidden/missing/non-public Properties, and returns explicit marker JSON, pagination, applied filters and clusterability. AJAX geo requests use the existing nonce-protected shared adapter.

Evidence: `verification-results/phase4-hardening.json` and `phase4-performance.json`.
