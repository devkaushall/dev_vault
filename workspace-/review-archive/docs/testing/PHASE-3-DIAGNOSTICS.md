# Phase 3 Search Diagnostics

## Architecture

`SearchIndexConsistency` is the single read-only source of search-index facts. `SearchIndexCheck` adapts that canonical structure to the existing `DiagnosticResult` contract and is registered in the existing `DiagnosticsRunner` through the service registry. No repair or second diagnostics framework is introduced.

## Canonical facts

The result contains projection-table and taxonomy-bridge existence, installed/current schema state, last rebuild state when available, published (`expected`) and indexed counts, missing, stale, orphaned, duplicate, taxonomy mismatch and visibility mismatch counts, plus overall `healthy`.

A healthy result maps to `PASS`; any schema or consistency defect maps to `FAIL`. Existing framework statuses remain unchanged (`PASS`, `WARN`, `FAIL`).

## Read-only guarantee and tests

The deliberate-corruption harness executes healthy, missing, stale, orphaned, taxonomy mismatch, visibility mismatch and duplicate-constraint cases. Before/after projection snapshots are equal for every diagnostic invocation. Status does not rebuild, repair, write options, modify posts/meta/terms, or delete records.

Duplicate `post_id` insertion is rejected by migration 002's primary key. The diagnostic retains a duplicate count and correctly reports zero under the enforced schema; the schema was not weakened.

Evidence: `verification-results/phase3-diagnostics.json`.
