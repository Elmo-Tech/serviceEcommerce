# Quickstart: Settings Management Validation

**Feature:** `006-settings-management`  
**Purpose:** Validate the feature end-to-end after implementation

## 1. Prerequisites

- Application configured with MySQL and storage settings
- Migrations run successfully
- Seeders run successfully, including permissions and singleton Settings setup
- Admin authentication feature working
- Public storage URL generation configured correctly

## 2. Prepare environment

Run from repository root:

```bash
php artisan migrate:fresh --seed
php artisan test --filter=Settings
```

If targeted tests are split by directory, run the relevant Pest files covering:

- Admin Settings read
- Admin Settings update
- Public Settings read
- Settings database persistence and atomicity

## 3. Validate Admin read

### Scenario A — Successful Admin read

Call:

```http
GET /api/v1/admin/settings
```

Validate:

- authenticated admin with `settings.view` receives `200`
- response uses shared success envelope
- both Arabic and English fields are returned
- file fields are URLs or `null`
- no internal IDs, timestamps, or raw storage paths are exposed
- `availableSocialPlatforms` matches the approved machine-key list

### Scenario B — Protected access failures

Validate:

- unauthenticated request returns `401 UNAUTHENTICATED`
- authenticated request without `settings.view` returns `403 FORBIDDEN`

## 4. Validate Admin update

### Scenario C — Scalar partial update

Send multipart PATCH updating only:

```text
siteNameAr
siteNameEn
publicEmail
```

Validate:

- omitted values remain unchanged
- required fields cannot be set empty or `null`
- success returns the full updated Admin resource

### Scenario D — Optional scalar clearing

Submit one optional scalar text field as an explicit empty string, for example:

```text
sloganAr=
```

Validate:

- the submitted field is persisted as `null`
- an omitted optional scalar remains unchanged
- no new scalar clear flag is accepted or required
- required fields still reject empty string and `null`

### Scenario E — SEO keyword validation

Submit valid keyword arrays and then duplicate values such as:

```text
```

Validate:

- each value is trimmed and must remain non-empty
- duplicates after trim and case-insensitive comparison return
  `422 VALIDATION_ERROR`
- original casing and order are preserved when the array is valid

### Scenario F — Phone replacement

Submit:

```text
phones[0][number]=01012345678
phones[0][hasWhats]=1
phones[1][number]=01112345678
phones[1][hasWhats]=0
```

Validate:

- phones are normalized and stored in canonical local format
- order matches submission order
- duplicate normalized numbers are rejected
- more than three phones is rejected
- two WhatsApp-enabled rows are rejected

### Scenario G — Phone clear

Submit:

```text
phones=[]
```

Validate:

- all phones are removed
- omitting `phones` preserves the current collection

### Scenario H — Social replacement and clear

Submit valid `socialLinks` and then `socialLinks=[]`.

Validate:

- platforms must come from the approved enum
- duplicate platforms are rejected
- invalid URL is rejected
- omitting `socialLinks` preserves the current collection

### Scenario I — Coordinates and location

Validate:

- out-of-range coordinates are rejected

### Scenario J — Branding files

Validate:

- logo and footer logo accept only `jpg`, `jpeg`, `png`, `webp`, or `svg` up to 5 MB
- favicon accepts only `png`, `ico`, or `svg` up to 1 MB
- invalid extension, MIME/content, or size returns validation failure
- file/removal flag conflict returns `422 VALIDATION_ERROR`
- successful replacement keeps the new file and removes the old file after commit
- removal flag clears the file field and removes the old file after commit
- API never accepts existing storage paths from the client

### Scenario K — SVG safety

Validate:

- safe SVG is accepted for approved branding fields without rewriting its content
- unsafe SVG containing `script`, `javascript:`, event handlers, `foreignObject`, embedded HTML, or external resources is rejected
- unsafe SVG is never silently sanitized, rewritten, or stored in modified form

### Scenario L — Atomicity

Validate:

- invalid phone input blocks scalar and file changes
- invalid social-link input blocks scalar and file changes
- simulated database failure removes newly stored files
- no partial phone/social replacement remains after failure
- concurrent PATCH requests serialize through `settings.id = 1` `lockForUpdate`
- concurrent missing-singleton Admin recovery does not create duplicates or leak an unhandled duplicate-key error

## 5. Validate Public read

### Scenario M — Arabic projection

Call:

```http
GET /api/v1/public/settings
Accept-Language: ar
```

Validate:

- Arabic values are returned under neutral machine keys
- `Content-Language: ar`
- `Vary: Accept-Language`
- no dual-language leakage
- no internal IDs or raw storage paths

### Scenario N — English projection

Call:

```http
GET /api/v1/public/settings
Accept-Language: en
```

Validate:

- English values are returned under the same keys
- `Content-Language: en`
- locale variants such as `ar-EG` and `en-US` are accepted and resolved to `ar` or `en`

## 6. Validate singleton behavior

### Scenario O — Seeder idempotency

Run seeders repeatedly and validate:

- `settings.id = 1` remains the only Settings row
- existing admin-managed values are not overwritten unexpectedly

### Scenario P — Missing singleton handling

Validate:

- Admin read/update can safely restore the singleton with placeholder-safe values
- Public read returns the approved safe default representation without creating a row

## 7. Completion checklist

- All approved routes exist and use correct middleware
- `settings.view` and `settings.update` are seeded and enforced
- Shared success/error envelope is preserved
- No raw storage paths or internal IDs leak in API responses
- Singleton behavior remains deterministic
- Pint passes
- Larastan/PHPStan passes
- Relevant Pest suites pass
- OpenAPI declares version `3.1.0`
- OpenAPI YAML parses successfully
- all local `$ref` values resolve
- exactly three operations exist
- all `operationId` values are unique
- no `nullable` keyword remains
- Public GET declares `security: []`
- exact request/response objects reject undocumented fields
- all approved Admin and Public response keys are required and nullable values use JSON Schema null unions
- Admin update phones reference `PhoneInput`, while Admin/Public response phones reference `PhoneOutput`
- approved formatted Egyptian phone inputs are accepted by the contract and canonical output remains normalized
- Admin and Public projections match the approved contract

## 8. Postman collection

Import `postman/Service-Commerce.postman_collection.json` and use only the
approved collection variables: `baseUrl`, `accessToken`, and `refreshToken`.

Feature 006 requests are grouped under `Admin Settings` and `Public Settings`.
The Admin update request uses `multipart/form-data`; its examples use canonical
camelCase keys, ordered bracket notation for phone/social arrays, integer
`0`/`1` flags, and binary file fields. Optional examples are disabled so each
one can be enabled without unintentionally clearing or replacing unrelated
settings.

## 9. Verification record — 2026-08-02

- Scenarios A–P were exercised against the dedicated MySQL test database by
  the mapped API, database, file, architecture, and real-process concurrency
  suites in `requirements-to-tasks.md`.
- File scenarios use Laravel's isolated public-disk fake; no production files
  are read, replaced, or removed during verification.
- Concurrency scenarios run separate PHP processes against MySQL and confirmed
  one canonical singleton plus complete serialized child replacements.
- Route inspection confirmed exactly two protected Admin operations and one
  unauthenticated Public operation with the approved permission middleware.
- The Postman collection parsed successfully and retained only `baseUrl`,
  `accessToken`, and `refreshToken` as collection variables.
- No environment-specific behavior exception was required.
