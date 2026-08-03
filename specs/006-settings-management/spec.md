# Feature Specification: Settings Management

**Feature Branch**: `[006-settings-management]`  
**Created**: 2026-08-02  
**Status**: Ready for Planning  
**Approved Source**: `docs/features/006-settings-management.md`

## Scope and Governing Context *(mandatory)*

### In Scope

- One global singleton Settings record.
- Admin read access to the complete editable Settings resource.
- Admin partial update through `multipart/form-data`.
- Public localized read access.
- Localized site identity, description, slogan, and address.
- Public email.
- Logo, footer logo, and favicon.
- Up to three Egyptian mobile phone numbers.
- At most one WhatsApp-enabled phone number.
- Dynamic social links selected from predefined platforms.
- Google Maps URL and optional coordinate pair.
- Localized default SEO title, description, and keyword arrays.
- Exact replacement and clear semantics for phone and social collections.
- Safe branding-file replacement, removal, and filesystem compensation.
- Permissions, localization headers, stable machine keys, validation, and tests.

### Out of Scope

- Multiple Settings records.
- Multi-tenant Settings.
- Multiple branches or branch management.
- Cache, Redis, or cache invalidation.
- Audit history or `updatedByAdmin`.
- SMTP configuration or credentials.
- Internal order-notification email.
- Order enable/disable settings.
- WhatsApp-order settings.
- Minimum order value.
- Google Analytics, Google Tag Manager, Meta Pixel, or verification settings.
- Theme colors or frontend theme customization.
- Open Graph image or `defaultOgImage`.
- Secrets of any kind.
- Create or Delete Settings APIs.
- Soft delete.
- Customer authentication.
- Raw storage paths, internal row IDs, or upload internals in API responses.

### Governing Documents Reviewed

- `.specify/memory/constitution.md`
- `AGENTS.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- `docs/02-standards/api-standards.md`
- `docs/02-standards/authorization-standards.md`
- `docs/02-standards/database-standards.md`
- `docs/02-standards/file-storage-standards.md`
- `docs/02-standards/localization-standards.md`
- `docs/02-standards/security-standards.md`
- `docs/02-standards/testing-standards.md`
- `docs/features/006-settings-management.md`

### Known Conflicts

- None.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admin Reviews Settings (Priority: P1)

An authenticated active administrator with `settings.view` can retrieve the
complete editable Settings resource in Arabic and English.

**Why this priority**: The Admin must see the current values before safely
editing a site-wide singleton.

**Independent Test**: Authenticate with `settings.view`, call
`GET /api/v1/admin/settings`, and verify the complete approved Admin contract
without internal IDs, timestamps, or storage paths.

**Acceptance Scenarios**:

1. **Given** an authenticated active administrator with `settings.view`,
   **When** `GET /api/v1/admin/settings` is called,
   **Then** the complete Admin Settings resource is returned.
2. **Given** an unauthenticated request,
   **When** the Admin endpoint is called,
   **Then** the shared `401` response is returned.
3. **Given** an authenticated administrator without `settings.view`,
   **When** the Admin endpoint is called,
   **Then** the shared `403` response is returned.
4. **Given** the Settings singleton is missing unexpectedly,
   **When** the Admin read workflow resolves Settings,
   **Then** the approved service may safely restore `settings.id = 1` using
   placeholder-safe values without creating duplicate records.

---

### User Story 2 — Admin Updates Settings (Priority: P1)

An authenticated active administrator with `settings.update` can partially
update Settings using `multipart/form-data`.

**Why this priority**: Managing public identity, contact, location, SEO,
phones, social links, and branding is the feature’s core value.

**Independent Test**: Submit multipart PATCH requests that update scalars,
replace or clear collections, replace or remove files, and preserve omitted
values.

**Acceptance Scenarios**:

1. **Given** a valid partial update,
   **When** `PATCH /api/v1/admin/settings` is called,
   **Then** omitted values remain unchanged and the complete updated Admin
   resource is returned.
2. **Given** a submitted `phones` array,
   **When** the update succeeds,
   **Then** the current phone collection is replaced atomically by the
   submitted ordered collection.
3. **Given** an explicitly submitted empty `phones` array,
   **When** the update succeeds,
   **Then** all phone rows are deleted.
4. **Given** a submitted `socialLinks` array,
   **When** the update succeeds,
   **Then** the current social-link collection is replaced atomically by the
   submitted ordered collection.
5. **Given** an explicitly submitted empty `socialLinks` array,
   **When** the update succeeds,
   **Then** all social-link rows are deleted.
6. **Given** a valid branding file,
   **When** it is submitted,
   **Then** the old file is replaced safely and the API returns a public URL.
7. **Given** a branding field submitted as an empty value,
   **When** no replacement file is submitted for that field,
   **Then** the stored path becomes `null` and the old file is cleaned up after
   commit.
8. **Given** any invalid phone, social, coordinate, or file input,
   **When** the update fails,
   **Then** no scalar, relational, or file partial state remains.

---

### User Story 3 — Public Visitors Read Localized Settings (Priority: P2)

An unauthenticated visitor can retrieve one localized public-safe Settings
projection.

**Why this priority**: The public site needs backend-managed identity and
contact data after Admin management is available.

**Independent Test**: Call `GET /api/v1/public/settings` with Arabic and English
locale headers and verify projection, headers, stable keys, and data
boundaries.

**Acceptance Scenarios**:

1. **Given** resolved locale `ar`,
   **When** the Public endpoint is called,
   **Then** Arabic values are returned under stable neutral keys.
2. **Given** resolved locale `en`,
   **When** the Public endpoint is called,
   **Then** English values are returned under the same stable neutral keys.
3. **Given** a public response,
   **When** it is serialized,
   **Then** it contains no dual-language fields, internal IDs, timestamps,
   storage paths, or `availableSocialPlatforms`.
4. **Given** the Settings singleton is missing unexpectedly,
   **When** the Public endpoint is called,
   **Then** it MUST return the approved safe default Public representation
   without creating a database record as a read side effect.

---

## Approved Field Inventory *(mandatory)*

### Localized General Fields

```text
siteNameAr
siteNameEn
siteDescriptionAr
siteDescriptionEn
sloganAr
sloganEn
addressAr
addressEn
```

### Branding Fields

```text
logo
footerLogo
favicon
```

### Contact Field

```text
publicEmail
```

### Location Fields

```text
googleMapsUrl
latitude
longitude
```

### SEO Fields

```text
defaultSeoTitleAr
defaultSeoTitleEn
defaultSeoDescriptionAr
defaultSeoDescriptionEn
defaultSeoKeywordsAr[]
defaultSeoKeywordsEn[]
```

### Collections

```text
phones[]
socialLinks[]
```

Collections are cleared by submitting `phones = []` or `socialLinks = []`.
Branding files are removed by submitting the matching `logo`, `footerLogo`, or
`favicon` field as an empty value. No separate clear/remove keys exist.

No field named `defaultOgImage` or `ogImage` exists in requests, persistence,
Admin responses, or Public responses.

---

## Persistent Required and Optional Fields

### Required at All Times

```text
siteNameAr
siteNameEn
publicEmail
```

Rules:

- They are not required in every PATCH request.
- Omission means unchanged.
- When submitted, they cannot be empty or `null`.
- They cannot be removed through a clear flag.

### Optional

```text
siteDescriptionAr
siteDescriptionEn
sloganAr
sloganEn
logo
footerLogo
favicon
addressAr
addressEn
googleMapsUrl
latitude
longitude
defaultSeoTitleAr
defaultSeoTitleEn
defaultSeoDescriptionAr
defaultSeoDescriptionEn
defaultSeoKeywordsAr
defaultSeoKeywordsEn
phones
socialLinks
```

Rules:

- Omission in PATCH means unchanged.
- Sending an optional scalar text field as an empty string (`""`) explicitly
  clears that field.
- The backend MUST normalize an explicitly submitted empty optional scalar text
  value to `null`.
- No additional clear flags may be introduced for optional scalar text fields.
- Coordinates continue to follow their approved paired clear/update rules.
- `siteDescriptionAr` and `siteDescriptionEn` have a maximum length of
  `500` characters.

---

## Functional Requirements *(mandatory)*

### Singleton and Persistence

- **FR-001**: The system MUST maintain exactly one Settings record with
  canonical identity `settings.id = 1`.
- **FR-002**: The system MUST expose no Create or Delete Settings endpoint.
- **FR-003**: The Settings record MUST not use soft delete.
- **FR-004**: An idempotent Seeder MUST create `settings.id = 1` using safe
  placeholders and MUST not create duplicates.
- **FR-005**: Re-running the Seeder MUST preserve existing Admin-edited values
  according to the repository’s established Seeder policy.
- **FR-006**: Admin read/update services MAY safely restore the singleton when
  unexpectedly missing.
- **FR-007**: If the singleton is missing, Public GET MUST return the approved
  safe default Public representation and MUST NOT create a Settings record as
  a side effect.
- **FR-008**: Application code MUST resolve Settings through a deterministic
  singleton service rather than relying on an unguarded `first()` lookup.

### Admin Read and Update

- **FR-009**: The system MUST allow an authorized administrator to retrieve the
  full approved Admin resource.
- **FR-010**: The system MUST allow an authorized administrator to partially
  update Settings through `multipart/form-data`.
- **FR-011**: Omitted scalar, collection, file, and removal fields MUST leave
  their current values unchanged.
- **FR-012**: A successful PATCH MUST return the complete updated Admin
  resource, not only changed fields.
- **FR-013**: Admin responses MUST return Arabic and English values together.
- **FR-014**: `Accept-Language` MUST affect Admin messages and validation errors
  only, not the Admin data projection.
- **FR-014A**: An explicitly submitted empty string for an optional scalar text
  field MUST be normalized to `null`; omission MUST remain unchanged, and no
  additional scalar clear flags may be added.

### Phones

- **FR-015**: A submitted phone item MUST have this exact shape:

```json
{
  "number": "01012345678",
  "hasWhats": 1
}
```

- **FR-016**: The system MUST allow a maximum of three phone entries.
- **FR-017**: The system MUST accept Egyptian mobile numbers only.
- **FR-018**: The system MUST normalize accepted Egyptian equivalents to local
  format before persistence and duplicate checking.
- **FR-019**: Normalization MUST trim whitespace, remove spaces, dashes, and
  parentheses, and convert `+20` or `0020` to the local `0` prefix.
- **FR-020**: The canonical stored format MUST be the normalized local Egyptian
  mobile form, for example `01012345678`.
- **FR-021**: Duplicate normalized phone numbers MUST be rejected.
- **FR-022**: `hasWhats` MUST accept only `0` or `1`.
- **FR-023**: At most one phone may have `hasWhats = 1`.
- **FR-024**: It MUST be valid for all phones to have `hasWhats = 0`.
- **FR-025**: Phone order MUST match submission order.
- **FR-026**: Phone row IDs MUST not be accepted or returned.
- **FR-027**: When `phones` is submitted, the full phone collection MUST be
  replaced atomically.
- **FR-028**: When `phones` is omitted, phones MUST remain unchanged.
- **FR-029**: Submitting `phones = []` MUST delete all phone rows.
- **FR-030**: No separate phone-clear key is accepted.

Accepted equivalent examples include:

```text
01012345678
+201012345678
00201012345678
010 1234 5678
010-1234-5678
(010) 12345678
```

### Social Links

- **FR-031**: A submitted social-link item MUST have this exact shape:

```json
{
  "platform": "facebook",
  "url": "https://facebook.com/example"
}
```

- **FR-032**: Social platforms MUST be selected from the approved set:

```text
facebook
instagram
linkedin
youtube
tiktok
x
telegram
pinterest
snapchat
```

- **FR-033**: Free-text or unsupported platform values MUST be rejected.
- **FR-034**: Each platform may appear at most once.
- **FR-035**: Every submitted link MUST contain a valid URL.
- **FR-036**: Presence in `socialLinks` means visible; there is no `isActive`.
- **FR-037**: Social-link order MUST match submission order.
- **FR-038**: Social-link row IDs MUST not be accepted or returned.
- **FR-039**: When `socialLinks` is submitted, the full collection MUST be
  replaced atomically.
- **FR-040**: When `socialLinks` is omitted, social links MUST remain unchanged.
- **FR-041**: Submitting `socialLinks = []` MUST delete all social links.
- **FR-042**: No separate social-link-clear key is accepted.
- **FR-043**: The Admin resource MUST return `availableSocialPlatforms` as the
  approved stable English machine-key list.

### Location

- **FR-044**: `googleMapsUrl` MUST be a valid URL when submitted.
- **FR-045**: `latitude` and `longitude` MUST be submitted together or both
  omitted/cleared.
- **FR-046**: `latitude` MUST be between `-90` and `90`.
- **FR-047**: `longitude` MUST be between `-180` and `180`.
- **FR-048**: `googleMapsUrl` MAY exist without coordinates.
- **FR-049**: Google Maps iframe or embed HTML MUST not be stored.
- **FR-050**: Coordinates MUST be returned as decimal strings or `null`.

### Default SEO

- **FR-051**: The system MUST manage localized default SEO title, description,
  and keyword arrays only.
- **FR-052**: SEO keywords MUST be arrays, not comma-separated strings.
- **FR-053**: Every keyword MUST be a non-empty string after trimming.
- **FR-054**: Duplicate keywords within the same language array MUST be
  rejected with `422 VALIDATION_ERROR` after trimming and case-insensitive
  comparison; the original submitted order and casing MUST otherwise be
  preserved.
- **FR-055**: Arabic and English SEO content MUST be managed independently.
- **FR-057**: No Open Graph image field MUST exist anywhere in the feature
  contract.

### Branding Files

- **FR-057**: `logo` and `footerLogo` MUST accept only:

```text
jpg
jpeg
png
webp
svg
```

- **FR-058**: `logo` and `footerLogo` MUST each have a maximum size of `5 MB`.
- **FR-059**: `favicon` MUST accept only:

```text
png
ico
svg
```

- **FR-060**: `favicon` MUST have a maximum size of `1 MB`.
- **FR-061**: The API MUST never accept a client-controlled existing storage
  path.
- **FR-062**: Omitting a file field MUST preserve the current file.
- **FR-063**: Submitting a valid new file MUST replace the current file.
- **FR-064**: Submitting `logo`, `footerLogo`, or `favicon` as an explicit empty
  value MUST clear the matching stored path.
- **FR-065**: No separate branding remove key is accepted.
- **FR-066**: File responses MUST contain absolute public URLs or `null`, never
  internal paths.

### SVG Security

- **FR-067**: SVG validation MUST inspect content and MUST NOT rely only on file
  extension or client-provided MIME type.
- **FR-068**: Invalid XML or invalid SVG MUST be rejected.
- **FR-069**: SVG content containing or enabling any of the following MUST be
  rejected or safely removed:

```text
script
javascript:
onload
onclick
onerror
foreignObject
iframe
object
embed
external CSS
external images
external resources
```

- **FR-070**: Stored SVG files MUST be safe to serve publicly.

### Atomicity and Filesystem Compensation

- **FR-071**: Scalar updates, phone replacement, social-link replacement, and
  file-path updates MUST be one logically atomic operation.
- **FR-072**: The complete request MUST be validated before destructive
  relational changes.
- **FR-073**: A newly stored file MUST be deleted if the database transaction
  fails.
- **FR-074**: An old file MUST be deleted only after the new database state is
  committed.
- **FR-075**: If post-commit old-file deletion fails, the valid database state
  MUST remain committed and the cleanup failure MUST be logged.
- **FR-076**: A failed update MUST not leave partial phone rows, partial social
  rows, invalid required fields, database paths to failed uploads, or newly
  orphaned files.

---

## Actors and Authorization *(mandatory)*

- **AR-001**: `GET /api/v1/admin/settings` MUST require authentication, active
  Admin status, and `settings.view`.
- **AR-002**: `PATCH /api/v1/admin/settings` MUST require authentication, active
  Admin status, and `settings.update`.
- **AR-003**: Protected unauthenticated requests MUST return `401`.
- **AR-004**: Protected requests without the required permission MUST return
  `403`.
- **AR-005**: `GET /api/v1/public/settings` MUST require no authentication.
- **AR-006**: `super-admin` MUST receive both permissions through the normal
  idempotent permission seeding flow.
- **AR-007**: The feature MUST not implement a hidden super-admin bypass.

---

## Trust, Security, and Content Boundaries *(mandatory)*

- **TR-001**: Every submitted scalar, phone, social link, clear flag, remove
  flag, coordinate, and file MUST be treated as untrusted.
- **TR-002**: The backend MUST not trust client filenames, file extensions,
  MIME declarations, or storage paths.
- **TR-003**: The backend MUST generate safe filenames and prevent path
  traversal.
- **TR-004**: Duplicate phone checks MUST run after normalization.
- **TR-005**: Duplicate social-platform checks MUST run against stable machine
  keys.
- **TR-006**: Public and Admin responses MUST expose no internal storage path.
- **TR-007**: Public responses MUST expose no internal IDs, timestamps,
  Admin-only platform inventory, or dual-language values.
- **TR-008**: Internal exception messages, SQL details, and server paths MUST
  not be exposed.

---

## Data Integrity Requirements *(mandatory)*

- **DI-001**: `settings.id = 1` MUST remain the single canonical Settings row.
- **DI-002**: Seeder execution MUST be idempotent.
- **DI-003**: Phone replacement MUST be atomic and ordered.
- **DI-004**: Social-link replacement MUST be atomic and ordered.
- **DI-005**: A maximum of three phones MUST be preserved under concurrent or
  repeated updates.
- **DI-006**: A maximum of one WhatsApp-enabled phone MUST be preserved.
- **DI-007**: Duplicate normalized phones and duplicate social platforms MUST
  not persist.
- **DI-008**: Correctness MUST not depend on cache or hidden in-memory state.
- **DI-009**: File compensation MUST preserve database/filesystem consistency
  according to FR-073 through FR-076.

---

## API Contract and Localization *(mandatory)*

### Endpoints

- **API-001**: The feature MUST expose:

```http
GET   /api/v1/admin/settings
PATCH /api/v1/admin/settings
GET   /api/v1/public/settings
```

- **API-002**: PATCH MUST always use `multipart/form-data`.
- **API-003**: All endpoints MUST use the approved shared response envelope.
- **API-004**: Application-controlled statuses MUST use the existing
  `StatusCode::*`.
- **API-005**: All request and response fields MUST use `camelCase`.

### Admin Response Contract

Successful Admin GET and PATCH `data` MUST have this shape:

```json
{
  "siteNameAr": "اسم الموقع",
  "siteNameEn": "Website Name",
  "siteDescriptionAr": "وصف الموقع باللغة العربية",
  "siteDescriptionEn": "Website description in English",
  "sloganAr": "الشعار النصي بالعربية",
  "sloganEn": "English slogan",
  "logo": "https://api.example.com/storage/settings/logo/example.webp",
  "footerLogo": "https://api.example.com/storage/settings/footer-logo/example.webp",
  "favicon": "https://api.example.com/storage/settings/favicon/example.ico",
  "publicEmail": "info@example.com",
  "phones": [
    {
      "number": "01012345678",
      "hasWhats": 1
    }
  ],
  "addressAr": "القاهرة، مصر",
  "addressEn": "Cairo, Egypt",
  "googleMapsUrl": "https://maps.google.com/example",
  "latitude": "30.0444000",
  "longitude": "31.2357000",
  "socialLinks": [
    {
      "platform": "facebook",
      "url": "https://facebook.com/example"
    }
  ],
  "availableSocialPlatforms": [
    "facebook",
    "instagram",
    "linkedin",
    "youtube",
    "tiktok",
    "x",
    "telegram",
    "pinterest",
    "snapchat"
  ],
  "defaultSeoTitleAr": "العنوان الافتراضي للموقع",
  "defaultSeoTitleEn": "Default website SEO title",
  "defaultSeoDescriptionAr": "وصف SEO الافتراضي باللغة العربية",
  "defaultSeoDescriptionEn": "Default SEO description in English",
  "defaultSeoKeywordsAr": [
    "خدمات",
    "تصميم"
  ],
  "defaultSeoKeywordsEn": [
    "services",
    "design"
  ]
}
```

- **API-006**: Both languages MUST always be returned in Admin data.
- **API-007**: File values MUST be absolute public URLs or `null`.
- **API-008**: Coordinates MUST be decimal strings or `null`.
- **API-009**: Admin data MUST contain no phone/social row IDs, timestamps,
  audit fields, or storage paths.

### Public Response Contract

For Arabic, successful Public `data` MUST have this shape:

```json
{
  "siteName": "اسم الموقع",
  "siteDescription": "وصف الموقع باللغة العربية",
  "slogan": "الشعار النصي بالعربية",
  "logo": "https://api.example.com/storage/settings/logo/example.webp",
  "footerLogo": "https://api.example.com/storage/settings/footer-logo/example.webp",
  "favicon": "https://api.example.com/storage/settings/favicon/example.ico",
  "email": "info@example.com",
  "phones": [
    {
      "number": "01012345678",
      "hasWhats": 1
    }
  ],
  "address": "القاهرة، مصر",
  "googleMapsUrl": "https://maps.google.com/example",
  "latitude": "30.0444000",
  "longitude": "31.2357000",
  "socialLinks": [
    {
      "platform": "facebook",
      "url": "https://facebook.com/example"
    }
  ],
  "defaultSeo": {
    "title": "العنوان الافتراضي للموقع",
    "description": "وصف SEO الافتراضي باللغة العربية",
    "keywords": [
      "خدمات",
      "تصميم"
    ]
  }
}
```

For English, the same keys MUST contain English localized values.

- **API-010**: Public contact email key MUST be `email`; Admin key MUST be
  `publicEmail`.
- **API-011**: Public responses MUST return one localized value per translatable
  field.
- **API-012**: Public responses MUST not return `*Ar`, `*En`, internal IDs,
  timestamps, storage paths, or `availableSocialPlatforms`.
- **API-013**: Public responses MUST include:

```http
Content-Language: ar|en
Vary: Accept-Language
```

- **API-014**: Stable English machine keys MUST remain unchanged between Arabic
  and English responses.
- **API-015**: If `settings.id = 1` is missing, the Public endpoint MUST return
  the same approved Public response shape populated with safe defaults and
  `null` or empty collections for optional values, without inserting or
  updating any database row.

---

## Error Contract

Feature-specific stable codes may include:

```text
SETTINGS_NOT_FOUND
INVALID_PHONE_NUMBER
PHONE_LIMIT_REACHED
DUPLICATE_PHONE_NUMBER
MULTIPLE_WHATSAPP_NUMBERS
INVALID_SOCIAL_PLATFORM
DUPLICATE_SOCIAL_PLATFORM
INVALID_SOCIAL_URL
INVALID_COORDINATES
UNSAFE_SVG
FILE_UPLOAD_FAILED
VALIDATION_ERROR
```

Status guidance:

- Validation failures: `422`.
- Unauthenticated Admin request: `401`.
- Missing permission: `403`.
- Unexpected file/database failure: project-standard safe server error.
- Missing/recovery behavior for the singleton MUST follow the approved service
  workflow without exposing internal details.

---

## Key Entities *(mandatory)*

### Settings

The singleton site configuration record containing localized identity, contact,
location, SEO, and branding paths.

### Setting Phone

A value-object-like relational row containing:

```text
number
hasWhats
position
```

### Setting Social Link

A value-object-like relational row containing:

```text
platform
url
position
```

### Branding File

One of:

```text
logo
footerLogo
favicon
```

managed with safe upload, replacement, removal, and compensation behavior.

---

## Verification Requirements *(mandatory)*

- **VR-001**: Tests MUST cover Admin GET success, `401`, and `403`.
- **VR-002**: Tests MUST cover Admin PATCH partial scalar updates and omitted
  field preservation.
- **VR-003**: Tests MUST cover all persistent required-field rules.
- **VR-004**: Tests MUST cover Arabic and English Public projections,
  `Content-Language`, and `Vary`.
- **VR-005**: Tests MUST prove Admin returns both languages and Public returns
  one language.
- **VR-006**: Tests MUST cover all phone normalization examples and duplicate
  detection after normalization.
- **VR-007**: Tests MUST cover the three-phone maximum, zero/one WhatsApp
  success, and two-WhatsApp rejection.
- **VR-008**: Tests MUST cover phone full replacement, single deletion by
  resubmitting remaining items, and empty-array clearing.
- **VR-009**: Tests MUST cover all approved social platforms, unsupported
  values, duplicate platforms, invalid URLs, ordering, full replacement,
  and empty-array clearing.
- **VR-010**: Tests MUST cover coordinate-pair rules, bounds, decimal-string
  responses, and URL-only usage.
- **VR-011**: Tests MUST cover SEO arrays, duplicate keyword rejection after
  trimming and case-insensitive comparison, and absence of all OG-image fields.
- **VR-012**: Tests MUST cover file type and maximum size for every branding
  field.
- **VR-013**: Tests MUST cover safe SVG success and every approved unsafe SVG
  boundary.
- **VR-014**: Tests MUST cover keep, replace, remove, and file/remove conflict
  behavior for all branding files.
- **VR-015**: Tests MUST prove new-file compensation on database failure.
- **VR-016**: Tests MUST prove old-file deletion occurs only after commit.
- **VR-017**: Tests MUST prove post-commit deletion failure preserves the valid
  database state and records cleanup failure.
- **VR-018**: At least one test MUST prove atomicity across scalar fields,
  collection replacement, and file upload.
- **VR-019**: Database tests MUST cover singleton integrity, foreign keys,
  ordering fields, JSON SEO casts, coordinate precision, and applicable unique
  constraints.
- **VR-020**: Tests MUST prove no response exposes storage paths, internal row
  IDs, timestamps, or audit fields.
- **VR-021**: Tests MUST prove that a missing singleton Public GET returns the
  approved safe default representation without writing to the database.
- **VR-022**: Tests MUST prove that an explicitly submitted empty optional
  scalar text value becomes `null`, while an omitted field remains unchanged.
- **VR-023**: The implementation MUST pass the dedicated MySQL Pest suites,
  Pint, and the configured PHPStan/Larastan gate.

---

## Edge Cases

- Settings row is missing during Admin GET.
- Settings row is missing during Public GET.
- Fourth phone submitted.
- Duplicate numbers that differ only by formatting or `+20`/`0020`.
- Two phones marked as WhatsApp.
- Empty phone collection versus omitted collection.
- Duplicate social platforms.
- Unsupported social machine key.
- Invalid social URL.
- Empty social collection versus omitted collection.
- One coordinate submitted without the other.
- Out-of-range coordinate.
- Unsafe SVG with script, event handler, external resource, or embedded HTML.
- New file stored, then database transaction fails.
- Database commit succeeds, then old-file deletion fails.
- Unsupported locale variant resolves through the existing locale middleware.
- Optional localized field cleared.
- Required localized field submitted empty or null.
- Public response accidentally includes dual-language fields.
- Missing singleton Public GET writes to the database.
- Optional scalar text is submitted as an empty string.
- Duplicate SEO keywords differ only by whitespace or letter casing.
- Any request or response includes an OG-image field.

---

## Success Criteria *(mandatory)*

- **SC-001**: An authorized administrator can retrieve the complete approved
  Settings contract in one request.
- **SC-002**: A valid partial multipart update changes only submitted values and
  returns the complete updated Admin resource.
- **SC-003**: Phone and social collections can each be replaced, reduced,
  reordered, or fully cleared without partial persistence.
- **SC-004**: Every supported Egyptian phone equivalent is normalized
  consistently, and invalid or duplicate numbers are rejected.
- **SC-005**: Branding files can be preserved, replaced, and removed without
  exposing storage paths or leaving new orphan files after failure.
- **SC-006**: Public Arabic and English responses use the exact approved keys,
  localized values, and locale headers.
- **SC-007**: Repeated seeding and updates preserve exactly one Settings row.
- **SC-008**: No response contains internal IDs, timestamps, storage paths,
  audit fields, or OG-image fields.
- **SC-009**: A missing singleton Public request returns the approved safe
  default representation without creating or modifying database rows.
- **SC-010**: Empty optional scalar text input clears to `null`, omitted scalar
  fields remain unchanged, and duplicate SEO keywords are rejected.
- **SC-011**: The feature passes all required MySQL, API, file, localization,
  authorization, atomicity, Pint, and PHPStan/Larastan verification.

---

## Assumptions

- The feature is backend API-only.
- The frontend sends Admin updates as `multipart/form-data`.
- The existing locale middleware resolves supported locale variants.
- Public branding files are served through the project-approved public-storage
  mechanism.
- No cache or additional infrastructure is introduced.
- No requirement outside `docs/features/006-settings-management.md` is implied.
