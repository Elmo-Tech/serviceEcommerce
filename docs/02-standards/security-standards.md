# Service Commerce Backend — Security Standards

> Scope: application, API, authentication, browser, deployment, and operations

> Status: Project-wide mandatory standard

## 1. Final security decisions

```text
Admin frontend and backend may live on separate domains
Production transport: HTTPS only
Authentication cookies: not used
CSRF refresh flow: not used
Access-Control-Allow-Credentials: false for the approved admin Web flow
Access token Web storage: runtime memory only
Refresh token Web storage: sessionStorage only
Refresh token Mobile storage: platform secure storage only
```

## 2. Browser trust boundary

The approved Admin Web model uses explicit Bearer credentials.

Rules:

- frontend may call `https://api.backend-example.net/api/v1` directly
- backend origin may be browser-visible configuration
- no same-origin proxy/BFF is required
- no auth cookie, `withCredentials=true`, or `credentials: include` flow is
  used

## 3. Token-handling rules

- access token lifetime: `900` seconds
- refresh token lifetime: `2592000` seconds
- refresh token accepted only from approved JSON body field
- refresh-token reuse revokes all sessions
- plain access tokens and refresh tokens are never persisted
- refresh tokens are stored only as SHA-256 hashes

Never allow tokens in:

- cookies
- URLs
- query strings
- logs
- analytics
- error trackers
- exception context

## 4. Accepted Web risk and compensating controls

Accepted risk:

```text
Refresh tokens in sessionStorage are readable by JavaScript and can be stolen
by successful XSS.
```

Required compensating controls:

- strict CSP
- no `unsafe-inline` unless separately approved
- no `unsafe-eval`
- dependency review and locking
- safe rendering and output encoding
- no token logging
- short access-token lifetime
- refresh rotation
- reuse detection with immediate all-session revocation
- exact allow-listed CORS
- HTTPS only

## 5. CORS rules

- exact origin allow-list only
- no wildcard production CORS
- no arbitrary origin reflection
- allowed methods include `OPTIONS`
- allowed headers are explicitly allow-listed
- exposed headers are explicitly allow-listed
- `supports_credentials=false`

CORS is a browser access policy, not the authentication mechanism.

## 6. Response and logging safety

Never log:

- passwords
- access tokens
- refresh tokens
- reset tokens
- recovery codes
- raw Authorization headers
- raw sensitive request bodies

Never expose in responses:

- stack traces
- SQL
- filesystem paths
- token hashes
- raw avatar paths
- internal exception details

## 7. Recovery-flow security

- unknown and inactive accounts remain enumeration-safe
- recovery codes are six digits and hashed only
- reset tokens are returned once and hashed only
- verification and reset flows require single-winner concurrency control
- recovery mail sends after commit and remains synchronous

## 8. Required security verification

- no auth `Set-Cookie`
- no refresh Origin or CSRF requirement
- exact allow-listed CORS works for approved origin only
- `supports_credentials=false` remains explicit
- no sensitive leakage appears in logs or responses
