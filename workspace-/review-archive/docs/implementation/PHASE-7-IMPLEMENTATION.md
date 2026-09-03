# Phase 7 Implementation Report

**Status:** PASS for the locally executable scope  
**Release:** 0.7.0  
**Schema:** 004  
**Date:** 2 September 2026

## Canonical implementation

The workspace audit found no executable Mayfair Forms & Leads engine, Lead/Form/Site Visit contract, or `mpfl_*` runtime hook. The Phase 5 saved-search alert feature is not a Lead Engine. Phase 7 therefore implements the canonical native engine in `plugins/realestate-platform`:

- `src/Leads/LeadService.php` — sole Lead Engine and private lead aggregate operations.
- `src/Requests/RequestService.php` — child-request facade; delegates creation to `LeadService`.
- `src/Forms/Submission.php` and `SubmissionValidator.php` — immutable validated input boundary.
- `src/SiteVisits/SiteVisitService.php` and `SiteVisitRequest.php` — linked site-visit workflow and state transitions.
- `src/Leads/LeadNotificationService.php` — provider-backed outbox with dedupe, claim-before-send, retry, and stale-claim recovery.
- `src/REST/Phase7WorkflowController.php` — public acknowledgement and authenticated private transport boundaries.
- `src/Privacy/PrivacyFoundation.php` — WordPress privacy export/erase integration.
- `src/Diagnostics/LeadWorkflowCheck.php` — read-only health diagnostics.
- `migrations/004_lead_workflows.php` — durable operational schema.

## Implemented invariants

1. There is exactly one Lead Engine. Forms, REST, future AJAX, Elementor, and future Mayfair adapters must call the validated application services.
2. Public input cannot set identity, status, assignments, timestamps, consent timestamp, network identifiers, or notification state.
3. Context is limited to published canonical Property/Project posts. Agent/Agency assignments are relationship-derived and validated.
4. Lead and site-visit status changes are allowlisted, capability checked, race guarded, and history recorded.
5. Private reads require ownership or explicit capability; public responses are acknowledgement-only.
6. Notification delivery is asynchronous and isolated from domain writes; provider failure yields retry state rather than domain rollback.
7. Replay identity is unique and race safe. Privacy erasure clears dedupe keys, redacts private fields/notes, cancels queued notices, and anonymizes requester relationships.
8. Deleted Agents/Agencies are unassigned from Lead and Site Visit records; deleted Properties are detached from workflow rows; deleted users trigger workflow cleanup.
9. Migration `004` is real and idempotent through the existing migration runner; no unnecessary migration was added beyond the required persistent schema.
10. Core has no Elementor, ACF, Mayfair, browser, or external mail dependency.

## Deliberately not claimed

No fixture is presented as real Mayfair Forms & Leads compatibility. Native MySQL 8.4/MariaDB 11.4, PHPStan at the required memory budget, WP-CLI, real ACF/Elementor/browser/UI/mobile clients, CI, and external notification delivery are `NOT VERIFIED` because their executable environments or authorized artifacts are unavailable.

See `docs/architecture/PHASE-7-LEADS-ARCHITECTURE.md`, `PHASE-7-FORMS-ARCHITECTURE.md`, `PHASE-7-SITE-VISITS.md`, and `docs/testing/PHASE-7-GATE-REPORT.md` for the locked design and evidence.
