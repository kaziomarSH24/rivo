# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer Bearer {YOUR_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Login to obtain a personal access token. Include it in every protected request as: <code>Authorization: Bearer YOUR_TOKEN</code>. Public endpoints are explicitly marked.
