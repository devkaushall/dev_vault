# Phase 8 privacy verification

**Release:** 0.8.0  
**Result:** PASS for the local WordPress Playground privacy contract; legal/production review **NOT VERIFIED**

## Public/private separation

Elementor dynamic tags are limited to public editorial and explicitly frontend-visible fields:

- Property public fields, published relations, public media URLs, and privacy-adjusted coordinates.
- Project public editorial fields and URLs.
- Agent public identity/contact/profile fields and published Agency name.
- Agency public identity/contact/office/license fields.
- Insight public editorial/source/CTA fields and URLs.

The adapter does not expose private notes, owner IDs as a public profile value, lead/request records, email delivery state, assignments, workflow histories, notification events, user identities, or operational audit data.

## Context and publication controls

A dynamic tag resolves only when the current post is a published post of the requested entity type. Missing, draft, private, mismatched, or invalid contexts resolve to no output. Related profile titles are returned only for published related posts. Attachment IDs must point to actual attachment posts.

## Submission minimization

The Pro action forwards only the documented minimum fields: name, email, phone, message, public context IDs, consent, website URL, and idempotency key. It adds `source=elementor`; workflow status, assignment, ownership, notification state, and audit data remain server-owned. Generic success/error text avoids reflecting submitted private values.

All Phase 7 controls remain in force through `RequestService`: strict DTO validation, consent, honeypot, public rate limiting, replay-safe dedupe, publication/context validation, private storage, and privacy exporter/eraser behavior.

## Evidence

The post-official-action Phase 8 harness passed:

- public profile serialization without private notes;
- published Property/Project/Agent/Agency/Insight contexts;
- empty draft and missing contexts;
- invalid property, missing consent, and honeypot submissions without mutation;
- replay producing one canonical lead/request path;
- existing Phase 7 privacy export and erasure regressions.

Evidence: `verification-results/phase8-runtime-8.3.json`, `verification-results/phase7-runtime-8.3.json`, and `verification-results/phase5-privacy.json`.

## Not verified

No privacy/legal retention review, real Elementor editor preview, browser network capture, real ACF exposure audit, Mayfair field inventory, production log inspection, or native database deployment was available. These remain **NOT VERIFIED** and are not converted into PASS by the fixture.
