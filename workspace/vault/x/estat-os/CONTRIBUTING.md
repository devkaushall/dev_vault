# Contributing

Thank you for wanting to help.

## Getting set up

Clone the repository into `wp-content/plugins/` of a local WordPress install and
activate it. There is no build step and no runtime dependency to install.

## Before you open a pull request

```bash
# 1. Every PHP file must parse.
find . -name '*.php' -print0 | xargs -0 -n1 php -l

# 2. Every script must parse.
for f in assets/js/*.js; do node --check "$f"; done

# 3. The test suite must pass.
php tests/run-tests.php

# 4. Coding standards (optional locally, advisory in CI).
phpcs
```

## House rules

* **Plain language in the interface.** No "custom post type", no "taxonomy", no
  "meta key", no "slug". If a beginner would not say it out loud in an office,
  do not put it on the screen.
* **All listing writes go through `Data\Listings::save()`.** Never write listing
  meta directly.
* **Storage is canonical, formatting is display-only.** Numbers in the database,
  formatting in `Support\Format`.
* **Migrations are additive.** No release ever drops or rewrites a column that
  holds somebody's data.
* **Nothing is deleted without an explicit, typed confirmation.**
* Every file starts with `declare( strict_types = 1 );` and
  `defined( 'ABSPATH' ) || exit;`.
* Every user-facing string is translatable with the `estat-os` text domain.
* Escape at the point of output; prepare every query.

## Adding a language

See [`docs/translating.md`](docs/translating.md). Translations are very welcome,
particularly regional Indian languages written in Latin script — that is exactly
what the Hinglish locale is for.

## Reporting a security issue

Please do not open a public issue. Email the maintainers instead and give us a
reasonable window to release a fix.
