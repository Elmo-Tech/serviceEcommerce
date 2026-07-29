# Prompt: Final Completeness and Traceability Correction for Feature 001

Use this prompt with Codex **before running `/speckit.implement`**.

```text
Perform a final, documentation-only completeness, precision, and traceability
correction for:

Feature 001 — Identity and Authentication

The approved authentication architecture is already decided:

```text
React Admin on Hostinger Shared Hosting
-> direct HTTPS requests with strict CORS
-> Laravel Backend API on a separate domain and hosting provider
```

The transport decision is final:

```text
Access Token:
- Laravel Sanctum Personal Access Token
- returned in JSON
- sent as Authorization: Bearer
- 900-second lifetime
- Web storage: runtime memory only

Refresh Token:
- custom opaque rotating token
- returned in JSON at login and refresh
- submitted only in the refresh JSON body
- 2592000-second lifetime
- persisted only as SHA-256 hash
- Web storage: sessionStorage only
- future Mobile storage: OS-backed secure storage
```

Feature 001 no longer uses:

```text
authentication cookies
HttpOnly Refresh Cookie
CSRF cookie/header
SameSite
cookie Domain or Path
Set-Cookie
same-origin Proxy/BFF
Cloudflare Worker
refresh Origin middleware
legacy cookie migration
```

The current rewritten artifacts correctly describe the new transport, but they
are too abbreviated and have lost important requirements from the original
Feature 001 contract.

This task must restore all missing business, security, persistence, API,
localization, recovery, file, concurrency, testing, and traceability details
without restoring the rejected cookie/proxy architecture.

This task is DOCUMENTATION-ONLY.

Do not modify Laravel application source code.
Do not modify React source code.
Do not create migrations.
Do not create Postman JSON unless the planning workflow explicitly requires it.
Do not run `/speckit.implement`.
Do not mark implementation tasks complete.
Do not adapt approved requirements to existing code.
Do not reintroduce cookies, CSRF, Origin middleware, Proxy/BFF, or Cloudflare.
Do not simplify the Feature specification merely to keep documents short.

# 1. Read the Complete Authority Chain

Before editing, read:

```text
.specify/memory/constitution.md
AGENTS.md

docs/00-project-overview/project-overview.md
docs/01-architecture/backend-architecture.md

docs/02-standards/api-standards.md
docs/02-standards/code-standards.md
docs/02-standards/database-standards.md
docs/02-standards/localization-standards.md
docs/02-standards/authentication-standards.md
docs/02-standards/authorization-standards.md
docs/02-standards/security-standards.md
docs/02-standards/testing-standards.md
docs/02-standards/file-storage-standards.md

docs/features/001-identity-authentication.md

specs/001-identity-authentication/spec.md
specs/001-identity-authentication/checklists/requirements.md
specs/001-identity-authentication/plan.md
specs/001-identity-authentication/research.md
specs/001-identity-authentication/data-model.md
specs/001-identity-authentication/contracts/openapi.yaml
specs/001-identity-authentication/quickstart.md
specs/001-identity-authentication/tasks.md

docs/deployment/separate-domain-admin-auth-cutover.md

git history or prior approved revisions of Feature 001, where available
```

Use prior revisions only to recover business/security requirements that were
accidentally removed.

Do not recover obsolete transport requirements involving:

```text
cookies
CSRF
Origin-gated refresh
same-origin proxy
server-only backend origin
legacy cookie cleanup
```

Apply the normal precedence rules.

If a shared standard still mandates the rejected cookie architecture, do not
silently work around it in lower-level files. Make the narrowest explicit
standard amendment required by the already-approved direct-token decision and
report it.

# 2. Freeze the New Architecture

All final artifacts must agree on:

```text
Admin Frontend:
https://admin.frontend-example.com

Backend API:
https://api.backend-example.net/api/v1

Browser:
direct HTTPS request to the Backend API

Frontend API configuration:
VITE_API_BASE_URL=https://api.backend-example.net/api/v1
or the equivalent browser-public variable used by the actual frontend build
system

CORS:
exact Frontend origin
supports_credentials=false

Proxy/BFF:
none

Authentication cookies:
none

CSRF:
not used
```

The Backend API origin is intentionally browser-visible.

Do not describe public visibility of an API base URL as a secret leakage.

Actual secrets remain:

```text
passwords
Access Tokens
Refresh Tokens
recovery codes
reset tokens
hash input material
mail credentials
database credentials
```

# 3. Preserve the Complete Original Feature Scope

The final documents must preserve all approved Feature 001 behavior unrelated
to the removed cookie/proxy transport.

## 3.1 Actor and Routes

Preserve:

```text
only the Super Admin is authenticated in the MVP
customers remain unauthenticated guest/domain records
no customer auth
no registration
no public auth
no MFA
no social login
no email verification
no session-management UI
no device management
```

Preserve exactly these nine operations across eight unique paths:

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

Multipart transport compatibility may use:

```http
POST /api/v1/admin/auth/profile
_method=PATCH
```

but it remains documentation for the canonical PATCH operation and must not be
counted as a tenth operation.

Prohibit:

```text
/api/v1/auth/*
registration
me
logout-all
sessions
email-verification
verification-resend
alternate refresh routes
mobile-only auth routes in Feature 001
```

## 3.2 Middleware

Protected Feature 001 routes must use:

```text
auth:sanctum
-> EnsureUserIsAdministrator
-> EnsureAdminIsActive
-> endpoint
```

If a later approved endpoint requires a permission:

```text
auth:sanctum
-> EnsureUserIsAdministrator
-> EnsureAdminIsActive
-> permission middleware
-> endpoint
```

Feature 001 self-service routes do not invent authentication permissions.

Refresh uses:

```text
throttle
-> RefreshTokenRequest
-> RefreshAdminSessionAction
```

Do not include:

```text
ValidateAdminOrigin
ValidateAdminCsrfToken
web/session CSRF middleware
```

## 3.3 User Type, Role, Active State

Preserve the independence of:

```text
Sanctum authentication
administrator UserType
is_active
Spatie role
Spatie permissions
```

Rules:

- `users.type = ADMIN` identifies administrator type;
- type is not a role;
- role is not an active-state check;
- `super-admin` remains the single initial role;
- inactive administrators cannot login, refresh, or access protected routes;
- profile output uses one `role` string and a deterministic permissions array;
- client input cannot mutate type, active state, role, permissions, or token
  ownership.

# 4. Restore Exact Input and Validation Rules

Restore detailed input contracts rather than saying “according to the approved
contract”.

## 4.1 Email

For every relevant flow:

```text
trim whitespace
normalize using mb_strtolower
validate the normalized email
use the same normalization for lookup and uniqueness
```

Do not mutate passwords or token bytes during normalization.

## 4.2 Password and Token Material

Never:

```text
trim
lowercase
uppercase
normalize Unicode
silently transform
```

the following:

```text
password
currentPassword
newPassword
accessToken
refreshToken
recovery code
resetToken
```

Request classes must use strict field allow-lists and reject undocumented
sensitive fields.

## 4.3 Password Policy

Restore the approved password policy:

```text
minimum 10 characters
at least one lowercase letter
at least one uppercase letter
at least one number
at least one symbol
confirmation required
new password must differ from the current password where applicable
```

Provisioning, change-password, and reset-password must use the same approved
policy unless an authoritative standard explicitly defines a difference.

# 5. Restore Exact Limits and Lifetimes

Every artifact must use the exact values below:

```text
Access Token lifetime:
900 seconds

Refresh Token lifetime:
2592000 seconds

Recovery code lifetime:
600 seconds

Reset Token lifetime:
600 seconds

Recovery code maximum failed attempts:
5

Forgot-password resend cooldown:
60 seconds
```

Restore request limits:

```text
Login:
5 requests/minute using normalized email + requester IP

Refresh:
10 requests/minute using requester IP plus safely available session context

Password change:
5 requests/minute per authenticated administrator

Forgot password:
5 requests/minute using normalized email + requester IP
```

If the governing Feature reference defines additional exact throttles, preserve
them.

Do not replace an exact limit with vague wording such as “rate limited”.

# 6. Restore Provisioning Requirements

The final spec, plan, tasks, quickstart, and tests must cover:

```text
environment-backed Super Admin name/email/password
no default production credential
safe failure when required production values are missing/invalid
normalized unique email
secure password hashing
administrator type
active state
super-admin role assignment
idempotent reruns
no duplicate user
no duplicate role
no password overwrite on rerun
no identity drift
role remains assigned
```

Identify the expected implementation files:

```text
database/seeders/RolesAndPermissionsSeeder.php
database/seeders/SuperAdminSeeder.php
config/services.php or the approved auth/provisioning config
.env.example
```

# 7. Restore Login Requirements

Login must:

- accept only `email` and `password`;
- normalize only the email;
- keep password bytes unchanged;
- enforce the login throttle;
- use the same generic `401 INVALID_CREDENTIALS` for unknown email and wrong
  password;
- return `403 USER_INACTIVE` for an identified inactive administrator;
- verify administrator type independently from active state and role;
- preserve/ensure the Super Admin role;
- atomically revoke previous Access and Refresh Tokens;
- leave one active Admin session;
- issue:
  - one 900-second Sanctum Access Token;
  - one 2592000-second opaque Refresh Token;
- store only hashes;
- return both plain tokens exactly once in the successful JSON response;
- emit no authentication `Set-Cookie`;
- log no credentials or token values.

Exact successful data:

```json
{
  "accessToken": "plain-sanctum-token",
  "refreshToken": "plain-opaque-refresh-token",
  "tokenType": "Bearer",
  "tokenExpiresIn": 900,
  "refreshTokenExpiresIn": 2592000,
  "profile": {
    "name": "Administrator",
    "email": "admin@example.com",
    "avatar": null,
    "role": "super-admin",
    "permissions": []
  }
}
```

# 8. Restore Refresh Requirements

## 8.1 Request

Refresh accepts only:

```json
{
  "refreshToken": "opaque-refresh-token"
}
```

Rules:

- JSON body only;
- unknown fields rejected;
- token bytes remain unchanged;
- no cookie;
- no query string;
- no URL;
- no Origin requirement;
- no CSRF;
- no alternate custom token header unless explicitly approved later;
- no request-body logging.

## 8.2 Structural vs Credential Failure

Use the governing API validation standard consistently:

```text
missing, null, wrong-type, empty, or structurally invalid refreshToken:
422 VALIDATION_ERROR

structurally valid but unusable token:
401 REFRESH_TOKEN_INVALID
```

The unusable-token contract includes:

```text
unknown hash
expired token
revoked token
rotated predecessor
ownerless token
deleted owner
non-User owner
non-admin owner
inactive owner
wrong owner state
invalid family
invalid rotation link
invalid successor relationship
```

Do not leak the internal reason.

## 8.3 Processing Order

```text
rate limit
-> RefreshTokenRequest
-> validate basic token format
-> SHA-256 hash
-> begin MySQL transaction
-> select token row using lockForUpdate()
-> revalidate token and owner state under lock
-> rotate or reject
```

## 8.4 Success

Successful refresh must:

- allow at most one winner under concurrency;
- revoke/rotate the predecessor;
- revoke the current Sanctum Access Token set according to the one-session
  rule;
- issue one new 900-second Access Token;
- issue one new 2592000-second Refresh Token;
- preserve the Refresh Token family;
- link predecessor to successor;
- return both new tokens exactly once;
- return no profile;
- set no cookie.

Exact success data:

```json
{
  "accessToken": "new-sanctum-token",
  "refreshToken": "new-opaque-refresh-token",
  "tokenType": "Bearer",
  "tokenExpiresIn": 900,
  "refreshTokenExpiresIn": 2592000
}
```

## 8.5 Reuse Detection

Presenting a rotated predecessor after structural validation must:

- identify reuse safely when the owner can be resolved;
- revoke every Access Token for that administrator;
- revoke every remaining Refresh Token;
- record a safe internal reuse event;
- return `401 REFRESH_TOKEN_INVALID`;
- never expose the reuse reason publicly;
- never log token material.

# 9. Restore Logout, Password Change, and Password Reset Revocation

Logout remains protected by Bearer authentication and must:

- revoke every Access and Refresh Token for the administrator;
- return the approved success envelope;
- set no cookie;
- clear no server cookie;
- require no Refresh Token body.

Password change must:

- use the exact protected middleware order;
- accept only the approved current/new/confirmation fields;
- verify current password;
- return `422 CURRENT_PASSWORD_INVALID` for incorrect current password;
- enforce policy and difference from the current password;
- update password transactionally;
- invalidate active password-reset workflows;
- revoke every Access and Refresh Token;
- return no new token.

Password reset must:

- validate normalized email;
- validate one-time reset token;
- enforce password policy and confirmation;
- lock and consume the workflow atomically;
- allow at most one concurrent success;
- update the password;
- revoke every Access and Refresh Token;
- invalidate relevant reset workflows;
- return no new auth token;
- use `422 PASSWORD_RESET_TOKEN_INVALID` for a structurally valid but unusable
  reset workflow unless a higher-authority standard explicitly says otherwise.

# 10. Restore Exact Profile and Avatar Requirements

Profile response contains exactly:

```text
name
email
avatar
role
permissions
```

Do not return:

```text
id
type
isActive
roles array
avatarUrl alternate field
avatarDisk
avatarPath
password metadata
token metadata
timestamps
```

Profile update accepts only:

```text
name
avatar
_method for multipart compatibility
```

Name:

```text
trimmed
1-150 characters
```

Avatar:

```text
extensions: jpg, jpeg, png, webp
detected MIME must match an approved image MIME
maximum size: 2 MB
server-generated cryptographically random filename
approved configurable public disk
response exposes approved public URL only
```

Behavior:

- absent avatar preserves the current avatar;
- empty-string avatar preserves the current avatar where the governing
  contract requires it;
- non-empty string, URL, path, disk name, raw path, or removal flag is
  rejected;
- no delete-only avatar behavior.

Compensation:

1. validate and store the new file;
2. transactionally update `avatar_disk` and `avatar_path`;
3. if the database update fails, delete the newly stored file;
4. after commit, synchronously attempt deletion of the old file;
5. old-file deletion failure does not roll back the successful profile update;
6. log only safe metadata;
7. do not dispatch a cleanup Job.

Profile update does not rotate or revoke the current session.

# 11. Restore Complete Password-Recovery Workflow

## 11.1 Forgot Password

Forgot-password request must:

- accept normalized email;
- apply the exact throttle;
- return the same enumeration-safe `200` response for eligible, unknown, and
  inactive emails;
- create no workflow and send no email for unknown/inactive targets;
- enforce the 60-second resend cooldown;
- create a cryptographically random six-digit code;
- persist only a secure password hash of the code;
- set 600-second expiry;
- invalidate prior usable workflows;
- commit before mail;
- send localized mail synchronously;
- use the request locale;
- send only:
  - the six-digit code;
  - ten-minute expiry;
  - ignore-if-unrequested guidance;
- include no password, auth token, reset token, privileged link, or sensitive
  internal data.

If transport fails:

- invalidate only the newly committed workflow in a bounded follow-up update;
- return `503 MAIL_SERVICE_UNAVAILABLE`;
- log no code or secret.

## 11.2 Concurrent Forgot-Password Creation

Restore the stable-row locking strategy:

1. normalize email and resolve an eligible active administrator;
2. begin a short MySQL transaction;
3. lock the stable `users` row using `lockForUpdate()`;
4. re-check eligibility and the 60-second cooldown under the lock;
5. invalidate every previous usable workflow;
6. create one new workflow;
7. commit;
8. send one synchronous mail after commit;
9. if transport fails, invalidate only the new workflow.

Required result:

```text
two concurrent first-time requests leave at most one usable new workflow and
at most one mail transport invocation for the serialized outcome
```

Do not rely only on locking existing `password_resets` rows because the first
request may have no row.

## 11.3 Code Verification

Code verification must:

- accept only normalized email and six-digit code;
- lock the workflow row;
- validate owner, active state, expiry, consumption, verification state, and
  attempt count;
- compare the code using the approved password hasher;
- increment failed attempts atomically;
- consume the workflow on the fifth failed attempt;
- return the same generic `422 PASSWORD_RESET_CODE_INVALID` for wrong,
  expired, consumed, attempt-limited, already-verified, inactive-owner, or
  otherwise unusable code state;
- issue at most one reset token under concurrency;
- make the recovery code unusable on success;
- generate a high-entropy reset token;
- store only its unique SHA-256 hash;
- set a 600-second expiry;
- return the plain reset token exactly once.

Exact success data:

```json
{
  "resetToken": "plain-one-time-reset-token",
  "resetTokenExpiresIn": 600
}
```

The reset token is held in frontend runtime memory only and must never be
stored in localStorage, sessionStorage, IndexedDB, cookies, URLs, mail, logs,
analytics, or persistent state.

# 12. Restore Localization and Response Safety

All auth messages must support:

```text
ar
en
```

Rules:

- resolve `Accept-Language` before validation;
- Arabic is default;
- English is fallback;
- stable codes, request keys, route names, role names, permission names, and
  enum values remain English;
- password-recovery email uses the request locale;
- every translation key required by the Feature exists in both locales.

Response headers required by the governing standards must remain explicit:

```text
Content-Language: resolved ar or en
Vary: Accept-Language
Cache-Control: no-store, private
Pragma: no-cache
```

Apply them consistently to every authentication response where the standards
require them:

```text
success
validation errors
authentication errors
inactive account
refresh failure
rate limit
mail failure
safe internal error
```

Only add other security headers when they are explicitly required by the
governing standards.

Do not invent an undocumented response-body metadata field.

# 13. Restore Stable Error Contracts

At minimum preserve and document:

```text
INVALID_CREDENTIALS
USER_INACTIVE
UNAUTHENTICATED
VALIDATION_ERROR
RATE_LIMITED
REFRESH_TOKEN_INVALID
CURRENT_PASSWORD_INVALID
PASSWORD_RESET_CODE_INVALID
PASSWORD_RESET_TOKEN_INVALID
MAIL_SERVICE_UNAVAILABLE
```

Use the exact approved HTTP statuses from the authoritative Feature reference
and shared API standards.

Expected key mappings include:

```text
INVALID_CREDENTIALS -> 401
USER_INACTIVE -> 403
UNAUTHENTICATED -> 401
VALIDATION_ERROR -> 422
RATE_LIMITED -> 429
REFRESH_TOKEN_INVALID -> 401
CURRENT_PASSWORD_INVALID -> 422
PASSWORD_RESET_CODE_INVALID -> 422
PASSWORD_RESET_TOKEN_INVALID -> 422
MAIL_SERVICE_UNAVAILABLE -> 503
```

Do not reintroduce:

```text
ORIGIN_NOT_ALLOWED
CSRF_TOKEN_MISMATCH
```

Do not use `401` for a recovery code or reset-token workflow when the approved
contract is `422`.

# 14. Restore Safe Logging and Secret Handling

No persistence, response, log, exception context, monitoring payload, analytics
event, URL, or console output may contain:

```text
plaintext password
Access Token
Refresh Token
recovery code
reset token
Cookie header
Authorization header
sensitive request body
raw avatar path
incoming token hash material
mail credentials
stack trace in production API output
SQL/internal exception detail
```

The focused security logger may record only approved safe metadata, such as:

```text
request ID
safe event name
administrator ID when safely resolved
status
duration
safe reason category
```

No raw query string if it may contain secret input.

# 15. Rebuild `spec.md` Without Losing Detail

Update:

```text
specs/001-identity-authentication/spec.md
```

The current shortened specification must be expanded.

Requirements:

- preserve the direct JSON token architecture;
- restore detailed User Stories and acceptance scenarios;
- restore all edge cases;
- restore complete functional requirements;
- restore API, authorization/trust, data-integrity, verification, and success
  criteria;
- preserve exact values, limits, fields, statuses, and prohibitions;
- preserve the one-active-session rule;
- add Web and future Mobile storage requirements;
- add XSS accepted-risk and compensating-control requirements;
- add exact direct-CORS requirements;
- keep no cookies/CSRF/Proxy.

Use stable requirement IDs where the meaning is unchanged.

Do not silently reuse an old ID whose meaning previously described cookies,
CSRF, Origin middleware, or Proxy/BFF.

Create a coherent new ID sequence or clearly document superseded IDs.

Every requirement must be:

```text
atomic
testable
unambiguous
traceable
```

Update status:

```text
Ready for Reimplementation
```

# 16. Rebuild `data-model.md` With Exact Schema Detail

Update:

```text
specs/001-identity-authentication/data-model.md
```

Do not use vague phrases such as:

```text
unique or effectively unique
related fields
approved indexes
```

## 16.1 users

Document at minimum:

```text
id BIGINT UNSIGNED primary key auto increment
name VARCHAR(150) not null
email VARCHAR(255) not null unique
email_verified_at nullable timestamp retained only for compatibility
password VARCHAR(255) not null
type TINYINT UNSIGNED not null default approved ADMIN value
is_active BOOLEAN not null default true
avatar_disk VARCHAR(50) nullable
avatar_path VARCHAR(500) nullable
created_at
updated_at
```

Document:

- normalization;
- casts;
- relationships;
- both avatar metadata fields remain consistent;
- no customer-auth fields;
- no MFA/device/login-history fields;
- whether `remember_token` is absent according to the governing schema.

## 16.2 personal_access_tokens

Document:

- installed Sanctum migration is authoritative;
- token hash only;
- token name;
- `admin:access` ability;
- exact `expires_at = issuance + 900 seconds`;
- revocation events;
- one-active-session behavior.

## 16.3 refresh_tokens

Document exact fields:

```text
id BIGINT UNSIGNED primary key
tokenable_type VARCHAR(255) not null
tokenable_id BIGINT UNSIGNED not null
token_hash CHAR(64) not null UNIQUE
family_id CHAR(36) not null
expires_at TIMESTAMP not null
revoked_at TIMESTAMP nullable
rotated_to_token_id BIGINT UNSIGNED nullable
revocation_reason VARCHAR(50) nullable
created_at
updated_at
```

Document exact indexes:

```text
UNIQUE(token_hash)
INDEX(tokenable_type, tokenable_id)
INDEX(family_id)
INDEX(expires_at)
INDEX(revoked_at)
```

Document self-reference behavior and `nullOnDelete` only if it remains the
approved governing design.

Restore exact backed enum values:

```text
login_replaced
refreshed
logout
password_changed
password_reset
user_inactive
refresh_reuse_detected
```

Document:

- 64 random bytes encoded URL-safely;
- SHA-256 lookup;
- same-family successor validation;
- no self-reference;
- state transitions;
- reuse behavior;
- row locking;
- no plaintext persistence.

## 16.4 password_resets

Document exact fields:

```text
id BIGINT UNSIGNED primary key
resettable_type VARCHAR(255) not null
resettable_id BIGINT UNSIGNED not null
email_normalized VARCHAR(255) not null
code_hash VARCHAR(255) not null
code_expires_at TIMESTAMP not null
verification_attempts TINYINT UNSIGNED not null default 0
verified_at TIMESTAMP nullable
reset_token_hash CHAR(64) nullable UNIQUE
reset_token_expires_at TIMESTAMP nullable
consumed_at TIMESTAMP nullable
created_at
updated_at
```

Document exact indexes and full workflow states:

```text
CODE_ACTIVE
RESET_TOKEN_ACTIVE
CONSUMED
EXPIRED/UNUSABLE by derived state
```

Restore concurrency rules for:

```text
forgot-password creation
code verification
password reset
```

## 16.5 Avatar and Transport Review

Document:

- avatar filesystem state and compensation;
- direct-token transport requires no cookie, CSRF, proxy, JWT, or browser
  session table;
- no Web/Mobile discriminator column;
- no plaintext token persistence.

# 17. Rebuild `plan.md` With Full Technical Detail

Update:

```text
specs/001-identity-authentication/plan.md
```

Correct the runtime statement:

- do not hard-code PHP 8.3 unless confirmed by the repository lock files;
- use “Laravel 13 with the compatible PHP version pinned by composer.lock”;
- if no lock file exists, task generation must verify the installed supported
  version before using version-specific APIs.

Restore:

- constitution pre/post design checks;
- technical context;
- performance/scale;
- exact limits and lifetimes;
- conventional Laravel architecture;
- Request -> Controller -> Action -> Service -> Eloquent flow;
- transaction boundaries;
- file-compensation boundary;
- localization;
- Postman as an implementation documentation deliverable;
- OpenAPI validation;
- MySQL concurrency testing;
- traceability strategy;
- Definition of Done.

Expected Backend structure includes:

```text
app/Actions/Auth/
app/Enums/
app/Http/Controllers/Api/V1/Admin/Auth/
app/Http/Middleware/EnsureUserIsAdministrator.php
app/Http/Middleware/EnsureAdminIsActive.php
app/Http/Requests/Api/V1/Admin/Auth/
app/Http/Resources/Api/V1/Admin/Auth/
app/Mail/AdminPasswordResetCodeMail.php
app/Models/User.php
app/Models/RefreshToken.php
app/Models/PasswordReset.php
app/Services/Auth/AccessTokenService.php
app/Services/Auth/RefreshTokenService.php
app/Services/Auth/AdminSessionRevocationService.php
app/Services/Auth/ForgotPasswordCodeService.php
app/Services/Auth/ResetTokenService.php
app/Support/Auth/AuthenticationSecurityLogger.php
routes/api.php
routes/api/v1/auth.php
config/cors.php
config/sanctum.php
config/services.php
lang/ar/
lang/en/
tests/
postman/
```

Explicitly exclude:

```text
AdminAuthCookieService
AdminCsrfService
ValidateAdminOrigin
ValidateAdminCsrfToken
Proxy/BFF classes
authentication Jobs
authentication Commands
Cron/scheduler auth work
Redis requirement
```

Do not claim external React files exist inside the Laravel repository.

# 18. Correct `research.md`

Update:

```text
specs/001-identity-authentication/research.md
```

Keep the new transport decision but restore research on:

```text
runtime/bootstrap authority
Sanctum + custom Refresh Token model
rotation and row locking
reuse detection
one-active-session enforcement
strict CORS
Web sessionStorage accepted risk
CSP/XSS compensating controls
future Mobile secure storage
password-recovery concurrency
mail-after-commit strategy
avatar compensation
localization
MySQL verification
```

Record rejected alternatives:

```text
HttpOnly Refresh Cookie for this approved architecture
Proxy/BFF
Cloudflare Worker requirement
cookie-based Sanctum SPA auth
Refresh Token in localStorage
Access Token in persistent storage
JWT replacement
Passport/OAuth2 without an OAuth2 requirement
Redis lock
SQLite-only concurrency evidence
```

No open research question may remain.

# 19. Correct `openapi.yaml` Completely

Update:

```text
specs/001-identity-authentication/contracts/openapi.yaml
```

Preserve:

```text
OpenAPI 3.1
9 operations
8 unique paths
server URL https://api.backend-example.net/api/v1
paths relative to the server base or full /api/v1 paths, but not a mixture
unique operationId values
Bearer security on protected routes
```

## 19.1 Closed Schemas

Use `additionalProperties: false` where the governing contract is closed.

Do not leave:

```yaml
data: {}
```

as the only type definition for endpoint-specific success responses.

Create explicit success schemas such as:

```text
LoginSuccess
RefreshSuccess
ProfileSuccess
NullDataSuccess
VerifyForgotPasswordCodeSuccess
```

## 19.2 Login

Login success must contain exact typed `LoginData`, including both tokens and
profile.

Document representative errors and stable codes.

## 19.3 Refresh

Refresh request must require exact `refreshToken`.

Refresh success must contain exact typed `RefreshData`.

Document:

```text
422 VALIDATION_ERROR for structural request failure
401 REFRESH_TOKEN_INVALID for a structurally valid unusable token
429 RATE_LIMITED
```

No Origin, CSRF, cookie, or Set-Cookie parameter/response may remain.

## 19.4 Verify Recovery Code

Create:

```yaml
VerifyForgotPasswordCodeData:
  type: object
  additionalProperties: false
  required:
    - resetToken
    - resetTokenExpiresIn
  properties:
    resetToken:
      type: string
      minLength: 1
    resetTokenExpiresIn:
      type: integer
      const: 600
```

The `200` response must use a typed success envelope containing this data.

Use:

```text
422 PASSWORD_RESET_CODE_INVALID
```

for the approved generic unusable-code contract.

Do not use an untyped generic success envelope.

## 19.5 Forgot Password

Document:

```text
200 enumeration-safe accepted response
422 validation error
429 rate limited
503 MAIL_SERVICE_UNAVAILABLE
```

## 19.6 Reset Password

Document:

```text
200 approved null-data success
422 validation error
422 PASSWORD_RESET_TOKEN_INVALID for structurally valid unusable workflow
429 rate limited when required
```

Do not describe cookie clearing.

## 19.7 Profile Multipart

Represent JSON and multipart contracts accurately.

Do not model an uploaded avatar as an ordinary JSON string.

Document method spoofing as transport compatibility without adding an
operation.

## 19.8 Reusable Headers

Create reusable components:

```yaml
components:
  headers:
    ContentLanguage:
    VaryAcceptLanguage:
    CacheControlNoStore:
    PragmaNoCache:
```

Semantic values:

```text
Content-Language: ar or en
Vary: Accept-Language
Cache-Control: no-store, private
Pragma: no-cache
```

Apply the header references consistently according to governing standards.

## 19.9 Reusable Responses

Use reusable responses where helpful, including:

```text
ValidationError
InvalidCredentials
UserInactive
Unauthenticated
RateLimited
MailServiceUnavailable
RefreshTokenInvalid
PasswordResetCodeInvalid
PasswordResetTokenInvalid
```

Examples must show exact codes and envelope shape.

## 19.10 No Obsolete Transport

Search the final OpenAPI and ensure it contains no active:

```text
cookie parameter
Set-Cookie
X-CSRF-TOKEN
CSRF_TOKEN_MISMATCH
ORIGIN_NOT_ALLOWED
Origin-required refresh input
Proxy/BFF server
SameSite
HttpOnly
```

# 20. Rebuild `quickstart.md`

Update:

```text
specs/001-identity-authentication/quickstart.md
```

Restore full validation sections for:

```text
prerequisites
environment safety
dependency/version verification
MySQL setup
Super Admin idempotency
OpenAPI validation
Postman validation
focused tests
full tests
login
refresh
concurrency
logout
profile/avatar
password change
forgot password
mail failure
code verification
reset
localization
response headers
CORS
frontend storage
future Mobile client
leakage
Pint
Larastan/PHPStan
staging
```

Exact CORS staging checks:

```text
approved Frontend Origin preflight succeeds
unapproved Origin receives no CORS approval
supports_credentials=false
no authentication Set-Cookie
Authorization is allowed
Content-Type is allowed
Accept-Language is allowed
non-browser login/refresh works without Origin
```

Exact Web checks:

```text
VITE_API_BASE_URL points directly to Backend
withCredentials=false
Access Token remains memory-only
Refresh Token exists only in sessionStorage
same-tab reload refreshes the Access Token
successful refresh replaces stored Refresh Token
failed refresh clears all auth state
logout clears all auth state
closing the tab removes sessionStorage Refresh Token
no localStorage/IndexedDB/cookie token storage
strict CSP baseline
no token in console/analytics/error reporting
```

Restore all precise recovery and avatar scenarios.

# 21. Regenerate `tasks.md` as a Full Implementation Plan

Completely regenerate:

```text
specs/001-identity-authentication/tasks.md
```

The current task list is too short and insufficiently traceable.

## 21.1 Task Format

Use:

```text
[ID] [P?] [US#?] Description with exact owning file paths and requirement IDs
```

Every implementation, API, security, persistence, localization, configuration,
documentation, frontend-contract, and verification task must cite applicable:

```text
FR-*
API-*
AUTH-*
DATA-*
VER-*
SC-*
```

No task may cite an unrelated ID.

All checkboxes remain unchecked.

## 21.2 Specification Is Frozen During Implementation

Remove tasks that say:

```text
synchronize spec to implementation
update spec to match code
rewrite OpenAPI after implementation behavior is chosen
```

The implementation authority direction is:

```text
constitution/shared standards/Feature reference
-> spec.md
-> openapi.yaml
-> plan/data-model/research
-> tasks.md
-> implementation
-> tests
-> Postman evidence
```

During `/speckit.implement`:

- treat approved specification artifacts as read-only;
- correct code/tests when they conflict with the specs;
- if a real authoritative contradiction is discovered, stop and report it;
- do not silently edit specs to accommodate code.

Tasks may update:

```text
task completion checkboxes
implementation evidence links
Postman implementation deliverables
deployment implementation notes
```

only where allowed.

## 21.3 Required Phases

Generate detailed phases for:

1. governance/version/configuration;
2. migrations and persistence;
3. models/enums/factories/seeders;
4. shared API response/localization/security logging;
5. Access Token service;
6. Refresh Token service;
7. session revocation;
8. login;
9. refresh;
10. logout;
11. profile/avatar;
12. password change;
13. forgot-password request;
14. code verification;
15. password reset;
16. CORS;
17. external React implementation contract;
18. future Mobile contract verification;
19. OpenAPI/Postman;
20. automated backend tests;
21. concurrency tests;
22. quality gates;
23. staging and security review.

## 21.4 Exact Files

Tasks must name expected files, including:

```text
app/Actions/Auth/LoginAdminAction.php
app/Actions/Auth/RefreshAdminSessionAction.php
app/Actions/Auth/LogoutAdminAction.php
app/Actions/Auth/UpdateAdminProfileAction.php
app/Actions/Auth/ChangeAdminPasswordAction.php
app/Actions/Auth/SendForgotPasswordCodeAction.php
app/Actions/Auth/VerifyForgotPasswordCodeAction.php
app/Actions/Auth/ResetForgottenPasswordAction.php

app/Enums/UserType.php
app/Enums/RefreshTokenRevocationReason.php

app/Http/Controllers/Api/V1/Admin/Auth/*
app/Http/Requests/Api/V1/Admin/Auth/LoginRequest.php
app/Http/Requests/Api/V1/Admin/Auth/RefreshTokenRequest.php
app/Http/Requests/Api/V1/Admin/Auth/UpdateProfileRequest.php
app/Http/Requests/Api/V1/Admin/Auth/ChangePasswordRequest.php
app/Http/Requests/Api/V1/Admin/Auth/ForgotPasswordRequest.php
app/Http/Requests/Api/V1/Admin/Auth/VerifyForgotPasswordCodeRequest.php
app/Http/Requests/Api/V1/Admin/Auth/ResetPasswordRequest.php

app/Http/Resources/Api/V1/Admin/Auth/AdminProfileResource.php
app/Http/Resources/Api/V1/Admin/Auth/AuthenticatedAdminResource.php
app/Http/Resources/Api/V1/Admin/Auth/AdminRefreshTokenResource.php
app/Http/Resources/Api/V1/Admin/Auth/VerifiedResetTokenResource.php

app/Http/Middleware/EnsureUserIsAdministrator.php
app/Http/Middleware/EnsureAdminIsActive.php
app/Http/Middleware/ResolveApiLocale.php

app/Mail/AdminPasswordResetCodeMail.php

app/Models/User.php
app/Models/RefreshToken.php
app/Models/PasswordReset.php

app/Services/Auth/AccessTokenService.php
app/Services/Auth/RefreshTokenService.php
app/Services/Auth/AdminSessionRevocationService.php
app/Services/Auth/ForgotPasswordCodeService.php
app/Services/Auth/ResetTokenService.php

app/Support/Auth/AuthenticationSecurityLogger.php
app/Support/Api/ApiResponse.php or the approved shared response helper

routes/api.php
routes/api/v1/auth.php
config/cors.php
config/sanctum.php
config/services.php
bootstrap/app.php

database/migrations/*
database/factories/*
database/seeders/RolesAndPermissionsSeeder.php
database/seeders/SuperAdminSeeder.php

lang/ar/auth.php
lang/ar/validation.php
lang/ar/mail.php
lang/en/auth.php
lang/en/validation.php
lang/en/mail.php

tests/Feature/Api/V1/Admin/Auth/*
tests/Concurrency/Authentication/*
tests/Architecture/AuthenticationArchitectureTest.php
tests/Unit/Auth/*

postman/Service-Commerce.postman_collection.json
postman/Service-Commerce.local.postman_environment.json.example
```

Do not create a duplicate class when an equivalent approved file already
exists.

## 21.5 Frontend Tasks Must Be Implementation Tasks

External frontend tasks must say:

```text
Implement in the owning React repository after repository inspection
```

not merely:

```text
Document
```

They must cover:

```text
absolute API base
withCredentials=false
Access Token memory store
Refresh Token sessionStorage helper
Authorization interceptor
single refresh retry lock
reload bootstrap
rotation replacement
logout/failure clearing
CSP
no token logging
frontend tests
```

Do not pretend the files live in Laravel.

## 21.6 Complete Test Inventory

Tasks must require:

```text
Super Admin seeder idempotency
login success/failure/inactive/rate limit
Access Token 900-second expiry
Refresh Token 2592000-second expiry
Login returns both tokens
No auth Set-Cookie
one active session
refresh 422 structural failures
refresh 401 unusable-token failures
rotation
reuse revocation
single-winner concurrent refresh
logout all-session revocation
protected middleware order
profile exact shape
profile allow-list
avatar type/size
avatar DB rollback compensation
old avatar deletion failure
password policy
current password failure
password-change revocation
forgot enumeration safety
resend cooldown
mail after commit
mail failure invalidation
concurrent forgot-password creation
five failed code attempts
code 600-second expiry
single-winner concurrent code verification
reset token 600-second expiry
single-winner concurrent reset
password-reset revocation
Arabic default
English fallback
localized mail
response headers
exact CORS
supports_credentials=false
approved preflight
unapproved Origin CORS behavior
non-browser no-Origin login/refresh
no cookie/CSRF/Origin middleware
no token leakage
9 operations
8 unique paths
no alternate auth routes
no auth Jobs/Commands/Cron/scheduler
no Redis requirement
```

Use real MySQL for transaction/locking tests.

No SQLite-only concurrency evidence.

No `sleep()`-based race proof.

Do not mock Eloquent models in API Feature Tests.

## 21.7 Parallelism

Mark `[P]` only when tasks:

- have satisfied prerequisites;
- touch independent files;
- do not modify the same service, route, migration, or OpenAPI section.

Dependencies and the Parallel Opportunities section must match actual file
ownership.

# 22. Correct Postman Planning

Postman environment uses:

```text
baseUrl=https://api.backend-example.net/api/v1
adminEmail
adminPassword
accessToken
refreshToken
resetToken
```

Remove:

```text
adminFrontendOrigin
csrfToken
cookie jar dependency
```

The collection contains exactly nine canonical operations.

Rules:

- Login extracts Access and Refresh Tokens to current-run variables only.
- Refresh sends:

```json
{
  "refreshToken": "{{refreshToken}}"
}
```

- Successful refresh atomically replaces current-run `accessToken` and
  `refreshToken`.
- Code verification extracts `resetToken` for the current run only.
- Protected requests use Bearer `accessToken`.
- No real password or token is committed.
- No Refresh Token is logged to the Postman console.
- Multipart profile method spoofing is not counted as a tenth operation.
- Examples match OpenAPI exactly.

# 23. Requirements Checklist

Update:

```text
specs/001-identity-authentication/checklists/requirements.md
```

The checklist must verify:

- full business requirements were restored;
- exact limits/lifetimes exist;
- exact password policy exists;
- provisioning is specified;
- avatar compensation is specified;
- forgot-password concurrency is specified;
- mail failure is specified;
- localization and headers are specified;
- data model is exact;
- OpenAPI recovery schemas are typed;
- OpenAPI mail failure is present;
- Postman is traceable;
- tasks cite requirement IDs;
- tasks name files;
- frontend tasks are implementation tasks;
- specs are frozen during implementation;
- direct JSON token architecture remains;
- no obsolete cookie/proxy requirement remains;
- 9 operations and 8 paths remain;
- no unresolved ambiguity remains.

Do not mark a failed or unresolved item complete.

# 24. Deployment Guide

Rewrite or correct:

```text
docs/deployment/separate-domain-admin-auth-cutover.md
```

It must cover:

```text
React static deployment on Hostinger Shared Hosting
Backend HTTPS domain
VITE_API_BASE_URL
Backend ADMIN_FRONTEND_ORIGIN
strict exact CORS
supports_credentials=false
no cookies
no CSRF
no Proxy/BFF
no Worker
server-side revocation of previous sessions before cutover
fresh login
removal of obsolete frontend cookie/proxy code
sessionStorage behavior
CSP/XSS controls
rollback without running mixed cookie/JSON transports
staging checks
```

Do not keep both old and new Refresh Token transports active in normal
operation.

# 25. Final Cross-Artifact Audit

Search every final artifact for consistency.

Required final contract:

```text
Frontend:
Hostinger Shared Hosting

Backend:
separate HTTPS API domain

Browser flow:
direct Frontend -> Backend

Access Token:
Sanctum Bearer
JSON
900 seconds
Web memory only

Refresh Token:
opaque custom token
JSON login/refresh response
JSON refresh request
2592000 seconds
Web sessionStorage only
future Mobile secure storage
SHA-256 persistence
rotation
reuse detection

CORS:
exact allow-list
supports_credentials=false

Cookies:
none

CSRF:
none

Origin refresh middleware:
none

Proxy/BFF:
none

One active Admin session:
yes

Operations:
9

Unique paths:
8
```

Remove active contradictory text such as:

```text
HttpOnly
admin_refresh_token cookie
admin_csrf_token
X-CSRF-TOKEN
SameSite
Set-Cookie
withCredentials=true
credentials: include
credentials: same-origin
Frontend proxy
BFF
Cloudflare Worker
server-only BACKEND_API_ORIGIN
relative browser /api/v1 base
ORIGIN_NOT_ALLOWED
CSRF_TOKEN_MISMATCH
legacy cookie
cookie clearing
```

Historical/rejected-alternative mentions are allowed only when unmistakably
labelled.

# 26. Documentation-Level Validation

Before finishing:

1. verify every required file exists or report it missing;
2. validate OpenAPI 3.1 syntax;
3. resolve every `$ref`;
4. verify unique operation IDs;
5. verify 9 operations;
6. verify 8 unique paths;
7. verify profile GET/PATCH share one path;
8. verify Login success includes Access and Refresh Tokens;
9. verify Refresh request requires Refresh Token;
10. verify Refresh success includes rotated Access and Refresh Tokens;
11. verify code-verification success includes Reset Token and 600 seconds;
12. verify forgot-password documents 503 mail failure;
13. verify reusable response headers exist and are referenced;
14. verify no cookie/CSRF/Origin refresh contract remains;
15. verify every task ID is unique;
16. verify every task is unchecked;
17. verify every implementation task cites real requirement IDs;
18. verify no task permits implementation-driven spec rewriting;
19. verify no source code was changed.

Do not run implementation tests because implementation has not yet been
recreated.

# 27. Required Final Report

Return a detailed report containing:

- exact files read;
- exact files modified;
- any shared standards amended and why;
- restored functional requirements;
- restored limits and lifetimes;
- restored provisioning contract;
- restored profile/avatar contract;
- restored password-recovery contract;
- restored concurrency strategy;
- restored localization/header contract;
- final stable error inventory;
- final data-model fields/indexes;
- final Login contract;
- final Refresh request/success/failure contracts;
- final code-verification contract;
- final password-reset contract;
- OpenAPI reusable headers;
- OpenAPI operation/path counts;
- Postman contract;
- tasks regeneration result;
- task traceability result;
- frontend implementation boundary;
- Web storage risk and controls;
- future Mobile readiness;
- contradictions removed;
- unresolved conflicts;
- confirmation that no application source code changed;
- final readiness.

Expected final result:

```text
Feature:
001 Identity and Authentication

Revision:
Final completeness and traceability correction

Architecture:
Direct Frontend-to-Backend HTTPS

Frontend:
Hostinger Shared Hosting

Backend:
Separate HTTPS API domain

Cookies:
None

CSRF:
None

Proxy/BFF:
None

CORS:
Exact allow-list
supports_credentials=false

Access Token:
Sanctum Bearer
900 seconds
Web memory only

Refresh Token:
Opaque rotating token
2592000 seconds
JSON transport
Web sessionStorage only
future Mobile secure storage
SHA-256 persistence
reuse detection

Recovery code:
600 seconds
5 attempts

Reset Token:
600 seconds
one-time use

Forgot-password cooldown:
60 seconds

OpenAPI:
3.1 valid
9 operations
8 unique paths
typed recovery success
503 mail failure
reusable response headers

Data model:
Exact fields, indexes, constraints, states, and concurrency rules

Tasks:
Detailed
file-owned
traceable
all unchecked
specs frozen during implementation

Application source code modified:
No

Unresolved conflicts:
None

Status:
Ready for /speckit.implement
```
```
