# Feature 008 — Hero Slider Management

## 1. Overview

This feature manages the Hero Slider displayed on the public homepage.

The slider contains multiple ordered slides. Administrators can create, view,
update, delete, activate, deactivate, and position slides. Public consumers
receive only active slides in the approved display order.

The backend manages slider content only. Slider movement and visual behavior
remain a Frontend responsibility.

---

## 2. Module Name

```text
008-hero-slider-management
```

Canonical reference:

```text
docs/features/008-hero-slider-management.md
```

---

## 3. Goals

The feature must:

1. Support multiple homepage Hero slides.
2. Allow a maximum of 10 total slides, including inactive slides.
3. Support Arabic and English title and description for every slide.
4. Require one image for every persisted slide.
5. Support independent active/inactive state per slide.
6. Maintain deterministic contiguous ordering starting from position `1`.
7. Allow position changes through Create and Update only.
8. Provide complete Admin CRUD operations.
9. Return only active slides publicly.
10. Keep slider autoplay, looping, arrows, dots, timing, and animation inside
    the Frontend.

---

## 4. Out of Scope

The following are outside this feature:

- More than 10 total slides.
- Buttons or calls to action.
- Primary or secondary button fields.
- Dedicated reorder endpoint.
- Slider autoplay settings.
- Autoplay delay.
- Loop settings.
- Arrow settings.
- Dot settings.
- Transition type.
- Transition duration.
- Mobile-specific image.
- Video slides.
- Scheduled publishing.
- Start/end visibility dates.
- Audience targeting.
- A/B testing.
- Analytics or click tracking.
- Custom CSS.
- Theme colors.
- Overlay settings.
- Soft delete.
- Public editing.
- Cache or Redis.
- Queue workers.

---

## 5. Actors

### Administrator

An authenticated active administrator may manage slides according to permissions.

### Public Visitor

An unauthenticated visitor may retrieve all active slides in display order.

---

## 6. Permissions

The feature introduces exactly four permissions:

```text
hero-slides.view
hero-slides.create
hero-slides.update
hero-slides.delete
```

Rules:

- Permissions are added through the existing idempotent permission Seeder.
- `super-admin` receives all permissions through the normal permission flow.
- No hidden super-admin bypass is introduced.
- No `hero-slides.reorder` permission exists.

Required Admin middleware order:

```text
auth:sanctum
EnsureUserIsAdministrator
EnsureAdminIsActive
permission
endpoint
```

---

## 7. API Endpoints

### Admin

```http
GET    /api/v1/admin/hero-slides
POST   /api/v1/admin/hero-slides
GET    /api/v1/admin/hero-slides/{heroSlide}
PATCH  /api/v1/admin/hero-slides/{heroSlide}
DELETE /api/v1/admin/hero-slides/{heroSlide}
```

### Public

```http
GET /api/v1/public/hero-slides
```

There is no dedicated reorder endpoint.

---

## 8. Slide Fields

Each Hero slide contains:

```text
id
titleAr
titleEn
descriptionAr
descriptionEn
image
isActive
position
```

No button fields are allowed.

---

## 9. Required Fields

### Create

Required:

```text
titleAr
titleEn
descriptionAr
descriptionEn
image
isActive
```

Optional:

```text
position
```

### Update

All fields are optional in the PATCH request.

Persistently required:

```text
titleAr
titleEn
descriptionAr
descriptionEn
image
isActive
position
```

Rules:

- Omitted update fields remain unchanged.
- Required text fields cannot be cleared.
- The image may be omitted during update to preserve the existing image.
- A new image replaces the current image.
- No image-removal flag exists.
- A slide cannot exist without an image.

---

## 10. Text Validation

```text
titleAr: string, min 1, max 150
titleEn: string, min 1, max 150
descriptionAr: string, min 1, max 1000
descriptionEn: string, min 1, max 1000
```

Rules:

- All submitted text values are trimmed before validation and persistence.
- Empty strings are invalid.
- `null` is invalid.
- Arabic and English values are both required on Create.
- Updating one localized field does not require re-submitting the other field,
  but the persisted slide must remain complete.

---

## 11. Image Rules

Each slide has exactly one required persisted image.

API field:

```text
image
```

Database field:

```text
image_path
```

Allowed formats:

```text
jpg
jpeg
png
webp
```

Maximum size:

```text
5 MB
```

SVG is not accepted.

Rules:

- Create requires an image.
- Update may omit the image to preserve the current file.
- Update may submit a new image to replace the current file.
- The image cannot be removed independently.
- Deleting the slide permanently deletes its image after commit.
- Validate extension, MIME type, and actual content.
- Generate safe filenames.
- Never trust client filenames.
- Prevent path traversal.
- Store on the configured public disk.
- Return an absolute public URL.
- Never expose raw storage paths.

### File Compensation

For replacement:

1. Store the new image.
2. Execute the database transaction.
3. Commit the new path.
4. Delete the old image after commit.
5. If the transaction fails, delete the newly stored image.

For deletion:

1. Lock and delete the database row inside the transaction.
2. Recompact positions.
3. Commit.
4. Delete the old image after commit.
5. If post-commit deletion fails, log the cleanup failure without corrupting the
   valid database state.

---

## 12. Active State

Field:

```text
isActive
```

Allowed values:

```text
0 = inactive
1 = active
```

Rules:

- Each slide has its own active state.
- Admin endpoints return active and inactive slides.
- Public endpoint returns active slides only.
- Inactive slides still count toward the maximum of 10 total slides.

---

## 13. Maximum Slide Count

The application allows at most:

```text
10 total hero slide records
```

This count includes:

```text
active slides
inactive slides
```

When 10 records already exist:

```http
POST /api/v1/admin/hero-slides
```

returns:

```text
422 VALIDATION_ERROR
```

Deleting a slide frees one slot.

The limit must be checked inside the transaction under a lock to prevent
concurrent requests from creating more than 10 records.

---

## 14. Position Rules

Field:

```text
position
```

Positions:

- Start from `1`.
- Are always positive integers.
- Are always contiguous.
- Have no duplicates.
- Have no gaps.

Valid persisted sequence examples:

```text
1
1, 2
1, 2, 3, 4
```

Invalid persisted sequence examples:

```text
0, 1
1, 3
1, 2, 2
```

---

## 15. Create Position Behavior

`position` is optional on Create.

### When omitted

The new slide is appended at the end:

```text
current: 1, 2, 3
new slide without position
result: 1, 2, 3, 4
```

### When submitted

The submitted position must be between:

```text
1
and
current slide count + 1
```

The backend inserts the slide at that position and shifts existing slides
forward automatically.

Example:

```text
current: 1, 2, 3
create at position 2
result:
old 1 → 1
new   → 2
old 2 → 3
old 3 → 4
```

A position above `current count + 1` returns:

```text
422 VALIDATION_ERROR
```

---

## 16. Update Position Behavior

`position` is optional on Update.

### When omitted

The slide keeps its current position.

### When submitted

The backend moves the slide and automatically shifts affected slides.

Move upward example:

```text
current slide position: 5
requested position: 2
```

Result:

- Target slide becomes `2`.
- Existing positions `2..4` shift forward by one.
- The old position gap is closed.
- The final sequence remains contiguous.

Move downward example:

```text
current slide position: 2
requested position: 5
```

Result:

- Existing positions `3..5` shift backward by one.
- Target slide becomes `5`.
- The final sequence remains contiguous.

The submitted update position must be between:

```text
1
and
current total slide count
```

Submitting the current position is accepted as a no-op.

---

## 17. Delete Position Behavior

Delete is permanent.

When a slide is deleted:

- Its row is removed.
- Its image is deleted after database commit.
- All following positions shift backward automatically.
- The final sequence remains contiguous.

Example:

```text
before: 1, 2, 3, 4
delete position 2
after:  1, 2, 3
```

No soft delete is used.

---

## 18. Concurrency and Ordering Safety

Create, Update, and Delete must run inside database transactions.

The implementation must lock the ordered Hero slide set before:

- Checking the maximum count.
- Calculating the append position.
- Shifting positions.
- Moving a slide.
- Closing gaps after deletion.

The application must prevent:

- More than 10 records.
- Duplicate positions.
- Missing position numbers.
- Lost moves under concurrent requests.
- Partial image/database state.

A safe implementation may lock all current Hero slide rows in position order
before applying ordering changes.

---

## 19. Admin Index

Endpoint:

```http
GET /api/v1/admin/hero-slides
```

### Sorting

Default and only approved ordering:

```text
position ascending
```

### Pagination

The Admin index is paginated.

Defaults:

```text
default perPage = 15
maximum perPage = 100
```

Approved query parameters:

```text
page
perPage
filter[isActive]
```

### Active Filter

Examples:

```http
GET /api/v1/admin/hero-slides?filter[isActive]=1
GET /api/v1/admin/hero-slides?filter[isActive]=0
```

Rules:

- Only `0` and `1` are accepted.
- Omitted filter returns all slides.
- No search filter is required.
- No custom sort parameter is required.

Although the current maximum total is 10, pagination remains part of the
contract for future compatibility.

---

## 20. Admin Index Response

Example:

```json
{
  "data": [
    {
      "id": 1,
      "titleAr": "حلول رقمية متكاملة",
      "titleEn": "Complete Digital Solutions",
      "descriptionAr": "نقدم خدمات تناسب احتياجات مشروعك",
      "descriptionEn": "We provide services tailored to your business",
      "image": "https://api.example.com/storage/hero-slides/example.webp",
      "isActive": 1,
      "position": 1
    }
  ],
  "meta": {
    "currentPage": 1,
    "lastPage": 1,
    "perPage": 15,
    "total": 1
  }
}
```

Rules:

- Admin responses return both languages.
- `image` is always an absolute URL because the persisted image is required.
- IDs are returned for Admin CRUD.
- Raw storage paths are never returned.
- Timestamps are not required in the approved resource.

---

## 21. Admin Show Response

Endpoint:

```http
GET /api/v1/admin/hero-slides/{heroSlide}
```

Example `data`:

```json
{
  "id": 1,
  "titleAr": "حلول رقمية متكاملة",
  "titleEn": "Complete Digital Solutions",
  "descriptionAr": "نقدم خدمات تناسب احتياجات مشروعك",
  "descriptionEn": "We provide services tailored to your business",
  "image": "https://api.example.com/storage/hero-slides/example.webp",
  "isActive": 1,
  "position": 1
}
```

---

## 22. Create Request

Endpoint:

```http
POST /api/v1/admin/hero-slides
Content-Type: multipart/form-data
```

Example:

```text
titleAr=حلول رقمية متكاملة
titleEn=Complete Digital Solutions
descriptionAr=نقدم خدمات تناسب احتياجات مشروعك
descriptionEn=We provide services tailored to your business
image=<binary>
isActive=1
position=1
```

`position` may be omitted to append the slide.

Successful Create returns:

```text
201
```

with the complete Admin slide resource.

---

## 23. Update Request

Endpoint:

```http
PATCH /api/v1/admin/hero-slides/{heroSlide}
Content-Type: multipart/form-data
```

Example:

```text
titleAr=حلول رقمية متكاملة ومبتكرة
isActive=1
position=2
image=<binary>
```

Rules:

- Omitted fields remain unchanged.
- Image omission preserves the existing image.
- New image replaces the existing image.
- Position omission preserves the current position.
- Successful Update returns the complete Admin slide resource.

---

## 24. Delete Response

Endpoint:

```http
DELETE /api/v1/admin/hero-slides/{heroSlide}
```

Successful response:

```json
{
  "data": null
}
```

inside the approved shared success envelope.

Status:

```text
200
```

---

## 25. Public Index

Endpoint:

```http
GET /api/v1/public/hero-slides
```

Rules:

- No authentication.
- Returns only active slides.
- Returns all active slides without pagination.
- Orders by `position` ascending.
- Uses localized neutral keys.
- Does not expose Admin-only fields.
- Does not expose `isActive`.
- Does not expose positions unless the frontend contract explicitly requires
  them; ordering is represented by array order.
- Does not expose IDs.
- Does not expose raw storage paths.

When there are no active slides:

```json
{
  "data": []
}
```

inside the approved shared success envelope.

---

## 26. Public Response Contract

Arabic example:

```json
{
  "data": [
    {
      "title": "حلول رقمية متكاملة",
      "description": "نقدم خدمات تناسب احتياجات مشروعك",
      "image": "https://api.example.com/storage/hero-slides/example.webp"
    }
  ]
}
```

English uses the same keys with English values.

The array order is the display order.

---

## 27. Localization

The Public endpoint resolves language through the existing locale middleware.

Supported resolved locales:

```text
ar
en
```

Examples:

```text
ar-EG → ar
en-US → en
```

Required response headers:

```http
Content-Language: ar|en
Vary: Accept-Language
```

Admin endpoints return both languages. `Accept-Language` affects Admin messages
and validation errors only.

---

## 28. Database Design

### Table

```text
hero_slides
```

### Columns

```text
id
title_ar
title_en
description_ar
description_en
image_path
is_active
position
created_at
updated_at
```

### Suggested Types

```text
id: unsigned big integer
title_ar: varchar(150)
title_en: varchar(150)
description_ar: text
description_en: text
image_path: varchar
is_active: unsigned tiny integer
position: unsigned tiny integer
created_at: timestamp
updated_at: timestamp
```

### Constraints and Indexes

- Primary key on `id`.
- Unique index on `position`.
- Index on `(is_active, position)`.
- Application transaction enforces maximum 10 total rows.
- Position sequence is maintained by the ordered mutation workflow.

No additional Hero tables are required.

---

## 29. Suggested Structure

```text
app/
├── Actions/HeroSlides/
│   ├── CreateHeroSlideAction.php
│   ├── UpdateHeroSlideAction.php
│   └── DeleteHeroSlideAction.php
├── Http/
│   ├── Controllers/Api/V1/Admin/HeroSlides/
│   │   └── HeroSlideController.php
│   ├── Controllers/Api/V1/Public/HeroSlides/
│   │   └── HeroSlideController.php
│   ├── Requests/Api/V1/Admin/HeroSlides/
│   │   ├── IndexHeroSlidesRequest.php
│   │   ├── StoreHeroSlideRequest.php
│   │   └── UpdateHeroSlideRequest.php
│   └── Resources/Api/V1/
│       ├── Admin/HeroSlides/
│       │   └── AdminHeroSlideResource.php
│       └── Public/HeroSlides/
│           └── PublicHeroSlideResource.php
├── Models/
│   └── HeroSlide.php
└── Services/HeroSlides/
    ├── HeroSlideOrderingService.php
    └── HeroSlideImageService.php
```

This structure is planning guidance and must not introduce unrelated
abstractions.

---

## 30. Error Contract

Applicable stable error codes may include:

```text
VALIDATION_ERROR
HERO_SLIDE_NOT_FOUND
HERO_SLIDE_LIMIT_REACHED
HERO_SLIDE_IMAGE_UPLOAD_FAILED
```

Status behavior:

- Unauthenticated Admin request: `401`.
- Missing permission: `403`.
- Missing slide: `404`.
- Invalid input: `422`.
- More than 10 total slides: `422`.
- Unexpected database/filesystem failure: project-standard safe server error.
- Internal exception, SQL, filesystem, or path details must not be exposed.

Use the existing:

```text
App\Enums\HttpStatusCode
```

Do not create another HTTP status enum.

---

## 31. Required Tests

### Authorization

- Every Admin endpoint rejects unauthenticated requests with `401`.
- Missing view permission returns `403`.
- Missing create permission returns `403`.
- Missing update permission returns `403`.
- Missing delete permission returns `403`.
- Super-admin receives all four permissions through seeding.

### Create

- Valid slide creates successfully.
- Arabic and English title are required.
- Arabic and English description are required.
- Image is required.
- `isActive` accepts only `0` or `1`.
- Position may be omitted.
- Omitted position appends the slide.
- Submitted position inserts and shifts later slides.
- Position starts from `1`.
- Position above `count + 1` is rejected.
- More than 10 total records is rejected.
- Inactive slides count toward the limit.
- Valid image formats succeed.
- SVG is rejected.
- Image above 5 MB is rejected.
- Invalid MIME/content is rejected.

### Admin Index

- Returns paginated result.
- Default `perPage` is `15`.
- Maximum `perPage` is `100`.
- Results are ordered by position ascending.
- `filter[isActive]=1` returns active slides.
- `filter[isActive]=0` returns inactive slides.
- Invalid active filter is rejected.
- Both languages are returned.
- Image URL is returned.
- Raw path is not returned.

### Show

- Existing slide is returned.
- Missing slide returns `404`.
- Both languages are returned.
- Raw path is not returned.

### Update

- Omitted fields remain unchanged.
- Required text cannot be cleared.
- Omitted image preserves the current image.
- New image replaces the current image.
- No image-removal operation exists.
- Position omission preserves position.
- Moving upward shifts affected slides.
- Moving downward shifts affected slides.
- Current position is accepted as a no-op.
- Invalid position is rejected.
- Old image is deleted only after commit.
- Failed database update removes newly stored image.

### Delete

- Delete is permanent.
- Successful delete returns `200` with `data: null`.
- Deleted image is removed after commit.
- Remaining positions close automatically.
- No gap remains.
- Missing slide returns `404`.

### Limit and Concurrency

- The 10-slide limit is preserved under concurrent Create requests.
- Duplicate positions cannot be created concurrently.
- Concurrent moves serialize safely.
- Concurrent Create and Delete preserve contiguous positions.
- Failed mutations leave previous state unchanged.

### Public

- Returns only active slides.
- Returns slides ordered by position.
- Returns localized Arabic projection.
- Returns localized English projection.
- Locale variants resolve correctly.
- Required localization headers are present.
- Returns `data: []` when no active slides exist.
- Does not paginate.
- Does not return IDs.
- Does not return `isActive`.
- Does not return dual-language keys.
- Does not return raw storage paths.

### Quality Gates

- Pest passes against MySQL.
- Pint passes.
- PHPStan/Larastan passes at the configured project level.

---

## 32. Acceptance Criteria

The feature is complete when:

1. Admin CRUD exists for Hero slides.
2. Public active-slide index exists.
3. Maximum 10 total slides is enforced.
4. Inactive slides count toward the limit.
5. Every slide has Arabic/English title and description.
6. Every slide has one required persisted image.
7. No buttons exist.
8. Every slide has independent active state.
9. Positions begin at `1`.
10. Positions remain unique and contiguous.
11. Create and Update accept optional position.
12. Omitted Create position appends.
13. Omitted Update position preserves.
14. Create, Update, and Delete automatically shift positions.
15. No reorder endpoint or reorder permission exists.
16. Admin index uses pagination.
17. Admin index supports `filter[isActive]`.
18. Public index returns only active slides.
19. Public empty state returns `data: []`.
20. Frontend owns slider behavior and animation.
21. Delete is permanent and filesystem-safe.
22. No raw storage path leaks.
23. Transactions and locks protect ordering and the 10-slide limit.
24. Pest, Pint, and PHPStan/Larastan checks pass.

---

## 33. Final Approved Scope

```text
module:
008-hero-slider-management

maximum:
10 total slides including inactive

admin:
GET    /api/v1/admin/hero-slides
POST   /api/v1/admin/hero-slides
GET    /api/v1/admin/hero-slides/{heroSlide}
PATCH  /api/v1/admin/hero-slides/{heroSlide}
DELETE /api/v1/admin/hero-slides/{heroSlide}

public:
GET /api/v1/public/hero-slides

permissions:
hero-slides.view
hero-slides.create
hero-slides.update
hero-slides.delete

slide fields:
titleAr
titleEn
descriptionAr
descriptionEn
image
isActive
position

required:
titleAr
titleEn
descriptionAr
descriptionEn
image
isActive

position:
optional on Create
optional on Update
starts from 1
omitted Create position appends
omitted Update position preserves
automatic shifting on Create/Update/Delete
no reorder endpoint

admin index:
pagination
default perPage 15
maximum perPage 100
filter[isActive]

image:
required persistently
jpg/jpeg/png/webp
maximum 5 MB
no SVG
replace on Update
no remove-image operation

public:
active only
position ascending
localized
no pagination
empty data array

no:
buttons
slider settings
reorder endpoint
soft delete
video
scheduling
cache
analytics
```
