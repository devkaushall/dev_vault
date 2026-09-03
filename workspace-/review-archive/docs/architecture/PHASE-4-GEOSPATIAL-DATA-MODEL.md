# Phase 4 Geospatial Data Model

**Status: implementation in progress, 31 August 2026.**

Canonical coordinates remain Phase-2 `rep_latitude` and `rep_longitude` WGS84 numeric Property metadata. Latitude is nullable and bounded to −90…90; longitude is nullable and bounded to −180…180. A location is geosearchable only when both values are present and valid. The Phase-3 disposable projection already stores both at decimal precision `(10,7)`; lifecycle and rebuild reuse the existing writer, so no second coordinate model is introduced.

Radius inputs are normalized to kilometres (miles use exact factor 1.609344) and capped at 500 input units. Distance uses the haversine great-circle formula and Earth mean radius 6371.0088 km. Bounds require all north/south/east/west values. North must be at least south. West greater than east explicitly means a dateline-crossing rectangle and uses longitude OR semantics.

Public coordinate exposure is separate from search truth. Property metadata `rep_coordinate_privacy` may be exact, rounded (3 decimals), approximate (2 decimals), or hidden; invalid policies fail closed to hidden. A typed global default exists. Search calculations retain canonical precision while provider-neutral marker output applies privacy.

No Phase-4 schema migration has yet been accepted. Native spatial extensions are not required. SQLite haversine execution is verified; MySQL/MariaDB execution remains NOT VERIFIED.
