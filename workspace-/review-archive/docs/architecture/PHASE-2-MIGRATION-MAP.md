# Phase 2 Migration Map

Phase 2 performs no automatic Mayfair import or migration. This map prepares a future explicit workflow.

| Existing source | Canonical destination | Transformation | Validation | Conflict behavior |
|---|---|---|---|---|
| Existing `property`/`project`/`insight` post | Adopt same WP post | none | type/ownership inventory | preserve ID, slug, status, URL |
| Existing taxonomy term/relationship | Canonical/adopted taxonomy | approved taxonomy-name mapping | term and object existence | preserve; never duplicate silently |
| Scalar `mpd_*`/ACF value | registered `rep_*` meta | field-specific normalization | canonical field validator | report both; no overwrite by default |
| Existing gallery/floor plan/brochure | attachment ID meta | normalize ordered IDs | attachment and MIME validation | preserve invalid source for remediation |
| Existing coordinates | numeric coordinate meta | decimal conversion | latitude/longitude range | reject ambiguous/swapped values |
| Existing Elementor document | unchanged post metadata | none | document/post references | never rewrite automatically |
| Existing REST route/workflow | unchanged external system | none | before/after route/workflow snapshot | abort on conflict |

A future migration requires backup, dry-run counts, source/target checksums, explicit conflict policy, batched execution, audit log, validation report, and migration-specific compensating recovery. No such execution is authorized in Phase 2.
