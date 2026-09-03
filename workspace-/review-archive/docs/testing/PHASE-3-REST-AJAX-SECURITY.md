# Phase 3 REST/AJAX Security Verification

Executed cases cover SQL-style and XSS payloads, parameter pollution/nesting, null and wrong types, invalid and negative IDs, inverted ranges, nonexistent taxonomy/project relationships, order-by injection, excessive pagination, unknown/internal filters, all non-public post statuses, and AJAX missing/invalid nonce.

REST validation and the shared criteria layer return structured 4xx errors. AJAX nonce failures return 403. Responses expose no SQL, credentials, stack traces, private metadata, notes or lead data. Search joins canonical posts and requires `post_type=property` and `post_status=publish`, even if a projection row is stale. Before/after post, metadata, taxonomy bridge and projection snapshots establish read-only behavior.

The endpoint is intentionally public and read-only; there is no write method on this controller. Existing authenticated Phase-2 write permissions remain separately enforced and passed their regression suite.

Evidence: `verification-results/phase3-rest-ajax-security.json`.
