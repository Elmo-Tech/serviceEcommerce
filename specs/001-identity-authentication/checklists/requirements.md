# Specification Quality Checklist: Identity and Authentication

**Purpose**: Validate specification completeness and evidence-based consistency

**Created**: 2026-07-28

**Feature**: [Identity and Authentication Specification](../spec.md)

## Governance and Scope

- [x] No implementation-code changes are described.
- [x] The specification preserves the approved direct Bearer + JSON refresh
  architecture.
- [x] The scope remains Super Admin only.
- [x] Customers remain unauthenticated.
- [x] The route surface remains exactly nine operations across eight unique
  paths.
- [x] Multipart profile method spoofing is documented as transport
  compatibility only, not as a tenth operation.
- [x] The specification status remains Ready for Reimplementation.
- [x] External React authentication integration and automated verification
  requirements are in scope while implementation ownership remains with the
  external React repository.

## Provisioning

- [x] Environment-backed Super Admin values are explicit.
- [x] No default production credential is explicit.
- [x] Safe failure for missing or invalid production values is explicit.
- [x] Email normalization with `trim()` + `mb_strtolower()` is explicit.
- [x] Normalized email uniqueness is explicit.
- [x] Password hashing with the approved hasher is explicit.
- [x] Administrator type enforcement is explicit.
- [x] Active-state enforcement is explicit.
- [x] Idempotent role creation and assignment are explicit.
- [x] Idempotent rerun with no duplicate user is explicit.
- [x] Idempotent rerun with no duplicate role is explicit.
- [x] No password overwrite on rerun is explicit.
- [x] Type, active-state, and role correction on rerun are explicit.
- [x] Identity preservation on rerun is explicit.

## Route and Actor Boundary

- [x] Super Admin only is explicit.
- [x] No registration is explicit.
- [x] No customer auth is explicit.
- [x] No public auth is explicit.
- [x] No MFA is explicit.
- [x] No social login is explicit.
- [x] No email verification is explicit.
- [x] No session-management UI is explicit.
- [x] No device management is explicit.

## Login and Refresh Contract

- [x] Login accepts only email and password.
- [x] Login normalizes email only.
- [x] Login does not trim or transform password.
- [x] Login throttle `5/min` by normalized email + IP is explicit.
- [x] `INVALID_CREDENTIALS` login boundary is explicit.
- [x] `USER_INACTIVE` login boundary is explicit.
- [x] Login revokes prior access and refresh tokens atomically.
- [x] Login returns both tokens once in JSON.
- [x] Login emits no auth cookie.
- [x] One active admin session is explicit.
- [x] Refresh request requires body `refreshToken`.
- [x] Structural refresh failures use `VALIDATION_ERROR`.
- [x] Structurally valid unusable refresh tokens use `REFRESH_TOKEN_INVALID`.
- [x] Refresh processing order is explicit.
- [x] Refresh is limited to `10/min` per resolved requester IP.
- [x] The submitted Refresh Token does not influence or create a limiter
  bucket.
- [x] Structurally valid and invalid refresh requests use the same IP-only
  limiter.
- [x] Trusted-proxy configuration governs requester-IP resolution.
- [x] The IP-only limiter is applied before full `RefreshTokenRequest`
  validation.
- [x] Full request validation and authoritative token lookup occur after
  throttling.
- [x] Refresh success data is explicit.
- [x] Refresh success excludes profile explicitly.
- [x] Successful refresh revokes every predecessor Access Token.
- [x] Only the replacement Access Token and Refresh Token remain active after
  successful refresh.

## Unauthenticated and Protected Admin Auth Operations

- [x] Unauthenticated Admin auth operations are listed explicitly.
- [x] Protected auth routes are listed explicitly.
- [x] Unauthenticated Admin auth operations are explicitly Bearer-auth free.
- [x] Unauthenticated Admin auth operations are explicitly Origin-free.
- [x] Unauthenticated Admin auth operations are explicitly CSRF-free.
- [x] Protected middleware order is explicit.
- [x] No current self-service permission middleware is introduced.
- [x] Unauthenticated Admin Auth Operations terminology is used consistently.
- [x] Public user authentication remains out of scope.
- [x] No ambiguous legacy unauthenticated-route term remains active.

## Web and Mobile Storage

- [x] Access Token memory-only requirement is explicit.
- [x] Refresh Token `sessionStorage`-only requirement is explicit.
- [x] Access Token storage prohibitions are explicit.
- [x] Refresh Token storage prohibitions are explicit.
- [x] Mobile OS-backed secure-storage requirement is explicit.
- [x] One active session across Web and Mobile is explicit.
- [x] Login from one client replacing the previous active client is explicit.
- [x] No mobile-only route is introduced.

## XSS Controls

- [x] Accepted `sessionStorage` risk is explicit.
- [x] Strict CSP is explicit.
- [x] No `eval` is explicit.
- [x] `unsafe-inline` policy is explicit.
- [x] `dangerouslySetInnerHTML` sanitization boundary is explicit.
- [x] Output encoding is explicit.
- [x] Dependency locking and vulnerability review are explicit.
- [x] No token leakage to console, analytics, or monitoring is explicit.
- [x] Short access-token lifetime, rotation, reuse detection, and revocation
  controls are explicit.

## CORS

- [x] The configured `ADMIN_FRONTEND_ORIGIN` is explicit.
- [x] The example domain is not the production requirement.
- [x] Allowed methods are explicit.
- [x] Allowed request headers are explicit.
- [x] Exposed headers are explicit.
- [x] `supports_credentials=false` is explicit.
- [x] Wildcard origins are prohibited.
- [x] Arbitrary origin reflection is prohibited.
- [x] Non-browser clients remain token-authenticated.

## API Envelopes and Errors

- [x] Success envelope is explicit.
- [x] Error envelope is explicit.
- [x] Success envelope forbids `code: null`.
- [x] Endpoint-specific typed success data is explicit.
- [x] Full stable error inventory is explicit.
- [x] HTTP status is mapped for every stable auth error.
- [x] Structural vs credential refresh failure is distinguished.
- [x] Recovery-code vs reset-token error contracts are distinguished.
- [x] Obsolete cookie/CSRF error codes are absent from active requirements.
- [x] Enumeration-safe `200` is limited to eligible-success, unknown, and
  inactive targets.
- [x] Validation `422`, throttle `429`, and mail-failure `503` are explicitly
  excluded from the enumeration-safe `200` rule.

## Profile and Avatar

- [x] Profile output fields are exactly specified.
- [x] Prohibited profile output fields are explicit.
- [x] Profile update accepted fields are explicit.
- [x] Name trimming and 1-150 characters are explicit.
- [x] Avatar extensions are explicit.
- [x] Avatar MIME validation is explicit.
- [x] Avatar max size is explicit.
- [x] Random server-generated filename is explicit.
- [x] Approved public disk is explicit.
- [x] Response exposes approved URL only.
- [x] Absent or empty avatar preserves current avatar.
- [x] Delete-only avatar flow is absent.
- [x] Compensation sequence is explicit.

## Passwords

- [x] Password policy minimum length is explicit.
- [x] Lowercase requirement is explicit.
- [x] Uppercase requirement is explicit.
- [x] Number requirement is explicit.
- [x] Symbol requirement is explicit.
- [x] Confirmation requirement is explicit.
- [x] New password differs from current password requirement is explicit.
- [x] Password change throttle is explicit.
- [x] `CURRENT_PASSWORD_INVALID` contract is explicit.
- [x] Password change revokes all sessions.
- [x] Password change invalidates active reset workflows.
- [x] Password reset revokes all sessions.
- [x] `PASSWORD_RESET_TOKEN_INVALID` contract is explicit.
- [x] Secret inputs are never trimmed, lowercased, uppercased,
  Unicode-normalized, or silently transformed.
- [x] Email is the only normalized authentication identifier.

## Recovery

- [x] Enumeration-safe forgot-password response is explicit.
- [x] Cooldown is explicit.
- [x] Cooldown recheck under users-row lock is explicit.
- [x] Users-row `lockForUpdate()` is explicit.
- [x] Prior usable workflow invalidation is explicit.
- [x] One usable workflow under concurrent requests is explicit.
- [x] At most one mail transport invocation for the serialized concurrent
  forgot-password result is explicit.
- [x] Recovery code lifetime is explicit.
- [x] Five failed attempts are explicit.
- [x] Verify-code single-winner behavior is explicit.
- [x] Reset-token lifetime is explicit.
- [x] Reset single-winner behavior is explicit.
- [x] Mail sent after commit is explicit.
- [x] Mail never sent inside the transaction is explicit.
- [x] Mail failure invalidates only the new workflow.
- [x] Recovery mail content is explicit and minimal.
- [x] Recovery code uses a cryptographically secure random source.

## Token Entropy and Generation

- [x] Refresh Token uses `64` cryptographically secure random bytes.
- [x] Refresh Token uses an approved URL-safe representation that preserves full entropy.
- [x] Reset Token uses at least `32` cryptographically secure random bytes.
- [x] Reset Token uses an approved URL-safe representation.
- [x] Predictable token generators are explicitly prohibited.

## Response Safety

- [x] `Content-Language` is explicit.
- [x] `Vary: Accept-Language` is explicit.
- [x] `Cache-Control` matches governing standards.
- [x] `Pragma` is explicit.
- [x] Locale resolution before validation is explicit.
- [x] Complete secret-leakage boundary is explicit.
- [x] Allowed one-time secret outputs are narrowly documented.
- [x] Plaintext secrets are never persisted.
- [x] Approved hashes are persisted only in authoritative columns.
- [x] Approved hashes never appear in responses, logs, analytics, monitoring,
  URLs, console, email, or client-visible errors.

## Verification Ownership

- [x] Backend verification responsibilities are separated explicitly.
- [x] Frontend verification responsibilities are separated explicitly.
- [x] Architecture/documentation verification responsibilities are separated
  explicitly.
- [x] Frontend verification uses MUST, not MAY.
- [x] Frontend verification is mandatory, not advisory.
- [x] Unavailable React repository evidence blocks full Feature completion.
- [x] Backend tests are not described as proving frontend storage behavior.
- [x] Backend tests are not described as proving Mobile guidance or documented
  XSS controls.
- [x] Backend verification explicitly proves predecessor Access Token
  revocation after refresh.

## Traceability

- [x] Requirement IDs are atomic where practical.
- [x] Requirement IDs are unique by category.
- [x] Verification requirements include provisioning, Mobile storage guidance,
  XSS controls, error inventory, headers, concurrent forgot-password result,
  recovery email content, secret leakage, and configured-origin CORS.
- [x] Duplicate one-time token-output requirements were removed.
- [x] The non-atomic duplicate verification requirement was removed.
- [x] Each verification requirement is atomic.
- [x] No “MAY and MUST” persistence wording remains.
- [x] No obsolete cookie or proxy requirement remains active.
- [x] No obsolete HMAC refresh-limiter fingerprint requirement remains active.
- [x] Every checklist claim points to explicit spec content.

## Notes

- This checklist reflects the corrected `spec.md` only.
- No item is marked complete by intention alone.
