# Feature 001 — Identity and Authentication

**Project:** Service Commerce Backend

**Feature ID:** `001`

**Status:** Approved implementation reference

**Last Updated:** 2026-07-29

## 1. Summary

Feature 001 defines the complete administrator authentication surface for the
backend-only Service Commerce platform.

The approved architecture is:

```text
Admin Frontend: https://admin.frontend-example.com
Backend API:    https://api.backend-example.net/api/v1
Transport:      Direct browser-to-backend HTTPS
```

The approved token model is:

- access token: Laravel Sanctum Bearer token
- access token lifetime: `900` seconds
- refresh token: custom opaque rotating token
- refresh token lifetime: `2592000` seconds
- Web access-token storage: runtime memory only
- Web refresh-token storage: `sessionStorage` only
- future Mobile refresh-token storage: OS secure storage only

Feature 001 does not use:

- authentication cookies
- CSRF refresh flow
- Origin-gated refresh middleware
- proxy/BFF requirements
- Cloudflare Worker requirements
- customer authentication

## 2. Authenticated actor

The only authenticated MVP actor is the administrator stored in `users`.

Approved identity constraints:

- `users.type = 0` identifies the administrator account type
- `type` is a classifier only and does not replace authorization
- `super-admin` is the initial role
- `is_active` is enforced independently from role assignment

Protected self-service routes do not require a dedicated auth permission.
They require successful authentication plus administrator and active-account
checks.

## 3. Canonical route contract

Approved operations:

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

- total operations: `9`
- unique paths: `8`
- multipart profile update may use `POST /api/v1/admin/auth/profile` with
  `_method=PATCH`
- method spoofing is transport compatibility only, not a tenth operation

Not approved:

- `/api/v1/auth/*`
- `/register`
- `/me`
- `/logout-all`
- `/sessions`
- `/verify-email`
- `/resend-verification`

## 4. Middleware order

Protected routes use:

```text
auth:sanctum
-> EnsureUserIsAdministrator
-> EnsureAdminIsActive
-> endpoint
```

Refresh uses:

```text
request-shape validation
-> derive trusted-proxy-aware refresh throttle key
-> throttle
-> request validation
-> RefreshAdminSessionAction
```

Rules:

- Feature 001 refresh uses no CSRF middleware
- Feature 001 refresh uses no Origin-validation middleware
- self-service auth routes do not depend on Spatie permission middleware

## 5. Provisioning contract

Required environment-backed inputs:

```env
SUPER_ADMIN_NAME=
SUPER_ADMIN_EMAIL=
SUPER_ADMIN_PASSWORD=
SUPER_ADMIN_ADDITIONAL_USERS=
```

Provisioning behavior:

1. read environment-backed values
2. trim `name`
3. normalize `email` using lowercase + trim
4. validate password policy before persistence
5. create the `super-admin` role idempotently
6. find the user by normalized email
7. create the user if missing
8. enforce:
   - `type = 0`
   - `is_active = true`
9. hash the password when the user is created
10. assign the `super-admin` role idempotently
11. preserve the existing password on rerun
12. avoid duplicates
13. optionally seed additional administrator accounts from
    `SUPER_ADMIN_ADDITIONAL_USERS`

Safe-failure rule:

- missing or invalid required provisioning values must fail safely
- no default weak production password may be generated

## 6. Login contract

Request:

```json
{
  "email": "admin@example.com",
  "password": "Password@123"
}
```

Validation:

- `email`: required, string, email, max `255`
- `password`: required, string

Normalization:

- normalize email only
- do not trim or transform password bytes

Throttle:

- `5` requests per minute
- key: normalized email + requester IP

Success behavior:

1. find administrator by normalized email
2. validate `type`
3. validate `is_active`
4. verify password
5. start transaction
6. revoke all existing Sanctum access tokens
7. revoke all active refresh tokens
8. opportunistically clean eligible expired auth records for that actor scope
9. issue a new access token
10. issue a new refresh token
11. commit
12. return JSON response

Success response:

```json
{
  "success": true,
  "message": "Localized message",
  "data": {
    "accessToken": "plain-access-token",
    "refreshToken": "plain-refresh-token",
    "tokenType": "Bearer",
    "tokenExpiresIn": 900,
    "refreshTokenExpiresIn": 2592000,
    "profile": {
      "name": "Super Admin",
      "email": "admin@example.com",
      "avatar": null,
      "role": "super-admin",
      "permissions": []
    }
  }
}
```

Failure rules:

- unknown email and wrong password share the same `401 INVALID_CREDENTIALS`
  contract
- inactive admin returns `403 USER_INACTIVE`
- no authentication cookie is set
- one active session remains globally enforced

## 7. Refresh contract

Request:

```json
{
  "refreshToken": "opaque-refresh-token"
}
```

Accepted transport:

- JSON body only

Rejected transport:

- cookies
- query string
- alternate headers

Throttle:

- `10` requests per minute
- key: requester IP plus SHA-256 fingerprint of the structurally valid
  submitted `refreshToken`
- fallback key: requester IP only when no structurally valid string
  `refreshToken` is available

Processing order:

```text
request-shape validation
-> derive refresh throttle key
-> throttle
-> request validation
-> basic token-shape validation
-> SHA-256 hash lookup key
-> transaction
-> lockForUpdate() token row
-> validate owner, activity, expiry, revocation, family, and rotation state
-> rotate or reject
```

Success response:

```json
{
  "success": true,
  "message": "Localized message",
  "data": {
    "accessToken": "new-access-token",
    "refreshToken": "new-refresh-token",
    "tokenType": "Bearer",
    "tokenExpiresIn": 900,
    "refreshTokenExpiresIn": 2592000
  }
}
```

Rules:

- refresh response does not include the profile
- predecessor refresh token becomes unusable immediately
- predecessor access tokens are revoked according to the single-session policy
- structurally invalid request shape returns `422 VALIDATION_ERROR`
- structurally valid but unusable tokens return `401 REFRESH_TOKEN_INVALID`
- rotated-token reuse revokes all sessions for that administrator

## 8. Logout contract

Route:

```http
POST /api/v1/admin/auth/logout
```

Rules:

- requires valid Bearer access token
- requires administrator and active-account checks
- revokes every access token for the administrator
- revokes every refresh token for the administrator
- returns no replacement token

## 9. Profile contract

Approved profile shape:

```json
{
  "name": "Super Admin",
  "email": "admin@example.com",
  "avatar": "https://api.backend-example.net/storage/avatars/example.webp",
  "role": "super-admin",
  "permissions": [
    "dashboard.view"
  ]
}
```

Rules:

- returned fields are exactly `name`, `email`, `avatar`, `role`,
  `permissions`
- no user ID
- no roles array
- no `type`
- no `isActive`
- no raw avatar path

Profile update rules:

- mutable fields: `name`, `avatar`
- `name` is trimmed and limited to `150` characters
- avatar accepts only uploaded `jpg`, `jpeg`, `png`, `webp`
- avatar MIME must resolve to an approved image type
- avatar max size: `2 MB`
- avatar filenames are generated by the backend
- avatar storage uses the approved public disk
- response exposes only the public URL or `null`
- absent avatar preserves current avatar
- empty-string avatar preserves current avatar
- no delete-only avatar behavior
- no `removeAvatar`
- non-empty string path or URL values are rejected

Avatar compensation workflow:

1. store new file
2. attempt DB update
3. if DB update fails, delete the new file
4. if DB update succeeds, attempt synchronous old-file deletion
5. if old-file deletion fails, log safely and keep API success

Profile update does not rotate tokens.

## 10. Password change contract

Route:

```http
PUT /api/v1/admin/auth/change-password
```

Required inputs:

- `currentPassword`
- `newPassword`
- `newPasswordConfirmation`

Password policy:

- minimum length: `10`
- at least one lowercase letter
- at least one uppercase letter
- at least one number
- at least one symbol
- new password must differ from current password

Throttle:

- `5` requests per minute per authenticated administrator

Rules:

- invalid current password returns the approved
  `CURRENT_PASSWORD_INVALID` contract
- successful password change updates the hash transactionally
- successful password change revokes all access and refresh tokens
- no replacement token is returned automatically

## 11. Forgot-password and reset contract

### 11.1 Forgot password

Route:

```http
POST /api/v1/admin/auth/forgot-password
```

Throttle:

- `5` requests per minute
- key: normalized email + IP

Cooldown:

- resend cooldown: `60` seconds

Success contract:

- always returns enumeration-safe `200`

Eligible-flow behavior:

1. normalize email
2. locate active administrator
3. open transaction
4. lock the owning `users` row with `lockForUpdate()`
5. invalidate any previous usable workflow for that actor
6. generate a cryptographically random six-digit code
7. hash the code only
8. persist workflow with `600`-second code expiry
9. commit
10. send localized email synchronously

Rules:

- unknown email creates no workflow and sends no mail
- inactive email creates no workflow and sends no mail
- on mail transport failure, invalidate only the just-created workflow
- return `503 MAIL_SERVICE_UNAVAILABLE`
- never log the code

### 11.2 Code verification

Route:

```http
POST /api/v1/admin/auth/verify-forgot-password-code
```

Request fields:

- normalized email
- six-digit code

Rules:

- lock the workflow row during success path
- increment failed attempts safely
- maximum failed attempts: `5`
- generic failure contract: `422 PASSWORD_RESET_CODE_INVALID`
- successful verification consumes the code
- at most one winning verification may issue a reset token

Success response data:

```json
{
  "resetToken": "one-time-reset-token",
  "resetTokenExpiresIn": 600
}
```

Rules:

- reset token is returned once only
- reset token is stored only as SHA-256 hash
- reset token must be kept in runtime memory only
- reset token must not be stored in `sessionStorage`, `localStorage`,
  IndexedDB, cookies, URLs, logs, or analytics

### 11.3 Password reset

Route:

```http
POST /api/v1/admin/auth/reset-password
```

Required inputs:

- normalized email
- `resetToken`
- `password`
- `passwordConfirmation`

Rules:

- reset token lifetime: `600` seconds
- successful reset consumes the workflow
- all sessions are revoked
- concurrent reset requests allow only one success

## 12. Localization and response headers

Feature 001 uses Arabic default and English fallback.

Rules:

- `Accept-Language` is resolved before validation
- machine-readable error codes remain English
- public-facing auth messages are localized
- synchronous recovery mail uses the resolved locale

Required response-header behavior for auth endpoints:

- `Content-Language`
- `Vary: Accept-Language`
- `Cache-Control: no-store, private`
- `Pragma: no-cache`

## 13. CORS contract

Approved admin Web origin:

```text
https://admin.frontend-example.com
```

Backend configuration example:

```env
ADMIN_FRONTEND_ORIGIN=https://admin.frontend-example.com
APP_URL=https://api.backend-example.net
```

Required CORS behavior:

- exact allow-list only
- no wildcard origin
- no arbitrary origin reflection
- approved methods include `OPTIONS`
- approved headers include `Authorization`, `Content-Type`, `Accept`,
  `Accept-Language`, and approved correlation headers
- exposed headers include `Content-Language`, approved rate-limit headers, and
  approved correlation headers
- `supports_credentials=false`

Frontend examples:

```text
VITE_API_BASE_URL=https://api.backend-example.net/api/v1
withCredentials=false
credentials: 'omit'
```

## 14. Persistence rules

Approved tables:

- `users`
- `personal_access_tokens`
- `refresh_tokens`
- `password_resets`
- Spatie roles and permissions tables

Refresh-token persistence:

- plain token is never persisted
- `token_hash` uses SHA-256 lookup
- family metadata is preserved
- rotation metadata is preserved
- revocation reason is preserved
- row locking is required for single-winner behavior

Password-reset persistence:

- code is hashed only
- reset token is hashed only
- normalized email is preserved
- attempt count is preserved
- verification and consumption states are preserved

## 15. Required implementation files

Expected backend implementation targets include:

- `app/Actions/Auth/LoginAdminAction.php`
- `app/Actions/Auth/RefreshAdminSessionAction.php`
- `app/Actions/Auth/LogoutAdminAction.php`
- `app/Actions/Auth/UpdateAdminProfileAction.php`
- `app/Actions/Auth/ChangeAdminPasswordAction.php`
- `app/Actions/Auth/SendForgotPasswordCodeAction.php`
- `app/Actions/Auth/VerifyForgotPasswordCodeAction.php`
- `app/Actions/Auth/ResetForgottenPasswordAction.php`
- `app/Services/Auth/AccessTokenService.php`
- `app/Services/Auth/RefreshTokenService.php`
- `app/Services/Auth/AdminSessionRevocationService.php`
- `app/Services/Auth/ForgotPasswordCodeService.php`
- `app/Services/Auth/ResetTokenService.php`
- `app/Support/Auth/AuthenticationSecurityLogger.php`
- `app/Http/Requests/Api/V1/Admin/Auth/*`
- `app/Http/Resources/Api/V1/Admin/Auth/*`
- `app/Http/Controllers/Api/V1/Admin/Auth/*`
- `app/Models/RefreshToken.php`
- `app/Models/PasswordReset.php`
- auth-related enums, migrations, seeders, lang files, routes, config, tests,
  Postman docs, and deployment docs

External React responsibilities remain outside this repository and must be
implemented in the owning React repository after repository inspection.

## 16. Verification inventory

Feature completion requires proof of:

- exact route count: `9` operations and `8` unique paths
- login returns both tokens in JSON
- refresh accepts only JSON `refreshToken`
- refresh returns rotated tokens in JSON
- no authentication cookie is emitted or required
- one active session remains enforced
- reuse revokes all sessions
- inactive admins are blocked on login, refresh, and protected routes
- profile output remains exact
- avatar compensation works
- password change revokes sessions
- forgot-password mail stays synchronous
- mail failure invalidates the new workflow and returns `503`
- code verification and password reset are concurrency-safe
- localized headers and messages remain correct
- exact allow-listed CORS and `supports_credentials=false` are enforced

## 17. Non-negotiable rules

- direct separate-domain frontend-to-backend HTTPS only
- no cookies
- no CSRF
- no proxy/BFF
- Super Admin only
- access token lifetime `900`
- refresh token lifetime `2592000`
- recovery code lifetime `600`
- reset token lifetime `600`
- recovery max failed attempts `5`
- forgot-password resend cooldown `60`
- login throttle `5/min`
- refresh throttle `10/min`
- password change throttle `5/min`
- forgot-password throttle `5/min`
- Web access token in memory only
- Web refresh token in `sessionStorage` only
- future Mobile refresh token in secure storage only
