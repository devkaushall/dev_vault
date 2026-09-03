# Phase 0 research conclusion

## Recommendation
Proceed with a **hybrid modular monolith**: WordPress-native editorial entities and media; one canonical typed field registry; purpose-built private/operational tables; a rebuildable local search index; provider interfaces for maps/geocoding/mail/PDF/payments/MLS; optional Elementor and ACF adapters; and explicit Standalone, Compatibility and Migration modes.

## Non-negotiable invariants
1. Existing Mayfair CPTs, post IDs, slugs, media and Elementor data are never replaced automatically.
2. One canonical field contract; aliases are compatibility mappings, not rival registries.
3. Leads and PII are private by construction and absent from public WordPress queries/routes.
4. Search projection is disposable; editorial truth remains exportable.
5. Core operates without Elementor, ACF, WooCommerce, Google or external APIs.
6. Premium checks never corrupt or conceal foundational data.
7. No production-ready/PASS claim without meaningful automated/manual evidence.

## Phase-1 entry criteria
- Approve ADR-001–009 and table naming/retention policy.
- Inventory a sanitized Mayfair site: CPT/taxonomy registrations, real `mpd_*` keys/types/counts, ACF definitions, Elementor template/document usage, routes and lead stores. Do not invent field keys.
- Confirm supported WP/PHP/DB/Elementor versions from a CI feasibility spike.
- Benchmark representative 1k/10k/100k synthetic datasets before freezing index DDL.
- Conduct privacy/legal review for India-facing lead consent/retention and any target markets.

## Risks requiring prototypes
`dbDelta` portability and spatial capabilities; field-definition evolution; index consistency; WP-Cron reliability; Elementor Pro API/version boundaries; SSRF-resistant imports; large media feeds; URL compatibility; dual-write drift. Each gets a focused spike/test, not speculative production code.

## Phase status
Research documents are internally aligned at proposal level. Phase 0 does **not** prove implementation, compatibility, security or performance. Production feature coding should begin only after stakeholder acceptance and the Mayfair inventory.
