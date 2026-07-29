# Implementation Plan: Identity and Authentication

**Feature:** `001-identity-authentication`  
**Branch:** `001-identity-authentication`  
**Status:** Ready for Reimplementation after artifact synchronization  
**Last Updated:** 2026-07-29

## Summary

Reimplement Feature 001 around direct separate-domain Admin authentication:

```text
React Admin
-> direct HTTPS
-> exact configured ADMIN_FRONTEND_ORIGIN CORS allow-list
-> Laravel Backend API
-> Sanctum Bearer Access Token + rotating JSON Refresh Token
```

Architecture constraints:

- browser calls backend directly;
- `supports_credentials=false`;
- no authentication cookies;
- no CSRF;
- no Origin-gated refresh middleware;
- no Proxy/BFF or Cloudflare Worker;
- no browser-session authentication;
- no JWT replacement.

Example deployment values are documentation only:

```text
Admin Frontend: https://admin.frontend-example.com
Backend API:    https://api.backend-example.net/api/v1
VITE_API_BASE_URL=https://api.backend-example.net/api/v1
```

Production CORS uses the exact configured `ADMIN_FRONTEND_ORIGIN`, not a
hard-coded example.

## Technical Context

- Backend: Laravel 13, PHP 8.3, MySQL;
- authentication: Laravel Sanctum Access Tokens plus custom rotating Refresh
  Tokens;
- authorization: Spatie Permission;
- Web Access Token: runtime memory only;
- Web Refresh Token: `sessionStorage` only;
- Web Reset Token: runtime memory only;
- future Mobile Refresh Token: OS-backed secure storage;
- persistence: `users`, `personal_access_tokens`, `refresh_tokens`,
  `password_resets`, roles and permissions.

## Governance Gates

### Pre-Design Gate

- direct JSON token transport matches `spec.md`;
- Super Admin is the only authenticated MVP actor;
- customers remain unauthenticated;
- no registration, MFA, social login, email verification, session-management
  UI, or device management;
- canonical surface remains `9` operations across `8` paths.

### Post-Design Gate

- all artifacts use the same token transport and route ownership;
- one active Administrator session remains global across Web and Mobile;
- successful refresh revokes predecessor Access Tokens atomically;
- exact configured-origin CORS remains mandatory;
- recovery mail remains synchronous after commit;
- no auth Queue Job, cleanup Command, Cron, or scheduler entry;
- mandatory external React verification is identified and cannot be silently
  claimed complete from the backend repository.

## Canonical API Surface

Unauthenticated Admin Auth Operations:

```http
POST /api/v1/admin/auth/login
POST /api/v1/admin/auth/refresh
POST /api/v1/admin/auth/forgot-password
POST /api/v1/admin/auth/verify-forgot-password-code
POST /api/v1/admin/auth/reset-password
```

Protected Admin Auth Operations:

```http
POST  /api/v1/admin/auth/logout
GET   /api/v1/admin/auth/profile
PATCH /api/v1/admin/auth/profile
PUT   /api/v1/admin/auth/change-password
```

Protected middleware order:

```text
auth:sanctum
-> EnsureUserIsAdministrator
-> EnsureAdminIsActive
-> endpoint
```

`POST /api/v1/admin/auth/profile` with `_method=PATCH` is multipart transport
compatibility only and is not a tenth operation.

## Token and Session Design

### Access Token

- Sanctum Bearer Token;
- lifetime: `900` seconds;
- returned once in successful login or refresh JSON;
- Web storage: memory only;
- never logged or placed in persistent browser storage.

### Refresh Token

- custom opaque rotating token;
- `64` cryptographically secure random bytes;
- approved URL-safe representation preserving entropy;
- lifetime: `2592000` seconds;
- returned once in successful login or refresh JSON;
- accepted only as JSON body `refreshToken`;
- persisted only as SHA-256;
- Web storage: `sessionStorage` only;
- future Mobile storage: OS secure storage only.

### Reset Token

- at least `32` cryptographically secure random bytes;
- approved URL-safe representation;
- lifetime: `600` seconds;
- returned once by successful code verification;
- client storage: runtime memory only;
- persisted only as SHA-256.

Forbidden token generators:

```text
rand()
mt_rand()
UUID-only secrets
predictable timestamps
database IDs
incrementing counters
email/user-derived values
hashes of predictable values without secure randomness
```

## Lifetimes and Throttles

| Control | Contract |
|---|---|
| Access Token | `900` seconds |
| Refresh Token | `2592000` seconds |
| Recovery code | `600` seconds |
| Reset Token | `600` seconds |
| Login | `5/min` by normalized email + requester IP |
| Refresh | `10/min` by requester IP + SHA-256 fingerprint of the structurally valid submitted Refresh Token |
| Password change | `5/min` per authenticated Administrator |
| Forgot Password | `5/min` by normalized email + requester IP |
| Forgot Password cooldown | `60` seconds |
| Recovery failed attempts | maximum `5` |

Refresh requester IP must use repository-approved trusted-proxy configuration.
The plaintext Refresh Token must never appear in the limiter key or logs. For
structurally valid submitted string tokens, the limiter key is requester IP plus
a deterministic SHA-256 fingerprint of the submitted Refresh Token. If the
request is structurally invalid and no valid string token is available, the
limiter falls back to an IP-only key. This throttle fingerprint is only for rate
limiting and never replaces the authoritative refresh-token hash lookup.

## Refresh Transaction

Processing order:

```text
request-shape validation
-> derive refresh throttle key
-> apply 10/min refresh throttle
-> token-format validation
-> SHA-256 authoritative lookup
-> transaction
-> lockForUpdate()
-> revalidate token and owner
-> revoke predecessor Sanctum Access Tokens
-> rotate Refresh Token
-> issue replacement token pair
-> commit
```

Success invariant:

```text
only the replacement Access Token is active
only the replacement Refresh Token is active
```

A rotated predecessor presented again returns
`401 REFRESH_TOKEN_INVALID` and revokes all sessions.

## Response and Error Contract

Success:

```json
{
  "success": true,
  "message": "Localized safe message",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Localized safe message",
  "code": "STABLE_ENGLISH_CODE",
  "errors": null
}
```

Stable errors:

| Code | HTTP |
|---|---:|
| `INVALID_CREDENTIALS` | 401 |
| `USER_INACTIVE` | 403 |
| `UNAUTHENTICATED` | 401 |
| `VALIDATION_ERROR` | 422 |
| `RATE_LIMITED` | 429 |
| `REFRESH_TOKEN_INVALID` | 401 |
| `CURRENT_PASSWORD_INVALID` | 422 |
| `PASSWORD_RESET_CODE_INVALID` | 422 |
| `PASSWORD_RESET_TOKEN_INVALID` | 422 |
| `MAIL_SERVICE_UNAVAILABLE` | 503 |

Sensitive auth responses preserve:

```text
Content-Language
Vary: Accept-Language
Cache-Control: no-store, private
Pragma: no-cache
```

Arabic is default, English is fallback, and locale is resolved before
validation.

## CORS

Production configuration:

```text
origin: exact ADMIN_FRONTEND_ORIGIN
supports_credentials: false
```

Rules:

- no wildcard or dynamic reflection;
- no credentialed CORS;
- methods: documented API methods plus `OPTIONS`;
- request headers: `Authorization`, `Content-Type`, `Accept`,
  `Accept-Language`, and approved request/correlation headers;
- exposed headers: `Content-Language`, approved rate-limit headers, and
  approved request/correlation headers.

## Provisioning

Implementation must provide idempotent Super Admin provisioning:

- environment-backed name, email, and password;
- no default production credential;
- safe failure for missing or invalid configuration;
- email normalization with `trim()` + `mb_strtolower()`;
- unique normalized email;
- approved password hash;
- correct Admin type and active state;
- idempotent `super-admin` role creation and assignment;
- rerun preserves password and identity;
- rerun creates no duplicate user or role.

## Profile and Avatar

Profile output exactly:

```text
name
email
avatar
role
permissions
```

Mutable input:

```text
name
avatar
_method
```

Avatar:

- `jpg`, `jpeg`, `png`, `webp`;
- matching detected MIME;
- maximum `2 MB`;
- generated random filename;
- approved public disk;
- public URL or `null` only;
- absent/empty avatar preserves current value;
- no delete-only flow.

Compensation:

1. store new file;
2. update metadata transactionally;
3. delete new file on database failure;
4. after commit, attempt old-file deletion;
5. cleanup failure does not fail committed update;
6. safe metadata logging only;
7. no cleanup Job.

## Password Change

- requires `currentPassword`, `newPassword`,
  `newPasswordConfirmation`;
- no secret input transformation;
- minimum 10 characters with lowercase, uppercase, number, and symbol;
- confirmation required;
- new password differs from current password;
- incorrect current password returns
  `422 CURRENT_PASSWORD_INVALID`;
- success revokes all sessions and active reset workflows;
- no new auth token returned.

## Password Recovery

Forgot Password:

- normalized email;
- `5/min` by normalized email + IP;
- `60`-second cooldown;
- stable `users` row lock;
- cooldown and eligibility rechecked under lock;
- prior usable workflows invalidated;
- one new workflow created;
- mail sent synchronously after commit;
- mail failure invalidates only the new workflow.

Response boundary:

```text
200: eligible success, unknown email, inactive Administrator
422: validation
429: rate limit
503: mail transport failure
```

Recovery mail contains only:

- six-digit code;
- ten-minute expiry statement;
- ignore-if-unrequested guidance.

Code verification:

- exact six-digit input;
- no secret transformation;
- maximum five failed attempts;
- row lock and one winner;
- unusable code returns `PASSWORD_RESET_CODE_INVALID`;
- success returns one Reset Token for `600` seconds.

Password reset:

- normalized email, Reset Token, password, confirmation;
- no secret transformation;
- transaction and row lock;
- one winner;
- unusable Reset Token returns `PASSWORD_RESET_TOKEN_INVALID`;
- success consumes workflow and revokes all sessions;
- no new auth token.

## Implementation File Expectations

Backend implementation is expected to touch:

```text
app/Actions/Auth/*
app/Services/Auth/*
app/Support/Auth/*
app/Http/Requests/Api/V1/Admin/Auth/*
app/Http/Controllers/Api/V1/Admin/Auth/*
app/Http/Resources/Api/V1/Admin/Auth/*
app/Models/RefreshToken.php
app/Models/PasswordReset.php
routes/api/v1/auth.php
config/cors.php
config/sanctum.php
bootstrap/app.php
auth migrations
seeders
lang files
backend tests
Postman documentation
```

Removed implementation concepts:

```text
AdminAuthCookieService
AdminCsrfService
ValidateAdminOrigin
ValidateAdminCsrfToken
Proxy/BFF phases
Cloudflare Worker phases
```

External React repository responsibilities:

- memory-only Access Token;
- `sessionStorage` Refresh Token;
- reload bootstrap;
- rotation replacement;
- auth-state clearing;
- `Authorization` injection;
- `withCredentials=false`;
- CSP and leakage controls;
- mandatory frontend tests.

If the React repository is unavailable, these tasks remain blocked and full
Feature 001 completion is not claimed.

## Required Design Artifacts

- `research.md`;
- `data-model.md`;
- `contracts/openapi.yaml`;
- `quickstart.md`;
- `tasks.md` after all preceding artifacts are synchronized.

## Verification Definition of Done

- all artifacts describe the same direct-token architecture;
- OpenAPI contains `9` operations and `8` unique paths;
- every operation documents `Accept-Language`;
- stable errors use exact codes and statuses;
- auth responses document the required cache/localization headers;
- CORS contract uses configured `ADMIN_FRONTEND_ORIGIN`;
- refresh throttle uses requester IP plus SHA-256 token fingerprint where
  structurally possible, with IP-only fallback for structurally invalid input;
- successful refresh invalidates predecessor Access Tokens;
- reuse revokes all sessions;
- recovery concurrency and mail failure behavior are tested;
- profile and avatar compensation are tested;
- secret persistence and leakage boundaries are tested;
- mandatory React evidence exists before full feature completion;
- implementation status remains `Ready for /speckit.implement` only after
  `tasks.md` is regenerated from synchronized artifacts.
