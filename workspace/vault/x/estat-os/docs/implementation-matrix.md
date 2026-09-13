# Implementation Matrix

Every major requirement from the master prompt, the code that implements it, its
status, and how it is verified. Statuses are assigned from **observed test
behaviour**, not from the presence of code.

Legend — **Status**: COMPLETE / PARTIAL / MISSING.
**Verified**: `unit` = `tests/run-tests.php`, `integration` = `tests/integration/run-integration.php`
against real WordPress source, `manual` = read and reasoned about but not executed.

| # | Requirement | Implementing module | Status | Verified | Notes / tech debt |
|---|---|---|---|---|---|
| 1 | Plugin bootstrap, constants, autoload | `estat-os.php`, `includes/Autoloader.php`, `includes/Plugin.php` | COMPLETE | integration | Boots under real WP; version guard added during QA |
| 2 | Activation, table creation | `Install/Activator.php`, `Install/Schema.php` | COMPLETE | integration | All 8 tables built from real `CREATE TABLE` SQL |
| 3 | Migrations, upgrade safety | `Install/Migrator.php` | COMPLETE | integration | Re-running the upgrade is a verified no-op; no data loss |
| 4 | Deactivation keeps data | `Install/Deactivator.php` | COMPLETE | integration | Only unschedules cron |
| 5 | Uninstall, opt-in delete | `uninstall.php` | COMPLETE | integration (static) | Guarded by `WP_UNINSTALL_PLUGIN` + `delete_data_on_uninstall`; **destructive path never executed** |
| 6 | Listings CRUD | `Data/Listings.php` | COMPLETE | integration | Create/read/update/delete, validation, derived fields |
| 7 | Separate status axes | `Data/Meta.php`, `Support/Vocabulary.php` | COMPLETE | unit + integration | Axis disjointness asserted |
| 8 | Numeric price + display formatting | `Support/Format.php` | COMPLETE | unit + integration | `₹8.5 Cr` from `85000000` |
| 9 | Price on request hidden publicly | `Data/Listings.php`, `Frontend/Components.php` | COMPLETE | integration | Neither raw nor formatted figure reaches the page |
| 10 | Area normalised to sqft | `Support/Format.php`, `Data/Listings.php` | COMPLETE | unit + integration | 100 sqyd → 900 sqft on save |
| 11 | Completeness score | `Data/Listings.php` | COMPLETE | integration | Score bounded 0–100 with a missing-field list |
| 12 | Projects / societies | `Data/Projects.php` | COMPLETE | integration | Save, stats, external-id lookup |
| 13 | Team / agents | `Data/Agents.php` | COMPLETE | integration | Created and assigned to enquiries |
| 14 | Agencies | `Data/PostTypes.php` | COMPLETE | integration | Post type registered; thin feature by design |
| 15 | Leads / enquiries | `Leads/Leads.php` | COMPLETE | integration | Create, validate, assign, note, status, erase, count |
| 16 | Lead idempotency + dedupe | `Leads/Leads.php` | COMPLETE | integration | Idempotency key **and** content-hash window both verified |
| 17 | Site visits | `Leads/Visits.php` | COMPLETE | integration | Scheduling, duplicate guard, past-date refusal, outcomes |
| 18 | Visual form builder | `Forms/Forms.php`, `assets/js/form-builder.js` | COMPLETE | integration | Definition round-trips; `node --check` clean |
| 19 | 25 field types | `Forms/FieldTypes.php` | COMPLETE | integration | All have human labels |
| 20 | Responsive form widths | `Forms/Forms.php`, `Forms/Renderer.php` | COMPLETE | integration | Desktop/tablet/mobile widths survive a save and reach the markup |
| 21 | Conditional logic | `assets/js/forms.js` | PARTIAL | manual | Logic is client-side; **not exercised by an automated browser test** |
| 22 | Submissions → lead pipeline | `Forms/Submissions.php` | COMPLETE | integration | Submission creates a real enquiry |
| 23 | Submission idempotency | `Forms/Submissions.php` | COMPLETE | integration | Double submit returns the original enquiry |
| 24 | Search index | `Search/SearchIndex.php` | COMPLETE | integration | Auto-sync on save and delete; rebuild verified |
| 25 | Search / filter | `Search/Query.php` | COMPLETE | integration | Keyword, offer, price ceiling, paging, page-size cap, draft exclusion |
| 26 | CSV import | `Csv/Spreadsheet.php` | COMPLETE | integration | Preview, mapping, batched job, drafts on arrival |
| 27 | CSV duplicate handling | `Csv/Spreadsheet.php` | COMPLETE | integration | Repeated `external_id` updates rather than duplicates |
| 28 | CSV export | `Csv/Spreadsheet.php` | COMPLETE | integration | Formula-injection guard verified |
| 29 | REST API `estat/v1` | `Rest/RestApi.php` | COMPLETE | integration | All 11 documented routes registered and dispatched |
| 30 | Capabilities and roles | `Security/Roles.php` | COMPLETE | integration | Least-privilege verified: one permission does not unlock another |
| 31 | Nonces / CSRF | `Admin/Actions.php` | COMPLETE | integration | A nonce-less settings save is stopped and writes nothing |
| 32 | Rate limiting | `Rest/RestApi.php` | PARTIAL | integration (path only) | Runs on public reads; **threshold behaviour under load not asserted** |
| 33 | Webhooks, HMAC signing | `Webhooks/Webhooks.php` | COMPLETE | unit + integration | Forged signature, wrong secret and tampered body all rejected |
| 34 | Webhook delivery | `Webhooks/Webhooks.php` | COMPLETE | integration | Signed request captured and verified with the documented recipe; **the socket itself was never opened** |
| 35 | Notifications | `Notifications/Notifier.php` | COMPLETE | integration | **Bug fixed during QA**: `estat_lead_created` was never hooked, so only form enquiries mailed. Recipient, subject, body and opt-out all asserted |
| 36 | Audit log | `Audit/AuditLog.php` | COMPLETE | integration | Create/update/schedule/erase all audited with plain descriptions |
| 37 | Privacy export / erase | `Privacy/Privacy.php` | COMPLETE | integration | Correct WordPress shapes; address gone after erasure |
| 38 | Practice / demo mode | `Support/PracticeMode.php` | COMPLETE | integration | Seeds and clears without touching genuine records |
| 39 | Cron housekeeping | `Cron/Scheduler.php` | COMPLETE | integration | Both jobs schedule, run and unschedule |
| 40 | Settings | `Settings/Settings.php` | COMPLETE | integration | Cached: 20 reads cost at most one query |
| 41 | Admin screens (11) | `Admin/Screens/*` | COMPLETE | integration | Every screen renders markup, throws nothing, uses no jargon |
| 42 | Beginner-first menu | `Admin/Admin.php` | COMPLETE | integration | All 13 entries present in plain language |
| 43 | "Add a Home" no editor redirect | `Admin/Screens/AddHome.php` | COMPLETE | integration | Renders its own screen |
| 44 | Elementor bridge, 17 widgets | `Integrations/Elementor/*` | PARTIAL | integration (guard only) | Absence of Elementor is safe and verified; **widgets never rendered inside Elementor** |
| 45 | Public rendering fallback | `Frontend/*`, `templates/` | COMPLETE | integration | Cards, search form, team, pagination render themeless |
| 46 | Shortcodes (7) | `Frontend/Shortcodes.php` | COMPLETE | integration | All registered; three rendered |
| 47 | SEO schema | `Frontend/Schema.php` | PARTIAL | manual | JSON-LD built but **not validated against a schema validator** |
| 48 | Multilingual, Hinglish | `I18n/Language.php`, `languages/` | COMPLETE | integration | Both locales offered; both `.mo` files ship |
| 49 | Favorites / compare | `assets/js/public.js` | PARTIAL | manual | localStorage feature; **no browser test** |
| 50 | Maps (Leaflet/OSM) | `Frontend/Components.php` | PARTIAL | manual | Markup emitted; **no browser test** |
| 51 | Documentation | `README.md`, `docs/*` | COMPLETE | manual | Eight documents including this matrix |
| 52 | No Composer / ACF / React / Elasticsearch | whole tree | COMPLETE | integration | Custom autoloader; boots with nothing installed |

## Architectural notes and tech debt

1. **Two classes named `Schema`** — `Install\Schema` (tables) and `Frontend\Schema`
   (JSON-LD). Legal because they are namespaced, but confusing to read. Renaming
   is a breaking change for anyone calling them, so it is deliberately deferred.
2. **`Widgets.php` is 1,278 lines** holding all 17 widgets. It works and is
   consistent, but splitting one class per file would be easier to maintain.
3. **Client-side conditional logic and favorites** have no automated coverage
   because the environment has no browser. They are the largest untested surface.
4. **`Schema::healthy()` is consulted from 34 call sites.** The result is now
   cached per request and in the object cache; without that caching it was the
   single worst performance problem in the plugin (see the release report).
