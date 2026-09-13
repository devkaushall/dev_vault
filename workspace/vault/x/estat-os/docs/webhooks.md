# Webhooks

Estat.OS can push events to another application — Zapier, Make, a CRM, or
something you wrote yourself. Configure it in
**Office Settings → Connections**.

## Events

| Event | Fires when |
| --- | --- |
| `lead.created` | A new enquiry arrives |
| `lead.updated` | An enquiry changes status or owner |
| `visit.scheduled` | A site visit is arranged |
| `visit.completed` | A site visit is marked as done |
| `listing.published` | A property goes live on the website |

## Request

`POST` with a JSON body and these headers:

| Header | Value |
| --- | --- |
| `Content-Type` | `application/json` |
| `X-Estat-Event` | the event name |
| `X-Estat-Timestamp` | Unix seconds |
| `X-Estat-Signature` | `sha256=<hex digest>` |

## Verifying the signature

The signed message is the timestamp, a literal dot, then the **raw** request
body — not a re-encoded version of it:

```php
$expected = 'sha256=' . hash_hmac(
	'sha256',
	$timestamp . '.' . $raw_body,
	$shared_secret
);
if ( ! hash_equals( $expected, $signature_header ) ) {
	http_response_code( 401 );
	exit;
}
```

```python
import hmac, hashlib
expected = 'sha256=' + hmac.new(
    secret.encode(), f"{timestamp}.{raw_body}".encode(), hashlib.sha256
).hexdigest()
```

Also reject anything where `X-Estat-Timestamp` is more than five minutes old.
That prevents somebody replaying a captured request.

## Delivery and retries

Deliveries are attempted in the background. A non-2xx response or a network
failure is retried with a growing delay. Every attempt is written to the
`estat_webhook_log` table. If a destination keeps failing, the office is emailed —
unless that notification is switched off in **Office Settings → Emails**.
