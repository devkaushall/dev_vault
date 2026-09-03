# Phase 9 security audit

**Version:** 0.9.0  
**Execution date:** 2026-09-03  
**Evidence:** `verification-results/phase9-security-8.3.json`

## 1. Security boundary

The import/export subsystem is a core application service. Its normal transport is capability-protected WP-CLI; no REST or AJAX import/export surface exists. Every application call binds the supplied actor ID to `get_current_user_id()` and checks the entity capability plus the import/export capability. Nonpublic export adds a separate `manage_realestate` check.

The normal path has no arbitrary SQL, option writes, capability mutation, arbitrary post type/taxonomy, executable PHP, unsafe URL fetch, unchecked filesystem path, or private workflow export. Canonical WordPress APIs, `ProfileService`, `MediaService`, and existing `Security` primitives are used instead.

## 2. Threat and control matrix

| Threat | Control | Result |
|---|---|---|
| capability bypass | `manage_realestate_imports` / `manage_realestate_exports` plus entity edit capability | **PASS** |
| IDOR or actor substitution | positive actor ID and current-user equality | **PASS** |
| guest mutation | authorization before parsing/planning/writing | **PASS** |
| private field injection | registry-derived public allowlist and protected-column rejection | **PASS** |
| arbitrary taxonomy | `TaxonomyRegistry` entity allowlist | **PASS** |
| malformed input | strict CSV/JSON parser and structured rejection | **PASS** |
| invalid UTF-8 | UTF-8 validation before normalization | **PASS** |
| resource exhaustion | 16 MiB / 10,000 row / 128 column / cell and depth limits | **PASS** |
| duplicate key ambiguity | duplicate normalized JSON key rejection | **PASS** |
| path traversal | `Security::safePath` and staging-root checks | **PASS** |
| output extension confusion | extension must match `csv` or `json` | **PASS** |
| symlink/output escape | real-directory checks and symlink refusal | **PASS** |
| SSRF via remote media | HTTPS, allowed port, DNS/IP safety, metadata/loopback/private-network rejection, no redirects | **PASS** |
| spreadsheet formula injection | leading apostrophe for dangerous CSV prefixes | **PASS** |
| direct operational-table mutation | static scan and runtime dry-run isolation | **PASS** |
| accidental REST exposure | route inventory check | **PASS** |
| serialized payload execution | payload treated as data, no eval/include path | **PASS** |
| arbitrary private export | schema allowlist and nonpublic capability gate | **PASS** |

## 3. Executed security result

The PHP 8.3 / WordPress 6.4 / SQLite runner passed all 25 checks, including import/export capability boundaries, guest no-mutation, actor binding, private/protected inputs, taxonomy bounds, malformed/oversized/deep/duplicate input, path traversal, unsafe ports, loopback/private-network SSRF, direct SQL/privilege static checks, no REST surface, and serialized-payload handling.

## 4. Remote media qualification

Remote media requires `allow_remote_media=true` or the CLI opt-in. The safety adapter rejects HTTP, nonstandard/unsafe ports, loopback, metadata, private-network, and DNS-resolving unsafe addresses; it rejects redirects and bounds response size/MIME before sideloading. The exercised unsafe URL was not downloaded and was reported as `NOT VERIFIED`.

Successful download behavior against an allowed public provider, DNS rebinding behavior outside the fixture resolver, CDN/object-storage policy, and production network egress remain **NOT VERIFIED**. The system fails closed; unavailable verification is not treated as success.

## 5. Residual risk and deployment requirements

Before production use, independently verify native MySQL/MariaDB, WP-CLI binary permissions, reverse-proxy/CLI operational controls, filesystem ownership, object-cache/concurrency behavior, audit-log retention, legal approval for exported content, and the real provider/media network. Do not grant import/export capabilities to untrusted operators.
