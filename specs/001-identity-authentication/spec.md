# Feature Specification: Identity and Authentication

**Feature Directory:** `specs/001-identity-authentication`

**Status:** Ready for Reimplementation

**Created:** 2026-07-28

**Last Updated:** 2026-07-28

## Governing Reference and Requirement Commitment

This specification follows the constitution, `AGENTS.md`, the project overview,
the backend architecture, shared standards, and the approved Feature 001
business reference.

This specification preserves the approved direct separate-domain administrator
authentication architecture:

```text
React Admin on Hostinger Shared Hosting
-> direct HTTPS with strict allow-listed CORS
-> Laravel Backend API on a separate domain
```

The approved auth transport remains:

- Access Token:
  - Laravel Sanctum Bearer token
  - returned in JSON
  - sent in `Authorization: Bearer ...`
  - no automatic expiration (`null` lifetime)
  - Web storage: runtime memory only
- Refresh Token:
  - custom opaque rotating token
  - returned in JSON at login and refresh
  - sent only in the refresh JSON body
  - `2592000`-second lifetime
  - persisted only as a SHA-256 hash
  - Web storage: `sessionStorage` only
  - future Mobile storage: OS-backed secure storage

Feature 001 does not use:

- authentication cookies
- CSRF refresh flow
- Origin-gated refresh middleware
- proxy/BFF
- Cloudflare Worker
- browser-session auth
- JWT replacement

This specification is implementation-driving and must be explicit enough to
support implementation, OpenAPI, tasks, tests, Postman, and deployment without
depending on vague phrases such as “approved contract”, “where required”, or
“documented behavior” for core auth behavior.

## Scope

### In Scope

- Super Admin provisioning
- administrator login
- Sanctum Bearer access-token issuance
- opaque rotating refresh-token issuance
- one active administrator session across Web and future Mobile
- logout
- authenticated profile read
- authenticated profile update
- avatar replacement
- authenticated password change
- forgot-password request
- forgot-password code verification
- one-time password reset
- inactive-account enforcement
- response-envelope rules
- stable error inventory
- localization and response-header behavior
- exact CORS behavior for separate domains
- security logging boundaries
- backend automated verification requirements
- external React frontend authentication integration and automated verification
  requirements, with implementation and evidence owned by the external React
  repository

### Out of Scope

- customer authentication
- customer registration
- customer password reset
- public authentication
- MFA
- social login
- email verification
- session-management UI
- device management
- auth cookies, CSRF refresh flow, or proxy/BFF transport

## User Scenarios and Testing

### User Story 1 - Provision and sign in as the Super Admin (Priority: P1)

As the system owner, I can provision the initial administrator safely and sign
in through the external Admin Frontend so that I can access protected backend
APIs.

Acceptance criteria:

1. Provisioning is idempotent and uses environment-backed values only.
2. Successful login returns `accessToken`, `refreshToken`, `tokenType`,
   `tokenExpiresIn`, `refreshTokenExpiresIn`, and the approved profile in JSON.
3. Login revokes every prior access token and refresh token for that
   administrator before issuing a replacement session.
4. Login emits no authentication cookie and requires no proxy/BFF.

### User Story 2 - Maintain and end a secure session (Priority: P1)

As an authenticated Super Admin, I can refresh and end my session safely so
that access remains short-lived and predecessor credentials become unusable
immediately.

Acceptance criteria:

1. Refresh accepts only body `refreshToken` and returns rotated access and
   refresh tokens in JSON.
2. Refresh uses transactional row locking and allows at most one success for a
   given predecessor token.
3. Successful refresh revokes every previously active Sanctum Access Token for
   the administrator before issuing the replacement Access Token.
4. Reuse of a rotated predecessor token revokes all sessions and returns
   `401 REFRESH_TOKEN_INVALID`.
5. Logout revokes every access token and refresh token for the administrator.

### User Story 3 - View and update the auth profile (Priority: P2)

As an authenticated Super Admin, I can retrieve and update only the approved
profile fields so that my visible account details remain current without
changing identity or privilege state.

Acceptance criteria:

1. Profile output contains exactly `name`, `email`, `avatar`, `role`,
   and `permissions`.
2. Update allows only approved mutable fields.
3. Multipart `_method=PATCH` works for avatar upload without creating a tenth
   operation.
4. Avatar compensation behavior is explicit and testable.

### User Story 4 - Change my password (Priority: P2)

As an authenticated Super Admin, I can replace my password after proving my
current one so that a known credential can be retired immediately.

Acceptance criteria:

1. Password change enforces the approved password policy.
2. Password change validates the current password and rejects using the current
   password again as the new password.
3. Successful password change revokes every access token and refresh token and
   invalidates active reset workflows.

### User Story 5 - Recover a forgotten password safely (Priority: P2)

As the Super Admin, I can request a short-lived code, exchange it for a one-time
reset token, and set a new password without exposing account existence or
secret material.

Acceptance criteria:

1. Forgot-password returns the same enumeration-safe `200` response for an
   eligible success case, an unknown email, and an inactive administrator.
2. Eligible recovery creates a hashed six-digit code that expires after
   `600` seconds and sends localized synchronous mail after commit.
3. Mail transport failure invalidates only the newly created workflow and
   returns `503 MAIL_SERVICE_UNAVAILABLE`.
4. Successful code verification returns exactly one `resetToken` and
   `resetTokenExpiresIn: 600`.
5. Successful password reset revokes every access token and refresh token.

### User Story 6 - Receive safe localized contracts (Priority: P1)

As an API consumer, I receive stable machine-readable auth contracts with
localized human-readable messages and no secret leakage.

Acceptance criteria:

1. Machine-readable error codes remain stable English identifiers.
2. Responses preserve the approved success and error envelopes.
3. Auth responses apply `Content-Language`, `Vary: Accept-Language`,
   `Cache-Control`, and `Pragma` according to governing standards.
4. No auth response or log exposes token values, raw avatar paths, stack
   traces, SQL, or internal exception details.

## Edge Cases

- login from Web invalidates an existing Mobile session and vice versa
- same-tab reload may recover a new access token by refreshing with the stored
  refresh token
- closing the browser tab clears `sessionStorage` and may require a new login
- every refresh attempt, including a malformed request, is rate-limited by
  requester IP before full request validation
- changing the submitted Refresh Token value does not create a new refresh
  limiter bucket for the same requester IP
- after successful refresh, every predecessor Access Token returns
  `401 UNAUTHENTICATED` and only the newly issued Access Token remains usable
- structurally valid but unknown, expired, revoked, reused, ownerless,
  deleted-owner, inactive-owner, invalid-family, or invalid-rotation refresh
  tokens return `401 REFRESH_TOKEN_INVALID`
- absent or empty-string avatar preserves the current avatar
- non-empty avatar string, path, or URL is invalid
- unknown or inactive forgot-password targets create no workflow and send no
  mail
- eligible forgot-password success, unknown email, and inactive administrator
  share the same enumeration-safe `200` success response
- forgot-password validation failure returns `422 VALIDATION_ERROR`
- forgot-password throttling returns `429 RATE_LIMITED`
- eligible forgot-password mail transport failure returns
  `503 MAIL_SERVICE_UNAVAILABLE`
- the fifth failed code verification consumes the workflow
- concurrent verification or reset attempts allow at most one winner
- two concurrent eligible first-time forgot-password requests for one
  administrator leave at most one newly usable workflow and at most one mail
  transport invocation for the serialized outcome

## Requirements

### Functional Requirements

- FR-001: The backend MUST expose exactly these nine authentication operations:
  `POST /login`, `POST /refresh`, `POST /logout`, `GET /profile`,
  `PATCH /profile`, `PUT /change-password`, `POST /forgot-password`,
  `POST /verify-forgot-password-code`, and `POST /reset-password`, all under
  `/api/v1/admin/auth/*`.
- FR-002: The canonical auth surface MUST remain exactly `9` operations across
  exactly `8` unique paths.
- FR-003: `POST /api/v1/admin/auth/profile` with `_method=PATCH` MUST remain
  multipart transport compatibility for the canonical `PATCH /profile`
  operation only and MUST NOT be counted as a tenth operation.
- FR-004: The Super Admin MUST remain the only authenticated MVP actor.
- FR-005: Customers MUST remain unauthenticated guest records and MUST NOT
  receive customer-authentication endpoints or tokens.
- FR-006: The backend MUST NOT introduce registration, customer auth, public
  auth, MFA, social login, email verification, session-management UI, or
  device-management routes in Feature 001.
- FR-007: The backend MUST use direct separate-domain frontend-to-backend
  HTTPS.
- FR-008: The primary Super Admin provisioning values MUST come from
  environment-backed configuration.
- FR-009: The system MUST NOT generate or ship an implicit administrator
  credential; any repository-owned additional seeded administrator account must
  be explicit and documented.
- FR-010: Provisioning MUST fail safely when required production name, email,
  or password values are missing or invalid.
- FR-011: Provisioning MUST normalize the configured email using
  `trim()` + `mb_strtolower()`.
- FR-012: The normalized administrator email MUST be unique.
- FR-013: Provisioning MUST hash the configured password with the approved
  Laravel password hasher.
- FR-014: Provisioning MUST enforce the approved administrator type value for
  the created or existing user.
- FR-015: Provisioning MUST ensure `is_active = true`.
- FR-016: Provisioning MUST create the `super-admin` role idempotently.
- FR-017: Provisioning MUST assign the `super-admin` role idempotently.
- FR-018: Provisioning reruns MUST NOT create a duplicate user.
- FR-019: Provisioning reruns MUST NOT create a duplicate role.
- FR-020: Provisioning reruns MUST preserve the existing password.
- FR-021: Provisioning reruns MUST preserve existing identity values unless a
  higher-authority governing rule explicitly permits correction.
- FR-022: Provisioning reruns MUST still ensure the administrator type, active
  state, and role assignment remain correct.
- FR-023: Successful login MUST return `accessToken`, `refreshToken`,
  `tokenType`, `tokenExpiresIn`, `refreshTokenExpiresIn`, and the approved
  profile in JSON.
- FR-024: The access token MUST be a Sanctum Bearer token with no automatic
  expiration; `tokenExpiresIn` and the persisted `expires_at` MUST be `null`.
- FR-025: The refresh token MUST be a custom opaque rotating token with a
  lifetime of `2592000` seconds.
- FR-026: Login MUST accept only `email` and `password`.
- FR-027: Login MUST normalize email only.
- FR-028: Login MUST NOT trim or transform password bytes.
- FR-029: Login MUST be throttled to `5/min` by normalized email + requester IP.
- FR-030: Unknown email and wrong password MUST share the same
  `401 INVALID_CREDENTIALS` contract.
- FR-031: An inactive administrator login attempt MUST return
  `403 USER_INACTIVE`.
- FR-032: Successful login MUST revoke every existing access token and refresh
  token for that administrator before issuing the replacement session.
- FR-033: Successful login MUST return both plain tokens exactly once in JSON.
- FR-034: The backend MUST emit no auth `Set-Cookie` header for login.
- FR-035: The one-active-administrator-session rule MUST remain global across
  Web and future Mobile clients.
- FR-036: Web access tokens MUST be stored only in runtime memory.
- FR-037: Web access tokens MUST NOT be stored in `localStorage`,
  `sessionStorage`, IndexedDB, cookies, URLs, persistent Redux stores,
  persistent Zustand stores, analytics, monitoring, logs, or browser console.
- FR-038: Web refresh tokens MUST be stored only in `sessionStorage`.
- FR-039: Web refresh tokens MUST NOT be stored in `localStorage`, IndexedDB,
  cookies, URLs, persistent Redux stores, persistent Zustand stores, analytics,
  monitoring, logs, or browser console.
- FR-040: A future Mobile refresh token MUST be stored only in OS-backed secure
  storage.
- FR-041: Guidance examples for future Mobile secure storage MAY include iOS
  Keychain, Android Keystore, and Flutter Secure Storage backed by platform
  secure storage.
- FR-042: Future Mobile MUST use the same login and refresh API contract and
  MUST NOT receive a new mobile-only auth route in Feature 001.
- FR-043: Login from one client MUST replace the previously active client
  session.
- FR-044: The accepted Web risk MUST be stated explicitly: a successful XSS
  attack can read `sessionStorage` and steal the refresh token.
- FR-045: The approved Admin Web security controls MUST include a strict
  Content Security Policy.
- FR-046: The approved Admin Web security controls MUST prohibit `eval`.
- FR-047: The approved Admin Web security controls MUST prohibit
  `unsafe-inline` unless separately approved by a higher authority.
- FR-048: The approved Admin Web security controls MUST prohibit
  `dangerouslySetInnerHTML` without an approved sanitization boundary.
- FR-049: The approved Admin Web security controls MUST require output
  encoding.
- FR-050: The approved Admin Web security controls MUST require dependency lock
  files.
- FR-051: The approved Admin Web security controls MUST require dependency
  vulnerability review.
- FR-052: The approved Admin Web security controls MUST prohibit token values
  in console output, analytics, monitoring, error reporting, or URLs.
- FR-053: The approved Admin Web security controls MUST rely on short access
  token lifetime, refresh rotation, reuse detection, all-session revocation on
  reuse, HTTPS only in production, and exact allow-listed CORS.
- FR-054: Unauthenticated Admin auth operations MUST be exactly:
  `POST /api/v1/admin/auth/login`,
  `POST /api/v1/admin/auth/refresh`,
  `POST /api/v1/admin/auth/forgot-password`,
  `POST /api/v1/admin/auth/verify-forgot-password-code`, and
  `POST /api/v1/admin/auth/reset-password`.
- FR-055: Unauthenticated Admin auth operations MUST NOT require Bearer access
  authentication.
- FR-056: Unauthenticated Admin auth operations MUST NOT require browser
  `Origin`.
- FR-057: Unauthenticated Admin auth operations MUST NOT require CSRF.
- FR-058: Unauthenticated Admin auth operations MUST still apply their approved
  throttles and request validation.
- FR-059: Protected auth operations MUST be exactly:
  `POST /api/v1/admin/auth/logout`,
  `GET /api/v1/admin/auth/profile`,
  `PATCH /api/v1/admin/auth/profile`, and
  `PUT /api/v1/admin/auth/change-password`.
- FR-060: Protected auth operations MUST use
  `auth:sanctum -> EnsureUserIsAdministrator -> EnsureAdminIsActive -> endpoint`.
- FR-061: If a future approved permission middleware is added to a protected
  auth self-service route, it MUST appear only after `EnsureAdminIsActive`.
- FR-062: Feature 001 MUST NOT introduce permission middleware for current
  self-service auth routes.
- FR-063: Refresh MUST accept only `refreshToken` in the JSON body.
- FR-064: Refresh MUST reject cookies, query strings, and alternate headers as
  refresh-token sources.
- FR-065: Refresh token input MUST NOT be trimmed, lowercased, uppercased,
  Unicode-normalized, decoded and re-encoded unless the approved wire format
  requires it, or otherwise silently transformed before validation, hashing,
  comparison, or lookup.
- FR-066: Refresh MUST use `422 VALIDATION_ERROR` for missing, null,
  wrong-type, empty, or otherwise structurally invalid `refreshToken` request
  fields.
- FR-067: Refresh MUST use `401 REFRESH_TOKEN_INVALID` for structurally valid
  but unknown, expired, revoked, reused, ownerless, deleted-owner,
  inactive-owner, invalid-family, or invalid-rotation tokens.
- FR-068: Refresh MUST be throttled to `10/min` per resolved requester IP.
  The submitted Refresh Token value MUST NOT create or influence the limiter
  bucket.
- FR-069: Refresh processing order MUST be:
  resolve the requester-IP limiter key -> apply the refresh throttle ->
  `RefreshTokenRequest` validation -> basic token-format validation -> SHA-256
  authoritative lookup hash -> transaction -> `lockForUpdate()` -> revalidate
  -> revoke predecessor Access Tokens -> rotate the Refresh Token -> issue the
  replacement Access Token and Refresh Token or reject.
- FR-070: Successful refresh MUST return rotated `accessToken`,
  `refreshToken`, `tokenType`, `tokenExpiresIn`, and
  `refreshTokenExpiresIn` in JSON.
- FR-071: Successful refresh MUST NOT include the profile.
- FR-072: Successful refresh MUST make the predecessor refresh token unusable
  immediately.
- FR-073: Reuse of a rotated refresh token MUST revoke all sessions for that
  administrator.
- FR-074: The backend MUST emit no auth `Set-Cookie` header for refresh.
- FR-224: Successful refresh MUST revoke every previously active Sanctum Access
  Token for the administrator inside the same database transaction before the
  replacement Access Token is issued.
- FR-225: After successful refresh, only the newly issued Access Token and the
  newly issued Refresh Token may remain active for the administrator.
- FR-075: Logout MUST revoke every access token and refresh token for the
  administrator.
- FR-076: Logout MUST return no new auth token.
- FR-077: The backend MUST emit no auth `Set-Cookie` header for logout.
- FR-078: Profile output MUST contain exactly `name`, `email`, `avatar`,
  `role`, and `permissions`.
- FR-079: Profile output MUST NOT contain `id`, `type`, `isActive`,
  `roles`, `avatarUrl`, `avatarDisk`, `avatarPath`, timestamps, password
  metadata, or token metadata.
- FR-080: Profile update MUST accept only `name`, `avatar`, and `_method`.
- FR-081: Profile update MUST NOT allow email, role, permission, type,
  active-state, or storage-path mutation.
- FR-082: Profile update name input MUST be trimmed.
- FR-083: Profile update name input MUST be limited to `1` through `150`
  characters.
- FR-084: Avatar replacement MUST accept only uploaded `jpg`, `jpeg`, `png`,
  and `webp` files.
- FR-085: Avatar replacement MUST validate that the detected MIME type matches
  an approved image type.
- FR-086: Avatar replacement MUST enforce a maximum size of `2 MB`.
- FR-087: Avatar replacement MUST use random server-generated filenames.
- FR-088: Avatar replacement MUST use the approved public disk.
- FR-089: Avatar responses MUST expose only the approved public URL or `null`.
- FR-090: Absent avatar input MUST preserve the current avatar.
- FR-091: Empty-string avatar input MUST preserve the current avatar.
- FR-092: Delete-only avatar behavior MUST NOT exist.
- FR-093: Non-empty string avatar paths or URLs MUST be rejected.
- FR-094: Avatar compensation MUST store the new file before metadata
  replacement.
- FR-095: Avatar compensation MUST update metadata transactionally.
- FR-096: Avatar compensation MUST delete the new file on database failure.
- FR-097: Avatar compensation MUST attempt old-file deletion after commit.
- FR-098: Old-file cleanup failure MUST NOT fail an already committed profile
  update.
- FR-099: Avatar cleanup failure logging MUST contain safe metadata only.
- FR-100: Avatar cleanup MUST NOT use a cleanup job.
- FR-101: Profile update MUST NOT rotate auth tokens.
- FR-102: Password policy MUST require a minimum of `10` characters.
- FR-103: Password policy MUST require at least one lowercase letter.
- FR-104: Password policy MUST require at least one uppercase letter.
- FR-105: Password policy MUST require at least one number.
- FR-106: Password policy MUST require at least one symbol.
- FR-107: Password change MUST require confirmation.
- FR-108: The new password MUST differ from the current password where
  applicable.
- FR-109: Password change MUST require `currentPassword`, `newPassword`, and
  `newPasswordConfirmation`.
- FR-110: `currentPassword`, `newPassword`, and
  `newPasswordConfirmation` MUST NOT be trimmed, lowercased, uppercased,
  Unicode-normalized, decoded and re-encoded unless the approved wire format
  requires it, or otherwise silently transformed before comparison, hashing,
  or validation.
- FR-111: Password change MUST be throttled to `5/min` per authenticated
  administrator.
- FR-112: Incorrect current password MUST return
  `422 CURRENT_PASSWORD_INVALID`.
- FR-113: Successful password change MUST revoke all sessions.
- FR-114: Successful password change MUST invalidate active reset workflows.
- FR-115: Successful password change MUST return no new auth token.
- FR-116: The backend MUST emit no auth `Set-Cookie` header for password
  change.
- FR-117: Forgot-password MUST normalize email.
- FR-118: Forgot-password MUST be throttled to `5/min` by normalized email + IP.
- FR-119: Forgot-password MUST enforce a `60`-second resend cooldown.
- FR-120: Forgot-password MUST return the same enumeration-safe `200` success
  response for:
  - an eligible active administrator when workflow creation and mail delivery
    succeed;
  - an unknown email;
  - an inactive administrator.
- FR-121: The enumeration-safe forgot-password `200` rule MUST NOT replace:
  `422 VALIDATION_ERROR`, `429 RATE_LIMITED`, or
  `503 MAIL_SERVICE_UNAVAILABLE`.
- FR-122: Unknown and inactive forgot-password targets MUST create no workflow.
- FR-123: Unknown and inactive forgot-password targets MUST send no email.
- FR-124: Unknown and inactive forgot-password targets MUST receive the same
  generic `200` response as an eligible success case.
- FR-125: Forgot-password MUST resolve the eligible active administrator before
  opening the creation lock transaction.
- FR-126: Forgot-password MUST begin a short MySQL transaction for workflow
  creation.
- FR-127: Forgot-password MUST lock the stable `users` row with
  `lockForUpdate()`.
- FR-128: Forgot-password MUST re-check administrator eligibility under the
  lock.
- FR-129: Forgot-password MUST re-check the `60`-second resend cooldown under
  the lock.
- FR-130: Forgot-password MUST invalidate previous usable workflows before
  creating the new workflow.
- FR-131: Forgot-password MUST create at most one newly usable workflow per
  serialized winning request.
- FR-132: Forgot-password MUST generate a cryptographically random six-digit
  recovery code.
- FR-133: Forgot-password MUST store the recovery code only as a password hash.
- FR-134: Forgot-password MUST set the recovery-code lifetime to `600` seconds.
- FR-135: Forgot-password MUST commit before attempting mail delivery.
- FR-136: Forgot-password mail MUST be sent synchronously after commit.
- FR-137: Forgot-password mail MUST NEVER be sent inside the transaction.
- FR-138: If forgot-password mail transport fails, only the newly created
  workflow MUST be invalidated.
- FR-139: Forgot-password mail transport failure MUST return
  `503 MAIL_SERVICE_UNAVAILABLE`.
- FR-140: Forgot-password mail transport failure MUST reveal no
  account-existence detail beyond the documented service failure.
- FR-141: Two concurrent eligible first-time forgot-password requests for one
  administrator MUST leave at most one newly usable workflow and at most one
  mail transport invocation for the serialized outcome.
- FR-142: Locking only `password_resets` rows is insufficient when no workflow
  row exists yet.
- FR-143: Recovery mail MUST contain only the six-digit recovery code, a
  ten-minute expiry statement, and ignore-if-unrequested guidance.
- FR-144: Recovery mail MUST NOT contain a password, access token, refresh
  token, reset token, privileged reset URL, or raw workflow data.
- FR-145: Recovery mail MUST use the resolved request locale.
- FR-146: Code verification MUST accept only normalized email and a six-digit
  code.
- FR-147: Recovery code input MUST NOT be trimmed, lowercased, uppercased,
  Unicode-normalized, decoded and re-encoded unless the approved wire format
  requires it, or otherwise silently transformed before comparison or hashing.
- FR-148: Code verification MUST lock the workflow row before issuing a reset
  token.
- FR-149: Code verification MUST increment failed attempts safely.
- FR-150: Code verification MUST enforce a maximum of `5` failed attempts.
- FR-151: Wrong, expired, consumed, or attempt-limited codes MUST use
  `422 PASSWORD_RESET_CODE_INVALID`.
- FR-152: Successful code verification MUST consume the code.
- FR-153: Successful code verification MUST return one plain `resetToken` and
  `resetTokenExpiresIn: 600`.
- FR-154: Successful code verification MUST store only the SHA-256 hash of the
  reset token.
- FR-155: Code verification MUST allow at most one winning reset-token
  issuance under concurrency.
- FR-156: Reset tokens MUST be stored client-side only in runtime memory.
- FR-157: Reset tokens MUST NOT be stored in `sessionStorage`, `localStorage`,
  IndexedDB, cookies, URLs, analytics, monitoring, logs, or browser console.
- FR-158: Password reset MUST require normalized email, `resetToken`,
  `password`, and `passwordConfirmation`.
- FR-159: `resetToken`, `password`, and `passwordConfirmation` MUST NOT be
  trimmed, lowercased, uppercased, Unicode-normalized, decoded and re-encoded
  unless the approved wire format requires it, or otherwise silently
  transformed before comparison, hashing, or lookup.
- FR-160: Reset tokens MUST expire after `600` seconds.
- FR-161: Structurally valid but unusable reset tokens MUST return
  `422 PASSWORD_RESET_TOKEN_INVALID`.
- FR-162: Password reset MUST use a transaction and row lock.
- FR-163: Password reset MUST permit at most one successful concurrent winner.
- FR-164: Successful password reset MUST consume the workflow atomically.
- FR-165: Successful password reset MUST revoke all sessions.
- FR-166: Successful password reset MUST return no new auth token.
- FR-167: The backend MUST emit no auth `Set-Cookie` header for password reset.
- FR-168: Authentication responses MUST use the shared success envelope:
  `success`, localized `message`, and endpoint-typed `data`.
- FR-169: Success responses MUST NOT include `code: null`.
- FR-170: Success responses MUST NOT include undocumented JSON metadata.
- FR-171: Authentication error responses MUST use the shared error envelope:
  `success`, localized `message`, stable English `code`, and `errors`.
- FR-172: Validation errors MAY use the approved structured `errors` object
  from the shared API standard.
- FR-173: Authentication responses MUST preserve `Content-Language`.
- FR-174: Authentication responses MUST preserve
  `Vary: Accept-Language`.
- FR-175: Authentication responses MUST preserve
  `Cache-Control: no-store, private` for sensitive auth responses.
- FR-176: Authentication responses MUST preserve `Pragma: no-cache`.
- FR-177: Locale MUST be resolved before validation.
- FR-178: Arabic MUST remain the default locale.
- FR-179: English MUST remain the fallback locale.
- FR-180: Machine-readable error codes MUST remain English.
- FR-181: Request keys, route names, role names, and permission names MUST
  remain English.
- FR-182: Email is the only authentication identifier that may be normalized
  using `trim()` + `mb_strtolower()`.
- FR-183: CORS allowed origin MUST be the exact configured
  `ADMIN_FRONTEND_ORIGIN`.
- FR-184: The example domain
  `https://admin.frontend-example.com` MUST remain an example only and MUST
  NOT be treated as a production binding requirement.
- FR-185: CORS allowed methods MUST include only methods required by the
  documented API plus `OPTIONS`.
- FR-186: CORS allowed request headers MUST include `Authorization`,
  `Content-Type`, `Accept`, `Accept-Language`, and approved request or
  correlation ID headers.
- FR-187: CORS exposed headers MUST include `Content-Language`, approved
  rate-limit headers, and approved request or correlation ID headers.
- FR-188: CORS `supports_credentials` MUST remain `false`.
- FR-189: CORS MUST NOT use a wildcard origin.
- FR-190: CORS MUST NOT use arbitrary `Origin` reflection.
- FR-191: CORS MUST NOT enable `Access-Control-Allow-Credentials: true`.
- FR-192: CORS MUST be treated as a browser access policy, not the
  authentication mechanism.
- FR-193: Postman and future Mobile clients MUST still require valid auth
  tokens even though browser CORS does not apply to them.
- FR-194: Plaintext passwords, access tokens, refresh tokens, recovery codes,
  reset tokens, raw `Authorization` headers, sensitive request bodies, and
  incoming raw secret bytes MUST NOT be persisted.
- FR-195: The approved password hash MUST be persisted only in its
  authoritative password-hash column.
- FR-196: The approved Sanctum access-token hash MUST be persisted only in its
  authoritative Sanctum token table column.
- FR-197: The approved refresh-token SHA-256 hash MUST be persisted only in
  its authoritative refresh-token column.
- FR-198: The approved recovery-code password hash MUST be persisted only in
  its authoritative password-reset workflow column.
- FR-199: The approved reset-token SHA-256 hash MUST be persisted only in its
  authoritative password-reset workflow column.
- FR-200: Approved secret hashes MUST NOT appear in API responses, logs,
  exception context, analytics, monitoring, URLs, query strings, browser
  console, email, or client-visible error messages.
- FR-201: Raw avatar paths, database credentials, mail credentials, SQL, stack
  traces, and internal exception details MUST NOT appear in API responses,
  logs, exception context, analytics, monitoring, URLs, query strings, browser
  console, email, or client-visible error messages.
- FR-202: The plain access token MAY appear once only in a successful login
  JSON response.
- FR-203: The plain access token MAY appear once only in a successful refresh
  JSON response.
- FR-204: The plain refresh token MAY appear once only in a successful login
  JSON response.
- FR-205: The plain refresh token MAY appear once only in a successful refresh
  JSON response.
- FR-206: The plain reset token MAY appear once only in a successful
  verify-code JSON response.
- FR-207: The six-digit recovery code MAY appear only in the approved recovery
  email.
- FR-208: No allowed secret output MAY be logged.
- FR-209: No auth Queue Job, cleanup Command, Cron task, or scheduler entry
  MAY be introduced.
- FR-210: The refresh limiter key MUST contain only the resolved requester IP
  and MUST NOT contain, hash, fingerprint, or otherwise derive from the
  submitted `refreshToken`.
- FR-211: The requester IP used for refresh throttling MUST be resolved through
  the repository-approved trusted-proxy configuration so untrusted forwarding
  headers cannot create arbitrary limiter buckets.
- FR-212: The same IP-only refresh limiter MUST apply to structurally valid and
  structurally invalid refresh requests before full request validation.
- FR-214: Refresh token generation MUST use `64` cryptographically secure
  random bytes encoded using an approved URL-safe representation that preserves
  the full entropy of those `64` bytes.
- FR-216: Reset token generation MUST use at least `32` cryptographically
  secure random bytes encoded using an approved URL-safe representation.
- FR-218: Recovery-code generation MUST use a cryptographically secure random
  source for the six-digit numeric code.
- FR-219: Refresh tokens, reset tokens, and recovery codes MUST NOT be derived
  from `rand()`, `mt_rand()`, UUID-only secret tokens, predictable timestamps,
  database IDs, incrementing counters, email-derived values, user-ID-derived
  values, or hashes of predictable input without cryptographically secure
  randomness.
- FR-220: The plaintext `refreshToken` MUST NOT be logged.
- FR-221: If the owning React repository is unavailable during backend
  implementation, the frontend verification work MUST remain explicitly
  blocked, and full Feature 001 completion MUST NOT be claimed.

### API Contract Requirements

- API-001: Login success MUST use:

```json
{
  "success": true,
  "message": "Localized safe message",
  "data": {
    "accessToken": "plain-sanctum-token",
    "refreshToken": "plain-opaque-refresh-token",
    "tokenType": "Bearer",
    "tokenExpiresIn": null,
    "refreshTokenExpiresIn": 2592000,
    "profile": {
      "name": "Administrator",
      "email": "admin@example.com",
      "avatar": null,
      "role": "super-admin",
      "permissions": []
    }
  }
}
```

- API-002: Refresh request MUST require `refreshToken` in the JSON body.
- API-003: Refresh success MUST use:

```json
{
  "success": true,
  "message": "Localized safe message",
  "data": {
    "accessToken": "new-sanctum-token",
    "refreshToken": "new-opaque-refresh-token",
    "tokenType": "Bearer",
    "tokenExpiresIn": null,
    "refreshTokenExpiresIn": 2592000
  }
}
```

- API-004: Refresh success MUST NOT return the profile.
- API-005: The shared error envelope MUST be:

```json
{
  "success": false,
  "message": "Localized safe message",
  "code": "STABLE_ENGLISH_CODE",
  "errors": null
}
```

- API-006: Multipart profile update MAY use `POST /profile` with `_method=PATCH`
  for transport compatibility only.
- API-007: Endpoint-specific success payloads MUST be typed and MUST NOT rely
  on generic placeholder `data: {}` descriptions.

### Error Inventory Requirements

- ERR-001: `INVALID_CREDENTIALS` MUST map to HTTP `401`.
- ERR-002: `USER_INACTIVE` MUST map to HTTP `403`.
- ERR-003: `UNAUTHENTICATED` MUST map to HTTP `401`.
- ERR-004: `VALIDATION_ERROR` MUST map to HTTP `422`.
- ERR-005: `RATE_LIMITED` MUST map to HTTP `429`.
- ERR-006: `REFRESH_TOKEN_INVALID` MUST map to HTTP `401`.
- ERR-007: `CURRENT_PASSWORD_INVALID` MUST map to HTTP `422`.
- ERR-008: `PASSWORD_RESET_CODE_INVALID` MUST map to HTTP `422`.
- ERR-009: `PASSWORD_RESET_TOKEN_INVALID` MUST map to HTTP `422`.
- ERR-010: `MAIL_SERVICE_UNAVAILABLE` MUST map to HTTP `503`.
- ERR-011: Unknown email and wrong password MUST share
  `INVALID_CREDENTIALS`.
- ERR-012: Structural refresh request failures MUST use `VALIDATION_ERROR`.
- ERR-013: Structurally valid but unusable refresh tokens MUST use
  `REFRESH_TOKEN_INVALID`.
- ERR-014: Wrong, expired, consumed, or attempt-limited codes MUST use
  `PASSWORD_RESET_CODE_INVALID`.
- ERR-015: Structurally valid but unusable reset tokens MUST use
  `PASSWORD_RESET_TOKEN_INVALID`.
- ERR-016: Feature 001 MUST NOT reintroduce `ORIGIN_NOT_ALLOWED`.
- ERR-017: Feature 001 MUST NOT reintroduce `CSRF_TOKEN_MISMATCH`.

### Authorization and Trust Requirements

- AUTH-001: Authentication and authorization MUST remain backend-enforced.
- AUTH-002: `users.type = 0` MUST remain a classifier and MUST NOT replace
  roles or permissions.
- AUTH-003: Inactive administrators MUST NOT log in, refresh, or use protected
  routes.
- AUTH-004: Protected routes MUST use:
  `auth:sanctum -> EnsureUserIsAdministrator -> EnsureAdminIsActive -> endpoint`.

### Data Integrity Requirements

- DATA-001: Refresh tokens MUST be stored only as SHA-256 hashes.
- DATA-002: Refresh rotation and login replacement MUST be transaction-safe.
- DATA-003: Password-reset workflows MUST preserve normalized email,
  code state, attempt count, reset-token hash, and consumption state.
- DATA-004: Forgot-password creation, verify-code, and password reset MUST
  enforce single-winner behavior where the workflow requires it.
- DATA-005: No schema field may be added solely to distinguish Web from Mobile.
- DATA-006: Approved password hashes, Sanctum access-token hashes,
  refresh-token SHA-256 hashes, recovery-code password hashes, and reset-token
  SHA-256 hashes MUST be persisted only in their authoritative columns.
- DATA-007: Successful refresh MUST atomically revoke predecessor Sanctum Access
  Tokens, rotate the Refresh Token, and issue the replacement token pair so the
  one-active-session invariant cannot be violated by partial completion.

### Verification Requirements

- VER-001: Backend automated verification MUST prove provisioning is
  idempotent.
- VER-002: Backend automated verification MUST prove login returns both tokens in
  JSON.
- VER-003: Backend automated verification MUST prove refresh rotates the refresh
  token and invalidates the predecessor.
- VER-004: Backend automated verification MUST prove reuse revokes all sessions.
- VER-005: Backend automated verification MUST prove no auth `Set-Cookie` is emitted.
- VER-006: Backend automated verification MUST prove no CSRF or Origin
  requirement remains on unauthenticated Admin auth routes.
- VER-007: Backend automated verification MUST prove exact CORS response
  behavior for the configured `ADMIN_FRONTEND_ORIGIN`.
- VER-008: Backend automated verification MUST prove
  `supports_credentials=false`.
- VER-009: Backend automated verification MUST prove auth responses preserve the
  approved success and error envelopes.
- VER-010: Backend automated verification MUST prove stable auth error codes.
- VER-011: Backend automated verification MUST prove auth responses preserve
  `Content-Language`, `Vary: Accept-Language`,
  `Cache-Control: no-store, private`, and `Pragma: no-cache`.
- VER-012: Backend automated verification MUST prove backend log and response
  secret-safety boundaries.
- VER-013: Backend automated verification MUST prove concurrent forgot-password
  processing leaves at most one newly usable workflow and applies the approved
  mail-failure invalidation rule.
- VER-014: Backend automated verification MUST prove password-change and
  password-reset revocation behavior.
- VER-015: Backend automated verification MUST prove avatar compensation
  behavior.
- VER-016: Backend automated verification MUST prove the auth surface remains
  `9` operations and `8` unique paths.
- VER-017: Frontend automated verification in the owning React repository MUST
  prove that the access token remains in runtime memory only.
- VER-018: Frontend automated verification in the owning React repository MUST
  prove that the refresh token exists only in `sessionStorage`.
- VER-019: Frontend automated verification in the owning React repository MUST
  prove there is no access token in `localStorage`, `sessionStorage`,
  IndexedDB, or cookies.
- VER-020: Frontend automated verification in the owning React repository MUST
  prove there is no refresh token in `localStorage`, IndexedDB, or cookies.
- VER-021: Frontend automated verification in the owning React repository MUST
  prove same-tab reload refresh bootstrap behavior.
- VER-022: Frontend automated verification in the owning React repository MUST
  prove successful refresh replaces the stored refresh token.
- VER-023: Frontend automated verification in the owning React repository MUST
  prove failed refresh clears auth state.
- VER-024: Frontend automated verification in the owning React repository MUST
  prove logout clears auth state.
- VER-025: Frontend automated verification in the owning React repository MUST
  prove Authorization-header injection and `withCredentials=false`.
- VER-026: Frontend automated verification in the owning React repository MUST
  prove no token leakage to console, analytics, monitoring, or error reporting
  and MUST verify the CSP baseline where testable.
- VER-027: Architecture and documentation verification MUST prove future Mobile
  secure-storage guidance remains explicit.
- VER-028: Architecture and documentation verification MUST prove the accepted
  `sessionStorage` XSS risk remains explicit.
- VER-029: Architecture and documentation verification MUST prove CSP and XSS
  controls remain documented.
- VER-030: Architecture and documentation verification MUST prove the full stable
  error inventory remains explicit.
- VER-031: Architecture and documentation verification MUST prove
  unauthenticated and protected Admin auth operation groups are explicit.
- VER-032: Architecture and documentation verification MUST prove no obsolete
  cookie or proxy requirement remains active.
- VER-033: Architecture and documentation verification MUST prove requirement IDs
  remain atomic and traceable.
- VER-034: Architecture and documentation verification MUST prove email remains
  the only normalized authentication identifier.
- VER-035: Architecture and documentation verification MUST prove secret-input
  non-transformation rules remain explicit.
- VER-036: Architecture and documentation verification MUST prove the
  forgot-password `200`/`422`/`429`/`503` boundary remains explicit.
- VER-037: Architecture and documentation verification MUST prove the spec
  remains internally consistent for forgot-password success and failure cases.
- VER-038: Architecture and documentation verification MUST prove approved
  secret hashes are permitted in authoritative persistence only.
- VER-039: Architecture and documentation verification MUST prove verification
  ownership is separated among backend, frontend, and documentation checks.
- VER-040: Architecture and documentation verification MUST prove unavailable
  owning-frontend evidence blocks full Feature 001 completion.
- VER-041: Backend automated verification MUST prove every refresh request is
  limited to `10/min` per resolved requester IP and that changing the submitted
  Refresh Token does not create a new limiter bucket.
- VER-042: Backend automated verification MUST prove the IP-only refresh
  throttle is applied before full `RefreshTokenRequest` validation for both
  structurally valid and structurally invalid requests.
- VER-044: Backend automated verification MUST prove refresh token generation
  uses `64` cryptographically secure random bytes and an approved URL-safe
  representation.
- VER-045: Backend automated verification MUST prove reset token generation
  uses at least `32` cryptographically secure random bytes and an approved
  URL-safe representation.
- VER-046: Backend automated verification MUST prove recovery-code generation
  uses a cryptographically secure random source.
- VER-047: Backend automated verification MUST prove no predictable token
  generator source is used for refresh tokens, reset tokens, or recovery
  codes.
- VER-050: Backend automated verification MUST prove that after successful
  refresh the replacement Access Token authenticates successfully, every
  predecessor Access Token returns `401 UNAUTHENTICATED`, and only the newly
  issued token pair remains active.

## Stable Error Inventory

| Code | HTTP Status | Boundary |
|---|---:|---|
| `INVALID_CREDENTIALS` | 401 | Unknown email and wrong password share this contract |
| `USER_INACTIVE` | 403 | Existing inactive administrator |
| `UNAUTHENTICATED` | 401 | Missing or invalid Bearer access token |
| `VALIDATION_ERROR` | 422 | Structural request-shape or validation failure |
| `RATE_LIMITED` | 429 | Throttle window exceeded |
| `REFRESH_TOKEN_INVALID` | 401 | Structurally valid but unusable refresh token |
| `CURRENT_PASSWORD_INVALID` | 422 | Incorrect current password |
| `PASSWORD_RESET_CODE_INVALID` | 422 | Wrong, expired, consumed, or attempt-limited code |
| `PASSWORD_RESET_TOKEN_INVALID` | 422 | Structurally valid but unusable reset token |
| `MAIL_SERVICE_UNAVAILABLE` | 503 | Recovery workflow mail transport failure |

## Key Entities

- Administrator User
- Sanctum Access Token
- Refresh Token
- Password Reset Workflow
- Administrator Profile Resource

## Assumptions and Dependencies

- the React Admin Frontend remains external to this repository
- the backend continues to use Laravel Sanctum, MySQL, and Spatie Permission
- the backend API origin may be browser-visible configuration for the Admin Web
- the accepted Web `sessionStorage` risk is mitigated by the documented
  compensating controls

## Source Traceability

- feature reference: `docs/features/001-identity-authentication.md`
- governing shared standards: API, authentication, authorization, security,
  localization, database, file-storage, and testing

## Success Criteria

1. Login returns both tokens in JSON and no auth cookie.
2. Refresh accepts only body `refreshToken` and returns rotated tokens in JSON.
3. Reuse revokes all sessions and returns the generic refresh failure contract.
4. Provisioning remains idempotent and safe on rerun.
5. Profile remains limited to the approved five fields with explicit avatar
   compensation.
6. Recovery mail is synchronous, localized, minimal, and safe on mail failure.
7. The auth surface remains exactly `9` operations and `8` unique paths.
8. Forgot-password enumeration-safe `200` behavior is limited to eligible
   success, unknown email, and inactive administrator and does not conflict
   with `422`, `429`, or `503`.
9. Successful refresh invalidates every predecessor Access Token and leaves only
   the replacement Access Token and Refresh Token active.
