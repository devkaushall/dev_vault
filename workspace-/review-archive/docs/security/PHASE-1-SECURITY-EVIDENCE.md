# Phase 1 Security Evidence Template

## Automated execution

Run `tools/verification/security/run.sh mysql` and repeat with `mariadb`. Attach the generated JSON from `evidence/phase-1/security/`.

## Required observations

| Area | Expected | Evidence | Status |
|---|---|---|---|
| Unauthenticated REST | 403 | Pending | NOT VERIFIED |
| Subscriber REST | 403 | Pending | NOT VERIFIED |
| Administrator REST | 200, no settings/secrets | Pending | NOT VERIFIED |
| Settings escalation | Server-side denial | Pending | NOT VERIFIED |
| Malformed/unexpected REST input | Predictable 4xx | Pending | NOT VERIFIED |
| Migration failure | A retained, B interrupted, C absent | Pending | NOT VERIFIED |
| Purge safeguards | Both setting and constant required | Pending | NOT VERIFIED |
| Explicit uninstall | Only plugin-owned data removed | Pending | NOT VERIFIED |
| Multisite purge | Safe refusal | Pending | NOT VERIFIED |
| Logging | Password/API key/token/PII redacted | Pending | NOT VERIFIED |
| Traversal | Rejected | Pending | NOT VERIFIED |
| SQL | Placeholders/safe APIs; no injection | Pending | NOT VERIFIED |

## Human review checklist

- Record container image digests and database versions.
- Inspect failure logs without copying credentials or personal information.
- Confirm unrelated options, posts, users, and core tables survive uninstall.
- Review every SQL statement reached during migration and uninstall.
- Record exact commands, timestamps, exit codes, and result JSON hashes.

Security remains **NOT VERIFIED** for the external blockers until this evidence is complete on required database engines.
