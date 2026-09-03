# Architecture options and ADRs

**Status:** Phase-0 accepted proposal; implementation requires benchmark/test validation.

## Options considered
A. Everything as CPT/postmeta: maximum WP compatibility, unacceptable complex-search and high-write behavior.  
B. Fully custom domain database: scalable but sacrifices WP editorial/page-builder interoperability.  
C. **Hybrid modular monolith (chosen):** WP posts/taxonomies/meta for editorial truth; custom relational tables for search projections and operational/private records.

## ADR-001 Property storage
**Problem:** preserve WP interoperability and scale. **Alternatives:** postmeta-only; custom-only; hybrid. **Chosen:** CPT plus typed registered meta/taxonomies, with derived index. **Why:** IDs, revisions, media, URLs and builders remain native. **Trade-offs:** synchronization complexity. **Migration consequences:** preserve IDs/slugs; adapters map `mpd_*`; index is rebuildable.

## ADR-002 Search indexing
**Problem:** multi-range/taxonomy/geo search at 100k properties. **Alternatives:** `WP_Meta_Query`; external SaaS; local index. **Chosen:** local denormalized index tables and taxonomy bridge, repository abstraction. **Why:** predictable SQL and no mandatory vendor. **Trade-offs:** eventual consistency. **Migration consequences:** versioned rebuild/dual-read during upgrades; external engines may later implement interface.

## ADR-003 Lead storage
**Problem:** private, high-write PII and workflow. **Alternatives:** public CPT; form-plugin records; tables. **Chosen:** private normalized tables with audit/retention. **Why:** least exposure and efficient workflow. **Trade-offs:** custom admin/export/privacy code. **Migration:** map existing leads only with explicit source adapter and reconciliation.

## ADR-004 Field architecture
**Problem:** configurable fields without ACF or serialized traps. **Alternatives:** code registry; options blob; definitions table. **Chosen:** versioned definitions table; scalar canonical values in typed meta/index; repeaters/groups in normalized value rows or JSON only when non-queryable. **Why:** one registry and query intent is explicit. **Trade-offs:** schema/compiler complexity. **Migration:** aliases, type conversion reports, preserve unknown source meta.

## ADR-005 Elementor integration
**Problem:** deep support without dependency. **Alternatives:** DOM hacks; hard dependency; adapter. **Chosen:** optional official-API adapter. **Why:** graceful absence and deprecation isolation. **Trade-offs:** some features require Pro. **Migration:** stable tag aliases; never rewrite templates without dry-run/backups.

## ADR-006 Map providers
**Problem:** vendor choice, keys, privacy and cost. **Alternatives:** Google-only; OSM-only; interfaces. **Chosen:** geocoder/map/directions interfaces; OSM presentation baseline subject to tile policy; provider adapters. **Why:** no lock-in. **Trade-offs:** capability differences. **Migration:** store coordinates/provider-neutral normalized address, not vendor payload as truth.

## ADR-007 Licensing architecture
**Problem:** commercial modules without crippling core. **Alternatives:** checks throughout core; separate unrelated plugins; module manifests. **Chosen:** core contracts + discoverable modules/entitlements. **Why:** replaceable and testable. **Trade-offs:** strict API governance. **Migration:** data remains readable/exportable when license expires; only paid execution/UI disabled.

## ADR-008 Import architecture
**Problem:** large/untrusted heterogeneous feeds. **Alternatives:** synchronous importer; provider-specific code; pipeline. **Chosen:** staged batch pipeline (ingest→validate→map→match→persist→media→reconcile) with job, journal and provider interfaces. **Why:** dry-run/retry/rollback. **Trade-offs:** more tables and operations. **Migration:** immutable source IDs and mapping versions; no private-format reverse engineering.

## ADR-009 Compatibility strategy
**Problem:** Mayfair CPTs, `mpd_*`, ACF, Elementor and URLs must survive. **Alternatives:** automatic replacement; fork; modes. **Chosen:** Standalone, Compatibility and explicit Migration modes. **Why:** lowest deployment risk. **Trade-offs:** adapter/test matrix. **Migration:** detect only first; explicit plan, backup, dry-run, checksums, rollback; preserve IDs/slugs/media/unknown meta.

## Module dependency rule
Core ← Content/Fields/Locations ← Search ← Listings/REST; optional adapters depend inward on contracts. Leads/Accounts/Imports depend on Core contracts, never Elementor. Cross-module writes go through application services/events, not direct table calls.
