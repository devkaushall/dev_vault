# Phase 6 Agents and Agencies

Agents and agencies are canonical WordPress editorial entities using the existing ContentRegistrar, FieldRegistry, media, author ownership, and capability architecture. `Profiles\\ProfileService` is the single application boundary. No custom table or migration is required.

## Relationships

Agent-to-Agency and Property-to-Agent/Agency relationships use typed canonical post metadata:

- Agent → Agency: `rep_agency_id`
- Property → Agent: `rep_agent_id`
- Property → Agency: `rep_agency_id`

Property assignment validates post types, Property edit authority, profile usability, and Agent-to-Agency consistency. A Property cannot pair an Agent with an unrelated Agency. Agency deletion is blocked while either an Agent or Property references it. Authenticated Property relationship removal is available through the shared service and REST adapter.

Permanent profile deletion removes matching relationship metadata. Cleanup is intentionally data-preserving for all unrelated WordPress content.

## Public data

Public serialization is allowlisted and excludes author identity, status, private notes, arbitrary metadata, and moderation data. REST adapters delegate operations to `ProfileService`; they do not contain business logic or direct SQL. Strict positive-ID parsing rejects negative, signed, non-decimal, composite, boolean, object, null, and overflowing values without silent coercion.

ACF and Elementor are optional and are not dependencies of the profile core.
