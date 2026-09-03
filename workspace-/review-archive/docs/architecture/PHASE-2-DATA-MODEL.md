# Phase 2 Data Model

Version 0.2.0, authorized under Entry Model B on 30 August 2026.

## Canonical architecture

Property, Project, and Insight are editorial WordPress post types in Standalone mode. Compatibility/Migration modes adopt an existing registration and never register a competing one. The single canonical field registry is `Fields\FieldRegistry`; adapters consume it rather than creating separate ACF/Elementor definitions.

No Phase 2 custom table is introduced. WordPress posts own identity, title, slug, content, excerpt, author, status, and featured image. Taxonomies own classification. Registered `rep_*` post meta owns scalar attributes and attachment references. This is sufficient at current scale and avoids premature search/relationship tables; future search projections are explicitly deferred.

## Entities

- **Property:** core editorial columns plus reference, canonical numeric price/currency/period, address components, coordinates, areas, rooms/floors/parking/year, furnishing/possession/availability/construction/developer/RERA, flags, URLs, brochure/floor-plan/gallery attachment IDs.
- **Project:** editorial columns plus developer, price, location/address, coordinates, possession/construction/RERA, featured, video, brochure, and gallery. Property relationships are deferred until their contract is approved.
- **Insight:** editorial columns/author/featured image plus subtitle, reading time, author image, featured, source URL/name, and CTA label/URL.

Missing optional values remain null/empty; no placeholder facts are invented. Prices and coordinates remain numeric canonical values.

## Taxonomies

`property_type`, `property_status`, `property_category`, `property_label`, `property_feature`, `property_amenity`, hierarchical `location`, `project_type`, and `insight_topic`. Each is registered only in Standalone mode and only when absent.

## Locations and media

Location classification uses hierarchical terms; normalized country/state/city/locality/neighborhood/micro-market/postal fields and WGS84 coordinates remain provider-neutral. Media uses WordPress attachments: featured image, ordered gallery IDs, floor-plan/brochure attachment IDs, and validated video/virtual-tour URLs. No binary duplication or external geocoding occurs.

## REST, capabilities, diagnostics

Native WordPress REST controllers expose post types, taxonomies, and explicitly REST-enabled canonical meta. Meta writes require object edit permission. WordPress meta-cap mapping uses distinct property/project/insight capability families granted only to administrators on activation. Existing Phase 1 status diagnostics remain; Phase 2 registration checks are covered by runtime QA and will be promoted into dedicated diagnostics only if operational need is proven.

## Search readiness

Definitions mark fields searchable/filterable/sortable for a later index design. These flags are metadata only; no search engine or premature index is implemented.

## Verification qualification

Canonical meta uses type/range/format schemas plus sanitization and validation. Final edge testing nevertheless observed native Project/Insight REST updates returning 200 for malformed nested canonical meta while making no invalid mutation. The required 4xx contract remains unresolved; see `docs/testing/PHASE-2-REST-EDGE-CASES.md`.
