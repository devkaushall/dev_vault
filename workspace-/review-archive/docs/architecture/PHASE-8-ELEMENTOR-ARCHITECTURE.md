# Phase 8 Elementor adapter architecture

**Release:** 0.8.0  
**Schema:** 004, unchanged  
**Status:** locally implemented and contract-tested; real vendor/browser environments **NOT VERIFIED**

## Boundary

```text
Optional Elementor hooks
        |
        v
ElementorIntegration (availability + composition)
   |             |                    |
   v             v                    v
PublicContext  QueryAdapter       LeadFormAction
   |             |                    |
   v             v                    v
FieldRegistry  SearchRequest     RequestService
Profiles       SearchEngine      LeadService -> private workflow tables
CoordinatePrivacy
```

The adapter is deliberately one-way: Elementor consumes canonical application/domain services. Core services do not call Elementor, know Elementor widget IDs, or require Elementor classes at activation time. The browser and editor remain presentation layers.

## Lifecycle

1. Core bootstrap registers the optional Elementor service without loading vendor code.
2. `ElementorIntegration::register()` boots immediately only when Elementor is already available; otherwise it listens to `elementor/loaded`.
3. `boot()` is idempotent and creates `PublicContext` and `QueryAdapter` only once.
4. Dynamic tags are registered on `elementor/dynamic_tags/register`.
5. The custom tag group is `realestate-platform`.
6. Query adapters register on the official `elementor/query/{query_id}` actions.
7. The Pro form action is registered on `elementor_pro/forms/actions/register`, and only when the Pro base action class is available.
8. If a runtime is absent, no integration hook or vendor class is required for core activation, content, search, REST, leads, or diagnostics.

## Supported public contexts

| Context | Public sources | Relationship/visibility rule |
|---|---|---|
| Property | title, price, currency, price period, area, units, beds, baths, address, location, taxonomy values, coordinates, public media URLs | Current post must be a published Property; related Agent/Agency must also be published |
| Project | title, type, location, status, price/currency, address, city/state/country, developer, RERA, brochure/image | Current post must be a published Project; only exposed frontend-visible fields |
| Agent | name, avatar, public phone/email, website, Agency title | `ProfileService` public serialization; no private notes or operational fields |
| Agency | name, logo, public phone/email, website, office address, license number | `ProfileService` public serialization; no private notes or operational fields |
| Insight | title, topic, subtitle, reading time, source name, public external source, CTA label/URL, image, status | Current post must be a published Insight; URLs are escaped |

The stable catalog is in `TagCatalog`. It exposes only fields that are public, frontend-visible, and Elementor-exposed in the canonical field registry. Unknown fields resolve to `null`, which renders nothing.

## Dynamic tag behavior

- Tag IDs are stable and machine-readable, for example `rep_property_title`, `rep_property_price`, `rep_property_type`, `rep_agent_public_phone`, and `rep_insight_cta_url`.
- Taxonomy IDs remain canonical and are not prefixed twice: `rep_property_type`, `rep_property_status`, `rep_project_type`, and `rep_insight_topic`.
- Post titles and taxonomy names are stripped of markup before contextual escaping.
- Text is escaped with `esc_html`; URL values with `esc_url`.
- Exact/rounded/approximate/hidden coordinate policy is applied through `CoordinatePrivacy`; hidden coordinates produce no value.
- Attachments are validated as attachment posts before their URLs are returned.
- Unpublished, mismatched, missing, invalid, or non-public contexts return no value.
- The adapter does not expose lead contacts, owner identities, private notes, assignments, workflow state, or notification data.

## Query adapters

The canonical IDs are:

| Query ID | Entity | Behavior |
|---|---|---|
| `rep_properties` | Property | Allowlisted `rep_*` inputs become a canonical `SearchRequest`; returned public IDs become bounded `post__in` values |
| `rep_projects` | Project | Forces public Project post type/status and clamps page size |
| `rep_agents` | Agent | Forces public Agent post type/status and clamps page size |
| `rep_agencies` | Agency | Forces public Agency post type/status and clamps page size |
| `rep_insights` | Insight | Forces public Insight post type/status and clamps page size |

Property inputs are allowlisted in `QueryAdapter::CRITERIA`; unknown values are ignored and canonical `SearchCriteria` still validates the delegated request. Page size is clamped to 1–100. An error or empty result becomes `post__in = [0]`, avoiding accidental broad queries. The adapter uses `no_found_rows` and `post__in` because `SearchRequest` owns pagination and result selection.

The generic entity adapters deliberately provide only public post bounds. They do not pretend to implement a second query language or bypass the canonical Property search service.

## Pro form boundary

`LeadFormAction` maps only known field IDs to a minimal request input and sends it to:

```text
Elementor Pro record
  -> allowlisted field map
  -> RequestService::submit()
  -> SubmissionValidator / consent / honeypot / rate limit / dedupe
  -> LeadService
```

Server-owned lead status, assignment, ownership, notification state, and workflow transitions are never accepted from Elementor fields. Elementor’s own request/nonce transport remains responsible for its hook invocation; the canonical service still validates all business input. Public success and error messages are generic.

## Modes and document integrity

- **Standalone:** Elementor consumes REP’s registered canonical content.
- **Compatibility:** the adapter reads the adopted canonical registrations and does not rename, replace, or rewrite existing IDs.
- **Migration:** the adapter remains read/submit-only; migration is a separate explicit administrator operation.
- No hook mutates `_elementor_data`, `_elementor_page_settings`, template IDs, widget IDs, document structure, slugs, URLs, media, or content.

## Deliberate non-scope

No custom Elementor widgets, controls, editor UI, Theme Builder templates, Loop Grid visual verification, ACF field registration, dynamic gallery editor, native Elementor query sorting language, or Mayfair-specific mapping is claimed in 0.8.0.
