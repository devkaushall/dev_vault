# Estatik public feature map

**Evidence snapshot:** 2026-08-27. This is capability/category research, not a compatibility guarantee or reproduction specification.

## Research method and limits
Research was performed 27 August 2026 (Asia/Calcutta), using official product pages/documentation first, WordPress.org listings second, and third-party material only for comparison or reported pain points. Pricing and packaging are snapshots, not promises. “Not found” means not confirmed in the reviewed public material. No proprietary code, packages, assets, or private APIs were inspected.

| Capability | Publicly observed tier | Evidence/notes | REP disposition |
|---|---|---|---|
| Property CRUD, types/status/categories/features | Free/all | Product and update pages | CORE |
| Fields builder/custom fields | Free/all; some field behavior historically tiered | Public docs/update notes | CORE, independent schema |
| Listing cards, sorting, shortcodes/widgets | Free/all | Product docs | CORE + blocks |
| Search and custom fields in search | All; advanced/map options Pro | Search shortcode docs | CORE basic; PRO advanced geo UX |
| AJAX/map search | Publicly marketed; advanced in Pro | Official marketing | PRO module over core search API |
| Gallery/video/request forms | Public feature descriptions | Official site/docs | CORE |
| Wishlist/favorites | Publicly marketed | Official product materials | PRO (guest/basic may be Core launch option) |
| Saved searches + email notifications | Pro/Premium | Search docs and release notes | PRO |
| Compare | Pro/Premium in historical public materials | Official release/product material | PRO |
| Agents/agencies and profile shortcodes | Pro/Premium | Agents docs | PRO |
| CRM/leads/request forms | Paid-oriented capability | Public materials; exact boundaries vary | CORE secure inquiry capture; PRO CRM workflow |
| Frontend profile/listing management | Pro/Premium | Docs/release notes | PRO |
| Subscriptions and one-time/recurring PayPal payment | Pro/Premium | Official payment page | PREMIUM |
| CSV/import | Version/tier varies; WP All Import documented | Official import docs | CORE CSV; PREMIUM feeds/schedules |
| Export | Publicly referenced, exact tier varies | Product documentation | CORE CSV/JSON |
| MLS / RETS / RESO Web API | Premium | FAQ and official site | PREMIUM adapter; RESO-first |
| PDF flyer | Paid/Premium | Official release material | PREMIUM provider |
| SEO/URLs/templates | Across product | Product pages/docs | CORE |
| Elementor widgets/template integration | Across/varies by widget | Official Elementor docs | CORE basic; PRO advanced |
| Localization/multilingual | Publicly marketed | Official site | CORE i18n; integrations optional |
| Email/notifications | Saved-search and workflow features | Docs/update notes | Core abstraction; automation PRO |
| Permissions/user profiles | Paid agent/frontend workflows | Docs | CORE capabilities; UI PRO |
| REST/public API | Not comprehensively confirmed in reviewed docs | Do not infer | REP explicit `rep/v1` CORE |

## Required clean-room behavior map
REP may reproduce generic behaviors—filtering, saving criteria, comparing fields, rendering cards—but not Estatik names, markup, visual assets, copy, field identifiers, private schemas or internal APIs. Import support accepts documented/user-provided exports only.

## Key official sources
- https://estatik.net/
- https://estatik.net/docs/es_search_form/
- https://estatik.net/docs/agents-es_my_agents/
- https://estatik.net/subscriptions-and-one-time-payment/
- https://estatik.net/frequently-asked-questions/
- https://estatik.net/docs/how-to-import-properties-via-wp-all-import/
- https://estatik.net/docs/how-to-create-a-property-page-template-with-elementor-pro/
