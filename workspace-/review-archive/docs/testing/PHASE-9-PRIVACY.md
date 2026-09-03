# Phase 9 privacy and export-boundary report

**Version:** 0.9.0  
**Execution date:** 2026-09-03  
**Evidence:** `verification-results/phase9-privacy-8.3.json`

## 1. Privacy policy

Phase 9 exports are editorial content exports, not a private workflow export. The only export entities are Property, Project, Insight, Agent, and Agency. `SchemaCatalog` derives the column list from the public/editorial `FieldRegistry` and approved taxonomy registry, then adds only explicitly supported media and relationship columns.

The following are excluded from export and rejected as import fields:

- leads, requests, visits, notification events, authentication records, and private user data;
- private notes and private workflow metadata;
- credentials, tokens, passwords, internal security/diagnostic data, arbitrary options, and arbitrary post/meta;
- unsupported relationship mappings; and
- arbitrary post types or taxonomies.

Public exports select published posts only. Draft/private/nonpublic content is available only when the caller explicitly requests it and has `manage_realestate` in addition to the normal export and entity-edit capabilities. The export does not create or expose a REST route.

## 2. Executed checks

The PHP 8.3 / WordPress 6.4 / SQLite privacy harness passed:

| Check | Result |
|---|---|
| catalog excludes private and operational columns | **PASS** |
| public export values use safe strings/serializable values | **PASS** |
| private editorial metadata is excluded | **PASS** |
| draft content can be explicitly reviewed | **PASS** |
| default export is public-only | **PASS** |
| nonpublic export requires `manage_realestate` | **PASS** |
| private import input is rejected | **PASS** |

The dedicated security harness additionally passed actor identity binding, capability boundaries, no-mutation rejection, parser limits, direct-write/static checks, path traversal, SSRF, and serialized-payload checks.

## 3. Serialization safety

- CSV and JSON use the fixed `SchemaCatalog` field order.
- Rows are ordered by WordPress ID ascending.
- CSV values beginning with `=`, `+`, `-`, or `@` are prefixed with an apostrophe to prevent spreadsheet formula evaluation.
- Output is valid UTF-8 and bounded to 32 MiB.
- File exports stay below the uploads staging directory, require a matching extension, and refuse accidental overwrite without an explicit force/overwrite option.
- Attachment exports expose validated IDs/URLs only; remote URL import is opt-in and does not turn an export URL into an unchecked download.

## 4. Qualifications

The harness uses disposable REP-owned WordPress fixtures. Legal/retention approval, production access-control review, native database behavior, external storage, real provider behavior, and real Mayfair/ACF field mappings remain **NOT VERIFIED**. No private or customer data was used.
