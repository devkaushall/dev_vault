# Phase 1 Blocker Status

## MySQL
- **Status:** NOT VERIFIED
- **Why open:** No executable MySQL/Docker runtime is available here.
- **Required environment:** Disposable Docker/Compose host.
- **Exact test:** `tools/verification/mysql/run.sh`
- **Expected:** Full lifecycle, schema, checksum, REST, capability, settings, and idempotency checks pass.
- **Current evidence:** Harness and SQLite evidence only.
- **Closure:** Successful JSON evidence from the MySQL harness.

## MariaDB
- **Status:** NOT VERIFIED
- **Why open:** No executable MariaDB/Docker runtime is available here.
- **Required environment:** Disposable Docker/Compose host.
- **Exact test:** `tools/verification/mariadb/run.sh`
- **Expected:** Same independent lifecycle matrix passes.
- **Current evidence:** Harness only.
- **Closure:** Successful MariaDB JSON evidence.

## Migration failure recovery
- **Status:** NOT VERIFIED
- **Why open:** A real database-level controlled failure has not run.
- **Required environment:** Disposable MySQL and MariaDB sites.
- **Exact tests:** `tools/verification/migration-failure/run.sh mysql` and `... mariadb`.
- **Expected:** A remains once, B fails, C is absent, version remains A; retry produces A/B/C once and version C.
- **Current evidence:** Normal migration passes; failure harness prepared.
- **Closure:** PASS result on each declared engine, including checksums/order.

## Uninstall
- **Status:** NOT VERIFIED
- **Why open:** Only deactivation preservation was previously executed.
- **Required environment:** Disposable database-backed WordPress, including disposable multisite.
- **Exact tests:** `tools/verification/uninstall/run.sh mysql` and `... mariadb`.
- **Expected:** Preserve cases retain data; explicit purge removes only plugin data; unrelated data survives; multisite refuses purge.
- **Current evidence:** Source policy and deactivation checks.
- **Closure:** Complete matrix result JSON passes.

## Security
- **Status:** NOT VERIFIED
- **Why open:** Database failure and destructive lifecycle runtime evidence is incomplete.
- **Required environment:** Same disposable database environments.
- **Exact tests:** security, migration-failure, and uninstall harnesses plus `PHASE-1-SECURITY-EVIDENCE.md` review.
- **Expected:** Authorization/input/logging safeguards and lifecycle protections all pass.
- **Current evidence:** Existing Playground security checks and source audit.
- **Closure:** Automated results plus completed evidence checklist.

## Real Mayfair compatibility
- **Status:** NOT VERIFIED
- **Why open:** Real proprietary artifacts are unavailable.
- **Required environment:** Licensed/authorized disposable site and artifacts.
- **Exact test:** Capture structures/state before and after activation using the fixture assertions as the preservation contract.
- **Expected:** No duplicate registration, replacement, ID/option/route/workflow change.
- **Current evidence:** Fixture Compatibility PASS only.
- **Closure:** Real-artifact evidence with exact versions.

## GitHub Actions
- **Status:** NOT VERIFIED
- **Why open:** No authenticated repository execution context.
- **Required environment:** Authenticated `gh`, configured remote, workflow permissions.
- **Exact test:** `tools/verification/ci/run.sh`
- **Expected:** All PHP matrix jobs and package artifact pass.
- **Current evidence:** Workflow configuration inspection.
- **Closure:** Commit SHA, run ID/URL, jobs, and successful conclusion.

## Gate

Phase 1 remains **FAIL**. Phase 2 remains **LOCKED**.
