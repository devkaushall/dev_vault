# Phase 7 Leads Architecture

**Implementation status:** locked and implemented in RealEstate Platform Core 0.7.0.

## Canonical aggregate

`Leads\\LeadService` is the only canonical Lead Engine. A Lead is a private operational record created from a validated submission. A form, REST request, future Elementor adapter, or future Mayfair adapter must delegate to it rather than create its own storage or status logic.

## Lead fields

| Field | Storage | Privacy | Rule |
|---|---|---|---|
| id | `rep_leads.id` | internal | never accepted from public input |
| user_id | `rep_leads.user_id` | private | derived from authenticated session |
| name/email/phone | `rep_leads` | private | validated and sanitized |
| source | `rep_leads.source` | internal | allowlisted channel |
| status | `rep_leads.status` | private | server-owned state machine |
| property/project | `rep_leads.property_id/project_id` | private | derived only after published-type validation |
| agent/agency | `rep_leads.agent_id/agency_id` | private | assigned by authorized service only |
| consent | `rep_leads.consent_granted/consent_at` | private | explicit affirmative consent |
| request metadata | `rep_lead_requests.metadata_json` | private | minimized; no raw IP or credentials |
| timestamps | `rep_leads` | private | server generated |

Raw IP and user-agent values are not stored. Only keyed hashes may be retained for abuse controls.

## Status state machine

```text
new → contacted → qualified → converted
new → lost
contacted → lost
qualified → lost
new/contacted → spam
```

`converted`, `lost`, and `spam` are terminal in the first implementation. Invalid transitions fail without mutation. Every valid transition writes a status-history row.

## Assignment

Only `manage_leads`/`assign_leads` authorized actors may assign Agent/Agency. The target Agent must belong to the target Agency through the Phase 6 canonical relationship. Public submission cannot supply assignment values. Assignment changes write an assignment-history row and do not change lead status implicitly. Lead listing/reading uses the separate view/manage boundary; status changes use the edit/manage boundary; REST mutations additionally require a valid REST nonce.

## Requests

A request/inquiry is a child record of a Lead in `rep_lead_requests`; it is not a second CRM or Lead Engine. Submissions with an idempotency key use a deterministic dedupe key; submissions without one use a bounded ten-minute contact/context/message bucket. Unique-key races are recovered by reading the winning record. Privacy erasure clears the lead dedupe key and cancels queued notices.

## Security and API

Public submission returns acknowledgement only. Private lead reads/listing and mutations require ownership or the appropriate capabilities. All IDs use `Security\\StrictId`; public serialization is an explicit allowlist. Rejected requests must not mutate lead, request, relationship, history or notification state. REST exposes public submission routes plus authenticated read and mutation routes; no duplicate Phase 7 AJAX storage/transport exists.
