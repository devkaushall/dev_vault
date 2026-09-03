# Source of Truth

Version 0.9.0, 2026-09-03. Canonical plugin identity: **RealEstate Platform**; slug/text domain `realestate-platform`; PHP namespace `Mayfair\RealEstatePlatform`; source `plugins/realestate-platform`; REST namespace `realestate-platform/v1`; current version 0.9.0; PHP ≥8.1; WordPress ≥6.4. WordPress 6.4 is the declared minimum; locally executable PHP 8.1–8.3 / WordPress 6.4 evidence is recorded in the phase reports, while native-database, CI, and external integration claims remain NOT VERIFIED.

Canonical Phase-0 feature matrix is `docs/research/feature-parity-matrix.md`. The duplicate uppercase file was deleted. Canonical architecture is `docs/architecture/MASTER-ARCHITECTURE.md`, refined by accepted ADRs. This file governs identity/contracts.

Canonical model: WP posts/meta/terms/media for editorial entities; `{prefix}rep_*` tables for private/operational data and derived indexes. The migration chain is now `001`–`004`; migration `004` owns private lead, request, site-visit, history, and notification-outbox data. REST v1 contains the authenticated status, property/search/profile/user-feature contracts and the Phase 7 public submission plus authenticated workflow contracts. Phase 8 adds an optional Elementor adapter only; Phase 9 adds provider-neutral import/export services without a REST route or persistent import state. Elementor has no core dependency and the REST contract is unchanged. Modules depend inward on Contracts/Core; optional adapters never become core dependencies.

Modes: Standalone, Compatibility, Migration. Phase 1 only detects; it does not register real-estate CPTs or migrate. Compatibility preserves existing IDs, slugs, media, metadata, URLs and Elementor documents. Multisite is explicitly unsupported in 0.x. Schema migrations are forward-only; restore relies on pre-upgrade backup and migration-specific documented remediation.

## Phase status — 2026-08-30

Phase 1 version 0.1.0 is a **frozen release candidate**. All locally executable quality, SQLite/Playground lifecycle, PHP 8.1–8.3, and package gates are green. MySQL 8.4, MariaDB 11.4, controlled real-database migration failure/recovery, the complete uninstall matrix, dependent security closure, real Mayfair Core/Forms & Leads compatibility, and external GitHub Actions remain transparently **NOT VERIFIED**. Consequently, the Phase 1 gate remains FAIL (externally blocked); this is not a production-readiness claim.

Phase 2 was authorized under Model B by Dev effective 30 August 2026, with external validation due by 30 September 2026 or before an earlier production release. Phase 1 external debt remains NOT VERIFIED and no production migration is authorized. Phase 2 version 0.2.0 adds the Property, Project, Insight, canonical field, taxonomy, location, media-reference, optional read-only ACF adapter, native REST, and capability data-layer foundations described in `docs/architecture/PHASE-2-DATA-MODEL.md`. Search, maps, leads, commerce, and advanced Elementor integration remained unimplemented at that historical phase.

## Phase 2 final-verification status

Phase 2 version 0.2.0 is complete and PASS as of 30 August 2026. The REST root cause and correction are recorded in `docs/testing/PHASE-2-REST-ROOT-CAUSE.md`; final evidence and package hash are in `docs/testing/PHASE-2-FINAL-VERIFICATION-REPORT.md`. Real ACF, real Mayfair, native MySQL/MariaDB, browser UI, and Phase 1 External Verification remain explicitly NOT VERIFIED.

## Phase 3 authorization

Phase 3 Search and Advanced Property Discovery is authorized as version 0.3.0 work. Its governing design is `docs/architecture/PHASE-3-SEARCH-ARCHITECTURE.md`. It must use the Phase-2 Property model and the Phase-1 migration/composition boundaries. Maps, favorites, saved searches, compare, agents/agencies, leads/forms/visits, imports, commerce, MLS/RESO, PDF, analytics, and advanced Elementor widgets remain excluded. Phase 4 is LOCKED.

## Phase 3 REST/AJAX/URL checkpoint — 2026-08-31

Version 0.3.0 now has a verified local public read-only Property search contract at `GET /realestate-platform/v1/properties`, a nonce-protected same-origin AJAX adapter, and deterministic URL state. All adapters use the shared `SearchRequest`, `SearchCriteria`, `SearchEngine`, and indexed provider. Results are bounded and public-only; arbitrary filters and sorts are rejected. SQLite executable evidence is recorded in `docs/testing/PHASE-3-REST-SEARCH.md`. Phase 3 remains in progress pending final security, integrity, performance, regression and packaging. External debts and Phase 4 lock remain unchanged.

## Phase 3 final gate — 2026-08-31

Phase 3 version 0.3.0 passed all mandatory locally executable release gates. The reproducible package is `dist/realestate-platform-0.3.0.zip`, SHA-256 `1cc091b50f0622ceb8b535d7c72013ea03870ef6145e2517bf7a637620726ea7`. External debt remains NOT VERIFIED exactly as recorded in `docs/testing/PHASE-3-FINAL-VERIFICATION-REPORT.md`. Phase 4 remains LOCKED and requires separate authorization.

## Phase 4 final gate — 2026-08-31

Phase 4 version 0.4.0 passed mandatory locally executable maps/geospatial gates. Canonical coordinates remain `rep_latitude`/`rep_longitude`; migration 002 projection is reused and no Phase-4 migration exists. Public geo search, privacy-safe bounded markers, provider abstractions, secure future geocoder transport and geo diagnostics are governed by the Phase-4 architecture documents. Artifact: `dist/realestate-platform-0.4.0.zip`, SHA-256 `c5d57be0d54fb492e808974c361c9f697c631cb485ecd0f7dc6071eb905edbd7`. External debt remains NOT VERIFIED. Phase 5 is LOCKED.

## Phase 5 and Phase 6 closure — 2026-09-02

Phase 5 version 0.5.0 passed its local gate. Phase 6 version 0.6.0 adds canonical WordPress Agent and Agency editorial entities through the existing content/field/capability architecture, one `ProfileService`, owner-scoped application operations, bounded REST, explicit public serialization, relationships, privacy handling, and lifecycle cleanup. No schema migration beyond 003 was required. External debt remains NOT VERIFIED. Phase 7 was then authorized under the master prompt and is now complete for its locally executable scope.

The Phase-6 hardening cycle added shared strict positive-ID parsing, enforced Agent-to-Agency consistency before Property assignment, blocked Agency deletion while either Agents or Properties reference it, and exposed authenticated Property relationship removal. The updated Phase-6 regression suite, PHP matrix and clean-package runtime passed; the Phase-6 local gate remains PASS.

## Phase 7 preflight and architecture lock — 2026-09-02

The repository and available artifacts were audited before implementation. No executable Lead/Form/Request/Site Visit engine, `mpfl_*` hook, or Mayfair Forms & Leads artifact was found. The Phase 5 saved-search alert service is not a lead engine. Phase 7 therefore implements one canonical native `Leads\\LeadService`, with `Requests\\RequestService` as its form-submission facade, indexed operational tables in migration 004, a SiteVisit state machine, and an asynchronous notification outbox. Real Mayfair compatibility remains NOT VERIFIED; a future adapter may be added only after authorized real artifacts and contracts are supplied. See `docs/architecture/PHASE-7-PREFLIGHT-AUDIT.md`.

## Phase 7 final local gate — 2026-09-02

Phase 7 version 0.7.0 / schema 004 passed all locally executable gates: PHPCS/WPCS, PHPUnit, PHP 8.1–8.3 syntax, clean WordPress Playground SQLite runtime on PHP 8.1–8.3, workflow security/privacy/diagnostic checks, migration 003 → 004 upgrade, final Phase 6 regression, reproducible packaging, and clean extracted-package runtime. The package SHA-256 is `81858aaa145b7005b8192dd8af582360a69e9f26ae0c5c7e74574e0db60afe3b`; detailed evidence is in `docs/testing/PHASE-7-GATE-REPORT.md`. Native MySQL/MariaDB, PHPStan at the required budget, WP-CLI, real Mayfair/ACF/Elementor/browser environments, CI, and external delivery remain NOT VERIFIED. Phase 8 was then authorized and is recorded below.


## Phase 8 preflight, implementation, and local gate — 2026-09-03

Phase 8 was authorized after auditing the Phase 7 baseline and confirming that Elementor, Elementor Pro, ACF, browser/editor, Theme Builder, Loop Grid, Mayfair, native database, CI, and production artifacts were unavailable. The implementation is an optional thin adapter in `src/Elementor/` over the canonical `FieldRegistry`, `ProfileService`, `SearchRequest`, `RequestService`, `LeadService`, `CoordinatePrivacy`, and `PublicSubmissionRateLimiter`. It uses the official dynamic-tag manager and `elementor/query/{query_id}` action contracts, stable public tags for Property, Project, Agent, Agency, and Insight, bounded query IDs, and one optional Pro form action. Existing Elementor documents, templates, widget IDs, URLs, media, and content are never rewritten.

No schema migration was required; schema 004 remains current. The post-official-action PHP 8.3 contract harness passed all 40 checks, and syntax, PHPUnit, PHPCS/WPCS, and Phase 2–7 regressions passed. PHPStan at the configured project scope is **NOT VERIFIED** because the constrained Playground runtime exhausted 256M and 512M and terminated the 1024M attempt; configuration was not weakened. Real vendor/browser/ACF/Mayfair/CI/native database/production verification remains **NOT VERIFIED**. Architecture, API, REST, implementation, QA, security, privacy, performance, gate, and final verification records are in the `PHASE-8-*` documents.

The reproducible 0.8.0 artifact is retained as historical Phase 8 evidence: `dist/realestate-platform-0.8.0.zip` (108 files, 115,972 bytes, SHA-256 `9b5d1d66df1c425976945819a818f688a6540f5039370f2ceb0f9fab1d5ef971`). Phase 9 was subsequently authorized and is recorded below; the current 0.9.0 artifact and gate are authoritative for the current release.

## Phase 9 preflight, implementation, and gate status — 2026-09-03

Phase 9 was authorized after the mandatory preflight audit in `docs/architecture/PHASE-9-PREFLIGHT-AUDIT.md`. The audit confirmed reuse of the canonical FieldRegistry, ContentRegistrar boundary, TaxonomyRegistry, MediaService, ProfileService, WordPress editorial services, Security primitives, CLI composition, REST architecture, and PrivacyFoundation. It also confirmed that import/export engines, bounded parsers, normalized DTOs, validation/planning/execution, taxonomy/relationship resolution, safe media import, allowlisted exports, reports, transports, and focused tests were absent. The implementation contract is `docs/architecture/PHASE-9-IMPORT-EXPORT.md`.

Version 0.9.0 adds `SchemaCatalog`, `SourceParser`, `ImportRow`, `ImportReport`, `ImportService`, `ExportSerializer`, `ExportService`, and `RemoteMediaImporter`. The only current transport is the capability-protected WP-CLI boundary: `realestate import validate`, `realestate import dry-run`, `realestate import execute`, and `realestate export`. The accepted entities are exactly Property, Project, Insight, Agent, and Agency. Exact IDs/slugs/canonical Property references drive deterministic create-only/upsert/update-only decisions. Validation and dry-run perform no persistent writes; execution preflights the complete plan, writes only through canonical WordPress/ProfileService boundaries, and compensates failed rows. Only Agent → Agency and Property → Agent/Agency relationships are supported; Property → Project is not invented. Schema 004 remains authoritative and migration 005 is not created.

Exports are fixed-order UTF-8 CSV/JSON, formula-safe, output-path-safe, overwrite-protected, and limited to public/editorial allowlists. Leads, requests, visits, notifications, authentication/private user data, private notes, credentials, and security data are excluded. Existing attachment IDs/local URLs are reused. Remote media is opt-in, HTTPS-only, bounded, MIME-checked, redirect-rejecting, and SSRF-checked; inability to verify is reported as NOT VERIFIED rather than downloaded. No real Mayfair mapping is claimed.

The Phase 9 local gate is closed PASS for version 0.9.0. The current artifact is `dist/realestate-platform-0.9.0.zip` (116 files, 140,466 bytes, SHA-256 `4c8441eb002a85e6435b0031dee9f84e5249309e1dc6c52a7154f4d4e24f9f9b`); two builds were byte-identical and the clean extracted package passed the PHP 8.1–8.3 / WordPress 6.4 / SQLite runtime matrix. Dedicated security, privacy, data-integrity, parser, performance, syntax, PHPUnit, PHPCS/WPCS, and current Phase 2–8 component regressions passed. PHPStan remains NOT VERIFIED after unchanged-configuration 256M exhaustion in `FileReader.php`. Native MySQL/MariaDB, native WP-CLI, real Mayfair/ACF/Elementor/browser/editor, allowed-provider remote-media success, external CI/providers, production infrastructure, 100k-scale benchmarks, and durable process-death resume remain NOT VERIFIED. Phase 10 remains locked and no Phase 10 code was started.
