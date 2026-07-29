# Service Commerce Backend — Authentication Standards

> Scope: `/api/v1/admin/auth/*`

> Status: Project-wide mandatory standard

## 1. Final decisions

```text
Authenticated actor: Super Admin only
Customer authentication: Not supported

Access token lifetime: 900 seconds
Access token transport: Authorization Bearer
Access token returned in JSON: Yes
Access token Web storage: runtime memory only

Refresh token lifetime: 2592000 seconds
Refresh token returned in JSON: Yes
Refresh token refresh transport: JSON body only
Refresh token storage at rest: SHA-256 hash only
Refresh token Web storage: sessionStorage only
Refresh token Mobile storage: platform secure storage only

Authentication cookies: Not used
CSRF refresh flow: Not used
Origin-gated refresh middleware: Not used
Session policy: one active administrator session
```

## 2. Canonical operations

```http
POST  /api/v1/admin/auth/login
POST  /api/v1/admin/auth/refresh
POST  /api/v1/admin/auth/logout
GET   /api/v1/admin/auth/profile
PATCH /api/v1/admin/auth/profile
PUT   /api/v1/admin/auth/change-password
POST  /api/v1/admin/auth/forgot-password
POST  /api/v1/admin/auth/verify-forgot-password-code
POST  /api/v1/admin/auth/reset-password
```

Rules:

- exactly `9` operations
- exactly `8` unique paths
- `POST /profile` with `_method=PATCH` is allowed only as multipart transport
  compatibility

## 3. Middleware rules

Protected routes:

```text
auth:sanctum
-> EnsureUserIsAdministrator
-> EnsureAdminIsActive
-> endpoint
```

Refresh route:

```text
throttle
-> request validation
-> refresh action
```

## 4. Login rules

- login accepts only `email` and `password`
- normalize email only
- do not trim or transform password
- throttle: `5/min` by normalized email + IP
- success revokes every previous access and refresh token first
- login returns both plain tokens only once
- login sets no auth cookie
- invalid credentials return `401 INVALID_CREDENTIALS`
- inactive admin returns `403 USER_INACTIVE`

## 5. Refresh rules

- request body must contain only `refreshToken`
- request-shape errors use `422 VALIDATION_ERROR`
- structurally valid but unusable tokens use `401 REFRESH_TOKEN_INVALID`
- refresh returns `accessToken`, `refreshToken`, `tokenType`,
  `tokenExpiresIn`, `refreshTokenExpiresIn`
- refresh does not return the profile
- throttle: `10/min` by requester IP + safely available session context
- refresh rotation uses transaction + `lockForUpdate()`
- reuse revokes all sessions

## 6. Password and recovery rules

- password policy minimum: `10` chars with uppercase, lowercase, number,
  symbol
- password change throttle: `5/min` per authenticated admin
- forgot-password throttle: `5/min` by normalized email + IP
- forgot-password resend cooldown: `60` seconds
- recovery code lifetime: `600` seconds
- reset token lifetime: `600` seconds
- max failed verification attempts: `5`
- forgot-password mail is synchronous
- mail failure invalidates only the newly created workflow and returns
  `503 MAIL_SERVICE_UNAVAILABLE`

## 7. Profile rules

- profile output contains only `name`, `email`, `avatar`, `role`,
  `permissions`
- no user ID is returned
- `role` is one string
- avatar file types: `jpg`, `jpeg`, `png`, `webp`
- avatar max size: `2 MB`
- absent or empty-string avatar preserves current avatar
- no delete-only avatar flow

## 8. Transport and storage rules

Web Admin:

- access token: memory only
- refresh token: `sessionStorage` only
- clear both client states on logout or unrecoverable refresh failure

Future Mobile:

- same API contract
- refresh token stored only in OS secure storage

## 9. Required verification

- no authentication cookie emitted or required
- login returns both tokens in JSON
- refresh accepts only body `refreshToken`
- refresh returns rotated tokens in JSON
- route count remains `9` operations / `8` unique paths
- exact middleware order remains intact
