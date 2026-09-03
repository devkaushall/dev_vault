# Phase 3 Search API

The canonical public endpoint is `GET /wp-json/realestate-platform/v1/properties`. Both REST and AJAX (`action=realestate_platform_search`) delegate to the same `SearchRequest -> SearchCriteria -> SearchEngine -> DatabaseSearchProvider` path. Adapters contain no SQL and responses contain no HTML.

The response has `results`, `pagination` (`total`, `total_pages`, `current_page`, `per_page`) and canonical `applied_filters`. Result fields are the allowlisted public identity, URL, numeric/property summary and flags emitted by `DatabaseSearchProvider`; raw metadata and projection internals are excluded.

Approved scalar, range, relationship, boolean and taxonomy fields are those declared by `SearchCriteria`; arbitrary keys are rejected. Pages are one-based, `per_page` is capped at 100, and sort is a closed enum. Different dimensions use AND. Feature/amenity multi-selection uses AND; other taxonomy multi-selection uses OR. The provider joins canonical posts and reasserts published Property visibility.

AJAX requires a same-origin nonce for `realestate_platform_search`. It does not create a more privileged dataset. URL state uses RFC 3986 encoding, sorted keys and comma-separated taxonomy IDs. Duplicate, nested, unknown and invalid URL parameters are rejected.
