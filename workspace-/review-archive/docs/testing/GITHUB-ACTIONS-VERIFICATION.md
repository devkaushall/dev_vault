# GitHub Actions Verification

## Workflow

- File: `.github/workflows/phase-1.yml`
- Triggers: push and pull request
- Matrix: PHP 8.1, 8.2, 8.3
- Steps: checkout, Composer development install, PHP syntax, PHPStan, PHPCS/WPCS, PHPUnit, production-only Composer install, package build, artifact upload

## Command

```bash
tools/verification/ci/run.sh
```

## Expected

A real workflow run associated with the current commit completes successfully for every matrix job and publishes the production ZIP. Evidence records commit SHA, run ID/URL, jobs, and conclusion.

## Actual

No authenticated GitHub CLI/repository execution context is available in the current workspace. The workflow has not been externally executed here.

## Status

**CI = NOT VERIFIED**

Configuration inspection is not represented as execution.
