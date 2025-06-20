# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_AUTH_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

You can obtain your authentication token by logging in via the `/login` endpoint. The token should be included in the Authorization header as `Bearer {token}`.
