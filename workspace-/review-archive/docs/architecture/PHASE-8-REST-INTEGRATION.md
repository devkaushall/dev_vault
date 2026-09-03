# Phase 8 REST integration boundary

**Release:** 0.8.0  
**Decision:** no new REST route and no REST schema migration

## Contract statement

Elementor is an optional presentation/consumer adapter. It does not replace or duplicate the canonical REST API. The existing namespace remains `realestate-platform/v1`, and Phase 8 does not add a public route, change a response serializer, or expose private workflow tables.

The adapter relationships are:

```text
Elementor dynamic tags -> canonical public post/profile/field services
Elementor property query -> SearchRequest/SearchEngine/SearchResult
Elementor Pro form -> RequestService -> LeadService
Native REST/AJAX -> the same canonical services, independently
```

There is no Elementor-to-REST HTTP loopback. Server-side delegation avoids browser credentials, duplicate validation, localhost assumptions, and public leakage.

## Existing REST guarantees preserved

- Public Property reads require published content and allowlisted fields.
- Profiles use `ProfileService` and public serialization.
- Leads, requests, site visits, assignments, and notification events remain private operational data.
- Authenticated workflow mutations retain capability, ownership, strict-ID, and nonce/CSRF checks.
- Public submissions retain consent, honeypot, validation, rate-limit, replay/dedupe, and generic acknowledgement behavior.
- REST error responses do not expose SQL, private notes, internal IDs beyond the public contract, or provider secrets.

## Regression evidence

The current source passed the Phase 2–7 REST/search/workflow regression harnesses after the Phase 8 adapter was added. The representative evidence files are:

- `verification-results/phase2-rest-contract.json`
- `verification-results/phase3-rest-search.json`
- `verification-results/phase3-rest-ajax-url.json`
- `verification-results/phase5-rest.json`
- `verification-results/phase6-rest.json`
- `verification-results/phase7-runtime-8.3.json`
- `verification-results/phase8-runtime-8.3.json`

The Phase 8 runner also verifies public/private serialization, invalid public contexts, invalid lead contexts, and no mutation for invalid consent/honeypot/property submissions.

## Explicit non-claims

No browser fetch, editor preview, Theme Builder REST flow, Elementor Pro frontend JavaScript flow, remote authentication, or production REST deployment was available. Those environments remain **NOT VERIFIED**. The local evidence proves only the adapter’s server-side contract against WordPress Playground and the project’s fake vendor interfaces.
