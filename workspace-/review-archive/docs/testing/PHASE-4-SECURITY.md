# Phase 4 Security

**PASS for local backend scope.** Invalid/extreme/non-finite/nested coordinates, incomplete/inverted bounds, zero/negative/excessive radius, private Property visibility, hidden coordinates, marker-title markup, bounded marker output, AJAX nonce behavior and structured REST errors were exercised. Marker serialization is explicit and contains only ID, privacy-adjusted coordinates, stripped title, safe URL, numeric price and currency.

Provider configuration contains no key/credential. Typed settings are not public REST settings. Search and marker generation are read-only. Native browser rendering and live provider credentials remain NOT VERIFIED.

Evidence: `verification-results/phase4-geo-core.json`, `phase4-hardening.json`.
