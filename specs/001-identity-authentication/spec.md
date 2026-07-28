# Feature Specification: Identity and Authentication

**Feature Branch**: `00-identity-authentiaction`
**Created**: 2026-07-28
**Status**: Ready for Planning
**Input**: Build the first Service Commerce feature from
[`docs/features/001-identity-authentication.md`](../../docs/features/001-identity-authentication.md)
and preserve every approved requirement.

## Governing Reference and Requirement Commitment

The approved Feature 001 document is the normative source for this feature.
Every `MUST`, `MUST NOT`, required behavior, prohibition, validation rule,
response contract, security constraint, test obligation, and definition-of-done
item in that document remains binding. This specification groups those
requirements into planning-ready outcomes; it does not replace, weaken, or
silently reinterpret them.

Planning and task generation MUST maintain traceability back to both this
specification and the approved Feature 001 reference. Where this specification
summarizes an implementation-specific rule, the more detailed rule in the
approved Feature 001 reference remains controlling.

The following governing artifacts were reviewed:

- `.specify/memory/constitution.md`
- `AGENTS.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- all applicable files under `docs/02-standards/`
- `docs/features/001-identity-authentication.md`

No unresolved conflict or clarification remains.

## Scope

### In Scope

- Secure, environment-backed initial Super Admin provisioning.
- Administrator login, access-token issuance, refresh-token rotation, refresh
  reuse detection, one-active-session enforcement, and logout.
- Retrieval and update of the authenticated administrator profile.
- Administrator avatar replacement through the approved public-storage flow.
- Authenticated password change.
- Synchronous password-recovery email, recovery-code verification, and
  one-time password reset.
- Inactive-account enforcement, authorization boundaries, localization,
  security headers, throttling, safe security logging, and error contracts.
- Opportunistic authentication-record cleanup.
- The approved Postman collection entries and complete automated verification
  defined by the Feature 001 reference.

### Out of Scope

- Customer registration, login, profile, password reset, or customer tokens.
- Public-site authentication.
- Additional administrator accounts in the MVP.
- User, role, or permission CRUD.
- Multiple administrator roles, MFA, social login, email verification,
  remember-me behavior, device management, session listing, or login history.
- Authentication queue Jobs, cleanup Commands, Cron tasks, scheduler entries,
  Redis, or Horizon.
- Avatar deletion without replacement.
- Frontend implementation or browser persistence code.

## User Scenarios and Testing

### User Story 1 - Provision and Sign In as the Super Admin (Priority: P1)

As the system owner, I can provision the one approved Super Admin securely and
sign in through the administration authentication API so that I can access the
administration dashboard.

**Why this priority**: No administration capability is usable until the
approved administrator can be provisioned and authenticated.

**Independent Test**: Provision from valid environment-backed values, submit
valid credentials, and confirm a short-lived access token, the exact profile
shape, and the two approved cookies are issued.

**Acceptance Scenarios**:

1. **Given** valid provisioning configuration and no administrator, **When**
   provisioning runs, **Then** one active administrator is created with a
   normalized email, securely hashed password, administrator type, and the
   `super-admin` role.
2. **Given** provisioning already succeeded, **When** it runs again with the
   same normalized email, **Then** it is idempotent and does not create a
   duplicate administrator or role.
3. **Given** valid active-administrator credentials, **When** login succeeds,
   **Then** the response contains a 15-minute access token and only the approved
   profile fields, while the refresh token is set only in its cookie.
4. **Given** an unknown email or incorrect password, **When** login is
   attempted, **Then** the same generic invalid-credentials response is
   returned without revealing which value was wrong.
5. **Given** a valid but inactive administrator, **When** login is attempted,
   **Then** access is rejected with the approved inactive-account response.
6. **Given** an existing active session, **When** the same administrator logs
   in again, **Then** the old access and refresh credentials are revoked and
   only the new session remains active.

---

### User Story 2 - Maintain and End a Secure Session (Priority: P1)

As an authenticated Super Admin, I can refresh an expired access token and log
out securely without exposing the long-lived credential to JavaScript.

**Why this priority**: Session continuity, rotation, and revocation are central
security boundaries for every protected administration feature.

**Independent Test**: Log in, refresh with valid Origin and CSRF values, verify
credential rotation, try to reuse the old refresh token, and then log out.

**Acceptance Scenarios**:

1. **Given** valid refresh and CSRF cookies plus matching header and approved
   Origin, **When** refresh is requested, **Then** a new access token is
   returned, the refresh token is rotated, and no refresh token appears in
   JSON.
2. **Given** a missing, malformed, expired, revoked, or otherwise invalid
   refresh credential, **When** refresh is requested, **Then** the generic
   refresh-token error is returned and both authentication cookies are cleared.
3. **Given** a previously rotated refresh token, **When** it is reused, **Then**
   all sessions for the administrator are revoked and reuse is logged safely.
4. **Given** two concurrent requests using one refresh token, **When** they are
   processed, **Then** at most one succeeds.
5. **Given** an authenticated active administrator, **When** logout succeeds,
   **Then** all access and refresh credentials are revoked and both cookies are
   cleared.

---

### User Story 3 - View and Update the Authentication Profile (Priority: P2)

As an authenticated Super Admin, I can view my authentication profile and
update my name or replace my avatar without being able to change identity,
role, permissions, or account state.

**Why this priority**: Profile self-service is useful after authentication, but
does not block secure administration access.

**Independent Test**: Retrieve the profile, update the name through JSON,
replace the avatar through multipart method spoofing, and verify forbidden
fields cannot mutate privileged data.

**Acceptance Scenarios**:

1. **Given** an authenticated active administrator, **When** the profile is
   requested, **Then** it contains exactly `name`, `email`, `avatar`, `role`,
   and `permissions`.
2. **Given** a valid new name, **When** the profile is updated through JSON,
   **Then** the name changes without rotating the active session.
3. **Given** a valid image submitted through multipart
   `POST /api/v1/admin/auth/profile` with `_method=PATCH`, **When** the update
   succeeds, **Then** the new file metadata is committed, old-file deletion is
   attempted synchronously, and `avatar` contains the approved public URL.
4. **Given** an existing avatar, **When** `avatar` is absent or an empty string,
   **Then** the existing avatar is preserved.
5. **Given** a database failure after storing a replacement, **When** the
   update fails, **Then** the new file is deleted and the old avatar metadata
   remains authoritative.
6. **Given** old-file deletion fails after the database commit, **When** the
   replacement completes, **Then** the API still succeeds and the cleanup
   failure is logged without exposing a path.
7. **Given** identity or privilege fields in the request, **When** profile
   update is attempted, **Then** those fields cannot change email, role,
   permissions, type, active state, or stored paths.

---

### User Story 4 - Change the Authenticated Password (Priority: P2)

As an authenticated Super Admin, I can change my password after proving my
current password so that a known credential can be replaced immediately.

**Why this priority**: Authenticated password maintenance is a required account
security control.

**Independent Test**: Change the password using a valid current password and a
compliant new password, then verify every existing access and refresh
credential is revoked.

**Acceptance Scenarios**:

1. **Given** a correct current password and a confirmed compliant new password,
   **When** password change succeeds, **Then** the password is updated and all
   sessions are revoked.
2. **Given** an incorrect current password, **When** password change is
   attempted, **Then** the approved validation error is returned and no
   credential changes.
3. **Given** a new password equal to the current password or one that violates
   policy, **When** password change is attempted, **Then** validation fails.

---

### User Story 5 - Recover a Forgotten Password (Priority: P2)

As the Super Admin, I can request a short-lived recovery code, exchange it for a
strong one-time reset token, and set a new password without revealing whether
an arbitrary account exists.

**Why this priority**: A secure recovery path prevents permanent lockout while
preserving enumeration and credential protections.

**Independent Test**: Request a code for the active administrator, inspect only
the delivered email in the test transport, verify the code, reset the password,
and confirm the workflow and all sessions are consumed.

**Acceptance Scenarios**:

1. **Given** an active administrator email, **When** recovery is requested,
   **Then** a hashed six-digit code with a ten-minute lifetime is committed
   before a localized email is sent synchronously.
2. **Given** an unknown or inactive email, **When** recovery is requested,
   **Then** the same generic accepted response is returned and no reset record
   or email is created.
3. **Given** direct email delivery fails, **When** the request is processed,
   **Then** the committed workflow is invalidated and the API returns
   `503 MAIL_SERVICE_UNAVAILABLE` without logging the code.
4. **Given** a valid unexpired code, **When** verification succeeds, **Then**
   the code becomes unusable and a one-time reset token valid for ten minutes
   is returned once.
5. **Given** an incorrect code, **When** it is submitted, **Then** the attempt
   count increases; on the fifth failed attempt the workflow becomes unusable.
6. **Given** concurrent verification requests for one valid code, **When** they
   are processed, **Then** at most one reset token is issued.
7. **Given** a valid reset token and confirmed compliant password, **When**
   reset succeeds, **Then** the password changes, the workflow is consumed, and
   all administrator sessions are revoked.
8. **Given** concurrent reset requests with one reset token, **When** they are
   processed, **Then** at most one changes the password.

---

### User Story 6 - Receive Safe, Localized Authentication Responses (Priority: P1)

As an API consumer, I receive stable machine-readable results and localized
human-readable messages without secret or internal-data leakage.

**Why this priority**: Every authentication flow depends on consistent,
secure, bilingual responses.

**Independent Test**: Exercise success, validation, inactive, unauthenticated,
forbidden, throttled, and internal-error cases in Arabic and English.

**Acceptance Scenarios**:

1. **Given** `Accept-Language: ar` or `en`, **When** an authentication response
   is returned, **Then** its user-facing message and language metadata use the
   resolved supported locale.
2. **Given** no supported locale, **When** a response is returned, **Then**
   Arabic is used as the default and English remains the fallback.
3. **Given** any authentication failure, **When** it is logged or returned,
   **Then** plaintext passwords, tokens, reset codes, cookie values, raw avatar
   paths, and stack traces are absent.
4. **Given** an inactive administrator with a previously issued access token,
   **When** a protected endpoint is requested, **Then** the request is rejected
   consistently.

## Edge Cases

- Email input is normalized consistently, while password input is never
  trimmed or silently transformed.
- Missing required production provisioning secrets fail safely and never
  generate a default production password.
- A refresh request with valid cookies but a missing, mismatched, or malformed
  CSRF header is rejected before token rotation.
- A refresh request from an unapproved or absent browser Origin is rejected
  according to the exact-Origin policy.
- An expired access token may be replaced through refresh, but an expired
  refresh token cannot be extended or revived.
- An inactive or deleted token owner cannot refresh an existing session.
- A non-empty avatar string, URL, or path is invalid; only an approved uploaded
  file may replace the avatar.
- A recovery resend inside the 60-second cooldown is rejected without creating
  an additional usable workflow.
- Expired, consumed, attempt-limited, or already-verified recovery codes return
  the same safe failure contract where required.
- A reset token is never accepted for a different normalized email or account.
- Opportunistic cleanup operates only on safely resolved user/workflow scope;
  it does not perform an unbounded global scan.

## Requirements

### Functional Requirements

- **FR-001**: The system MUST recognize the Super Admin as the only
  authenticated MVP actor; customers remain unauthenticated guest domain
  records and MUST NOT receive authentication endpoints or tokens.
- **FR-002**: The system MUST expose exactly these canonical authentication
  operations under `/api/v1/admin/auth/*`: login, refresh, logout, profile show,
  profile update, change password, forgot password, verify forgot-password code,
  and reset password.
- **FR-003**: The system MUST NOT create `/api/v1/auth/*` or alternate
  registration, `me`, password, logout-all, sessions, email-verification, or
  verification-resend routes.
- **FR-004**: Public authentication operations and protected operations MUST use
  the exact middleware responsibilities, ordering, throttles, active-account
  checks, Origin validation, and CSRF validation defined in Feature 001.
- **FR-005**: Authenticated identities MUST be stored in `users`; the approved
  administrator type and active state MUST be enforced independently from
  roles and permissions.
- **FR-006**: The initial role MUST be the single `super-admin` role managed by
  the approved roles-and-permissions mechanism, with deterministic permission
  output and backend authorization.
- **FR-007**: Provisioning MUST read required values from environment-backed
  configuration, validate the approved password policy, normalize the email,
  hash the password, assign the role, and be idempotent.
- **FR-008**: Provisioning MUST fail safely when required production values are
  absent or invalid and MUST NOT contain a hard-coded production credential.
- **FR-009**: Login MUST accept only the approved email and password inputs,
  normalize email consistently, leave the password byte-for-byte unchanged,
  and validate the exact request contract.
- **FR-010**: Login MUST be limited to five attempts per minute using normalized
  email plus requester IP.
- **FR-011**: Unknown-email and wrong-password failures MUST share the generic
  `401 INVALID_CREDENTIALS` contract; inactive administrators MUST receive the
  approved `403 USER_INACTIVE` contract.
- **FR-012**: Successful login MUST atomically revoke prior access and refresh
  credentials for that administrator before issuing one new active session.
- **FR-013**: A successful login MUST return the plain Sanctum access token once
  in JSON with Bearer metadata and an exact 900-second lifetime.
- **FR-014**: The access token MUST be intended for React memory only and MUST
  NOT be placed in a cookie, local storage, session storage, or other persistent
  browser storage.
- **FR-015**: The login profile MUST contain only `name`, `email`, `avatar`,
  `role`, and `permissions`; it MUST omit user ID and every internal or alternate
  representation, use `avatar`, and use one role string rather than a roles
  array.
- **FR-016**: Refresh credentials MUST be high-entropy opaque values, returned
  only through the `admin_refresh_token` cookie, stored only as hashes in the
  `refresh_tokens` table, and owned polymorphically.
- **FR-017**: Refresh-token lifetime MUST be exactly 30 days
  (`2592000` seconds), and each record MUST preserve the approved family,
  expiry, rotation, revocation, and ownership metadata and indexes.
- **FR-018**: The refresh cookie MUST be HttpOnly, Secure in production,
  SameSite Strict, host-only by omitting Domain, scoped to
  `/api/v1/admin/auth`, and use a 2592000-second Max-Age.
- **FR-019**: The `admin_csrf_token` cookie MUST be readable by the frontend,
  Secure in production, SameSite Strict, host-only, and scoped to
  `/api/v1/admin/auth`; refresh MUST require its value in `X-CSRF-TOKEN`.
- **FR-020**: Browser refresh MUST also pass the exact approved administration
  Origin check and MUST NOT accept a refresh token in the request body.
- **FR-021**: Refresh MUST be limited to ten requests per minute by requester IP
  plus available session context.
- **FR-022**: Each successful refresh MUST use transactional row locking,
  rotate the refresh credential, revoke the predecessor, issue a new 900-second
  access token, return no profile, and return no refresh token in JSON.
- **FR-023**: Missing, malformed, expired, revoked, reused, ownerless, or
  inactive-owner refresh credentials MUST use the generic
  `401 REFRESH_TOKEN_INVALID` contract and clear both authentication cookies.
- **FR-024**: Reuse of a rotated refresh credential MUST revoke every access and
  refresh credential for the administrator and record the approved safe event.
- **FR-025**: Refresh revocation reasons MUST use the approved backed-enum
  values and MUST NOT use a native database enum.
- **FR-026**: Login, refresh, and logout MUST perform only the approved
  opportunistic, owner-scoped cleanup while retaining records required for
  reuse detection until eligible for deletion.
- **FR-027**: Logout MUST require an authenticated active administrator, revoke
  all administrator access and refresh credentials, clear both cookies with
  matching attributes, and succeed without creating a separate logout-all API.
- **FR-028**: Profile retrieval MUST return exactly the approved authentication
  profile and MUST NOT expose an ID, type, active flag, password metadata,
  token data, raw avatar storage data, or alternate avatar field.
- **FR-029**: Profile update MUST accept only `name`, `avatar`, and multipart
  `_method`; name MUST be trimmed and limited to 150 characters.
- **FR-030**: Profile update MUST reject email, roles, permissions, type, active
  state, stored paths, removal flags, and every other identity or privilege
  mutation.
- **FR-031**: Avatar replacement MUST accept only uploaded JPG, JPEG, PNG, or
  WebP files up to 2 MB, generate the stored name, use the approved public disk,
  and return only the approved public URL or `null`.
- **FR-032**: An absent or empty avatar value MUST preserve the current avatar;
  a non-empty string, path, or URL MUST be rejected; delete-only avatar behavior
  MUST NOT exist.
- **FR-033**: Avatar replacement MUST store the new file before the database
  update, delete it if persistence fails, commit new metadata before attempting
  synchronous old-file deletion, and log but not fail a committed update when
  old-file deletion fails.
- **FR-034**: Profile updates MUST NOT rotate access or refresh credentials.
- **FR-035**: Password change MUST require the exact current-password,
  new-password, and confirmation contract; the new password MUST have at least
  ten characters with mixed case, a number, and a symbol, and MUST differ from
  the current password.
- **FR-036**: Password change MUST be limited to five requests per minute per
  authenticated administrator and use the approved
  `CURRENT_PASSWORD_INVALID` failure for an incorrect current password.
- **FR-037**: Successful password change MUST update the hash transactionally,
  revoke all access and refresh credentials, and clear authentication cookies.
- **FR-038**: Forgot password MUST normalize and validate email, limit requests
  to five per minute by normalized email plus IP, enforce a 60-second resend
  cooldown, and always use the approved enumeration-safe accepted response.
- **FR-039**: Unknown and inactive recovery targets MUST create no reset record,
  send no email, and receive the same accepted response as an eligible target.
- **FR-040**: Eligible recovery requests MUST invalidate a previous usable
  workflow, generate a cryptographically random six-digit code, store it as a
  secure password hash in `password_resets`, and expire it after ten minutes.
- **FR-041**: Password-reset ownership MUST be polymorphic and each workflow
  MUST preserve normalized email, code expiry, attempt count, verification,
  hashed reset token, reset-token expiry, consumption state, and required
  indexes.
- **FR-042**: The recovery workflow MUST commit before sending localized email
  synchronously; it MUST NOT dispatch an authentication Job or Queue work.
- **FR-043**: Recovery mail MUST contain only the approved six-digit code,
  ten-minute expiry, and ignore-if-unrequested guidance; it MUST NOT contain a
  password, access token, refresh token, reset token, or privileged link.
- **FR-044**: If recovery email delivery fails, the workflow MUST be invalidated
  and the API MUST return `503 MAIL_SERVICE_UNAVAILABLE`; the code MUST NOT be
  logged.
- **FR-045**: Code verification MUST atomically validate the approved workflow
  state and code hash, increment failed attempts, consume the workflow on the
  fifth failure, and issue at most one reset token under concurrency.
- **FR-046**: Successful code verification MUST make the code unusable, return
  one high-entropy reset token once with a 600-second lifetime, and store only
  its unique SHA-256 hash.
- **FR-047**: The reset token MUST be intended for React memory only and MUST
  NOT be placed in cookies, persistent browser storage, logs, or URLs.
- **FR-048**: Password reset MUST require normalized email, one-time reset
  token, compliant password, and confirmation; it MUST atomically consume the
  workflow and permit at most one successful concurrent reset.
- **FR-049**: Successful reset MUST update the password and revoke all
  administrator sessions; no password-change confirmation email is required.
- **FR-050**: Recovery cleanup MUST be opportunistic and scoped; no
  authentication Queue Job, cleanup Command, Cron task, or scheduler entry may
  be introduced.
- **FR-051**: Every authentication success and failure MUST use the approved
  response envelope, HTTP status, stable English error code, generic-message
  boundary, and headers defined by Feature 001.
- **FR-052**: Authentication responses MUST apply the approved cache-prevention
  and security headers, including the documented `Cache-Control`, `Pragma`,
  `Expires`, language, and variation behavior where applicable.
- **FR-053**: Authentication localization MUST support `ar` and `en`, resolve
  `Accept-Language` before validation, use Arabic by default and English as the
  fallback, and preserve English machine identifiers.
- **FR-054**: Synchronous recovery email MUST use the locale resolved for that
  request.
- **FR-055**: The system MUST safely log the approved authentication security
  events without passwords, plaintext tokens, reset codes, cookie values,
  Authorization headers, raw paths, or sensitive request bodies.
- **FR-056**: The approved CORS and credential behavior MUST allow the external
  React administrator consumer only as documented, never use wildcard origins
  with credentialed administration requests, and retain the host-only cookie
  boundary.
- **FR-057**: Every protected endpoint MUST require both valid Sanctum
  authentication and an active administrator; self-service authentication
  operations do not grant the ability to mutate roles or permissions.
- **FR-058**: The Postman collection MUST contain all nine canonical routes,
  appropriate variables, cookies, headers, request examples, and success and
  failure examples defined in Feature 001.

### API Contract Requirements

- **API-001**: Canonical routes MUST be:
  `POST /api/v1/admin/auth/login`,
  `POST /api/v1/admin/auth/refresh`,
  `POST /api/v1/admin/auth/logout`,
  `GET /api/v1/admin/auth/profile`,
  `PATCH /api/v1/admin/auth/profile`,
  `PUT /api/v1/admin/auth/change-password`,
  `POST /api/v1/admin/auth/forgot-password`,
  `POST /api/v1/admin/auth/verify-forgot-password-code`, and
  `POST /api/v1/admin/auth/reset-password`.
- **API-002**: Multipart profile replacement MAY use
  `POST /api/v1/admin/auth/profile` only with `_method=PATCH`; this is transport
  compatibility for the canonical PATCH operation, not a tenth feature route.
- **API-003**: Success responses MUST use the approved `success`, localized
  `message`, and `data` envelope; error responses MUST use the approved
  `success`, localized `message`, stable `code`, `errors`, and correlation
  metadata contract where specified.
- **API-004**: Login JSON MUST include `accessToken`, `tokenType`,
  `tokenExpiresIn: 900`, `refreshTokenExpiresIn: 2592000`, and the approved
  profile; refresh JSON MUST include the new access-token metadata without a
  profile or refresh token.
- **API-005**: No authentication response MUST expose a user ID, refresh token,
  password, hash, raw avatar path, internal status/type, recovery code, or reset
  token except the one-time reset token returned by successful code exchange.

### Authorization and Trust Requirements

- **AUTH-001**: The backend, not the frontend, MUST be authoritative for
  identity, active state, administrator type, role, and permissions.
- **AUTH-002**: Authentication MUST execute before authorization, and an
  authenticated but inactive administrator MUST remain blocked from every
  protected route.
- **AUTH-003**: Client-supplied role, permission, type, active-state, email,
  storage path, token ownership, and revocation values MUST be ignored or
  rejected according to the approved request contract.
- **AUTH-004**: Account-type checks, role/permission checks, and active-account
  checks MUST remain separate controls.

### Data Integrity Requirements

- **DATA-001**: Normalized administrator email MUST be unique according to the
  approved user schema and normalization rules.
- **DATA-002**: `refresh_tokens.token_hash` MUST be unique and refresh records
  MUST have the approved ownership, family, expiry, rotation, and revocation
  indexes and relationships.
- **DATA-003**: `password_resets` MUST preserve the approved polymorphic owner,
  unique reset-token hash, expiry, normalized-email, and workflow indexes.
- **DATA-004**: Login replacement, refresh rotation, password change, code
  verification, and password reset MUST use transactions and row locks wherever
  Feature 001 requires single-winner behavior.
- **DATA-005**: External email delivery MUST occur only after the relevant
  database transaction commits and MUST never occur inside that transaction.
- **DATA-006**: Database rollback MUST be paired with the documented avatar
  filesystem compensation so database and storage state cannot silently
  diverge.

### Verification Requirements

- **VER-001**: Automated verification MUST cover every test inventory item in
  Feature 001 sections 60 through 70, including provisioning, login, access
  expiry, cookies, one-session enforcement, refresh rotation and reuse,
  concurrency, logout, profile, avatar compensation, password change, recovery,
  localization, error contracts, and security leakage.
- **VER-002**: Integration verification MUST use the project-approved MySQL
  environment for transaction, locking, uniqueness, and index-dependent
  behavior; SQLite-only evidence is insufficient.
- **VER-003**: Tests MUST prove the access-token lifetime is 900 seconds,
  refresh-token lifetime is 2592000 seconds, recovery code and reset-token
  lifetimes are 600 seconds, maximum verification attempts are five, and resend
  cooldown is 60 seconds.
- **VER-004**: Tests MUST prove no refresh token appears in JSON and no user ID,
  alternate avatar field, roles array, raw path, or internal user field appears
  in the authentication profile.
- **VER-005**: Tests MUST prove exactly one successful result for concurrent use
  of one refresh token, one recovery code, and one reset token.
- **VER-006**: Tests MUST prove authentication Queue Jobs, cleanup Commands,
  Cron tasks, and scheduler entries are absent.
- **VER-007**: The final feature review MUST satisfy every Definition of Done
  item in Feature 001 sections 74 and 75 before implementation is considered
  complete.

## Key Entities

- **Administrator User**: The one authenticated MVP identity, with normalized
  email, password hash, administrator type, active state, optional avatar
  storage metadata, and assigned role/permissions.
- **Role and Permission Assignment**: The authoritative backend authorization
  relationship, initially containing only `super-admin` while remaining ready
  for later approved roles.
- **Access Token**: A Sanctum personal access token with a 15-minute lifetime,
  returned once in JSON and used as a Bearer credential.
- **Refresh Token**: A 30-day opaque rotating credential stored as a hash,
  linked polymorphically to its owner, grouped by family, and delivered only
  through a protected host-only cookie.
- **Password Reset Workflow**: A polymorphic reset record that stores a hashed
  six-digit code, attempt and expiry state, verification state, a one-time
  hashed reset token, and consumption state.
- **Avatar**: Optional public-storage metadata controlled only by validated
  upload and replacement workflows; raw paths are never part of the API
  profile.

## Assumptions and Dependencies

- The approved Feature 001 reference fully resolves product and security
  decisions; no product clarification is required before planning.
- The React administration dashboard and its memory-only state are external to
  this backend repository; the backend contract and documentation still define
  the required storage boundary.
- Laravel Sanctum, the approved roles-and-permissions package, MySQL, mail
  configuration, locale middleware, and the public storage disk will be
  available or introduced by the implementation plan in accordance with
  governing project documentation.
- Authentication mail is the approved synchronous exception; the database
  queue remains reserved for separately approved asynchronous email use cases.

## Source Traceability

| Approved Feature 001 Sections | Coverage in This Specification |
|---|---|
| 1-6: purpose, scope, decisions, exclusions, dependencies | Scope, assumptions, FR-001, FR-050 |
| 7-18: routes, middleware, users, role, provisioning, profile, login, access token | User Stories 1 and 6; FR-002-FR-015; API-001-API-005 |
| 19-25: refresh persistence, issuance, cookies, refresh, rotation, reuse, cleanup | User Story 2; FR-016-FR-026; DATA-002, DATA-004 |
| 26-30: logout, profile show/update, avatar, password change | User Stories 2-4; FR-027-FR-037; DATA-006 |
| 31-41: recovery request, reset persistence, mail, verification, reset | User Story 5; FR-038-FR-050; DATA-003-DATA-005 |
| 42-48: cross-origin, headers, throttling, errors, localization | User Story 6; FR-051-FR-057; AUTH-001-AUTH-004 |
| 49-58: architecture, services, resources, middleware, transactions, logs, configuration, Postman | FR-055-FR-058; DATA-004-DATA-006; assumptions |
| 59-70: testing strategy and test inventory | VER-001-VER-006 |
| 71: acceptance scenarios | User Stories 1-6 and edge cases |
| 72-73: implementation sequence and file plan | Preserved as mandatory planning input through the governing-reference commitment |
| 74-75: Definition of Done and non-negotiable summary | VER-007 and the governing-reference commitment |

## Success Criteria

### Measurable Outcomes

- **SC-001**: One hundred percent of the nine approved authentication
  operations pass their documented success and failure acceptance scenarios.
- **SC-002**: At any measured point after login or refresh, the administrator
  has no more than one active session, and concurrent use of one rotating
  credential yields no more than one success.
- **SC-003**: One hundred percent of authentication profile responses contain
  exactly the five approved fields and zero user IDs, internal fields, raw
  paths, or secret credentials.
- **SC-004**: One hundred percent of tested refresh responses keep the refresh
  token out of JSON and rotate it through the protected host-only cookie.
- **SC-005**: One hundred percent of tested inactive-account attempts are
  blocked across login, refresh, and protected routes.
- **SC-006**: One hundred percent of eligible recovery attempts deliver the
  approved localized email synchronously after commit, while unknown and
  inactive accounts remain indistinguishable to the requester.
- **SC-007**: Every supported authentication response has verified Arabic and
  English user-facing messages with stable English machine identifiers.
- **SC-008**: Security review finds zero plaintext passwords, access tokens,
  refresh tokens, reset codes, reset tokens, cookie values, raw avatar paths,
  or stack traces in persistence, logs, or unintended responses.
- **SC-009**: The complete approved automated test inventory and every Feature
  001 Definition of Done item pass before the feature is declared complete.
