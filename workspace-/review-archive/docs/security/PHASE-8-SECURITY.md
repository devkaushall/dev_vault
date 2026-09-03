# Phase 8 security review

**Release:** 0.8.0  
**Result:** local adapter security checks PASS; external vendor/browser/runtime verification **NOT VERIFIED**

## Threat model and controls

| Threat | Control |
|---|---|
| Core activation fails because Elementor is absent | No hard dependency; conditional availability and deferred boot |
| Unpublished/private content appears in a tag | Type-matched current `WP_Post` plus `publish` requirement |
| Cross-entity context confusion | Entity allowlist and post-type equality check |
| Private Agent/Agency data leaks | `ProfileService` public read path plus `FieldRegistry` exposure flags |
| Private lead/workflow data leaks | No dynamic tags, query output, or form response serializes private workflow records |
| Raw HTML/script in titles or taxonomy terms | `wp_strip_all_tags()` then contextual `esc_html()` |
| Unsafe URL output | Attachment validation and `esc_url()` |
| Hidden coordinate disclosure | `CoordinatePrivacy` policy, with hidden mode returning null |
| Arbitrary query/meta/SQL injection | Closed query-variable/criteria allowlist; canonical prepared search provider |
| Query broadening on invalid search | Error/empty result becomes `post__in=[0]`; public status/type forced |
| Pagination/resource abuse | Per-page clamp 1–100; bounded canonical SearchRequest |
| Form field privilege escalation | Fixed field map; server-owned state/assignment/ownership fields are never accepted |
| Form replay/spam | Phase 7 rate limiter, idempotency/dedupe, consent, honeypot, validator path |
| CSRF/auth bypass | Elementor’s invocation boundary plus canonical request service; authenticated mutations remain on Phase 7 capability/nonce path |
| Adapter creates a second data path | Delegation only; no direct workflow-table writes or SQL in `src/Elementor` |
| Existing document/content damage | No Elementor document/template/widget/content writes |
| Diagnostic leakage | Existing diagnostics are not changed; generic public form messages |

## Static and executable checks

`scripts/phase8.mjs` rejects adapter files containing `$wpdb`, direct `INSERT/UPDATE/DELETE INTO`, or Phase 7 workflow table names. The PHP 8.3 contract run reports `adapter_has_no_direct_sql_or_workflow_tables=true` and passes the security/privacy/integrity checks.

The unit suite covers optional-runtime behavior, public catalog stability, and bounded query input. Phase 7 regression coverage confirms the canonical lead validation and workflow controls remain intact.

## Scope limitations

The local fake classes do not prove vendor implementation details, JavaScript/editor behavior, browser-origin/nonce handling, third-party extensions, or production hosting configuration. Elementor, Elementor Pro, ACF, browser, Theme Builder, Loop Grid, Mayfair, CI, MySQL/MariaDB, and production security testing remain **NOT VERIFIED**.
