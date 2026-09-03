# Phase 8 QA and regression report

**Run date:** 2026-09-03  
**Source:** RealEstate Platform 0.8.0 / schema 004

## Primary gates

| Gate | Result | Evidence/command |
|---|---|---|
| PHP syntax 8.1 | PASS | `PHP_VERSION=8.1 node scripts/php-syntax-check.mjs`; 2,797 files |
| PHP syntax 8.2 | PASS | `PHP_VERSION=8.2 node scripts/php-syntax-check.mjs`; 2,797 files |
| PHP syntax 8.3 | PASS | `PHP_VERSION=8.3 node scripts/php-syntax-check.mjs`; 2,797 files |
| PHPUnit | PASS | `node run-tool.mjs phpunit --testdox`; 37 tests, 313 assertions, 1 documented skip |
| PHPCS/WPCS | PASS | `node run-tool.mjs phpcs -q --report=summary` |
| PHPStan full project | NOT VERIFIED | `verification-results/phase8-phpstan.json`; 256M and 512M exhausted memory, 1024M process terminated by the constrained WASM runtime |
| Phase 8 contract harness | PASS | `scripts/phase8.mjs`; 40/40 checks on PHP 8.1, 8.2, and 8.3 (`phase8-runtime-8.1/8.2/8.3.json`) |
| Static adapter direct-SQL/workflow scan | PASS | Included in `phase8.mjs` |

The PHPStan configuration was not weakened and no broad ignore was added. The unavailable PHPStan execution is recorded as **NOT VERIFIED**, not PASS.

## Phase 8 contract coverage

The executable fixture covers core-without-Elementor, optional ACF, service boot, all five dynamic contexts, unpublished/missing context behavior, Property/entity query adapters, Pro action registration, valid/replayed/invalid submissions, consent/honeypot/rate-limit paths, privacy/integrity, diagnostics, and adapter performance bounds. All checks in `verification-results/phase8-runtime-8.3.json` are true.

## Prior-phase regression matrix

| Phase | Rerun evidence | Result |
|---|---|---|
| Phase 7 runtime | `phase7-runtime-8.1/8.2/8.3.json` | PASS on all three |
| Phase 7 migration 003→004 | `phase7-migration-upgrade-8.1/8.2/8.3.json` | PASS on all three |
| Phase 6 profile/REST/security/privacy/integrity/performance | `phase6-runtime-8.1/8.2/8.3.json` | PASS on all three |
| Phase 5 foundation/contracts/migration/alerts/performance | `phase5-*.json` | PASS |
| Phase 4 geo/hardening/HTTP/performance | `phase4-*.json` | PASS |
| Phase 3 search/index/REST/AJAX/CLI/diagnostics/performance/migration | `phase3-*.json` | PASS |
| Phase 2 final and REST contracts | `phase2-final.json`, `phase2-rest-contract.json` | PASS |

The Phase 3 and Phase 5 migration regression scripts were made schema-current so they assert idempotency and preserve migrations 001–003 while accepting the already-authorized schema 004. No source migration was changed.

## Unavailable environments

Real Elementor, Elementor Pro, ACF, browser/editor, Theme Builder, Loop Grid, Mayfair, CI, native WP-CLI, native MySQL/MariaDB, production traffic, and remote delivery remain **NOT VERIFIED**. The fake classes in the contract runner must not be described as real compatibility evidence.
