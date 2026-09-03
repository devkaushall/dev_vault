# Local Verification Environment

## Selected environment
The host has Node.js 20.20.2 and npm 10.8.2, but no native PHP, Composer, WP-CLI, Docker, or Docker Compose. Verification therefore uses `@wp-playground/cli` (WordPress Playground/PHP WebAssembly) as a disposable, reproducible WordPress runtime. It uses WordPress 6.4.x, PHP 8.1/8.2/8.3, and Playground's SQLite database integration. This does not substitute for the required MySQL/MariaDB and native WP-CLI CI jobs.

## Setup
```bash
npm install --save-dev @wp-playground/cli@latest
curl -sS https://getcomposer.org/download/latest-stable/composer.phar -o composer.phar
node run-composer.mjs validate --strict
node run-composer.mjs install --no-interaction --prefer-dist
node run-composer.mjs dump-autoload --optimize
```
No real credentials are used. Playground creates a disposable WordPress installation and SQLite database internally. The plugin mounts at `/wordpress/wp-content/plugins/realestate-platform`.

## Commands
```bash
node scripts/playground-verify.mjs 8.1 8.2 8.3
node run-tool.mjs phpunit --colors=never
node run-tool.mjs phpstan analyse --no-progress --error-format=raw
node run-tool.mjs phpcs -q --report=summary
./scripts/package.sh
rm -rf /tmp/repzip && mkdir /tmp/repzip
unzip -q dist/realestate-platform-0.1.0.zip -d /tmp/repzip
PLUGIN_PATH=/tmp/repzip/realestate-platform node scripts/playground-verify.mjs 8.3
```
The verification script records JSON evidence in `verification-results/`.

## Teardown
```bash
rm -rf node_modules composer.phar /tmp/repzip .tool-runner.php verification-results
```
Do not remove `plugins/realestate-platform/vendor` before packaging unless regenerating production autoload files with `composer install --no-dev --optimize-autoloader`.

## Environment variables
`PLUGIN_PATH` optionally points the Playground test runner at an extracted production ZIP. No database or application secrets are needed.

## Limitations
Playground uses SQLite, not MySQL/MariaDB; it does not provide native WP-CLI; browser/manual admin-screen interaction was not performed; PHPStan process execution is unreliable in this WASM runtime; GitHub Actions was not externally triggered. Native CI must execute Composer, syntax, PHPCS, PHPStan, PHPUnit, MySQL WordPress integration, WP-CLI, and ZIP install jobs before release.
