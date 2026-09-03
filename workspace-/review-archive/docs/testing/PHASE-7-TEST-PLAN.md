# Phase 7 Test Plan and Release Gates

**Closure:** The locally executable Phase 7 gate passed on 2 September 2026. The detailed decision is in `docs/testing/PHASE-7-GATE-REPORT.md`.

## Mandatory local gates

1. PHP syntax over every plugin PHP file on PHP 8.1, 8.2 and 8.3.
2. PHPUnit unit and existing integration suites.
3. PHPCS/WPCS with the existing configuration and no new broad exclusions.
4. Clean WordPress Playground activation/runtime on PHP 8.1, 8.2 and 8.3.
5. Phase 7 workflow runner: schema 004, form validation, public acknowledgement allowlist, replay dedupe, context validation, one Lead Engine, IDOR/capabilities, relationship-derived assignment, status histories, site-visit transitions/rescheduling, notification failure isolation/retry, privacy export/erase, and read-only diagnostics.
6. Existing Phase 5 REST/AJAX/privacy/security regression and Phase 6 profile regression.
7. Two reproducible package builds, source/package-install comparison, clean extracted runtime, and SHA-256 recording.

All seven local gates passed. The runtime and package evidence is machine-readable under `verification-results/` and summarized in the gate report.

## Security assertions covered

- Public payloads cannot set identity, status, assignment, timestamps, consent timestamp, IP, or notification fields.
- Public responses contain acknowledgement/status only; private workflow reads use explicit allowlists.
- Anonymous and authenticated REST mutations have the appropriate public/nonce boundary; authenticated workflow mutations require a valid REST nonce.
- Invalid IDs, unpublished context, invalid transitions, unauthorized reads, invalid relationships, profile deletion, and replay races do not create unauthorized operational state.
- Lead requests and site visits are children of the canonical Lead Engine; no parallel engine is permitted.
- Notification provider failure changes only outbox retry state and never domain state. Concurrent workers claim outbox rows before delivery.
- Export/erase covers contact, request, and site-visit data; raw network identifiers are not stored, dedupe keys are cleared, queued notices are cancelled, and history notes are cleared.

## Not verified gates

Native MySQL/MariaDB, WP-CLI, PHPStan at the required memory budget, browser/Elementor/Astra/React/mobile clients, real Mayfair Forms & Leads/Mayfair Core artifacts, ACF Pro, CI, and external email delivery remain `NOT VERIFIED` because those executable environments/artifacts were unavailable. They are not converted to FAIL merely because they are unavailable.
