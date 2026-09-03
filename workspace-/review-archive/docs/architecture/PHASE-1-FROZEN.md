# Phase 1 Frozen Foundation

**Status:** release candidate, frozen 2026-08-30  
**Plugin version:** 0.1.0  
**Schema version:** 001

## Source structure

- `realestate-platform.php` — guarded composition entry point and lifecycle hook registration.
- `src/Core/` — bootstrap, environment checks, autoloading, lifecycle, and service registry.
- `src/Contracts/` — database, settings, logging, diagnostics, and migration boundaries.
- `src/Database/` and `src/Migration/` — WordPress database adapter and forward migration runner.
- `migrations/` — versioned foundation DDL; Phase 1 creates only `{prefix}rep_schema_migrations`.
- `src/Capabilities/`, `Settings/`, `Diagnostics/`, `REST/`, `Security/`, `Privacy/`, `Logging/`, and `Compatibility/` — bounded foundation services.
- `tests/` — unit tests and an honestly skipped native-integration placeholder.
- `tools/verification/` — disposable external verification harnesses.

## Locally verified capabilities

PHP syntax, PHPStan level 8, WordPress-Core PHPCS, PHPUnit, SQLite lifecycle, activation/deactivation/reactivation, migration/checksum/idempotency, typed settings, fine-grained capabilities, diagnostics, authenticated REST status, privacy hooks, fixture compatibility, PHP 8.1–8.3, clean ZIP installation, and reproducible packaging are green. Runtime evidence uses WordPress 6.4.10 Playground.

## External blockers

MySQL 8.4, MariaDB 11.4, real database migration-failure recovery, the complete uninstall matrix, dependent security closure, real Mayfair Core/Forms & Leads compatibility, and external GitHub Actions execution remain NOT VERIFIED. See `docs/testing/PHASE-1-EXTERNAL-BLOCKERS.md`.

## Freeze rules

Without a reproduced defect, do not alter Phase 1 contracts, migration/capability/settings architecture, PHPStan or PHPCS configuration, passing tests, package logic, public identifiers, option names, table names, REST route, compatibility behavior, or uninstall policy. Any proven defect requires the smallest compatible correction, a complete local regression, a rebuilt/retested ZIP, updated evidence, and explicit review of backward compatibility.

This freeze is not a production-readiness declaration and does not authorize Phase 2.
