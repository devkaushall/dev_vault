# Integration tests

These run the plugin against **real WordPress source code**, without a database.

## Running them

```bash
# Fetch WordPress once (the harness expects it at /tmp/wordpress/).
curl -sL https://wordpress.org/latest.tar.gz | tar -xz -C /tmp

php tests/integration/run-integration.php
```

The runner exits non-zero if anything fails.

## What is real and what is not

**Real WordPress code**, loaded from `/tmp/wordpress/`:

* `plugin.php` and `class-wp-hook.php` — actual hook dispatch
* `formatting.php` — actual `sanitize_text_field`, `esc_html`, `esc_url`, `esc_attr`
* `kses.php` and the HTML API — actual `wp_kses_post`
* `shortcodes.php` — actual shortcode parsing
* `class-wp-error.php`, `class-wp-post.php`
* `WP_REST_Request` and `WP_REST_Response`

**Harness substitutes**, in `wp-stubs.php` and `class-fake-wpdb.php`:

* `$wpdb` — an in-memory store with a real mini SQL interpreter. Its `prepare()`
  **throws** on a placeholder/argument mismatch, and INSERT materialises every
  declared column as `NULL`, matching MySQL. Every query is recorded in
  `$wpdb->queries` so tests can assert query counts.
* `dbDelta()` — parses the plugin's real `CREATE TABLE` SQL and rejects a table
  with no columns or no primary key.
* Options, transients, object cache, posts, meta, terms, users, capabilities,
  nonces, cron, mail and HTTP — working in-memory implementations.
* `wp_redirect()` and `wp_die()` throw `Estat_Redirect_Exception` and
  `Estat_Die_Exception` so tests can assert on them.
* Outgoing mail lands in `$GLOBALS['estat_mail']`, HTTP in `$GLOBALS['estat_http']`.

`ABSPATH` points at `tests/integration/shim/` so that the plugin's correct
`require_once ABSPATH . 'wp-admin/includes/upgrade.php'` resolves to a harness
file rather than the real one, which needs a live database.

## Writing a test

Add `it-NN-name.php`; the runner picks it up automatically. Start with:

```php
estat_reset_world();   // clean posts, meta, tables, mail, HTTP
estat_login_owner();   // grant every capability
```

Use `estat_login_with( $user_id, array( 'estat_manage_leads' ) )` to test that a
limited capability set cannot reach something it should not.

## Known limits

A real database is never touched, so `dbDelta()` against MySQL, real outbound
HTTP, any browser behaviour, Elementor and multisite are **not** covered here.
These are listed in `docs/release-readiness.md`.
