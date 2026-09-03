# Migration Failure Recovery Verification

## Environment
WordPress Playground/SQLite is available. Disposable MySQL and MariaDB services are unavailable.

## Command/Test
Required database scenario: A succeeds; B performs an intentional database-level failure; C must not run. Inspect migration records, schema version, checksums, logs, retry behavior, and duplicate prevention before and after a documented recovery.

## Expected result
A remains recorded once; B does not falsely succeed; C does not execute; schema version does not advance past A; retry is deterministic; checksums remain correct; recovery instructions restore a runnable state without claiming automatic rollback.

## Actual result
Normal migration `001`, its 64-character checksum, repeated activation, and duplicate prevention pass under SQLite. The required A/B/C database-failure scenario was not executed on a declared database engine. Exception-path inspection is not substituted for database execution.

## Result
**NOT VERIFIED**

## Recovery procedure pending verification
Restore from the pre-migration database backup or apply the migration-specific compensating action, correct the cause of B, and rerun activation/migrations. Confirm A is skipped, B executes once, C follows only after B succeeds, and the recorded schema version/checksums match the files. This procedure remains unverified until exercised.

## Evidence
- `plugins/realestate-platform/src/Migration/MigrationRunner.php`
- `plugins/realestate-platform/migrations/001_initial.php`
- `verification-results/php-8.3.json`
