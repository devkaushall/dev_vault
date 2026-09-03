# Phase 8 preflight audit and authorization

**Audit date:** 2026-09-03  
**Baseline:** RealEstate Platform Core 0.7.0 / database schema 004  
**Target:** 0.8.0 Elementor integration, with schema 004 retained

## Audit result

Phase 8 was authorized only after the Phase 7 baseline was inspected. The baseline has one canonical Lead Engine (`Leads\\LeadService`), `Requests\\RequestService` as its validated request facade, one canonical `SearchRequest` pipeline, `ProfileService`, the shared `FieldRegistry`, `PublicSubmissionRateLimiter`, and the existing Compatibility/Standalone/Migration mode detector. No second lead engine, Elementor adapter, or persistent Phase 8 requirement was found.

The repository contains no Elementor dependency in `composer.json`, and activation is designed to work without Elementor, Elementor Pro, ACF, or Mayfair artifacts. Before this phase there was no executable Elementor registration hook or form/query adapter. The existing Elementor material was research/documentation, not a runtime claim.

## Availability audit

| Runtime or artifact | Available locally | Phase 8 treatment |
|---|---:|---|
| WordPress 6.4 / SQLite Playground | Yes | Executable contract fixture |
| PHP 8.1, 8.2, 8.3 Playground runtimes | Yes | Syntax and prior-phase matrix |
| Elementor plugin runtime | No | Contract harness only; **NOT VERIFIED** |
| Elementor Pro runtime | No | Contract harness only; **NOT VERIFIED** |
| ACF runtime | No | Optional absence path; **NOT VERIFIED** |
| Browser/editor/Theme Builder/Loop Grid | No | **NOT VERIFIED** |
| Mayfair production artifacts/hooks | No | No compatibility claim; **NOT VERIFIED** |
| Native MySQL/MariaDB | No | SQLite only; **NOT VERIFIED** |

The fake Elementor and Elementor Pro classes in `scripts/phase8-runner.php` model only the documented method signatures needed by the adapter contract. They are not vendor runtimes and are not evidence of editor, browser, widget, Theme Builder, Loop Grid, ACF, or production compatibility.

## Existing-code audit

- Canonical public content remains WordPress posts, fields, terms, and media.
- Public field exposure is already described by `FieldRegistry`; the adapter does not invent a parallel field schema.
- Agent and Agency reads go through `ProfileService`; private profile values are not serialized to Elementor.
- Property query delegation goes through `SearchRequest`; the adapter does not write SQL or search/index tables.
- Elementor Pro submissions go through `RequestService` and therefore the Phase 7 validation, consent, honeypot, rate-limit, dedupe, and Lead Engine path.
- No Phase 8 code writes `rep_leads`, `rep_lead_requests`, `rep_site_visits`, or notification tables.
- REST routes are unchanged. Elementor is a presentation/consumer adapter, not a replacement API.

## Authorization decisions

1. Implement a thin optional adapter in `src/Elementor/`.
2. Register only after the official Elementor hooks/runtime are available.
3. Use the official `elementor/dynamic_tags/register` hook and dynamic-tag manager contracts.
4. Use the official `elementor/query/{query_id}` action contract, not a filter substitute.
5. Register a bounded set of stable public tags for Property, Project, Agent, Agency, and Insight contexts.
6. Provide one safe Property search adapter and bounded public entity query adapters.
7. Provide the optional Elementor Pro form action only as a transport bridge into `RequestService`.
8. Keep schema 004. No empty or speculative migration is created.
9. Never rewrite Elementor documents, templates, widget IDs, settings, or content.
10. Treat unavailable real runtimes and browser/editor paths as **NOT VERIFIED**, never PASS.

## Official contract references

The implementation was checked against the official Elementor developer documentation:

- Dynamic tag registration: <https://developers.elementor.com/docs/dynamic-tags/add-new-dynamic-tag/>
- Dynamic tag manager registration: <https://developers.elementor.com/docs/managers/registering-dynamic-tags/>
- Dynamic tag groups: <https://developers.elementor.com/docs/dynamic-tags/dynamic-tags-groups/>
- Custom query action: <https://developers.elementor.com/docs/hooks/custom-query-filter>
- Elementor Pro custom form action: <https://developers.elementor.com/docs/form-actions/add-new-action/>

These references establish manager `register()`, group `register_group()`, the `elementor/query/{$query_id}` action receiving `WP_Query`, and the Pro form registrar action. They do not establish a claim of compatibility with a particular unavailable Elementor version.

## Gate outcome

The preflight authorized implementation. The executable Phase 8 contract harness subsequently passed on PHP 8.3 / WordPress 6.4 / SQLite. Actual Elementor, Elementor Pro, ACF, browser/editor, Theme Builder, Loop Grid, Mayfair, CI, native database, and production verification remain explicitly open.
