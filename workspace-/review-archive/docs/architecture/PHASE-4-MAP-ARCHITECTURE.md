# Phase 4 Map Architecture

Provider-neutral map data flows through the existing `SearchCriteria -> SearchEngine -> DatabaseSearchProvider`. `MapSearchRequest` requires a radius or viewport, caps results using typed settings (and the REST maximum of 100), primes post/meta caches, applies `MarkerFactory` privacy, and returns markers plus pagination, applied filters and clustering capability. REST route: `GET /realestate-platform/v1/properties/map`.

`MapProviderInterface` exposes only provider ID, public configuration and clustering capability. `OpenStreetMapProvider` is keyless and core has no Google dependency. `MapListState` provides immutable criteria/viewport, result and selection transitions; event fingerprints suppress repeated transitions. Browser rendering, debounce wiring and accessibility remain NOT VERIFIED because no browser map implementation or automation is available.
