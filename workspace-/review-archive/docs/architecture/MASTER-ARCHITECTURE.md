# MASTER ARCHITECTURE

**Status:** Phase 9 closed local gate, version 0.9.0 (2026-09-03).  
**Authority:** `docs/SOURCE-OF-TRUTH.md` is the current source of truth; this document records the cross-cutting architecture baseline and Phase 9 decision. ADR detail lives in `docs/research/architecture-options.md`.

## Context and goals
Realestate Platform (REP) is an independent WordPress real-estate platform for 100–100,000 properties. It serves Mayfair without forcing replacement of existing `property`, `project`, `insight`, `mpd_*`, ACF, URLs, media or Elementor templates. It supports open core and replaceable commercial modules.

## Logical architecture
```text
WordPress/Admin/Blocks/Shortcodes/Elementor/CLI
                    │ adapters
           Application commands + queries
                    │
  Content ─ Fields ─ Locations ─ Search ─ Listings
  Accounts ─ Agents ─ Leads ─ Forms ─ Imports
                    │ ports
 WP posts/meta/terms/media   REP repositories/index/jobs
                    │
 Maps · Geocode · Mail · PDF · Payment · RESO providers
```

Dependencies point inward to contracts. Modules do not instantiate one another or write one another’s tables. A small composition root/service registry wires services; no global God object. Domain/application code is framework-aware only where WordPress semantics are the actual primitive.

## Canonical persistence
- **Editorial:** WP posts for property/project/insight/agent/agency, adopting existing CPTs in Compatibility Mode.
- **Classification:** WP taxonomies for type/status/category/label/feature/amenity/location/project type/topic.
- **Fields:** one versioned definition registry; registered typed meta for scalar canonical values; relational rows for repeating/query-relevant complex values.
- **Operational/private:** custom tables for leads, requests/visits, favorites, saved searches/matches, relationships, forms, notifications, jobs, audit, subscriptions/payments. Phase 9 import plans/reports are bounded in-process objects; no import job/checkpoint/history table is introduced.
- **Search:** denormalized, rebuildable projection with range/sort/location coordinates and term bridge. Canonical writes emit index invalidations/jobs.

## Modes
**Standalone:** REP registers its own entities. **Compatibility:** adapters read/adopt existing Mayfair registrations and map real observed fields; no destructive writes. **Migration:** explicit administrator-run, backup-gated, dry-run, batched migration with manifest, validation and rollback journal. Mode changes are audited.

## Request flows
**Property save:** authorize → schema validate → canonical transaction-like write → media/relationship updates → audit → index update/enqueue → cache invalidation.  
**Search:** parse allowlisted URL/REST criteria → normalize DTO → indexed repository → object hydration → permission-safe response → bounded cache.  
**Lead:** anti-spam/rate limit → validate/consent → private insert → audit/minimal log → asynchronous notification; never public query.  
**Import:** bounded source parse → normalized DTO → allowlist/type/taxonomy/relationship/media validation → zero-mutation validate/dry-run → deterministic create/update plan → canonical WordPress/ProfileService writes → verification/report with row-level compensation. No durable job/checkpoint table is needed for the bounded Phase 9 scope.

## API contracts
Namespace `realestate-platform/v1`. Public properties/projects/locations/search expose schema, pagination and links. `/me`, favorites and saved searches require authentication and ownership. Leads/imports/settings/diagnostics require fine-grained capabilities. Native `wp/v2` is retained for safe editorial interoperability; private records have no CPT/public controller. API versions are additive within v1; breaking changes require v2 and migration period.

## Extension contracts
`GeocoderInterface`, `MapProviderInterface`, `DirectionsProviderInterface`, `SearchRepositoryInterface`, `NotificationInterface`, `MailTransportInterface`, `PdfGeneratorInterface`, `PaymentGatewayInterface`, `ImportProviderInterface`, plus domain repositories and clocks/loggers. Public hooks are prefixed `rep/` and documented/tested before stability guarantees.

## Frontend and Elementor
Shared PHP/view-model/render services drive native templates, dynamic blocks, shortcodes and Elementor widgets. Assets load only where used. Filters synchronize with shareable URLs. Arbitrary combinations are canonicalized/noindexed. Elementor boots after compatibility checks and uses only official widget, control, tag, query, form and theme APIs; absence does not affect core.

## Security/privacy
Fine-grained capabilities and object ownership; nonce plus authorization; REST permission callbacks; prepared SQL; contextual escaping; strict upload and SSRF controls; rate limiting; secrets excluded from browser/logs; audit transitions; WordPress privacy exporter/eraser/policy hooks; configurable minimization, retention and anonymization. Payment card data is never stored.

## Performance/reliability
No unbounded frontend work. Indexed queries, capped pagination, object-cache-aware versioned keys, deterministic invalidation, lazy maps/media, bounded imports/exports, and durable workflow jobs where the domain requires them. Phase 9 is explicitly bounded to in-process plans and reports. WP-Cron dispatches opportunistically; WP-CLI/system cron provides reliable runners. Migrations are idempotent, versioned, observable and separate large backfills from activation.

## Versioning and delivery
PSR-4 source at `plugins/realestate-platform/`; Composer/npm tooling; semantic versions; reproducible ZIP from source. CI gates PHPCS, PHPStan, PHPUnit/WP integration, REST/security/migration/JS/E2E tests and packaging across declared matrices. Compatibility is claimed only for green tested versions.

## Architectural acceptance tests
- Elementor/ACF absent: core activation, CRUD, search and render pass.
- Compatibility mode: no CPT conflict and no automatic rewrite/data mutation.
- 100k synthetic inventory: agreed search SLO benchmark passes.
- Cross-user private-resource requests fail.
- Index can be dropped/rebuilt without canonical loss.
- License/provider outage does not break core frontend.
- Migration dry-run and rollback preserve IDs/slugs/media/template data.

## Remaining production-validation decisions
The Phase 7 core decisions are locked, including the migration-004 schema, outbox claim/retry semantics, PHP 8.1 and WordPress 6.4 minimums, and the dependency-free core boundary. Remaining validation/approval work is native MySQL/MariaDB execution, controlled migration-failure recovery, retention/legal review for the target market, the authorized Mayfair field/taxonomy/Elementor inventory, commercial entitlement approval, and the Phase 9 external runtime matrix.

## Phase-1 consistency resolution (2026-08-27)
The lowercase `docs/research/feature-parity-matrix.md` is canonical; the byte-identical uppercase duplicate was removed. The stable PHP namespace is `Mayfair\RealEstatePlatform`; the REST namespace is expanded from the Phase-0 placeholder `rep/v1` to collision-resistant `realestate-platform/v1`. Phase 1 detects existing Mayfair REST namespaces at runtime and registers no CPTs. PHP 8.1 and WordPress 6.4 are minimums; tested-through remains unclaimed. Multisite is unsupported in 0.x. The only foundation table is `{prefix}rep_schema_migrations`; WordPress options hold the restartable applied-migration list and typed settings. This resolves the Phase-0 open identity/version/multisite decisions without changing the hybrid architecture.

## Frozen foundation and handoff (2026-08-30)

Phase 1 version 0.1.0 is frozen as a release candidate under `PHASE-1-FROZEN.md`. Locally executable static analysis, coding standards, tests, SQLite/Playground lifecycle, supported PHP runtime, fixture compatibility, and package checks are green. Native MySQL/MariaDB, controlled real-database failure/recovery, complete uninstall, dependent security closure, real Mayfair artifacts, and external CI remain NOT VERIFIED; therefore Phase 1 remains externally blocked and is not declared production-ready.

Phase 2 was authorized under Model B on 30 August 2026. Version 0.2.0 implements a bounded content data layer using WordPress posts, registered meta, taxonomies, and attachments; it introduces no custom Phase 2 table or competing schema. Standalone mode registers absent canonical structures, while Compatibility and Migration modes adopt existing registrations without takeover. Canonical decisions are in `PHASE-2-DATA-MODEL.md`; field and future migration mappings remain explicitly versioned and unverified where real Mayfair artifacts are absent. Phase 1 external debt remains NOT VERIFIED.

## Phase 2 verification closure and Phase 3 authorization (2026-08-30)

Phase 2 is complete and PASS. The malformed metadata defect was traced to null defaults causing WordPress to reject scalar meta registration; the native registration path was corrected and fully regressed without a second schema or response-rewriting workaround. Phase 1 External Verification remains NOT VERIFIED.

Phase 3 is authorized for the bounded Search and Advanced Property Discovery scope. `PHASE-3-SEARCH-ARCHITECTURE.md` governs its disposable two-table projection, reusable criteria/query/provider boundaries, lifecycle synchronization, REST/AJAX adapters, diagnostics, and batched CLI rebuild. Canonical Property posts/meta/terms/media remain authoritative. Phase 4 and all explicitly excluded later features remain locked.

## Phase 3 search transport implementation checkpoint (2026-08-31)

The implemented search transport boundary is `SearchRequest -> SearchCriteria -> SearchEngine -> DatabaseSearchProvider`. REST `GET /realestate-platform/v1/properties`, nonce-protected AJAX, and deterministic URL state share this boundary and stable result semantics. Adapters contain no SQL or HTML. Public visibility is reasserted by joining canonical published Property posts. Parameters, pagination and sorting are closed and bounded. Detailed verified contract: `PHASE-3-SEARCH-API.md`. This does not unlock Phase 4.

## Phase 3 release closure (2026-08-31)

The bounded search projection, lifecycle, rebuild, consistency, diagnostics, CLI command implementation, public REST, nonce-protected AJAX and canonical URL state passed the local release gate. Canonical posts/meta remain authoritative and the index remains disposable. Package and final evidence are governed by `docs/testing/PHASE-3-FINAL-GATE.md`. This closure does not authorize Phase 4.

## Phase 4 geospatial closure (2026-08-31)

The Phase-3 search pipeline now accepts portable radius/bounds criteria and produces bounded privacy-adjusted marker data through provider-neutral map interfaces. Existing canonical coordinates and disposable projection remain authoritative/derived respectively. The default geocoder is disabled; future HTTP adapters must use the hardened no-redirect bounded HTTPS client. No schema migration was required. See `PHASE-4-GEOSPATIAL-DATA-MODEL.md`, `PHASE-4-MAP-ARCHITECTURE.md`, and the final gate report. Phase 5 remains locked.

## Phase 6 profile closure (2026-09-02)

Agent and Agency are WordPress editorial entities registered/adopted by the canonical ContentRegistrar. ProfileService is the sole application service for profile CRUD and relationships. Relationships use canonical typed post metadata and lifecycle cleanup; public REST is allowlisted. No custom Phase-6 table or second field/capability architecture exists. Optional integrations remain optional.

## Phase 7 lead workflow closure (2026-09-02)

Phase 7 locks one canonical Lead Engine, `Leads\LeadService`, with `Requests\RequestService` as its thin child-request facade. `SiteVisitService` is a separate workflow linked to a Lead, not a second lead system. Forms, REST, and future Elementor/Mayfair adapters are transport boundaries into the same application services. Migration 004 owns private leads, requests, site visits, histories, and the provider-backed notification outbox. Strict validation, ownership/capability/nonce boundaries, allowlisted serialization, replay-safe dedupe, state-transition history, privacy erasure, profile/property/user cleanup, diagnostics, and reproducibility are implemented. The local Phase 7 gate is PASS; unavailable native databases, PHPStan, WP-CLI, real Mayfair/ACF/Elementor/browser environments, CI, and external delivery remain NOT VERIFIED. Phase 8 was subsequently authorized under the Phase 8 master prompt.

## Phase 8 Elementor adapter closure (2026-09-03)

Phase 8 adds a thin, optional Elementor adapter over canonical REP services. `ElementorIntegration` conditionally composes `PublicContext`, `QueryAdapter`, and the optional `LeadFormAction`; core does not depend on Elementor, Elementor Pro, ACF, or a frontend framework. Dynamic tags use the official manager/group contracts and expose only published, allowlisted, escaped Property, Project, Agent, Agency, and Insight values. Query IDs use the documented `elementor/query/{query_id}` actions; Property queries delegate to `SearchRequest`, while entity adapters enforce public post bounds. Pro forms delegate to `RequestService` and the single Lead Engine.

No Elementor document/template/widget/content rewrite, duplicate search/lead/privacy logic, direct adapter SQL, REST route, or schema migration was introduced. Schema 004 remains authoritative. The local Phase 8 harness and Phase 2–7 evidence remain historical regression inputs. Phase 9 is authorized and adds only the bounded provider-neutral import/export subsystem described in `PHASE-9-IMPORT-EXPORT.md`; Elementor remains unchanged and is not a dependency.

## Phase 9 import/export architecture lock (2026-09-03)

Phase 9 is authorized for version 0.9.0. Its provider-neutral CSV/JSON boundary is `SourceParser -> ImportRow -> ImportService` and `SchemaCatalog -> ExportService`. The catalog derives public/editorial field and taxonomy allowlists from the canonical registries. The import service supports bounded parsing, strict pre-sanitize type checks, exact ID/slug/reference identity, create-only/upsert/update-only decisions, visible conflicts, opt-in missing-term creation, existing attachment IDs/URLs, SSRF-safe opt-in remote media, and only the existing Agent → Agency and Property → Agent/Agency relationships. It does not invent Property → Project.

Validation and dry-run are read-only and produce deterministic reports. Execute builds the complete plan before mutating; preflight findings prevent all planned writes. Canonical WordPress editorial primitives and ProfileService perform writes, with row-level snapshots/compensation and post-identity verification. Exports are fixed-order UTF-8 CSV/JSON, formula-safe, path-safe, and allowlisted; private lead/request/visit/notification/authentication/security data is excluded. WP-CLI is the sole current transport. No persistent import job/checkpoint/history schema or migration 005 is introduced.

## Phase 9 local gate closure (2026-09-03)

The 0.9.0 Phase 9 local gate is **PASS**. The PHP 8.1–8.3 / WordPress 6.4 / SQLite runtime, dedicated security/privacy/data-integrity harnesses, 2,806-file syntax matrix, PHPUnit (44 tests/341 assertions/one integration skip), PHPCS/WPCS, current Phase 2–8 component regressions, two-build reproducibility check, and clean extracted-package matrix passed. The final artifact is `dist/realestate-platform-0.9.0.zip`, 116 files, 140,466 bytes, SHA-256 `4c8441eb002a85e6435b0031dee9f84e5249309e1dc6c52a7154f4d4e24f9f9b`.

PHPStan remains **NOT VERIFIED** because the unchanged configured project analysis exhausted the 256 MiB Playground memory budget in `FileReader.php`. Native MySQL/MariaDB, native WP-CLI, real Mayfair/ACF/Elementor/browser/editor, allowed-provider remote-media success, external CI/providers, production infrastructure, 100k-scale performance, and durable process-death resume remain **NOT VERIFIED**. Phase 10 remains locked; no Phase 10 code was started.
