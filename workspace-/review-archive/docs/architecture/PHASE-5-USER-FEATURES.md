# Phase 5 User Features Architecture

Persistent features are authenticated and keyed only by the current WordPress user ID resolved server-side. Services contain ownership and validation; future REST/AJAX adapters delegate to them. Favorites are idempotent. Saved searches reuse `SearchCriteria::canonical()` and a stable SHA-256 hash. Compare accepts client-held IDs, removes duplicates, caps four, validates public Property state and returns only approved fields.

Guests may maintain favorites/compare candidates locally in their browser, but no guest state is persisted server-side and no account is created implicitly. Saved searches and alerts require authentication.
