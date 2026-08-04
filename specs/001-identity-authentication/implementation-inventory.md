# Feature 001 Implementation Inventory

**Feature:** Identity and Authentication  
**Last updated:** 2026-07-29  
**Scope:** Laravel backend implementation and repository-owned API/deployment artifacts.

## Frozen inputs

- `specs/001-identity-authentication/spec.md` and `specs/001-identity-authentication/checklists/requirements.md` are treated as frozen approved inputs during implementation.
- The requirements checklist currently passes: 181 checked items, 0 incomplete.
- Supporting artifacts were synchronized to the direct JSON token contract before code changes: `plan.md`, `data-model.md`, `quickstart.md`, `contracts/openapi.yaml`, and this task file.

## Installed platform versions

Verified from `composer.json` and `composer.lock`:

| Package | Constraint | Installed |
|---|---:|---:|
| PHP | `^8.3` | Runtime required by Composer |
| `laravel/framework` | `^13.8` | `v13.23.0` |
| `laravel/sanctum` | `^4.3` | `v4.3.3` |
| `spatie/laravel-permission` | `^8.3` | `8.3.0` |
| `spatie/laravel-query-builder` | `^7.3` | `7.3.0` |
| `pestphp/pest` | `^4.7` | `v4.7.5` |
| `pestphp/pest-plugin-laravel` | `^4.1` | `v4.1.0` |
| `larastan/larastan` | `^3.10` | `v3.10.0` |

## Current backend surface map

| Area | Existing target | Decision |
|---|---|---|
| Auth routes | `routes/api/v1/auth.php` | Update refresh route to direct JSON body refresh with only the approved auth-header and rate-limit middleware. |
| Auth controllers | `app/Http/Controllers/Api/V1/Admin/Auth/*` | Reuse controllers, remove cookie writes/clears, and return JSON token payloads only. |
| Auth actions | `app/Actions/Auth/*` | Reuse session actions, remove CSRF token issuance, preserve transaction and revocation behavior. |
| Refresh request | `app/Http/Requests/Api/V1/Admin/Auth/RefreshTokenRequest.php` | Add dedicated request accepting only unmodified non-empty `refreshToken`. |
| Auth resources | `app/Http/Resources/Api/V1/Admin/Auth/*` | Reuse login resource and include JSON refresh token in login response. |
| Session services | `app/Services/Auth/*` | Keep token/revocation services; remove browser cookie and CSRF services. |
| Middleware | `bootstrap/app.php`, `app/Http/Middleware/*` | Remove active Feature 001 Origin-gated refresh and CSRF middleware aliases/classes. |
| Rate limiter | `app/Providers/AppServiceProvider.php` | Update `admin-refresh` limiter to requester IP plus SHA-256 token fingerprint, with IP-only fallback for invalid/missing structure. |
| CORS | `config/cors.php` | Set direct JSON CORS with `supports_credentials=false` and no CSRF header. |
| Sanctum config | `config/sanctum.php` | Disable stateful/browser-session middleware usage for Feature 001. |
| Auth config/env | `config/auth.php`, `.env.example` | Remove auth cookie/proxy env knobs; preserve token lifetimes and exact admin frontend origin. |
| Postman/OpenAPI | `postman/*`, `contracts/openapi.yaml` | Synchronize to JSON body refresh and Bearer protected operations; remove cookie/CSRF contracts. |
| Deployment docs | `deploy/nginx/admin-frontend-api-proxy.conf.example` | Keep only optional transport forwarding guidance; no auth proxy, CSRF forwarding, or Set-Cookie forwarding. |

## Removed legacy implementation concepts

The following active implementation dependencies were removed because the approved contract excludes them:

- `app/Services/Auth/AdminAuthCookieService.php`
- `app/Services/Auth/AdminCsrfService.php`
- `app/Http/Middleware/ValidateAdminOrigin.php`
- `app/Http/Middleware/ValidateAdminCsrfToken.php`
- `admin.origin` and `admin.csrf` route middleware usage
- Admin refresh/auth cookie and CSRF cookie configuration/env values
- Sanctum stateful browser-session middleware configuration for admin auth

## Configuration audit

- `ADMIN_FRONTEND_ORIGIN` remains the exact configured browser origin for CORS validation.
- Access token lifetime is `null` (no automatic expiration).
- Refresh token lifetime remains 2,592,000 seconds.
- Super Admin provisioning remains environment-backed and must not introduce production default credentials.
- Mail configuration remains environment-backed; production values are deployment-owned.
- Public storage/avatar behavior remains repository-owned; internal paths must not leak.
- Trusted proxy behavior is deployment-owned and must be verified in production/staging; no local default production proxy credentials or proxy trust shortcuts were introduced.

## Repository conventions confirmed

- API routes remain versioned under `/api/v1/admin/auth/*`, `/api/v1/admin/*`, and `/api/v1/public/*`.
- The backend remains API-only; no React or Next.js implementation is added to this repository.
- Admin authentication uses `users.type = 0`, Sanctum personal access tokens, and Spatie roles/permissions.
- Request/response keys remain camelCase; database columns remain snake_case.
- Machine error codes and route names remain English.
