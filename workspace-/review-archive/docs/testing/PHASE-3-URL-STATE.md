# Phase 3 URL State Verification

`SearchUrlState` parses query strings into the same immutable `SearchCriteria` used by transports and serializes canonical criteria with sorted keys and RFC 3986 encoding. Taxonomy lists serialize once as comma-separated IDs. Defaults remain explicit in canonical output, making refresh/share behavior deterministic.

Empty state, multi-filter/range/taxonomy/location/sort/page round trip, repeat serialization, duplicate keys, nested keys, unknown keys, invalid values and encoded sort injection were executed. Invalid state returns a structured 400 `WP_Error` and never reaches SQL.

Evidence: `verification-results/phase3-url-state.json`.
