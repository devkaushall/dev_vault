# Phase 8 implementation report

**Release:** 0.8.0  
**Database:** schema 004 retained; no migration 005  
**Implementation date:** 2026-09-03

## Implemented files

- `src/Elementor/ElementorIntegration.php` — conditional composition root and official hook registration.
- `src/Elementor/TagCatalog.php` — stable public Property, Project, Agent, Agency, and Insight catalog.
- `src/Elementor/PublicContext.php` — publication, field, relation, attachment, taxonomy, and coordinate privacy resolver.
- `src/Elementor/PublicFieldTag.php` — official dynamic-tag object with contextual escaping.
- `src/Elementor/QueryAdapter.php` — official custom-query action adapters and canonical Property `SearchRequest` delegation.
- `src/Elementor/LeadFormAction.php` — optional Elementor Pro form transport into `RequestService`.
- `tests/Unit/ElementorAdapterTest.php` — optional-runtime, catalog, and bounded-input unit coverage.
- `scripts/phase8-runner.php` and `scripts/phase8.mjs` — executable WordPress Playground contract, security, privacy, integrity, performance, and no-direct-SQL harness.

## Core wiring

`Bootstrap` registers the `elementor` service lazily from existing canonical services. The service is harmless when vendor runtimes are missing. No Composer package, schema change, REST route, CPT, taxonomy, field definition, or core service was duplicated for Elementor.

## Official contract correction

The custom query integration was initially exercised through a filter-shaped fixture invocation. After checking Elementor’s official documentation, registration and execution were corrected to the documented action contract: `add_action( 'elementor/query/{id}', ... )` and `do_action()` in the executable fixture. The post-correction PHP 8.3 Phase 8 run passed all 40 checks.

## Security and integrity choices

- Public tag values are resolved only from published, type-matching contexts.
- Field exposure requires canonical `FieldDefinition` flags.
- Profile data is read via `ProfileService` and cannot leak private notes.
- Search/query inputs are allowlisted and bounded.
- No adapter file contains direct `$wpdb` access, workflow-table writes, or duplicate SQL.
- Pro form values are a transport DTO only; `RequestService` and `LeadService` remain authoritative.
- Existing Elementor documents/templates/widget IDs are never read-modified-written.

## Evidence result

`scripts/phase8.mjs` returned `PASS` with all 40 checks true on PHP 8.1.34, 8.2.32, and 8.3.32 with WordPress 6.4.10 and SQLite. Actual Elementor, Elementor Pro, ACF, browser/editor, Theme Builder, Loop Grid, Mayfair, CI, native database, and production environments were unavailable and remain **NOT VERIFIED**.
