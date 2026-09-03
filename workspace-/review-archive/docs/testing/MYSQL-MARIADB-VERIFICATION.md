# MySQL and MariaDB Verification

## Environment
Host inspection and the established local-environment report confirm that native PHP, Docker, Docker Compose, WP-CLI, MySQL, and MariaDB runtimes are unavailable. WordPress Playground provides SQLite only.

## Command/Test
Required plan: provision clean disposable WordPress installations separately on MySQL and MariaDB; install the production ZIP; exercise activation, `rep_schema_migrations`, checksums, settings, capabilities, diagnostics, REST, repeated activation, deactivation, reactivation, and uninstall.

## Expected result
Each engine must independently create and preserve the intended schema, record migration `001` once with its checksum, avoid duplicates, and complete the lifecycle without SQL errors.

## Actual result
The required engines could not be started in this workspace. No engine commands were executed and SQLite evidence was not reused.

## Result
- MySQL: **NOT VERIFIED**
- MariaDB: **NOT VERIFIED**

## Evidence
- `docs/testing/LOCAL-VERIFICATION-ENVIRONMENT.md`
- `verification-results/php-8.1.json`
- `verification-results/php-8.2.json`
- `verification-results/php-8.3.json`

The JSON files establish SQLite/Playground behavior only.
