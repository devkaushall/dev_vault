# REST API — `estat/v1`

Base URL: `https://example.com/wp-json/estat/v1/`

Authentication for anything that is not public: a standard WordPress
[application password](https://wordpress.org/documentation/article/application-passwords/)
over HTTPS, or a logged-in session with the `X-WP-Nonce` header.

Anonymous requests to public routes are rate limited per IP address.

## Public routes

### `GET /properties`

Search published listings.

| Parameter | Type | Notes |
| --- | --- | --- |
| `keyword` | string | Matches the headline |
| `offer` | string | `sale`, `rent`, `lease` |
| `property_type` | string[] | |
| `locality` | int[] | Locality term IDs |
| `bedrooms_min` | int | |
| `price_max` | number | |
| `orderby` | string | `price_asc`, `price_desc`, `newest`, `featured` |
| `page` | int | |
| `per_page` | int | Maximum 60 |

Returns `{ items, total, pages, page }`. Listings marked *price on request*
report `price: null` and `price_on_request: true`; the number is never sent.

### `GET /properties/{id}`

One published listing. Private fields (internal notes, your reference number,
the full address, the completeness score) are only included when the request is
authenticated and the user can `estat_manage_listings`.

### `GET /projects/{id}/stats`

Counts and price range for one society or project.

### `GET /agents`

The public team list.

### `POST /forms/{id}/submit`

Submit a form. Body:

```json
{
  "fields": { "name": "Asha", "phone": "+91…", "message": "…" },
  "idempotency_key": "a-random-string",
  "consent": true
}
```

Returns `{ ok, message, lead_id, submission_id, redirect? }`, or a `WP_Error`
with status 400 (bad request), 404 (no such form), 422 (validation failed) or
429 (too many submissions). Sending the same `idempotency_key` twice is safe:
the second call returns the first result instead of creating a duplicate.

## Authenticated routes

| Route | Method | Capability |
| --- | --- | --- |
| `/leads` | GET, POST | `estat_manage_leads` |
| `/leads/{id}` | PATCH | `estat_manage_leads` (+ `estat_assign_leads` to change the owner) |
| `/visits` | GET, POST | `estat_manage_visits` |
| `/visits/{id}` | PATCH | `estat_manage_visits` |
| `/audit` | GET | `estat_view_audit` |
| `/maintenance/{task}` | POST | `estat_run_maintenance` |

Maintenance tasks: `rebuild_index` (batches of 200, returns progress),
`expire_listings`, `index_status`.

## Errors

Errors follow the WordPress convention:

```json
{ "code": "estat_invalid_scheduled_at",
  "message": "That date is in the past. Please pick a future date and time.",
  "data": { "status": 422 } }
```

Messages are written for humans and are safe to show to an end user.
