# Feature parity matrix

Classification is the proposed packaging, not a competitor entitlement guarantee. Status is Phase-0 only. “Table?” and risk answers are summarized in Our Design.

| Feature | Competitor | Free/Paid | Required | Our Design | Dependency | Status |
|---|---|---:|---|---|---|---|
| Property/project CRUD | All | Free | CORE | CPT/adopted CPT; WP primitive; no table | WordPress | RESEARCHED |
| Taxonomies/features/locations | All | Free | CORE | WP taxonomies + normalized location fields; cache impact | WordPress | RESEARCHED |
| Configurable field builder | Estatik/WPL/EPL | Mixed | CORE | Definitions table; scalar values registered meta; complex relational values; validation/privacy review | None | DESIGNED |
| Listing cards/grid/single/gallery | All | Free | CORE | Shared render components, theme overrides, responsive media | WP Media | DESIGNED |
| Basic search/sort/paging | All | Free | CORE | Search index table; URL state; avoids meta chains | DB | DESIGNED |
| AJAX/REST advanced filters | Estatik/WPL | Paid/mixed | PRO | Same canonical search service; cache/rate limits | None | DESIGNED |
| Radius/bounds/polygon map search | Estatik/Property Hive/EPL | Paid | PRO | Indexed coordinates; provider-neutral UI; privacy/key concerns | Map provider | DESIGNED |
| Favorites/wishlist | Estatik/WPCasa | Mixed | PRO | User-scoped table + signed/local guest state; IDOR controls | Browser storage | DESIGNED |
| Compare | Estatik/EPL comparables | Paid | PRO | Configurable safe field projection; guest/user list table | None | DESIGNED |
| Saved searches/alerts | Estatik/Property Hive/EPL | Paid | PRO | Table with normalized query hash and match ledger; mail/job load | Mail/job runner | DESIGNED |
| Agents/agencies | Estatik/WPL | Paid/mixed | PRO | CPTs + relationship table; capabilities/PII | None | DESIGNED |
| Frontend listing dashboard | Estatik/WPCasa/EPL | Paid | PRO | Moderated commands; upload/security burden | None | DESIGNED |
| Inquiry/forms | All | Mixed | CORE | Reusable validated form engine; private submission table; anti-spam | CAPTCHA optional | DESIGNED |
| Lead CRM/site visits | WPL/Property Hive/Estatik | Paid/mixed | PRO | Private normalized tables, audit, retention; never public REST | None | DESIGNED |
| CSV/JSON import/export | EPL/Estatik/Property Hive | Mixed | CORE | Batch mapping, dry-run, duplicate keys, rollback journal | None | DESIGNED |
| Scheduled remote feeds/XLSX/XML | All via add-ons | Paid | PREMIUM | Provider adapters + jobs; SSRF/media constraints | Feed/parser | DESIGNED |
| MLS/RESO | Estatik/WPL/EPL | Paid | PREMIUM | RESO-first adapter; credentials/provider contract; no vendor lock-in | MLS credentials | DESIGNED |
| RETS | Legacy competitors | Paid/legacy | OPTIONAL INTEGRATION | Compatibility adapter only; deprecated ecosystem | External library/feed | RESEARCHED |
| Subscriptions/payments | Estatik/Directorist | Paid | PREMIUM | Entitlements separate from content; payment adapter/table/webhooks | Gateway | DESIGNED |
| PDF brochures | Estatik/Property Hive | Paid | PREMIUM | `PdfGeneratorInterface`; async generation | PDF provider | DESIGNED |
| Elementor basic tags/widgets | Estatik/Property Hive | Mixed | CORE | Optional official API adapter | Elementor | DESIGNED |
| Elementor Pro queries/forms/theme | Competitors | Paid/mixed | OPTIONAL INTEGRATION | Pro-only adapters; native fallback | Elementor Pro | DESIGNED |
| Blocks/shortcodes | WPCasa/EPL | Free | CORE | Shared render layer; blocks primary, shortcodes compatibility | WordPress | DESIGNED |
| SEO/schema/canonicals | WPCasa/Property Hive | Mixed | CORE | Actual-data-only schema; noindex arbitrary filters | SEO plugins optional | DESIGNED |
| Multicurrency/units | Estatik/WPCasa | Mixed | CORE | numeric canonical values + formatter; conversion optional | FX provider optional | DESIGNED |
| Analytics | WPL/add-ons | Paid | PREMIUM | Aggregated privacy-aware events; separate table/retention | Optional | PROPOSED |
| Native mobile apps | WPL | Paid | OUT OF SCOPE | REST enables third parties; no bundled app | External app | DEFERRED |
| Portal syndication | Property Hive | Paid | OPTIONAL INTEGRATION | Export adapters; partner contracts | Portal | PROPOSED |

## Decision checklist applied
Core placement requires broad user value and a stable WordPress/core primitive. Tables are used for high-cardinality queries, private records, many-to-many state or independent retention. Frontend-heavy map/infinite-scroll code is lazy loaded. PII, payments, remote fetches and public mutations receive dedicated threat models. External vendors are behind interfaces and raw canonical data remains exportable to prevent lock-in.
