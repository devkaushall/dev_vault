# Competitor analysis

**Research date:** 2026-08-27  
**Purpose:** clean-room capability research for an independent WordPress real-estate platform.

## Research method and limits
Research was performed 27 August 2026 (Asia/Calcutta), using official product pages/documentation first, WordPress.org listings second, and third-party material only for comparison or reported pain points. Pricing and packaging are snapshots, not promises. “Not found” means not confirmed in the reviewed public material. No proprietary code, packages, assets, or private APIs were inspected.

## Executive findings
The market clusters into: (1) polished listing products (Estatik), (2) developer-oriented native-WordPress frameworks (Easy Property Listings and WPCasa), (3) vertically integrated/MLS-heavy systems (Realtyna WPL), and (4) agency operations/CRM products (Property Hive). The opportunity is a useful native core plus indexed search and private operational tables, avoiding both postmeta-only scaling and a closed parallel CMS.

## Estatik
Public material describes a free-to-Premium ladder, fields builder, configurable search, maps, gallery/video, wishlist, saved search, agents/agencies, frontend profiles/listing management, subscriptions/payments, PDF flyers, CSV/WP All Import workflows, Elementor support, and Premium RETS/RESO/MLS integration. Search forms accept standard and custom fields and can expose saved-search controls. Current public pages place advanced search, agents/agencies, saved searches, subscriptions and map search in Pro, and MLS in Premium. Exact entitlements vary by release and must be rechecked at implementation/release time.

**Strengths:** broad buyer/agent journey; cohesive templates; monetization and MLS path; Elementor-facing features.  
**Risks/pain points to avoid:** tier ambiguity over time; dependency coupling in imports; map vendor assumptions; large feature surface; migration lock-in.

Sources: [official site](https://estatik.net/), [search shortcode](https://estatik.net/docs/es_search_form/), [agents shortcode](https://estatik.net/docs/agents-es_my_agents/), [Elementor property template](https://estatik.net/docs/how-to-create-a-property-page-template-with-elementor-pro/), [WP All Import workflow](https://estatik.net/docs/how-to-import-properties-via-wp-all-import/), [subscriptions/payments](https://estatik.net/subscriptions-and-one-time-payment/), [FAQ/MLS terms](https://estatik.net/frequently-asked-questions/).

## Easy Property Listings (EPL)
EPL emphasizes a free, theme-agnostic listing core and a large extension catalog. Public official material confirms multiple listing types, structured pricing/status data, search, features, multiple agents, widgets/shortcodes, REST/Gutenberg support, and paid extensions for advanced mapping, brochures, floor plans, frontend submissions, galleries, inspections, listing alerts, location profiles, OpenStreetMap, Matterport and RESO.

**Lesson:** extension points and theme neutrality create developer trust. **Risk:** assembling many narrowly scoped add-ons raises operational and purchasing complexity.

Sources: [official extension bundle](https://easypropertylistings.com.au/extensions/core-bundle/), [official repository](https://github.com/easypropertylistings/Easy-Property-Listings), [official site](https://easypropertylistings.com.au/).

## Realtyna / WPL
WPL’s public positioning stresses a flexible field/search system, Basic/Pro editions, many add-ons, agent/agency and CRM possibilities, and MLS/IDX capability. Realtyna explicitly states legacy RETS is deprecated and recommends RESO Web API; public notices say legacy MLS/RETS/Data Replicator support ended 31 December 2024.

**Lesson:** large inventories require deliberate indexing/storage and feed operations. **Risk:** a highly parallel database model can reduce compatibility with WP_Query/page builders and complicate migration. MLS pricing/transport changes independently of plugin code.

Sources: [WPL overview](https://realtyna.com/blog/wpl-in-a-nutshell-2/), [RETS deprecation guidance](https://realtyna.com/blog/integrate-rets-feed-wordpress/), [official WPL/CRM comparison article](https://realtyna.com/blog/7-best-real-estate-plugins-with-free-demos/).

## WPCasa
WPCasa is a free listing framework with a `listing` CPT and location/type/feature/category taxonomies, search widget, settings, templates, shortcodes, maps, currencies, responsive UI and schema markup. Add-ons cover favorites, labels, advanced search, frontend dashboard, energy data and more.

**Lesson:** stay close to WordPress content primitives and expose hooks/templates. **Risk:** key workflows distributed across add-ons can make capability discovery harder.

Sources: [WordPress.org listing](https://wordpress.org/plugins/wpcasa/), [official site/add-ons](https://wpcasa.com/).

## Property Hive (additional credible comparator)
Property Hive combines free property/search/results/details/enquiry features with CRM, calculators, Elementor/Divi, SEO-plugin compatibility and customizable templates. Its paid offering adds imports, portal export, map/draw/radial search, shortlist, saved searches, brochures, location autocomplete and operational tools.

**Lesson:** agency workflow and contact handling are first-class domain concerns, not generic form entries. **Risk:** private CRM data demands stricter permissions, retention and REST design than listings.

Sources: [official pricing and feature packaging](https://wp-property-hive.com/pricing/), [WordPress.org listing](https://wordpress.org/plugins/propertyhive/).

## Product implications
1. Core must include useful property CRUD, configurable fields, basic forms/search/rendering, REST and portability.
2. Search uses a derived index, while WordPress remains canonical for editorial property content.
3. Leads, saved searches, jobs, audit and payments use purpose-built private tables.
4. Maps, geocoding, mail, PDF, payment and MLS are provider adapters.
5. Elementor is optional and registered only through supported APIs.
6. Every premium module consumes stable core contracts; foundational data is never paywalled.
