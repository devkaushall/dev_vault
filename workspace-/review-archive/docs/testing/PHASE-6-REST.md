# Phase 6 REST

Public bounded Agent/Agency list and single routes plus authenticated create/update/delete routes passed. Relationship routes delegate to `ProfileService`.

Implemented relationship routes:

- `PUT /realestate-platform/v1/agents/{id}/agency`
- `DELETE /realestate-platform/v1/agents/{id}/agency`
- `PUT /realestate-platform/v1/properties/{id}/profile`
- `DELETE /realestate-platform/v1/properties/{id}/profile`

The final contract covers anonymous access, owner actions, IDOR, invalid relationships, pagination bounds, XSS, public allowlists, Agent-to-Agency consistency, Agency deletion protection, and Property relationship removal.

All route IDs and pagination values use the shared strict positive-ID parser. Invalid values—including zero, negative, signed, alphabetic, decimal, array, object, null, and overflowing values—return controlled 4xx responses without silent coercion.
