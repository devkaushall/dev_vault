# Phase 2 REST Edge Cases

Status: **PASS** for the executable WordPress 6.4.10 / SQLite test environment.

Evidence:
- `verification-results/rest-contract-php-8.1.json`
- `verification-results/rest-contract-php-8.2.json`
- `verification-results/rest-contract-php-8.3.json`
- `verification-results/rest-root-diagnostic-after-fix.json`

The suite proves valid Project and Insight persistence and structured 4xx/no-mutation behavior for wrong scalar types, nested objects, unexpected arrays, unknown canonical metadata, oversized values, invalid numbers, invalid coordinates, invalid URLs, nonexistent attachment IDs, nonexistent taxonomy IDs, malformed JSON, unauthenticated access, and insufficient capability. It also verifies missing metadata, empty optional values, native optional-null reset semantics, and XSS sanitization.

Every rejected case compares complete before/after state (title, slug, status, featured media, metadata, Project taxonomy, and Insight taxonomy) and scans returned errors for stack traces, filesystem paths, database details, warnings, and notices.

Optional canonical fields permit `null` as WordPress's documented metadata reset/delete operation; the suite verifies this as successful intentional behavior. No Phase-2 canonical Project/Insight metadata field is declared required, so there is no valid “null where forbidden” canonical field to fabricate.
