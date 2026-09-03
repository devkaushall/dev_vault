# Phase 4 Geocoding

`GeocoderInterface` defines forward/reverse provider-neutral results. `NullGeocoder` is the default and returns controlled `WP_Error`; existing coordinates require no provider. No live geocoder adapter or credentials are shipped, so live provider execution is NOT VERIFIED.

`SecureGeoHttpClient` is the guarded transport for future adapters: HTTPS/public address/port 443, five-second timeout, redirects disabled, 256 KiB response cap, JSON content type and valid JSON required, and sanitized provider errors. Disabling redirects prevents public-to-private redirect chains. Raw provider responses are not persisted. Cache TTL is typed for future adapters, but no cache is created while geocoding is disabled.
