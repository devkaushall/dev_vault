# Uninstall Verification

## Environment
WordPress Playground 6.4.10 with SQLite is available. A native MySQL/MariaDB WordPress environment and native WP-CLI are unavailable.

## Command/Test
Required matrix: deactivation; normal uninstall; purge disabled; purge setting enabled with constant absent; explicit purge with both safeguards; and multisite destructive-purge refusal. For each case inspect settings, plugin tables, and unrelated WordPress data before and after WordPress invokes `uninstall.php`.

## Expected result
Deactivation and all non-explicit purge cases preserve plugin data. Explicit purge removes only plugin-owned data. Multisite refuses partial destructive purge. Unrelated WordPress data always remains.

## Actual result
Deactivation preservation and reactivation pass in the 46-check Playground suite. Source inspection confirms dual purge safeguards and multisite refusal. The complete actual WordPress uninstall matrix has not been executed; source inspection is not runtime proof.

## Result
**NOT VERIFIED** — mandatory external execution is incomplete.

## Evidence
- `plugins/realestate-platform/uninstall.php`
- `scripts/playground-verify.mjs`
- `verification-results/php-8.1.json`
- `verification-results/php-8.2.json`
- `verification-results/php-8.3.json`


## 2026-08-30 hardening update

Harness review found that explicit purge removed settings but left the plugin-owned migration ledger table and internal migration/log options. `uninstall.php` was corrected to remove those resources only when both purge safeguards pass, while retaining multisite refusal. PHPStan, PHPCS, PHPUnit, source runtime, and extracted-ZIP PHP 8.1–8.3 regressions pass after the correction. The external MySQL/MariaDB uninstall matrix remains **NOT VERIFIED** until `tools/verification/uninstall/run.sh` executes.
