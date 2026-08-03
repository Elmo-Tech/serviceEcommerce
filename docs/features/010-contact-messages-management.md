# Feature 010 — Contact Messages Management

## 1. Overview

This feature allows public website visitors to submit Contact Us messages and
allows authorized administrators to review, manage, and delete those messages.

The feature follows the existing Laravel 13 API architecture, shared response
envelope, Sanctum Bearer authentication, Spatie permissions, localization
middleware, MySQL persistence, and project validation conventions.

This document is a working draft. Final product decisions are listed in
section 35 and must be confirmed before specification freeze.

---

## 2. Module Name

```text
010-contact-messages-management
```

Canonical reference:

```text
docs/features/010-contact-messages-management.md
```

Status:

```text
Final Approved Scope
```

---

## 3. Goals

The feature must:

1. Provide a public Contact Us submission endpoint.
2. Store each accepted message safely in MySQL.
3. Allow authorized administrators to list submitted messages.
4. Allow administrators to inspect one complete message.
5. Allow administrators to update message workflow status.
6. Allow administrators to delete a message.
7. Support filtering and searching in the Admin index.
8. Protect the public endpoint against abuse.
9. Avoid exposing Admin-only message data publicly.
10. Keep the feature compatible with Hostinger-style deployment without queues,
    Redis, workers, or scheduler dependencies.

---

## 4. Out of Scope

Unless explicitly approved, the following are outside the feature:

- Public message listing.
- Public message tracking.
- Customer accounts.
- Message attachments.
- Images or files.
- Live chat.
- WhatsApp API integration.
- SMS integration.
- Ticket comments or threaded replies.
- Admin reply composer.
- Sending replies from the Dashboard.
- Assignment to Admin users.
- Internal notes.
- Labels or tags.
- Priority levels.
- SLA tracking.
- Automated chatbot responses.
- Queue workers.
- Redis.
- Scheduled cleanup.
- Soft delete.
- Message export.
- Bulk actions.
- Webhook delivery.

---

## 5. Actors

### Public Visitor

An unauthenticated visitor may submit a Contact Us message.

### Administrator

An authenticated active administrator may:

- View the paginated message index.
- View one message.
- Update its workflow status.
- Delete it permanently.

---

## 6. Permissions

Recommended permissions:

```text
contact-messages.view
contact-messages.update
contact-messages.delete
```

Rules:

- Permissions are added through the existing idempotent permission Seeder.
- `super-admin` receives all permissions through the normal assignment flow.
- No hidden super-admin bypass is introduced.
- There is no Admin Create permission because messages originate from the
  Public endpoint.

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

### Public

```http
POST /api/v1/public/contact-messages
```

### Admin

```http
GET    /api/v1/admin/contact-messages
GET    /api/v1/admin/contact-messages/{contactMessage}
PATCH  /api/v1/admin/contact-messages/{contactMessage}
DELETE /api/v1/admin/contact-messages/{contactMessage}
```

No Public GET endpoint exists.

No Admin POST endpoint exists.

---

## 8. Proposed Message Fields

Publicly submitted fields:

```text
name
email
phone
subject
message
```

Admin-managed fields:

```text
status
```

Internal fields:

```text
id
created_at
updated_at
```

No attachments, internal notes, assignee, or priority fields are included in
the baseline scope.

---

## 9. Required Fields

### Public Create

Required:

```text
name
phone
subject
message
```

Optional:

```text
email
```

Rules:

- All submitted string values are trimmed.
- Empty strings are invalid for required fields.
- An empty submitted email is normalized to `null`.
- The phone is stored as supplied after trimming outer whitespace.
- The backend does not convert the phone to Egyptian local format or E.164.
- Status is backend-controlled and cannot be submitted publicly.

---

## 10. Validation Rules

Recommended limits:

```text
name: string, min 2, max 150
email: nullable, valid email when present, max 254
phone: required string, min 3, max 30
subject: string, min 3, max 200
message: string, min 10, max 5000
```

General rules:

- Store plain text only.
- Preserve normal line breaks in `message`.
- Reject control characters except approved whitespace.
- Do not interpret submitted HTML.
- Do not expose raw validation internals.
- Use the shared `422 VALIDATION_ERROR` response contract.

---

## 11. Phone Handling

The phone field is required.

Rules:

- Trim outer whitespace.
- Store the visitor's submitted phone value without Egyptian or international
  normalization.
- Do not convert to local format.
- Do not convert to E.164.
- Allow reasonable international formatting characters such as:
  - digits
  - spaces
  - `+`
  - `-`
  - parentheses
- Reject HTML, control characters, and clearly invalid values.
- Return the stored value to authorized Admin users exactly as persisted.

---

## 12. Message Status

The feature uses an integer-backed status enum:

```text
0 = NEW
1 = READ
```

Stable API machine keys:

```text
new
read
```

Rules:

- Every accepted Public submission starts as `NEW`.
- The Public request cannot submit or influence status.
- Admin PATCH is the only workflow that may change status between `new` and
  `read`.
- No `resolved`, `closed`, `replied`, or `in_progress` status exists.
- Admin Show does not change the status.
- Status changes occur only through the Admin PATCH endpoint.

---

## 13. Public Create Request

Endpoint:

```http
POST /api/v1/public/contact-messages
Content-Type: application/json
```

Example:

```json
{
  "name": "Mohamed Hassan",
  "email": "mohamed@example.com",
  "phone": "01012345678",
  "subject": "Service inquiry",
  "message": "I would like to know more about the available services."
}
```

Rules:

- Unknown fields are rejected.
- Status is never accepted.
- ID and timestamps are never accepted.
- Attachments are never accepted.
- No `acceptedPrivacy` field is required or accepted.
- Honeypot fields are not required or accepted.
- The request is unauthenticated.
- Rate limiting and anti-spam behavior apply.

---

## 14. Public Create Response

Recommended successful status:

```text
201 Created
```

Recommended response:

```json
{
  "data": null
}
```

inside the approved shared success envelope.

The Public response must not return:

- Contact message ID.
- Status.
- Internal timestamps.
- Admin workflow data.

This avoids exposing identifiers that have no Public follow-up use.

---

## 15. Public Localization

The Public endpoint uses the existing locale middleware for:

- Validation messages.
- Success message.
- Error messages.

Supported resolved locales:

```text
ar
en
```

Locale variants may resolve through existing project rules:

```text
ar-EG → ar
en-US → en
```

Required response headers:

```http
Content-Language: ar|en
Vary: Accept-Language
```

Submitted content is stored exactly in the visitor's language and is not
translated by the backend.

---

## 16. Public Rate Limiting

A dedicated rate limiter is required.

Approved limit:

```text
5 attempted submissions per minute per IP
```

Rate-limit behavior:

- Exceeding the limit returns `429`.
- The response uses the shared error envelope.
- Do not expose server internals.
- Rate limiting must not require Redis.
- Laravel's configured cache/rate-limiter storage must remain compatible with
  the deployed environment.

---

## 17. Anti-Spam Strategy

The approved anti-spam strategy is:

```text
rate limit only
```

Rules:

- Apply the dedicated limit of 5 attempted submissions per minute per IP.
- Do not require a honeypot field.
- Do not require CAPTCHA.
- Do not add an external anti-spam provider.
- Keep strict validation, exact request schema, and maximum lengths.

---

## 18. Delivery Behavior

Accepted messages are stored for the Admin Dashboard only.

Rules:

- Do not send an email notification.
- Do not send SMS or WhatsApp notifications.
- Do not introduce queues or mail-delivery dependencies.
- The Dashboard retrieves messages through the Admin APIs.

---

## 19. Admin Index

Endpoint:

```http
GET /api/v1/admin/contact-messages
```

Default ordering:

```text
createdAt descending
```

### Pagination

Recommended:

```text
default perPage = 15
maximum perPage = 100
```

### Approved Query Parameters

```text
page
perPage
filter[status]
filter[search]
filter[date]
```

---

## 20. Admin Status Filter

Examples:

```http
GET /api/v1/admin/contact-messages?filter[status]=new
GET /api/v1/admin/contact-messages?filter[status]=read
```

Rules:

- Uses stable English machine keys.
- Invalid status returns `422 VALIDATION_ERROR`.
- Omitted status returns all records.

---

## 21. Admin Search Filter

Example:

```http
GET /api/v1/admin/contact-messages?filter[search]=mohamed
```

Approved search fields:

```text
name
email
phone
subject
```

Rules:

- Search is optional.
- Search value is trimmed.
- Empty search is treated as absent.
- Search is case-insensitive where supported by the configured MySQL collation.
- Message content is not searched.
- Search may be combined with status and date filters.
- Results remain ordered by `createdAt` descending.

---

## 22. Admin Date Filter

The Admin index uses one array filter:

```text
filter[date]
```

The filter accepts one or two positions:

```text
filter[date][0] = dateFrom
filter[date][1] = dateTo
```

Supported forms:

```text
[2025-02-25, null]
→ created_at on or after 2025-02-25 00:00:00 UTC

[2025-02-25, 2025-03-25]
→ created_at between both dates inclusively

[null, 2025-02-25]
→ created_at on or before 2025-02-25 23:59:59.999999 UTC
```

HTTP examples:

```http
filter[date][0]=2025-02-25
filter[date][0]=2025-02-25&filter[date][1]=2025-03-25
filter[date][1]=2025-02-25
```

Rules:

- Each supplied date uses `YYYY-MM-DD`.
- Both values may be supplied.
- Only the start date may be supplied.
- Only the end date may be supplied.
- At least one non-empty value is required when `filter[date]` is present.
- Empty values normalize to `null`.
- The filter applies to `created_at`.
- Date calculations use UTC.
- The range is inclusive.
- When both values exist, `dateFrom` later than `dateTo` returns
  `422 VALIDATION_ERROR`.

---

## 23. Admin Index Response

Example:

```json
{
  "data": [
    {
      "id": 15,
      "name": "Mohamed Hassan",
      "email": "mohamed@example.com",
      "phone": "+20 101 234 5678",
      "subject": "Service inquiry",
      "status": "new",
      "createdAt": "2026-08-02T14:00:00Z"
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

Admin index projection rules:

- Return the subject as the message title.
- Do not return `message`.
- Do not return `messagePreview`.
- The complete message is returned only by Admin Show.
- Return `id`, contact fields, `subject`, `status`, and `createdAt`.
- `updatedAt` is not required in the index resource.

---

## 24. Admin Show

Endpoint:

```http
GET /api/v1/admin/contact-messages/{contactMessage}
```

Example `data`:

```json
{
  "id": 15,
  "name": "Mohamed Hassan",
  "email": "mohamed@example.com",
  "phone": "01012345678",
  "subject": "Service inquiry",
  "message": "I would like to know more about the available services.",
  "status": "new",
  "createdAt": "2026-08-02T14:00:00Z",
  "updatedAt": "2026-08-02T14:00:00Z"
}
```

Rules:

- Admin Show is read-only.
- Opening a `new` message does not change it to `read`.
- The message remains `new` until an authorized Admin submits an explicit
  PATCH request.

Missing record:

```text
404 CONTACT_MESSAGE_NOT_FOUND
```

---

## 25. Admin Update

Endpoint:

```http
PATCH /api/v1/admin/contact-messages/{contactMessage}
Content-Type: application/json
```

Baseline request:

```json
{
  "status": "read"
}
```

Rules:

- Only approved Admin-managed fields may be updated.
- Public contact details and message content are immutable.
- Unknown fields are rejected.
- Empty PATCH requests are rejected.
- Invalid status returns `422`.
- Successful PATCH returns the complete updated Admin resource.

---

## 26. Admin Delete

Endpoint:

```http
DELETE /api/v1/admin/contact-messages/{contactMessage}
```

Approved behavior:

- Permanent delete.
- No soft delete.
- Successful response status `200`.
- Response `data` is `null`.
- Missing record returns `404`.


---

## 27. Database Design

### Table

```text
contact_messages
```

### Proposed Columns

```text
id
name
email nullable
phone
subject
message
status
created_at
updated_at
```

### Suggested Types

```text
id: unsigned big integer
name: varchar(150)
email: varchar(254) nullable
phone: varchar(30)
subject: varchar(200)
message: text
status: unsigned tiny integer
created_at: timestamp
updated_at: timestamp
```

### Proposed Indexes

```text
status
created_at
(status, created_at)
email
phone
```

Do not add speculative full-text infrastructure unless approved.

---

## 28. Privacy and Data Minimization

Baseline privacy behavior:

- Store only submitted contact information and workflow status.
- Do not store IP address.
- Do not store User-Agent.
- Do not perform geolocation.
- Do not expose messages publicly.
- Do not expose data to unauthorized Admin users.
- Do not include message contents in application logs.
- Do not include personal data in exception messages.

IP address and User-Agent are not persisted.

---

## 29. Suggested Structure

```text
app/
├── Actions/ContactMessages/
│   ├── CreateContactMessageAction.php
│   ├── UpdateContactMessageStatusAction.php
│   └── DeleteContactMessageAction.php
├── Enums/ContactMessages/
│   └── ContactMessageStatus.php
├── Http/
│   ├── Controllers/Api/V1/Admin/ContactMessages/
│   │   └── ContactMessageController.php
│   ├── Controllers/Api/V1/Public/ContactMessages/
│   │   └── ContactMessageController.php
│   ├── Requests/Api/V1/Admin/ContactMessages/
│   │   ├── IndexContactMessagesRequest.php
│   │   └── UpdateContactMessageRequest.php
│   ├── Requests/Api/V1/Public/ContactMessages/
│   │   └── StoreContactMessageRequest.php
│   └── Resources/Api/V1/Admin/ContactMessages/
│       ├── AdminContactMessageCollectionResource.php
│       └── AdminContactMessageResource.php
└── Models/
    └── ContactMessage.php
```

Optional synchronous mail notification may use a focused notification/service
only if approved.

---

## 30. Error Contract

Applicable stable application codes may include:

```text
VALIDATION_ERROR
CONTACT_MESSAGE_NOT_FOUND
CONTACT_MESSAGE_RATE_LIMITED
CONTACT_MESSAGE_SUBMISSION_FAILED
```

Status behavior:

- Invalid Public input: `422`.
- Rate limited Public request: `429`.
- Unauthenticated Admin request: `401`.
- Missing Admin permission: `403`.
- Missing Contact Message: `404`.
- Invalid Admin update: `422`.
- Unexpected persistence failure: project-standard safe server error.

Use the existing:

```text
App\Enums\StatusCode
```

Do not create another HTTP status enum.

---

## 31. Required Tests

### Public Submission

- Valid submission returns `201`.
- Successful response returns `data: null`.
- Required fields are enforced.
- Maximum lengths are enforced.
- Invalid email is rejected.
- Phone is required and stored without normalization.
- Email is optional.
- Unknown fields are rejected.
- Status cannot be submitted publicly.
- ID and timestamps cannot be submitted.
- Plain-text line breaks are preserved.
- HTML is not interpreted.
- Accepted submission starts with `NEW` status.
- Locale headers are returned.
- Arabic and English validation messages follow locale middleware.

### Abuse Protection

- Dedicated rate limiter is applied.
- Requests above the approved threshold return `429`.
- Rate limiting is the only anti-spam mechanism.
- No honeypot field is required.
- No CAPTCHA integration exists.
- No Redis dependency is introduced.

### Admin Authorization

- Every Admin endpoint rejects unauthenticated requests with `401`.
- Missing view permission returns `403`.
- Missing update permission returns `403`.
- Missing delete permission returns `403`.
- Super-admin receives all permissions through seeding.

### Admin Index

- Returns paginated results.
- Default `perPage` is `15`.
- Maximum `perPage` is `100`.
- Results order by newest first.
- Approved status filter works.
- Approved search filter works.
- `filter[date]` supports start-only, range, and end-only forms.
- Filters can be combined.
- Invalid filters return `422`.
- Response does not expose internal enum integers.

### Admin Show

- Existing record returns complete data.
- Missing record returns `404`.
- Status uses the stable machine key.
- Showing a `new` message does not change its status.
- Status changes only through PATCH.

### Admin Update

- `new` and `read` status updates succeed.
- Invalid status is rejected.
- Contact details cannot be modified.
- Message content cannot be modified.
- Unknown fields are rejected.
- Empty PATCH is rejected.
- Updated resource is returned.

### Admin Delete

- Delete is permanent.
- Successful Delete returns `200` with `data: null`.
- Missing record returns `404`.

### Privacy

- Public endpoints never expose stored messages.
- Public response never exposes ID or status.
- Logs do not contain message content.
- IP address and User-Agent are not persisted.

### Dashboard Delivery

- Accepted messages are available through the Admin Dashboard APIs.
- No notification email is sent.
- No queue or mail-delivery dependency is introduced.

### Quality Gates

- Pest passes against MySQL.
- Pint passes.
- PHPStan/Larastan passes at the configured project level.

---

## 32. Acceptance Criteria

The feature is complete when:

1. Public visitors can submit valid Contact Us messages.
2. Accepted messages are stored safely.
3. New messages receive the approved initial status.
4. Public responses do not expose message identifiers or workflow data.
5. Admin index, show, update, and delete endpoints exist.
6. Explicit view, update, and delete permissions are enforced.
7. Admin index is paginated.
8. Status, search, and `filter[date]` filters are enforced.
9. Contact content is immutable through Admin update.
10. Delete is permanent.
11. Public rate limiting is implemented as the only anti-spam mechanism.
12. IP address and User-Agent are not persisted.
13. Messages are delivered to the Dashboard only; no email is sent.
14. No queues, Redis, attachments, or reply system are added.
15. Pest, Pint, and PHPStan/Larastan checks pass.
16. Admin Show remains read-only and status changes only through PATCH.

---

## 33. Recommended Baseline Scope

```text
module:
010-contact-messages-management

public:
POST /api/v1/public/contact-messages

admin:
GET    /api/v1/admin/contact-messages
GET    /api/v1/admin/contact-messages/{contactMessage}
PATCH  /api/v1/admin/contact-messages/{contactMessage}
DELETE /api/v1/admin/contact-messages/{contactMessage}

permissions:
contact-messages.view
contact-messages.update
contact-messages.delete

fields:
name
email
phone
subject
message
status

status:
new
read

admin show:
read-only
does not auto-mark as read

admin update:
status only

admin index:
pagination
filter[status]
filter[search]
filter[date]

public protection:
rate limit only
5 attempts per minute per IP
strict validation
no honeypot
no CAPTCHA

delivery:
dashboard only
no email notification

delete:
permanent

no:
attachments
public tracking
admin replies
assignment
internal notes
soft delete
queues
Redis
```

---

## 34. Decisions Already Aligned with Existing Project Patterns

The draft follows:

- Laravel 13 API-only backend.
- Sanctum Bearer authentication.
- Spatie permissions.
- Shared `ApiResponse` envelope.
- Existing `App\Enums\StatusCode`.
- Arabic/English API localization.
- UTC timestamps.
- Admin pagination.
- Stable English machine enum keys.
- Delete success as `200` with `data: null`.
- No queue/Redis/runtime worker assumptions.
- Pest, Pint, and PHPStan/Larastan quality gates.

---

## 35. Final Approved Decisions

### Anti-Spam Method

```text
rate limit only
5 attempted submissions per minute per IP
```

No honeypot and no CAPTCHA are used.

### Read Status Behavior

```text
Admin Show does not change status
```

A message remains `new` until an authorized Admin explicitly changes it to
`read` through:

```http
PATCH /api/v1/admin/contact-messages/{contactMessage}
```

The Show endpoint is strictly read-only.
