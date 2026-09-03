# Security and privacy model

## Assets and boundaries
Public listings are untrusted input until validated. Leads, contact details, saved searches, visits, payment references, API credentials and audit records are confidential. Boundaries include browser↔REST, frontend submission, admin, cron/CLI, remote feeds/media, map/geocode/mail/payment providers and Elementor callbacks.

## Control baseline
- Deny by default; capabilities plus object ownership on every mutation/read of private data. Nonces mitigate CSRF but never replace authorization.
- REST controllers define schemas, validate before sanitize, use `permission_callback`, cap page sizes, return generic errors and suppress private IDs/counts where enumeration matters.
- Escape at output context (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`); do not “sanitize once” as an XSS strategy.
- SQL uses `$wpdb->prepare()`, fixed identifier allowlists and bounded queries. Dynamic sort columns are mapped, never interpolated from requests.
- Uploads require capability, nonce, size quota, extension+MIME+signature checks, safe names and WordPress media handling. SVG/executables disabled by default.
- Remote media/feed fetches allow HTTPS by default, resolve and reject loopback/private/link-local/metadata networks, restrict redirects/ports, revalidate each hop, enforce timeout/size/content type and hash-deduplicate.
- Public forms use honeypot, signed timing token, normalized dedupe key, per-subject/IP-hash rate limits and CAPTCHA hooks. Raw IP storage off by default; rotating salted hashes only where justified.
- Secrets are not rendered client-side or logged. Prefer environment/wp-config constants or encrypted-at-rest provider credentials with a site-held key; acknowledge WordPress cannot perfectly protect secrets from a fully compromised administrator/server.
- Audit sensitive transitions; redact bodies/tokens/contact values from general logs.

## Threat priorities
| Threat | Controls / tests |
|---|---|
| IDOR favorites/leads/visits | ownership/capability repository scope; cross-user REST tests |
| Privilege escalation | explicit caps, `map_meta_cap`, role migration tests |
| SQL injection | prepared values, identifier allowlist, fuzz filters/sorts |
| Stored/reflected XSS | strict schemas, KSES policy, contextual escaping, payload tests |
| CSRF | nonces + SameSite cookies + capability; destructive confirmation |
| SSRF/media bombs | network deny rules, redirect recheck, byte/pixel/time limits |
| Spam/resource exhaustion | tiered rate limits, queue bounds, pagination and query budgets |
| Formula injection exports | prefix dangerous CSV cells; UTF-8 and permissions |
| Webhook spoof/replay | provider signatures, timestamp tolerance, idempotency keys |
| Sensitive logging | structured redaction allowlist and retention |

## Privacy lifecycle
Capture purpose/versioned consent; collect minimum fields; expose privacy-policy text; register WordPress exporters/erasers; configurable lead/submission/log retention; anonymize records needed for aggregate/audit obligations; cascade user deletion to favorites/searches or transfer only by explicit policy. Erasure never silently corrupts statutory payment records—identifiers are minimized/anonymized where legally allowed.

## Release gates
Threat-model review, dependency/license scan, PHPCS security rules, static analysis, unauthorized/CSRF/XSS/SQLi/upload/SSRF/IDOR/rate-limit tests, and manual REST route inventory. Security claims remain FAIL until those tests run against supported WP/PHP/DB versions.

Sources: [WordPress Plugin Security](https://developer.wordpress.org/plugins/security/), [Nonces](https://developer.wordpress.org/apis/security/nonces/), [REST authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/), [Privacy](https://developer.wordpress.org/plugins/privacy/).
