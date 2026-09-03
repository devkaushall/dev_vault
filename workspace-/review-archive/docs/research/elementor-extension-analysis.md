# Elementor extension analysis

## Research method and limits
Research was performed 27 August 2026 (Asia/Calcutta), using official product pages/documentation first, WordPress.org listings second, and third-party material only for comparison or reported pain points. Pricing and packaging are snapshots, not promises. “Not found” means not confirmed in the reviewed public material. No proprietary code, packages, assets, or private APIs were inspected.

## Integration principles
Elementor is an optional adapter. Core boot must never reference Elementor classes before dependency/version checks. When absent or incompatible, only Elementor features are disabled and diagnostics explain why. No DOM scraping, editor monkey-patching, copied controls or Elementor-owned template data.

## Supported official surfaces
| Surface | Official mechanism | REP design |
|---|---|---|
| Dynamic Tags | `elementor/dynamic_tags/register`, Tag classes/categories/groups | Typed property/project/agent tags; context resolver; empty values return empty. |
| Widgets | `elementor/widgets/register`, `Widget_Base` | Thin widgets call shared render/query services; controls are presentation/query DTOs. |
| Controls | `register_controls()` and Controls Manager | Standard controls first; custom controls only when unavoidable. |
| Custom queries | `elementor/query/{query_id}` | Whitelisted IDs modify `WP_Query`; where index-only features are needed, REP widgets call Search API instead. |
| Form Actions | Elementor Pro form action APIs | Optional bridge into validated REP Form/Lead service; no direct table writes. |
| Theme Builder | WP template conditions/Elementor theme locations where supported | Property/project/agent contexts; graceful native template fallback. |
| Loop templates | Native post context and dynamic tags | Keep canonical content/meta compatible; document Pro dependency for Loop-specific UI. |
| Editor | official editor/frontend enqueue and render hooks | Register assets only in relevant contexts; editor preview uses deterministic sample/current entity. |

## Compatibility policy
- Declare and test minimum Elementor and PHP/WP versions; maintain a CI smoke matrix.
- Subscribe to Elementor deprecation notices. Use `register()` and current hooks, not removed `register_tag()` or `widgets_registered`.
- Treat Elementor Pro-only APIs (query widgets, forms, Theme Builder features) as optional sub-adapters.
- Never alter saved Elementor JSON during routine upgrades. Migration changes only known URLs/IDs with backup, dry-run and rollback.
- Dynamic tag contracts are stable REP identifiers documented in SOURCE-OF-TRUTH; aliases preserve old Mayfair tags where known.

## Sources
- [Developer docs/components](https://developers.elementor.com/docs/)
- [Building add-ons and compatibility checks](https://developers.elementor.com/docs/addons/)
- [Registering dynamic tags](https://developers.elementor.com/docs/managers/registering-dynamic-tags/)
- [Dynamic-tag controls](https://developers.elementor.com/docs/dynamic-tags/dynamic-tags-controls/)
- [Custom query filter](https://developers.elementor.com/docs/hooks/custom-query-filter/)
- [Current PHP hooks](https://developers.elementor.com/docs/hooks/php/)
- [Deprecation example](https://developers.elementor.com/v3-5-planned-deprecations/)
