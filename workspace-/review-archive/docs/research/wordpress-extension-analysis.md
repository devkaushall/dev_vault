# WordPress native extension analysis

## Research method and limits
Research was performed 27 August 2026 (Asia/Calcutta), using official product pages/documentation first, WordPress.org listings second, and third-party material only for comparison or reported pain points. Pricing and packaging are snapshots, not promises. “Not found” means not confirmed in the reviewed public material. No proprietary code, packages, assets, or private APIs were inspected.

## Decision table
| Concern | Native primitive | Decision / constraint |
|---|---|---|
| Editorial entities | `register_post_type()` | Property/project/insight as CPTs, or adopt existing CPTs in Compatibility Mode; `show_in_rest`; explicit capabilities. |
| Classification | `register_taxonomy()` | Type/status/category/label/features/amenities/location; avoid a term per numeric value. |
| Scalar fields | `register_post_meta()` | Typed schema, sanitize/auth callbacks, subtype-specific registration. Canonical numerics stored unformatted. |
| Complex/high-write data | `$wpdb` custom tables | Leads, index, saved searches, jobs, audit etc.; repository boundaries and migrations. |
| REST | `WP_REST_Controller`, `register_rest_route()` | JSON Schema, permission callbacks, standard pagination/errors/links. Native CPT endpoints remain available where safe. |
| Auth | Cookies+nonces in wp-admin/front end; Application Passwords for remote clients | A nonce is CSRF intent, not authorization; always check capabilities/object ownership. |
| Roles/capabilities | Roles plus `map_meta_cap` | Fine-grained caps; never gate everything on `manage_options`. |
| Settings | Settings/Options APIs | Split versioned option groups by concern; sanitize each field. |
| Admin lists | `WP_List_Table` only behind adapter | It is not a formally stable public API; isolate usage and test WP updates. |
| Admin navigation | menu/submenu APIs | One Real Estate top level with capability-specific children. |
| Scheduling | WP-Cron | Small/periodic dispatch only; persistent jobs table and WP-CLI runner for reliability. |
| Schema | `dbDelta()` + explicit migration ledger | Idempotent forward migrations; test SQL quirks; never migrate large datasets during normal requests. |
| Upload/media | media sideload/attachment APIs | Capability, MIME, extension, signature/size checks; SSRF-safe remote fetch policy. |
| Cache | object cache first, transients fallback | Namespaced/versioned keys; event-driven invalidation; no correctness dependency. |
| URLs/templates | rewrite API/template filters | Configurable bases; flush only activation or slug change; override hierarchy with safe theme templates. |
| Shortcodes/blocks | Shortcode API + block metadata/render callbacks | Shared presentation services; block-first for new UX, shortcode compatibility. |
| AJAX | REST preferred; `wp_ajax_*` only where needed | Avoid duplicate contracts. Public mutations are rate-limited and CSRF/spam protected. |
| Privacy | exporter, eraser, policy guide hooks | Leads/favorites/searches/visits included; retention/anonymization configurable. |
| Uninstall | `uninstall.php`/uninstall hook | Default preserve-data safety; explicit verified purge option; multisite considered. |
| CLI | WP-CLI command registration | Index, migration, import, diagnostics, cache and jobs. |

## Important native constraints
Registered CPTs/taxonomies can use default REST controllers by setting `show_in_rest`; custom controllers are appropriate for indexed search and private workflows. Registered metadata requires an explicit type/schema and authorization; CPT support for `custom-fields` is required for REST meta behavior. `WP_Query` remains suitable for editorial archives but not arbitrary high-cardinality, multi-range geo search at 100k records.

## Sources
- [Plugin Handbook](https://developer.wordpress.org/plugins/)
- [CPT/taxonomy REST support](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-rest-api-support-for-custom-content-types/)
- [Registered metadata in REST](https://developer.wordpress.org/rest-api/extending-the-rest-api/modifying-responses/)
- [`register_meta`](https://developer.wordpress.org/reference/functions/register_meta/)
- [REST Handbook](https://developer.wordpress.org/rest-api/)
- [Privacy guidance](https://developer.wordpress.org/plugins/privacy/)
- [WP-CLI commands](https://developer.wordpress.org/cli/commands/)
- [Creating tables with plugins](https://developer.wordpress.org/plugins/creating-tables-with-plugins/)
- [Nonces](https://developer.wordpress.org/apis/security/nonces/)
- [HTTP API](https://developer.wordpress.org/plugins/http-api/)
