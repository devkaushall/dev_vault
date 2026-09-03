# Phase 9 Import/Export Architecture

**Version:** 0.9.0  
**Status:** implemented architecture contract; local gate closed  
**Date:** 2026-09-03  
**Scope:** provider-neutral CSV/JSON import and export for the canonical Property, Project, Insight, Agent, and Agency entities.

## Boundary and composition

The subsystem is a core application capability. It does not depend on Elementor, Astra, React, mobile clients, ACF, Mayfair, or a browser. `Bootstrap` composes:

- `SchemaCatalog`, derived from `FieldRegistry` and `TaxonomyRegistry`;
- `SourceParser`, the bounded CSV/JSON parser;
- `ImportService`, the normalized-row validator, deterministic planner, and executor;
- `ExportService`, the deterministic public/editorial serializer and safe file writer;
- `RemoteMediaImporter`, an explicit opt-in adapter using the existing `Security::validateRemoteUrl` primitive;
- canonical `MediaService` and `ProfileService` boundaries.

The service registry keys are `imports` and `exports`. The only transport currently needed is WP-CLI. No REST upload route or admin UI was introduced: a file-upload HTTP surface is not necessary for the bounded Phase 9 deliverable and would add another authorization, body-size, and temporary-file boundary. A future REST transport must call these same services and may not implement a second import engine.

## Source contract

The caller supplies one fixed entity and one format. The parser accepts CSV with a unique canonical header row or JSON containing an object row, an array of object rows, or the exported `{entity, columns, rows}` envelope. Input is bounded to 16 MiB, 10,000 rows, 128 columns, 65,535 bytes per CSV cell, and JSON nesting depth 32. Malformed shape, invalid UTF-8, inconsistent CSV columns, duplicate headers, oversized rows/cells, and unsupported formats fail before any plan is built.

The allowlist is explicit and entity-scoped. Common columns are `id`, `slug`, `title`, `content`, `excerpt`, and `status`. Direct metadata columns are public/editorial fields from `FieldRegistry`, excluding private notes, relationship metadata, attachments, and fields not exposed as canonical public/editorial fields. Taxonomies are explicit `tax_<registered_taxonomy>` columns. Media columns are explicit `featured_image_id|featured_image_url` and `media_<canonical_attachment_field>_<id|ids|urls>`. Relationship columns are only:

- Agent → Agency: `relationship_agency_id`;
- Property → Agent and Agency together: `relationship_agent_id` and `relationship_agency_id`.

Property → Project is deliberately not offered because it is not an existing canonical relationship. The subsystem does not invent a mapping.

Every row requires a title and at least one deterministic identity: an existing positive `id`, a sanitized `slug`, or the canonical Property `reference` field. An explicitly requested missing ID is a conflict, not a create instruction. There is no fuzzy matching, title matching, email matching, external-ID metadata, post-type selection, taxonomy selection, author selection, option write, capability write, raw post/meta bypass, executable PHP, or user-controlled SQL.

`status` is limited to `draft` and `publish`, with the normal entity publish capability checked before planning. The current actor must hold `manage_realestate_imports` and the entity edit capability. New posts are attributed to that actor; the source cannot select an author.

## Normalized row and plan

`ImportRow` is the normalized DTO: source line, raw bounded values, sanitized canonical post values, canonical field values, explicit taxonomy references, explicit media references, explicit supported relationships, identity, warnings, and errors. Raw numeric and boolean types are checked before canonical sanitization so PHP coercion cannot turn malformed input into valid zero/false values.

The planner resolves only exact identities (`id`, exact WordPress slug, or exact Property reference), exact existing term IDs/slugs/names, exact existing attachment URLs, and exact relationship post types. A row decision is always visible as `create`, `update`, `conflict`, or `invalid`. Strategies are:

- `upsert`: exact identity updates, otherwise creates;
- `create_only`: an existing exact identity is a conflict;
- `update_only`: a missing exact identity is a conflict.

Duplicate Property references and multiple identities resolving to different records are conflicts. Existing term references must exist unless `create_missing_terms` is explicitly enabled. In that mode the plan records a pending term creation; planning and dry-run never call `wp_insert_term`.

## Mutation boundary

`validate` and `dry_run` parse, sanitize, query exact identities, resolve terms, check relationship targets, and validate media URLs. They do not call a mutating WordPress function. A dry-run result is a deterministic JSON report with stable row order, line numbers, identities, decisions, conflicts, warnings, and bounded error details.

`execute` first builds the complete plan. If any row has a validation or conflict finding, all otherwise-valid rows are marked `skipped` and no mutation is applied. If the plan is valid, each row is executed through canonical WordPress editorial APIs or `ProfileService`; taxonomy writes use `wp_set_post_terms`, attachment references use `MediaService`, and supported relationships use `ProfileService`. Missing terms are created only at this execution boundary.

Each row has a compensation boundary. Existing records are snapshotted for post fields, allowlisted metadata, terms, relationships, and featured media. A create that fails after its post exists is deleted. An update that fails after a partial write is restored. Newly sideloaded media is deleted during compensation. A runtime failure is reported as `failed`; it is never silently swallowed. A successful rerun uses the same exact identity and is deterministic. No persistent checkpoint/history table was added because the bounded plan/report is in-process and schema 004 is retained.

## Media policy

Existing attachment IDs are validated through `MediaService`. Existing local attachment URLs are resolved through `attachment_url_to_postid` and are not duplicated. Remote URLs are never fetched during validation or dry-run. Remote media requires `allow_remote_media`, HTTPS, the existing remote URL/IP safety primitive, no redirects, a five-second timeout, an eight-megabyte response bound, an allowlisted MIME type, a sanitized filename, a WordPress temporary file, and `media_handle_sideload`.

If URL validation, DNS/IP verification, HTTP retrieval, response size, MIME validation, or sideloading cannot be safely verified, the row reports `media: ... NOT VERIFIED` and no remote download is attempted. Remote media is not a Mayfair compatibility mapping.

## Export contract

`ExportService` exports only the explicit `SchemaCatalog` columns, never leads, requests, site visits, notifications, authentication data, private notes, operational tables, security data, credentials, or arbitrary metadata. By default only published posts are exported; `include_nonpublic` is an explicit privileged option. Rows are ordered by ascending WordPress ID. Columns and JSON object keys are always in the catalog order.

JSON is UTF-8, unescaped Unicode/slashes, and a stable compact envelope. CSV is UTF-8 without a platform-specific BOM, uses comma/quote CSV with an empty escape character, and uses pipe-delimited values for bounded lists. Values beginning with `=`, `+`, `-`, or `@` receive a leading apostrophe in CSV to prevent spreadsheet formula interpretation; the CSV importer removes that guard only for those exact leading patterns. JSON values retain native booleans, numbers, arrays, and nulls.

Export files are limited to 32 MiB, written only below the dedicated upload staging directory, reject traversal and symlink paths, require the matching extension, and refuse existing files unless overwrite is explicit. The output includes a SHA-256 checksum. The service does not write arbitrary filesystem paths.

## Known verification qualifications

The implementation is locally testable without external vendors. Native MySQL/MariaDB, native WP-CLI, WordPress Playground matrix, real ACF/Elementor/Mayfair, browser/editor, CI, external providers, and production-scale 100k benchmarks remain separate verification items and must not be represented as PASS without executable evidence.
