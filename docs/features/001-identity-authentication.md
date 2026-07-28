# Feature 001 — Identity and Authentication

> **Project:** Service Commerce Backend
>
> **Feature ID:** `001`
>
> **Feature Name:** Identity and Authentication
>
> **Target Framework:** Laravel 13
>
> **Authentication:** Laravel Sanctum access tokens with a custom refresh-token
> mechanism
>
> **Authorization:** `spatie/laravel-permission`
>
> **Primary Actor:** Administrator
>
> **Initial Role:** `super-admin`
>
> **Status:** Approved implementation reference
>
> **Intended Use:** Authoritative reference for planning, implementation,
> `/speckit.specify`, Codex, code review, Postman documentation, and acceptance
> testing.

---

## 1. Feature Summary

This Feature implements administrator identity and authentication for the
Service Commerce Backend.

It provides:

- initial Super Admin provisioning
- administrator login
- short-lived access tokens
- long-lived rotating refresh tokens
- one active administrator session
- secure refresh cookies
- CSRF protection for refresh
- logout
- profile retrieval
- profile update
- avatar replacement
- authenticated password change
- forgot-password code delivery
- forgot-password code verification
- one-time reset tokens
- password reset
- inactive-account enforcement
- authentication localization
- security logging
- opportunistic cleanup
- Postman documentation
- automated API tests

This Feature does not provide public registration or customer authentication.

---

## 2. Business Goal

The administration dashboard requires a secure, predictable, API-first
authentication flow.

The Feature must allow the initial Super Admin to:

1. log in using email and password
2. receive a short-lived access token
3. continue the session through a rotating refresh cookie
4. retrieve the authenticated profile
5. update the profile name and avatar
6. change the password
7. log out from all sessions
8. recover access through a six-digit email code

The Feature must prevent:

- multiple active administrator sessions
- persistent access-token storage in the browser
- refresh-token access from JavaScript
- refresh-token reuse
- account enumeration through forgot password
- inactive-account access
- role or permission mutation through profile APIs
- plaintext password, token, or reset-code persistence

---

## 3. Authoritative Decisions

```text
Administrator registration: Not supported
Customer authentication: Not supported
User management: Not supported
Role management: Not supported
Permission management: Not supported
MFA: Not supported
MFA scaffolding: Not required
Email verification: Not required
Remember me: Not supported
Device management: Not supported
Session list: Not supported
Login history: Not stored
```

Token decisions:

```text
Access token lifetime: 15 minutes
Access token returned in JSON: Yes
Refresh token lifetime: 30 days
Access token browser storage: React memory only
Refresh token browser storage: HttpOnly Secure host-only cookie
Refresh token returned in JSON: No
One active administrator session: Yes
Refresh-token rotation: Mandatory
Refresh-token reuse detection: Mandatory
```

Password recovery decisions:

```text
Recovery code: Six numeric digits
Recovery code lifetime: 10 minutes
Verification attempts: 5
Resend cooldown: 60 seconds
Reset-token lifetime: 10 minutes
Mail delivery: Synchronous
Queue or Job: Not used
Cron or scheduled cleanup: Not used
Cleanup command: Not used
Cleanup approach: Opportunistic during authentication requests
```

Profile decisions:

```text
Response fields: name, email, avatar, role, permissions
User ID in auth/profile responses: No
Role response type: String
Permissions response type: Array
Avatar deletion without replacement: Not supported
Avatar absent or empty: Keep current avatar
Avatar uploaded as file: Replace current avatar
```

---

## 4. Related Standards

Implementation must comply with:

```text
AGENTS.md
docs/00-project-overview/project-overview.md
docs/01-architecture/backend-architecture.md
docs/02-standards/api-standards.md
docs/02-standards/code-standards.md
docs/02-standards/database-standards.md
docs/02-standards/localization-standards.md
docs/02-standards/authentication-standards.md
docs/02-standards/authorization-standards.md
docs/02-standards/file-storage-standards.md
docs/02-standards/testing-standards.md
docs/02-standards/security-standards.md
```

Where a higher-level governing document intentionally leaves a detail
configurable, this Feature's more specific approved business decision controls
Feature 001. This Feature MUST NOT weaken, bypass, or contradict the
constitution, backend architecture, or shared cross-cutting standards. Any
genuine exception requires the governing files to be approved and amended
before planning or implementation.

---

## 5. Actor

### 5.1 Administrator

The only authenticated actor in this Feature is an administrator stored in:

```text
users
```

The initial administrator has:

```text
type = 0
role = super-admin
is_active = true
```

The `type` field classifies the account.

It does not replace roles or permissions.

### 5.2 Initial Role

The initial role is:

```text
super-admin
```

The role receives all permissions implemented by the project.

No Feature-specific permission is required for the administrator's own:

- login
- refresh
- logout
- profile retrieval
- profile update
- password change
- password recovery

Protected self-service routes require authentication and active-account
middleware.

---

## 6. Scope

### 6.1 Included

- user table preparation
- Sanctum access tokens
- generic refresh-token table
- generic password-reset table
- Spatie Super Admin role
- initial Super Admin Seeder
- login
- refresh
- logout
- profile show
- profile update
- avatar upload
- password change
- forgot password
- code verification
- reset password
- direct localized email delivery
- security cookies
- CSRF protection
- rate limiting
- localized API messages
- Postman requests
- Pest API tests

### 6.2 Excluded

```text
Public registration
Customer authentication
Customer passwords
Email verification
MFA
MFA placeholders
Remember me
Device names
Device fingerprints
Session-management screen
Session list
User CRUD
Role CRUD
Permission CRUD
Role assignment API
Permission assignment API
Change email
Password history
Login history
Last-login fields
Login notification email
Password-change notification email
Social login
Google login
Apple login
Audit dashboard
Queued authentication mail
Authentication cleanup Jobs
Authentication cleanup Commands
Authentication scheduled tasks
```

---

## 7. Route Contract

All routes use:

```text
/api/v1/admin/auth
```

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

The multipart profile update may be sent by the frontend as:

```http
POST /api/v1/admin/auth/profile
```

with:

```text
_method=PATCH
```

Laravel treats the request as the approved `PATCH` route.

No alternate top-level authentication route area is created.

Do not create:

```text
/register
/me
/password
/logout-all
/sessions
/verify-email
/resend-verification
```

---

## 8. Route Middleware Matrix

| Route | Public | Middleware |
|---|---:|---|
| `POST /login` | Yes | locale, JSON/API response, login throttle, admin Origin validation where applicable |
| `POST /refresh` | Yes | locale, refresh throttle, exact Origin validation, admin CSRF validation |
| `POST /logout` | No | `auth:sanctum`, `admin.active` |
| `GET /profile` | No | `auth:sanctum`, `admin.active` |
| `PATCH /profile` | No | `auth:sanctum`, `admin.active` |
| `PUT /change-password` | No | `auth:sanctum`, `admin.active`, password-change throttle |
| `POST /forgot-password` | Yes | locale, forgot-password throttle |
| `POST /verify-forgot-password-code` | Yes | locale, verification-attempt enforcement |
| `POST /reset-password` | Yes | locale, reset-password throttle where configured |

Authentication must execute before authorization middleware.

No Spatie permission middleware is required for self-service authentication
routes.

---

## 9. User Data Model

### 9.1 Table

```text
users
```

### 9.2 Required Columns

```text
id
name
email
email_verified_at nullable
password
type
is_active
avatar_disk nullable
avatar_path nullable
created_at
updated_at
```

### 9.3 Column Direction

```text
id:
BIGINT UNSIGNED primary key

name:
VARCHAR(150)

email:
VARCHAR(255)
unique
stored normalized

email_verified_at:
TIMESTAMP nullable

password:
VARCHAR(255)

type:
TINYINT UNSIGNED
default 0 for administrator

is_active:
BOOLEAN
default true

avatar_disk:
VARCHAR(50) nullable

avatar_path:
VARCHAR(500) nullable
```

### 9.4 Excluded User Columns

Do not add for this Feature:

```text
remember_token
deleted_at
preferred_locale
last_login_at
last_login_ip
device_name
device_fingerprint
two_factor_secret
two_factor_recovery_codes
```

### 9.5 Email Normalization

Before persistence and lookup:

```php
mb_strtolower(trim($email))
```

Rules:

- Store the normalized email in `users.email`.
- Apply a unique database index.
- Do not modify the password string.
- Do not trim the password.
- Do not preserve a second email field in this Feature.

---

## 10. User Type

Use a PHP backed enum or equivalent stable value.

Example:

```php
enum UserType: int
{
    case ADMIN = 0;
}
```

Rules:

- `type = 0` identifies the broad administrator account type.
- `type` is not used as the final authorization decision.
- Protected routes use authentication, active-account checks, roles,
  permissions, and Policies where required.
- `type` is not accepted from profile requests.
- `type` is not returned in authentication profile responses.

---

## 11. Active Account

Use:

```text
is_active BOOLEAN
```

An active administrator may authenticate.

An inactive administrator:

- cannot log in
- cannot refresh
- cannot use an existing access token
- has active tokens revoked when detected
- receives the approved localized inactive-account error

Arabic:

```text
هذا الحساب غير نشط.
```

English:

```text
This account is inactive.
```

Response:

```text
HTTP 403
code: USER_INACTIVE
```

---

## 12. Spatie Role

Role name:

```text
super-admin
```

Guard:

```text
The configured guard used by the User model and Spatie package.
```

This is commonly `web`, but implementation must read the repository
configuration instead of hard-coding an assumption.

Rules:

- create role idempotently
- assign role to the initial administrator
- return one role string in API responses
- do not return a roles array
- do not introduce auth-specific permissions
- do not allow role mutation through profile

---

## 13. Super Admin Seeder

### 13.1 Environment Variables

```env
SUPER_ADMIN_NAME=
SUPER_ADMIN_EMAIL=
SUPER_ADMIN_PASSWORD=
```

The real password must never appear in:

- source
- `.env.example`
- documentation
- Postman examples
- tests
- logs

### 13.2 Seeder Behaviour

The Seeder must:

1. read the environment-backed configuration
2. normalize email
3. validate required values
4. validate the password policy
5. create the `super-admin` role idempotently
6. find the user by normalized email
7. create the user when missing
8. assign:
   - `type = 0`
   - `is_active = true`
9. hash the password when creating the user
10. assign the `super-admin` role
11. preserve the existing password on repeated runs
12. preserve existing user identity
13. avoid duplicate users
14. avoid duplicate roles

### 13.3 Rerun Rules

When the administrator already exists:

- do not create a duplicate
- do not overwrite the password
- ensure `type = ADMIN`
- ensure `is_active = true`
- ensure the `super-admin` role is assigned

### 13.4 Missing Secret

When the production password is missing:

- fail safely
- print a clear console error
- do not create a partial user
- do not generate a weak default password

---

## 14. Shared Profile Resource

Login and profile endpoints use the same profile representation.

Approved shape:

```json
{
  "name": "Super Admin",
  "email": "admin@example.com",
  "avatar": "https://api.example.com/storage/avatars/...",
  "role": "super-admin",
  "permissions": [
    "dashboard.view",
    "orders.view"
  ]
}
```

When no avatar exists:

```json
{
  "avatar": null
}
```

Rules:

- no `id`
- no alternate avatar URL field
- no `roles` array
- no `type`
- no `isActive`
- no `emailVerifiedAt`
- `role` is one string
- `permissions` is an array of strings
- permission order should be deterministic
- role and permission identifiers remain English
- the backend remains authoritative even though React receives permissions

---

## 15. Shared Success Response

Success shape:

```json
{
  "success": true,
  "message": "Localized message",
  "data": {}
}
```

Rules:

- success responses do not include `code: null`
- request and response keys use `camelCase`
- messages use the resolved request locale
- IDs are omitted from auth profile output by approved decision
- internal token hashes are never returned

---

## 16. Shared Error Response

Error shape:

```json
{
  "success": false,
  "message": "Localized message",
  "code": "STABLE_ERROR_CODE",
  "errors": null
}
```

Validation shape:

```json
{
  "success": false,
  "message": "Localized validation message",
  "code": "VALIDATION_ERROR",
  "errors": {
    "fieldName": [
      "Localized error"
    ]
  }
}
```

---

# Login

## 17. Login Route

```http
POST /api/v1/admin/auth/login
```

### 17.1 Request

```json
{
  "email": "admin@example.com",
  "password": "Password@123"
}
```

No additional fields are accepted.

Do not accept:

```text
rememberMe
deviceName
type
role
permissions
isActive
```

### 17.2 Validation

```text
email:
required
string
email
maximum 255 characters

password:
required
string
```

### 17.3 Normalization

Normalize only email.

Do not trim or transform password.

### 17.4 Rate Limit

```text
5 attempts per minute
key: normalized email + requester IP
```

Successful login clears the relevant failed-attempt state.

### 17.5 Credential Failure

Unknown email and incorrect password return the same response:

```text
HTTP 401
code: INVALID_CREDENTIALS
```

Arabic message:

```text
بيانات تسجيل الدخول غير صحيحة.
```

English message:

```text
The provided credentials are invalid.
```

Do not reveal whether the email exists.

### 17.6 Inactive Account

```text
HTTP 403
code: USER_INACTIVE
```

The inactive-account message is explicit by approved decision.

### 17.7 Successful Login Transaction

Successful login performs:

1. find user by normalized email
2. verify administrator type
3. verify active state
4. verify password
5. begin transaction
6. revoke all existing Sanctum access tokens
7. revoke all active refresh tokens
8. opportunistically delete expired refresh tokens for this user
9. create one access token
10. create one refresh token record
11. commit
12. set refresh cookie
13. set CSRF cookie
14. return access token and profile

Exactly one active administrator session remains.

### 17.8 Login Response

```json
{
  "success": true,
  "message": "تم تسجيل الدخول بنجاح.",
  "data": {
    "accessToken": "plain-access-token",
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

Response status:

```text
200 OK
```

The response also sets:

```text
admin_refresh_token
admin_csrf_token
```

The response does not contain:

```text
refreshToken
id
type
roles
```

---

# Access Token

## 18. Access Token Rules

Technology:

```text
Laravel Sanctum personal access token
```

Lifetime:

```text
15 minutes
900 seconds
```

Recommended token name:

```text
admin-access-token
```

Recommended ability:

```text
admin:access
```

Rules:

- store access-token hash through Sanctum
- use `expires_at`
- do not rely only on frontend expiry
- return plain token once
- React stores the token in memory only
- send as Bearer token
- delete all previous access tokens on login
- delete previous access token on refresh
- delete all access tokens on logout
- delete all access tokens after password change
- delete all access tokens after password reset

Protected request:

```http
Authorization: Bearer {accessToken}
```

---

# Refresh Token

## 19. Refresh Token Table

Table name:

```text
refresh_tokens
```

Use the approved polymorphic owner schema. The current and only authenticated
account type is the administrator `User`.

### 19.1 Columns

```text
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

### 19.2 Recommended Types

```text
id:
BIGINT UNSIGNED primary key

tokenable_type:
VARCHAR(255)

tokenable_id:
BIGINT UNSIGNED

token_hash:
CHAR(64)
unique

family_id:
CHAR(36) or approved UUID representation
indexed

expires_at:
TIMESTAMP
indexed

revoked_at:
TIMESTAMP nullable
indexed

rotated_to_token_id:
BIGINT UNSIGNED nullable
self reference

revocation_reason:
VARCHAR(50) nullable
```

### 19.3 Indexes

```text
INDEX(tokenable_type, tokenable_id)
UNIQUE(token_hash)
INDEX(family_id)
INDEX(expires_at)
INDEX(revoked_at)
```

### 19.4 Current Owner

Current Feature usage:

```text
tokenable_type = User model morph class
tokenable_id = administrator user ID
```

No customer authentication is implemented by this Feature.

---

## 20. Refresh Token Generation

Generate a high-entropy random token.

Recommended direction:

```text
64 cryptographically secure random bytes
URL-safe encoding
```

Store:

```text
SHA-256 hash
```

The plain token exists only:

- during generation
- in the HttpOnly cookie sent to the browser
- in the incoming cookie during refresh

Do not use the plain token as a database value.

Use SHA-256 because the server needs deterministic lookup by token hash.

---

## 21. Refresh Cookie

Cookie name:

```text
admin_refresh_token
```

Approved attributes:

```text
HttpOnly: true
Secure: true in production
SameSite: Strict
Domain: omitted
Path: /api/v1/admin/auth
Max-Age: 2592000
```

Rules:

- host-only cookie
- not readable by JavaScript
- not returned in JSON
- not available to the public root domain
- not stored in localStorage
- rotated after every successful refresh
- cleared on logout
- cleared when refresh fails
- cleared after password change where the current browser receives the response
- cleared after password reset where the browser has the cookie

---

## 22. CSRF Cookie

Cookie name:

```text
admin_csrf_token
```

Approved attributes:

```text
HttpOnly: false
Secure: true in production
SameSite: Strict
Domain: omitted
Path: /api/v1/admin/auth
```

The frontend reads the CSRF cookie and sends:

```http
X-CSRF-TOKEN: {csrfToken}
```

Rules:

- CSRF token is random
- CSRF token is not the refresh token
- CSRF token does not authenticate the user
- refresh requires exact cookie/header match
- use safe comparison
- refresh requires approved Origin
- login establishes the CSRF cookie
- refresh may rotate it
- logout clears it

The CSRF token is not returned in JSON.

---

## 23. Refresh Route

```http
POST /api/v1/admin/auth/refresh
```

Request body:

```text
Empty
```

Required browser data:

```http
Cookie: admin_refresh_token=...
Cookie: admin_csrf_token=...
X-CSRF-TOKEN: ...
Origin: https://admin.example.com
```

### 23.1 Refresh Rate Limit

```text
10 requests per minute
key: IP plus available session identifier
```

### 23.2 Successful Refresh Flow

1. validate Origin
2. validate CSRF cookie and header
3. read refresh cookie
4. hash refresh token with SHA-256
5. begin transaction
6. select refresh row using `lockForUpdate()`
7. verify:
   - row exists
   - not expired
   - not revoked
   - owner exists
   - owner is administrator
   - owner is active
8. revoke current refresh row
9. revoke current Sanctum access tokens
10. create new access token
11. create new refresh-token row in the same family
12. link old row to new row
13. commit
14. set rotated refresh cookie
15. set or rotate CSRF cookie
16. return the new access token

### 23.3 Refresh Response

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

Do not return profile.

Do not return refresh token.

### 23.4 Early Refresh

The frontend may call refresh before access-token expiry.

A successful call always rotates the refresh token and replaces the current
access token.

### 23.5 Invalid Refresh

All expected failures return:

```text
HTTP 401
code: REFRESH_TOKEN_INVALID
```

Do not expose whether the token was:

- malformed
- missing
- expired
- revoked
- reused
- owned by an inactive user
- linked to a missing user

Clear both authentication cookies.

### 23.6 Refresh Reuse

When a revoked rotated token is presented and the owner can be identified:

- revoke all access tokens
- revoke all refresh tokens for the owner
- clear cookies
- log safe security event
- return generic `REFRESH_TOKEN_INVALID`

---

## 24. Refresh Revocation Reasons

Stable internal values:

```text
login_replaced
refreshed
logout
password_changed
password_reset
user_inactive
refresh_reuse_detected
```

Use a PHP backed enum.

Do not use a native MySQL enum.

Do not return these values in public API responses.

---

## 25. Opportunistic Refresh Cleanup

The hosting plan does not provide an approved command, scheduler, Cron, or Job
for authentication cleanup.

Therefore cleanup occurs during authentication requests.

Rules:

- login cleans expired refresh records for the current user
- refresh cleans expired records for the resolved user when safe
- logout cleans expired records for the authenticated user
- revoked records remain until their original expiry when required for reuse
  detection
- expired revoked records may be deleted
- no global table scan is required on every request
- no Queue Job is dispatched
- no Artisan cleanup command is created
- no scheduler entry is created

Trade-off:

Records belonging to accounts that never authenticate again may remain longer.

This is accepted for the MVP.

---

# Logout

## 26. Logout Route

```http
POST /api/v1/admin/auth/logout
```

Requirements:

```text
Valid access token
Active administrator
```

There is no refresh-cookie-only logout route.

### 26.1 Logout Behaviour

Logout:

1. resolves authenticated administrator
2. begins transaction
3. deletes all Sanctum access tokens
4. revokes all active refresh tokens
5. opportunistically removes expired refresh records
6. commits
7. clears refresh cookie
8. clears CSRF cookie
9. frontend clears in-memory access token

### 26.2 Logout Response

```json
{
  "success": true,
  "message": "تم تسجيل الخروج بنجاح.",
  "data": null
}
```

Status:

```text
200 OK
```

Ordinary logout is effectively logout from all sessions.

---

# Profile

## 27. Profile Controller

Use one controller:

```text
ProfileController
```

Methods:

```php
public function show(): JsonResponse
public function update(UpdateProfileRequest $request): JsonResponse
```

Routes:

```php
Route::get('/profile', [ProfileController::class, 'show']);
Route::patch('/profile', [ProfileController::class, 'update']);
```

---

## 28. Show Profile

Route:

```http
GET /api/v1/admin/auth/profile
```

Response:

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

Status:

```text
200 OK
```

Do not return:

```text
id
type
isActive
emailVerifiedAt
password
token data
raw avatar path
```

---

## 29. Update Profile

Approved route:

```http
PATCH /api/v1/admin/auth/profile
```

Multipart request used by frontend:

```http
POST /api/v1/admin/auth/profile
Content-Type: multipart/form-data
```

Fields:

```text
_method=PATCH
name=Updated Name
avatar={optional file}
```

The endpoint may also accept JSON when updating only the name.

### 29.1 Allowed Fields

```text
name
avatar
_method
```

Do not accept:

```text
email
password
role
roles
permissions
type
isActive
avatarPath
avatarDisk
removeAvatar
```

### 29.2 Name Validation

```text
sometimes
required when present
string
trimmed
maximum 150 characters
```

### 29.3 Avatar Validation

Allowed extensions:

```text
jpg
jpeg
png
webp
```

Maximum size:

```text
2 MB
```

### 29.4 Avatar Input Semantics

```text
avatar field absent:
keep current avatar

avatar = empty string:
keep current avatar

avatar = valid uploaded file:
replace current avatar
```

Rules:

- no `removeAvatar` field
- no delete-only avatar operation
- do not accept an avatar URL or path from the frontend
- a non-empty string path is invalid
- backend generates the storage name
- public disk is used
- API returns `avatar` as approved public URL or `null`

### 29.5 Replacement Flow

1. authorize authenticated self-update
2. validate request
3. store new file
4. begin database transaction
5. update `avatar_disk` and `avatar_path`
6. update name when provided
7. commit
8. synchronously attempt old-file deletion
9. log deletion failure safely
10. return updated profile

When database update fails:

- delete newly stored file synchronously
- preserve old avatar metadata
- return safe error

When old-file deletion fails after commit:

- profile update remains successful
- log orphan candidate
- do not dispatch a Job

### 29.6 Update Response

Return the full shared profile shape:

```json
{
  "success": true,
  "message": "تم تحديث الملف الشخصي بنجاح.",
  "data": {
    "name": "Updated Name",
    "email": "admin@example.com",
    "avatar": "https://api.example.com/storage/avatars/...",
    "role": "super-admin",
    "permissions": []
  }
}
```

Profile update does not rotate or revoke tokens.

---

# Change Password

## 30. Change Password Route

```http
PUT /api/v1/admin/auth/change-password
```

### 30.1 Request

```json
{
  "currentPassword": "OldPassword@123",
  "password": "NewPassword@123",
  "passwordConfirmation": "NewPassword@123"
}
```

### 30.2 Validation

```text
currentPassword:
required
string

password:
required
string
minimum 10
mixed case
number
symbol
different from current password

passwordConfirmation:
required
same as password
```

Do not implement password history.

### 30.3 Rate Limit

```text
5 requests per minute per authenticated administrator
```

### 30.4 Invalid Current Password

```text
HTTP 422
code: CURRENT_PASSWORD_INVALID
```

### 30.5 Success Flow

1. authenticate active administrator
2. validate request
3. verify current password
4. begin transaction
5. update password hash
6. delete all Sanctum access tokens
7. revoke all refresh tokens
8. invalidate active password-reset workflows
9. commit
10. clear refresh and CSRF cookies in current browser response
11. require login again

### 30.6 Response

```json
{
  "success": true,
  "message": "تم تغيير كلمة المرور. يرجى تسجيل الدخول مرة أخرى.",
  "data": null
}
```

No password-change notification email is sent.

---

# Forgot Password

## 31. Forgot Password Route

```http
POST /api/v1/admin/auth/forgot-password
```

### 31.1 Request

```json
{
  "email": "admin@example.com"
}
```

Normalize email before lookup.

### 31.2 Generic Response

For unknown email, inactive account, and successful accepted request, the public
message must not reveal account existence.

Arabic:

```text
إذا كان البريد الإلكتروني مسجلاً، فسيتم إرسال رمز الاستعادة.
```

English:

```text
If the email address is registered, a recovery code will be sent.
```

Recommended successful status:

```text
200 OK
```

### 31.3 Inactive Account

When email belongs to an inactive administrator:

- return generic response
- do not send code
- do not reveal inactive state
- do not create a reset workflow

### 31.4 Rate Limit

```text
5 requests per minute
key: normalized email + IP
```

Resend cooldown:

```text
60 seconds
```

---

## 32. Password Reset Table

Table name:

```text
password_resets
```

Use the approved polymorphic owner schema. The current and only authenticated
account type is the administrator `User`.

### 32.1 Columns

```text
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

### 32.2 Recommended Types

```text
id:
BIGINT UNSIGNED primary key

resettable_type:
VARCHAR(255)

resettable_id:
BIGINT UNSIGNED

email_normalized:
VARCHAR(255)
indexed

code_hash:
VARCHAR(255)

code_expires_at:
TIMESTAMP
indexed

verification_attempts:
TINYINT UNSIGNED
default 0

verified_at:
TIMESTAMP nullable

reset_token_hash:
CHAR(64) nullable
unique

reset_token_expires_at:
TIMESTAMP nullable
indexed

consumed_at:
TIMESTAMP nullable
indexed
```

### 32.3 Current Owner

```text
resettable_type = User model morph class
resettable_id = administrator user ID
```

Do not create a reset record for an unknown or inactive account.

The response remains generic.

---

## 33. Recovery Code

Generate:

```php
random_int(100000, 999999)
```

Properties:

```text
six numeric digits
single use
10-minute lifetime
maximum 5 failed verification attempts
```

Store the code using:

```text
Laravel password hash
```

Verify using:

```php
Hash::check()
```

Do not store plaintext code.

Do not return code in API.

Do not log code.

---

## 34. New Code Request

For a valid active administrator:

1. enforce throttle
2. enforce 60-second resend cooldown
3. opportunistically remove expired or consumed reset records for the same
   account/email
4. mark previous active workflow consumed or otherwise unusable
5. generate six-digit code
6. hash code
7. begin transaction
8. create one reset record
9. commit
10. send localized email synchronously
11. return generic response

A new code invalidates all previous codes for the account.

---

## 35. Direct Mail Delivery

The hosting decision does not use a queue or Job for this Feature.

Send mail directly:

```php
Mail::to($user->email)->send($mailable);
```

Do not use:

```text
ShouldQueue
Queue::push
dispatch()
afterCommit Job
Database queue
Queue worker
```

### 35.1 Why Mail Is Sent After Commit

Do not send email inside the open database transaction.

Correct order:

```text
1. Persist reset workflow.
2. Commit database transaction.
3. Send email synchronously.
4. Return API response.
```

This prevents:

- the user receiving a code that was not committed
- a slow SMTP call holding database locks
- transaction duration depending on mail-server response time

### 35.2 Mail Failure

When synchronous mail delivery fails for a valid active account:

1. mark the newly created reset workflow consumed or unusable
2. log a safe error
3. do not log the code
4. return:

```text
HTTP 503
code: MAIL_SERVICE_UNAVAILABLE
```

Arabic:

```text
تعذر إرسال رسالة الاستعادة حالياً. يرجى المحاولة مرة أخرى لاحقاً.
```

English:

```text
The recovery email could not be sent at this time. Please try again later.
```

No Job retry is created.

The user may retry after the configured rate-limit/cooldown rules.

### 35.3 Email Content

The email contains:

- six-digit code
- ten-minute expiry
- instruction to ignore the email when not requested

The email does not contain:

- password
- access token
- refresh token
- reset token
- direct reset link
- server path

### 35.4 Email Locale

Resolve locale from:

```http
Accept-Language
```

Supported:

```text
ar
en
```

Because mail is synchronous, apply the resolved locale directly while building
and sending the Mailable.

No locale needs to be carried through a Job payload.

---

# Verify Recovery Code

## 36. Verify Route

```http
POST /api/v1/admin/auth/verify-forgot-password-code
```

### 36.1 Request

```json
{
  "email": "admin@example.com",
  "code": "123456"
}
```

### 36.2 Verification Rules

Verify:

- normalized email
- active reset record exists
- record is not consumed
- code has not expired
- verification attempts are below five
- account exists
- account is administrator
- account state follows the approved recovery policy
- code hash matches

### 36.3 Failed Attempt

On wrong code:

- increment `verification_attempts`
- when count reaches five, mark workflow consumed
- return generic code failure

Response:

```text
HTTP 422
code: PASSWORD_RESET_CODE_INVALID
```

Do not reveal:

- wrong code
- expired code
- consumed code
- unknown email
- attempt limit
- inactive account

### 36.4 Successful Verification

On success:

1. begin transaction
2. select reset row using `lockForUpdate()`
3. revalidate code state
4. generate a long random reset token
5. store SHA-256 hash
6. set `verified_at`
7. set reset-token expiry after ten minutes
8. make the six-digit code unusable for another verification
9. commit
10. return plain reset token once

---

## 37. Why Verify Returns a Reset Token

The six-digit code is short and designed for human entry.

After the code is verified, the password is changed through a separate screen
and request.

The server therefore exchanges the verified code for a stronger one-time reset
token.

Flow:

```text
Step 1:
Request recovery code

Step 2:
Submit six-digit code

Step 3:
Receive long one-time reset token

Step 4:
Submit new password with reset token
```

The reset token is:

- long
- cryptographically random
- difficult to guess
- valid for ten minutes
- single-use
- stored as SHA-256 hash
- held in React memory only

The code is no longer reused during password submission.

This separates human verification from the privileged password-reset command.

---

## 38. Verify Response

```json
{
  "success": true,
  "message": "تم التحقق من رمز الاستعادة بنجاح.",
  "data": {
    "resetToken": "long-random-one-time-token",
    "resetTokenExpiresIn": 600
  }
}
```

Status:

```text
200 OK
```

React stores `resetToken` in memory only.

Do not store it in:

```text
localStorage
sessionStorage
cookie
URL
```

---

# Reset Password

## 39. Reset Password Route

```http
POST /api/v1/admin/auth/reset-password
```

### 39.1 Request

```json
{
  "email": "admin@example.com",
  "resetToken": "long-random-one-time-token",
  "password": "NewPassword@123",
  "passwordConfirmation": "NewPassword@123"
}
```

### 39.2 Validation

Validate:

- email
- reset token
- password policy
- confirmation
- active reset workflow
- verified state
- reset-token hash
- reset-token expiry
- unused state
- administrator account

### 39.3 Reset Token Storage

Store:

```text
SHA-256 hash
```

Lookup uses deterministic hash.

Do not use plaintext token persistence.

### 39.4 Failure

All expected token failures return:

```text
HTTP 422
code: PASSWORD_RESET_TOKEN_INVALID
```

Do not reveal exact failure reason.

### 39.5 Success Flow

1. normalize email
2. hash incoming reset token
3. begin transaction
4. select reset row with `lockForUpdate()`
5. validate token and expiry
6. resolve administrator
7. hash new password
8. update user password
9. mark current reset workflow consumed
10. invalidate every other active reset workflow for the account
11. delete all Sanctum access tokens
12. revoke all refresh tokens
13. opportunistically clean expired auth records for the account
14. commit
15. clear auth cookies where present
16. return success
17. require login again

### 39.6 Response

```json
{
  "success": true,
  "message": "تم تغيير كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.",
  "data": null
}
```

No confirmation email is sent.

---

## 40. Opportunistic Password-Reset Cleanup

No Cron, scheduler, Command, Queue, or Job is available.

Cleanup occurs during:

- forgot-password request
- code verification
- password reset
- authenticated password change

Rules:

- clean expired records for the same account/email
- invalidate old active workflows before new code creation
- mark used workflows consumed
- do not scan the whole table on every request
- do not create a cleanup command
- do not create a cleanup Job
- do not require Hostinger Cron

Trade-off:

Expired records for accounts that never use authentication again may remain
longer.

This is accepted for the MVP.

---

## 41. Password Policy

Approved rule:

```php
Password::min(10)
    ->mixedCase()
    ->numbers()
    ->symbols();
```

Apply to:

- Super Admin initial creation
- authenticated password change
- forgotten-password reset
- future administrator creation when approved

Rules:

- confirmation required
- new authenticated password differs from current password
- no password history
- no `uncompromised()` dependency in MVP
- password hashes use Laravel's configured hasher

---

## 42. Cookies and Cross-Origin Requests

Expected production topology:

```text
Admin:
https://admin.example.com

API:
https://api.example.com
```

The frontend must use credentials for login/refresh/logout requests that set or
clear auth cookies.

Example browser option:

```js
credentials: 'include'
```

CORS uses exact configured origins.

Production must not use:

```text
Access-Control-Allow-Origin: *
```

The refresh cookie remains host-only to the API domain.

---

## 43. Security Headers

Sensitive authentication responses should include:

```http
Cache-Control: no-store, private
Pragma: no-cache
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: no-referrer
```

The API security policy follows `security-standards.md`.

---

## 44. Rate-Limit Summary

```text
Login:
5 per minute by normalized email + IP

Refresh:
10 per minute by IP plus available session context

Change password:
5 per minute per authenticated administrator

Forgot password:
5 per minute by normalized email + IP

Resend cooldown:
60 seconds

Verify code:
5 failed attempts per code
```

Rate-limit response:

```text
HTTP 429
code: RATE_LIMITED
```

---

## 45. Error Codes

Approved Feature codes:

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
FILE_TYPE_NOT_ALLOWED
FILE_SIZE_EXCEEDED
MAIL_SERVICE_UNAVAILABLE
INTERNAL_ERROR
```

Do not expose separate public codes for:

```text
expired recovery code
attempt-limit recovery code
consumed recovery code
expired refresh token
revoked refresh token
reused refresh token
```

Detailed causes remain in safe internal logs only.

---

## 46. HTTP Status Matrix

| Operation | Condition | Status | Code |
|---|---|---:|---|
| Login | Success | 200 | — |
| Login | Invalid credentials | 401 | `INVALID_CREDENTIALS` |
| Login | Inactive account | 403 | `USER_INACTIVE` |
| Refresh | Success | 200 | — |
| Refresh | Invalid token/cookie/CSRF session | 401 or approved CSRF status | `REFRESH_TOKEN_INVALID` or stable CSRF code |
| Logout | Success | 200 | — |
| Profile show | Success | 200 | — |
| Profile update | Success | 200 | — |
| Profile update | Validation failure | 422 | `VALIDATION_ERROR` |
| Change password | Success | 200 | — |
| Change password | Wrong current password | 422 | `CURRENT_PASSWORD_INVALID` |
| Forgot password | Accepted | 200 | — |
| Forgot password | Mail transport failure | 503 | `MAIL_SERVICE_UNAVAILABLE` |
| Verify code | Success | 200 | — |
| Verify code | Invalid workflow/code | 422 | `PASSWORD_RESET_CODE_INVALID` |
| Reset password | Success | 200 | — |
| Reset password | Invalid workflow/token | 422 | `PASSWORD_RESET_TOKEN_INVALID` |
| Any limited endpoint | Rate limited | 429 | `RATE_LIMITED` |

The exact CSRF error code must be stable and documented by implementation.

Recommended:

```text
CSRF_TOKEN_MISMATCH
```

---

## 47. Localization

Supported locales:

```text
ar
en
```

Locale source:

```http
Accept-Language
```

Do not store preferred locale on `users`.

Translate:

- login messages
- refresh messages
- logout messages
- profile messages
- password messages
- forgot-password messages
- recovery email
- validation errors
- inactive-account error
- rate-limit error
- mail-service failure

Do not translate:

- route paths
- JSON keys
- error codes
- role names
- permission names
- token type
- email value
- filenames
- technical cookie names

---

## 48. Recommended Translation Keys

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
auth.mail_service_unavailable
auth.rate_limited
auth.csrf_token_mismatch
```

Mail keys:

```text
mail.password_reset.subject
mail.password_reset.introduction
mail.password_reset.code_label
mail.password_reset.expiry
mail.password_reset.ignore
```

---

## 49. Controllers

Approved controllers:

```text
LoginController
RefreshTokenController
LogoutController
ProfileController
ChangePasswordController
ForgotPasswordController
VerifyForgotPasswordCodeController
ResetPasswordController
```

`ProfileController` contains `show` and `update`.

Other controllers should remain single-purpose.

Controllers must:

- receive validated requests
- invoke Actions
- return Resources or response builders
- avoid business logic
- avoid raw token persistence
- avoid direct permission mutation

---

## 50. Form Requests

Recommended requests:

```text
LoginRequest
UpdateProfileRequest
ChangePasswordRequest
ForgotPasswordRequest
VerifyForgotPasswordCodeRequest
ResetPasswordRequest
```

The refresh request may use middleware and a focused request object when useful,
even though the JSON body is empty.

Every mutation uses a dedicated Form Request.

---

## 51. Actions

Recommended Actions:

```text
LoginAdminAction
RefreshAdminSessionAction
LogoutAdminAction
UpdateAdminProfileAction
ChangeAdminPasswordAction
SendForgotPasswordCodeAction
VerifyForgotPasswordCodeAction
ResetForgottenPasswordAction
```

Actions own orchestration and transaction boundaries.

---

## 52. Services

Recommended services:

```text
AccessTokenService
RefreshTokenService
AdminSessionRevocationService
AdminAuthCookieService
AdminCsrfService
ForgotPasswordCodeService
ResetTokenService
AdminProfileResourceBuilder or Resource
```

Responsibilities:

### AccessTokenService

- issue Sanctum token
- set expiry
- set ability
- revoke access tokens

### RefreshTokenService

- generate token
- hash token
- persist token
- rotate token
- revoke token family/session
- detect reuse
- opportunistic cleanup

### AdminSessionRevocationService

- revoke all access tokens
- revoke all refresh tokens
- apply revocation reason

### AdminAuthCookieService

- set refresh cookie
- set CSRF cookie
- clear both cookies
- use consistent attributes

### ForgotPasswordCodeService

- generate six-digit code
- hash and verify code
- enforce attempts
- enforce expiry

### ResetTokenService

- generate long random token
- SHA-256 hash
- verify deterministic hash
- enforce expiry and single use

---

## 53. API Resources

Recommended Resource:

```text
AdminProfileResource
```

Output:

```text
name
email
avatar
role
permissions
```

Rules:

- one role string
- deterministic permission order
- no user ID
- no raw avatar path
- no internal user fields

Login may compose:

```text
AuthenticatedAdminResource
```

with token metadata plus `AdminProfileResource`.

---

## 54. Middleware

Recommended middleware or equivalent boundaries:

```text
auth:sanctum
EnsureAdminIsActive
ValidateAdminOrigin
ValidateAdminCsrfToken
throttle
```

Aliases may be:

```text
admin.active
admin.origin
admin.csrf
```

Rules:

- active-account check runs on every protected admin route
- active-account failure revokes tokens
- refresh validates Origin
- refresh validates CSRF cookie/header
- protected self-service routes do not require Spatie permissions
- future Feature routes require permission middleware

---

## 55. Transactions

Transactions are required for:

```text
login token replacement
refresh rotation
logout revocation
password change
verify code and reset-token creation
password reset
avatar metadata replacement
```

### 55.1 Refresh Lock

Use:

```php
lockForUpdate()
```

on the refresh-token row.

This prevents two successful refreshes using the same token.

### 55.2 Reset Lock

Use row locking during:

- successful code verification
- password reset

This prevents:

- multiple reset tokens from one code
- reuse of one reset token
- concurrent password resets

### 55.3 Mail and Transactions

Do not send mail inside the database transaction.

Sequence:

```text
Commit reset workflow
Then send mail directly
```

There is no `afterCommit` Job.

---

## 56. Security Logging

Recommended safe events:

```text
admin_login_succeeded
admin_login_failed
admin_login_rate_limited
inactive_admin_blocked
admin_refresh_succeeded
admin_refresh_failed
admin_refresh_reuse_detected
admin_logout
admin_password_changed
admin_password_reset_requested
admin_password_reset_code_verified
admin_password_reset_completed
admin_password_reset_mail_failed
avatar_cleanup_failed
```

Never log:

```text
password
access token
refresh token
refresh cookie
CSRF token
reset code
reset token
Authorization header
Cookie header
```

---

## 57. Postman Documentation

Update the Postman collection for all nine routes.

Environment variables may include:

```text
baseUrl
adminEmail
adminPassword
accessToken
csrfToken
```

Rules:

- use placeholders only
- do not commit real passwords
- do not commit production tokens
- Postman cookie jar stores HttpOnly refresh cookie automatically
- login test script may store `accessToken`
- refresh pre-request script may read the non-HttpOnly CSRF cookie and set
  `X-CSRF-TOKEN`
- refresh body remains empty
- protected requests use Bearer access token
- documentation explains cookie requirements
- no Swagger UI is required

---

## 58. Postman Request Sequence

```text
1. Login
2. Inspect profile
3. Refresh
4. Update profile
5. Change password
6. Login again
7. Forgot password
8. Verify code
9. Reset password
10. Login with new password
11. Logout
```

For automated Postman documentation, recovery code entry remains a manual
environment-variable step because the code is delivered by email.

---

## 59. Testing Strategy

Primary tests are Pest API Feature Tests using MySQL.

Use:

```text
RefreshDatabase
Mail::fake()
Storage::fake('public')
UploadedFile::fake()
time travel
```

Do not use:

```text
Queue::fake()
queued Mailable assertions
SQLite
sleep()
Eloquent mocks in Feature Tests
```

Mail is synchronous.

Test mail with sent assertions.

---

## 60. Login Tests

Required:

- active administrator logs in
- normalized email
- password is not trimmed
- incorrect password
- unknown email
- unknown and wrong password use same response
- inactive account
- non-admin account rejected
- login rate limit
- successful login returns access token
- response excludes refresh token
- response excludes user ID
- profile has `name`
- profile has `email`
- profile has `avatar`
- profile has one `role` string
- profile has `permissions` array
- access expiry equals 900
- refresh expiry equals 2592000
- refresh cookie is HttpOnly
- refresh cookie is Secure in production
- refresh cookie is SameSite Strict
- refresh cookie is host-only
- refresh cookie path is correct
- CSRF cookie is readable
- second login revokes old access token
- second login revokes old refresh token
- one active session remains

---

## 61. Refresh Tests

Required:

- valid cookie and CSRF refresh succeeds
- request body not required
- refresh token absent from JSON
- profile absent from refresh response
- access token rotated
- refresh cookie rotated
- old access token revoked
- old refresh token revoked
- expired refresh rejected
- malformed refresh rejected
- missing cookie rejected
- missing CSRF header rejected
- mismatched CSRF rejected
- unknown Origin rejected
- inactive user rejected
- reused token revokes all sessions
- failure clears cookies
- same token used concurrently succeeds once only
- MySQL row lock behaviour
- opportunistic cleanup removes eligible expired records

---

## 62. Logout Tests

Required:

- valid access token required
- logout succeeds
- all access tokens revoked
- all refresh tokens revoked
- refresh cookie cleared
- CSRF cookie cleared
- old access token rejected
- old refresh token rejected
- response contains no token

---

## 63. Profile Tests

Show:

- authenticated profile
- active-account enforcement
- exact response keys
- no `id`
- no alternate avatar URL field
- one `role` string
- permissions array
- no raw path

Update:

- JSON name update
- multipart `_method=PATCH`
- name maximum length
- avatar absent keeps image
- empty avatar keeps image
- uploaded image replaces image
- string avatar path rejected
- no `removeAvatar`
- invalid type rejected
- file over 2 MB rejected
- generated stored name
- new public URL returned in `avatar`
- database failure deletes new file
- old-file delete failure logs and keeps success
- email field cannot change
- role cannot change
- permissions cannot change
- type cannot change
- status cannot change
- profile update does not rotate tokens

---

## 64. Change Password Tests

Required:

- correct current password
- incorrect current password
- weak password
- missing uppercase
- missing lowercase
- missing number
- missing symbol
- confirmation mismatch
- new password same as current
- rate limiting
- password hash updated
- all access tokens revoked
- all refresh tokens revoked
- cookies cleared
- login required again
- old password fails
- new password succeeds
- no email sent

---

## 65. Forgot Password Tests

Required:

- active administrator request
- unknown email generic response
- inactive account generic response
- unknown email creates no record
- inactive account creates no record
- six-digit code
- code stored as password hash
- plaintext code not stored
- code expires in ten minutes
- resend cooldown
- rate limit
- previous workflow invalidated
- old records for same account cleaned opportunistically
- Arabic email sent
- English email sent
- mail sent synchronously
- no Queue Job dispatched
- API does not return code
- API does not claim delivery details
- mail failure invalidates workflow
- mail failure returns 503
- mail failure returns `MAIL_SERVICE_UNAVAILABLE`
- logs do not contain code

---

## 66. Verify Code Tests

Required:

- valid code
- invalid code
- expired code
- consumed code
- unknown email
- fifth failed attempt invalidates workflow
- error remains generic
- reset token returned once
- reset token expiry equals 600
- reset token stored as SHA-256 hash
- plaintext reset token not stored
- recovery code cannot be reused
- concurrent verification permits one reset token
- response has no user ID

---

## 67. Reset Password Tests

Required:

- valid reset token
- invalid reset token
- expired reset token
- consumed reset token
- unknown email
- weak password
- confirmation mismatch
- password updated
- reset workflow consumed
- all other workflows invalidated
- all access tokens revoked
- all refresh tokens revoked
- cookies cleared where present
- old password fails
- new password succeeds
- no confirmation email sent
- same reset token cannot be used twice
- concurrent reset succeeds once

---

## 68. Seeder Tests

Required:

- role created
- administrator created
- email normalized
- password hashed
- password policy validated
- type is administrator
- active state true
- role assigned
- Seeder idempotent
- no duplicate user
- no duplicate role
- password not overwritten on rerun
- missing production password fails safely
- no plaintext secret in source

---

## 69. Architecture and Route Tests

Verify:

- exact nine routes
- no alternate top-level authentication route area
- no registration route
- no customer auth route
- no user-management route
- protected routes use `auth:sanctum`
- protected routes use active middleware
- refresh uses Origin and CSRF middleware
- ProfileController contains show/update
- mutation endpoints use Form Requests
- Controllers do not contain business workflows
- no Queue or Job class for authentication mail
- no authentication cleanup Command
- no authentication scheduler entry

---

## 70. Security Tests

Verify:

- access token not in cookie
- refresh token not in JSON
- refresh token not readable by JavaScript
- access token not persisted by backend browser contract
- sensitive responses use `no-store`
- cookies use approved path
- production cookie uses Secure
- wildcard CORS absent
- exact Origin enforced
- CSRF mismatch blocked
- generic credential response
- generic forgot-password account response
- tokens and codes absent from logs
- profile cannot escalate privileges
- avatar path cannot be supplied
- stack traces hidden in production response
- rate limits enforced

---

## 71. Acceptance Scenarios

### Scenario A — First Login

Given the Super Admin exists and is active  
When valid email and password are submitted  
Then previous sessions are revoked  
And one access token is returned  
And one refresh cookie is set  
And one CSRF cookie is set  
And profile fields match the approved shape.

### Scenario B — Session Renewal

Given a valid refresh cookie and CSRF token  
When refresh is submitted from an approved Origin  
Then the old token pair is invalidated  
And a new access token is returned  
And a rotated refresh cookie is set.

### Scenario C — Refresh Reuse

Given a refresh token has already been rotated  
When the old token is submitted again  
Then every administrator session is revoked  
And cookies are cleared  
And the API returns `REFRESH_TOKEN_INVALID`.

### Scenario D — Profile Avatar Replacement

Given an administrator has an avatar  
When a valid new avatar file is submitted with `_method=PATCH`  
Then the new avatar is stored  
And metadata is committed  
And old-file deletion is attempted synchronously  
And the response uses `avatar`.

### Scenario E — Empty Avatar

Given an administrator has an avatar  
When profile update sends no avatar or `avatar=""`  
Then the existing avatar remains unchanged.

### Scenario F — Password Recovery

Given an active administrator email  
When forgot password is requested  
Then a hashed six-digit code is committed  
And the email is sent directly after commit  
And the API returns the generic accepted response.

### Scenario G — Mail Failure

Given reset state was committed  
When direct SMTP delivery fails  
Then the workflow is invalidated  
And the API returns `503 MAIL_SERVICE_UNAVAILABLE`  
And no code appears in logs.

### Scenario H — Code Exchange

Given a valid recovery code  
When the code is verified  
Then the code becomes unusable  
And a long one-time reset token is returned  
And only its SHA-256 hash is stored.

### Scenario I — Password Reset

Given a valid reset token  
When a compliant new password is submitted  
Then the password is changed  
And every session is revoked  
And every reset workflow is invalidated  
And login is required again.

---

## 72. Implementation File Map

Recommended direction:

```text
app/
  Actions/
    Auth/
      LoginAdminAction.php
      RefreshAdminSessionAction.php
      LogoutAdminAction.php
      UpdateAdminProfileAction.php
      ChangeAdminPasswordAction.php
      SendForgotPasswordCodeAction.php
      VerifyForgotPasswordCodeAction.php
      ResetForgottenPasswordAction.php

  Enums/
    UserType.php
    RefreshTokenRevocationReason.php

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

    Middleware/
      EnsureAdminIsActive.php
      ValidateAdminOrigin.php
      ValidateAdminCsrfToken.php

    Requests/
      Api/
        V1/
          Admin/
            Auth/
              LoginRequest.php
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
              AdminProfileResource.php
              AuthenticatedAdminResource.php

  Mail/
    AdminPasswordResetCodeMail.php

  Models/
    User.php
    RefreshToken.php
    PasswordReset.php

  Services/
    Auth/
      AccessTokenService.php
      RefreshTokenService.php
      AdminSessionRevocationService.php
      AdminAuthCookieService.php
      AdminCsrfService.php
      ForgotPasswordCodeService.php
      ResetTokenService.php

database/
  migrations/
    create_or_update_users_table.php
    create_personal_access_tokens_table.php
    create_refresh_tokens_table.php
    create_password_resets_table.php
    create_permission_tables.php

  seeders/
    RolesAndPermissionsSeeder.php
    SuperAdminSeeder.php

lang/
  ar/
    auth.php
    validation.php
    mail.php

  en/
    auth.php
    validation.php
    mail.php

routes/
  api.php

tests/
  Feature/
    Api/
      V1/
        Admin/
          Auth/
            LoginAdminTest.php
            RefreshAdminSessionTest.php
            LogoutAdminTest.php
            ShowAdminProfileTest.php
            UpdateAdminProfileTest.php
            ChangeAdminPasswordTest.php
            ForgotAdminPasswordTest.php
            VerifyForgotPasswordCodeTest.php
            ResetAdminPasswordTest.php

  Concurrency/
    Authentication/
      RefreshTokenConcurrencyTest.php
      PasswordResetConcurrencyTest.php

  Architecture/
    IdentityAuthenticationRouteTest.php
```

The exact migration filenames follow Laravel timestamp conventions.

---

## 73. Implementation Order

Recommended order:

1. configure Sanctum
2. configure Spatie Permission
3. update users schema
4. create refresh-token migration/model
5. create password-reset migration/model
6. create enums
7. create role and Super Admin Seeders
8. create shared API response handling
9. create profile Resource
10. create token and cookie Services
11. create active, Origin, and CSRF middleware
12. implement login
13. implement refresh
14. implement logout
15. implement profile show/update
16. implement password change
17. implement forgot-password direct mail
18. implement code verification
19. implement password reset
20. add localization
21. add Postman requests
22. add API tests
23. add concurrency tests
24. add route/architecture tests
25. run Pint, Larastan, and Pest

---

## 74. Definition of Done

The Feature is complete only when:

- all nine routes exist
- route paths match exactly
- Super Admin Seeder is safe and idempotent
- login uses normalized email and password
- inactive users are blocked
- access token expires after 15 minutes
- access token is returned in JSON
- access token is intended for React memory only
- refresh token expires after 30 days
- refresh token is stored in HttpOnly cookie
- refresh token is absent from JSON
- CSRF protection is implemented
- exact Origin validation is implemented
- one active session is enforced
- refresh rotation is implemented
- refresh reuse is detected
- logout revokes all sessions
- profile output has no ID
- profile uses `avatar`
- profile uses one `role` string
- profile returns permissions array
- multipart `_method=PATCH` works
- empty avatar keeps current image
- avatar file replaces current image
- change password revokes sessions
- forgot password uses six-digit code
- mail is sent directly after commit
- no authentication Queue or Job exists
- no cleanup Command or scheduler exists
- code verification returns one-time reset token
- password reset revokes sessions
- Arabic and English messages exist
- Postman documentation is updated
- MySQL API tests pass
- refresh concurrency test passes
- Pint passes
- Larastan passes
- no known critical authentication vulnerability remains

---

## 75. Non-Negotiable Rules

- Use the exact approved routes.
- Do not create registration.
- Do not create customer authentication.
- Do not create MFA.
- Do not create future MFA scaffolding.
- Do not create user management.
- Do not return user ID in auth/profile responses.
- Return `role` as one string.
- Return `permissions` as an array.
- Use `avatar` as the exact avatar field.
- Access token lifetime is 15 minutes.
- Refresh token lifetime is 30 days.
- Store access token in React memory only.
- Do not store access token in localStorage.
- Store refresh token in HttpOnly cookie.
- Do not return refresh token in JSON.
- Use `refresh_tokens` table.
- Use polymorphic refresh-token ownership.
- Rotate refresh token on every refresh.
- Detect refresh-token reuse.
- Enforce one active session.
- Use CSRF protection for refresh.
- Validate exact admin Origin.
- Use `password_resets` table.
- Use polymorphic reset ownership.
- Recovery code is six digits.
- Recovery code expires after ten minutes.
- Maximum verification attempts is five.
- Reset token expires after ten minutes.
- Store recovery code as secure password hash.
- Store reset token as SHA-256 hash.
- Send recovery mail synchronously.
- Send mail only after database commit.
- Do not create authentication mail Jobs.
- Do not create authentication Queue processing.
- Do not create cleanup Commands.
- Do not require Cron.
- Use opportunistic cleanup.
- Avatar absent or empty keeps current avatar.
- Avatar file replaces current avatar.
- Do not accept avatar path from frontend.
- Do not support remove-avatar operation.
- ProfileController owns show and update.
- Password change revokes all sessions.
- Password reset revokes all sessions.
- Do not log secrets.
- Do not expose internal failure details.
- Test every route through Pest API tests.
