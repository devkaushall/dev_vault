# Workspace Inventory

This workspace contains the RealEstate Platform Core source, architecture documents, verification harnesses, tests, and evidence through the closed local Phase 9 gate.

## Major folders

- `.github/workflows/` — GitHub Actions configuration for quality checks and packaging.
- `.wordpress-playground/` — cached WordPress Playground runtime used by local PHP/WASM verification.
- `docs/architecture/` — approved architecture and phase specifications, including the Phase 9 import/export contract.
- `docs/research/` — Phase 0 market, feature, WordPress, Elementor, data-model, security, and strategy research.
- `docs/security/` — security requirements and audit reports, including `PHASE-9-SECURITY.md`.
- `docs/testing/` — QA plans, environment instructions, implementation and final-gate reports.
- `plugins/realestate-platform/` — canonical plugin source, migrations, tests, Composer metadata, and development tooling.
- `scripts/` — deterministic packaging and WordPress Playground verification scripts.
- `verification-results/` — machine-readable runtime results and regression evidence.
- `dist/realestate-platform-0.9.0.zip` — current Phase 9 package; 116 files, 140,466 bytes, SHA-256 `4c8441eb002a85e6435b0031dee9f84e5249309e1dc6c52a7154f4d4e24f9f9b`.

## Canonical current state

- Plugin version: **0.9.0**
- Database schema: **004**; no Phase 9 migration 005 was required.
- Phase 1: frozen; external verification debt remains open.
- Phases 2–8: current-source component regressions passed after the latest Phase 9 edits.
- Phase 9: **closed local PASS** for the bounded provider-neutral CSV/JSON Import/Export subsystem.
- Phase 10: not started and remains locked.

## Phase 9 entry points

- Preflight and architecture: `docs/architecture/PHASE-9-PREFLIGHT-AUDIT.md`, `docs/architecture/PHASE-9-IMPORT-EXPORT.md`
- Service/API contract: `docs/architecture/PHASE-9-IMPORT-EXPORT-API.md`
- Test plan and QA: `docs/testing/PHASE-9-TEST-PLAN.md`, `docs/testing/PHASE-9-QA.md`
- Security/privacy/integrity/performance: `docs/security/PHASE-9-SECURITY.md`, `docs/testing/PHASE-9-PRIVACY.md`, `docs/testing/PHASE-9-DATA-INTEGRITY.md`, `docs/testing/PHASE-9-PERFORMANCE.md`
- Final evidence: `docs/testing/PHASE-9-FINAL-GATE.md`, `docs/testing/PHASE-9-FINAL-VERIFICATION-REPORT.md`
- Focused implementation: `plugins/realestate-platform/src/ImportExport/`
- Focused tests/runners: `plugins/realestate-platform/tests/Unit/ImportExportTest.php`, `scripts/phase9-runner.php`, `scripts/phase9-security-runner.php`, `scripts/phase9-privacy-runner.php`, `scripts/phase9-integrity-runner.php`

## Artifact policy

The source workspace retains tests, lockfiles, reports, and development tools. Production packages are built separately and exclude tests, quality-tool configuration, caches, and development-only Composer packages. The final package was built twice with deterministic timestamps and sorted ZIP input; the two byte streams are identical. `package-install/realestate-platform/` is the clean extraction used by the PHP 8.1–8.3 package smoke matrix. Do not use `dist` or `verification-results` as the source for code changes.

## External debt and qualifications

Real native MySQL/MariaDB, native WP-CLI binary runtime, real Mayfair Core/Forms & Leads/ACF/Elementor artifacts, browser/editor/Theme Builder/Loop Grid, external CI, external delivery providers, successful allowed-public-host remote-media behavior, production infrastructure, PHPStan project-scope verification at the configured budget, 100k-scale performance, concurrent process-independent resume, and legal/retention approval remain explicitly **NOT VERIFIED**. Fixture-based Mayfair compatibility must never be presented as real Mayfair compatibility. The local gate is not a general production-readiness claim.
