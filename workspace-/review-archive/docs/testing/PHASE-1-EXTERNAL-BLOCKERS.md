# Phase 1 External Blocker Register

## MySQL 8.4
- **Status:** NOT VERIFIED
- **Why unavailable:** no `docker` executable or native MySQL/PHP/WP-CLI stack exists in this sandbox.
- **Required environment:** Docker Engine + Compose v2 with permission to run official MySQL 8.4, WordPress 6.4.10/PHP 8.3, and WP-CLI containers.
- **Harness:** `REALESTATE_VERIFICATION_DISPOSABLE=YES tools/verification/mysql/run.sh`
- **Expected:** generated lifecycle and normal-uninstall JSON pass with exact PHP, WordPress, plugin, and database versions.
- **Evidence:** `evidence/phase-1/database/mysql/`
- **Closure:** real run exits zero and all generated checks pass.

## MariaDB 11.4
- **Status:** NOT VERIFIED
- **Why unavailable:** no Docker or native MariaDB service; MySQL evidence is not transferable.
- **Required environment:** independent disposable MariaDB 11.4 Compose runtime.
- **Harness:** `REALESTATE_VERIFICATION_DISPOSABLE=YES tools/verification/mariadb/run.sh`
- **Expected/evidence:** independent lifecycle and uninstall PASS JSON in `evidence/phase-1/database/mariadb/`.
- **Closure:** independent real run exits zero.

## Controlled migration failure/recovery
- **Status:** NOT VERIFIED
- **Why unavailable:** the required database-level invalid SQL and retry cannot be established on a declared engine here.
- **Required environment:** both disposable database environments above.
- **Harness:** `REALESTATE_VERIFICATION_DISPOSABLE=YES tools/verification/migration-failure/run.sh mysql` and `... mariadb`.
- **Expected:** A recorded once; B fails and is absent; C does not execute; version remains A; checksums/evidence are present; corrected retry yields A/B/C once and version C.
- **Evidence:** `evidence/phase-1/migration-failure/`
- **Closure:** generated PASS on each declared engine.

## Complete uninstall matrix
- **Status:** NOT VERIFIED
- **Why unavailable:** actual destructive database and multisite scenarios require isolated native database environments.
- **Required environment:** disposable single-site and multisite WordPress on each declared engine.
- **Harness:** `REALESTATE_VERIFICATION_DISPOSABLE=YES tools/verification/uninstall/run.sh mysql|mariadb`.
- **Expected:** preserve cases retain all platform data; incomplete protection refuses purge; explicit purge removes only plugin-owned table/options; unrelated data survives; multisite refuses destructive purge.
- **Evidence:** `evidence/phase-1/uninstall/`
- **Closure:** every generated case passes after actual execution.

## Final security closure
- **Status:** NOT VERIFIED
- **Why unavailable:** database-failure and destructive-lifecycle assertions above lack runtime evidence.
- **Required environment:** successful database, migration, and uninstall environments plus reviewer.
- **Harness:** `REALESTATE_VERIFICATION_DISPOSABLE=YES tools/verification/security/run.sh mysql|mariadb`; checklist `docs/security/PHASE-1-SECURITY-EVIDENCE.md`.
- **Expected:** authorization, malformed input, redaction, SQL, purge, path, URL, and token checks pass without leakage.
- **Evidence:** `evidence/phase-1/security/` and final security audit.
- **Closure:** automated evidence and review checklist complete with PASS.

## Real Mayfair Core and Forms & Leads
- **Status:** NOT VERIFIED (fixture compatibility is separately PASS).
- **Why unavailable:** no authorized installable Mayfair Core or Forms & Leads artifacts are present; no licensed ACF Pro/Elementor Pro artifacts are present.
- **Required environment:** authorized versions installed on a disposable WordPress instance, with representative non-customer test data.
- **Harness:** fixture preservation contract in `tools/verification/mayfair-compatibility/`; repeat state capture using real artifacts.
- **Expected:** CPTs, taxonomies, IDs, settings, REST routes, and lead workflow remain unchanged and unduplicated.
- **Evidence:** `evidence/phase-1/compatibility/`
- **Closure:** exact versions and before/after real-artifact evidence pass.

## GitHub Actions
- **Status:** NOT VERIFIED
- **Why unavailable:** `gh` is absent, the workspace has no `.git` metadata/commit SHA, and no authenticated remote execution context exists.
- **Required environment:** repository checkout, authenticated GitHub CLI, Actions read/write permission, and workflow-enabled branch.
- **Harness:** `tools/verification/ci/run.sh`
- **Expected:** syntax, PHPStan, PHPCS, PHPUnit, and package/artifact jobs pass across PHP 8.1–8.3; run metadata is captured.
- **Evidence:** `evidence/phase-1/ci/`
- **Closure:** real run URL, commit, run ID, job conclusions, and artifact result are recorded.
