# Phase 9 implementation report

**Release:** 0.9.0  
**Status:** implemented; local gate closed  
**Date:** 2026-09-03

## Delivered modules

The implementation is under `plugins/realestate-platform/src/ImportExport/`:

- `SourceParser` — bounded CSV/JSON input with UTF-8, shape, size, row, column, cell, depth, duplicate-header, and duplicate-normalized-key controls;
- `ImportRow` — normalized row DTO carrying source line, raw values, normalized values, errors, and warnings;
- `SchemaCatalog` — entity, field, taxonomy, media, relationship, import, and export allowlists derived from canonical registries;
- `ImportReport` — deterministic status, mutation, counts, row decisions, warnings, and errors, including skipped-row accounting;
- `ImportService` — validation, dry-run, exact identity planning, taxonomy/media/relationship validation, canonical writes, verification, and compensation;
- `ExportSerializer` — stable UTF-8 CSV/JSON serialization and formula-safe CSV values;
- `ExportService` — public/editorial row selection, stable ID ordering, capability-gated nonpublic output, safe staging paths, bounded file output, and checksums; and
- `RemoteMediaImporter` — explicit opt-in HTTPS media validation/sideload boundary that fails closed on unsafe or unverifiable URLs.

## Composition and transports

`Bootstrap` registers `SchemaCatalog`, `SourceParser`, `ImportService`, `ExportService`, and `RemoteMediaImporter` without changing the Phase 8 adapter boundary. `CLI\Commands` registers:

```text
realestate import validate
realestate import dry-run
realestate import execute
realestate export
```

No REST route, AJAX route, admin UI, frontend framework, or second lead/search/import system was introduced.

## Canonical write policy

- Property, Project, and Insight use WordPress post/meta/term/media primitives already governed by `ContentRegistrar` and `FieldRegistry`.
- Agent and Agency use `ProfileService` for profile CRUD and the supported Agent → Agency relationship.
- Property → Agent/Agency is applied through the existing profile relationship boundary after consistency validation.
- Search projections and Phase 7 private/operational tables are never normal-path import targets.
- No arbitrary SQL, options, capabilities, post types, taxonomies, PHP, filesystem path, or private-data field is accepted.

## Determinism and failure policy

Exact positive ID, sanitized slug, and canonical reference identities are resolved in a fixed order. Create-only, upsert, and update-only decisions are visible before mutation. Validate and dry-run are read-only. Execute builds a complete plan before writing; any preflight error causes otherwise-valid rows to be reported as skipped and leaves the batch unchanged. Executed rows are verified and compensated on an observed write failure. Schema 004 is retained; no migration 005 or durable import job was needed.

## Export policy

The same catalog controls both formats. Columns and JSON keys are stable, rows are ordered by ascending WordPress ID, public output defaults to published records, and nonpublic output requires explicit elevated capability. Private workflow/user/security data is outside the export schema. CSV formula-leading values are prefixed, file paths are restricted to the uploads staging directory, overwrites require explicit force, and output is bounded to 32 MiB.

## Verification reference

The implementation passed the Phase 9 runtime, security, privacy, data-integrity, syntax, PHPUnit, PHPCS/WPCS, package, and current Phase 2–8 component regression gates recorded in `docs/testing/PHASE-9-FINAL-VERIFICATION-REPORT.md`. PHPStan, native databases/WP-CLI, real external integrations, provider success paths, and production-scale/concurrent/process-death behavior remain explicitly NOT VERIFIED.
