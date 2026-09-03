# Phase 2 Entry Criteria

Phase 2 is **LOCKED** until the project owner explicitly selects and records one of these governance models.

## Model A — Phase 1 fully verified

All mandatory Phase 1 gates, including declared database engines, controlled migration failure/recovery, uninstall, security, and required real compatibility, have executed successfully. Evidence is stored under `evidence/phase-1/`; the final result and gate documents are updated to PASS; the owner authorizes Phase 2 in writing.

## Model B — transparent environmental deferral

The owner formally accepts named external checks as deferred environmental validation. Each remains visibly NOT VERIFIED in machine-readable status, release notes, risk register, and handoff documentation. The decision identifies owner, date, rationale, affected deployment environments, compensating controls, execution deadline, and stop/rollback criteria. Deferred checks are never relabeled PASS, and production readiness is not inferred.

## Common entry requirements

- Preserve the Phase 1 freeze and source-of-truth identifiers.
- Approve the Phase 2 scope and sequence before code is written.
- Confirm actual Mayfair field/taxonomy/content inventory and mode choice.
- Approve data model and migrations before production DDL.
- Define acceptance, security, privacy, compatibility, performance, and rollback tests.
- Keep optional ACF/Elementor integrations outside the core dependency path.

## Recorded authorization

**Model B selected.** Decision owner: Dev. Effective date: 30 August 2026. External validation deadline: 30 September 2026 or before any earlier production release. Scope is limited to development and disposable Phase 2/Mayfair validation environments; no production migration or destructive production change is authorized.

The accepted debt remains NOT VERIFIED for MySQL 8.4, MariaDB 11.4, controlled database failure/recovery, complete uninstall, dependent security closure, real Mayfair Core/Forms & Leads compatibility, and GitHub Actions. A failure involving database integrity, recovery, destructive uninstall, security, Mayfair takeover, or a frozen Phase 1 regression stops affected development/release activity. The affected Phase 2 change must be reverted to the last verified state, failing evidence preserved, relevant gates reopened, and regressions rerun.

This authorization permits Phase 2 implementation but does not mark Phase 1 PASS or authorize production deployment.
