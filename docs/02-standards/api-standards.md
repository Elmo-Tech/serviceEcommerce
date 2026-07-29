# Service Commerce Backend - API Standards

> **Scope:** All Laravel JSON APIs exposed by the Service Commerce Backend and
> consumed by the approved React administration dashboard, Next.js public
> website, or explicitly approved integrations.
>
> **Architecture:** Versioned API-first conventional Laravel monolith.
>
> **Repository Scope:** Backend only.

---

## 1. Purpose

This document defines the global API contract for:

- administrator authentication
- roles and permissions
- customers and customer addresses
- service categories and subcategories
- services
- service media
- service specifications
- service order options
- guest order submission
- quote-required pricing
- orders and order items
- order-item attachments
- site settings
- hero content
- Contact Us enquiries and replies
- testimonials
- FAQs
- featured services
- best-selling services
- dashboard and reporting APIs

It defines shared API behaviour only.

Feature-specific fields, permissions, validation limits, workflow rules,
transition matrices, and business error codes belong in the related approved
feature specification and API contract.

Every feature API MUST reference and comply with this document instead of
redefining the global API envelope, field casing, pagination structure, error
shape, or localization behaviour.

---

## 2. Related Documents

Before designing or implementing an endpoint, read:

- `AGENTS.md`
- `.specify/memory/constitution.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- applicable files under `docs/02-standards/`
- the active feature's `spec.md`, `plan.md`, and `tasks.md`
- the active feature API contract when one exists

Important future standards may include:

- `docs/02-standards/coding-standards.md`
- `docs/02-standards/database-standards.md`
- `docs/02-standards/authentication-standards.md`
- `docs/02-standards/authorization-standards.md`
- `docs/02-standards/file-storage-standards.md`
- `docs/02-standards/localization-standards.md`
- `docs/02-standards/testing-standards.md`
- `docs/02-standards/security-standards.md`
- `docs/02-standards/queue-job-standards.md`

When documents conflict, implementation MUST stop until the governing documents
are reconciled.

The agent MUST NOT silently choose a contract that changes approved product
behaviour.

---

## 3. Golden Rules

- Every product endpoint MUST be versioned.
- Every endpoint MUST return JSON unless it intentionally returns or streams a
  file.
- Every JSON response MUST follow the approved global envelope except
  `204 No Content`.
- API request and response keys use `camelCase`.
- Database columns use `snake_case`.
- URL path segments use lowercase kebab-case.
- Technical error codes use `UPPER_SNAKE_CASE`.
- Stable enum values use documented lowercase snake-case strings.
- Controllers MUST remain thin.
- All mutation input MUST use dedicated Laravel Form Requests.
- Output MUST use API Resources, Resource Collections, or an approved explicit
  transformer.
- Authentication and authorization MUST be enforced on the backend.
- Public guest requests MUST NOT be treated as authenticated customer requests.
- Customers MUST NOT receive customer-authentication APIs in the MVP.
- Frontend-provided prices, totals, availability, category eligibility, option
  eligibility, ownership, and statuses are never authoritative.
- Every service submitted in an order MUST be reloaded and revalidated.
- Every selected option MUST be validated against its submitted service.
- Order-item attachments MUST be mapped to the intended submitted item.
- Order snapshots MUST preserve approved order-time values.
- Backend PHP code that selects an HTTP status MUST use the shared
  `App\Enums\HttpStatusCode` enum unless an approved architecture decision
  replaces it.
- Lists MUST be paginated unless the endpoint is an explicitly documented,
  safely bounded reference list.
- Filters, sorts, includes, and optional sparse fieldsets MUST be explicitly
  allow-listed.
- Errors MUST be safe, predictable, localized in Arabic or English, and
  machine-readable through stable English codes.
- Missing product behaviour MUST be clarified in the feature specification,
  not invented during implementation.

---

## 4. API Base URL and Versioning

All application APIs use:

```text
/api/v1/...
```

Approved route areas:

```text
/api/v1/admin/auth/*
/api/v1/admin/*
/api/v1/public/*
```

Examples:

```http
POST /api/v1/admin/auth/login
POST /api/v1/admin/auth/logout
GET  /api/v1/admin/auth/profile

GET  /api/v1/admin/categories
GET  /api/v1/admin/services
GET  /api/v1/admin/orders

GET  /api/v1/public/categories
GET  /api/v1/public/services
POST /api/v1/public/orders
POST /api/v1/public/contact-us
```

Rules:

- Unversioned product API routes are prohibited.
- Non-breaking additive changes MAY remain in the current API version.
- Breaking field removal, field renaming, semantic changes, incompatible enum
  changes, or incompatible workflow changes require:
  - a new API version, or
  - an explicitly approved backward-compatibility strategy
- Business logic MUST NOT be duplicated across API versions.
- Version-specific controllers and Resources SHOULD call shared Actions,
  Services, and Query classes where behaviour remains shared.
- A route version is part of the external contract and MUST NOT be changed
  casually.

---

## 5. API Areas

### 5.1 Authentication APIs

Authentication APIs are used only by administrator users in the MVP.

Examples:

```http
POST  /api/v1/admin/auth/login
POST  /api/v1/admin/auth/refresh
POST  /api/v1/admin/auth/logout
GET   /api/v1/admin/auth/profile
PATCH /api/v1/admin/auth/profile
PUT   /api/v1/admin/auth/change-password
```

Rules:

- Login is public but rate-limited.
- Logout, current-user, profile, and password endpoints require
  `auth:sanctum`.
- The active Identity and Authentication specification controls exact routes
  and fields.
- Customer authentication endpoints MUST NOT be introduced in the MVP.

### 5.2 Administration APIs

Used by authenticated and authorized administrators.

Examples:

```http
GET /api/v1/admin/customers
GET /api/v1/admin/categories
GET /api/v1/admin/categories/{category}/subcategories
GET /api/v1/admin/services
GET /api/v1/admin/services/{service}/order-questions
GET /api/v1/admin/orders
GET /api/v1/admin/contact-messages
GET /api/v1/admin/dashboard/summary
```

Administration APIs may manage:

- customers
- addresses
- root categories
- subcategories
- services
- service media
- service specifications
- order options
- orders
- quote-required prices
- order-item attachments
- site content
- contact enquiries and replies
- dashboard and reports

### 5.3 Public APIs

Public APIs are consumed by the external Next.js website.

Examples:

```http
GET  /api/v1/public/categories
GET  /api/v1/public/categories/{category:slug}/subcategories
GET  /api/v1/public/services
GET  /api/v1/public/services/{service:slug}
GET  /api/v1/public/services/{service:slug}/order-questions
GET  /api/v1/public/site-settings
GET  /api/v1/public/hero-sections
GET  /api/v1/public/testimonials
GET  /api/v1/public/faqs
GET  /api/v1/public/featured-services
GET  /api/v1/public/best-selling-services
POST /api/v1/public/orders
POST /api/v1/public/contact-us
```

Public APIs do not imply customer authentication.

A guest customer cannot use public APIs to:

- list previous orders
- view order details
- track an order
- edit an order
- cancel an order
- upload files after order submission
- update a stored customer profile
- manage stored addresses

### 5.4 Internal or Integration APIs

Internal APIs, webhooks, or integration endpoints MUST be:

- explicitly approved
- documented separately
- separately authenticated
- rate-limited
- idempotent where retries can duplicate effects
- excluded from public route groups
- covered by automated tests

No internal or webhook route area is introduced merely for future possibility.

---

## 6. Route File Organization

Recommended structure:

```text
routes/
  api.php
  api/
    v1/
      auth.php
      admin.php
      public.php
```

`routes/api.php` SHOULD load only versioned route groups.

Rules:

- Do not place business logic in route closures.
- Apply `auth:sanctum`, active-user, and broad permission middleware at the
  route-group level where appropriate.
- Route names MUST be stable and descriptive.
- Route model binding MUST NOT replace authorization.
- Public and administration route definitions MUST remain clearly separated.
- Route files SHOULD be split further only when size or ownership justifies it.
- Do not create one route file per controller without a demonstrated need.

---

## 7. Resource and Route Naming

Use plural nouns for resource collections:

```http
/customers
/customer-addresses
/categories
/subcategories
/services
/service-media
/service-specifications
/service-option-groups
/service-option-values
/orders
/order-items
/order-item-attachments
/contact-messages
/testimonials
/faqs
/featured-services
```

Use nested routes only when the child does not have a meaningful independent
context or when the hierarchy improves clarity.

Examples:

```http
GET  /api/v1/admin/categories/{category}/subcategories
POST /api/v1/admin/categories/{category}/subcategories

GET  /api/v1/admin/services/{service}/media
POST /api/v1/admin/services/{service}/media

GET  /api/v1/admin/orders/{order}/items
GET  /api/v1/admin/orders/{order}/items/{orderItem}/attachments
```

Domain commands are allowed when CRUD does not clearly express the behaviour:

```http
POST /api/v1/admin/orders/{order}/cancel
POST /api/v1/admin/orders/{order}/reject
POST /api/v1/admin/orders/{order}/status-transitions
POST /api/v1/admin/orders/{order}/items/{orderItem}/price
POST /api/v1/admin/contact-messages/{contactMessage}/reply
POST /api/v1/admin/services/{service}/restore
```

Rules:

- URL segments use lowercase kebab-case.
- Collection names use plural nouns.
- Do not expose database table names when the product term differs.
- Do not use verbs in CRUD collection routes.
- Use command endpoints for meaningful state transitions or domain actions.
- Avoid deeply nested routes.
- A nested route MUST validate that every child belongs to the route parent.
- Nested route binding MUST NOT allow cross-parent resource access.

---

## 8. HTTP Methods

| Method | Use |
|---|---|
| `GET` | Retrieve data without mutation |
| `POST` | Create a resource or execute a domain command |
| `PATCH` | Partially update a resource |
| `PUT` | Fully replace a resource or perform a deliberately designed full update |
| `DELETE` | Delete, archive, detach, or remove according to documented semantics |
| `HEAD` | Optional lightweight existence or status check |
| `OPTIONS` | Framework-managed CORS and preflight |

Rules:

- `GET` MUST NOT mutate state.
- Use `PATCH` for ordinary partial updates.
- Use `POST` for commands such as cancel, reject, reply, restore, or set a
  quote-required price.
- Do not use `POST` as a replacement for every method.
- `DELETE` does not automatically mean physical database deletion.
- Soft-delete, archive, deactivate, or detach semantics MUST be documented by
  the feature contract.
- Method semantics must remain stable once published.

---

## 9. Required Request Headers

All JSON API requests SHOULD send:

```http
Accept: application/json
Content-Type: application/json
Accept-Language: ar
```

or:

```http
Accept-Language: en
```

Authenticated administration requests MUST send:

```http
Authorization: Bearer {sanctumToken}
```

Multipart order or media requests send:

```http
Accept: application/json
Content-Type: multipart/form-data
Accept-Language: ar
```

or:

```http
Accept-Language: en
```

The HTTP client or browser MUST generate the multipart boundary. Consumers
SHOULD NOT manually hard-code the boundary.

Optional approved headers:

```http
X-Request-ID: <uuid-or-ulid>
Idempotency-Key: <client-generated-key>
```

Rules:

- The server MAY generate `X-Request-ID` when absent.
- The response SHOULD return the effective request ID when request correlation
  is enabled.
- `Accept-Language` resolves to Arabic or English.
- Arabic is the initial configured default.
- English is the configured fallback.
- Missing or unsupported locale values use the configured fallback chain.
- Client-provided role, user ID, customer ID, category scope, permission, or
  ownership headers MUST NOT be trusted.
- Bearer token authentication is the approved MVP Sanctum flow.
- CSRF-cookie authentication is not the approved administration API flow in the
  MVP.

---

## 10. Request and Response Key Naming

### 10.1 Database keys

Database columns use `snake_case`:

```text
first_name
customer_id
subcategory_id
publication_status
pricing_type
created_at
order_number
```

### 10.2 API keys

API keys use `camelCase`:

```json
{
  "firstName": "Mohamed",
  "customerId": 12,
  "subcategoryId": 8,
  "publicationStatus": "active",
  "pricingType": "quote_required",
  "createdAt": "2026-07-28T09:30:00Z",
  "orderNumber": "ORD-260728-5896"
}
```

Rules:

- Request and response keys use `camelCase`.
- URL segments use kebab-case.
- Query parameter names use `camelCase`.
- Technical error codes use `UPPER_SNAKE_CASE`.
- Enum values use documented lowercase snake-case strings.
- A payload MUST NOT mix `snake_case` and `camelCase`.
- Validation error keys MUST match public request keys.
- Nested validation errors use dot notation with array indexes when
  appropriate.

Example:

```json
{
  "errors": {
    "items.0.subcategoryId": [
      "القسم الفرعي المحدد غير صالح."
    ],
    "items.1.attachments.0": [
      "نوع الملف غير مدعوم."
    ]
  }
}
```

---

## 11. Data Type Representation

### 11.1 Identifiers

Internal identifiers are represented as JSON numbers unless the feature
contract intentionally exposes another public identifier.

Example:

```json
{
  "id": 125
}
```

The public order reference is represented as a string:

```json
{
  "orderNumber": "ORD-260728-5896"
}
```

### 11.2 Money

Money values MUST be returned as fixed-precision decimal strings.

Example:

```json
{
  "basePrice": "150.00",
  "knownSubtotal": "300.00",
  "totalAmount": null
}
```

Rules:

- Do not return floating-point money.
- Do not remove meaningful decimal precision inconsistently.
- `null` represents a valid unknown final amount for incomplete quote pricing.
- Currency is not part of the MVP contract.
- Do not add a currency field until currency behaviour is approved.

### 11.3 Booleans

Boolean values use JSON booleans:

```json
{
  "isActive": true,
  "isAvailable": false
}
```

Do not return `0`, `1`, `"true"`, or `"false"` for API booleans.

### 11.4 Null

Use `null` when absence or unknown state is valid.

Do not use:

- empty strings as unknown numeric values
- `0` as an unknown price
- placeholder dates
- `"N/A"` as a machine value

### 11.5 Arrays and objects

- Empty collections return `[]`.
- Empty object-shaped metadata returns `{}` only when the contract requires the
  object.
- Optional absent fields should normally be omitted or returned as `null`
  according to the documented Resource contract.
- The same field must not alternate unpredictably between array and object.

---

## 12. Global Success Response

Standard success envelope:

```json
{
  "success": true,
  "message": "تم إنشاء الطلب بنجاح.",
  "data": {
    "orderNumber": "ORD-260728-5896"
  }
}
```

Rules:

- `success` is always `true`.
- `message` is a safe user-facing string in the resolved Arabic or English locale.
- `data` contains a resource, collection, operation result, or `null`.
- Controllers MUST use the approved shared response builder.
- API Resources remain responsible for fields inside `data`.
- Stable machine behaviour MUST NOT depend on parsing the translated message.
- A successful response does not require a machine code unless an approved
  global contract later introduces success codes.

### 12.1 Created resource

Use:

```http
201 Created
```

Example:

```json
{
  "success": true,
  "message": "تم إنشاء الخدمة بنجاح.",
  "data": {
    "id": 25,
    "name": "Website Development",
    "slug": "website-development"
  }
}
```

### 12.2 Updated resource

Use:

```http
200 OK
```

Return the updated Resource unless the feature contract explicitly uses
`204 No Content`.

### 12.3 Command success

A command may return the updated resource or a focused result.

Example cancellation response:

```json
{
  "success": true,
  "message": "تم إلغاء الطلب بنجاح.",
  "data": {
    "orderNumber": "ORD-260728-5896",
    "status": "cancelled",
    "cancellationReason": "تعذر تنفيذ الخدمة في الموعد المطلوب."
  }
}
```

### 12.4 Empty success

Use either:

```http
204 No Content
```

with no response body, or a normal `200` envelope when a message or resulting
state is useful.

Never return a JSON body with `204`.

### 12.5 Asynchronous side effect

When the database operation is complete but a side effect is queued, the
response MUST describe only what is known.

Contact reply example:

```json
{
  "success": true,
  "message": "تم حفظ الرد وإضافته إلى قائمة إرسال البريد.",
  "data": {
    "status": "replied",
    "emailDeliveryStatus": "queued"
  }
}
```

The API MUST NOT claim that an email was delivered merely because the Job was
queued.

---

## 13. Global Error Response

Standard error envelope:

```json
{
  "success": false,
  "message": "الخدمة غير متاحة حالياً.",
  "code": "SERVICE_UNAVAILABLE",
  "errors": null
}
```

Rules:

- `success` is always `false`.
- `message` is safe and localized for user display.
- `code` is a stable English machine-readable identifier.
- `errors` contains field-validation errors or approved structured details;
  otherwise it is `null`.
- Error messages MUST NOT expose:
  - stack traces
  - SQL
  - database names
  - filesystem paths
  - exception class names
  - tokens
  - secrets
  - SMTP details
  - internal storage paths

### 13.1 Validation error

- HTTP status: `422`
- Code: `VALIDATION_ERROR`

Example:

```json
{
  "success": false,
  "message": "البيانات المرسلة غير صالحة.",
  "code": "VALIDATION_ERROR",
  "errors": {
    "phone": [
      "رقم الهاتف مطلوب."
    ],
    "items.0.serviceId": [
      "الخدمة المحددة غير صالحة."
    ]
  }
}
```

### 13.2 Authentication error

- HTTP status: `401`
- Code: `UNAUTHENTICATED`

### 13.3 Authorization error

- HTTP status: `403`
- Code: `FORBIDDEN`

### 13.4 Not found

- HTTP status: `404`
- Code: `RESOURCE_NOT_FOUND`

### 13.5 Conflict

- HTTP status: `409`
- Code: a shared or feature-specific conflict code

Examples:

```text
ORDER_STATUS_CONFLICT
ORDER_PRICING_INCOMPLETE
CATEGORY_HIERARCHY_INVALID
ORDER_ALREADY_CANCELLED
```

### 13.6 Rate limit

- HTTP status: `429`
- Code: `RATE_LIMITED`

### 13.7 Unexpected server error

- HTTP status: `500`
- Code: `INTERNAL_ERROR`

The response uses a generic localized Arabic or English message.

Technical detail belongs in application logs correlated with the request ID.

---

## 14. HTTP Status Codes

| Situation | Status |
|---|---|
| Successful read or update with body | `200 OK` |
| Successful resource creation | `201 Created` |
| Accepted asynchronous operation whose primary operation is not yet complete | `202 Accepted` |
| Successful operation with no body | `204 No Content` |
| Malformed or semantically invalid request outside normal validation | `400 Bad Request` |
| Not authenticated | `401 Unauthorized` |
| Authenticated but not authorized | `403 Forbidden` |
| Resource missing or intentionally hidden | `404 Not Found` |
| Unsupported method | `405 Method Not Allowed` |
| State conflict or duplicate business operation | `409 Conflict` |
| Validation failed | `422 Unprocessable Content` |
| Rate limit exceeded | `429 Too Many Requests` |
| Unexpected application error | `500 Internal Server Error` |
| Required infrastructure dependency unavailable | `503 Service Unavailable` |

Rules:

- Do not return `200` for validation or business-rule failures.
- Do not return `500` for expected validation or state conflicts.
- Use `404` when hiding resource existence is part of the authorization
  strategy.
- Use `409` when valid input cannot be applied because current resource state
  prevents the operation.
- Use `422` for field and payload validation errors.
- Laravel code selecting a response status uses
  `App\Enums\HttpStatusCode`.

---

## 15. Business Error Codes

Error codes MUST be:

- stable
- documented
- written in `UPPER_SNAKE_CASE`
- independent from localized messages
- specific enough for frontend handling
- reused only when semantics are genuinely shared

Shared codes:

```text
VALIDATION_ERROR
UNAUTHENTICATED
INVALID_CREDENTIALS
USER_INACTIVE
FORBIDDEN
RESOURCE_NOT_FOUND
METHOD_NOT_ALLOWED
CONFLICT
RATE_LIMITED
INTERNAL_ERROR
DEPENDENCY_UNAVAILABLE
```

Common service-commerce codes may include:

```text
CATEGORY_INACTIVE
SUBCATEGORY_INACTIVE
CATEGORY_HIERARCHY_INVALID
CATEGORY_HAS_SUBCATEGORIES
SUBCATEGORY_HAS_SERVICES
SERVICE_INACTIVE
SERVICE_UNAVAILABLE
SERVICE_NOT_ORDERABLE
OPTION_GROUP_INVALID
OPTION_VALUE_INVALID
OPTION_VALUE_UNAVAILABLE
OPTION_SELECTION_REQUIRED
OPTION_SELECTION_LIMIT_EXCEEDED
REQUIRED_SERVICE_ANSWER_MISSING
INVALID_SERVICE_ANSWER
SERVICE_QUESTION_NOT_ACTIVE
SERVICE_QUESTION_CHOICE_INVALID
ORDER_PRICING_INCOMPLETE
ORDER_STATUS_CONFLICT
ORDER_TERMINAL
ORDER_CANCELLATION_REASON_REQUIRED
ORDER_REJECTION_REASON_REQUIRED
ORDER_ITEM_NOT_QUOTE_REQUIRED
ORDER_ITEM_ALREADY_PRICED
ATTACHMENT_TYPE_NOT_ALLOWED
ATTACHMENT_SIZE_EXCEEDED
ATTACHMENT_COUNT_EXCEEDED
ATTACHMENT_ITEM_MAPPING_INVALID
CONTACT_MESSAGE_ALREADY_ARCHIVED
```

The feature specification MUST document:

- code
- HTTP status
- triggering condition
- response fields
- whether the resource existence should be hidden

Do not invent new codes inside controllers.

---

## 16. Controller, Action, Service, and Resource Flow

Approved complex flow:

```text
Route
  -> Middleware
  -> Form Request
  -> Authorization and state checks
  -> Action
      -> focused reusable Services
  -> API Resource / Resource Collection
  -> Global API Response
```

Approved reusable-capability flow:

```text
Route
  -> Middleware
  -> Form Request
  -> Controller
      -> focused Service
  -> API Resource
  -> Global API Response
```

Approved simple CRUD flow:

```text
Route
  -> Middleware
  -> Form Request
  -> Controller
      -> small Eloquent operation
  -> API Resource
  -> Global API Response
```

Rules:

- Actions orchestrate complex use cases.
- Services provide reusable business capabilities.
- Simple CRUD does not require a pass-through Action or Service.
- Controllers MUST NOT:
  - return raw Eloquent models
  - return ad hoc response envelopes
  - catch every exception manually
  - contain long transactions
  - calculate prices
  - implement category hierarchy rules inline repeatedly
  - implement order status maps inline
  - perform direct email delivery
  - hard-code scattered numeric HTTP statuses

---

## 17. Validation Standards

- Mutation endpoints MUST use dedicated Form Requests.
- Complex query input SHOULD use dedicated query Form Requests.
- Validation errors use `422` and `VALIDATION_ERROR`.
- Error keys use public `camelCase` names.
- Complex reusable validation belongs in custom Rule classes or focused
  validators.
- Database uniqueness validation MUST be backed by a database unique
  constraint when the value is a true invariant.
- Uploaded files require:
  - upload success validation
  - allowed extension validation
  - MIME validation
  - size validation
  - count validation
  - item-context validation
- IDs must be validated for format and existence, then authorized.
- Existence alone does not establish authorization or valid business state.
- Mutation payloads MUST reject or ignore unapproved protected fields according
  to the feature contract.
- The backend MUST not mass assign:
  - prices
  - totals
  - order status
  - pricing status
  - user roles
  - permission sets
  - system-generated order numbers
  - snapshot values supplied as authoritative frontend data

### 17.1 Normalization

Normalization must be explicit and consistent.

Examples:

- trim text fields
- normalize email casing according to the approved standard
- normalize phone numbers before customer matching
- normalize slugs through the approved slug strategy
- normalize booleans before validation when appropriate
- never normalize identifiers into a different actor-owned resource silently

---

## 18. Authentication Standards

The administration API uses Laravel Sanctum personal access tokens.

Protected requests send:

```http
Authorization: Bearer {token}
```

Rules:

- Only administrator users authenticate in the MVP.
- Login errors MUST be generic.
- Login MUST NOT reveal whether an email exists.
- Inactive administrators MUST be denied.
- Repeated failed attempts MUST trigger temporary rate limiting.
- Rate-limit thresholds and cooldowns MUST be configurable.
- Successful login returns the plain-text token only once.
- Raw tokens MUST NOT be persisted by application code.
- Raw tokens MUST NOT be logged.
- Logout revokes the current access token.
- Protected endpoints return `401` when unauthenticated.
- Authentication does not replace permission authorization.
- Token abilities do not replace roles, permissions, or resource rules.
- Customer records do not receive Sanctum tokens.

Example login success:

```json
{
  "success": true,
  "message": "تم تسجيل الدخول بنجاح.",
  "data": {
    "token": "plain-text-token-returned-once",
    "tokenType": "Bearer",
    "user": {
      "id": 1,
      "name": "Super Admin",
      "email": "admin@example.com",
      "type": 0,
      "roles": [
        "super-admin"
      ]
    }
  }
}
```

The active authentication specification determines exact token metadata and
expiration fields.

---

## 19. Authorization Standards

Authentication answers who the administrator is.

Authorization answers whether the administrator may perform the operation.

Rules:

- Every administration endpoint MUST authorize the required permission or
  ability.
- `users.type` alone MUST NOT authorize actions.
- React UI visibility MUST NOT authorize actions.
- Spatie permissions provide the baseline capability model.
- Policies or Action-level checks may enforce resource and state rules.
- Route model binding MUST NOT bypass authorization.
- Nested resources MUST be confirmed to belong to the parent route resource.
- Request fields MUST NOT grant:
  - roles
  - permissions
  - ownership
  - pricing authority
  - status-transition authority
- Jobs and commands acting on behalf of an administrator MUST preserve the
  approved authorization context where required.
- Public endpoints expose only approved data and never administration-only
  fields.

The initial role is:

```text
super-admin
```

Exact permission names belong in the Roles and Permissions specification.

---

## 20. API Resources and Visibility

Use explicit API Resources.

Rules:

- Never return raw Eloquent models.
- Resource fields MUST be explicit.
- Use `whenLoaded()` for optional relationships.
- Index Resources MUST remain lightweight.
- Resources MUST NOT trigger avoidable database queries.
- Eager load required relationships before transformation.
- Public and administration Resources MAY differ.
- Public Resources MUST exclude:
  - administrative notes
  - private customer metadata
  - raw storage paths
  - internal pricing workflow fields not approved for public output
  - administrator identifiers not needed publicly
- Administration Resources may expose approved operational fields only to
  authorized users.
- Timestamps use ISO 8601 UTC.
- Money values use decimal strings.
- Enum values remain stable English strings.

### 20.1 Catalogue Resource visibility

Public category and subcategory Resources should expose approved catalogue
fields such as:

- id where approved
- name
- slug
- image where approved
- sort order where required by the frontend
- active child collections only when explicitly included

Public services must not become orderable solely because the service record is
active.

Orderability requires approved eligibility across:

- root category
- subcategory
- service publication
- service availability

Visibility of an active but unavailable service is controlled by the Services
Catalog feature contract.

Localized public Resources return the resolved Arabic or English string.

Administration Resources may return editable translation objects such as:

```json
{
  "name": {
    "ar": "تطوير المواقع",
    "en": "Website Development"
  }
}
```

The same endpoint must not alternate unpredictably between a resolved string and
translation object.

### 20.2 Order Resource visibility

Public order creation responses should expose only the approved submission
result, normally including:

- order number
- initial status where approved
- pricing state where approved
- safe known totals where approved

They MUST NOT expose:

- internal order ID unless explicitly required
- attachment paths
- administrative notes
- internal customer metadata
- other customer orders
- a tracking token that has not been approved

---

## 21. Pagination Standards

All potentially unbounded lists MUST be paginated.

Default values:

```text
default perPage = 15
maximum perPage = 100
```

A feature may define a stricter maximum.

Approved query parameters:

```http
?page=2&perPage=25
```

Standard collection response:

```json
{
  "success": true,
  "message": "تم جلب الخدمات بنجاح.",
  "data": [
    {
      "id": 10,
      "name": "Website Development"
    }
  ],
  "meta": {
    "pagination": {
      "currentPage": 2,
      "perPage": 25,
      "total": 140,
      "lastPage": 6,
      "from": 26,
      "to": 50
    }
  },
  "links": {
    "first": "https://api.example.com/api/v1/public/services?page=1",
    "last": "https://api.example.com/api/v1/public/services?page=6",
    "prev": "https://api.example.com/api/v1/public/services?page=1",
    "next": "https://api.example.com/api/v1/public/services?page=3"
  }
}
```

Rules:

- Invalid `page` or `perPage` values return `422`.
- `perPage` MUST be capped.
- Empty pages return an empty `data` array and valid metadata.
- Small reference lists MAY be unpaginated only when documented and bounded.
- The envelope shape must remain consistent across paginated endpoints.

---

## 22. Filtering Standards

Use bracketed filter parameters compatible with
`spatie/laravel-query-builder`.

Examples:

```http
GET /api/v1/public/services?filter[categoryId]=3
GET /api/v1/public/services?filter[subcategoryId]=12
GET /api/v1/admin/services?filter[publicationStatus]=active
GET /api/v1/admin/orders?filter[status]=awaiting_review
```

Rules:

- Filters MUST be allow-listed.
- Public filter names use `camelCase`.
- Do not pass arbitrary request keys directly to `where()`.
- Invalid filter values return `422`.
- Relationship filters must enforce approved visibility.
- Filter semantics must be documented and tested.
- A filter must not silently change meaning between public and admin APIs.

### 22.1 Category and subcategory filters

For services:

```text
filter[categoryId]
```

returns services belonging to all subcategories of the selected root category.

```text
filter[subcategoryId]
```

returns services assigned directly to the selected subcategory.

Rules:

- A root category ID is not a valid `subcategoryId`.
- A subcategory ID is not silently treated as a root `categoryId`.
- The public query must enforce active hierarchy eligibility.
- The administration query may expose inactive records when explicitly
  filtered and authorized.
- Category hierarchy deeper than two levels is unsupported.

### 22.2 Multiple values

Multiple-value syntax must be documented per feature.

Do not support comma-separated values automatically unless the feature contract
defines them.

---

## 23. Sorting Standards

Use `sort`:

```http
GET /api/v1/public/services?sort=sortOrder
GET /api/v1/public/services?sort=-createdAt
GET /api/v1/admin/orders?sort=status,-createdAt
```

Rules:

- `field` means ascending.
- `-field` means descending.
- Sort fields MUST be allow-listed.
- Public sort names use `camelCase`.
- Sort names map internally to approved database columns or query expressions.
- Request-provided raw SQL or column expressions are prohibited.
- A stable secondary sort SHOULD be applied when duplicate primary values can
  cause inconsistent pagination.
- Manual catalogue ordering commonly uses `sortOrder`.

---

## 24. Search Standards

Use `q` for general text search:

```http
GET /api/v1/admin/customers?q=01012345678
GET /api/v1/admin/orders?q=ORD-260728-5896
GET /api/v1/public/services?q=website
```

Rules:

- Search input MUST be trimmed.
- Search length MUST be limited.
- Search behaviour must be documented per endpoint.
- Search must respect public or administration visibility.
- Search must not expose private customer data through public APIs.
- High-volume search fields require suitable indexes or an approved search
  strategy.
- Do not assume full-text search across long descriptions unless specified.
- Customer phone search should use the approved normalized phone field where
  applicable.
- Order search should support the public order number where approved.

---

## 25. Includes and Relationship Loading

Optional relationship inclusion may use:

```http
?include=subcategory,media,optionGroups
```

Rules:

- Includes MUST be allow-listed.
- Unknown includes return a controlled query error according to the approved
  Query Builder handling.
- Public includes and administration includes may differ.
- Includes must not bypass authorization or expose internal fields.
- Deep recursive includes are prohibited.
- Avoid includes that create unbounded nested collections.
- Use separate endpoints when a relationship is large or independently
  paginated.
- Resource methods MUST NOT trigger hidden N+1 queries.

Examples of possible public service includes:

```text
subcategory
subcategory.category
media
specifications
optionGroups.values
```

The exact includes belong in the Services Catalog feature contract.

---

## 26. Date, Time, and Timezone Standards

- Store timestamps in UTC unless the database standard explicitly documents a
  different storage approach.
- Return ISO 8601 UTC strings.

Example:

```json
{
  "createdAt": "2026-07-28T09:30:00Z",
  "updatedAt": "2026-07-28T10:15:42Z",
  "repliedAt": null
}
```

Rules:

- Use `null` for absent timestamps.
- Do not return display-formatted dates as authoritative API values.
- Date filters must define inclusive or exclusive boundaries.
- Order-number date generation uses the configured business timezone.
- API timestamps and the date encoded in an order number are different
  concerns.
- Consumers format dates for display.

---

## 27. Category and Subcategory API Rules

The catalogue hierarchy is:

```text
Category
  -> Subcategory
      -> Services
```

Rules:

- Root categories have no parent.
- Subcategories belong to one root category.
- A subcategory cannot contain another subcategory.
- A category cannot reference itself.
- Circular relationships are invalid.
- Every service belongs to one subcategory.
- The API must prevent direct service assignment to a root category.
- Inactive root categories prevent affected services from being normally
  orderable.
- Inactive subcategories prevent their services from being normally orderable.
- Soft deletion must preserve historical order meaning.
- Public category APIs return only approved active hierarchy data.
- Administration APIs may expose inactive or soft-deleted data through
  explicit authorized filters.

Possible administration routes:

```http
GET    /api/v1/admin/categories
POST   /api/v1/admin/categories
GET    /api/v1/admin/categories/{category}
PATCH  /api/v1/admin/categories/{category}
DELETE /api/v1/admin/categories/{category}

GET    /api/v1/admin/categories/{category}/subcategories
POST   /api/v1/admin/categories/{category}/subcategories
GET    /api/v1/admin/subcategories/{subcategory}
PATCH  /api/v1/admin/subcategories/{subcategory}
DELETE /api/v1/admin/subcategories/{subcategory}
```

Exact routes are controlled by Feature `004 Service Categories and
Subcategories`.

---

## 28. Service Catalogue API Rules

A public service response may expose approved:

- identity
- slug
- name
- descriptions
- category
- subcategory
- publication and availability state where appropriate
- pricing type
- price where applicable
- duration
- images
- one video
- specifications
- order options
- SEO fields

Rules:

- Public list Resources remain lighter than detail Resources.
- Public service detail lookup SHOULD use the service slug.
- Inactive services are excluded from normal public catalogue responses.
- Soft-deleted services are excluded from public responses.
- Orderability is recalculated by the backend.
- The frontend must not decide that a service is orderable from stale
  localStorage data.
- Quote-required services must not expose a fake zero price.
- A missing quote price is represented as `null`.
- Service options returned publicly must exclude values that are not intended
  for selection according to the feature contract.
- Unavailable option values may be omitted or explicitly marked unavailable
  according to the approved public contract, but order submission must reject
  them either way.

---


## 29. Service Order Question API Rules

Services may expose administrator-configured questions that the guest answers
for each proposed order item.

Possible administration routes:

```http
GET    /api/v1/admin/services/{service}/order-questions
POST   /api/v1/admin/services/{service}/order-questions
GET    /api/v1/admin/services/{service}/order-questions/{question}
PATCH  /api/v1/admin/services/{service}/order-questions/{question}
DELETE /api/v1/admin/services/{service}/order-questions/{question}
```

Choice management may use nested routes under the question.

Possible public route:

```http
GET /api/v1/public/services/{service:slug}/order-questions
```

The service detail endpoint may include questions directly instead of requiring
a separate public route.

The feature contract chooses one stable shape.

### 29.1 Administration input

Question content supports Arabic and English:

```json
{
  "questionKey": "required_dimensions",
  "label": {
    "ar": "ما المقاسات المطلوبة؟",
    "en": "What dimensions are required?"
  },
  "helpText": {
    "ar": "أدخل العرض والارتفاع.",
    "en": "Enter the width and height."
  },
  "placeholder": {
    "ar": "مثال: 20 × 30 سم",
    "en": "Example: 20 × 30 cm"
  },
  "inputType": "short_text",
  "isRequired": true,
  "isActive": true,
  "sortOrder": 1,
  "validationConfig": {
    "minLength": 3,
    "maxLength": 100
  }
}
```

Rules:

- stable keys remain untranslated
- both required translations are validated before activation
- validation configuration uses allow-listed keys
- arbitrary Laravel rule strings are prohibited
- file upload is not a question input type in the MVP

### 29.2 Public output

Public output returns resolved localized text:

```json
{
  "id": 15,
  "questionKey": "required_dimensions",
  "label": "ما المقاسات المطلوبة؟",
  "helpText": "أدخل العرض والارتفاع.",
  "placeholder": "مثال: 20 × 30 سم",
  "inputType": "short_text",
  "isRequired": true,
  "choices": []
}
```

Question and choice IDs, keys, types, required state, and validation metadata
remain machine-stable.

### 29.3 Order answers

Every order-item answer must identify the approved question.

Conceptual payload:

```json
{
  "questionId": 15,
  "value": "20 × 30 cm"
}
```

Choice answers use approved choice IDs rather than translated labels.

Rules:

- required active questions must be answered
- answers must match the question type
- answers for another service are rejected
- inactive questions and choices are rejected
- duplicate answers are rejected
- customer-entered answer text is not automatically translated
- answers belong to the item identified by `clientReference`
- snapshots preserve the bilingual question and choice labels

---

## 30. Guest Order Submission API Rules

Guest order creation uses:

```http
POST /api/v1/public/orders
Content-Type: multipart/form-data
```

The request includes approved:

- customer data
- address data
- order items
- service quantities
- selected option values
- selected option quantities
- service-question answers
- selected question choices
- item-specific attachments
- customer notes where approved

### 30.1 Item references

Each submitted item MUST include a request-only client reference.

Conceptual example:

```text
items[0][clientReference] = item-1
items[0][serviceId] = 10
items[0][quantity] = 2
items[0][attachments][] = file-a.pdf

items[1][clientReference] = item-2
items[1][serviceId] = 20
items[1][quantity] = 1
items[1][attachments][] = file-b.webp
```

Rules:

- `clientReference` maps uploaded files to the proposed item.
- It is not persisted as an ownership identifier unless the feature contract
  explicitly requires diagnostic persistence.
- It must be unique within the request.
- It is not a service ID, order-item ID, or database key.
- The backend must reject ambiguous or invalid file mappings.

### 30.2 Authoritative validation

The backend must reload and validate:

- service existence
- root-category eligibility
- subcategory eligibility
- service publication state
- service availability
- service quantity
- pricing type
- current base price
- option-group ownership
- option-value ownership
- option availability
- option selection rules
- option quantities
- active service questions
- required question answers
- answer types and limits
- selected question-choice ownership
- file rules

The backend ignores frontend-calculated totals.

### 30.3 Customer matching

Customer matching uses the normalized phone number.

Rules:

- Normalize before lookup.
- Reuse an existing customer when the approved match succeeds.
- Do not silently overwrite the existing customer record with guest data.
- Preserve submitted customer data in the order snapshot.
- Preserve submitted address data in the order snapshot.

### 30.4 Transaction and file consistency

Order creation must be transaction-safe for database records.

Because filesystem writes do not roll back automatically:

- created paths must be tracked
- failed workflows must remove newly stored files
- cleanup failures must be logged
- success must not be returned with inconsistent state

### 30.5 Success response

The normal success response uses:

```http
201 Created
```

Example:

```json
{
  "success": true,
  "message": "تم إنشاء الطلب بنجاح.",
  "data": {
    "orderNumber": "ORD-260728-5896",
    "status": "awaiting_review",
    "pricingStatus": "requires_review",
    "knownSubtotal": "300.00",
    "totalAmount": null
  }
}
```

Exact returned fields belong in the Orders feature contract.

---

## 31. Quote-Required Pricing API Rules

Quote-required pricing remains inside orders and order items.

The API MUST NOT introduce a separate quotation resource unless the governing
product documentation changes.

A quote-required item may initially return:

```json
{
  "pricingType": "quote_required",
  "pricingStatus": "awaiting_quote",
  "finalUnitPrice": null,
  "lineTotal": null
}
```

An authorized administrator pricing command must:

1. authenticate the administrator
2. authorize pricing permission
3. resolve the order item through its order
4. verify the item requires quote pricing
5. protect against concurrent updates
6. validate the submitted decimal price
7. update the final item price
8. recalculate the line total
9. recalculate the complete order
10. update order pricing status
11. return the updated approved order or item Resource

Possible route:

```http
POST /api/v1/admin/orders/{order}/items/{orderItem}/price
```

Possible errors:

```text
ORDER_ITEM_NOT_QUOTE_REQUIRED
ORDER_ITEM_ALREADY_PRICED
ORDER_PRICING_CONFLICT
ORDER_TERMINAL
```

The exact ability to reprice an already priced item belongs in the Quote Pricing
feature specification.

---

## 32. Order Status API Rules

Approved statuses:

```text
pending
awaiting_review
awaiting_payment
confirmed
in_progress
completed
cancelled
rejected
```

Terminal statuses:

```text
completed
cancelled
rejected
```

Rules:

- Only authorized administrators change order status.
- Status transitions are validated centrally.
- Request consumers cannot supply arbitrary target statuses outside the
  approved transition matrix.
- A transition that requires complete pricing must fail while quote pricing is
  incomplete.
- Terminal orders cannot return to active states in the MVP.
- Cancellation requires a reason.
- Rejection requires a reason when approved by the feature contract.
- The MVP does not expose customer order-status actions.
- The MVP does not maintain a complete order status-history API.

Possible conflict response:

```json
{
  "success": false,
  "message": "لا يمكن نقل الطلب إلى هذه الحالة.",
  "code": "ORDER_STATUS_CONFLICT",
  "errors": null
}
```

---

## 33. Order-Item Attachment API Standards

Initial approved types:

```text
png
jpg
jpeg
webp
pdf
doc
docx
```

Rules:

- Files are uploaded during guest order submission.
- Each attachment belongs to one order item.
- File count, size, MIME, extension, and context must be validated.
- Original filenames are untrusted.
- Stored filenames are generated.
- Metadata is stored in MySQL.
- Public order responses never expose attachment URLs or raw paths.
- Administration attachment output requires authentication and permission.
- Order attachments use the approved configured public disk in the MVP.
- Public-disk storage does not mean attachments are included in public APIs.
- Raw storage paths MUST NOT be returned.
- Server configuration must prevent uploaded files from executing.
- Guest customers cannot upload files after order submission.

When an administration endpoint returns an attachment URL, it must return only
an approved URL or route and never the internal filesystem path.

---

## 34. Contact Us API Rules

Public submission:

```http
POST /api/v1/public/contact-us
```

The endpoint must:

- validate approved fields
- apply rate limiting
- avoid revealing mail infrastructure
- persist the enquiry
- return `201 Created`

Administrator reply:

```http
POST /api/v1/admin/contact-messages/{contactMessage}/reply
```

The endpoint must:

1. authenticate and authorize the administrator
2. validate reply content
3. save the reply
4. update the enquiry state
5. commit the transaction
6. dispatch the email Job after commit
7. return the persisted reply state

The response must distinguish:

```text
saved
queued
delivered
failed
```

only when each state is actually known and persisted.

Dispatching a Job means `queued`, not `delivered`.

Temporary SMTP failure must not roll back the saved reply.

---

## 35. File Upload API Standards

Use `multipart/form-data` for direct uploads unless a later approved
temporary-upload or signed-upload architecture replaces it.

Rules:

- Validate file count.
- Validate individual file size.
- Validate total request size where applicable.
- Validate MIME type.
- Validate allowed extension.
- Reject empty or failed uploads.
- Do not trust original filenames.
- Generate storage names.
- Store approved metadata.
- Do not return raw disk paths.
- Track and clean stored files when a multi-step operation fails.
- File limits must be centralized in configuration and documented by the
  relevant feature.
- Service media and order-item attachments may have different allowed types and
  limits.
- One service video maximum must be enforced.
- One service main image maximum must be enforced.
- No video transcoding or resumable upload behaviour is implied by accepting a
  video.

---

## 36. Rate Limiting Standards

Rate limits are defined by endpoint sensitivity and actor context.

Required candidates:

- administrator login
- public guest order submission
- public Contact Us submission
- abuse-sensitive file uploads

Rules:

- Limits and cooldowns must be configurable.
- Rate-limit state must expire automatically.
- A failure uses:
  - HTTP `429`
  - code `RATE_LIMITED`
- `Retry-After` SHOULD be returned where supported.
- Login rate limiting must not reveal whether an account exists.
- Rate limiting does not replace authentication, authorization, validation, or
  file rules.
- Public read endpoints may use less restrictive limits than public mutation
  endpoints.
- Exact thresholds belong in the related security or feature standard.

---

## 37. Idempotency Standards

Idempotency is not globally required for every MVP endpoint.

It MAY be introduced for retry-prone operations when the feature specification
approves it.

Primary candidate:

```text
POST /api/v1/public/orders
```

Possible header:

```http
Idempotency-Key: <client-generated-key>
```

Before enabling idempotency, the feature must define:

- key scope
- key lifetime
- request fingerprinting
- response replay behaviour
- conflict behaviour when the same key has different input
- storage cleanup
- interaction with multipart files

Do not add idempotency infrastructure to simple CRUD endpoints without a real
need.

---

## 38. Concurrency and Transaction Standards

API commands that can race must be transaction-safe.

Examples:

- quote-required item pricing
- order total recalculation
- order status transitions
- default-address changes
- category or subcategory moves
- featured-service reordering

Rules:

- Use short transactions.
- Use row-level locks when current state controls mutation eligibility.
- Use unique constraints for true invariants.
- Dispatch dependent Jobs after commit.
- Do not send email inside an open transaction.
- Do not expose deadlock, lock-timeout, or SQL details to API consumers.
- A state conflict returns `409` with a stable feature code.
- Retrying a transaction must not duplicate file writes or queued side effects.

---

## 39. Async Operation Standards

Use asynchronous Jobs for work that is:

- slow
- retryable
- dependent on external infrastructure
- unnecessary before the HTTP response

Approved initial use:

- Contact Us reply email delivery

Potential future uses require explicit approval:

- large report generation
- media processing
- external integrations
- imports and exports
- bulk notifications

Rules:

- Database queue is the approved MVP driver.
- Jobs must be retry-safe.
- Jobs should carry scalar identifiers.
- Jobs reload authoritative data.
- Jobs define retry count, backoff, and timeout.
- Failed Jobs remain visible and recoverable.
- API responses must not claim unverified side-effect completion.

---

## 40. CORS Standards

- Allowed origins MUST be explicitly configured by environment.
- Approved origins include the deployed React administration dashboard and
  Next.js public website.
- Production wildcard origins are prohibited for protected administration APIs.
- Allowed methods and headers must match actual API requirements.
- `Authorization`, `Accept`, `Content-Type`, and `Accept-Language` must be
  permitted where required.
- CORS failures must be fixed through configuration.
- Do not disable CORS protections to hide a configuration error.
- Bearer-token authentication does not require weakening origin restrictions.

---

## 41. Caching Standards

No application-level cache is required in the MVP.

API rules:

- Administration responses SHOULD default to private or no shared caching.
- Sensitive responses MUST NOT be publicly cached.
- Login and token-bearing responses MUST use appropriate no-store behaviour.
- Guest order responses MUST NOT be publicly cached.
- Public catalogue HTTP caching may be introduced only with an explicit
  invalidation and freshness strategy.
- Cache behaviour must not expose inactive catalogue content after an
  administrator change.
- The absence of Redis does not prevent correct API behaviour.
- Do not add cache keys or cache tags without an approved cache design.

---

## 42. Logging Standards for APIs

API logs SHOULD include approved operational context such as:

```text
request_id
route
method
status
duration_ms
authenticated_user_id
order_id or order_number when relevant
customer_id when operationally necessary
service_id when relevant
job_id when relevant
```

Rules:

- Do not log passwords.
- Do not log access tokens.
- Do not log raw `Authorization` headers.
- Do not log SMTP credentials.
- Do not log attachment contents.
- Do not log full sensitive request bodies.
- Do not log unnecessary customer personal data.
- Validation failures may be logged without sensitive values.
- Unexpected failures must be correlated with a request ID.
- API responses never include internal log details.
- Public guest order logs should prefer internal IDs and order number over full
  customer payloads.

---

## 43. Localization Standards

Supported locales:

```text
ar
en
```

Arabic is the initial default locale.

English is the fallback locale.

Rules:

- `Accept-Language` selects the supported locale.
- Regional values may normalize to `ar` or `en`.
- Missing or unsupported values use the configured fallback chain.
- Locale resolution occurs before validation.
- User-facing messages are translated.
- Public catalogue and approved site content are resolved to the request
  locale.
- Administration mutation APIs accept approved Arabic and English fields.
- Active public content must satisfy feature-defined bilingual completeness.
- Customer-entered content is not automatically translated.
- Orders and Contact Us enquiries preserve their resolved locale when required.
- Queued email work preserves or reloads the intended locale.
- The following are never translated:
  - error codes
  - field names
  - route paths
  - enum values
  - role and permission identifiers
  - slugs
  - question and option keys
  - database columns
- Payload structure does not change by locale.
- Do not hard-code repeated Arabic or English messages.
- Translation keys belong in language files.
- Responses may include `Content-Language`.
- Locale-dependent cache responses use `Vary: Accept-Language`.

---

## 44. OpenAPI Documentation

Each implemented endpoint SHOULD be represented in the project OpenAPI contract
when API documentation tooling is adopted.

Document:

- method and path
- API area
- authentication requirement
- permission requirement
- request headers
- path parameters
- query parameters
- request body
- multipart field structure
- validation rules
- success response
- error responses and codes
- pagination
- filters
- sorting
- includes
- file rules
- localization behaviour

OpenAPI documentation MUST reflect actual tested behaviour.

Do not document speculative endpoints as implemented.

---

## 45. Testing Requirements

Every endpoint requires applicable automated tests.

### 45.1 Common endpoint tests

- correct success status
- correct response envelope
- correct `camelCase` fields
- localized Arabic or English message
- stable English error code
- validation failure
- unauthenticated request where protected
- unauthorized request where protected
- resource not found
- no sensitive field exposure
- unsupported or protected mutation fields

### 45.2 List endpoint tests

- pagination
- maximum page size
- filters
- sorting
- search
- includes
- public or admin visibility
- inactive and soft-deleted behaviour
- N+1 prevention where practical

### 45.3 Category and subcategory tests

- root category creation
- subcategory creation under a root category
- third-level category rejection
- self-parent rejection
- circular relationship rejection
- service assignment only to a subcategory
- category filter returns services from child subcategories
- subcategory filter returns directly assigned services
- inactive root category affects public orderability
- inactive subcategory affects public orderability

### 45.4 Authentication tests

- valid credentials
- invalid credentials
- generic failure message
- inactive administrator
- login rate limiting
- unauthenticated protected endpoint
- logout revokes current token
- customer records cannot authenticate

### 45.5 Guest order tests

- valid multipart submission
- multiple order items
- attachment mapping by client reference
- invalid client-reference mapping
- inactive category rejection
- inactive subcategory rejection
- inactive service rejection
- unavailable service rejection
- missing required service answer rejection
- invalid answer type rejection
- invalid question ownership rejection
- invalid question-choice ownership rejection
- unavailable option rejection
- invalid option ownership
- price tampering does not control totals
- customer matching by normalized phone
- existing customer is not silently overwritten
- customer and address snapshots
- service and option snapshots
- question, choice, and answer snapshots
- mixed priced and quote-required items
- incomplete final total
- file cleanup after failed creation
- unique order number response

### 45.6 Order administration tests

- permission enforcement
- quote-required pricing
- order recalculation
- invalid pricing state
- valid status transition
- invalid status transition
- cancellation reason required
- terminal-state protection
- attachment visibility restricted to administration APIs

### 45.7 Contact reply tests

- public enquiry creation
- public submission rate limiting
- administrator reply authorization
- reply persistence
- status update
- Job dispatched after commit
- queued state is not represented as delivered
- email failure does not roll back the reply

Tests must verify observable API behaviour, not merely implementation details.

---

## 46. API Review Checklist

- [ ] Endpoint is covered by an approved feature specification.
- [ ] Route is versioned.
- [ ] Route belongs to `auth`, `admin`, or `public`.
- [ ] HTTP method is correct.
- [ ] HTTP status is selected through the approved enum.
- [ ] Request and response keys use `camelCase`.
- [ ] Mutation uses a dedicated Form Request.
- [ ] Authentication is enforced where required.
- [ ] Permission and resource authorization are enforced.
- [ ] Public guest requests are not treated as authenticated customer actions.
- [ ] Protected fields cannot be supplied or overridden by request input.
- [ ] Output uses an explicit Resource or transformer.
- [ ] Response uses the global envelope.
- [ ] Arabic or English message matches the resolved locale.
- [ ] English error code remains stable.
- [ ] Money is returned as a decimal string.
- [ ] Timestamps use ISO 8601 UTC.
- [ ] List endpoints paginate where required.
- [ ] Filters, sorts, includes, and search are allow-listed.
- [ ] Category and subcategory eligibility is enforced where relevant.
- [ ] Service and option eligibility is revalidated.
- [ ] Frontend prices and totals are ignored.
- [ ] Order snapshots preserve historical values.
- [ ] Multipart files map to the correct order item.
- [ ] Raw storage paths are not exposed.
- [ ] Transactions or locks protect relevant races.
- [ ] Queued email is not reported as delivered.
- [ ] Rate limiting is applied where required.
- [ ] Tests cover success, validation, authorization, state conflicts, and
      response shape.
- [ ] OpenAPI or the active feature contract is updated.

---

## 47. Non-Negotiable Rules

- No unversioned product endpoints.
- No customer authentication APIs in the MVP.
- No customer order-history or tracking endpoints in the MVP.
- No raw Eloquent models in responses.
- No inconsistent JSON envelopes.
- No mixed `snake_case` and `camelCase` payloads.
- No translated machine-readable error codes.
- No raw numeric HTTP status literals when the approved enum applies.
- No backend authorization delegated to React or Next.js.
- No frontend-provided authoritative price or total.
- No service ordering without backend category, subcategory, service, option,
  question, answer, and choice validation.
- No missing required service-question answers.
- No translated labels used as ownership identifiers.
- No direct service relationship to a root category.
- No third category level.
- No arbitrary filters, sorts, includes, fields, or database column names.
- No unbounded operational lists.
- No separate quotation domain in the MVP.
- No guest file upload after order submission.
- No raw internal storage path in API responses.
- No claim that queued email has been delivered.
- No sensitive exception details in responses.
- No implementation guess when an approved feature specification is ambiguous.

## 48. Administrator Separate-Domain Direct API Contract

When the Admin Frontend and Laravel Backend use different physical origins,
the approved Admin browser API base may be the absolute Laravel API origin,
such as `https://api.backend-example.net/api/v1`. Authenticated browser code
MAY call the Laravel origin directly.

Rules:

- direct browser requests use HTTPS only
- exact allow-listed CORS is mandatory
- `Access-Control-Allow-Credentials` remains disabled for this flow
- Bearer access tokens are sent in `Authorization`
- refresh tokens are sent only in the documented JSON request body
- no same-origin proxy, BFF, Cloudflare Worker, or cookie-forwarding layer is
  required by the API standard
- browser-public frontend configuration may expose the approved backend API
  origin for this contract
