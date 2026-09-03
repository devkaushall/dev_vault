# Phase 9 preflight audit — Import / Export Engine

**Audit date:** 2026-09-03  
**Authorized target:** RealEstate Platform 0.9.0  
**Verified baseline:** 0.8.0 / schema 004 / Phase 7 PASS / Phase 8 local gate CLOSED-PASS

## Authorization and audit boundary

Phase 9 was authorized after the Phase 8 local gate closed. This audit was completed before changing plugin implementation code. The current source of truth is `docs/SOURCE-OF-TRUTH.md`; the cross-cutting architecture is `docs/architecture/MASTER-ARCHITECTURE.md`. Phase 10 and all later phases remain unauthorized.

The audit covered the Phase 2–8 architecture and final verification documents, the Phase-0 research documents named by the authorization, the plugin source/tests/migrations, CLI and REST adapters, and the existing Elementor adapter. No real Mayfair, ACF, Elementor, native WP-CLI, MySQL/MariaDB, browser, CI, or production artifacts are available in this workspace.

## What already exists

### Canonical content and fields

- `ContentRegistrar` owns/adopts `property`, `project`, `insight`, `agent`, and `agency` post types according to Standalone/Compatibility behavior.
- `FieldRegistry` is the one canonical field definition source. It currently defines typed fields for references, price/currency, address/location, coordinates, property characteristics, project fields, media references, Agent/Agency public/private profile values, and Insight values.
- `FieldDefinition` supplies type, entity scope, defaults, REST exposure, searchable/filterable/sortable flags, sanitization, validation, and privacy/exposure flags.
- `TaxonomyRegistry` owns the approved taxonomy set. The repository does not contain an import/export taxonomy registry or arbitrary-taxonomy pathway.
- `MediaService` validates attachment IDs and normalizes image galleries, but it does not currently download or sideload remote media.

### Canonical application services

- `ProfileService` is the application boundary for Agent/Agency CRUD and relationships.
- Core editorial writes currently use WordPress post/meta/term primitives through `ContentRegistrar`/REST and `ProfileService`; no import-specific write service exists.
- Search is a disposable projection. `SearchIndexWriter`, `SearchIndexRebuilder`, and `SearchRequest` are reusable after canonical writes, but import must not write projection tables directly.
- `RequestService`/`LeadService`/`SiteVisitService` remain private Phase 7 workflows. They are not export targets.
- `PrivacyFoundation` implements user/workflow export and erasure; it is not a content export engine.
- `CompatibilityDetector` reports Mayfair/ACF/Elementor availability. There is no real Mayfair field mapping artifact or import mapping implementation.

### Existing transports and operations

- REST controllers exist for public/editorial reads, authenticated profiles/user features, search/maps, and private Phase 7 workflows. There are no import/export endpoints.
- `CLI\\Commands` exposes `realestate status` and search-index status/rebuild only. There are no import/export commands.
- `ElementorIntegration` is optional and must remain unchanged in architecture and behavior. It is not an import/export path.
- Existing migrations 001–004 are forward-only and integrity-checked. There is no import job/checkpoint/history table.
- Existing security primitives include `StrictId`, `Security`, prepared WordPress database access, nonce/capability/ownership checks, public rate limiting, URL/SSRF validation for geospatial HTTP, and contextual output escaping.

## What is missing

1. A provider-neutral parser abstraction for bounded CSV and JSON.
2. A canonical import schema/allowlist derived from `FieldRegistry`, approved taxonomies, supported relationships, and explicit identity fields.
3. Normalized row DTOs and deterministic validation errors/warnings.
4. Validate, dry-run, create-only, upsert/update, and import planning/execution modes.
5. Deterministic identity resolution without fuzzy destructive matching.
6. Canonical term reference resolution and explicitly opt-in missing-term creation.
7. Relationship preflight and canonical relationship writes for supported existing relationships.
8. Safe remote-media handling; existing `MediaService` only handles attachment references.
9. Allowlisted CSV/JSON editorial exports with deterministic ordering and CSV formula-injection protection.
10. Restartable, bounded execution/reporting and interruption/retry semantics.
11. Import/export capability, nonce, REST, and CLI boundaries.
12. Focused tests, integrity snapshots, security audit, performance harness, package smoke test, and reproducibility evidence.

## Reusable components

| Requirement | Reuse |
|---|---|
| Entity scope | `ContentRegistrar::entities()` and `FieldRegistry::forEntity()` |
| Field type/sanitization/validation | `FieldDefinition` and `ContentRegistrar` validation rules |
| IDs | `Security\\StrictId` |
| Profile writes/relationships | `ProfileService` |
| Public fields/URLs | `FieldRegistry` flags and existing public serializers |
| Attachment IDs | `MediaService::validAttachment()` / `normalizeGallery()` |
| Remote URL deny rules | `Security::validateRemoteUrl()` plus bounded HTTP patterns from `SecureGeoHttpClient` |
| Post visibility/index consistency | canonical WordPress writes followed by existing index synchronization/rebuild |
| Authorization/nonce | existing REST permission callbacks and `Security`/capability conventions |
| Logging/diagnostics | `OptionLogger`, `DiagnosticsRunner`, existing read-only diagnostics |
| Privacy | `PrivacyFoundation` and its explicit private workflow boundary |
| CLI composition | `CLI\\Commands` and `ServiceRegistry` |
| Optional integrations | Phase 8’s no-hard-dependency pattern; no change to Elementor code required |

## Mayfair and compatibility findings

No Mayfair field names, IDs, taxonomies, or import mappings are invented. The current implementation will accept only canonical REP names and explicitly documented aliases if an alias is later added from an authorized artifact. Compatibility mode continues to adopt existing registrations without destructive takeover. A source column named like an unverified `mpd_*` field is rejected or reported as unknown rather than guessed.

## Schema 005 decision

**Schema 005 is not required for the minimum Phase 9 scope and will not be created.** Validate, dry-run, plan, execute, report, and export can be bounded in-process without persistent import jobs or checkpoints. The initial implementation will use deterministic plans and row-level reports in memory/file output, with canonical WordPress writes and post-import verification. It will not claim resumability across process death as a durable job feature; it will support deterministic re-run of the same plan and safe create-only/upsert identity decisions.

A future persistent queue/checkpoint requirement would need separate authorization and a genuinely required migration 005. No empty migration is acceptable.

## Required additions

- `ImportExport` domain/application classes for schemas, parsers, normalization, validation, planning, execution, reports, serializers, and bounded file handling.
- Service registration in `Bootstrap` without changing Phase 8 integration.
- Admin-only REST/CLI transport only if the canonical service can enforce the same authorization and bounded inputs.
- Focused unit and WordPress Playground contract harnesses.
- Phase 9 documentation and machine-readable evidence.

The preferred implementation writes editorial content through a dedicated `ContentImportService` that reuses `FieldRegistry`, `ContentRegistrar` validation, `ProfileService` for profiles/relationships, WordPress post/term APIs for the actual editorial primitives, and existing index lifecycle hooks. It will not write `rep_*` operational tables or accept arbitrary meta/options/capabilities.

## External NOT VERIFIED status

The following remain unavailable and must not be represented as PASS:

- native MySQL/MariaDB and controlled native-database failure recovery;
- native WP-CLI binary runtime;
- real Mayfair Core, Forms & Leads, field/taxonomy mappings, and production data;
- real ACF/ACF Pro, Elementor/Elementor Pro runtimes and browser/editor UI;
- remote media provider behavior against real networks;
- external CI/GitHub Actions;
- external email providers and production infrastructure;
- production-scale 100k import/export performance;
- legal/retention approval for any customer or personal data export.

Local fake fixtures may prove only REP’s own parser, planner, security, and canonical-service contracts.

## Preflight decision

The repository is suitable for a bounded CSV/JSON editorial Import/Export subsystem without a schema migration. Implementation may begin with the canonical field/entity/relationship boundaries above. The Phase 8 Elementor adapter is not redesigned or coupled to import/export. The hard stop remains: any failed local gate must be fixed and rerun before Phase 9 can close.
