# Phase 4 Final Checkpoint

## Completed / PASS

Coordinate model/validation; radius/bounds/dateline; geo plus Phase-3 filters; privacy; marker factory/serialization; lifecycle; map REST; geo AJAX; canonical URL compatibility; map/list state; provider abstraction; null geocoder; secure HTTP and SSRF; typed settings; diagnostics; security; data integrity; SQLite performance through 1,000; full available Phase-2/3/4 regression; PHPCS; PHPUnit; PHP 8.1/8.2/8.3 syntax/runtime; production ZIP; clean extracted-ZIP verification; byte reproducibility.

## Failures fixed

- Geo performance harness incorrectly treated `SearchPage` as an array; corrected and rerun.
- Marker serialization replaced implicit object-state export with an explicit public allowlist.
- MarkerFactory now rejects non-finite/out-of-range canonical coordinates and strips marker-title markup.
- Marker retrieval now primes post/meta caches, reducing bounded marker retrieval to three queries.
- Canonical radius URL serialization now translates internal kilometre fields back to transport parameters.

## NOT VERIFIED / external blockers

Phase 1 external verification; native MySQL/MariaDB; native WP-CLI; actionable PHPStan completion; live geocoder provider; browser/UI/accessibility; real Mayfair; real ACF.

## Package

`dist/realestate-platform-0.4.0.zip`, 60,519 bytes, 72 files, SHA-256 `c5d57be0d54fb492e808974c361c9f697c631cb485ecd0f7dc6071eb905edbd7`.

## Evidence

`verification-results/phase4-final.json`, `phase4-geo-core.json`, `phase4-hardening.json`, `phase4-secure-http.json`, `phase4-performance.json`, plus refreshed prior-phase and PHP runtime evidence.

## Gate

Phase 4 PASS. Phase 5 LOCKED and not started.
