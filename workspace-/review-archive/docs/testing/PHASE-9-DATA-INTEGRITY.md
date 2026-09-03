# Phase 9 data-integrity and recovery report

**Version:** 0.9.0  
**Execution date:** 2026-09-03  
**Evidence:** `verification-results/phase9-integrity-8.3.json`

## 1. Integrity contract

Canonical WordPress post/meta/term/media state is authoritative for editorial entities. Agent and Agency writes/relationships use `ProfileService`; import/export does not write search projections, leads, requests, visits, notifications, authentication data, or other `{prefix}rep_*` operational tables.

An import execute call is a complete-plan operation:

1. parse and bound the source;
2. normalize each row into `ImportRow`;
3. validate the complete batch, including identity, fields, terms, relationships, and media;
4. return all deterministic decisions and findings; and
5. write only if the complete plan is clean.

If the plan contains an invalid, conflict, taxonomy, relationship, media, or source-level error, otherwise-valid rows are returned as `skipped` and no planned mutation is applied. Each executed row has a snapshot, canonical write sequence, identity/content verification, and compensation path for an execution failure.

## 2. Executed checks

The PHP 8.3 / WordPress 6.4 / SQLite harness passed all six checks:

| Check | Result | Meaning |
|---|---|---|
| `invalid_batch_has_no_partial_mutation` | **PASS** | a batch with one invalid row changed no editorial state |
| `invalid_batch_reports_skipped_rows` | **PASS** | the invalid row and the valid-but-skipped row were both accounted for |
| `deterministic_retry_reuses_identity` | **PASS** | rerunning the same source found the same canonical identity |
| `deterministic_retry_preserves_content` | **PASS** | retry did not duplicate or destroy the existing content |
| `relationship_targets_are_consistent` | **PASS** | supported target IDs remained canonical and consistent |
| `dry_run_does_not_touch_operational_tables` | **PASS** | operational table snapshots were identical before and after dry-run |

The Phase 9 runtime matrix independently passed dry-run, upsert, taxonomy, relationship, and no-Phase-10 checks on PHP 8.1–8.3.

## 3. Retry and interruption model

Version 0.9.0 provides deterministic rerun recovery, not a durable job queue. Schema 004 has no process-death checkpoint, cursor, import-history, or resume token. A worker interrupted between rows must rerun the same source and strategy; exact identity rules make the rerun converge without fuzzy matching. Row compensation covers an observed write failure within the call.

The following are deliberately **NOT VERIFIED** and not claimed:

- process death at every possible write boundary;
- durable cross-process resume;
- native MySQL/MariaDB transaction/locking behavior;
- production object-cache or concurrent-worker behavior; and
- large-batch recovery outside the 10,000-row parser bound.

No migration 005 was created because a persistent checkpoint was not required by the implemented scope. Adding one would require separate schema authorization and a new recovery contract.

## 4. Data-integrity invariants

- no arbitrary post type, taxonomy, meta key, option, capability, executable payload, or operational table can enter the normal path;
- validation and dry-run have `mutation=NONE`;
- a clean execute plan is the only path that can mutate;
- create/update/conflict/skipped/imported/failed decisions are row-visible;
- deterministic identities are positive ID, exact sanitized slug, or exact canonical reference;
- unsupported Property → Project mapping is rejected rather than inferred; and
- exports cannot become an alternate source of private workflow data.
