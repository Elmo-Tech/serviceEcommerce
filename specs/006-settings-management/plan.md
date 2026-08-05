# Implementation Plan: Settings Management

**Branch**: `006-settings-management`  
**Date**: 2026-08-02  
**Status**: Ready for Task Generation  
**Specification**: [spec.md](./spec.md)  
**Approved reference**: `docs/features/006-settings-management.md`

## 1. Summary

Feature 006 adds singleton site settings management to the Laravel 13 API:

- Admin read access to the full editable Settings resource.
- Admin partial update through `multipart/form-data` for scalar values, phone
  collection replacement, social-link collection replacement, branding-file
  replacement/removal, location fields, and localized SEO defaults.
- Public localized read access with Arabic/English projection and locale
  headers.
- Safe singleton recovery semantics, idempotent seeding, exact permissions,
  safe public URLs, and atomic update/cleanup behavior.

The implementation stays inside the existing Laravel monolith. Controllers stay
thin, Form Requests own normalization and validation entry points, Resources own
Admin/Public projection, one focused Action owns the transactional update
workflow, and small Services own singleton resolution, Egyptian phone
normalization, and SVG/file validation behavior.

## 2. Technical Context

| Area | Decision |
|---|---|
| Runtime | PHP 8.2, Laravel 13 |
| Database | MySQL with dedicated MySQL test database; no SQLite assumptions |
| Authentication | Sanctum Bearer access tokens for Admin APIs |
| Authorization | Spatie Permission with exact `settings.view` and `settings.update` permissions |
| Storage | Laravel Filesystem on the project-configured public disk, with safe generated filenames and post-commit cleanup; disk names are not persisted per Settings row |
| Testing | Pest, Pint, Larastan/PHPStan, API Feature Tests, persistence tests, and focused atomicity/file-compensation coverage |
| Deployment | Hostinger-compatible PHP/MySQL; no Redis, scheduler, queue worker, Docker, or shell dependency added |
| API format | Shared success/error envelope, `camelCase` payload keys, existing `App\Enums\StatusCode` for application-controlled statuses; no new HTTP-status enum |
| Performance goals | Admin/Public read return one singleton record without N+1 behavior; update validates complete payload and commits or rolls back as one unit |
| Constraints | Backend-only scope, Arabic/English localization, singleton persistence, no audit trail, no cache, no secrets, no OG image |
| Scale/Scope | One Settings row, up to 3 phones, up to 9 predefined social platforms, 3 branding-file fields, 2 keyword arrays, 1 coordinate pair |

## 3. Scope and Contract Freeze

### 3.1 Google Maps URL amendment — 2026-08-04

The implementation exposes `googleMapsUrl` as one nullable scalar backed by
`settings.google_maps_url`. Admin multipart PATCH validates an absolute
HTTP/HTTPS URL with a 2048-character maximum, omission preserves the value, and
an empty string clears it. Both Admin and Public Resources return the URL or
`null`. No coordinate fields are exposed. This amendment supersedes older
coordinate-pair planning references below.

The plan implements exactly:

- **2 protected Admin operations**:
  - `GET /api/v1/admin/settings`
  - `PATCH /api/v1/admin/settings`
- **1 unauthenticated Public operation**:
  - `GET /api/v1/public/settings`

Approved stable machine enums and collections:

- social platforms: `facebook`, `instagram`, `linkedin`, `youtube`, `tiktok`,
  `x`, `telegram`, `pinterest`, `snapchat`
- `hasWhats` uses approved integer semantics: `0=no`, `1=yes`.
- Empty submitted collections clear phones/social links, empty branding fields
  remove their current file, and omission preserves current state.

The OpenAPI 3.1 contract in [contracts/openapi.yaml](./contracts/openapi.yaml) is the planning source for routes, payloads, exact request/response properties, permissions, and error codes. It must contain exactly three operations, unique `operationId` values, resolvable local `$ref` values, no OpenAPI 3.0 `nullable` keywords, and explicit `security: []` on the Public operation.

## 4. Constitution and Governance Gate

### 4.1 Pre-design result

PASS.

The active specification and approved feature reference align with the
constitution and shared standards:

- backend-only Laravel scope remains unchanged
- no unsupported infrastructure is added
- permissions are explicit and backend-enforced
- singleton persistence and atomic update behavior remain MySQL-authoritative
- public responses remain localized and safe
- files remain validated, safely named, and exposed only through approved URLs

No approved exception is required for Feature 006.

### 4.2 Post-design result

PASS.

The design preserves:

- thin controllers and dedicated Form Requests
- explicit Sanctum and Spatie authorization boundaries
- one atomic update workflow for scalar fields, collections, and file-path
  mutations
- Arabic/English localization behavior and stable machine keys
- no raw storage-path exposure
- required Pest, Pint, and Larastan/PHPStan quality gates

No new infrastructure dependency, speculative module, or governance exception
is introduced.

## 5. Source Structure

```text
app/
├── Actions/
│   └── Settings/
│       └── UpdateSettingsAction.php
├── Enums/Settings/
│   └── SocialPlatform.php
├── Http/
│   ├── Controllers/Api/V1/Admin/Settings/
│   │   └── SettingsController.php
│   ├── Controllers/Api/V1/Public/Settings/
│   │   └── SettingsController.php
│   ├── Requests/Api/V1/Admin/Settings/
│   │   └── UpdateSettingsRequest.php
│   └── Resources/Api/V1/
│       ├── Admin/Settings/
│       │   └── AdminSettingsResource.php
│       └── Public/Settings/
│           └── PublicSettingsResource.php
├── Models/
│   ├── Setting.php
│   ├── SettingPhone.php
│   └── SettingSocialLink.php
├── Services/
│   └── Settings/
│       ├── SettingsResolver.php
│       ├── EgyptianPhoneNormalizer.php
│       ├── BrandingFileService.php
│       └── SvgSafetyInspector.php

database/
├── factories/
├── migrations/
└── seeders/

routes/api/v1/
├── admin.php
└── public.php

tests/
├── Feature/Api/V1/Admin/Settings/
├── Feature/Api/V1/Public/Settings/
└── Feature/Database/Settings/
```

The Admin read and Public read flows remain simple and must not gain
unnecessary orchestration layers beyond the singleton resolver and Resources.

## 6. Database Design

The canonical relational model is detailed in
[data-model.md](./data-model.md).

### 6.1 Tables

```text
settings
setting_phones
setting_social_links
```

### 6.2 Foreign-key behavior

- `setting_phones.setting_id` references `settings.id` and cascades on delete.
- `setting_social_links.setting_id` references `settings.id` and cascades on
  delete.
- The application preserves singleton semantics through `settings.id = 1` plus
  deterministic resolver behavior.

### 6.3 Initial query indexes

The migration adds only indexes required by approved write and read paths:

- primary key `settings.id`
- ordered child indexes for `setting_phones(setting_id, position)` and
  `setting_social_links(setting_id, position)`
- uniqueness support for normalized phone within one Settings record
- uniqueness support for social platform within one Settings record

The `settings` row stores only `logo_path`, `footer_logo_path`, and `favicon_path`; disk-name columns are not added because the project uses one configured public disk.

No speculative indexing or search infrastructure is added.

## 7. Core Design Decisions

### 7.1 Singleton resolution

`SettingsResolver` owns deterministic Settings loading:

1. Attempt to resolve `settings.id = 1`.
2. If missing in Admin workflows, restore the singleton with safe placeholder
   values without creating duplicates.
3. If missing in Public read, return the approved safe public projection
   without creating a record as a read side effect.

No `first()`-style unscoped lookup is allowed.

### 7.2 Admin update workflow

`UpdateSettingsAction` owns the atomic write workflow:

1. Normalize multipart scalar and collection input.
2. Validate conflicts, phones, social links, coordinates, and files.
3. Store any replacement files using safe generated names.
4. Open one database transaction.
5. Update scalar Settings columns.
6. Replace phone rows when `phones` is submitted; an empty array clears them.
7. Replace social-link rows when `socialLinks` is submitted; an empty array
   clears them.
8. Update branding paths and removal state.
9. Commit the transaction.
10. Delete superseded files after commit.
11. Delete newly stored files immediately if the transaction fails.

### 7.3 Phone normalization and duplication rules

`EgyptianPhoneNormalizer` owns:

- accepting canonical local numbers containing exactly 10 or 11 digits

- trimming whitespace
- removing spaces, dashes, and parentheses
- converting `+20` and `0020` to local `0`
- rejecting non-Egyptian numbers and invalid mobile patterns
- returning canonical stored format `01012345678`

Duplicate checks run after normalization and before persistence.

### 7.4 Social-platform constraints

The application uses an integer-backed PHP enum. `setting_social_links.platform` is persisted as an unsigned tiny integer, while API Resources expose only the approved stable English machine keys. The stored row order follows submitted order, and duplicate platforms are rejected before persistence.

### 7.5 Branding files and SVG safety

`BrandingFileService` owns:

- approved disk selection
- safe generated filenames
- absolute public URL generation through Laravel Filesystem
- post-commit deletion of old files
- rollback cleanup of newly stored files

`SvgSafetyInspector` owns SVG content inspection rules required by the
feature reference. Invalid or unsafe SVG files are rejected; the backend
must not silently sanitize, rewrite, or modify them. The design must reject
invalid XML/SVG or unsafe
constructs such as `script`, `javascript:`, inline event handlers,
`foreignObject`, and external-resource references.

### 7.6 Response projection

- Admin Resource always returns Arabic and English values together plus
  `availableSocialPlatforms`.
- Public Resource returns the localized `address` plus stable branding,
  contact, phone, map, and social-link keys.
- Neither Resource may expose internal row IDs, timestamps, or raw storage
  paths.

## 8. Transaction and Locking Design

### 8.1 Lock order

Every Admin mutation locks the canonical singleton before reading or replacing
child collections:

```php
Setting::query()
    ->whereKey(1)
    ->lockForUpdate()
    ->firstOrFail();
```

The update workflow uses this canonical mutation order:

```text
lock settings.id = 1
→ read current setting_phones rows in position order
→ read current setting_social_links rows in position order
→ validate locked current state
→ mutate scalar fields
→ replace child rows
→ update branding paths
→ commit
```

The lock prevents concurrent PATCH requests from bypassing the three-phone,
single-WhatsApp, normalized-phone uniqueness, or social-platform uniqueness
rules.

### 8.2 Atomic workflows

Operations requiring explicit transaction boundaries:

- Admin PATCH settings with any scalar, relational, or file-path mutation
- singleton restoration during Admin workflow bootstrap when the Settings row is
  unexpectedly missing

Missing-singleton Admin recovery must be concurrency-safe: perform an idempotent insert such as `insertOrIgnore` for `id = 1`, then re-read `settings.id = 1` under `lockForUpdate`. A concurrent recovery must not surface an unhandled duplicate-key exception or create a second Settings row.

### 8.3 Revalidation rule

Any workflow that mutates Settings must validate the entire submitted state
before destructive relational replacement and must re-check file/removal
conflicts before commit.

## 9. API Design

### 9.1 Request media types

- Admin read: JSON response only
- Admin update: `multipart/form-data` only
- Public read: JSON response only

### 9.2 Admin response behavior

`GET /api/v1/admin/settings` and successful `PATCH /api/v1/admin/settings`
return the complete Admin Settings resource with both languages, file URLs or
`null`, decimal-string coordinates or `null`, ordered phones, ordered social
links, and `availableSocialPlatforms`.

### 9.3 Public response behavior

`GET /api/v1/public/settings` returns one localized projection, includes
`Content-Language` and `Vary: Accept-Language`, and excludes Admin-only
inventory fields. It declares `security: []` in OpenAPI. Locale variants such as `ar-EG` and `en-US` are accepted by the contract and resolved by the existing locale middleware to `ar` or `en`.

### 9.4 OpenAPI 3.1 exactness

The contract must:

- use OpenAPI `3.1.0`
- use JSON Schema null unions instead of `nullable: true`
- declare every Admin/Public response property as required, using `null`   or empty arrays for absent optional data
- set `additionalProperties: false` on exact request and response objects
- document phone/social maximum counts, descriptions, coordinate ranges,   SEO keyword constraints, file types/sizes, and all mutually exclusive   clear/remove combinations
- keep exactly three operations with unique operation IDs
- resolve every local `$ref` successfully

## 10. Permission Mapping

| Area | Permission |
|---|---|
| Admin settings read | `settings.view` |
| Admin settings update | `settings.update` |

`super-admin` receives both permissions through the normal idempotent permission
seeding flow. Public read requires no authentication.

## 11. Testing Strategy

The canonical verification scenarios are detailed in
[quickstart.md](./quickstart.md). Required automated coverage includes:

- admin read success, `401`, and `403`
- public localized read for Arabic and English
- singleton-seed and singleton-recovery behavior
- admin scalar update, collection replacement, and omission semantics
- empty-array collection clearing and empty-value branding removal
- invalid phone, duplicate phone, WhatsApp limit, and invalid social platform
  handling
- coordinate pair validation and invalid URL rejection
- optional scalar empty-string-to-`null` behavior and omitted-field preservation
- duplicate SEO keyword rejection after trim and case-insensitive comparison
- safe SVG acceptance, unsafe SVG rejection, exact file size/type rules,
  empty-value removal, and file cleanup compensation
- safe response projection without IDs or raw storage paths
- database persistence for phones, social links, keywords, and file-path state
- concurrency coverage for row locking and idempotent missing-singleton recovery
- OpenAPI 3.1 validation: parse, local `$ref` resolution, exactly three   operations, unique `operationId` values, exact properties, and no   undocumented fields
- quality gates: Pest, Pint, and Larastan/PHPStan

## 12. Generated Planning Artifacts

- [research.md](./research.md)
- [data-model.md](./data-model.md)
- [quickstart.md](./quickstart.md)
- [contracts/openapi.yaml](./contracts/openapi.yaml)

## 13. Complexity Tracking

No approved governance exception is active for Feature 006.
