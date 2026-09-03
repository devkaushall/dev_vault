# Phase 7 Forms Architecture

**Implementation status:** locked and implemented in RealEstate Platform Core 0.7.0.

Forms are transport mechanisms, not business engines.

```text
REST / future AJAX / Elementor adapter
                  ↓
Forms\\SubmissionValidator
                  ↓
Validated Submission DTO
                  ↓
Requests\\RequestService
                  ↓
Leads\\LeadService
```

## Input contract

The validated submission contains name, email, optional phone, optional message, optional published Property/Project context, an allowlisted source, explicit consent, a honeypot result, and an optional idempotency key. The REST adapter also accepts the idempotency key from the `Idempotency-Key` header. Server-owned user identity, status, assignment, timestamps, IP, consent timestamp and notification state are never accepted from the client.

Text is sanitized before persistence; email and URLs/IDs are validated; payload lengths are bounded; unsupported values are rejected. Public submissions use a short-window IP/user-agent keyed rate limit and honeypot rejection. No raw IP or user-agent is persisted. Authenticated public submissions require a valid `X-WP-Nonce`; staff workflow mutations require the same REST nonce plus service-level capabilities.

## Transport decision

Phase 7 exposes a REST submission boundary. No duplicate Phase 7 AJAX transport is introduced because no Phase 7 frontend adapter exists in the current core. A future AJAX or Elementor adapter must call the same `RequestService` and must not write tables directly.

## Privacy

Contact fields, message, context and consent are private. Public responses contain acknowledgement/status only. The existing `PrivacyFoundation` owns export/erase integration; no second privacy system is introduced. Erasure also clears replay identity and cancels queued workflow notices.
