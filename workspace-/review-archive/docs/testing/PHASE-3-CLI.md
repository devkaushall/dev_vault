# Phase 3 WP-CLI Search Index Commands

Commands: `wp realestate search-index status` (read-only, `view_realestate_diagnostics`) and `wp realestate search-index rebuild [--batch-size=1..1000]` (`manage_realestate_migrations`). Both use existing shared consistency/rebuilder services and structured JSON. Authorization and operational failures use `WP_CLI::error`, yielding non-zero exits without stack traces or SQL.

A CLI-compatible executable harness passed registration, output, read-only status, unauthorized rejection, rebuilds at 0/10/100/1,000, repeatability, batching and invalid-batch failure. The final environment check found no native `wp` executable, so native WP-CLI process execution remains **NOT VERIFIED**. This does not downgrade verified implementation/callback behavior.

Evidence: `verification-results/phase3-cli.json`.
