# Cross-Domain Admin Authentication Cutover

**Status:** Superseded by the direct-token separate-domain contract.
**Last Updated:** 2026-07-29

This document is retained only as a historical filename for older references.
The active Feature 001 deployment runbook is:

```text
docs/deployment/separate-domain-admin-auth-cutover.md
```

The current approved architecture is:

```text
React Admin -> direct HTTPS -> Laravel API
```

Active rules:

- Access Token is returned in JSON and sent as `Authorization: Bearer`.
- Refresh Token is returned in JSON and submitted only as JSON body
  `refreshToken`.
- Web stores Access Token in runtime memory only.
- Web stores Refresh Token in `sessionStorage` only.
- CORS uses exact `ADMIN_FRONTEND_ORIGIN`.
- `supports_credentials=false`.
- No authentication cookies.
- No CSRF refresh flow.
- No proxy/BFF authentication.
- No Cloudflare Worker authentication bridge.

Do not use the older cookie/proxy cutover notes for implementation,
deployment, testing, or rollback decisions.
