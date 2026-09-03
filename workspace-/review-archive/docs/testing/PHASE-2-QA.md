# Phase 2 QA

## Required gates

- Property/Project/Insight registration only in Standalone mode; existing registrations remain untouched.
- Native CRUD, taxonomy assignment, media references, canonical meta sanitation/validation, and object capabilities.
- Field registry uniqueness, types, defaults, missing-data behavior, REST schemas, and optional ACF reads.
- Location normalization and coordinate bounds.
- REST public projection and unauthorized write rejection.
- XSS/unsafe URL/attachment/taxonomy/capability tests.
- Representative 10/100/1,000 entity query measurements without unbounded/N+1 behavior.
- PHP syntax, PHPStan level 8, PHPCS/WPCS, PHPUnit, PHP 8.1–8.3 Playground, clean extracted ZIP, and reproducibility.

## Current evidence

Unit tests cover canonical registry scoping, missing optional values, field sanitation/coordinate/attachment validation, and location normalization. The Playground harness now covers Standalone CPT/taxonomy registration, entity creation/update/read, taxonomy assignment, and the frozen Phase 1 suite. Full final results belong in `PHASE-2-IMPLEMENTATION-REPORT.md`.

Phase 1 external verification remains NOT VERIFIED and tracked independently.
