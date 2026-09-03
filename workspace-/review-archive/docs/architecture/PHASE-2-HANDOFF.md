# Phase 2 Architecture Handoff

**Planning only — no Phase 2 implementation is authorized.** Phase 2 must satisfy `PHASE-2-ENTRY-CRITERIA.md` and preserve the frozen Phase 1 foundation.

## Shared rules

New functionality belongs in bounded modules under `src/`, depends inward on existing contracts/core, and is wired only at the composition root. Standalone, Compatibility, and explicit Migration modes remain distinct. ACF and Elementor are optional adapters. Every schema change is versioned, restartable, observable, tested for failure/recovery, and does not rewrite Mayfair data implicitly.

## Property
- **Module:** `Content/Property` domain/application with WordPress adapter.
- **Contracts:** settings, logger, database, future repository contract; no direct module coupling.
- **Database:** editorial record in a registered/adopted WP post; typed registered meta for scalar fields; justified relation/index tables only.
- **Migration:** adopt in Compatibility mode; explicit manifest/dry-run in Migration mode; preserve IDs/slugs/media.
- **REST/capabilities/diagnostics:** schema-based endpoints, object-level read/edit/publish capabilities, registration/index health checks.
- **Elementor/Mayfair:** optional dynamic adapter; never replace existing `property` registration or templates.

## Project
- **Module:** `Content/Project` with explicit property relationship boundary.
- **Database:** WP post plus typed meta; query-relevant relationships use a justified relation table rather than duplicated postmeta.
- **Migration:** preserve existing `project` IDs and property links.
- **REST/capabilities/diagnostics:** dedicated schema and project permissions; relationship-integrity checks.
- **Elementor/Mayfair:** optional rendering tags; adopt existing registration and fields without mutation.

## Insight
- **Module:** `Content/Insight` editorial adapter.
- **Database:** WP post/taxonomy primitives unless evidence requires otherwise.
- **Migration:** preserve IDs, slugs, URLs, authors, media, and taxonomy links.
- **REST/capabilities/diagnostics:** native REST interoperability where safe; editorial capabilities; registration/permalink diagnostics.
- **Elementor/Mayfair:** retain existing dynamic content and templates.

## Fields
- **Module:** `Fields` definition registry and adapters.
- **Contracts/database:** one versioned typed definition source; registered meta for scalar canonical values; relation rows only for repeating/query-critical structures.
- **Migration:** field mapping is explicit, reversible by backup/manifest, and compatibility-first.
- **REST/security:** per-field schema, sanitize/validate/auth callbacks; no secret fields exposed.
- **Elementor/Mayfair:** ACF and Elementor adapters consume canonical definitions without becoming mandatory; map observed `mpd_*` fields rather than renaming them.

## Taxonomies
- **Module:** `Classification` with entity-specific registration/adoption policies.
- **Database:** WordPress terms/taxonomies; no duplicate custom taxonomy store.
- **Migration:** preserve term IDs/slugs/relationships and adopt existing Mayfair taxonomies.
- **REST/capabilities/diagnostics:** explicit `show_in_rest`, term capabilities, conflict/orphan checks.
- **Elementor/Mayfair:** existing taxonomy queries and dynamic tags remain valid.

## Media
- **Module:** `Media` application service over WordPress attachments.
- **Database:** attachment IDs and metadata; no copied binary blobs in custom tables.
- **Migration:** preserve attachment IDs, URLs, galleries, featured images, and Elementor references; remote ingestion is explicit and SSRF-safe.
- **REST/capabilities/diagnostics:** upload/type/size authorization and orphan/missing-file checks.
- **Elementor/Mayfair:** retain existing attachment references and responsive metadata.

## Locations
- **Module:** `Locations` domain plus provider interfaces; mapping/geocoding adapters remain optional.
- **Database:** hierarchical WP taxonomy for editorial classification; normalized coordinates/search projection only when justified by approved search design.
- **Migration:** map existing location terms and coordinates without mandatory re-geocoding.
- **REST/capabilities/diagnostics:** bounded location schemas, provider-key protection, geocode/provider health checks.
- **Elementor/Mayfair:** provider-neutral presentation; preserve existing Mayfair location taxonomies and field bindings.

## Before coding

Approve exact observed Mayfair inventory, canonical field/taxonomy schemas, table justifications, migration IDs and failure tests, capability map, REST schemas, privacy effects, diagnostics, performance targets, and acceptance evidence. Search, maps, leads, commerce, imports, and widgets remain outside this handoff unless separately authorized.
