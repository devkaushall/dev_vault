# Phase 7 Site Visits

**Implementation status:** locked and implemented in RealEstate Platform Core 0.7.0.

Site Visits are a separate operational workflow linked to the canonical Lead Engine; they are not a form field or a second lead store.

## State machine

```text
requested → scheduled → confirmed → completed
requested → cancelled
scheduled → reschedule_requested → scheduled
confirmed → reschedule_requested → confirmed
scheduled → cancelled
confirmed → cancelled
reschedule_requested → cancelled
```

`completed` and `cancelled` are terminal. Every state transition is validated server-side and recorded in `rep_site_visit_history`. An authorized scheduling update may also change a future window while retaining `scheduled` or `confirmed`; that reschedule event is recorded in the same history table. Rescheduling requires a valid future UTC interval of no more than twelve hours. A visit's Property must be published at creation; Agent/Agency assignment is derived from the Property's canonical profile metadata and is never trusted from public input.

## Access

Public users may submit a visit request only with a validated contact submission and explicit consent. Private reads and state changes require the lead owner for reads or a site-visit/lead workflow capability for mutations. Anonymous callers never receive visit or lead records. Authenticated REST mutations require a valid REST nonce.

## Replay and privacy

The site-visit dedupe key is unique and derived from normalized contact/context/window/idempotency data. A concurrent unique-key race returns the existing visit rather than creating a second one. Erasure removes the dedupe key, clears history notes, cancels queued notices, anonymizes the requester relationship, and cancels active visits.

## Notifications

Creation, scheduling, confirmation, reschedule and cancellation enqueue private notification outbox events. Delivery is asynchronous, provider-backed, claimed before delivery, retried with bounded backoff, and failure does not roll back or corrupt the visit state.
