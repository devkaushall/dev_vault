# Data model proposal

## Canonical ownership
`wp_posts` owns editorial identity/content/status/slug for property, project, agent, agency and insight (or adopted existing equivalents). `wp_terms` owns curated classifications. Registered scalar meta owns low-write canonical attributes. REP custom tables own field definitions, query projections, private workflows and many-to-many user state. The search index is disposable—not canonical.

## Proposed tables (prefix `{wp}_rep_`)
| Table | Purpose / key constraints | Retention |
|---|---|---|
| `schema_migrations` | migration id/checksum/applied time | permanent |
| `field_definitions` | unique entity+field key; type/config/version | permanent |
| `field_values` | only relational/repeating/non-meta values; object+field+ordinal | follows object |
| `search_properties` | one row/property; numeric/geospatial/sort columns | rebuildable |
| `search_terms` | property↔taxonomy/term projection, composite indexes | rebuildable |
| `favorites` | unique user+property | user retention |
| `saved_searches` | owner, canonical criteria JSON, hash, cadence, status | user retention |
| `saved_search_matches` | unique search+property notification ledger | bounded |
| `compare_items` | user/session token+property, expiry | bounded |
| `relationships` | typed entity links (agent/agency/property/project) | follows entities |
| `leads` | contact/context/consent/status/assignee timestamps | configurable |
| `lead_notes` | private notes | with lead |
| `requests` / `visits` | typed requests and scheduling workflow | configurable |
| `form_submissions` | form/version/payload envelope; minimize PII duplication | configurable |
| `notifications` | channel/template/status/dedupe; no secret payload | bounded |
| `jobs` / `job_attempts` | queue, locks, attempts/errors | bounded |
| `imports` / `import_rows` | source/mapping/state/error/rollback journal | configurable |
| `audit_log` | actor/action/object/summary; no secrets | configurable |
| `subscriptions` / `payments` | entitlement and gateway references, never card data | statutory/configurable |

Agencies remain CPTs because they have public editorial pages; the relationship table handles membership. Analytics, if enabled, uses a separate aggregate/event table with consent/retention controls.

## Property fields
Canonical keys use `rep_` names: price/currency/period, normalized address components, latitude/longitude, areas and unit, bedrooms/bathrooms/floors/floor/parking/year, furnishing/possession/availability/construction/developer/RERA/reference, flags, URLs and media attachment IDs. Presentation labels are never stored as numeric truth. Gallery and floor-plan ordering use attachment relationships/value rows rather than serialized searchable blobs.

## Index strategy
B-tree indexes cover publish state, price, area, beds, baths, featured, updated time and location IDs. Coordinates use bounded-box prefilter plus exact distance; optional spatial indexes require a supported DB capability check. Keyset pagination is available for deep result sets; public API retains conventional pages within capped depth. Index updates enqueue on canonical changes and can run synchronously for small edits; bulk work is batched.

## Compatibility
A mapping registry translates `mpd_*` and ACF field names to canonical keys at read time in Compatibility Mode. Writes default to canonical plus optional explicitly enabled dual-write. Unknown source metadata is preserved. Migration manifests record source key, target key, converter, count, checksum and rollback action.
