=== RealEstate Platform ===
Contributors: mayfair
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 0.9.0
License: GPLv2 or later

RealEstate Platform Core is an independent, API-ready WordPress real-estate engine. Version 0.9.0 adds a provider-neutral, bounded CSV/JSON import/export subsystem for Property, Project, Insight, Agent, and Agency. It derives its allowlists from the canonical FieldRegistry and TaxonomyRegistry, uses deterministic IDs/slugs/references for create-only and upsert decisions, supports zero-mutation validation and dry runs, and writes through WordPress editorial primitives and ProfileService. Exports are stable UTF-8 CSV/JSON with public/editorial allowlists, formula-injection protection, and no private lead, request, visit, notification, authentication, or security data. Elementor remains an optional presentation adapter; all real-estate business logic remains frontend-independent.

== Installation ==
Install the generated production ZIP or this source directory and activate. Multisite is unsupported in 0.x. Deactivation preserves all data. Uninstall preserves data unless the explicit purge option and REALESTATE_PLATFORM_PURGE_DATA constant are both enabled. Elementor, Elementor Pro, ACF, and external Mayfair compatibility are optional. Core activation and services do not require them.

== Import and export ==
Imports are available through the `realestate import validate`, `realestate import dry-run`, and `realestate import execute` WP-CLI commands. Use `--entity=property|project|insight|agent|agency`, `--file=<relative path>`, and `--format=csv|json`; files are restricted to the plugin upload staging directory. Execute only after reviewing the deterministic row decisions. Strategies are `upsert`, `create_only`, and `update_only`. Missing taxonomy terms and remote media require explicit opt-in. Remote media is HTTPS-only, bounded, MIME-allowlisted, non-following-redirect, and SSRF-checked; a failed verification is reported as NOT VERIFIED and is never downloaded.

Exports use `realestate export` with the same entity and output format plus `--file` and an optional bounded `--limit`. Output is restricted to the staging directory, refuses accidental overwrite unless `--force` is explicit, and is ordered by WordPress ID. Draft/non-public content requires the explicit `--include-nonpublic` flag. Private workflow data and unsupported relationships are never exported. Property-to-Project import/export is not offered because it is not a canonical relationship in the existing model; no invented mapping is used.

== Elementor integration ==
The adapter registers only when the official Elementor runtime hooks are available. It uses the official dynamic-tag manager and custom-query action contracts. Tags resolve only published public content and use contextual escaping and coordinate privacy. Elementor Pro form submissions are delegated to the canonical Phase 7 request/lead validation path; private workflow fields are server-owned.

Existing Elementor documents, templates, widget IDs, settings, URLs, media, and content are never rewritten. The local contract harness uses minimal fake vendor classes because real Elementor, Elementor Pro, ACF, browser/editor, Theme Builder, Loop Grid, and Mayfair environments were unavailable; those environments remain NOT VERIFIED.

== Changelog ==

= 0.9.0 =
* Added bounded provider-neutral CSV/JSON validation, normalized row DTOs, deterministic import plans, create-only/upsert/update-only strategies, conflict reporting, taxonomy resolution, supported profile/property relationships, safe existing-media resolution, and compensating rollback.
* Added zero-mutation validation/dry-run reporting and canonical WordPress/ProfileService execution for Property, Project, Insight, Agent, and Agency.
* Added deterministic public/editorial CSV/JSON exports with fixed field ordering, UTF-8 output, formula-injection protection, safe staging paths, overwrite protection, and explicit privacy exclusions.
* Added opt-in SSRF-checked remote media sideloading with bounded size/MIME/redirect controls and explicit NOT VERIFIED failures.
* Retained database schema 004; no persistent Phase 9 import-job migration was required.
* Real native databases, WP-CLI runtime, browser/editor, ACF, Elementor, Mayfair, external delivery, CI, production infrastructure, and PHPStan project-scope verification remain NOT VERIFIED.

= 0.8.0 =
* Added optional Elementor dynamic tags for canonical public Property, Project, Agent, Agency, and Insight values.
* Added official Elementor custom-query action adapters with bounded allowlisted inputs and canonical SearchRequest delegation.
* Added optional Elementor Pro lead form action delegating to RequestService and the single Lead Engine.
* Added publication, ownership/relation, field-exposure, coordinate-privacy, escaping, anti-replay, consent, honeypot, and no-direct-SQL contract coverage.
* Retained database schema 004; no Phase 8 migration was required.
* Real vendor, browser/editor, ACF, Mayfair, native database, CI, and PHPStan project-scope verification remain NOT VERIFIED.

= 0.7.0 =
* Added the canonical Lead Engine, validated form/request transport, site-visit workflow, private workflow storage, notification outbox, privacy integration, and REST APIs.
