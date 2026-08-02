# Feature 006 — Settings Management

## 1. Overview

This feature manages one global Settings record for the whole application.

It covers:

- Localized website identity.
- Logo, footer logo, and favicon.
- One public email.
- Up to three Egyptian phone numbers.
- At most one WhatsApp-enabled phone number.
- One localized business address.
- Google Maps URL and optional coordinates.
- Dynamic social links selected from predefined platforms.
- Localized default SEO title, description, and keyword arrays.
- Admin and Public APIs.
- Permissions and seed data.

It does not cover analytics, SMTP, order settings, multiple branches, theme
colors, cache, audit history, secrets, or Open Graph images.

---

## 2. Module Name

```text
006-settings-management
```

Canonical reference:

```text
docs/features/006-settings-management.md
```

---

## 3. Actors

### Administrator

An authenticated active administrator may read or update Settings according to
the assigned permissions.

### Public Visitor

An unauthenticated visitor may read public-safe localized Settings.

---

## 4. Permissions

```text
settings.view
settings.update
```

Rules:

- `GET /api/v1/admin/settings` requires `settings.view`.
- `PATCH /api/v1/admin/settings` requires `settings.update`.
- `GET /api/v1/public/settings` is public.
- `super-admin` receives both permissions through the existing idempotent
  permission Seeder.
- There is no hidden bypass.

Admin middleware order:

```text
auth:sanctum
EnsureUserIsAdministrator
EnsureAdminIsActive
permission
endpoint
```

---

## 5. Singleton Rules

There is exactly one Settings record:

```text
settings.id = 1
```

Rules:

- No Create API.
- No Delete API.
- No soft delete.
- Seeder creates the record with placeholder values.
- Admin read/update services may safely restore the record if missing.
- Public GET must not create records as a side effect.
- Code must not depend on an unguarded `first()` lookup.
- A dedicated Settings service is the canonical access layer.

---

## 6. Database Design

Use explicit relational tables:

```text
settings
setting_phones
setting_social_links
```

Do not use a key/value settings table.

### 6.1 `settings`

Suggested columns:

```text
id
site_name_ar
site_name_en
site_description_ar nullable
site_description_en nullable
slogan_ar nullable
slogan_en nullable
logo_path nullable
footer_logo_path nullable
favicon_path nullable
public_email
address_ar nullable
address_en nullable
google_maps_url nullable
latitude nullable
longitude nullable
default_seo_title_ar nullable
default_seo_title_en nullable
default_seo_description_ar nullable
default_seo_description_en nullable
default_seo_keywords_ar nullable
default_seo_keywords_en nullable
created_at
updated_at
```

Recommended types:

```text
site_name_ar                    varchar
site_name_en                    varchar
site_description_ar             varchar(500) nullable
site_description_en             varchar(500) nullable
slogan_ar                       varchar nullable
slogan_en                       varchar nullable
logo_path                       varchar nullable
footer_logo_path                varchar nullable
favicon_path                    varchar nullable
public_email                    varchar
address_ar                      text nullable
address_en                      text nullable
google_maps_url                 text nullable
latitude                        decimal(10,7) nullable
longitude                       decimal(10,7) nullable
default_seo_title_ar            varchar nullable
default_seo_title_en            varchar nullable
default_seo_description_ar      varchar nullable
default_seo_description_en      varchar nullable
default_seo_keywords_ar         json nullable
default_seo_keywords_en         json nullable
```

### 6.2 `setting_phones`

Suggested columns:

```text
id
setting_id
number
has_whats
position
created_at
updated_at
```

Rules:

- Maximum three rows.
- Normalized phone is unique within the Settings record.
- Maximum one row with `has_whats = 1`.
- Position preserves Admin order.
- Row IDs are not exposed in the API.

### 6.3 `setting_social_links`

Suggested columns:

```text
id
setting_id
platform
url
position
created_at
updated_at
```

Rules:

- `platform` uses an integer-backed enum.
- Each platform appears at most once.
- URL is required and valid.
- Position preserves Admin order.
- Presence means active and visible.
- Row IDs are not exposed in the API.

---

## 7. Localized General Fields

Fields:

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

Persistent required fields:

```text
siteNameAr
siteNameEn
publicEmail
```

Optional fields:

```text
siteDescriptionAr
siteDescriptionEn
sloganAr
sloganEn
addressAr
addressEn
```

Rules:

- Description maximum length is 500 characters.
- Admin responses always return Arabic and English.
- Public responses return one localized value based on `Accept-Language`.
- Required fields cannot be cleared.
- Optional fields may be cleared.

---

## 8. Public Email

Field:

```text
publicEmail
```

Rules:

- Required at all times.
- Must be a valid email.
- Cannot be empty or `null`.
- Returned to Admin and Public APIs.
- No SMTP credentials.
- No internal notification email.

---

## 9. Phones

Admin logical shape:

```json
{
  "phones": [
    {
      "number": "01012345678",
      "hasWhats": 1
    },
    {
      "number": "01112345678",
      "hasWhats": 0
    }
  ]
}
```

Multipart shape:

```text
phones[0][number] = 01012345678
phones[0][hasWhats] = 1
phones[1][number] = 01112345678
phones[1][hasWhats] = 0
```

Rules:

- `phones` is optional in PATCH.
- Maximum array size: 3.
- Egyptian mobile numbers only.
- Duplicate normalized numbers are rejected.
- `hasWhats` accepts `0` or `1`.
- Maximum one WhatsApp-enabled number.
- Zero WhatsApp-enabled numbers is valid.
- Submitted order is preserved.
- No phone IDs are accepted or returned.

### 9.1 Egyptian Phone Normalization

Accepted equivalents include:

```text
01012345678
+201012345678
00201012345678
010 1234 5678
010-1234-5678
(010) 12345678
```

Canonical stored value:

```text
01012345678
```

Normalization must:

- Trim whitespace.
- Remove spaces, dashes, and parentheses.
- Convert `+20` and `0020` to local `0`.
- Reject non-Egyptian numbers.
- Reject invalid Egyptian mobile formats.
- Perform duplicate checks after normalization.

### 9.2 Phone Replacement and Deletion

Phone arrays use full replacement.

```text
phones absent + clearPhones absent
→ no change

phones submitted
→ replace the full collection

clearPhones = 1
→ delete all phones
```

Invalid:

```text
phones submitted + clearPhones = 1
```

Result:

```text
422 VALIDATION_ERROR
```

To delete one phone, the frontend removes it locally and submits the remaining
array.

---

## 10. Social Links

The Admin UI uses a dynamic list with a Select for the platform.

Initial supported platforms:

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

Logical shape:

```json
{
  "socialLinks": [
    {
      "platform": "facebook",
      "url": "https://facebook.com/example"
    },
    {
      "platform": "instagram",
      "url": "https://instagram.com/example"
    }
  ]
}
```

Rules:

- Platform is selected from a predefined enum.
- Free-text platform names are not allowed.
- Duplicate platforms are rejected.
- URL is required and valid.
- Presence means visible.
- No separate `isActive`.
- Submitted order is preserved.
- No social-link IDs are accepted or returned.

### 10.1 Social Replacement and Deletion

```text
socialLinks absent + clearSocialLinks absent
→ no change

socialLinks submitted
→ replace the full collection

clearSocialLinks = 1
→ delete all links
```

Invalid:

```text
socialLinks submitted + clearSocialLinks = 1
```

Result:

```text
422 VALIDATION_ERROR
```

To delete one link, the frontend removes it locally and submits the remaining
array.

---

## 11. Location

Fields:

```text
googleMapsUrl
latitude
longitude
```

Rules:

- All are optional.
- `googleMapsUrl` must be a valid URL.
- Coordinates must be submitted together.
- Latitude range: `-90` to `90`.
- Longitude range: `-180` to `180`.
- Coordinates are returned as decimal strings or `null`.
- Google Maps iframe/embed HTML is not stored.
- `googleMapsUrl` may exist without coordinates.

---

## 12. Default SEO

Fields:

```text
defaultSeoTitleAr
defaultSeoTitleEn
defaultSeoDescriptionAr
defaultSeoDescriptionEn
defaultSeoKeywordsAr[]
defaultSeoKeywordsEn[]
```

Rules:

- All fields are optional.
- Keywords are arrays, not comma-separated strings.
- Each keyword must be a non-empty string.
- Admin returns both languages.
- Public returns the requested language only.
- There is no `defaultOgImage`.
- There is no `ogImage` in any request or response.

---

## 13. Branding Files

Fields:

```text
logo
footerLogo
favicon
```

### Logo and Footer Logo

Allowed formats:

```text
jpg
jpeg
png
webp
svg
```

Maximum size:

```text
5 MB
```

### Favicon

Allowed formats:

```text
png
ico
svg
```

Maximum size:

```text
1 MB
```

---

## 14. SVG Security

SVG is allowed for all three branding fields.

The backend must inspect content, not only extension or MIME type. Invalid or
unsafe SVG is rejected unchanged; it is never silently sanitized or rewritten.

Reject or remove unsafe content including:

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

Invalid or unsafe SVG returns:

```text
422 VALIDATION_ERROR
```

---

## 15. File Update Semantics

Admin updates always use:

```text
multipart/form-data
```

The client must never submit existing storage paths.

### Keep Current File

Do not send the file or removal flag.

```text
logo absent
removeLogo absent
→ keep current logo
```

### Replace File

```text
logo = binary file
```

Workflow:

1. Validate file.
2. Reject invalid or unsafe SVG without rewriting it.
3. Store new file using a safe generated name.
4. Update database path.
5. Commit transaction.
6. Delete previous file after commit.

### Remove File

Removal flags:

```text
removeLogo
removeFooterLogo
removeFavicon
```

Example:

```text
removeLogo = 1
```

Workflow:

1. Set database path to `null`.
2. Commit transaction.
3. Delete old file after commit.

### Conflict

Invalid:

```text
logo = new-file.png
removeLogo = 1
```

The same applies to footer logo and favicon.

Result:

```text
422 VALIDATION_ERROR
```

### Filesystem Compensation

If a new file is stored but the database update fails:

- Delete the new file.
- Preserve the old database value and file.

If database update succeeds:

- Delete superseded files after commit.

If post-commit old-file deletion fails:

- Keep the valid database state.
- Log the cleanup failure.
- Do not expose the old file in the API.

---

## 16. Admin APIs

### Show Settings

```http
GET /api/v1/admin/settings
```

Permission:

```text
settings.view
```

### Update Settings

```http
PATCH /api/v1/admin/settings
```

Permission:

```text
settings.update
```

Content type:

```text
multipart/form-data
```

Behavior:

- Partial update.
- Omitted scalar fields remain unchanged.
- Submitted phone array replaces all phones.
- Submitted social array replaces all links.
- Clear flags delete complete collections.
- File fields replace files.
- Removal flags clear files.
- Success returns the full updated Admin resource.
- Uses shared `ApiResponse`.
- Uses existing `StatusCode::*`.

---

## 17. Public API

```http
GET /api/v1/public/settings
```

Authentication:

```text
None
```

Localization:

```http
Accept-Language: ar
```

or:

```http
Accept-Language: en
```

Headers:

```http
Content-Language: ar|en
Vary: Accept-Language
```

Rules:

- Return one localized value per translatable field.
- Keep stable English machine keys.
- Do not return both languages.
- Do not return internal IDs.
- Do not return storage paths.
- Do not return timestamps.
- Do not return available platform configuration.

---

## 18. Admin Response

Example `data`:

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
    },
    {
      "number": "01112345678",
      "hasWhats": 0
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

Admin rules:

- Both languages are always returned.
- `Accept-Language` affects messages and validation errors only.
- File fields return absolute public URLs or `null`.
- Coordinates return decimal strings or `null`.
- No IDs, timestamps, audit fields, or storage paths.

---

## 19. Public Arabic Response

Example `data`:

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

---

## 20. Public English Response

Example `data`:

```json
{
  "siteName": "Website Name",
  "siteDescription": "Website description in English",
  "slogan": "English slogan",
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
  "address": "Cairo, Egypt",
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
    "title": "Default website SEO title",
    "description": "Default SEO description in English",
    "keywords": [
      "services",
      "design"
    ]
  }
}
```

---

## 21. Multipart Update Example

```text
siteNameAr = اسم الموقع
siteNameEn = Website Name
publicEmail = info@example.com

phones[0][number] = 01012345678
phones[0][hasWhats] = 1

phones[1][number] = 01112345678
phones[1][hasWhats] = 0

addressAr = القاهرة، مصر
addressEn = Cairo, Egypt
googleMapsUrl = https://maps.google.com/example
latitude = 30.0444000
longitude = 31.2357000

socialLinks[0][platform] = facebook
socialLinks[0][url] = https://facebook.com/example

defaultSeoKeywordsAr[0] = خدمات
defaultSeoKeywordsAr[1] = تصميم

defaultSeoKeywordsEn[0] = services
defaultSeoKeywordsEn[1] = design

logo = {binary file}
```

---

## 22. Atomic Update Behavior

The update must be logically atomic across:

- Scalar Settings fields.
- Phone replacement.
- Social-link replacement.
- Branding files.

Required sequence:

1. Normalize multipart input.
2. Validate full request.
3. Normalize phone numbers.
4. Validate complete phone collection.
5. Validate complete social-link collection.
6. Store new files safely.
7. Start database transaction.
8. Update scalar fields.
9. Replace phone rows when submitted.
10. Replace social rows when submitted.
11. Commit transaction.
12. Delete old files after commit.
13. Delete newly stored files if the transaction fails.

A failed request must not leave:

- Partial phone replacement.
- Partial social replacement.
- Invalid required fields.
- Database paths to failed uploads.
- New orphan files.

---

## 23. Seeder

The Seeder creates:

```text
settings.id = 1
```

Placeholder values:

```text
site_name_ar = اسم الموقع
site_name_en = Website Name
public_email = info@example.com
```

Rules:

- Idempotent.
- Safe to run repeatedly.
- Does not create duplicate Settings records.
- Integrates with `DatabaseSeeder`.
- Optional fields may start as `null`.
- Existing Admin-edited values are preserved according to project Seeder
  conventions.

---

## 24. Error Codes

Feature-specific codes may include:

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

- Validation errors: `422`.
- Unauthenticated Admin: `401`.
- Missing permission: `403`.
- Unexpected file/database failure: safe project-standard server error.
- Never expose internal exception messages or paths.

---

## 25. Required Tests

### Database

- Singleton record.
- Required and nullable columns.
- Phone and social foreign keys.
- Coordinate precision.
- Keyword JSON casts.
- Unique phone/platform behavior where enforced.

### Permissions

- Admin GET authentication and permission.
- Admin PATCH authentication and permission.
- Public GET without authentication.
- Super-admin permission seeding.

### Admin Read

- Both languages returned.
- All fields returned.
- Absolute file URLs.
- No storage paths.
- No internal IDs.
- Available platforms returned.

### Public Read

- Arabic projection.
- English projection.
- `Content-Language`.
- `Vary: Accept-Language`.
- Stable machine keys.
- No dual-language leakage.
- No internal fields.

### Phone Update

- Maximum three phones.
- Fourth rejected.
- Egyptian normalization.
- Duplicate normalized phone rejected.
- Zero or one WhatsApp allowed.
- Two WhatsApp numbers rejected.
- Full replacement.
- `clearPhones`.
- Conflict between array and clear flag.
- Order preserved.

### Social Update

- Supported platform accepted.
- Unsupported platform rejected.
- Duplicate platform rejected.
- Invalid URL rejected.
- Full replacement.
- `clearSocialLinks`.
- Conflict between array and clear flag.
- Order preserved.

### File Update

- Valid upload for each file.
- Type and size limits.
- Safe SVG accepted.
- Unsafe SVG rejected.
- Replacement cleanup.
- Database-failure compensation.
- Removal flags.
- File/removal conflict.
- No storage-path exposure.

### Atomicity

- Invalid phone blocks all changes.
- Invalid social link blocks all changes.
- File failure blocks relational partial updates.
- Failed transaction removes new files.

---

## 26. Acceptance Criteria

The feature is complete when:

1. Exactly one Settings record is used.
2. Admin can read all Settings.
3. Admin can partially update Settings using multipart PATCH.
4. Public can read localized Settings.
5. Required fields remain valid.
6. Maximum three Egyptian phones are enforced.
7. Maximum one WhatsApp-enabled phone is enforced.
8. Phone and social deletion work through replacement and clear flags.
9. Social platforms come from a predefined enum Select.
10. Duplicate phones and platforms are rejected.
11. Logo, footer logo, and favicon can be uploaded, replaced, and removed.
12. Safe SVG files are accepted unchanged and unsafe SVG files are rejected.
13. Filesystem compensation prevents failed-upload leftovers.
14. Public responses expose no internal paths or IDs.
15. Admin returns both languages.
16. Public returns one language.
17. Permissions are seeded and enforced.
18. No cache, audit history, OG image, analytics, SMTP, order configuration, or
    branch management is added.
19. Pest passes on the dedicated MySQL test database.
20. Pint passes.
21. PHPStan/Larastan passes at the configured project level.

---

## 27. Final Approved Scope

```text
Singleton Settings record
Admin GET settings
Admin PATCH settings
Public GET settings
Arabic and English identity fields
Logo
Footer logo
Favicon
One public email
Maximum three Egyptian phone numbers
Maximum one WhatsApp-enabled number
One localized address
Google Maps URL
Latitude and longitude
Dynamic predefined social platforms
Localized default SEO title
Localized default SEO description
Localized SEO keyword arrays
Full replacement for phone and social collections
Explicit clear flags
Safe SVG handling
No internal storage-path exposure
No cache
No audit history
No OG image
No analytics
No SMTP
No order settings
No branches
```
