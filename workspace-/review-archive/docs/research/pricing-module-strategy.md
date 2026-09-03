# Pricing and module strategy

## Market signal
Competitors validate three patterns: useful free core (WPCasa/EPL/Property Hive/WPL), annual bundles/add-ons (EPL, Property Hive), and higher-value MLS/data connectivity (Estatik Premium/Realtyna). Prices differ by geography, support and external feed costs and are intentionally not copied. Sources: [EPL bundle](https://easypropertylistings.com.au/extensions/core-bundle/), [Property Hive pricing](https://wp-property-hive.com/pricing/), [WPCasa](https://wpcasa.com/), [Estatik](https://estatik.net/), [Realtyna WPL overview](https://realtyna.com/blog/wpl-in-a-nutshell-2/).

## Recommended packaging
**Core (open/free):** property/project CRUD; taxonomies; field builder; media; normalized locations; basic indexed search/list/single; CSV import/export + JSON export; basic forms/inquiry capture; explicit public REST; blocks/shortcodes; basic Elementor tags/card/grid/search; SEO/schema; privacy hooks; diagnostics; core CLI; migration detection and read compatibility. This is independently useful.

**Pro:** agents/agencies; favorites; compare; saved searches and scheduled alerts; frontend listing management/moderation; advanced filters/map/list UX; lead/visit workflow; advanced Elementor widgets/queries; analytics summaries; automation.

**Premium:** subscriptions/entitlements; payment gateways; scheduled/remote/XLSX/XML feeds; RESO/MLS connectors; PDF; advanced geo/polygon; advanced notification/provider adapters; high-scale operational features.

**Optional integration:** Elementor Pro forms/loops/theme builder, ACF, SMTP/mail vendors, Google/Mapbox, CAPTCHA, CRM/webhooks, portal exports, WooCommerce bridge. **Out of scope:** copied competitor UX/assets, bundled native mobile app, acting as MLS credential vendor, card storage.

## Licensing principles
Licensing activates modules/services, not canonical data. Expiry never deletes or encrypts content and permits read/export. Core interfaces have semantic versions; paid modules declare compatible ranges. Remote license checks are cached, timeout safely and disclose telemetry. No frontend request depends synchronously on a licensing server. External-provider costs are clearly separate.

## Commercial validation needed before launch
Interview Indian agencies, Mayfair operators, solo agents and feed-heavy brokers; test willingness-to-pay by outcome; model support and map/mail/feed costs; publish entitlement table; review GPL/distribution and third-party licenses with counsel. Pricing itself is a later product decision.
