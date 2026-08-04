# Direct-Token Separate-Domain Admin Authentication Cutover

**Last Updated:** 2026-07-29

## 1. Target Production Shape

The Admin dashboard and Laravel API are deployed on separate HTTPS origins. The
browser calls the Laravel API directly.

Example values only:

| Owner | Setting | Example |
|---|---|---|
| React Admin | `VITE_API_BASE_URL` | `https://api.backend-example.net/api/v1` |
| Laravel Backend | `APP_URL` | `https://api.backend-example.net` |
| Laravel Backend | `ADMIN_FRONTEND_ORIGIN` | `https://admin.frontend-example.com` |

Production must use the real deployed Admin origin in
`ADMIN_FRONTEND_ORIGIN`. Do not hard-code the example domain.

The active architecture is:

```text
React Admin -> direct HTTPS -> Laravel API
```

Not used:

- proxy or BFF authentication
- Cloudflare Worker authentication bridge
- authentication cookies
- CSRF refresh flow
- Origin-gated refresh middleware
- JWT replacement

## 2. Backend Environment

Required authentication and provisioning values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.backend-example.net

ADMIN_FRONTEND_ORIGIN=https://admin.frontend-example.com

SUPER_ADMIN_NAME="Service Commerce Super Admin"
SUPER_ADMIN_EMAIL=admin@example.com
SUPER_ADMIN_PASSWORD=

AUTH_ACCESS_TOKEN_TTL_MINUTES=null
AUTH_REFRESH_TOKEN_TTL_MINUTES=43200
AUTH_PASSWORD_RESET_CODE_TTL_MINUTES=10
AUTH_PASSWORD_RESET_TOKEN_TTL_MINUTES=10
AUTH_PASSWORD_RESET_MAX_ATTEMPTS=5
AUTH_PASSWORD_RESET_RESEND_COOLDOWN_SECONDS=60
SANCTUM_EXPIRATION=null

FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
```

Rules:

- `SUPER_ADMIN_PASSWORD` must be set to a strong value before seeding.
- `ADMIN_FRONTEND_ORIGIN` must be one exact origin with no path, credentials,
  query string, fragment, or wildcard.
- Production `APP_URL` and `ADMIN_FRONTEND_ORIGIN` must both use HTTPS.
- CORS must keep `supports_credentials=false`.
- Postman and future Mobile clients still authenticate with Bearer tokens;
  CORS is only a browser policy.

## 3. Trusted Proxies and Requester IP

Refresh throttling depends on the requester IP. The deployed web server or
hosting proxy must forward the real client IP only through trusted proxy
configuration.

Deployment checks:

- confirm the platform's trusted proxy rules before using forwarded headers;
- confirm untrusted `X-Forwarded-For` values cannot create separate throttle
  buckets;
- verify the refresh limiter uses requester IP plus a SHA-256 fingerprint of a
  structurally valid submitted `refreshToken`;
- verify structurally invalid refresh requests fall back to an IP-only bucket;
- verify plaintext tokens never appear in logs or limiter diagnostics.

## 4. CORS and Headers

Required CORS behavior:

- allowed origin is exactly `ADMIN_FRONTEND_ORIGIN`;
- no wildcard origin;
- no arbitrary Origin reflection;
- `supports_credentials=false`;
- no `Access-Control-Allow-Credentials: true`;
- allowed methods are documented API methods plus `OPTIONS`;
- allowed request headers include `Authorization`, `Content-Type`, `Accept`,
  `Accept-Language`, `Origin`, and `X-Requested-With`;
- exposed headers include at least `Content-Language`.

Every Admin Auth response must preserve:

```text
Content-Language: ar or en
Vary: Accept-Language
Cache-Control: no-store, private
Pragma: no-cache
```

## 5. Token Model After Cutover

- Access Token is returned in login and refresh JSON and stored by Web only in
  runtime memory.
- Refresh Token is returned in login and refresh JSON and stored by Web only in
  `sessionStorage`.
- Reset Token is returned by successful code verification and stored by Web
  only in runtime memory.
- No token is stored in cookies, URLs, `localStorage`, IndexedDB, analytics,
  monitoring, logs, or browser console.
- Successful login or refresh leaves only one active Access Token and one
  active Refresh Token for the administrator.
- Refresh Token reuse revokes every administrator session.

## 6. Mail and Recovery

Password-recovery mail is synchronous for Feature 001.

Deployment checks:

- configure a real production mailer and `MAIL_FROM_ADDRESS`;
- run forgot-password against an eligible Admin in staging;
- confirm the workflow commits before mail delivery;
- confirm the email contains only the six-digit code, ten-minute expiry
  statement, and ignore-if-unrequested guidance;
- simulate mail transport failure in staging or a controlled test environment
  and confirm `503 MAIL_SERVICE_UNAVAILABLE`;
- confirm mail failure invalidates only the newly created workflow.

Authentication recovery must not introduce Queue Jobs, Cron, scheduler entries,
or cleanup Commands.

## 7. Public Avatar Storage

Admin avatar upload uses the approved public filesystem disk.

Deployment checks:

- run `php artisan storage:link` when the hosting environment needs the public
  storage symlink;
- confirm uploaded files cannot execute as scripts;
- confirm directory listing is disabled;
- confirm profile responses expose only a public URL or `null`;
- confirm raw disk names and storage paths are never returned.

Public storage is not private authorization. Possession of an exact public URL
can read the file outside Laravel authorization.

## 8. One-Time Revocation and Fresh Login

Before switching production traffic:

1. back up the database through the approved operational workflow;
2. deploy backend and frontend changes together;
3. revoke existing administrator Access Tokens and Refresh Tokens;
4. require a fresh administrator login after deployment.

Use the secure database administration workflow and stage the exact SQL first.
The revocation must be scoped to administrator credentials only.

## 9. Staging Verification

Run these checks from the deployed React Admin and a non-browser API client:

- frontend domain and backend domain differ;
- browser login goes directly to the backend and succeeds;
- approved preflight succeeds;
- unapproved origin receives no CORS approval;
- login returns both tokens in JSON and no `Set-Cookie`;
- refresh sends `refreshToken` in JSON only;
- refresh rotation invalidates the previous Access Token and Refresh Token;
- reuse of the rotated Refresh Token revokes all sessions;
- same-tab reload restores auth only through refresh bootstrap;
- logout revokes server tokens and clears client state;
- profile returns exactly `name`, `email`, `avatar`, `role`, `permissions`;
- forgot-password returns enumeration-safe `200` for unknown and inactive
  targets;
- mail transport failure returns `503 MAIL_SERVICE_UNAVAILABLE`;
- verify-code success returns only `resetToken` and `resetTokenExpiresIn: 600`;
- every response has the required localization and cache headers.

External React evidence required before full Feature 001 completion:

- Access Token memory-only tests;
- Refresh Token `sessionStorage`-only tests;
- Reset Token memory-only tests;
- reload bootstrap and refresh replacement tests;
- `withCredentials=false` evidence;
- CSP and client-side token leakage evidence.

If the owning React repository or deployment evidence is unavailable, Feature
001 remains blocked on external verification and must not be claimed complete.

## 10. Rollback

Rollback means reverting both frontend and backend to a prior compatible auth
version and requiring a fresh login again.

Rules:

- do not run cookie and JSON refresh-token transports together;
- do not keep stale CSRF or cookie assumptions in frontend code;
- revoke administrator sessions minted during the failed cutover;
- re-run login, refresh, logout, CORS, and response-header checks before
  reopening Admin access;
- record whether frontend CSP and token-storage evidence remains valid after
  rollback.
