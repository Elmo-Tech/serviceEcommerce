# Feature 009 — FAQ Management

## 1. Overview

This feature manages Frequently Asked Questions displayed on the public website.

Administrators can create, view, update, delete, activate, deactivate, and order
FAQ items. Public consumers receive only active FAQ items in the approved
display order and in the requested language.

The feature follows the existing Laravel 13 API architecture, shared response
envelope, Sanctum authentication, Spatie permissions, localization middleware,
and MySQL persistence conventions.

---

## 2. Module Name

```text
009-faq-management
```

Canonical reference:

```text
docs/features/009-faq-management.md
```

Status:

```text
Final Approved Scope
```

---

## 3. Goals

The feature must:

1. Allow Admin users to manage FAQ items.
2. Support Arabic and English question text.
3. Support Arabic and English answer text.
4. Support independent active/inactive state per FAQ.
5. Support deterministic ordered display.
6. Allow position changes through Create and Update.
7. Return only active FAQs publicly.
8. Return one localized public projection.
9. Keep Admin CRUD protected by explicit permissions.
10. Avoid categories, nested FAQs, voting, analytics, and unrelated CMS scope.

---

## 4. Out of Scope

The following are outside the initial feature:

- FAQ categories.
- FAQ groups.
- Nested questions.
- Customer-submitted questions.
- Voting or helpful/not-helpful reactions.
- View counters.
- Analytics.
- Scheduled publishing.
- Start/end visibility dates.
- Attachments.
- Images.
- Videos.
- Tags.
- Search engine indexing controls.
- Rich page-builder blocks.
- Reorder-only endpoint.
- Soft delete.
- Public editing.
- Cache or Redis.
- Queue workers.
- Audit history.

Any item above requires explicit approval before implementation.

---

## 5. Actors

### Administrator

An authenticated active administrator may manage FAQ items according to the
assigned permissions.

### Public Visitor

An unauthenticated visitor may retrieve active localized FAQs.

---

## 6. Permissions

The feature introduces exactly four permissions:

```text
faqs.view
faqs.create
faqs.update
faqs.delete
```

Rules:

- Permissions are added through the existing idempotent permission Seeder.
- `super-admin` receives all four permissions through the normal permission
  assignment flow.
- No hidden super-admin bypass is introduced.
- No `faqs.reorder` permission exists.

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
GET    /api/v1/admin/faqs
POST   /api/v1/admin/faqs
GET    /api/v1/admin/faqs/{faq}
PATCH  /api/v1/admin/faqs/{faq}
DELETE /api/v1/admin/faqs/{faq}
```

### Public

```http
GET /api/v1/public/faqs
```

There is no dedicated reorder endpoint.

---

## 8. FAQ Fields

Each FAQ contains:

```text
id
questionAr
questionEn
answerAr
answerEn
isActive
position
```

No category, slug, image, or attachment fields are included.

---

## 9. Required Fields

### Create

Required:

```text
questionAr
questionEn
answerAr
answerEn
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
questionAr
questionEn
answerAr
answerEn
isActive
position
```

Rules:

- Omitted update fields remain unchanged.
- Required fields cannot be cleared.
- Arabic and English content are both required when creating an FAQ.
- Updating one localized value does not require re-submitting the other values,
  but the persisted FAQ must remain complete.

---

## 10. Text Validation

Recommended initial limits:

```text
questionAr: string, min 1, max 500
questionEn: string, min 1, max 500
answerAr: string, min 1, max 5000
answerEn: string, min 1, max 5000
```

Rules:

- Submitted values are trimmed before validation and persistence.
- Empty strings are invalid.
- `null` is invalid.
- Control characters that are not normal whitespace are rejected.
- Questions and answers are stored and returned as plain text.
- Line breaks are preserved.
- HTML is not interpreted or rendered by the backend.
- Questions and answers must not contain executable scripts.

---

## 10.1 Duplicate Question Rules

Duplicate questions are rejected per locale after normalization.

Normalization for duplicate comparison:

```text
trim
collapse comparison to case-insensitive form
```

Rules:

- `questionAr` must not duplicate another persisted Arabic question after trim
  and case-insensitive comparison.
- `questionEn` must not duplicate another persisted English question after trim
  and case-insensitive comparison.
- On Update, the current FAQ row is excluded from the duplicate check.
- If either submitted localized question duplicates another FAQ in the same
  locale, the request returns `422 VALIDATION_ERROR`.
- Original submitted casing and text are preserved when valid.
- Database/application handling must make duplicate prevention safe under
  concurrent Create and Update requests.


## 11. Active State

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

- Each FAQ has its own active state.
- Admin endpoints may return active and inactive records.
- Public endpoint returns active records only.

---

## 11.1 FAQ Count

There is no hard application maximum for the total number of FAQ records.

Pagination protects both Admin and Public list responses. Database capacity and
normal operational limits remain the only practical boundaries.


## 12. Position Rules

Field:

```text
position
```

Positions:

- Start from `1`.
- Are positive integers.
- Are unique.
- Remain contiguous.
- Have no gaps after successful writes.

Valid sequences:

```text
1
1, 2
1, 2, 3, 4
```

Invalid persisted sequences:

```text
0, 1
1, 3
1, 2, 2
```

---

## 13. Create Position Behavior

`position` is optional on Create.

### When omitted

The FAQ is appended at the end.

Example:

```text
current: 1, 2, 3
new FAQ without position
result: 1, 2, 3, 4
```

### When submitted

The submitted position must be between:

```text
1
and
current FAQ count + 1
```

The backend inserts the new FAQ at that position and shifts existing FAQ items
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

An invalid position returns:

```text
422 VALIDATION_ERROR
```

---

## 14. Update Position Behavior

`position` is optional on Update.

### When omitted

The FAQ keeps its current position.

### When submitted

The backend moves the FAQ and shifts affected records automatically.

Move upward:

```text
current position: 5
requested position: 2
```

Result:

- The target becomes position `2`.
- Existing positions `2..4` shift forward.
- The previous position gap is closed.

Move downward:

```text
current position: 2
requested position: 5
```

Result:

- Existing positions `3..5` shift backward.
- The target becomes position `5`.

Submitting the current position is accepted as a no-op.

---

## 15. Delete Behavior

Delete is permanently destructive.

When an FAQ is deleted:

- Its database row is removed.
- All later positions shift backward.
- The final sequence remains contiguous.
- No soft-delete record is retained.

Example:

```text
before: 1, 2, 3, 4
delete position 2
after:  1, 2, 3
```

Successful Delete returns status `200` with `data: null` inside the shared
success envelope.

---

## 16. Concurrency and Ordering Safety

Create, Update, and Delete must execute inside database transactions.

The implementation must lock the ordered FAQ set before:

- Calculating an append position.
- Inserting at a requested position.
- Moving an FAQ.
- Closing gaps after deletion.

The application must prevent:

- Duplicate positions.
- Missing position numbers.
- Lost updates under concurrent requests.
- Partial ordering state.

A safe implementation may lock all current FAQ rows in position order before
applying mutations.

---

## 17. Admin Index

Endpoint:

```http
GET /api/v1/admin/faqs
```

Default ordering:

```text
position ascending
```

### Pagination

Recommended:

```text
default perPage = 15
maximum perPage = 100
```

Approved query parameters:

```text
page
perPage
filter[isActive]
filter[search]
```

### Search Filter

Example:

```http
GET /api/v1/admin/faqs?filter[search]=service
```

Rules:

- `filter[search]` is optional.
- The value is trimmed before use.
- Empty search values are treated as absent.
- Search is case-insensitive.
- Search checks both `question_ar` and `question_en`.
- Arabic and English question text are searched in the same request.
- Answer fields are not searched.
- `filter[search]` may be combined with `filter[isActive]`.
- Results remain ordered by `position` ascending after filtering.

### Active Filter

Examples:

```http
GET /api/v1/admin/faqs?filter[isActive]=1
GET /api/v1/admin/faqs?filter[isActive]=0
```

Rules:

- Only `0` and `1` are accepted.
- Omitted filter returns active and inactive FAQ items.
- Invalid filter values return `422 VALIDATION_ERROR`.

---

## 18. Admin Index Response

Example:

```json
{
  "data": [
    {
      "id": 1,
      "questionAr": "كيف أطلب الخدمة؟",
      "questionEn": "How do I order a service?",
      "answerAr": "اختر الخدمة ثم أكمل بيانات الطلب.",
      "answerEn": "Choose the service and complete the order details.",
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

- Both languages are returned.
- 
- IDs are returned for Admin CRUD.
- Timestamps are not required in the approved resource.
- No internal-only fields are exposed.

---

## 19. Admin Show Response

Endpoint:

```http
GET /api/v1/admin/faqs/{faq}
```

Example `data`:

```json
{
  "id": 1,
  "questionAr": "كيف أطلب الخدمة؟",
  "questionEn": "How do I order a service?",
  "answerAr": "اختر الخدمة ثم أكمل بيانات الطلب.",
  "answerEn": "Choose the service and complete the order details.",
  "isActive": 1,
  "position": 1
}
```

Missing FAQ behavior:

```text
404 FAQ_NOT_FOUND
```

---

## 20. Create Request

Endpoint:

```http
POST /api/v1/admin/faqs
Content-Type: application/json
```

Example:

```json
{
  "questionAr": "كيف أطلب الخدمة؟",
  "questionEn": "How do I order a service?",
  "answerAr": "اختر الخدمة ثم أكمل بيانات الطلب.",
  "answerEn": "Choose the service and complete the order details.",
  "isActive": 1,
  "position": 1
}
```

`position` may be omitted to append.

Successful Create:

```text
201
```

The response returns the complete Admin FAQ resource.

---

## 21. Update Request

Endpoint:

```http
PATCH /api/v1/admin/faqs/{faq}
Content-Type: application/json
```

Example:

```json
{
  "questionAr": "كيف يمكنني طلب الخدمة؟",
  "isActive": 1,
  "position": 2
}
```

Rules:

- Omitted fields remain unchanged.
- Position omission preserves the current position.
- Required text fields cannot be cleared.
- Successful Update returns the complete Admin FAQ resource.

---

## 22. Delete Response

Endpoint:

```http
DELETE /api/v1/admin/faqs/{faq}
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

## 23. Public Index

Endpoint:

```http
GET /api/v1/public/faqs
```

Baseline rules:

- No authentication.
- Returns active FAQ items only.
- Orders by `position` ascending.
- Uses localized neutral keys.
- Does not expose `isActive`.
- Does not expose dual-language fields.
- Does not expose timestamps.
- ID exposure remains unnecessary and is excluded.
- Public responses are paginated.

### Public Pagination

Approved query parameters:

```text
page
perPage
```

Defaults:

```text
default perPage = 15
maximum perPage = 100
```

The Public endpoint returns pagination metadata using the shared project
pagination contract.

When there are no active FAQ items:

```json
{
  "data": [],
  "meta": {
    "currentPage": 1,
    "lastPage": 1,
    "perPage": 15,
    "total": 0
  }
}
```

inside the approved shared success envelope.

---

## 24. Public Response Contract

Arabic example:

```json
{
  "data": [
    {
      "question": "كيف أطلب الخدمة؟",
      "answer": "اختر الخدمة ثم أكمل بيانات الطلب."
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

English uses the same machine keys with English content.

The array order is the display order.

---

## 25. Localization

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

## 26. Database Design

### Table

```text
faqs
```

### Columns

```text
id
question_ar
question_en
answer_ar
answer_en
is_active
position
created_at
updated_at
```

### Suggested Types

```text
id: unsigned big integer
question_ar: varchar(500)
question_en: varchar(500)
answer_ar: text
answer_en: text
is_active: unsigned tiny integer
position: unsigned integer
created_at: timestamp
updated_at: timestamp
```

### Constraints and Indexes

- Primary key on `id`.
- Unique index on `position`.
- Index on `(is_active, position)`.
- Duplicate-question prevention is enforced after trim and case-insensitive
  comparison per locale through the approved validation/persistence strategy.
- Position sequence is maintained by the transactional ordering workflow.

No additional FAQ tables are introduced.

---

## 27. Suggested Structure

```text
app/
├── Actions/Faqs/
│   ├── CreateFaqAction.php
│   ├── UpdateFaqAction.php
│   └── DeleteFaqAction.php
├── Http/
│   ├── Controllers/Api/V1/Admin/Faqs/
│   │   └── FaqController.php
│   ├── Controllers/Api/V1/Public/Faqs/
│   │   └── FaqController.php
│   ├── Requests/Api/V1/Admin/Faqs/
│   │   ├── IndexFaqsRequest.php
│   │   ├── StoreFaqRequest.php
│   │   └── UpdateFaqRequest.php
│   └── Resources/Api/V1/
│       ├── Admin/Faqs/
│       │   └── AdminFaqResource.php
│       └── Public/Faqs/
│           └── PublicFaqResource.php
├── Models/
│   └── Faq.php
└── Services/Faqs/
    └── FaqOrderingService.php
```

This structure is planning guidance and must not introduce unrelated
abstractions.

---

## 28. Error Contract

Applicable stable error codes may include:

```text
VALIDATION_ERROR
FAQ_NOT_FOUND
```

Status behavior:

- Unauthenticated Admin request: `401`.
- Missing permission: `403`.
- Missing FAQ: `404`.
- Invalid input: `422`.
- Unexpected database failure: project-standard safe server error.
- Internal SQL or exception details must not be exposed.

Use the existing:

```text
App\Enums\StatusCode
```

Do not create another HTTP status enum.

---

## 29. Required Tests

### Authorization

- Every Admin endpoint rejects unauthenticated requests with `401`.
- Missing view permission returns `403`.
- Missing create permission returns `403`.
- Missing update permission returns `403`.
- Missing delete permission returns `403`.
- Super-admin receives all four permissions through seeding.

### Create

- Valid FAQ creates successfully.
- Arabic and English questions are required.
- Arabic and English answers are required.
- `isActive` accepts only `0` or `1`.
- Position may be omitted.
- Omitted position appends.
- Submitted position inserts and shifts later records.
- Position starts from `1`.
- Position above `count + 1` is rejected.
- Empty question or answer is rejected.
- Maximum text lengths are enforced.
- Plain-text line breaks are preserved.
- HTML is not interpreted as rich content.
- Duplicate Arabic questions are rejected after trim and case-insensitive comparison.
- Duplicate English questions are rejected after trim and case-insensitive comparison.

### Admin Index

- Returns paginated result.
- Default `perPage` is `15`.
- Maximum `perPage` is `100`.
- Results are ordered by position ascending.
- `filter[isActive]=1` returns active records.
- `filter[isActive]=0` returns inactive records.
- Invalid active filter is rejected.
- `filter[search]` searches Arabic question text.
- `filter[search]` searches English question text.
- Search is case-insensitive.
- Empty search is treated as absent.
- Search may be combined with `filter[isActive]`.
- Answer fields are not searched.
- Both languages are returned.

### Show

- Existing FAQ is returned.
- Missing FAQ returns `404`.
- Both languages are returned.

### Update

- Omitted fields remain unchanged.
- Required text cannot be cleared.
- Position omission preserves position.
- Moving upward shifts affected records.
- Moving downward shifts affected records.
- Current position is accepted as a no-op.
- Invalid position is rejected.

### Delete

- Delete is permanent.
- Successful Delete returns `200` with `data: null`.
- Remaining positions close automatically.
- No gaps remain.
- Missing FAQ returns `404`.

### Ordering and Concurrency

- Concurrent creates do not create duplicate positions.
- Concurrent moves serialize safely.
- Concurrent create/delete operations preserve contiguous positions.
- Failed mutations leave the previous valid ordering unchanged.

### Public

- Returns active FAQs only.
- Returns position order.
- Returns localized Arabic projection.
- Returns localized English projection.
- Locale variants resolve correctly.
- Required localization headers are present.
- Returns paginated results.
- Default Public `perPage` is `15`.
- Maximum Public `perPage` is `100`.
- Returns `data: []` with pagination metadata when no active FAQs exist.
- Does not expose `isActive`.
- Does not expose dual-language keys.
- Does not expose IDs or timestamps.

### Quality Gates

- Pest passes against MySQL.
- Pint passes.
- PHPStan/Larastan passes at the configured project level.

---

## 30. Acceptance Criteria

The feature is complete when:

1. Admin CRUD exists for FAQ items.
2. Public active FAQ index exists.
3. Every FAQ has Arabic and English question and answer.
4. Every FAQ has independent active state.
5. Positions start from `1`.
6. Positions remain unique and contiguous.
7. Create and Update accept optional position.
8. Omitted Create position appends.
9. Omitted Update position preserves.
10. Create, Update, and Delete automatically shift positions.
11. No reorder endpoint or reorder permission exists.
12. Admin index uses pagination.
13. Admin index supports `filter[isActive]`.
14. Public response is localized and paginated.
15. Public pagination defaults to `15` and allows at most `100` per page.
16. Public empty state returns `data: []` with pagination metadata.
17. FAQ answers use plain text with preserved line breaks.
18. No hard FAQ-count limit exists.
19. Admin supports `filter[isActive]` and `filter[search]`.
20. `filter[search]` searches both Arabic and English question text case-insensitively.
21. Duplicate questions are rejected per locale after trim and case-insensitive comparison.
22. Delete is permanent.
23. No categories, media, buttons, analytics, or unrelated CMS scope is added.
24. Transactions and locks protect ordering and duplicate prevention.
25. Required Pest, Pint, and PHPStan/Larastan checks pass.

---

## 31. Recommended Final Scope

```text
module:
009-faq-management

admin:
GET    /api/v1/admin/faqs
POST   /api/v1/admin/faqs
GET    /api/v1/admin/faqs/{faq}
PATCH  /api/v1/admin/faqs/{faq}
DELETE /api/v1/admin/faqs/{faq}

public:
GET /api/v1/public/faqs

permissions:
faqs.view
faqs.create
faqs.update
faqs.delete

fields:
questionAr
questionEn
answerAr
answerEn
isActive
position

required:
questionAr
questionEn
answerAr
answerEn
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
filter[search] across questionAr and questionEn

public:
active only
position ascending
localized
pagination
default perPage 15
maximum perPage 100
empty data array with meta

content:
plain text
line breaks preserved

duplicates:
rejected per locale after trim and case-insensitive comparison

count:
no hard application limit

delete:
permanent
```

---

## 32. Final Product Decisions

```text
answer format:
plain text with preserved line breaks

maximum FAQ count:
no hard application limit

admin filters:
filter[isActive]
filter[search]

admin search:
case-insensitive across Arabic and English question text only

public pagination:
enabled
default perPage 15
maximum perPage 100

duplicate questions:
rejected per locale after trim and case-insensitive comparison

delete:
permanent

position:
optional with automatic shifting
```

---

## 33. Decisions Already Aligned with Existing Project Patterns

The draft follows these existing project conventions:

- Laravel 13 API-only backend.
- Sanctum Bearer authentication.
- Spatie Permission authorization.
- Shared `ApiResponse` envelope.
- Existing `App\Enums\StatusCode`.
- Arabic/English localization.
- `Content-Language` and `Vary: Accept-Language` on Public responses.
- Admin paginated indexes.
- Public localized paginated projection.
- Delete success returns `200` with `data: null`.
- MySQL transactions and row locks for ordered mutations.
- Pest, Pint, and PHPStan/Larastan quality gates.

---

## 34. Final Approved Decisions

### Answer Format

```text
plain text with preserved line breaks
```

No sanitized HTML or rich-text contract is introduced.

### Maximum FAQ Count

```text
no hard application maximum
```

### Admin Filtering

```text
filter[isActive]
filter[search]
```

`filter[search]` performs a case-insensitive search across Arabic and English
question text only. It may be combined with `filter[isActive]`.


### Public Pagination

```text
enabled
default perPage = 15
maximum perPage = 100
```

### Duplicate Questions

```text
reject duplicates per locale after trim and case-insensitive comparison
```

### Delete

```text
permanent delete
```

Inactive state remains available for temporary hiding.
