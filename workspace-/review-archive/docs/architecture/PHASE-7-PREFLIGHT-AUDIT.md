# Phase 7 Preflight Audit and Architecture Lock

**Date:** 2 September 2026  
**Baseline:** RealEstate Platform Core 0.6.0 / schema 003; Phase 7 target 0.7.0 / schema 004  
**Decision:** Phase 7 implementation may proceed with a native canonical Lead Engine.

## Repository audit

At preflight, before implementation, the canonical source `plugins/realestate-platform/` contained no executable Lead, Form, Request, Site Visit, Submission, or lead-notification application service. It contained only the Phase 5 saved-search alert notification abstraction. No Phase 7 operational tables, lead CPT, site-visit CPT, form-submission table, `mpfl_*` hook, or `mpfl_submission_created` implementation was found.

The workspace contains no authorized Mayfair Forms & Leads, Mayfair Core, ACF Pro, or Elementor Pro installation artifact. Compatibility evidence is fixture-based only and is not real-plugin compatibility evidence.

## Existing-system decision

There is no existing executable Lead Engine in the repository to extend or wrap. The Phase 5 alert system is not a Lead Engine and will not be repurposed for lead storage. A second lead engine is therefore not being created: Phase 7 introduces the one and only canonical native `LeadService` for this plugin.

A future Mayfair adapter may be added only after an authorized artifact and a real hook/contract are supplied and verified. No Mayfair compatibility claim is made by this Phase 7 implementation.

## Locked boundaries

- Editorial Property, Project, Agent and Agency records remain WordPress posts/meta/taxonomies.
- Leads, lead requests, histories, site visits and notification outbox events are operational data and use indexed custom tables.
- Forms are transport/validation adapters. They contain no lead business logic.
- REST adapters call shared application services. No Phase 7 AJAX adapter is added because no Phase 7 frontend requires it; this is not a feature-parity reason to duplicate transport logic.
- Elementor remains optional and is not a Phase 7 core dependency.
- Public submission never accepts server-owned status, user, agent, agency, IP, timestamps or consent timestamps.
- External email delivery is provider-backed and may remain NOT VERIFIED; state persistence is independent of delivery success.

## Architecture lock

Canonical services:

```text
REST / future form adapters
          ↓
Forms\\SubmissionValidator
          ↓
Requests\\RequestService
          ↓
Leads\\LeadService  ← one canonical Lead Engine
          ├── status/assignment history
          ├── notification outbox
          └── privacy/lifecycle cleanup

SiteVisits\\SiteVisitService
          ├── LeadService
          ├── explicit state machine
          └── notification outbox
```

Phase 7 uses migration `004`; no empty migration is permitted. The locked schema and acceptance rules are documented in the Phase 7 architecture and testing documents.

## Implementation outcome

The locked decision was implemented without introducing a competing engine. `LeadService`, `RequestService`, `SiteVisitService`, `SubmissionValidator`, the notification provider/outbox, REST boundary, privacy hooks, diagnostics, lifecycle cleanup, and migration `004` are present in the canonical plugin. The final local gate passed; unavailable native databases, real Mayfair/ACF/Elementor artifacts, browser/UI, CI, PHPStan, WP-CLI, and external delivery remain `NOT VERIFIED` rather than being inferred from fixtures.
