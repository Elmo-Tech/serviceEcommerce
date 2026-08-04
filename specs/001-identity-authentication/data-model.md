# Data Model: Identity and Authentication

**Feature:** `001-identity-authentication`  
**Database:** MySQL  
**Status:** Synchronized with `spec.md` and `requirements.md`  
**Last Updated:** 2026-07-29

## Design Rules

- internal database columns use `snake_case`; API keys use `camelCase`;
- plaintext passwords, Access Tokens, Refresh Tokens, recovery codes, and
  Reset Tokens are never persisted;
- approved hashes are persisted only in their authoritative columns;
- no authentication cookie, CSRF, Proxy/BFF, browser-session, or Web-vs-Mobile
  persistence is introduced;
- the Super Admin is the only authenticated MVP actor;
- `users.type = 0` classifies an administrator but does not replace role,
  permission, or active-state enforcement;
- database timestamps and key types follow repository database standards.

## Entity Relationship Overview

```text
users
  ├──< personal_access_tokens
  ├──< refresh_tokens
  ├──< password_resets
  └──<> roles / permissions

refresh_tokens
  └── rotated_to_token_id -> refresh_tokens.id
```

## 1. Administrator User

Authoritative table:

```text
users
```

Required fields used by Feature 001:

| Field | Requirement |
|---|---|
| `id` | Internal identifier; never exposed by the auth profile |
| `name` | Trimmed, 1–150 characters |
| `email` | `trim()` + `mb_strtolower()` before lookup/storage; unique |
| `password` | Approved Laravel password hash |
| `type` | Approved Administrator enum/scalar value (`0`) |
| `is_active` | Independently enforced; provisioning ensures `true` |
| `avatar_disk` | Nullable internal approved-disk identifier |
| `avatar_path` | Nullable internal generated relative path |
| timestamps | Repository-standard timestamps |

Constraints and indexes:

- unique index on normalized `email`;
- avatar disk/path are internal and are never returned directly;
- no device type, Web/Mobile discriminator, CSRF, or browser-session fields.

Provisioning invariants:

- values come from environment-backed configuration;
- no default production credential;
- missing or invalid required production values fail safely;
- password is hashed only when creating the initial user;
- reruns do not duplicate the user or role;
- reruns preserve the existing password and identity values;
- reruns ensure Admin type, active state, and `super-admin` role assignment.

Relationships:

- morph-many Sanctum `personal_access_tokens`;
- morph-many custom `refresh_tokens`;
- morph-many `password_resets`;
- Spatie role and permission relationships.

Auth profile projection contains exactly:

```text
name
email
avatar
role
permissions
```

## 2. Sanctum Access Token

Authoritative table:

```text
personal_access_tokens
```

The schema supplied by the installed Sanctum version remains authoritative.

Feature invariants:

- only Sanctum's token hash is persisted;
- plaintext exists only in successful login or refresh JSON;
- `expires_at` is `null` so the token has no automatic expiration;
- owner is the Administrator User;
- login revokes every prior Access Token before issuing a replacement session;
- successful refresh revokes every predecessor Access Token inside the same
  transaction before issuing the replacement Access Token;
- logout, password change, password reset, inactive-owner detection, and
  Refresh Token reuse revoke the applicable Access Tokens;
- after successful login or refresh, only one Access Token may remain active
  for the administrator.

## 3. Refresh Token

Authoritative table:

```text
refresh_tokens
```

Required fields:

| Field | Requirement |
|---|---|
| `id` | Internal primary key |
| `tokenable_type` | Polymorphic owner type |
| `tokenable_id` | Administrator owner ID |
| `token_hash` | Unique lowercase SHA-256 digest; 64 hexadecimal characters |
| `family_id` | Stable opaque identifier shared by one rotation family |
| `expires_at` | Issuance time + `2592000` seconds |
| `revoked_at` | Nullable; set when no longer active |
| `rotated_to_token_id` | Nullable self-reference to the successful successor |
| `revocation_reason` | Nullable controlled internal value; never public |
| timestamps | Repository-standard timestamps |

Required indexes and constraints:

- unique index on `token_hash`;
- composite index on `(tokenable_type, tokenable_id)`;
- index on `family_id`;
- index on `expires_at`;
- index on `revoked_at`;
- nullable self-reference for `rotated_to_token_id`;
- application validation prevents self-reference and enforces matching owner
  and family for predecessor/successor linkage.

Token material:

- generation uses `64` cryptographically secure random bytes;
- encoding uses an approved URL-safe representation preserving full entropy;
- plaintext exists only during issuance and in the one successful JSON
  response;
- persistence and lookup use only `SHA-256(refreshToken)`;
- plaintext, request bodies, token bytes, and token hashes never enter logs,
  exception output, analytics, URLs, or client-visible errors.

State derivation:

```text
ACTIVE:
  revoked_at = null
  rotated_to_token_id = null
  not expired

ROTATED:
  revoked_at is set
  rotated_to_token_id points to successor
  internal reason records refresh rotation

REVOKED:
  revoked_at is set
  no usable credential remains

EXPIRED:
  expires_at <= now, regardless of revoked_at
```

A structurally valid but unknown, expired, revoked, reused, ownerless,
deleted-owner, inactive-owner, invalid-family, or invalid-rotation token maps
to:

```text
401 REFRESH_TOKEN_INVALID
```

### Atomic Refresh Transaction

A successful refresh is one atomic unit:

1. resolve `SHA-256(refreshToken)`;
2. begin a short MySQL transaction;
3. lock the predecessor Refresh Token row with `lockForUpdate()`;
4. revalidate token, owner, expiry, revocation, family, rotation, and active
   Administrator state;
5. revoke every predecessor Sanctum Access Token for the administrator;
6. create the successor Refresh Token in the same family;
7. mark the predecessor rotated and link it to the successor;
8. issue the replacement Sanctum Access Token;
9. commit.

Post-commit invariant:

```text
active Access Tokens: exactly the replacement Access Token
active Refresh Tokens: exactly the replacement Refresh Token
```

Only one concurrent request may transition one predecessor token. Reuse of a
rotated predecessor revokes all Access Tokens and all remaining Refresh Tokens
for the administrator.

### Refresh Throttling

No token-derived limiter data is persisted.

The refresh limiter is:

```text
10/min by requester IP + SHA-256 fingerprint of the structurally valid
submitted Refresh Token
```

The requester IP is resolved through repository-approved trusted-proxy
configuration. The plaintext Refresh Token is never placed in the limiter key
or logs. Structurally invalid requests without a valid string token use an
IP-only fallback key. The throttle fingerprint is for rate limiting only and is
not a replacement for the authoritative refresh-token hash lookup.

## 4. Password Reset Workflow

Authoritative table:

```text
password_resets
```

Required workflow fields:

| Field | Requirement |
|---|---|
| `id` | Internal primary key |
| owner polymorphic fields | Administrator workflow owner |
| `email_normalized` | Normalized email snapshot |
| `code_hash` | Approved password hash of the six-digit code |
| `code_expires_at` | Creation time + `600` seconds |
| failed-attempt counter | Starts at `0`; maximum `5` |
| verified state/timestamp | Marks the single successful code exchange |
| `reset_token_hash` | Nullable unique SHA-256 digest |
| `reset_token_expires_at` | Verification time + `600` seconds |
| consumed/invalidated state | Marks terminal unusable workflow |
| timestamps | Include the resend-cooldown basis |

Required indexes:

- owner polymorphic index;
- index on normalized email;
- index on code expiry;
- unique nullable index on Reset Token hash;
- index on Reset Token expiry;
- index on consumed/invalidated state.

Secret rules:

- recovery code is generated from a cryptographically secure random source and
  is exactly six numeric digits;
- recovery code is stored only with the approved password hasher;
- Reset Token is generated from at least `32` cryptographically secure random
  bytes using an approved URL-safe representation;
- Reset Token is stored only as SHA-256;
- no code or token is logged.

State behavior:

```text
CODE_ACTIVE:
  not consumed
  not verified
  code not expired
  failed attempts < 5

wrong code:
  failed attempts += 1

fifth failed attempt:
  workflow becomes unusable

successful verification:
  code becomes unusable
  one Reset Token hash is created
  reset-token expiry is set to 600 seconds

successful reset:
  workflow is atomically consumed
  password changes
  all sessions are revoked
```

Concurrency:

- verification locks the workflow row and permits at most one Reset Token
  issuance;
- password reset locks the workflow row and permits at most one successful
  password change.

### Concurrent Forgot-Password Creation

The stable `users` row is the serialization point because two first-time
requests may have no workflow row to lock.

Flow:

1. normalize email and resolve the eligible active Administrator;
2. begin a short transaction;
3. lock the stable `users` row with `lockForUpdate()`;
4. re-check eligibility and the `60`-second cooldown;
5. invalidate previous usable workflows;
6. create exactly one new workflow;
7. commit;
8. send localized mail synchronously after commit;
9. if mail transport fails, invalidate only the newly created workflow.

Two concurrent eligible first-time requests leave at most one newly usable
workflow and at most one mail transport invocation for the serialized outcome.

Unknown or inactive targets create no workflow and send no mail.

## 5. Role and Permission State

Authoritative mechanism:

```text
spatie/laravel-permission
```

Rules:

- `super-admin` is created and assigned idempotently;
- role and permission guard configuration follows the installed repository
  configuration;
- no authentication-specific permission is invented for self-service routes;
- profile mutation cannot change roles or permissions;
- profile returns one role string and a deterministic permission array.

## 6. Avatar State

Binary data is stored on the approved public disk. `users.avatar_disk` and
`users.avatar_path` contain internal metadata only.

Validation:

```text
extensions: jpg, jpeg, png, webp
detected MIME: approved matching image type
maximum size: 2 MB
filename: random and server-generated
```

Replacement compensation:

1. validate and store the new file;
2. transactionally update metadata;
3. delete the new file if the database update fails;
4. after commit, attempt old-file deletion;
5. old-file cleanup failure does not fail the committed profile update;
6. log only safe metadata;
7. no cleanup Queue Job, Command, Cron, or scheduler entry.

Absent or empty-string avatar input preserves the current file. Non-empty
string paths or URLs are rejected.

## 7. Retention and Cleanup

- predecessor Refresh Token metadata is retained long enough for reuse
  detection;
- expired or consumed records may be cleaned only through bounded,
  owner-scoped auth flows;
- no unbounded global cleanup scan;
- no Queue Job, cleanup Command, Cron, or scheduler entry;
- no cookie-to-JSON migration state;
- no Web/Mobile discriminator.

## 8. Data Integrity Verification

Tests must prove:

- normalized Admin email uniqueness;
- provisioning idempotency;
- unique Refresh Token hashes;
- unique Reset Token hashes;
- one successful refresh per predecessor token;
- successful refresh atomically revokes predecessor Access Tokens;
- only the replacement token pair remains active after refresh;
- reused predecessor Refresh Token revokes all sessions;
- one Reset Token per verified code;
- one password reset per Reset Token;
- concurrent Forgot Password leaves at most one usable workflow and one mail
  transport invocation for the serialized outcome;
- exact expiries, cooldown, and failed-attempt thresholds;
- avatar database/filesystem compensation;
- no plaintext secret or approved hash leakage.
