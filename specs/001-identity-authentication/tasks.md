# Tasks: Identity and Authentication

**Feature:** `001-identity-authentication`  
**Status:** Implementation-ready after synchronized artifact review  
**Generated From:** `spec.md`, `requirements.md`, `plan.md`, `research.md`,
`data-model.md`, `contracts/openapi.yaml`, and `quickstart.md`  
**Last Updated:** 2026-07-29

## Task Format

```text
- [ ] T### [P?] [US#?] Action with exact target paths and requirement IDs
```

- `[P]`: may run in parallel after its phase prerequisites are complete.
- `[US1]`–`[US6]`: belongs to the matching user story.
- Tasks without a user-story marker are shared prerequisites, governance,
  external integration, documentation, or final verification.
- A task is complete only when its implementation and named verification
  evidence both exist.
- Do not change `spec.md` or `requirements.md` to accommodate code drift.

## Canonical Implementation Contract

```text
Architecture:
React Admin -> direct HTTPS -> Laravel API

Authentication:
Sanctum Bearer Access Token + custom rotating JSON Refresh Token

Access Token:
900 seconds
Web runtime memory only

Refresh Token:
2592000 seconds
Web sessionStorage only
future Mobile OS-backed secure storage
SHA-256 persistence only

Refresh throttle:
10/min by requester IP + SHA-256 fingerprint of the structurally valid
submitted Refresh Token, with IP-only fallback for structurally invalid input

Session invariant:
successful login or refresh leaves only one active Access Token and one active
Refresh Token for the Administrator

Excluded:
authentication cookies
CSRF
Origin-gated refresh middleware
Proxy/BFF
Cloudflare Worker
browser-session authentication
JWT replacement
```

---

# Phase 1: Governance, Repository Inspection, and Frozen Inputs

**Goal:** establish the actual implementation surface before changing code.

- [X] T001 Inspect existing authentication routes, controllers, requests,
  actions, services, models, middleware, migrations, seeders, language files,
  tests, Postman files, and deployment docs; record a reuse/update/delete map in
  `specs/001-identity-authentication/implementation-inventory.md`
  (`FR-001`–`FR-007`, `FR-054`–`FR-062`, `AUTH-001`–`AUTH-004`).
- [X] T002 Confirm that `specs/001-identity-authentication/spec.md` and
  `specs/001-identity-authentication/checklists/requirements.md` match the
  approved final files and freeze them during implementation
  (`FR-001`–`FR-225`, `API-001`–`API-007`, `ERR-001`–`ERR-017`,
  `AUTH-001`–`AUTH-004`, `DATA-001`–`DATA-007`, `VER-050`).
- [X] T003 Replace the feature artifacts in
  `specs/001-identity-authentication/research.md`,
  `specs/001-identity-authentication/data-model.md`,
  `specs/001-identity-authentication/plan.md`,
  `specs/001-identity-authentication/quickstart.md`, and
  `specs/001-identity-authentication/contracts/openapi.yaml` with the
  synchronized approved versions before implementation begins
  (`VER-027`–`VER-040`).
- [X] T004 Audit `.env.example`, `config/*.php`, `bootstrap/app.php`, and the
  deployment environment for required values including
  `ADMIN_FRONTEND_ORIGIN`, token lifetimes, provisioning values, mail settings,
  public avatar disk, and trusted-proxy configuration; document missing values
  without introducing default production credentials
  (`FR-008`–`FR-022`, `FR-183`–`FR-193`, `FR-211`).
- [X] T005 Confirm the backend uses Laravel 13, PHP 8.3, MySQL, Sanctum, and
  Spatie Permission versions compatible with the synchronized plan; record
  repository-specific conventions in
  `specs/001-identity-authentication/implementation-inventory.md`
  (`AUTH-001`–`AUTH-004`, `DATA-001`–`DATA-007`).
- [X] T006 Create or update shared auth test helpers, factories, time controls,
  mail fakes, storage fakes, and database-state assertions under
  `tests/Support/Auth/`, `database/factories/`, and existing repository test
  support paths (`VER-001`–`VER-016`, `VER-041`, `VER-042`,
  `VER-044`–`VER-047`).
- [X] T007 Add a feature-level requirement traceability helper or documented
  mapping in `specs/001-identity-authentication/traceability.md` linking every
  task to its `FR`, `API`, `ERR`, `AUTH`, `DATA`, and `VER` identifiers
  (`VER-033`).
- [X] T008 Add an architecture baseline test in
  `tests/Architecture/AuthenticationArchitectureTest.php` that initially
  records the canonical nine operations, eight paths, route ownership, and
  forbidden legacy concepts; keep failing assertions visible until the related
  implementation tasks are complete (`FR-001`–`FR-007`, `FR-054`–`FR-062`,
  `ERR-016`, `ERR-017`, `VER-006`, `VER-016`, `VER-031`, `VER-032`).

**Phase 1 exit criteria**

- Current implementation inventory exists.
- Final artifacts are installed in the feature directory.
- Missing config and legacy implementation concepts are known.
- `spec.md` and `requirements.md` remain unchanged.

---

# Phase 2: Shared API, Localization, and Security Foundations

**Goal:** establish response, exception, locale, cache, and secret-safety
infrastructure before endpoint work.

- [X] T009 Implement or align the shared API success envelope helper in the
  repository-approved response layer so success contains only `success`,
  localized `message`, and endpoint-typed `data`, with no `code: null` or
  undocumented metadata (`FR-168`–`FR-170`, `API-001`, `API-003`, `API-007`,
  `VER-009`).
- [X] T010 Implement or align the shared API error envelope and exception
  renderer in `bootstrap/app.php` and existing exception support files so auth
  errors contain `success`, localized `message`, stable English `code`, and
  approved `errors` structure (`FR-171`, `FR-172`, `API-005`,
  `ERR-001`–`ERR-015`, `VER-009`, `VER-010`).
- [X] T011 Create or update stable auth-domain exception classes or error
  mapping under `app/Exceptions/Auth/` or the repository-approved equivalent
  for `INVALID_CREDENTIALS`, `USER_INACTIVE`, `UNAUTHENTICATED`,
  `VALIDATION_ERROR`, `RATE_LIMITED`, `REFRESH_TOKEN_INVALID`,
  `CURRENT_PASSWORD_INVALID`, `PASSWORD_RESET_CODE_INVALID`,
  `PASSWORD_RESET_TOKEN_INVALID`, and `MAIL_SERVICE_UNAVAILABLE`
  (`ERR-001`–`ERR-015`).
- [X] T012 Implement locale resolution before validation in the existing locale
  middleware/bootstrap path, using Arabic as default and English as fallback
  while preserving English request keys and machine codes
  (`FR-161`–`FR-165`, `FR-173`, `FR-174`, `FR-177`–`FR-182`,
  `VER-011`, `VER-034`).
- [X] T013 Implement or update sensitive auth response headers in middleware or
  the shared response layer:
  `Content-Language`, `Vary: Accept-Language`,
  `Cache-Control: no-store, private`, and `Pragma: no-cache`
  (`FR-157`–`FR-160`, `FR-173`–`FR-176`, `VER-011`).
- [X] T014 Create or update auth translation files under `lang/ar/` and
  `lang/en/` for all stable auth messages without translating machine codes,
  route names, request keys, role names, or permission names
  (`FR-162`–`FR-165`, `FR-178`–`FR-182`).
- [X] T015 Implement `app/Support/Auth/AuthenticationSecurityLogger.php` or the
  repository-approved equivalent with explicit secret redaction and safe event
  metadata only (`FR-177`, `FR-184`, `FR-194`–`FR-208`, `FR-220`,
  `VER-012`).
- [X] T016 [P] Add focused unit tests for success and error envelopes in
  `tests/Unit/Support/Api/AuthenticationResponseTest.php`
  (`FR-168`–`FR-172`, `API-005`, `API-007`, `VER-009`, `VER-010`).
- [X] T017 [P] Add focused tests for locale-before-validation and required
  response headers in
  `tests/Feature/Api/V1/Admin/Auth/LocalizationHeadersTest.php`
  (`FR-157`–`FR-165`, `FR-173`–`FR-182`, `VER-011`).
- [X] T018 [P] Add logger redaction tests in
  `tests/Unit/Support/Auth/AuthenticationSecurityLoggerTest.php` proving
  passwords, tokens, codes, Authorization headers, hashes, SQL, stack traces,
  credentials, and raw avatar paths never appear
  (`FR-194`–`FR-208`, `FR-220`, `VER-012`).

---

# Phase 3: Persistence, Models, and Provisioning

**Goal:** prepare authoritative MySQL state before session and recovery logic.

- [X] T019 Audit the existing `users` table and create only the required
  migration adjustments for normalized unique email, Admin type, active state,
  and avatar metadata while following repository database standards
  (`FR-011`–`FR-015`, `FR-068`, `FR-069`, `DATA-005`, `DATA-006`).
- [X] T020 Implement or update the `refresh_tokens` migration with owner
  polymorphic fields, unique 64-character SHA-256 `token_hash`, `family_id`,
  `expires_at`, nullable `revoked_at`, nullable self-reference
  `rotated_to_token_id`, nullable controlled `revocation_reason`, timestamps,
  and the synchronized indexes in
  `database/migrations/*create_or_update_refresh_tokens_table.php`
  (`FR-025`, `FR-072`, `FR-073`, `FR-197`, `FR-214`, `DATA-001`,
  `DATA-002`, `DATA-006`).
- [X] T021 Implement or update the `password_resets` workflow migration with
  owner fields, normalized email, password-hashed code, code expiry,
  failed-attempt counter, verification/consumption state, unique nullable
  SHA-256 Reset Token hash, Reset Token expiry, timestamps, and required
  indexes in the repository-approved migration
  (`FR-119`–`FR-155`, `FR-160`–`FR-164`, `FR-198`, `FR-199`,
  `FR-216`, `FR-218`, `DATA-003`, `DATA-004`, `DATA-006`).
- [X] T022 [P] Implement or update `app/Models/RefreshToken.php` with owner and
  successor relationships, active/expired/revoked/rotated state helpers,
  guarded secret fields, and owner/family invariant checks
  (`FR-067`, `FR-072`, `FR-073`, `DATA-001`, `DATA-002`).
- [X] T023 [P] Implement or update `app/Models/PasswordReset.php` with owner
  relationship, workflow state helpers, expiry/attempt checks, and one-time
  consumption behavior (`FR-125`–`FR-155`, `FR-160`–`FR-164`,
  `DATA-003`, `DATA-004`).
- [X] T024 Update the Administrator User model relationships for Sanctum Access
  Tokens, Refresh Tokens, Password Reset workflows, Spatie roles/permissions,
  and approved avatar accessors without exposing internal fields
  (`FR-012`, `FR-014`–`FR-022`, `FR-078`, `FR-079`, `AUTH-002`,
  `DATA-005`).
- [X] T025 Add environment-backed Super Admin provisioning configuration in
  `.env.example` and the repository-approved config file, with no production
  defaults and explicit validation requirements
  (`FR-008`–`FR-013`).
- [X] T026 Implement idempotent `super-admin` role creation and assignment in
  `database/seeders/` without duplicate roles or assignments
  (`FR-016`, `FR-017`, `FR-019`, `FR-022`, `VER-001`).
- [X] T027 Implement idempotent Super Admin provisioning in
  `database/seeders/` and support classes: normalize email, hash the password
  only on creation, preserve password and identity on rerun, and correct Admin
  type, active state, and role assignment
  (`FR-008`–`FR-022`, `VER-001`).
- [X] T028 [P] Add migration and model tests in
  `tests/Feature/Database/AuthPersistenceTest.php` for indexes, unique hashes,
  self-link integrity, workflow indexes, and prohibited Web/Mobile
  discriminator fields (`DATA-001`–`DATA-006`).
- [X] T029 [P] Add provisioning tests in
  `tests/Feature/Database/SuperAdminProvisioningTest.php` covering safe missing
  configuration failure, normalization, uniqueness, hashing, rerun
  idempotency, password preservation, identity preservation, and corrective
  role/type/active behavior (`FR-008`–`FR-022`, `VER-001`).
- [X] T030 [P] Add database secret-persistence tests proving plaintext secrets
  are absent and approved hashes exist only in authoritative columns
  (`FR-194`–`FR-200`, `DATA-001`, `DATA-003`, `DATA-006`,
  `VER-012`, `VER-038`).

---

# Phase 4: Shared Token, Password, Session, and Recovery Services

**Goal:** centralize security-sensitive logic before endpoint actions.

- [X] T031 Implement `app/Support/Auth/SecureAuthTokenGenerator.php` using
  cryptographically secure randomness for 64-byte Refresh Tokens, at least
  32-byte Reset Tokens, and six-digit numeric recovery codes; prohibit
  predictable generators (`FR-132`, `FR-214`, `FR-216`, `FR-218`,
  `FR-219`, `VER-044`–`VER-047`).
- [X] T032 Implement `app/Services/Auth/AccessTokenService.php` for Sanctum
  issuance with 900-second expiry, scoped owner revocation, and replacement
  issuance without plaintext persistence (`FR-024`, `FR-032`, `FR-065`,
  `FR-075`, `FR-102`, `FR-113`, `FR-149`, `FR-165`,
  `FR-196`, `FR-202`, `FR-203`, `DATA-002`).
- [X] T033 Implement `app/Services/Auth/RefreshTokenService.php` for secure
  generation, SHA-256 lookup, family creation, successor creation, rotation,
  predecessor linkage, expiry, revocation, and reusable-token detection
  (`FR-025`, `FR-033`, `FR-063`–`FR-073`, `FR-197`,
  `FR-204`, `FR-205`, `FR-214`, `FR-219`, `FR-220`,
  `DATA-001`, `DATA-002`).
- [X] T034 Implement `app/Services/Auth/AdminSessionRevocationService.php` for
  scoped revocation on login replacement, refresh replacement, logout,
  password change, password reset, inactive-owner detection, and Refresh Token
  reuse (`FR-032`, `FR-043`, `FR-063`–`FR-077`, `FR-113`,
  `FR-149`, `FR-165`, `DATA-002`).
- [X] T035 Implement an approved reusable password policy rule or ruleset under
  `app/Rules/Auth/` requiring minimum 10 characters, lowercase, uppercase,
  number, symbol, confirmation, and current-password difference where
  applicable (`FR-092`–`FR-110`, `FR-158`, `FR-159`).
- [X] T036 Implement `app/Services/Auth/ForgotPasswordCodeService.php` for
  secure six-digit generation, password hashing, 600-second expiry, 60-second
  cooldown checks, failed-attempt tracking, and workflow invalidation
  (`FR-108`, `FR-117`–`FR-150`, `FR-198`, `FR-207`,
  `FR-218`, `DATA-003`, `DATA-004`).
- [X] T037 Implement `app/Services/Auth/ResetTokenService.php` for at least
  32-byte secure generation, SHA-256 persistence, 600-second expiry, one-time
  consumption, and structurally valid unusable-token handling
  (`FR-138`, `FR-139`, `FR-153`–`FR-166`, `FR-199`,
  `FR-206`, `FR-216`, `DATA-003`, `DATA-004`).
- [X] T038 Configure trusted-proxy-aware requester IP resolution and define the
  named Refresh limiter as `10/min` by requester IP plus SHA-256 fingerprint of
  the structurally valid submitted Refresh Token; ensure the plain token is not
  used directly in the limiter key, structurally invalid requests use IP-only
  fallback, and the limiter order matches request-shape validation before
  throttle-key derivation (`FR-068`, `FR-069`, `FR-210`–`FR-213`,
  `VER-041`, `VER-042`).
- [X] T039 [P] Add unit tests for the secure generator in
  `tests/Unit/Support/Auth/SecureAuthTokenGeneratorTest.php`, including entropy
  source use, byte requirements, numeric code format, and forbidden generator
  absence (`FR-214`, `FR-216`, `FR-218`, `FR-219`,
  `VER-044`–`VER-047`).
- [X] T040 [P] Add unit tests for Access Token, Refresh Token, session
  revocation, password policy, code, and Reset Token services under
  `tests/Unit/Services/Auth/`
  (`DATA-001`–`DATA-007`, `VER-003`, `VER-004`, `VER-014`,
  `VER-047`).

---

# Phase 5: Requests, Resources, Routes, and Middleware Ownership

**Goal:** implement exact request shapes and canonical route boundaries.

- [X] T041 Implement or update
  `app/Http/Requests/Api/V1/Admin/Auth/LoginRequest.php` to accept only
  `email` and `password`, normalize email only, preserve password bytes, and
  use the approved validation envelope (`FR-026`–`FR-031`, `FR-182`,
  `API-001`, `ERR-001`, `ERR-002`, `ERR-004`).
- [X] T042 Implement or update
  `app/Http/Requests/Api/V1/Admin/Auth/RefreshTokenRequest.php` to accept only
  non-empty string `refreshToken` from JSON and never transform secret bytes
  (`FR-063`–`FR-067`, `API-002`, `ERR-004`, `ERR-006`).
- [X] T043 [P] Implement or update
  `app/Http/Requests/Api/V1/Admin/Auth/UpdateProfileRequest.php` for only
  `name`, `avatar`, and `_method`, including name and avatar validation
  (`FR-080`–`FR-093`, `API-006`).
- [X] T044 [P] Implement or update
  `app/Http/Requests/Api/V1/Admin/Auth/ChangePasswordRequest.php` for exact
  fields, no secret transformation, approved policy, and confirmation
  (`FR-097`–`FR-112`).
- [X] T045 [P] Implement or update
  `app/Http/Requests/Api/V1/Admin/Auth/ForgotPasswordRequest.php` for normalized
  email and validation-safe enumeration behavior
  (`FR-106`, `FR-117`–`FR-124`).
- [X] T046 [P] Implement or update
  `app/Http/Requests/Api/V1/Admin/Auth/VerifyForgotPasswordCodeRequest.php` for
  normalized email and exact six-digit unmodified code
  (`FR-132`, `FR-146`, `FR-147`, `ERR-008`).
- [X] T047 [P] Implement or update
  `app/Http/Requests/Api/V1/Admin/Auth/ResetPasswordRequest.php` for normalized
  email, unmodified Reset Token, password policy, and confirmation
  (`FR-143`, `FR-158`–`FR-161`, `ERR-009`).
- [X] T048 [P] Implement
  `app/Http/Resources/Api/V1/Admin/Auth/AdminProfileResource.php` returning
  exactly `name`, `email`, `avatar`, `role`, and deterministic `permissions`
  (`FR-068`, `FR-078`, `FR-079`, `API-007`).
- [X] T049 [P] Implement typed login and refresh success resources under
  `app/Http/Resources/Api/V1/Admin/Auth/` matching `API-001` and `API-003`,
  with profile only in Login success (`FR-023`, `FR-033`, `FR-060`,
  `FR-061`, `FR-070`, `FR-071`, `API-001`–`API-004`).
- [X] T050 [P] Implement endpoint-specific typed success resources for logout,
  password change, Forgot Password, Verify Code, and Reset Password without
  generic undocumented data (`FR-152`–`FR-170`, `API-007`).
- [X] T051 Configure exactly the five Unauthenticated Admin Auth Operations and
  four Protected Admin Auth Operations in `routes/api/v1/auth.php` or the
  existing equivalent; keep multipart POST `_method=PATCH` as transport
  compatibility only (`FR-001`–`FR-003`, `FR-054`–`FR-062`,
  `API-006`, `AUTH-004`).
- [X] T052 Align `EnsureUserIsAdministrator` and `EnsureAdminIsActive` with the
  exact protected middleware order and inactive-account contracts; do not add
  self-service permission middleware (`FR-031`, `FR-059`–`FR-062`,
  `AUTH-002`–`AUTH-004`, `ERR-002`, `ERR-003`).
- [X] T053 Remove active Feature 001 dependencies on auth-cookie, CSRF,
  Origin-refresh, Proxy/BFF, Cloudflare Worker, browser-session, or JWT
  middleware/services/routes after confirming they are not used elsewhere
  (`FR-006`, `FR-007`, `FR-034`, `FR-055`–`FR-057`,
  `FR-064`, `FR-074`, `FR-077`, `FR-116`, `FR-151`,
  `ERR-016`, `ERR-017`, `VER-006`, `VER-032`).
- [X] T054 Add request/resource/route contract tests in
  `tests/Feature/Api/V1/Admin/Auth/AuthContractTest.php` covering allowed keys,
  forbidden keys, nine operations, eight paths, method spoofing, middleware
  ownership, and typed resources (`FR-001`–`FR-003`, `FR-054`–`FR-062`,
  `API-001`–`API-007`, `VER-016`).

---

# Phase 6: User Story 1 — Provision and Sign In

**Goal:** provide idempotent provisioning and replacement login.

- [X] T055 [US1] Implement `app/Actions/Auth/LoginAdminAction.php` for normalized
  email lookup, unmodified password verification, generic invalid-credential
  handling, inactive-account handling, atomic prior-session revocation, and
  one replacement token-pair issuance (`FR-023`–`FR-035`,
  `ERR-001`, `ERR-002`, `AUTH-001`–`AUTH-003`, `DATA-002`).
- [X] T056 [US1] Implement or update
  `app/Http/Controllers/Api/V1/Admin/Auth/LoginController.php` using the exact
  Login Request, action, resource, localized envelope, and headers
  (`FR-023`–`FR-035`, `FR-168`–`FR-182`, `API-001`).
- [X] T057 [US1] Register the Login route with the `5/min` normalized-email +
  resolved-IP limiter and without Bearer, Origin, CSRF, or cookie behavior
  (`FR-026`–`FR-034`, `FR-054`–`FR-058`, `VER-005`, `VER-006`).
- [X] T058 [P] [US1] Add Login contract tests in
  `tests/Feature/Api/V1/Admin/Auth/LoginTest.php` for exact JSON, token
  lifetimes, five profile fields, no cookie, required headers, and no secret
  leakage (`FR-023`–`FR-035`, `API-001`, `VER-002`, `VER-005`,
  `VER-009`–`VER-012`).
- [X] T059 [P] [US1] Add Login security tests for unknown email/wrong password
  indistinguishability, inactive Admin, password non-transformation, rate
  limiting, and English stable error codes (`FR-027`–`FR-031`,
  `ERR-001`, `ERR-002`, `ERR-005`, `VER-010`).
- [X] T060 [US1] Add replacement-session tests proving a new Web or future
  Mobile login revokes every prior Access and Refresh Token and leaves one
  active token pair (`FR-032`, `FR-035`, `FR-043`, `DATA-002`).

---

# Phase 7: User Story 2 — Refresh and Logout

**Goal:** rotate safely, revoke predecessor Access Tokens, detect reuse, and end
all sessions.

- [X] T061 [US2] Implement Refresh route throttling so request-shape validation
  occurs before limiter key derivation and the named limiter uses requester IP
  plus SHA-256 fingerprint of the structurally valid submitted Refresh Token,
  with IP-only fallback for invalid/missing input and no plaintext token in the
  limiter key (`FR-068`, `FR-069`, `FR-210`–`FR-213`, `VER-041`, `VER-042`).
- [X] T062 [US2] Implement
  `app/Actions/Auth/RefreshAdminSessionAction.php` with exact order:
  authoritative SHA-256 lookup, transaction, `lockForUpdate()`, token/owner
  revalidation, predecessor Access Token revocation, Refresh Token rotation,
  replacement Access Token issuance, and commit
  (`FR-063`–`FR-074`, `FR-224`, `FR-225`, `DATA-001`, `DATA-002`,
  `DATA-007`).
- [X] T063 [US2] Implement rotated-predecessor reuse handling that atomically
  revokes every Access and Refresh Token for the Administrator and returns only
  `401 REFRESH_TOKEN_INVALID`
  (`FR-067`, `FR-073`, `ERR-006`, `VER-004`).
- [X] T064 [US2] Implement or update
  `app/Http/Controllers/Api/V1/Admin/Auth/RefreshTokenController.php` with body
  token transport only, typed response without profile, no cookie, and required
  headers (`FR-063`–`FR-074`, `API-002`–`API-004`).
- [X] T065 [US2] Implement `app/Actions/Auth/LogoutAdminAction.php` and
  `app/Http/Controllers/Api/V1/Admin/Auth/LogoutController.php` for all-session
  revocation, no replacement token, and no cookie
  (`FR-075`–`FR-077`, `AUTH-004`).
- [X] T066 [P] [US2] Add Refresh validation-boundary tests for missing, null,
  wrong-type, empty, query-string, header, cookie, and transformed-token cases
  (`FR-063`–`FR-067`, `ERR-004`, `ERR-006`).
- [X] T067 [P] [US2] Add Refresh limiter tests in
  `tests/Feature/Api/V1/Admin/Auth/RefreshRateLimitTest.php` using the same
  token fingerprint, different token fingerprints, and invalid/missing-token
  fallback from the same resolved IP; prove the eleventh matching-bucket
  request is `429 RATE_LIMITED`, the plaintext token is never used as the key,
  and forwarded-header spoofing cannot create arbitrary buckets (`FR-068`,
  `FR-069`, `FR-210`–`FR-213`, `ERR-005`, `VER-041`, `VER-042`).
- [X] T068 [P] [US2] Add Refresh success tests proving the replacement token
  pair, exact lifetimes, absence of profile/cookie, predecessor Refresh Token
  invalidation, predecessor Access Token `401 UNAUTHENTICATED`, and only one
  active token pair (`FR-070`–`FR-074`, `FR-224`, `FR-225`, `VER-003`,
  `VER-005`, `VER-050`).
- [X] T069 [P] [US2] Add Refresh credential-failure tests for unknown, expired,
  revoked, reused, ownerless, deleted-owner, inactive-owner, invalid-family,
  and invalid-rotation states, all returning the generic stable contract
  (`FR-067`, `ERR-006`, `VER-003`, `VER-004`).
- [X] T070 [US2] Add MySQL concurrency tests in
  `tests/Feature/Api/V1/Admin/Auth/RefreshConcurrencyTest.php` proving one
  predecessor yields at most one successful rotation and no partial
  Access/Refresh replacement state (`FR-069`, `FR-072`, `FR-224`, `FR-225`,
  `DATA-002`, `DATA-007`).
- [X] T071 [US2] Add reuse tests proving a rotated predecessor revokes the
  replacement token pair and every other session while never logging token
  values (`FR-073`, `FR-208`, `FR-220`, `VER-004`, `VER-012`).
- [X] T072 [US2] Add Logout tests in
  `tests/Feature/Api/V1/Admin/Auth/LogoutTest.php` for protected middleware,
  inactive owner, all-session revocation, no replacement token, no cookie, and
  required headers (`FR-075`–`FR-077`, `AUTH-003`, `AUTH-004`,
  `VER-005`, `VER-011`).

---

# Phase 8: User Story 3 — Profile and Avatar

**Goal:** expose exactly the approved profile and implement compensated avatar
replacement.

- [X] T073 [US3] Implement or update profile-show behavior in
  `ProfileController` using `AdminProfileResource`, returning only the five
  approved fields and deterministic permissions
  (`FR-078`, `FR-079`, `API-007`).
- [X] T074 [US3] Implement
  `app/Actions/Auth/UpdateAdminProfileAction.php` for approved name mutation,
  prohibited identity/privilege mutation, and no token rotation
  (`FR-080`–`FR-083`, `FR-101`).
- [X] T075 [US3] Implement avatar storage and compensation inside the profile
  action or a repository-approved service: store new file, transactionally
  update metadata, delete new file on DB failure, delete old file after commit,
  tolerate old cleanup failure, and log safe metadata only
  (`FR-084`–`FR-100`, `VER-015`).
- [X] T076 [US3] Implement or update `ProfileController` for GET and canonical
  PATCH plus multipart POST `_method=PATCH` transport compatibility
  (`FR-003`, `FR-078`–`FR-101`, `API-006`).
- [X] T077 [P] [US3] Add profile-shape and mutation tests in
  `tests/Feature/Api/V1/Admin/Auth/ProfileTest.php` for exact fields, forbidden
  fields, allowed request keys, name boundaries, inactive owner, and no token
  rotation (`FR-078`–`FR-083`, `FR-101`, `VER-009`, `VER-010`).
- [X] T078 [P] [US3] Add avatar validation and compensation tests covering
  formats, MIME, 2 MB limit, generated filename, approved disk, public URL,
  absent/empty preservation, string path rejection, DB failure, post-commit
  cleanup failure, safe logging, and absence of cleanup jobs
  (`FR-084`–`FR-100`, `FR-201`, `FR-209`, `VER-015`).

---

# Phase 9: User Story 4 — Change Password

**Goal:** replace the authenticated password and revoke all authentication
state.

- [X] T079 [US4] Implement
  `app/Actions/Auth/ChangeAdminPasswordAction.php` for current-password proof,
  policy enforcement, new/current difference, approved hashing, all-session
  revocation, and active reset-workflow invalidation
  (`FR-092`–`FR-115`, `FR-195`, `DATA-003`).
- [X] T080 [US4] Implement or update the Change Password controller and route
  using protected middleware, `5/min` per authenticated Administrator, exact
  errors, typed success, no replacement token, no cookie, and required headers
  (`FR-099`–`FR-116`, `ERR-007`, `AUTH-004`).
- [X] T081 [P] [US4] Add Change Password validation tests for required keys,
  secret non-transformation, every password-policy component, confirmation,
  and current-password difference
  (`FR-092`–`FR-110`, `VER-014`).
- [X] T082 [P] [US4] Add Change Password domain tests for
  `CURRENT_PASSWORD_INVALID`, rate limiting, inactive owner, all-session
  revocation, reset-workflow invalidation, no new token, no cookie, and headers
  (`FR-111`–`FR-116`, `ERR-005`, `ERR-007`, `VER-005`,
  `VER-011`, `VER-014`).

---

# Phase 10: User Story 5 — Forgot Password, Verify Code, and Reset Password

**Goal:** implement enumeration-safe recovery with deterministic concurrency
and one-time credentials.

- [X] T083 [P] [US5] Implement localized recovery mail under
  `app/Mail/` and `resources/views/mail/` or repository equivalents containing
  only the six-digit code, ten-minute expiry statement, and
  ignore-if-unrequested guidance
  (`FR-129`–`FR-131`, `FR-143`–`FR-145`, `FR-207`).
- [X] T084 [US5] Implement
  `app/Actions/Auth/SendForgotPasswordCodeAction.php` using normalized-email
  lookup, eligibility check, stable `users` row transaction lock, under-lock
  eligibility/cooldown recheck, previous-workflow invalidation, one new
  workflow, commit-before-mail, synchronous localized mail, and targeted
  mail-failure invalidation
  (`FR-106`–`FR-145`, `DATA-003`, `DATA-004`).
- [X] T085 [US5] Implement or update the Forgot Password controller and route
  with exact `200` enumeration boundary, `422`, `429`, and `503` distinctions,
  typed success, and required headers
  (`FR-117`–`FR-145`, `ERR-004`, `ERR-005`, `ERR-010`).
- [X] T086 [US5] Implement
  `app/Actions/Auth/VerifyForgotPasswordCodeAction.php` with workflow row lock,
  safe failed-attempt increment, fifth-attempt consumption, expiry/state
  checks, one Reset Token issuance, code consumption, SHA-256 Reset Token
  persistence, and one concurrent winner
  (`FR-146`–`FR-155`, `FR-198`, `FR-199`,
  `FR-206`, `FR-216`, `DATA-003`, `DATA-004`).
- [X] T087 [US5] Implement or update the Verify Code controller using exact
  `PASSWORD_RESET_CODE_INVALID` behavior and typed success containing only
  `resetToken` and `resetTokenExpiresIn: 600`
  (`FR-146`–`FR-155`, `ERR-008`).
- [X] T088 [US5] Implement
  `app/Actions/Auth/ResetForgottenPasswordAction.php` with normalized email,
  unmodified secrets, policy enforcement, transaction, row lock, one winner,
  workflow consumption, approved password hashing, all-session revocation, and
  no replacement token
  (`FR-158`–`FR-167`, `FR-195`, `DATA-003`, `DATA-004`).
- [X] T089 [US5] Implement or update the Reset Password controller using exact
  `PASSWORD_RESET_TOKEN_INVALID`, typed success, no cookie, and required
  headers (`FR-158`–`FR-167`, `ERR-009`).
- [X] T090 [P] [US5] Add Forgot Password response-boundary tests for eligible
  success, unknown email, inactive Admin, validation, rate limiting, mail
  failure, no enumeration leakage, no workflow/mail for ineligible targets,
  and targeted invalidation
  (`FR-117`–`FR-145`, `ERR-004`, `ERR-005`, `ERR-010`,
  `VER-013`, `VER-036`, `VER-037`).
- [X] T091 [US5] Add real MySQL concurrency tests for two eligible first-time
  Forgot Password requests, proving at most one newly usable workflow and one
  mail transport invocation for the serialized outcome
  (`FR-125`–`FR-142`, `DATA-004`, `VER-013`).
- [X] T092 [P] [US5] Add Verify Code tests for exact input, non-transformation,
  expiry, failed-attempt increments, fifth-attempt consumption, every unusable
  state, exact stable error, secure Reset Token properties, and one concurrent
  winner (`FR-146`–`FR-157`, `FR-216`, `ERR-008`,
  `VER-044`, `VER-045`).
- [X] T093 [P] [US5] Add Reset Password tests for non-transformation, policy,
  confirmation, expiry, invalid/consumed state, one concurrent winner,
  workflow consumption, all-session revocation, no new token, no cookie, and
  required headers
  (`FR-158`–`FR-167`, `ERR-009`, `VER-005`, `VER-011`,
  `VER-014`).
- [X] T094 [P] [US5] Add recovery mail content, locale, synchronous-delivery,
  commit ordering, and secret-safety tests
  (`FR-129`–`FR-145`, `FR-184`, `FR-200`, `FR-207`,
  `FR-208`, `VER-012`, `VER-013`).

---

# Phase 11: User Story 6 — CORS, Localization, Stable Errors, and Safety

**Goal:** enforce the direct separate-domain browser policy and safe localized
contracts.

- [X] T095 [US6] Implement exact CORS in `config/cors.php` using only configured
  `ADMIN_FRONTEND_ORIGIN`, documented methods plus `OPTIONS`, approved request
  and exposed headers, `supports_credentials=false`, no wildcard, and no
  arbitrary Origin reflection
  (`FR-166`–`FR-176`, `FR-183`–`FR-193`).
- [X] T096 [US6] Ensure non-browser clients remain token-authenticated without
  browser Origin or CSRF dependencies and protected operations continue to use
  Bearer authentication
  (`FR-055`–`FR-060`, `FR-175`, `FR-176`, `FR-192`,
  `FR-193`, `AUTH-001`–`AUTH-004`).
- [X] T097 [P] [US6] Add CORS tests in
  `tests/Feature/Api/V1/Admin/Auth/CorsTest.php` for approved actual requests,
  approved preflight, unapproved origin, wildcard absence, reflection absence,
  approved methods/headers, exposed headers, and
  `supports_credentials=false`
  (`FR-183`–`FR-193`, `VER-007`, `VER-008`).
- [X] T098 [P] [US6] Add stable error inventory tests covering every code/status
  pair and proving obsolete `ORIGIN_NOT_ALLOWED` and `CSRF_TOKEN_MISMATCH`
  never appear (`ERR-001`–`ERR-017`, `VER-010`, `VER-030`).
- [X] T099 [P] [US6] Add endpoint-wide response safety tests proving required
  locale/cache headers, exact envelopes, typed data, no `code: null`, and no
  undocumented metadata on success and failure
  (`FR-152`–`FR-182`, `API-005`, `API-007`,
  `VER-009`–`VER-011`).
- [X] T100 [P] [US6] Add endpoint-wide secret-leakage tests across responses,
  validation errors, exceptions, logs, analytics adapters, URLs, mail, and
  browser-facing metadata
  (`FR-177`, `FR-184`, `FR-194`–`FR-208`, `FR-220`,
  `VER-012`, `VER-038`).
- [X] T101 [US6] Complete
  `tests/Architecture/AuthenticationArchitectureTest.php` for exact route
  count, operation count, middleware order, actor boundary, absence of
  customer/public auth, and absence of cookie/CSRF/Origin-refresh/Proxy/BFF
  implementation dependencies
  (`FR-001`–`FR-007`, `FR-054`–`FR-062`,
  `ERR-016`, `ERR-017`, `VER-006`, `VER-016`,
  `VER-031`, `VER-032`, `VER-040`).

---

# Phase 12: External React Admin Frontend

**Goal:** implement mandatory client responsibilities in the owning React
repository.

> These tasks must be executed in the actual React repository after inspection.
> If that repository or its evidence is unavailable, leave the tasks unchecked,
> record them as blocked, and do not claim full Feature 001 completion.

- [ ] T102 Inspect the owning React repository and record actual API client,
  state-management, routing, storage, testing, CSP, build, and deployment paths
  in its Feature 001 implementation note
  (`FR-036`–`FR-053`, `FR-141`, `FR-142`, `FR-156`,
  `FR-157`, `FR-221`, `VER-017`–`VER-029`, `VER-040`).
- [ ] T103 Implement direct backend base URL configuration and an HTTP client
  using `Authorization: Bearer`, `withCredentials=false`, Accept-Language, and
  no Proxy/BFF or cookie behavior
  (`FR-007`, `FR-024`, `FR-034`, `FR-042`, `FR-052`,
  `FR-166`–`FR-176`, `VER-025`).
- [ ] T104 Implement Access Token runtime-memory storage only; prohibit
  `localStorage`, `sessionStorage`, IndexedDB, cookies, URLs, persisted stores,
  analytics, monitoring, logs, and browser console
  (`FR-036`, `FR-037`, `FR-052`, `VER-017`, `VER-019`,
  `VER-026`).
- [ ] T105 Implement Refresh Token `sessionStorage` storage only; prohibit all
  other persistent/browser leakage destinations and replace the stored value
  after successful rotation
  (`FR-038`, `FR-039`, `FR-044`, `FR-052`,
  `VER-018`, `VER-020`, `VER-022`, `VER-026`).
- [ ] T106 Implement same-tab reload bootstrap: read the Refresh Token from
  `sessionStorage`, call body-only refresh, keep the new Access Token in memory,
  replace the Refresh Token, and clear auth state on terminal failure
  (`FR-038`, `FR-054`–`FR-074`, `VER-021`–`VER-023`).
- [ ] T107 Implement logout and global auth-state clearing, including Access
  Token memory, Refresh Token `sessionStorage`, profile state, pending Reset
  Token state, and failed-refresh state
  (`FR-065`–`FR-077`, `FR-141`, `FR-142`,
  `VER-023`, `VER-024`).
- [ ] T108 Implement Reset Token runtime-memory-only handling for recovery
  verification through password reset; prohibit storage in
  `sessionStorage`, `localStorage`, IndexedDB, cookies, URLs, analytics,
  monitoring, logs, and console
  (`FR-141`, `FR-142`, `FR-156`, `FR-157`).
- [ ] T109 Implement client-side XSS compensating controls: strict CSP
  integration, no `eval`, no unapproved `unsafe-inline`, approved
  sanitization boundary for dangerous HTML, output encoding, dependency lock
  enforcement, dependency vulnerability review, and token-safe error reporting
  (`FR-044`–`FR-053`, `VER-027`–`VER-029`).
- [ ] T110 [P] Add frontend automated tests for Access Token memory-only,
  Refresh Token `sessionStorage`-only, Reset Token memory-only, and absence of
  prohibited storage/leakage
  (`VER-017`–`VER-020`, `VER-026`).
- [ ] T111 [P] Add frontend automated tests for reload bootstrap, Refresh Token
  replacement, Authorization injection, retry-loop prevention,
  `withCredentials=false`, terminal failure clearing, and logout clearing
  (`VER-021`–`VER-025`).
- [ ] T112 Produce frontend verification evidence and deployment CSP evidence;
  if unavailable, mark Feature 001 externally blocked rather than complete
  (`FR-221`, `VER-026`–`VER-029`, `VER-040`).

---

# Phase 13: OpenAPI, Postman, Deployment, and Documentation

**Goal:** align delivery and operator artifacts with the implemented contract.

- [X] T113 Install the synchronized OpenAPI file at
  `specs/001-identity-authentication/contracts/openapi.yaml` and update it only
  for repository-derived implementation details that do not alter the frozen
  contract (`API-001`–`API-007`, `VER-030`, `VER-031`).
- [X] T114 Synchronize
  `postman/Service-Commerce.postman_collection.json` with all nine operations,
  JSON token transport, Bearer protected routes, token rotation, stable errors,
  Accept-Language, required assertions, and no cookie/CSRF/Origin behavior
  (`FR-001`–`FR-007`, `FR-054`–`FR-077`, `FR-224`, `FR-225`,
  `FR-168`–`FR-193`, `API-001`–`API-007`).
- [X] T115 Synchronize
  `postman/Service-Commerce.local.postman_environment.json.example` with backend
  base URL, example Admin origin, runtime collection variables for tokens, and
  no committed secrets
  (`FR-166`–`FR-184`, `FR-194`–`FR-208`).
- [X] T116 Update `docs/deployment/separate-domain-admin-auth-cutover.md` with
  exact `ADMIN_FRONTEND_ORIGIN`, trusted proxies, HTTPS, CORS,
  `supports_credentials=false`, mail, public storage, cache headers, backend
  rollback cautions, React evidence, and CSP verification
  (`FR-044`–`FR-053`, `FR-166`–`FR-193`,
  `FR-211`, `FR-221`, `VER-027`–`VER-040`).
- [X] T117 Update `docs/features/001-identity-authentication.md` only where the
  governing feature reference must reflect the already-approved direct-token
  contract; do not weaken or replace `spec.md`
  (`FR-001`–`FR-225`).
- [X] T118 Update `specs/001-identity-authentication/quickstart.md` with actual
  repository commands, test paths, staging URLs, and smoke evidence while
  preserving the synchronized scenarios
  (`VER-001`–`VER-016`, `VER-041`, `VER-042`,
  `VER-044`–`VER-047`).
- [X] T119 Add Postman or scripted smoke assertions for Access Token predecessor
  revocation, Refresh throttle key fingerprinting with IP-only fallback,
  Refresh Token reuse, Forgot Password 200/422/429/503 boundaries, exact
  profile shape, and required headers
  (`FR-068`, `FR-069`, `FR-078`, `FR-120`, `FR-121`,
  `FR-173`–`FR-176`).

---

# Phase 14: Automated Verification and Quality Gates

**Goal:** prove the implementation against every backend-verifiable contract.

- [X] T120 Run focused backend feature suites for provisioning, login, refresh,
  logout, profile, avatar, password change, recovery, CORS, localization,
  envelopes, secret safety, and architecture
  (`VER-001`–`VER-016`, `VER-041`, `VER-042`,
  `VER-044`–`VER-047`).
- [X] T121 Run Refresh, Forgot Password, Verify Code, and Reset Password
  concurrency suites against MySQL rather than an engine that does not model
  the required row locking
  (`FR-069`, `FR-125`–`FR-142`, `FR-148`–`FR-155`,
  `FR-162`–`FR-164`, `DATA-002`, `DATA-004`).
- [ ] T122 Run repository-supported formatter, linter, static analysis, type
  checks, dependency audit, and test coverage checks for all modified backend
  files and record results in
  `specs/001-identity-authentication/verification-report.md`
  (`FR-050`, `FR-051`).
- [X] T123 Validate OpenAPI 3.1 syntax, all local `$ref` values, nine operations,
  eight unique paths, unique `operationId` values, Accept-Language parameters,
  required headers, stable error `const` values, CORS metadata, and absence of
  obsolete cookie/CSRF/Origin-refresh contracts
  (`API-001`–`API-007`, `ERR-001`–`ERR-017`,
  `VER-009`–`VER-011`, `VER-016`, `VER-030`–`VER-032`).
- [X] T124 Run a repository-wide secret and obsolete-concept scan for plaintext
  token/password/code output, Authorization logging, raw avatar paths,
  `Set-Cookie`, auth cookie names, CSRF services, Origin-refresh middleware,
  Proxy/BFF phases, `ORIGIN_NOT_ALLOWED`, and `CSRF_TOKEN_MISMATCH`
  (`FR-034`, `FR-064`, `FR-074`, `FR-077`, `FR-116`,
  `FR-151`, `FR-194`–`FR-220`, `ERR-016`, `ERR-017`,
  `VER-005`, `VER-006`, `VER-012`, `VER-032`).
- [X] T125 Run deployment-level CORS and response-header checks from the
  approved and unapproved origins, including trusted-proxy requester-IP
  behavior, HTTPS, cache headers, and no credentialed CORS
  (`FR-166`–`FR-193`, `FR-211`, `VER-007`, `VER-008`,
  `VER-011`).

---

# Phase 15: Final Cross-Artifact and Completion Audit

**Goal:** prevent implementation or documentation drift before declaring the
feature complete.

- [X] T126 Re-run requirement traceability and prove every active `FR`, `API`,
  `ERR`, `AUTH`, `DATA`, and `VER` identifier is covered by implementation,
  test, external evidence, or an explicitly blocked external task
  (`VER-033`, `VER-039`).
- [X] T127 Compare routes and implementation responses against OpenAPI and
  Postman for exact keys, statuses, headers, throttles, operation count, and
  route count; fix code/docs drift without changing the frozen spec
  (`API-001`–`API-007`, `VER-009`, `VER-010`, `VER-016`).
- [X] T128 Confirm post-refresh authoritative state contains only the
  replacement Access Token and Refresh Token and that predecessor Access
  Tokens return `401 UNAUTHENTICATED`
  (`FR-035`, `FR-043`, `FR-070`–`FR-073`, `FR-224`, `FR-225`,
  `DATA-002`, `DATA-007`, `VER-003`, `VER-050`).
- [X] T129 Confirm one active Administrator session remains global across Web
  and future Mobile and that login replacement, Refresh Token reuse, logout,
  password change, and password reset enforce the documented revocation scope
  (`FR-032`, `FR-035`, `FR-043`, `FR-073`, `FR-075`,
  `FR-113`, `FR-149`, `FR-165`, `DATA-002`).
- [X] T130 Confirm no auth Queue Job, cleanup Command, Cron task, or scheduler
  entry was introduced and cleanup remains narrow and owner-scoped
  (`FR-100`, `FR-209`).
- [X] T131 Confirm frontend verification and production CSP evidence are
  present; otherwise mark the feature `Blocked: External React verification`
  and do not claim full completion
  (`FR-221`, `VER-017`–`VER-029`, `VER-040`).
- [X] T132 Produce
  `specs/001-identity-authentication/final-completeness-report.md` listing
  modified files, migrations, routes, tests, OpenAPI/Postman validation,
  deployment checks, frontend evidence, remaining blockers, and confirmation
  that `spec.md` and `requirements.md` were not modified.

---

# Dependencies and Execution Order

## Mandatory Phase Order

```text
Phase 1
-> Phase 2
-> Phase 3
-> Phase 4
-> Phase 5
-> User Story phases 6–11
-> Phase 13
-> Phase 14
-> Phase 15
```

## User Story Dependencies

```text
US1 Login:
requires Phases 1–5

US2 Refresh/Logout:
requires Phases 1–5 and shared session services
may proceed after US1 token issuance contract is stable

US3 Profile:
requires protected routes and profile resource
may run in parallel with US2 implementation after Phase 5

US4 Change Password:
requires password policy and session revocation service
may run in parallel with US3 after Phase 5

US5 Recovery:
requires Password Reset persistence and recovery services
may run in parallel with US2–US4 after Phase 5

US6 CORS/Localization/Safety:
shared foundations begin in Phase 2
final endpoint-wide verification follows US1–US5
```

## Parallel Work Examples

```text
After Phase 3:
T031, T032, T033, T035, T036, T037 can be split by file ownership.

After Phase 5:
US3, US4, and most US5 implementation may proceed in parallel.

External React:
T102–T112 can proceed in parallel with backend story implementation after the
contract is frozen.

Testing:
tasks marked [P] may run in parallel when their implementation dependencies
exist.
```

---

# Requirement Coverage Index

| Requirement Group | Primary Tasks |
|---|---|
| `FR-001`–`FR-007` | T001, T008, T051, T053, T101, T114 |
| `FR-008`–`FR-022` | T004, T025–T030 |
| `FR-023`–`FR-035` | T049, T055–T060 |
| `FR-036`–`FR-053` | T102–T112, T116 |
| `FR-054`–`FR-062` | T001, T051–T054, T101 |
| `FR-063`–`FR-074` | T042, T061–T071 |
| `FR-075`–`FR-077` | T065, T072, T107 |
| `FR-078`–`FR-101` | T043, T048, T073–T078 |
| `FR-102`–`FR-116` | T035, T044, T079–T082 |
| `FR-117`–`FR-145` | T036, T045, T083–T085, T090–T094 |
| `FR-146`–`FR-157` | T046, T086, T087, T092 |
| `FR-158`–`FR-167` | T047, T088, T089, T093 |
| `FR-168`–`FR-182` | T009–T014, T099 |
| `FR-183`–`FR-193` | T004, T095–T097, T116, T125 |
| `FR-194`–`FR-209` | T015, T018, T030, T078, T094, T100, T124, T130 |
| `FR-210`–`FR-213` | T038, T061, T067, T119 |
| `FR-214`, `FR-216`, `FR-218`, `FR-219` | T031, T039, T092 |
| `FR-220` | T015, T033, T071, T100 |
| `FR-221` | T102, T112, T116, T131 |
| `FR-224`, `FR-225` | T062, T068, T070, T114, T128 |
| `API-001`–`API-007` | T009, T041, T042, T048–T050, T054, T056, T064, T113, T123 |
| `ERR-001`–`ERR-017` | T010, T011, endpoint tests, T098, T123, T124 |
| `AUTH-001`–`AUTH-004` | T001, T005, T051, T052, T055, T072, T096 |
| `DATA-001`–`DATA-007` | T020–T024, T028, T030, T032–T040, T062, T070, T128, concurrency tasks |
| `VER-001`–`VER-016` | Backend implementation and tests T029, T058–T101, T120 |
| `VER-017`–`VER-029` | External React tasks T102–T112 |
| `VER-030`–`VER-040` | Artifact, architecture, and completion tasks T003, T007, T101, T113, T116, T123, T126, T131 |
| `VER-041`, `VER-042` | T038, T061, T067, T120 |
| `VER-044`–`VER-047` | T031, T039, T092, T120 |
| `VER-050` | T068, T128 |

---

# Non-Negotiable Completion Rules

- [ ] The implementation must not change the approved architecture.
- [ ] `spec.md` and `requirements.md` remain frozen.
- [ ] No task is marked complete solely because code was written; named tests or
  evidence must pass.
- [ ] MySQL concurrency behavior must be tested with MySQL.
- [ ] External React tasks remain blocked when the owning repository is absent.
- [ ] Full Feature 001 completion is not claimed without frontend and deployment
  evidence.
- [ ] No authentication cookie, CSRF, Origin-gated refresh, Proxy/BFF,
  Cloudflare Worker, browser session, or JWT replacement is introduced.
- [ ] Successful refresh leaves only the replacement Access Token and Refresh
  Token active.
- [ ] The final auth surface remains exactly nine operations across eight paths.
