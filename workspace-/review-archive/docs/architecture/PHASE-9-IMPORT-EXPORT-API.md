# Phase 9 Import/Export API

**Version:** 0.9.0  
**Status:** implemented contract  
**Date:** 2026-09-03  
**Scope:** provider-neutral CSV/JSON import and export for the canonical Property, Project, Insight, Agent, and Agency entities.

## 1. Boundary

The public operational boundary is WP-CLI. The application boundary is deliberately transport-neutral and is composed by `Bootstrap` under these service keys:

- `imports` → `ImportService`;
- `exports` → `ExportService`;
- `schema` → `SchemaCatalog`;
- `import_parser` → `SourceParser`;
- `remote_media` → `RemoteMediaImporter`.

There is no Phase 9 REST route, AJAX route, admin form, or frontend dependency. A future transport must call the same services and preserve the same actor binding, capability, limits, report shape, and zero-mutation guarantees.

The implementation does not depend on Elementor, Astra, React, ACF, Mayfair, or a browser. WordPress editorial APIs and `ProfileService` remain the canonical write boundaries. Search indexes and Phase 7 private workflow tables are derived or private and are not import/export targets.

## 2. Application service contracts

### ImportService

```php
public function runContent(
    string $mode,
    string $entity,
    string $format,
    string $contents,
    array $options,
    int $actor_id
): array|WP_Error;

public function runFile(
    string $mode,
    string $entity,
    string $format,
    string $relative_path,
    array $options,
    int $actor_id
): array|WP_Error;
```

`$mode` is one of `validate`, `dry_run`, or `import`. `runContent()` is useful to tests and an adapter that already owns bounded content. `runFile()` accepts only a relative path below the plugin upload staging directory (`wp-content/uploads/realestate-platform-imports`) and parses the file after a safe-path check.

Import authorization requires all of the following:

1. `actor_id` is positive;
2. `actor_id` is the current WordPress user; and
3. the current user has `manage_realestate_imports` and the entity edit capability (`edit_properties`, `edit_projects`, `edit_insights`, `edit_agents`, or `edit_agencies`).

The caller cannot supply a different actor, bypass the capability check, or use an arbitrary post type.

### ExportService

```php
public function content(
    string $entity,
    string $format,
    int $actor_id,
    array $options = []
): string|WP_Error;

public function writeFile(
    string $entity,
    string $format,
    string $relative_path,
    int $actor_id,
    array $options = []
): array|WP_Error;
```

Export authorization requires the current actor, `manage_realestate_exports`, the entity edit capability, and—only when `include_nonpublic` is true—`manage_realestate`. Default exports contain published content only. `writeFile()` writes only below the same staging directory, requires a matching `.csv` or `.json` extension, refuses overwrite unless `overwrite` is explicit, and enforces the bounded 32 MiB output limit.

`content()` is a bounded convenience result. It materializes the selected result before applying its output-size check; `writeFile()` is the streaming-oriented path and enforces the limit per chunk. Neither path claims unbounded or constant-memory export.

## 3. Supported entities and identity

The entity allowlist is exactly:

| Entity | WordPress type | Exact identity inputs | Supported relationship columns |
|---|---|---|---|
| Property | `property` | positive `id`, canonical `slug`, canonical `reference` | `relationship_agent_id`, `relationship_agency_id` |
| Project | `project` | positive `id`, canonical `slug`, canonical `reference` | none |
| Insight | `insight` | positive `id`, canonical `slug`, canonical `reference` | none |
| Agent | `agent` | positive `id`, canonical `slug`, canonical `reference` | `relationship_agency_id` |
| Agency | `agency` | positive `id`, canonical `slug`, canonical `reference` | none |

Identity is exact and deterministic: positive ID first, then exact sanitized slug, then exact canonical reference. A source row without an identity is a create decision; an existing exact identity is an update decision for `upsert`; existing identity under `create_only` is a visible conflict; missing identity under `update_only` is a visible conflict. No fuzzy title, email, partial string, or destructive guess is used.

Property-to-Project is intentionally not offered because it is not a canonical relationship in the existing model. No Mayfair or `mpd_*` mapping is invented.

## 4. Formats and parser limits

- CSV is UTF-8, has one canonical header row, uses comma delimiters, and rejects duplicate/non-canonical headers and row width mismatches.
- JSON accepts an object, an array of row objects, or `{ "columns": [...], "rows": [...] }`.
- JSON object keys are canonical, case-normalized field names. Duplicate keys after normalization are rejected before planning.
- JSON nesting depth is capped at 32.
- source bytes: 16 MiB maximum;
- rows: 10,000 maximum;
- columns: 128 maximum;
- cell bytes: 65,535 maximum.

Malformed, invalid-UTF-8, oversized, deeply nested, duplicate-key, or non-object input returns a structured report/error and never starts a write.

## 5. Import options

```text
--strategy=upsert|create_only|update_only
--create-missing-terms
--allow-remote-media
```

At the application boundary the corresponding keys are `strategy`, `create_missing_terms`, and `allow_remote_media`. Boolean options must be real booleans or their explicit `true`/`false` forms. Missing taxonomy terms are errors by default; `create_missing_terms` is explicit opt-in and remains read-only in validate/dry-run modes. Remote media is explicit opt-in and is never downloaded unless the URL passes all safety checks.

## 6. Import pipeline

```text
bounded source
  → SourceParser
  → ImportRow(raw, normalized, errors, warnings)
  → allowlist/type/sanitization/taxonomy/relationship/media validation
  → deterministic identity plan
  → complete-plan preflight
  → canonical WordPress/ProfileService writes
  → row verification and compensation on failure
  → ImportReport
```

Strict type checks happen before sanitization. Fields are derived from `FieldRegistry` and taxonomy names from `TaxonomyRegistry`; arbitrary meta, options, capabilities, post types, taxonomies, executable PHP, and operational table names are not accepted. Existing attachments are validated by `MediaService`. Profile writes and the Agent → Agency relationship use `ProfileService`; editorial post/meta/term writes use WordPress primitives already governed by the content model.

`validate` and `dry_run` report the same decisions without persistent mutations. `import` first plans every row. If any row, taxonomy, relationship, media, or source-level finding makes the complete plan invalid, valid rows are reported as `skipped` and no row is written. Execution snapshots each affected row, writes in the canonical order, verifies identity/content, and compensates a failed row. The report makes create, update, conflict, skipped, imported, and failed decisions visible.

## 7. Report envelope

All successful service calls return an array shaped like:

```json
{
  "status": "PASS",
  "mutation": "NONE",
  "entity": "property",
  "mode": "dry_run",
  "strategy": "upsert",
  "format": "json",
  "counts": {
    "total": 1,
    "valid": 1,
    "invalid": 0,
    "create": 1,
    "update": 0,
    "conflict": 0,
    "skipped": 0,
    "imported": 0,
    "failed": 0,
    "taxonomy_issues": 0,
    "relationship_issues": 0,
    "media_issues": 0,
    "warning_count": 0,
    "error_count": 0
  },
  "rows": [
    {
      "line": 1,
      "status": "create",
      "decision": "create",
      "id": null,
      "identity": "slug=phase9-home; reference=P9-001",
      "errors": [],
      "warnings": []
    }
  ],
  "warnings": [],
  "errors": []
}
```

`mutation` is `NONE` for validate and dry-run. An execute/import report uses `APPLIED` only when at least one row was imported. A failed preflight explicitly includes the reason valid rows were skipped. Row errors and warnings are bounded to prevent unbounded report growth.

## 8. Export contract

`SchemaCatalog::exportColumns()` is the single fixed-order column list for both CSV and JSON. It contains common editorial columns (`id`, `slug`, `title`, `content`, `excerpt`, `status`), public registry fields, approved taxonomy ID columns, safe attachment ID/URL columns, featured-image ID/URL columns, and supported relationship IDs. Rows are ordered by WordPress ID ascending. JSON includes `entity`, `columns`, and `rows`; CSV begins with the exact column header.

Only fields marked public/editorial by the canonical registry are exported. Private notes, leads, requests, visits, notification events, authentication data, private user data, credentials, internal security data, and arbitrary post/meta values are excluded. CSV values beginning with `=`, `+`, `-`, or `@` receive a leading apostrophe to prevent spreadsheet formula execution. Invalid UTF-8 is normalized/rejected at the serializer boundary.

## 9. WP-CLI commands

All commands require a logged-in WP-CLI user with the relevant capability and emit JSON reports.

```bash
wp realestate import validate \
  --entity=property --file=inventory.csv --format=csv \
  --strategy=upsert

wp realestate import dry-run \
  --entity=property --file=inventory.json --format=json \
  --strategy=create_only --create-missing-terms

wp realestate import execute \
  --entity=property --file=inventory.json --format=json \
  --strategy=upsert --allow-remote-media

wp realestate export \
  --entity=property --file=property.csv --format=csv --limit=1000

wp realestate export \
  --entity=property --file=property.json --format=json \
  --include-nonpublic --force
```

The CLI maps `--create-missing-terms`, `--allow-remote-media`, `--include-nonpublic`, and `--force` to explicit service options. It does not add a REST or browser surface. Native WP-CLI binary execution was unavailable in this workspace; the callback registration and command contracts were exercised in a deterministic CLI-compatible harness and remain separately qualified as NOT VERIFIED for the native binary.

## 10. Recovery and retry

There is no durable import job, checkpoint, or process-death resume table in schema 004. Recovery is intentionally limited to a complete-plan preflight, row compensation on a write failure, and deterministic rerun of the same source/strategy. The exact identity rules ensure a retry reuses an existing identity and does not create a duplicate or overwrite unrelated content. Cross-process crash-resume is NOT VERIFIED and is not claimed by version 0.9.0.
