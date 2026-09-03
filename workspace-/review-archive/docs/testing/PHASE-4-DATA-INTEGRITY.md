# Phase 4 Data Integrity

Canonical `rep_latitude`/`rep_longitude` remain authoritative. Projection coordinates are disposable. Executable lifecycle checks covered coordinate addition/update/removal, publish/unpublish and prior Phase-3 trash/restore/delete behavior. Marker/search/diagnostic requests do not mutate canonical data. Missing/hidden/non-public coordinates produce no marker. Rebuild, consistency and Phase-3 lifecycle regressions passed.

No Phase-4 migration is required: migration 002 already contains decimal coordinate projection columns and appropriate lifecycle/rebuild behavior. Historical migrations 001/002 were not modified.

Evidence: `verification-results/phase4-hardening.json` and refreshed Phase-3 lifecycle/rebuild/consistency evidence.
