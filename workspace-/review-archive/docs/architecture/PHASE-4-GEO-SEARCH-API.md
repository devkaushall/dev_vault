# Phase 4 Geo Search API

The existing Property REST and AJAX search accepts either `latitude`, `longitude`, `radius`, optional `radius_unit=km|mi`, or complete `north`, `south`, `east`, `west` bounds. Radius and bounds are mutually exclusive. Radius is positive and capped at 500 input units. North must be at least south. `west > east` explicitly denotes a dateline-crossing viewport.

All normal Phase-3 filters, sorts and pagination compose with geo criteria. Radius is normalized to kilometres and evaluated with haversine distance (mean Earth radius 6371.0088 km). The provider always joins canonical published Property posts. Invalid input produces structured 400 errors.

The map route requires radius/bounds, returns at most 100 markers, omits hidden/missing coordinates and applies exact/rounded/approximate privacy. It exposes no provider secrets or raw metadata.
