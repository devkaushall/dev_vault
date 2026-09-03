# Phase 2 REST Root Cause

Date: 30 August 2026

## Observed behavior

Malformed nested values submitted as canonical Project or Insight metadata were not persisted, but the native post endpoint returned HTTP 200 with the unchanged resource.

## Expected behavior

Validation must precede mutation. Invalid input must return a structured WordPress REST 4xx response and leave the complete resource state unchanged.

## Actual control flow

1. `ContentModule::initialize()` registers the Project and Insight post types and asks `ContentRegistrar::registerMeta()` to register canonical fields.
2. `registerMeta()` passed a `default` argument for every field, including `null` for optional fields.
3. WordPress 6.4.10 `register_meta()` validates every explicitly supplied default with `rest_validate_value_from_schema()`.
4. A null default does not match scalar schemas (`string`, `integer`, `number`, and so on), so `register_meta()` returned `false` before adding those fields to the global metadata registry.
5. The return value was not checked. Only fields with type-compatible non-null defaults—such as `rep_currency`, `rep_country`, and `rep_featured`—were registered.
6. The generated Project/Insight REST `meta` schema therefore did not contain fields such as `rep_latitude` or `rep_reading_time`.
7. WordPress's meta object validator only checked registered properties; the absent input property reached the controller as an unregistered meta key and was ignored by `WP_REST_Meta_Fields::update_value()`.
8. No metadata mutation occurred, no controller error existed, and `WP_REST_Posts_Controller` constructed its normal HTTP 200 response.

The precise origin is the type-incompatible explicit `default => null` passed by `ContentRegistrar::registerMeta()`. WordPress `register_meta()` rejected the registration. The successful response was then constructed by the native `WP_REST_Posts_Controller` because its `WP_REST_Meta_Fields` instance had no registered field to validate or update.

## Runtime/API verification

The WordPress 6.4.10 runtime exposes `set_body_params()`, `set_body()`, `get_json_params()`, and `set_route()`. It does **not** expose `set_json_params()`. Tests use `set_body_params()` for parsed requests and `set_header()` plus `set_body()` for malformed raw JSON.

Before the fix, `verification-results/rest-root-diagnostic.json` shows that the controller schema contained only three Project fields and returned 200. After the fix, `verification-results/rest-root-diagnostic-after-fix.json` shows `rep_latitude` in the schema, direct schema validation failure, HTTP 400, and unchanged storage.

## Fix

- Omit the `default` registration argument when a field's canonical default is `null`; retain explicit type-compatible defaults.
- Rely on WordPress's native generated metadata schema for type validation and structured REST errors.
- Retain the existing pre-dispatch canonical validator for semantic checks not expressible by basic JSON schema, including attachment existence and URL/coordinate rules.
- Add bounded string/text/URL schema lengths.
- Extend the existing validator to reject nonexistent Project/Insight taxonomy IDs before the endpoint callback.

## Why the fix is safe

The fix restores the intended native WordPress registration path rather than rewriting responses or validating after persistence. Native route argument validation executes before the endpoint callback. Semantic and taxonomy validation uses the already registered pre-dispatch validation boundary and returns `WP_Error` before mutation. Full before/after snapshots in the REST contract suite cover post title, slug, status, featured media, canonical metadata, and taxonomies.
