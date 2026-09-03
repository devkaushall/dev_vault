# Phase 9 test plan

**Version:** 0.9.0  
**Date:** 2026-09-03  
**Objective:** verify the bounded provider-neutral CSV/JSON import/export subsystem without weakening prior phase contracts.

## 1. Acceptance coverage

| Requirement | Primary executable coverage | Expected result |
|---|---|---|
| bounded CSV parsing | `SourceParser`, `tests/Unit/ImportExportTest.php`, `phase9-security-runner.php` | malformed/oversized/invalid UTF-8 input rejected |
| bounded JSON parsing | `SourceParser`, unit test, security runner | malformed/deep/non-object/duplicate normalized keys rejected |
| normalized row DTO | `ImportRow`, `ImportService::normalizeRow()` | typed row data, bounded errors/warnings, no arbitrary columns |
| allowlist/type validation | `SchemaCatalog`, registry tests, Phase 9 runner | only canonical entities/fields/taxonomies accepted |
| zero-mutation validation | Phase 9 runner, integrity runner | `mutation=NONE`; post/meta/terms unchanged |
| zero-mutation dry-run | Phase 9 runner, integrity runner | planned decisions visible; no editorial or operational mutation |
| deterministic create/update | Phase 9 runner, integrity runner | exact ID/slug/reference identity; stable retry |
| create-only conflict visibility | Phase 9 runner | conflict row and count, no replacement |
| update-only behavior | `ImportService` option/plan paths and unit coverage | missing identity is a visible conflict |
| taxonomy resolution | Phase 9 runner and integrity runner | existing terms resolve; missing terms require explicit opt-in |
| relationship validation | Phase 9 runner and integrity runner | only existing Agent → Agency and Property → Agent/Agency links |
| media validation | Phase 9 runner, security runner | existing attachments validate; unsafe remote media is reported |
| canonical writes | Phase 9 runner and package runner | WordPress editorial APIs/ProfileService only |
| failure isolation | integrity runner | invalid complete batch has zero partial mutation |
| recovery/retry | integrity runner | deterministic rerun reuses identity and preserves content |
| deterministic export | Phase 9 runner and privacy runner | stable field order, row order, byte-equal repeated content |
| formula injection | Phase 9 runner and privacy runner | formula-leading CSV values are prefixed |
| export privacy | privacy runner | private/editorial workflow data is absent |
| export authorization | privacy/security runners | nonpublic output requires `manage_realestate` |
| path/SSRF security | security runner | traversal, unsafe ports, loopback/private-network URLs rejected |
| bounded processing | parser limits and runner | source/row/column/cell/output bounds enforced |
| 10/100/1,000 rows | Phase 9 runtime matrix | all sizes pass under 30-second dry-run threshold |
| package install | package build + `phase9-package-install.mjs` | clean extracted ZIP passes PHP 8.1–8.3 |
| no Phase 10 | Phase 9 runner/package runner | no `src/Phase10` path and no Phase 10 implementation |

## 2. Focused test assets

- `plugins/realestate-platform/src/ImportExport/` — parser, DTO/report, schema, planner/executor, serializer, file/remote-media services.
- `plugins/realestate-platform/tests/Unit/ImportExportTest.php` — report accounting and duplicate normalized JSON-key unit coverage.
- `scripts/phase9-runner.php` — end-to-end import/export, dry-run, taxonomy, relationship, privacy/formula, unsafe media, performance, and memory observations.
- `scripts/phase9-security-runner.php` — capability/IDOR, parser boundary, path, SSRF, static direct-write, REST-surface, and serialized-payload checks.
- `scripts/phase9-privacy-runner.php` — public/nonpublic export and private-field boundary checks.
- `scripts/phase9-integrity-runner.php` — rollback/no-partial-mutation, skipped-row, deterministic retry, relationship, and operational-table isolation checks.

## 3. Quality and regression matrix

| Layer | Command/evidence | Matrix |
|---|---|---|
| PHP syntax | `PHP_VERSION=8.1/8.2/8.3 node scripts/php-syntax-check.mjs` | 2,806 PHP files per runtime |
| PHPUnit | `PHP_VERSION=8.3 node scripts/phpunit-playground.mjs` | 44 tests, 341 assertions, 1 documented integration skip |
| PHPCS/WPCS | `PHP_VERSION=8.3 node scripts/phpcs-playground.mjs` | configured source, no findings |
| PHPStan | `PHP_VERSION=8.3 PHP_MEMORY=256M node scripts/phpstan-playground.mjs` | NOT VERIFIED on unchanged config due memory exhaustion |
| WordPress runtime | `node scripts/phase9.mjs` | PHP 8.1, 8.2, 8.3 / WordPress 6.4 / SQLite |
| security | `node scripts/phase9-security.mjs` | PHP 8.3 / WordPress 6.4 / SQLite |
| privacy | `node scripts/phase9-privacy.mjs` | PHP 8.3 / WordPress 6.4 / SQLite |
| integrity | `node scripts/phase9-integrity.mjs` | PHP 8.3 / WordPress 6.4 / SQLite |
| clean package | `node scripts/phase9-package-install.mjs` | extracted artifact on PHP 8.1, 8.2, 8.3 |
| prior phases | Phase 2–8 component scripts | current source regression; evidence recorded in `verification-results/` |

## 4. Test policy

A test is PASS only when the executable result is available and the result is green. A missing vendor, browser, native database, native WP-CLI, provider, or external integration is NOT VERIFIED—not a synthetic PASS. Fixture classes in the harness prove only REP-owned contracts and are not compatibility evidence for Mayfair, ACF, Elementor, or Elementor Pro.

No production/customer data, credentials, secrets, or private Mayfair artifacts are used. Tests create disposable WordPress users, posts, terms, and attachments inside isolated Playground databases.
