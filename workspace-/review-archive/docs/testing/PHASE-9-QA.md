# Phase 9 QA report

**Version:** 0.9.0  
**Execution date:** 2026-09-03  
**Environment:** WordPress Playground, WordPress 6.4.10, SQLite, PHP 8.1.34 / 8.2.32 / 8.3.32 as shown by each result.

## 1. Release-quality results

| Gate | Result | Evidence |
|---|---|---|
| Phase 9 runtime matrix | **PASS** | `verification-results/phase9-runtime-matrix.json` |
| dedicated security harness | **PASS** | `verification-results/phase9-security-8.3.json` |
| dedicated privacy harness | **PASS** | `verification-results/phase9-privacy-8.3.json` |
| dedicated data-integrity harness | **PASS** | `verification-results/phase9-integrity-8.3.json` |
| PHP 8.1 syntax | **PASS**; 2,806 files | `verification-results/php-syntax-8.1.json` |
| PHP 8.2 syntax | **PASS**; 2,806 files | `verification-results/php-syntax-8.2.json` |
| PHP 8.3 syntax | **PASS**; 2,806 files | `verification-results/php-syntax-8.3.json` |
| PHPUnit | **PASS**; 44 tests, 341 assertions, 1 skip | `verification-results/phpunit-8.3.log` |
| PHPCS/WPCS | **PASS** | `verification-results/phpcs-8.3.log` |
| PHPStan | **NOT VERIFIED** | `verification-results/phpstan-8.3-256M.log` |
| final package reproducibility | **PASS**; two byte-identical builds | `verification-results/phase9-package.json` |
| clean extracted-package runtime | **PASS** on PHP 8.1–8.3 | `verification-results/phase9-package-runtime-matrix.json` |

## 2. PHPStan qualification

PHPStan was reattempted with the unchanged project configuration and a 256 MiB Playground limit. It terminated at `phar:///workspace/plugin/vendor/phpstan/phpstan/phpstan.phar/src/File/FileReader.php` with `Allowed memory size of 268435456 bytes exhausted`. This is **NOT VERIFIED**, not a code-quality PASS or FAIL. The configuration was not weakened, findings were not hidden, and no broad exclusion was added. Higher-memory attempts are also not treated as a substitute for the declared environment evidence.

## 3. Phase 9 runtime checks

Each PHP runtime passed the following checks:

- services composed from the canonical registry;
- validation/dry-run zero mutation;
- create and approved taxonomy assignment;
- deterministic upsert;
- visible create-only conflict;
- private-field rejection without a created post;
- missing-term dry-run isolation and explicit opt-in creation;
- supported relationship consistency;
- byte-stable CSV and JSON export;
- formula-injection and private-data exclusion;
- unsafe remote-media handling without a download;
- bounded 10/100/1,000-row execution; and
- no Phase 10 implementation path.

The current runtime benchmark observations were:

| PHP | 10 rows | 100 rows | 1,000 rows | observed process peak |
|---|---:|---:|---:|---:|
| 8.1 | 0.075 s | 0.711 s | 6.545 s | 46,137,344 bytes |
| 8.2 | 0.069 s | 0.703 s | 6.632 s | 46,137,344 bytes |
| 8.3 | 0.076 s | 0.798 s | 6.816 s | 46,137,344 bytes |

The process peak is the Playground PHP allocator observation for the complete harness, not a production SLO or a claim of constant-memory JSON `content()` exports. The streaming-oriented `writeFile()` path remains the bounded file path.

## 4. Regression result

After the latest Phase 9 edits, Phase 2–8 source component regressions were executed on their available local harnesses. Phase 2 REST was exercised on PHP 8.1–8.3; Phase 6, Phase 7, and Phase 8 were exercised on PHP 8.1–8.3; Phase 3–5 focused search, geo, user-feature, alert, migration, security, and performance scripts passed on PHP 8.3. The exact machine-readable results are in `verification-results/` and are summarized in `PHASE-9-FINAL-VERIFICATION-REPORT.md`.

The historical Phase 3 final-report wrapper expects a retired `dist/realestate-platform-0.3.0.zip` artifact and therefore was not used as the Phase 9 gate; its underlying Phase 3 component checks passed. The final Phase 9 package is independently rebuilt and tested below.

## 5. Availability qualifications

The following remain NOT VERIFIED: native MySQL/MariaDB, native WP-CLI binary execution, real Mayfair or ACF mappings, real Elementor/Elementor Pro/editor/browser rendering, remote-media success against an allowed public provider, external CI, external notification providers, production infrastructure, and 100k-scale performance. Local fake vendor classes are contract fixtures only.
