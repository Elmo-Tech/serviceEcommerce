# Phase 0 Research: Identity and Authentication

**Feature:** `001-identity-authentication`  
**Status:** Synchronized with `spec.md` and `requirements.md`  
**Last Updated:** 2026-07-29

## 1. Repository and Runtime Baseline

Decision: retain Laravel Sanctum, MySQL-backed custom Refresh Tokens, and
Spatie Permission.

Rationale:

- these are the repository-aligned authentication and authorization primitives;
- the approved transport does not require JWT or OAuth2;
- MySQL transactions and `lockForUpdate()` support rotation, revocation,
  reuse detection, and recovery single-winner behavior;
- the backend remains authoritative for authentication and authorization.

Rejected alternatives:

- JWT-only authentication;
- Laravel Passport or an OAuth2 provider flow;
- customer authentication inside Feature 001.

## 2. Direct Separate-Domain Transport

Decision:

```text
React Admin on Hostinger Shared Hosting
-> direct HTTPS with exact allow-listed CORS
-> Laravel Backend API on a separate domain
```

Rationale:

- the Admin Frontend is external to the backend repository;
- Hostinger Shared Hosting is not required to provide a same-origin proxy;
- future Mobile can reuse the same JSON token contract;
- the design avoids cookie domain, path, SameSite, and ambient-credential
  concerns.

Rejected alternatives:

- same-origin Proxy/BFF;
- Cloudflare Worker;
- cookie-based SPA authentication;
- browser-session authentication.

## 3. Access Token

Decision:

- Laravel Sanctum Bearer Token;
- returned once in successful login or refresh JSON;
- sent as `Authorization: Bearer ...`;
- lifetime: `900` seconds;
- Web storage: runtime memory only.

Successful refresh atomically revokes every predecessor Sanctum Access Token
before issuing the replacement Access Token. After commit, only the newly
issued Access Token and Refresh Token remain active for the administrator.

## 4. Refresh Token

Decision:

- custom opaque rotating credential;
- generated from `64` cryptographically secure random bytes;
- encoded using an approved URL-safe representation preserving full entropy;
- lifetime: `2592000` seconds;
- returned once in successful login or refresh JSON;
- accepted only from the refresh JSON body;
- persisted only as `SHA-256` in the authoritative Refresh Token column.

Rejected sources and transports:

- cookies;
- query strings;
- alternate request headers;
- `localStorage`;
- `rand()`, `mt_rand()`, UUID-only secrets, timestamps, database IDs,
  counters, user-derived values, or hashes of predictable input.

## 5. Web Storage and Accepted XSS Risk

Decision:

- Access Token: runtime memory only;
- Refresh Token: `sessionStorage` only;
- Reset Token: runtime memory only.

Accepted risk:

```text
A successful XSS attack can read sessionStorage and steal the Refresh Token.
```

Required compensating controls:

- strict Content Security Policy;
- no `eval`;
- no `unsafe-inline` unless a higher authority explicitly permits it;
- no `dangerouslySetInnerHTML` without an approved sanitization boundary;
- safe output encoding;
- dependency lock files and vulnerability review;
- no token values in URLs, console output, logs, analytics, monitoring, or
  error reporting;
- short Access Token lifetime;
- Refresh Token rotation and reuse detection;
- all-session revocation on reuse;
- HTTPS-only production transport;
- exact allow-listed CORS.

`sessionStorage` is not equivalent to HttpOnly protection.

## 6. Future Mobile Readiness

Decision: future Mobile uses the same login and refresh HTTP contract.

Rules:

- Refresh Token storage belongs in OS-backed secure storage;
- examples include iOS Keychain, Android Keystore, and platform-backed Flutter
  Secure Storage;
- no Mobile-only authentication route or database discriminator is introduced;
- the one-active-Administrator-session rule remains global across Web and
  Mobile;
- login from one client replaces the previous active client session.

## 7. Route Ownership

Unauthenticated Admin Auth Operations:

```http
POST /api/v1/admin/auth/login
POST /api/v1/admin/auth/refresh
POST /api/v1/admin/auth/forgot-password
POST /api/v1/admin/auth/verify-forgot-password-code
POST /api/v1/admin/auth/reset-password
```

These operations require no Bearer Access Token, browser `Origin`, or CSRF
credential. Their approved throttles and request validation still apply.

Protected Admin Auth Operations:

```http
POST  /api/v1/admin/auth/logout
GET   /api/v1/admin/auth/profile
PATCH /api/v1/admin/auth/profile
PUT   /api/v1/admin/auth/change-password
```

Middleware order:

```text
auth:sanctum
-> EnsureUserIsAdministrator
-> EnsureAdminIsActive
-> endpoint
```

## 8. CORS

Decision: use the exact configured `ADMIN_FRONTEND_ORIGIN`.

The example:

```text
https://admin.frontend-example.com
```

is documentation only and is not a hard-coded production origin.

Policy:

- `supports_credentials=false`;
- no wildcard origin;
- no arbitrary Origin reflection;
- no `Access-Control-Allow-Credentials: true`;
- allowed methods are only the documented API methods plus `OPTIONS`;
- allowed request headers include `Authorization`, `Content-Type`, `Accept`,
  `Accept-Language`, and approved request/correlation headers;
- exposed headers include `Content-Language`, approved rate-limit headers, and
  approved request/correlation headers.

CORS is a browser access policy, not authentication. Postman and future Mobile
still require valid credentials.

## 9. Refresh Throttling

Decision:

```text
10 requests per minute per resolved requester IP
```

The submitted Refresh Token does not influence or create the limiter bucket.

The requester IP must be resolved through the repository-approved trusted-proxy
configuration so untrusted forwarding headers cannot create arbitrary buckets.

Processing order:

```text
resolve requester-IP limiter key
-> apply refresh throttle
-> RefreshTokenRequest validation
-> basic token-format validation
-> SHA-256 authoritative lookup
-> transaction
-> lockForUpdate()
-> revalidate token and owner
-> revoke predecessor Access Tokens
-> rotate Refresh Token
-> issue replacement token pair or reject
```

This prevents changing token values from bypassing the limiter and ensures
malformed requests are also throttled.

## 10. Rotation, Reuse, and Concurrency

Decision: preserve transaction-safe rotation and single-winner behavior.

Refresh success:

1. lock the predecessor Refresh Token row;
2. revalidate token, owner, expiry, active state, family, and rotation state;
3. revoke every predecessor Sanctum Access Token;
4. mark the predecessor Refresh Token rotated;
5. create its successor;
6. issue the replacement Access Token;
7. commit.

Only one concurrent request may win for one predecessor Refresh Token.

Reuse of a rotated predecessor returns `401 REFRESH_TOKEN_INVALID` and revokes
all sessions for the administrator.

## 11. Password Recovery

Decision:

- Forgot Password mail is synchronous;
- workflow state is committed before mail delivery;
- eligible mail failure invalidates only the new workflow and returns
  `503 MAIL_SERVICE_UNAVAILABLE`;
- recovery code: cryptographically secure six-digit numeric value;
- code lifetime: `600` seconds;
- maximum failed verification attempts: `5`;
- Reset Token: at least `32` cryptographically secure random bytes, URL-safe;
- Reset Token lifetime: `600` seconds;
- verification and reset each enforce a single winner.

Forgot Password response boundaries:

```text
200: eligible success, unknown email, inactive administrator
422: validation failure
429: rate limit
503: eligible workflow mail transport failure
```

Concurrent eligible first-time requests are serialized by locking the stable
`users` row. At most one newly usable workflow and one mail transport
invocation remain for the serialized outcome.

## 12. API Safety and Localization

Success envelope:

```json
{
  "success": true,
  "message": "Localized safe message",
  "data": {}
}
```

Error envelope:

```json
{
  "success": false,
  "message": "Localized safe message",
  "code": "STABLE_ENGLISH_CODE",
  "errors": null
}
```

Rules:

- Arabic is the default locale;
- English is the fallback;
- locale is resolved before validation;
- machine codes and request keys remain English;
- sensitive auth responses preserve:
  - `Content-Language`;
  - `Vary: Accept-Language`;
  - `Cache-Control: no-store, private`;
  - `Pragma: no-cache`.

## 13. Verification Ownership

Backend repository:

- API, token lifecycle, concurrency, CORS, headers, errors, persistence,
  revocation, recovery, and avatar tests.

Owning React repository:

- memory-only Access Token;
- `sessionStorage`-only Refresh Token;
- reload bootstrap;
- token replacement and auth-state clearing;
- `Authorization` injection;
- `withCredentials=false`;
- no client-side token leakage;
- testable CSP behavior.

Architecture/documentation verification:

- future Mobile secure-storage guidance;
- accepted XSS risk and controls;
- route ownership;
- error inventory;
- absence of obsolete cookie, CSRF, and Proxy/BFF requirements.

If the React repository or its evidence is unavailable, frontend verification
remains blocked and full Feature 001 completion must not be claimed.

## Research Resolution

Approved architecture:

```text
Direct separate-domain Admin authentication using Sanctum Bearer Access Tokens
and rotating Refresh Tokens returned in JSON.
```

Open research questions:

- none.
