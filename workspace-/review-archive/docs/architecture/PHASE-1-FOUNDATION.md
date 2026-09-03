# Phase 1 Foundation
The lifecycle is plugin file → autoloader → singleton bootstrap → registry → context hooks. The main file contains constants, loading, lifecycle hooks and one bootstrap call. Services are lazy/singleton where useful. Admin, REST and CLI are context-gated. No property feature or CPT is registered.

Identity and support are defined in SOURCE-OF-TRUTH. Administrator receives four additive capabilities; unrelated caps are untouched. Settings are typed definitions split into General, Performance, Privacy and Advanced options. Logs are bounded, redacted option storage suitable only for foundation volume. Diagnostics is contract-based. The authenticated status endpoint and CLI expose non-secret summaries.
