# Phase 1 external verification harness

This directory contains reproducible, destructive **disposable-environment-only** verification for the Phase 1 foundation. It does not turn an unexecuted gate into PASS.

## Prerequisites

- Docker Engine with Compose v2
- Bash, `curl`, and standard Unix utilities
- Ports available to Docker
- At least 2 GB free memory and 5 GB free disk
- Run commands from the repository/workspace root

Optional requirements:

- Real Mayfair Core and Mayfair Forms & Leads ZIP files for real compatibility checks
- Licensed ACF Pro/Elementor Pro artifacts where applicable
- GitHub CLI (`gh`) authenticated to a repository for CI execution

No production database, credentials, customer data, or lead data may be used. Database passwords are generated in memory by each runner and are not committed. Every destructive runner requires `REALESTATE_VERIFICATION_DISPOSABLE=YES` and rejects externally supplied database targets.

## Commands

```bash
REALESTATE_VERIFICATION_DISPOSABLE=YES tools/verification/mysql/run.sh
REALESTATE_VERIFICATION_DISPOSABLE=YES tools/verification/mariadb/run.sh
REALESTATE_VERIFICATION_DISPOSABLE=YES tools/verification/migration-failure/run.sh mysql
REALESTATE_VERIFICATION_DISPOSABLE=YES tools/verification/migration-failure/run.sh mariadb
REALESTATE_VERIFICATION_DISPOSABLE=YES tools/verification/uninstall/run.sh mysql
REALESTATE_VERIFICATION_DISPOSABLE=YES tools/verification/uninstall/run.sh mariadb
REALESTATE_VERIFICATION_DISPOSABLE=YES tools/verification/security/run.sh mysql
REALESTATE_VERIFICATION_DISPOSABLE=YES tools/verification/mayfair-compatibility/run-fixture.sh mysql
tools/verification/ci/run.sh
```

The database scripts start isolated WordPress/database containers, install WordPress, mount the plugin under test, run WP-CLI checks, write JSON evidence, and tear down on exit. Set `KEEP_ENV=1` only for debugging; otherwise teardown is automatic.

## Expected outputs

Machine-readable output is written below each harness and copied to `evidence/phase-1/`. A result is PASS only when its script exits zero and the JSON says PASS. Placeholder result files remain NOT_VERIFIED.

## Destructive tests

Migration-failure and uninstall scripts intentionally execute failing SQL, reset disposable databases, remove plugin-owned data, and may convert a disposable site to multisite. Never point their Compose configuration or environment variables at an existing site.

## Mayfair

`run-fixture.sh` proves detector/non-takeover behavior using a clean-room fixture only. It cannot close real Mayfair compatibility. Real artifacts must be supplied explicitly to a later real-artifact runner and must never be committed to this workspace.

## GitHub

`ci/run.sh` requires an authenticated `gh` session, a configured remote, and permission to dispatch/read workflow runs. It records run URL, commit, jobs, matrix, and conclusion; without access it emits NOT_VERIFIED.
