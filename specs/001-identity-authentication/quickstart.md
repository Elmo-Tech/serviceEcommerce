# Quickstart Validation Guide: Identity and Authentication

**Feature:** `001-identity-authentication`  
**Status:** Synchronized with `spec.md` and `requirements.md`  
**Last Updated:** 2026-07-29

## 1. Purpose

Validate the synchronized documentation contract before implementation and
define the backend, frontend, deployment, and end-to-end checks required before
Feature 001 can be declared complete.

## 2. Canonical Environment

Example only:

```text
Frontend: https://admin.frontend-example.com
Backend:  https://api.backend-example.net/api/v1
VITE_API_BASE_URL=https://api.backend-example.net/api/v1
```

Production rules:

```text
ADMIN_FRONTEND_ORIGIN=<exact deployed Admin origin>
supports_credentials=false
HTTPS only
```

The example domain must not be hard-coded as the production requirement.

### 2.1 Repository Commands

Local setup uses the existing Laravel application in place:

```powershell
composer install
copy .env.example .env
php artisan key:generate
```

Before seeding, set a strong value for:

```env
SUPER_ADMIN_PASSWORD=
```

Then run:

```powershell
php artisan migrate:fresh --seed
```

Testing uses a dedicated MySQL database. Start from
`.env.testing.example`, set `APP_KEY`, set a strong
`SUPER_ADMIN_PASSWORD`, and confirm the configured database is not production:

```env
DB_CONNECTION=mysql
DB_DATABASE=service_commerce_test
APP_ENV=testing
APP_DEBUG=false
```

### 2.2 Backend Test Commands

Focused backend suites:

```powershell
php artisan test tests\Feature\Database\SuperAdminProvisioningTest.php tests\Feature\Database\AuthPersistenceTest.php
php artisan test tests\Feature\Api\V1\Admin\Auth\LoginAdminTest.php tests\Feature\Api\V1\Admin\Auth\RefreshAdminSessionTest.php tests\Feature\Api\V1\Admin\Auth\LogoutAdminTest.php
php artisan test tests\Feature\Api\V1\Admin\Auth\ProfileAdminTest.php tests\Feature\Api\V1\Admin\Auth\ChangeAdminPasswordTest.php
php artisan test tests\Feature\Api\V1\Admin\Auth\ForgotAdminPasswordTest.php tests\Feature\Api\V1\Admin\Auth\VerifyForgotPasswordCodeTest.php tests\Feature\Api\V1\Admin\Auth\ResetAdminPasswordTest.php
php artisan test tests\Feature\Api\V1\Admin\Auth\CorsTest.php tests\Feature\Api\V1\Admin\Auth\LocalizationHeadersTest.php tests\Feature\Api\V1\Admin\Auth\ResponseSafetyTest.php tests\Feature\Api\V1\Admin\Auth\SecretLeakageTest.php
php artisan test tests\Architecture\AuthenticationArchitectureTest.php tests\Architecture\IdentityAuthenticationOpenApiTest.php tests\Architecture\IdentityAuthenticationPostmanTest.php tests\Architecture\CrossDomainAdminAuthConfigurationTest.php
```

Refresh concurrency suite:

```powershell
php artisan test tests\Feature\Api\V1\Admin\Auth\RefreshConcurrencyTest.php
```

Repository quality gates:

```powershell
php artisan test
vendor\bin\pint --test
vendor\bin\phpstan analyse app tests --no-progress
```

Run MySQL-backed tests sequentially when a suite performs migrations or
concurrency checks against the same test database.

### 2.3 Staging URLs

Example staging values:

```text
Admin Frontend: https://admin-staging.frontend-example.com
Backend API:    https://api-staging.backend-example.net/api/v1
```

Staging environment:

```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://api-staging.backend-example.net
ADMIN_FRONTEND_ORIGIN=https://admin-staging.frontend-example.com
```

React staging environment:

```env
VITE_API_BASE_URL=https://api-staging.backend-example.net/api/v1
```

## 3. Documentation Gate

Before implementation, verify:

- `spec.md` status is `Ready for Reimplementation`;
- `requirements.md` contains no unresolved checklist item;
- architecture is direct Frontend-to-Backend HTTPS;
- no cookie, CSRF, Origin-gated refresh, Proxy/BFF, Cloudflare Worker, or JWT
  contract remains active;
- route count is `9` operations across `8` unique paths;
- OpenAPI is `3.1.0` and has no unresolved `$ref`;
- all artifacts use `Cache-Control: no-store, private`;
- all artifacts use `Pragma: no-cache`;
- CORS uses exact configured `ADMIN_FRONTEND_ORIGIN`;
- Refresh Token transport is JSON body only;
- Refresh throttle is `10/min` by requester IP plus SHA-256 fingerprint of the
  structurally valid submitted Refresh Token, with IP-only fallback for
  structurally invalid requests;
- successful refresh revokes predecessor Access Tokens;
- token generation requirements are synchronized;
- stable error codes and statuses are synchronized.

## 4. Backend Validation Targets

### 4.1 Route Ownership

Verify exactly:

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

Protected middleware:

```text
auth:sanctum
-> EnsureUserIsAdministrator
-> EnsureAdminIsActive
-> endpoint
```

Multipart `POST /profile` with `_method=PATCH` is transport compatibility only.

### 4.2 Response Contract

Verify every auth response preserves:

```text
Content-Language: ar or en
Vary: Accept-Language
Cache-Control: no-store, private
Pragma: no-cache
```

Verify:

- Arabic default;
- English fallback;
- locale resolved before validation;
- success responses contain `success`, localized `message`, and typed `data`;
- success responses contain no `code: null`;
- error responses contain stable English `code`;
- validation errors may include structured `errors`;
- no undocumented metadata or secret values.

### 4.3 CORS

Verify:

- exact configured `ADMIN_FRONTEND_ORIGIN`;
- `supports_credentials=false`;
- no wildcard origin;
- no arbitrary Origin reflection;
- no `Access-Control-Allow-Credentials: true`;
- only documented methods plus `OPTIONS`;
- required request and exposed headers;
- Postman requests do not depend on browser CORS.

### 4.4 Login

Verify:

- request accepts only email and password;
- email is normalized;
- password bytes are not transformed;
- throttle is `5/min` by normalized email + requester IP;
- unknown email and wrong password return `401 INVALID_CREDENTIALS`;
- inactive Admin returns `403 USER_INACTIVE`;
- success revokes prior Access and Refresh Tokens;
- success returns both tokens once in JSON;
- no `Set-Cookie`.

### 4.5 Refresh Rate Limiter

Run from one resolved requester IP:

1. submit structurally valid requests with different Refresh Token values;
2. submit missing, null, wrong-type, and empty Refresh Token values;
3. exceed ten total refresh attempts inside one minute.

Expected:

- structurally valid string-token attempts use requester IP plus deterministic
  SHA-256 token fingerprint;
- the plain Refresh Token is never used directly in the limiter key or logs;
- structurally invalid requests without a valid string token use an IP-only
  fallback bucket;
- request-shape validation happens before throttle-key derivation;
- the eleventh request returns `429 RATE_LIMITED`;
- trusted-proxy configuration prevents arbitrary forwarding headers from
  creating buckets;
- no plaintext token is logged.

### 4.6 Refresh Rotation and Access Token Replacement

1. Login and store Access Token A and Refresh Token A.
2. Call a protected endpoint with Access Token A; expect success.
3. Refresh with Refresh Token A.
4. Receive Access Token B and Refresh Token B.
5. Call a protected endpoint with Access Token B; expect success.
6. Call the same endpoint with Access Token A; expect
   `401 UNAUTHENTICATED`.
7. Query authoritative test state.

Expected:

```text
Access Token A: revoked
Refresh Token A: rotated and unusable
Access Token B: active
Refresh Token B: active
```

Only the replacement token pair remains active.

### 4.7 Refresh Reuse

1. Reuse Refresh Token A after successful rotation.
2. Expect `401 REFRESH_TOKEN_INVALID`.
3. Try Access Token B and Refresh Token B.

Expected:

- all sessions are revoked;
- Access Token B returns `401 UNAUTHENTICATED`;
- Refresh Token B is unusable;
- no secret appears in logs.

### 4.8 Protected Inactive Account

For logout, profile read, profile update, and password change:

- valid Bearer token + inactive owner returns `403 USER_INACTIVE`;
- middleware order matches the specification.

Refresh with an inactive owner returns the generic
`401 REFRESH_TOKEN_INVALID` credential boundary.

### 4.9 Profile and Avatar

Verify:

- profile returns exactly `name`, `email`, `avatar`, `role`, `permissions`;
- no IDs, internal paths, disk names, timestamps, or token metadata;
- update accepts only `name`, `avatar`, `_method`;
- name is trimmed and limited to 1–150 characters;
- allowed formats: `jpg`, `jpeg`, `png`, `webp`;
- detected MIME matches;
- maximum size: `2 MB`;
- absent/empty avatar preserves current value;
- non-empty string path/URL is rejected;
- database failure deletes the newly stored file;
- old-file deletion occurs after commit;
- old-file cleanup failure does not fail the update;
- safe metadata logging only;
- no cleanup Job.

### 4.10 Password Change

Verify:

- `currentPassword`, `newPassword`, `newPasswordConfirmation`;
- no secret transformation;
- minimum 10 characters with lowercase, uppercase, number, and symbol;
- confirmation matches;
- new password differs from current;
- wrong current password returns `422 CURRENT_PASSWORD_INVALID`;
- rate limit is `5/min` per authenticated Administrator;
- success revokes all sessions and reset workflows;
- no new token is returned.

### 4.11 Forgot Password

Verify:

```text
eligible success -> 200
unknown email -> 200
inactive Admin -> 200
validation failure -> 422 VALIDATION_ERROR
rate limit -> 429 RATE_LIMITED
eligible mail failure -> 503 MAIL_SERVICE_UNAVAILABLE
```

Unknown and inactive targets:

- create no workflow;
- send no mail;
- receive the same generic success envelope.

Eligible target:

- locks the stable `users` row;
- rechecks eligibility and 60-second cooldown;
- invalidates prior usable workflows;
- creates one workflow;
- commits;
- sends localized mail synchronously;
- mail contains only code, expiry statement, and ignore guidance;
- mail failure invalidates only the new workflow.

Concurrency test:

- run two eligible first-time requests concurrently;
- expect at most one newly usable workflow and one mail transport invocation.

### 4.12 Verify Code and Reset Password

Verify code:

- exact six-digit code;
- no transformation;
- code expires in `600` seconds;
- fifth failed attempt consumes the workflow;
- unusable code returns `422 PASSWORD_RESET_CODE_INVALID`;
- concurrent verification has one winner;
- success returns one Reset Token with `resetTokenExpiresIn: 600`.

Reset Password:

- Reset Token and passwords are not transformed;
- password policy and confirmation are enforced;
- Reset Token expires in `600` seconds;
- unusable token returns `422 PASSWORD_RESET_TOKEN_INVALID`;
- concurrent reset has one winner;
- success consumes workflow and revokes every session;
- no new auth token.

### 4.13 Secret Safety

Verify no plaintext or approved hash appears in:

```text
logs
exception context
analytics
monitoring
URLs
query strings
browser console
email, except the approved six-digit recovery code
client-visible errors
```

Allowed one-time plaintext outputs are only:

- Access Token in login/refresh success;
- Refresh Token in login/refresh success;
- Reset Token in verify-code success;
- recovery code in recovery email.

## 5. React Web Validation Targets

Mandatory tests in the owning React repository must prove:

- direct `VITE_API_BASE_URL`;
- `withCredentials=false`;
- Access Token remains in runtime memory only;
- Refresh Token exists only in `sessionStorage`;
- Reset Token remains in runtime memory only;
- no prohibited localStorage, IndexedDB, cookie, URL, persisted store, console,
  analytics, monitoring, or error-reporting leakage;
- same-tab reload uses refresh bootstrap;
- successful refresh replaces the stored Refresh Token;
- failed refresh clears auth state;
- logout clears auth state;
- `Authorization: Bearer` injection;
- testable CSP behavior and safe rendering controls.

Deployment/architecture validation must verify the final production CSP header.

If the React repository or its evidence is unavailable, these checks remain
blocked and full Feature 001 completion must not be claimed.

## 6. Future Mobile Readiness

Verify:

- same login and refresh endpoints;
- JSON transport only;
- no Origin or CSRF requirement;
- Refresh Token can be stored in OS-backed secure storage;
- no Mobile-only route or schema discriminator;
- login from Mobile replaces a Web session and vice versa.

## 7. Required End-to-End Smoke Scenarios

1. Provisioning rerun remains idempotent and preserves password/identity.
2. Login returns both tokens and no cookie.
3. Login replacement invalidates the prior token pair.
4. Refresh throttle key uses IP plus SHA-256 token fingerprint where possible
   and IP-only fallback for structurally invalid requests.
5. Refresh rotates both tokens and revokes predecessor Access Tokens.
6. Reuse of the rotated predecessor revokes all sessions.
7. Protected inactive account returns `USER_INACTIVE`.
8. Profile returns exactly five fields.
9. Avatar replacement and compensation succeed.
10. Password change returns correct domain and validation errors.
11. Forgot Password response boundary distinguishes 200/422/429/503 correctly.
12. Concurrent Forgot Password leaves one usable workflow and one mail attempt.
13. Code verification has one winner and five-attempt consumption.
14. Password reset has one winner and revokes every session.
15. Unapproved browser origin receives no CORS approval.
16. Every response has the required localization and cache headers.
17. Stable error codes match OpenAPI exactly.
18. Backend and frontend secret-leakage checks pass.

### 7.1 Postman Smoke

Use:

```text
postman/Service-Commerce.postman_collection.json
postman/Service-Commerce.local.postman_environment.json.example
```

The collection keeps only these auth runtime variables:

```text
baseUrl
accessToken
refreshToken
```

All Feature 001 requests live under the `Admin Auth` folder. During a smoke
run, capture evidence for:

- login response contains Access Token and Refresh Token in JSON;
- refresh response rotates both tokens and updates runtime variables;
- predecessor Access Token returns `401 UNAUTHENTICATED` after refresh;
- reused predecessor Refresh Token returns `401 REFRESH_TOKEN_INVALID`;
- refresh validation/rate-limit boundaries cover fingerprint and IP-only
  fallback behavior;
- profile response contains exactly `name`, `email`, `avatar`, `role`,
  `permissions`;
- forgot-password covers `200`, `422`, `429`, and `503`;
- every response preserves `Content-Language`, `Vary`, `Cache-Control`, and
  `Pragma`;
- no `Set-Cookie` response header appears.

### 7.2 Scripted Smoke

Use the backend smoke script when a staging or local API is running:

```powershell
$env:ADMIN_AUTH_SMOKE_BASE_URL = "https://api-staging.backend-example.net/api/v1"
$env:ADMIN_AUTH_SMOKE_EMAIL = "admin@example.com"
$env:ADMIN_AUTH_SMOKE_PASSWORD = "<set-locally>"
$env:ADMIN_AUTH_SMOKE_ORIGIN = "https://admin-staging.frontend-example.com"
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\admin-auth-smoke.ps1
```

When the staging environment is deliberately configured to make password-reset
mail fail for an eligible Admin, set:

```powershell
$env:ADMIN_AUTH_SMOKE_MAIL_FAILURE_EMAIL = "admin-mail-failure@example.com"
```

Optional throttle exercise:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\admin-auth-smoke.ps1 -ExerciseThrottle
```

The smoke script asserts:

- login returns both JSON tokens and no `Set-Cookie`;
- profile shape is exactly `name`, `email`, `avatar`, `role`, `permissions`;
- refresh rotates both tokens;
- predecessor Access Token returns `401 UNAUTHENTICATED`;
- reused predecessor Refresh Token returns `401 REFRESH_TOKEN_INVALID`;
- reuse revokes the replacement Access Token;
- forgot-password covers enumeration-safe `200` and validation `422`;
- configured mail-failure smoke covers `503 MAIL_SERVICE_UNAVAILABLE`;
- reset-password validation returns `422 VALIDATION_ERROR`;
- auth responses include required localization/cache headers.

With `-ExerciseThrottle`, it also asserts forgot-password `429`, the IP-only
invalid refresh fallback, and distinct structurally valid unknown Refresh
Tokens not collapsing into one shared throttle bucket.

### 7.3 Current Smoke Evidence Status

Backend automated evidence is recorded in:

```text
specs/001-identity-authentication/verification-report.md
```

The external React repository, production CSP evidence, and deployment-level
browser CORS evidence are not present in this backend repository. Until those
artifacts are supplied by the owning React/deployment environment, Feature 001
must remain blocked on external verification and must not be declared fully
complete.

## 8. Final Quality Gate

Feature 001 is complete only when:

- synchronized artifacts agree;
- backend tests pass;
- external React tests and evidence pass;
- deployment CORS and CSP checks pass;
- no obsolete transport requirement remains;
- one active token pair is preserved after login and refresh;
- all-session revocation works for logout, password change/reset, inactivity,
  and Refresh Token reuse;
- `tasks.md` has been regenerated from the synchronized artifacts and all
  applicable tasks are complete.
