# Service Commerce Backend — Authentication Standards

> **Scope:** Administrator authentication for the Laravel 13 Service Commerce
> Backend.
>
> **API Area:** `/api/v1/admin/auth/*`
>
> **Status:** Project-wide mandatory standard.

---

## 1. Purpose

This document defines the mandatory authentication rules for the Service
Commerce Backend.

It covers:

- administrator login
- access tokens
- refresh tokens
- single-session enforcement
- token rotation
- logout
- authenticated profile retrieval
- profile update
- password change
- forgot-password code delivery
- forgot-password code verification
- password reset
- password policy
- rate limiting
- inactive-account behaviour
- Super Admin seeding
- localization
- security
- persistence
- concurrency
- testing

This project does not provide customer authentication in the MVP.

Guest customers use public ordering APIs without login.

---

## 2. Related Documents

Authentication implementation MUST comply with:

- `AGENTS.md`
- `.specify/memory/constitution.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- `docs/02-standards/api-standards.md`
- `docs/02-standards/code-standards.md`
- `docs/02-standards/database-standards.md`
- `docs/02-standards/localization-standards.md`
- the active Identity and Authentication feature specification
- the active `plan.md`
- the active `tasks.md`

When documents conflict, implementation MUST stop until the conflict is
resolved.

This standard defines shared authentication behaviour.

The feature API contract defines exact request and response fields where more
detail is required.

---

## 3. Final Authentication Decisions

```text
Authenticated actor: Administrator user only
Public registration: Not supported
Customer authentication: Not supported
Login identifier: Email
Login secret: Password

Access token lifetime: 15 minutes
Access token returned in JSON: Yes
Refresh token lifetime: 30 days
Refresh token transport: HttpOnly Secure cookie
Refresh token returned in JSON: No
Access token browser storage: React memory only
Forgot-password code lifetime: 10 minutes

Session policy: One active administrator session
New login: Revokes all previous access and refresh tokens
Refresh: Rotates access and refresh tokens
Logout: Revokes all access and refresh tokens
Password change: Revokes all access and refresh tokens
Password reset: Revokes all access and refresh tokens

Email verification: Not required in the MVP
Preferred locale storage: Not used
Locale source: Accept-Language
Device name storage: Not used
Last-login metadata: Not stored in the MVP
```

The approved authentication area is:

```text
/api/v1/admin/auth/*
```

No alternate top-level authentication route area is permitted.

---

## 4. Authentication Route Contract

Approved routes:

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

- All routes are versioned.
- All routes return JSON.
- All request and response keys use `camelCase`.
- Login, refresh, and forgot-password routes are public but rate-limited.
- Logout, profile, profile update, and change-password require a valid access
  token.
- No public registration route is allowed.
- No customer login route is allowed.
- No `/me` route is used.
- No `/password` route is used.
- Password change uses `/change-password`.

---

## 5. Authentication Architecture

Approved flow:

```text
Route
  -> Locale middleware
  -> Rate-limit middleware when applicable
  -> Form Request
  -> Controller
  -> Authentication Action
  -> Token Services
  -> API Resource
  -> Global API Response
```

Recommended application components:

```text
app/
  Actions/
    Auth/
      LoginAdminAction.php
      RefreshAdminSessionAction.php
      LogoutAdminAction.php
      ChangeAdminPasswordAction.php
      SendForgotPasswordCodeAction.php
      VerifyForgotPasswordCodeAction.php
      ResetForgottenPasswordAction.php

  Services/
    Auth/
      AccessTokenService.php
      RefreshTokenService.php
      AdminSessionRevocationService.php
      ForgotPasswordCodeService.php

  Http/
    Controllers/
      Api/
        V1/
          Admin/
            Auth/
              LoginController.php
              RefreshTokenController.php
              LogoutController.php
              ProfileController.php
              ChangePasswordController.php
              ForgotPasswordController.php
              VerifyForgotPasswordCodeController.php
              ResetPasswordController.php

    Requests/
      Api/
        V1/
          Admin/
            Auth/
              LoginRequest.php
              RefreshTokenRequest.php
              UpdateProfileRequest.php
              ChangePasswordRequest.php
              ForgotPasswordRequest.php
              VerifyForgotPasswordCodeRequest.php
              ResetPasswordRequest.php

    Resources/
      Api/
        V1/
          Admin/
            Auth/
              AuthenticatedAdminResource.php
              AdminProfileResource.php
```

Names are recommended unless the existing repository already uses an equivalent
clear convention.

---

## 6. Administrator Identity

Authenticated administrator identities are stored in:

```text
users
```

The MVP uses:

```text
users.type = 0
```

for the broad administrator classification.

Rules:

- `users.type` is not sufficient authorization.
- Spatie roles and permissions control capabilities.
- The initial role is `super-admin`.
- Customers are stored separately from users.
- Customer records MUST NOT receive passwords or authentication tokens.
- Only active administrator users may authenticate.
- Email verification is not required in the MVP.
- There is no public administrator registration.

---

## 7. Login

### 7.1 Route

```http
POST /api/v1/admin/auth/login
```

### 7.2 Request

```json
{
  "email": "admin@example.com",
  "password": "secret-password"
}
```

Rules:

- Login uses email and password only.
- Email is normalized before lookup.
- Password is never trimmed or altered.
- `deviceName` is not accepted.
- `rememberMe` is not accepted.
- User ID, role, type, status, permissions, and locale are not accepted.
- Validation uses a dedicated Form Request.

### 7.3 Login Validation

Recommended rules:

```text
email:
- required
- string
- email
- maximum approved length

password:
- required
- string
```

Validation failure returns:

```text
HTTP 422
code: VALIDATION_ERROR
```

### 7.4 Login Rate Limit

Login is limited to:

```text
5 attempts per minute
```

The rate-limit key should combine:

```text
normalized email + requester IP
```

Rules:

- Rate limiting applies before expensive password verification where practical.
- The limiter must not reveal whether the email exists.
- Successful login clears the applicable failed-attempt state.
- Rate-limit configuration should remain centralized.
- A rate-limit failure returns:
  - HTTP `429`
  - code `RATE_LIMITED`
  - localized message
  - `Retry-After` where supported

### 7.5 Credential Failure

Unknown email and incorrect password return the same generic response.

Example code:

```text
INVALID_CREDENTIALS
```

The response MUST NOT reveal whether the email exists.

### 7.6 Inactive Account

An existing administrator with inactive status receives a specific localized
message.

Arabic:

```text
هذا الحساب غير نشط.
```

English:

```text
This account is inactive.
```

Recommended machine code:

```text
USER_INACTIVE
```

Recommended status:

```text
403 Forbidden
```

The response may reveal inactive state because this is an approved product
decision.

### 7.7 Single-Session Login Rule

Before issuing new tokens, a successful login MUST revoke:

- all existing Sanctum access tokens for the user
- all active refresh tokens for the user

Then the backend issues:

- one access token
- one refresh token

This guarantees one active administrator session.

The revocation and token issuance workflow must be transaction-safe.

### 7.8 Login Success Response

Successful login returns the access token in JSON and sets the refresh token in
an HttpOnly cookie.

Example body:

```json
{
  "success": true,
  "message": "تم تسجيل الدخول بنجاح.",
  "data": {
    "accessToken": "plain-access-token-returned-once",
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

The response also sets:

```text
admin_refresh_token
admin_csrf_token
```

Rules:

- `tokenExpiresIn` is expressed in seconds.
- `refreshTokenExpiresIn` is expressed in seconds.
- The access token is returned in JSON.
- The refresh token is never returned in JSON.
- The refresh token is delivered through an HttpOnly Secure cookie.
- The access token is stored by React in memory only.
- Plain tokens are never stored in application tables.
- The API MUST NOT return password hashes.
- The API MUST NOT return raw token hashes.
- Permissions may be returned according to the approved authorization
  contract.

---

## 8. Access Tokens

### 8.1 Technology

Access tokens use Laravel Sanctum personal access tokens.

### 8.2 Lifetime

Access-token lifetime:

```text
15 minutes
900 seconds
```

The API returns:

```json
{
  "tokenExpiresIn": 900
}
```

### 8.3 Token Abilities

Access tokens should have a narrowly defined ability such as:

```text
admin:access
```

Abilities do not replace:

- Spatie roles
- Spatie permissions
- Policies
- resource ownership checks
- workflow validation

### 8.4 Storage

Sanctum stores only the token hash.

Application code MUST NOT store the plain access token.

### 8.5 Protected Requests

Clients send:

```http
Authorization: Bearer {accessToken}
```

Protected routes require:

```text
auth:sanctum
```

and the approved active-administrator check.

### 8.6 Expired Access Token

An expired access token returns:

```text
HTTP 401
code: UNAUTHENTICATED
```

The React dashboard may call the refresh endpoint and retry the original request
according to its own frontend authentication flow.

---

## 9. Refresh Tokens

### 9.1 Purpose

Refresh tokens allow the dashboard to obtain a new access token without
resubmitting the administrator password.

### 9.2 Lifetime

Refresh-token lifetime:

```text
30 days
2592000 seconds
```

The API returns:

```json
{
  "refreshTokenExpiresIn": 2592000
}
```

After the refresh token expires, the administrator must log in again.

### 9.3 Storage Strategy

Refresh tokens use an application-managed table.

Approved table:

```text
refresh_tokens
--------------
id
tokenable_type
tokenable_id
token_hash
family_id
expires_at
revoked_at nullable
rotated_to_token_id nullable
revocation_reason nullable
created_at
updated_at
```

The owner is polymorphic. The current owner is the administrator `User`; no
other authenticated account type is part of the MVP.

Do not store:

- plain refresh token
- password
- access token
- device name
- full user agent unless separately approved

### 9.4 Refresh Token Generation

Generate refresh tokens using a cryptographically secure random source.

Recommended conceptual approach:

```text
random 64 bytes
-> URL-safe encoded token
-> SHA-256 hash for lookup and storage
```

The plain refresh token is set only in the approved HttpOnly cookie.

It is never returned in JSON.

Only its hash is persisted.

### 9.5 Rotation

Every successful refresh MUST rotate the refresh token.

Rotation means:

1. receive the plain refresh token
2. hash it
3. find and lock the stored token
4. verify it is active and unexpired
5. verify the user is active
6. revoke the old refresh token
7. revoke all existing access tokens for the user
8. issue one new access token
9. issue one new refresh token
10. link the old token to the new token where rotation metadata is stored
11. commit
12. set the rotated refresh token in the HttpOnly cookie
13. return the new access token in JSON

The previous refresh token becomes unusable immediately.

### 9.6 Refresh Route

```http
POST /api/v1/admin/auth/refresh
```

The request body does not contain the refresh token.

The browser sends:

```text
admin_refresh_token
```

through the HttpOnly cookie and sends the approved CSRF token through:

```http
X-CSRF-TOKEN: {csrfToken}
```

Success:

```json
{
  "success": true,
  "message": "تم تجديد جلسة الدخول بنجاح.",
  "data": {
    "accessToken": "new-access-token",
    "tokenType": "Bearer",
    "tokenExpiresIn": 900,
    "refreshTokenExpiresIn": 2592000
  }
}
```

The response rotates the refresh cookie.

Rules:

- The refresh token is never accepted from JSON.
- The refresh token is never returned in JSON.
- Exact Origin validation is required.
- CSRF cookie/header validation is required.

### 9.7 Refresh Failure

The following conditions return one generic refresh failure:

- token not found
- malformed token
- expired token
- revoked token
- rotated token reuse
- token belongs to inactive user
- token hash mismatch
- missing related user

Recommended code:

```text
REFRESH_TOKEN_INVALID
```

Recommended status:

```text
401 Unauthorized
```

Do not expose which condition occurred.

### 9.8 Reuse Detection

Presenting an already rotated or revoked refresh token is suspicious.

When reuse is detected, the backend SHOULD:

- revoke all access tokens for the user when the user can be identified safely
- revoke all refresh tokens for the user
- return a generic refresh failure
- log a safe security event
- avoid logging the raw token

---

## 10. One Active Session Rule

The project supports one active session per administrator.

A session consists of:

- one current access token
- one current refresh-token chain

The following actions revoke all sessions before issuing or completing the new
state:

- successful login
- password change
- forgot-password reset

The following action revokes all sessions without issuing a replacement:

- logout

Refresh rotates the current session and leaves one active token pair.

Rules:

- Multiple device sessions are prohibited.
- A new login invalidates existing browsers and devices.
- Device names are not stored.
- A `logout-all` endpoint is unnecessary because ordinary logout already logs
  out all sessions.

---

## 11. Logout

### 11.1 Route

```http
POST /api/v1/admin/auth/logout
```

### 11.2 Authentication

Logout requires a valid access token.

### 11.3 Behaviour

Logout revokes:

- all Sanctum access tokens for the user
- all refresh tokens for the user

Logout also clears:

- `admin_refresh_token`
- `admin_csrf_token`

It does not revoke only the current token.

### 11.4 Response

Example:

```json
{
  "success": true,
  "message": "تم تسجيل الخروج من جميع الجلسات بنجاح.",
  "data": null
}
```

Rules:

- Logout must be idempotent at the business level where practical.
- A successful response must not claim anything beyond token revocation.
- No `logout-all` route is required.
- Refresh tokens must become unusable immediately after logout.

---

## 12. Profile Retrieval

### 12.1 Route

```http
GET /api/v1/admin/auth/profile
```

### 12.2 Authentication

Requires a valid access token.

### 12.3 Response

Example:

```json
{
  "success": true,
  "message": "تم جلب الملف الشخصي بنجاح.",
  "data": {
    "name": "Super Admin",
    "email": "admin@example.com",
    "avatar": null,
    "role": "super-admin",
    "permissions": []
  }
}
```

Rules:

- Email may be returned but cannot be updated through the profile endpoint.
- Profile fields are exactly `name`, `email`, `avatar`, `role`, and
  `permissions`.
- A user ID is not returned.
- `role` is one string, not an array.
- Profile output uses an explicit API Resource.
- Password hashes and token metadata are never exposed.
- Internal storage paths are never exposed.
- Roles and permissions are technical identifiers and are not translated.

---

## 13. Profile Update

### 13.1 Route

```http
PATCH /api/v1/admin/auth/profile
```

### 13.2 Allowed Fields

The administrator may update only:

```text
name
avatar
```

The endpoint MUST NOT accept:

```text
email
password
type
status
roles
permissions
preferredLocale
lastLoginAt
lastLoginIp
deviceName
```

### 13.3 Name Validation

Recommended rules:

```text
name:
- sometimes
- required when present
- string
- trimmed
- maximum 150 characters
```

### 13.4 Avatar Validation

Exact avatar limits belong in the file-storage standard.

The endpoint must validate:

- successful upload
- approved image MIME type
- approved extension
- maximum size
- generated storage name

Rules:

- Avatar is optional.
- Existing avatar replacement must preserve file/database consistency.
- Raw storage paths are not returned.
- Deleting or clearing an avatar requires an explicitly documented request
  contract.
- Profile update does not modify tokens.

### 13.5 Response

Return the updated profile Resource.

---

## 14. Authenticated Password Change

### 14.1 Route

```http
PUT /api/v1/admin/auth/change-password
```

### 14.2 Request

```json
{
  "currentPassword": "current-secret",
  "password": "new-secret",
  "passwordConfirmation": "new-secret"
}
```

The exact confirmation field may use Laravel's `confirmed` convention if the
API contract defines:

```text
password_confirmation
```

However, the public API standard uses `camelCase`.

The feature contract MUST choose one consistent field mapping.

Recommended API fields:

```text
currentPassword
password
passwordConfirmation
```

### 14.3 Validation

The endpoint must verify:

- current password is correct
- new password satisfies the password policy
- confirmation matches
- new password differs from the current password

### 14.4 Behaviour

On success:

1. lock or otherwise safely resolve the user
2. verify current password
3. hash and save the new password
4. revoke all access tokens
5. revoke all refresh tokens
6. commit
7. return success

The current request token is revoked as part of the operation.

The administrator must log in again.

### 14.5 Response

Example:

```json
{
  "success": true,
  "message": "تم تغيير كلمة المرور. يرجى تسجيل الدخول مرة أخرى.",
  "data": null
}
```

### 14.6 Current Password Failure

Recommended code:

```text
CURRENT_PASSWORD_INVALID
```

Recommended status:

```text
422 Unprocessable Content
```

The error may be attached to:

```text
currentPassword
```

---

## 15. Password Policy

Approved policy:

```text
minimum length: 10 characters
at least one uppercase letter
at least one lowercase letter
at least one number
at least one symbol
confirmation required
```

Recommended Laravel rule:

```php
Password::min(10)
    ->mixedCase()
    ->numbers()
    ->symbols()
```

Rules:

- Do not use `uncompromised()` in the MVP.
- Password policy applies to:
  - initial Super Admin secret validation
  - authenticated password change
  - forgot-password reset
  - future administrator creation when that Feature is introduced
- Passwords are hashed using Laravel's configured hasher.
- Passwords are never logged.
- Passwords never cross an asynchronous boundary.
- Passwords are never written to documentation or committed source.
- Avoid arbitrary maximum lengths that prevent secure password-manager output.
- Apply a safe technical maximum when required for request-size protection.

---

## 16. Forgot Password Overview

Forgot password follows:

```text
Email
-> Send six-digit code
-> Verify code
-> Receive one-time reset token
-> Reset password
```

Approved endpoints:

```http
POST /api/v1/admin/auth/forgot-password
POST /api/v1/admin/auth/verify-forgot-password-code
POST /api/v1/admin/auth/reset-password
```

Only administrator users participate.

Customer records do not use this flow.

---

## 17. Send Forgot-Password Code

### 17.1 Route

```http
POST /api/v1/admin/auth/forgot-password
```

### 17.2 Request

```json
{
  "email": "admin@example.com"
}
```

### 17.3 Security Response

The public response SHOULD remain generic whether the email exists or not.

Example:

```json
{
  "success": true,
  "message": "إذا كان البريد الإلكتروني مسجلاً، فسيتم إرسال رمز الاستعادة.",
  "data": null
}
```

English:

```text
If the email address is registered, a recovery code will be sent.
```

This prevents account enumeration.

### 17.4 Code Requirements

The code is:

```text
six numeric digits
```

Use a cryptographically secure generator such as:

```php
random_int(100000, 999999)
```

Rules:

- Code lifetime is 10 minutes.
- Code is single-use.
- Store only a secure hash of the code.
- Do not store the plain code.
- Email the plain code only through the Mail flow.
- A new code invalidates previous active codes for the email.
- The code is not logged.
- The code is not returned by the API.
- The code must not be placed in error logs.

### 17.5 Delivery

Code email is sent synchronously after the reset workflow transaction commits.
No authentication Queue or Job is used. A delivery failure follows the
Feature 001 error contract and must not leave an active unusable workflow.

### 17.6 Rate Limiting

Forgot-password requests require rate limiting.

Recommended baseline:

```text
5 requests per minute per normalized email and IP
```

A stricter resend cooldown may be defined by the feature contract.

Recommended resend cooldown:

```text
60 seconds
```

Do not reveal whether rate limiting applies to an existing account only.

---

## 18. Verify Forgot-Password Code

### 18.1 Route

```http
POST /api/v1/admin/auth/verify-forgot-password-code
```

### 18.2 Request

```json
{
  "email": "admin@example.com",
  "code": "123456"
}
```

### 18.3 Verification Requirements

The backend verifies:

- normalized email
- active reset record
- code hash
- code expiry
- code has not already been used
- maximum verification attempts
- associated user exists
- associated user is an administrator

Recommended maximum failed verification attempts:

```text
5
```

After exceeding the limit:

- invalidate the code
- require requesting a new code

### 18.4 Successful Verification

On successful verification:

1. mark the code as verified or consumed
2. generate a cryptographically secure one-time reset token
3. store only its hash
4. set reset-token expiry
5. return the plain reset token once

Recommended reset-token lifetime:

```text
10 minutes
```

Example response:

```json
{
  "success": true,
  "message": "تم التحقق من رمز الاستعادة بنجاح.",
  "data": {
    "resetToken": "plain-one-time-reset-token",
    "resetTokenExpiresIn": 600
  }
}
```

### 18.5 Verification Failure

All expected failures use a generic response.

Recommended code:

```text
PASSWORD_RESET_CODE_INVALID
```

Recommended status:

```text
422 Unprocessable Content
```

Do not distinguish publicly between:

- wrong code
- expired code
- consumed code
- too many attempts
- unknown email

---

## 19. Reset Forgotten Password

### 19.1 Route

```http
POST /api/v1/admin/auth/reset-password
```

### 19.2 Request

```json
{
  "email": "admin@example.com",
  "resetToken": "plain-one-time-reset-token",
  "password": "new-secret",
  "passwordConfirmation": "new-secret"
}
```

### 19.3 Validation

The backend validates:

- email format
- reset token presence
- password policy
- password confirmation
- active reset-token record
- token hash
- token expiry
- token has not been consumed
- administrator user exists

### 19.4 Behaviour

On success:

1. lock the reset record
2. lock or safely resolve the user
3. verify the reset token
4. hash and save the new password
5. mark the reset token consumed
6. invalidate outstanding password-reset records for the user
7. revoke all Sanctum access tokens
8. revoke all refresh tokens
9. commit
10. return success

The administrator must log in again.

### 19.5 Response

Example:

```json
{
  "success": true,
  "message": "تم تغيير كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.",
  "data": null
}
```

### 19.6 Failure

Recommended code:

```text
PASSWORD_RESET_TOKEN_INVALID
```

Recommended status:

```text
422 Unprocessable Content
```

Do not reveal the specific token failure reason.

---

## 20. Password Reset Persistence

Approved table:

```text
password_resets
---------------
id
resettable_type
resettable_id
email_normalized
code_hash
code_expires_at
verification_attempts
verified_at nullable
reset_token_hash nullable
reset_token_expires_at nullable
consumed_at nullable
created_at
updated_at
```

Rules:

- Store normalized email.
- Store only hashes of codes and reset tokens.
- Use a polymorphic owner; the current owner is the administrator `User`.
- Do not create a reset record for an unknown or inactive account.
- Use indexes for:
  - `resettable_type, resettable_id`
  - `email_normalized`
  - `code_expires_at`
  - `reset_token_expires_at`
- Eligible records are cleaned opportunistically during authentication
  requests.
- Sensitive hashes are never returned.
- One active reset workflow per administrator is sufficient.
- A new forgot-password request invalidates previous workflows.

---

## 21. Refresh Token Persistence

Approved table:

```text
refresh_tokens
--------------
id
tokenable_type
tokenable_id
token_hash
family_id
expires_at
revoked_at nullable
rotated_to_token_id nullable
revocation_reason nullable
created_at
updated_at
```

Recommended indexes:

```text
index(tokenable_type, tokenable_id)
unique(token_hash)
index(expires_at)
index(revoked_at)
index(family_id)
```

The owner is polymorphic. The current owner is the administrator `User`; no
customer authentication is implemented.

Rules:

- Token hash must be unique.
- Plain token is never persisted.
- Eligible expired and revoked records are cleaned opportunistically during
  authentication requests.
- Revoked records may be retained briefly for reuse detection.
- Cleanup policy must not remove records needed for active security
  investigation prematurely.
- Refresh-token records are operational security data, not business history.

---

## 22. Token Revocation Reasons

When persisted, recommended stable values include:

```text
login_replaced
refreshed
logout
password_changed
password_reset
user_deactivated
refresh_reuse_detected
expired_cleanup
```

Use a PHP backed enum.

Do not use native MySQL `ENUM`.

Do not expose internal revocation reasons through normal public API responses.

---

## 23. Transactions and Concurrency

### 23.1 Login

Successful login should run in a transaction that:

- locks the administrator user where justified
- revokes current refresh tokens
- deletes current access tokens
- creates one refresh-token record
- creates one Sanctum access token

Filesystem or mail work is not part of login.

### 23.2 Refresh

Refresh MUST use a transaction and row lock.

Lock:

- refresh-token row
- user row where required

This prevents:

- two simultaneous refreshes succeeding with the same token
- multiple active sessions
- duplicate rotation
- stale access tokens remaining valid

### 23.3 Logout

Logout token revocation should be atomic at the database level.

### 23.4 Password Change and Reset

Password update and token revocation belong in one transaction.

### 23.5 Forgot-Password Verification

Code verification and reset-token creation must be transaction-safe to prevent
the same code from producing multiple valid reset tokens.

### 23.6 External Email

Do not send email inside an open transaction.

Persist reset state, commit, then send the recovery email synchronously. Do not
dispatch an authentication Queue Job.

---

## 24. Active User Enforcement

Active-user enforcement applies at:

- login
- refresh
- every protected administration request

A user deactivated after login must lose access.

Recommended middleware:

```text
EnsureAdminIsActive
```

The middleware checks:

- authenticated user exists
- user is the approved administrator type
- user status is active

When inactive:

- revoke tokens where appropriate
- return `403`
- code `USER_INACTIVE`
- localized message

Do not rely only on login-time validation.

---

## 25. Email Verification

Email verification is not required in the MVP.

Rules:

- `email_verified_at` may remain nullable.
- Login does not require a verified timestamp.
- Forgot password uses the account email even when email verification is not
  enabled.
- Do not introduce:
  - verification-email endpoint
  - resend-verification endpoint
  - verification middleware
  - verification notification
- A future feature may add email verification only through an approved product
  and architecture change.

---

## 26. Public Registration and User Management

The MVP has:

```text
no public registration
no administrator user-management feature
```

Rules:

- Do not create `/register`.
- Do not create public role assignment.
- Do not create administration CRUD for users during the initial Identity and
  Authentication Feature.
- Do not expose role or permission mutation through profile APIs.
- The initial Super Admin is created through a Seeder.

Future administrator management requires a dedicated approved Feature.

---

## 27. Super Admin Seeder

### 27.1 Purpose

The initial Super Admin is provisioned through a Seeder.

### 27.2 Configuration

The Seeder reads environment-backed configuration.

Example `.env.example` keys:

```env
SUPER_ADMIN_NAME=Super Admin
SUPER_ADMIN_EMAIL=admin@resolution.com
SUPER_ADMIN_PASSWORD=
```

Rules:

- The real password value MUST NOT appear in:
  - Seeder source
  - `.env.example`
  - committed configuration
  - standards
  - documentation
  - logs
  - test snapshots
- Production supplies the password securely through environment configuration.
- The Seeder hashes the password using Laravel's configured hasher.
- The Seeder validates the configured password against the approved password
  policy.
- The Seeder assigns:
  - `type = 0`
  - active status
  - `super-admin` role
- Role and required permissions are created idempotently.
- The Seeder should use deterministic lookup such as normalized email.
- Re-running the Seeder must not create duplicate Super Admin users.
- The Seeder must not silently replace an existing production password unless
  explicitly designed and approved.
- Missing production password configuration should fail safely with a clear
  console error.

### 27.3 Recommended Idempotency

A safe direction:

```text
find by normalized email
-> create only when missing
-> ensure role assignment
-> do not overwrite password on every seed
```

Password rotation is performed through the authenticated or forgot-password
flow, not by repeatedly running the Seeder.

---

## 28. Profile Locale

The administrator preferred locale is not stored.

The backend resolves language from:

```http
Accept-Language
```

Supported values:

```text
ar
en
```

Rules:

- Do not add `preferred_locale` to `users` in the MVP.
- Profile update does not accept locale.
- Token payload does not require locale.
- Synchronous forgot-password email uses the locale resolved for the request.
- API messages follow the current request locale.

---

## 29. Login Metadata and Device Data

The MVP does not persist:

```text
last_login_at
last_login_ip
device_name
device_fingerprint
session_name
```

Rules:

- Do not add these columns speculatively.
- Do not require `deviceName` in login.
- Security logs may include safe request context according to the logging
  standard.
- A later session-management or security-monitoring Feature may introduce
  device metadata through an approved specification.

---

## 30. Localization

Supported locales:

```text
ar
en
```

Arabic is the initial default.

English is the fallback.

Localization applies to:

- login success
- logout success
- refresh success
- profile messages
- password-change messages
- forgot-password messages
- validation errors
- inactive-account errors
- authentication errors
- rate-limit errors
- forgot-password email subject and body

The following remain untranslated:

- access token
- refresh token
- token type
- `tokenExpiresIn`
- `refreshTokenExpiresIn`
- IDs
- email values
- route paths
- request and response keys
- role and permission identifiers
- error codes
- enum values

### 30.1 Translation Keys

Recommended keys:

```text
auth.login_success
auth.logout_success
auth.refresh_success
auth.profile_retrieved
auth.profile_updated
auth.password_changed
auth.invalid_credentials
auth.user_inactive
auth.unauthenticated
auth.refresh_token_invalid
auth.forgot_password_accepted
auth.reset_code_verified
auth.password_reset_success
auth.password_reset_code_invalid
auth.password_reset_token_invalid
auth.current_password_invalid
auth.rate_limited
```

### 30.2 Forgot-Password Email Locale

The send-code workflow uses the locale resolved from the current request while
building and synchronously sending the Mailable.

The numeric code itself is not translated.

---

## 31. API Error Codes

Recommended stable codes:

```text
VALIDATION_ERROR
INVALID_CREDENTIALS
USER_INACTIVE
UNAUTHENTICATED
FORBIDDEN
RATE_LIMITED
REFRESH_TOKEN_INVALID
CURRENT_PASSWORD_INVALID
PASSWORD_RESET_CODE_INVALID
PASSWORD_RESET_TOKEN_INVALID
INTERNAL_ERROR
```

Rules:

- Codes remain English.
- Codes remain stable across Arabic and English.
- Codes are documented in the feature API contract.
- Controllers do not invent codes inline.
- Localized messages may change without changing machine codes.

---

## 32. Security Standards

- Hash passwords using Laravel's configured secure hasher.
- Store only hashed access and refresh tokens.
- Store forgot-password codes and reset tokens only as hashes.
- Generate refresh and reset tokens cryptographically.
- Use generic credential and refresh failures.
- Rate-limit login and forgot-password endpoints.
- Enforce one active session.
- Rotate refresh tokens.
- Detect refresh-token reuse.
- Revoke tokens after password change and reset.
- Revoke tokens for inactive users.
- Never log:
  - passwords
  - access tokens
  - refresh tokens
  - forgot-password codes
  - reset tokens
  - authorization headers
  - token hashes unless strictly necessary and safely redacted
- Never expose:
  - token database IDs
  - token hashes
  - reset-record IDs
  - internal revocation reasons
  - stack traces
  - SQL
- Use constant-time hash comparison through approved cryptographic functions.
- Do not compare plaintext secret values directly.
- Do not place secret values in error messages or logs.
- Do not use predictable token values.
- Do not store tokens in URL query parameters.
- Do not accept tokens through request headers other than the approved Bearer
  access-token header.
- The refresh token is accepted only from the approved HttpOnly cookie.
- The password-reset token is accepted in the approved JSON request body over
  HTTPS.

---

## 33. Logging and Observability

Authentication logs MAY include safe fields such as:

```text
request_id
route
http_status
user_id when known
normalized-email hash or redacted email when approved
operation
failure category
ip_address when operationally approved
```

Logs MUST NOT include:

```text
password
access token
refresh token
forgot-password code
reset token
authorization header
token hash
full sensitive request body
```

Recommended security events:

```text
admin_login_succeeded
admin_login_failed
admin_login_rate_limited
admin_refresh_succeeded
admin_refresh_failed
admin_refresh_reuse_detected
admin_logout
admin_password_changed
admin_password_reset_requested
admin_password_reset_code_verified
admin_password_reset_completed
inactive_admin_access_blocked
```

Logging must not become a substitute for a future audit-history feature.

---

## 34. Opportunistic Cleanup and Retention

Authentication requests opportunistically remove eligible:

- expired password-reset records
- consumed password-reset records after the approved retention window
- expired refresh tokens
- old revoked refresh tokens after the reuse-detection window
- expired Sanctum tokens according to the approved cleanup approach

Rules:

- Cleanup is idempotent.
- Cleanup does not delete active records.
- Cleanup uses indexed expiry fields.
- Cleanup does not log raw secret material.
- Cleanup is scoped to the resolved administrator or email where practical.
- Do not create an authentication cleanup Command, Queue Job, Cron task, or
  scheduler entry.

---

## 35. Testing Requirements

Authentication implementation requires automated Pest tests.

### 35.1 Login Tests

- valid email and password
- email normalization
- invalid email
- invalid password
- unknown and incorrect credentials use the same generic response
- inactive user receives `USER_INACTIVE`
- non-admin user cannot authenticate
- login rate limit is 5 attempts per minute
- successful login clears limiter state
- successful login returns an access token
- successful login sets an HttpOnly refresh cookie
- successful login does not return the refresh token in JSON
- `tokenExpiresIn` equals `900`
- `refreshTokenExpiresIn` equals `2592000`
- successful login revokes previous access tokens
- successful login revokes previous refresh tokens
- only one active session remains
- no device name is required
- email verification is not required

### 35.2 Access Token Tests

- valid access token accesses protected route
- expired access token returns `401`
- revoked access token returns `401`
- inactive user is blocked after login
- access token has approved ability
- ability does not bypass permissions
- profile response does not expose secrets

### 35.3 Refresh Tests

- valid refresh cookie returns a new access token
- successful refresh rotates the HttpOnly refresh cookie
- old refresh token is revoked
- old access token is revoked
- only one access token remains active
- only one current refresh token remains active
- expired refresh token fails
- malformed refresh token fails
- revoked refresh token fails
- rotated token reuse fails
- user deactivation causes refresh failure
- concurrent refresh attempts allow only one success
- refresh failures remain generic
- raw token is not persisted

### 35.4 Logout Tests

- authenticated logout succeeds
- logout revokes every access token
- logout revokes every refresh token
- refresh fails after logout
- previous access tokens fail after logout
- no separate logout-all endpoint is required

### 35.5 Profile Tests

- authenticated profile retrieval
- profile output fields
- name update
- avatar upload
- invalid avatar type
- oversized avatar
- raw avatar path is not exposed
- email cannot be updated
- password cannot be updated through profile
- type cannot be updated
- status cannot be updated
- roles and permissions cannot be updated
- locale cannot be stored through profile

### 35.6 Password Change Tests

- valid current password changes password
- invalid current password fails
- weak password fails
- password confirmation mismatch fails
- new password equal to current password fails
- all access tokens are revoked
- all refresh tokens are revoked
- old password no longer works
- new password works
- current request token is revoked

### 35.7 Forgot-Password Request Tests

- valid administrator email
- unknown email returns same accepted response
- inactive administrator behaviour follows approved generic flow
- code is six digits
- code is generated securely
- code hash is stored
- plain code is not stored
- code expires in 10 minutes
- previous code is invalidated
- recovery email is sent synchronously after persistence commits
- no authentication Queue Job is dispatched
- response does not claim delivery
- rate limiting works
- resend cooldown works
- Arabic email locale
- English email locale

### 35.8 Code Verification Tests

- valid code verifies
- invalid code fails
- expired code fails
- consumed code fails
- unknown email fails generically
- maximum five failed attempts
- code invalidates after attempt limit
- successful verification returns one-time reset token
- reset token hash is stored
- plain reset token is not stored
- reset token expires in 10 minutes
- concurrent verification permits one valid reset token

### 35.9 Password Reset Tests

- valid reset token changes password
- invalid reset token fails
- expired reset token fails
- consumed reset token fails
- weak password fails
- confirmation mismatch fails
- reset token is single-use
- outstanding reset records are invalidated
- all access tokens are revoked
- all refresh tokens are revoked
- user can log in with new password
- old password fails

### 35.10 Localization Tests

- Arabic messages
- English messages
- error codes remain unchanged
- validation keys remain `camelCase`
- `Accept-Language` selects locale
- unsupported locale follows fallback
- synchronous forgot-password mail uses the resolved request locale
- tokens and identifiers are not translated

### 35.11 Seeder Tests

- Seeder creates one Super Admin
- Seeder is idempotent
- Super Admin email comes from configuration
- password comes from secret configuration
- password is hashed
- role is assigned
- type is zero
- user is active
- Seeder does not overwrite existing password on rerun
- missing password configuration fails safely

Use a real MySQL test environment for:

- row locks
- concurrent refresh
- unique token hashes
- transaction behaviour

---

## 36. API Review Checklist

- [ ] Route begins with `/api/v1/admin/auth/`.
- [ ] No alternate top-level authentication route area was introduced.
- [ ] Request keys use `camelCase`.
- [ ] Mutation uses a dedicated Form Request.
- [ ] Access token lifetime is 15 minutes.
- [ ] Refresh token lifetime is 30 days.
- [ ] `tokenExpiresIn` equals `900`.
- [ ] `refreshTokenExpiresIn` equals `2592000`.
- [ ] Successful login revokes all previous sessions.
- [ ] Access token is returned in JSON and stored in React memory only.
- [ ] Refresh token is set in an HttpOnly cookie.
- [ ] Refresh token is not returned in JSON.
- [ ] Refresh endpoint validates CSRF token and Origin.
- [ ] Refresh rotates both tokens.
- [ ] Logout revokes all access and refresh tokens.
- [ ] Password change revokes all tokens.
- [ ] Password reset revokes all tokens.
- [ ] Profile update allows only name and avatar.
- [ ] Email verification is not required.
- [ ] No public registration exists.
- [ ] No customer authentication exists.
- [ ] Rate limiting is applied.
- [ ] Inactive-user response is localized and explicit.
- [ ] Raw tokens, codes, and passwords are never logged.
- [ ] Secret values are never stored in plaintext.
- [ ] Arabic and English messages exist.
- [ ] Pest tests cover success, failure, rotation, concurrency, and revocation.
- [ ] OpenAPI or the active feature API contract is updated.

---

## 37. Definition of Done

Authentication work is complete only when:

- approved routes are implemented
- login uses email and password
- single-session behaviour is enforced
- access tokens expire after 15 minutes
- refresh tokens expire after 30 days
- refresh rotation is implemented
- refresh reuse is rejected
- logout revokes all tokens
- profile retrieval is implemented
- profile update is limited to name and avatar
- password change is separate and revokes all tokens
- forgot-password code delivery is implemented
- code verification is implemented
- one-time reset token is implemented
- password reset revokes all tokens
- password policy is enforced
- inactive users are blocked
- login rate limiting is implemented
- Arabic and English messages exist
- secret configuration is not committed
- tests pass
- documentation is synchronized
- no known critical authentication vulnerability remains

---

## 38. Non-Negotiable Rules

- Authentication routes use `/api/v1/admin/auth/*`.
- Login uses email and password only.
- No public registration.
- No customer authentication.
- No email verification in the MVP.
- One active administrator session only.
- New login revokes all previous sessions.
- Access token expires after 15 minutes.
- Refresh token expires after 30 days.
- Every refresh rotates both tokens.
- Logout revokes every access and refresh token.
- Password change revokes every access and refresh token.
- Password reset revokes every access and refresh token.
- Profile update changes only name and avatar.
- Locale comes from `Accept-Language`.
- No preferred-locale database field.
- No device-name field.
- No last-login metadata in the MVP.
- No plaintext access token storage.
- No plaintext refresh token storage.
- No refresh token in JSON responses.
- No access token in persistent browser storage.
- Cookie-based refresh requires CSRF protection.
- No plaintext forgot-password code storage.
- No plaintext reset-token storage.
- No tokens in URLs.
- No secrets in logs.
- No committed production password.
- No Seeder password hard-coded in source.
- No raw token hash in API responses.
- No user enumeration through forgot-password responses.
- No refresh-token reuse.
- No multiple active sessions.
- Password-recovery delivery follows the synchronous Feature 001 contract.
