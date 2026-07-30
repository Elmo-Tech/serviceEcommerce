# AGENTS.md

## Purpose

This file defines mandatory working instructions for Codex and every coding
agent operating in this repository.

The repository contains a production-oriented, backend-only service-commerce
platform built with:

- Laravel 13
- a Laravel 13-compatible PHP version pinned by the repository
- MySQL
- Laravel Sanctum personal access tokens
- `spatie/laravel-permission`
- `spatie/laravel-query-builder`
- Pest on Laravel's PHPUnit-compatible testing foundation
- Laravel database queues for approved asynchronous email work

The backend exposes versioned RESTful JSON APIs for:

- an external React administration dashboard
- an external Next.js public website

The React and Next.js applications are external API consumers and are outside
this repository's implementation scope.

All changes MUST follow the project constitution, approved project
documentation, active feature specifications, implementation plans, task files,
and the rules in this document.

---

## 1. Instruction Precedence

For repository artifacts and implementation decisions, follow this order:

1. `.specify/memory/constitution.md`.
2. This `AGENTS.md` for repository workflow, operational safety, and agent
   behaviour.
3. `docs/00-project-overview/project-overview.md`.
4. `docs/01-architecture/backend-architecture.md`.
5. Applicable approved files under `docs/02-standards/`.
6. Approved feature-specific Service Commerce business rules under
   `docs/features/`.
7. The active implementation specification.
8. The active feature's `plan.md`.
9. The active feature's `tasks.md`.
10. Existing implementation, only when it does not conflict with an approved
   artifact.

A feature-specific business rule MAY specialize a higher-level standard only
where that standard intentionally leaves room for feature-specific
configuration. A feature document MUST NOT weaken, bypass, or contradict this
constitution, backend architecture, or any applicable shared cross-cutting
standard.

A general repository workflow rule in this `AGENTS.md` MUST NOT silently
replace a specific approved feature business decision. If a feature genuinely
requires an exception to a higher-level rule, planning and implementation MUST
stop until the conflict is reported, the user explicitly approves the
exception, and the affected governing files are amended and synchronized.

The user's current request defines the task, but it does not silently amend or
override an approved constitution or governing product rule. Laravel defaults,
package documentation, and existing conventions may fill implementation
details only when the precedence list above does not already decide them.

The agent MUST NOT silently choose an interpretation that changes approved
business behaviour.

When the user requests a constitutional, product, architectural, API, database,
or workflow change, the agent MUST update the governing documentation before
implementing code that depends on that change.

When a request conflicts with approved documentation, the agent MUST identify
the conflict and update the relevant documentation before implementing the new
behaviour, unless the user explicitly limits the task to analysis only.

---

## 2. Mandatory Session Startup

Before editing code, the agent MUST:

1. Identify the repository root and current working directory.
2. Read the root `AGENTS.md`.
3. Read any nearer `AGENTS.md` or `AGENTS.override.md` that applies to the
   target files.
4. Read `.specify/memory/constitution.md`.
5. Read:
   - `docs/00-project-overview/project-overview.md`
   - `docs/01-architecture/backend-architecture.md`
6. Read the applicable files under `docs/02-standards/` when they exist.
7. Read the active feature's `spec.md`, `plan.md`, and `tasks.md`.
8. Inspect `README.md`, `composer.json`, `composer.lock`, `.env.example`, and
   relevant configuration files.
9. Inspect the existing code near the requested change before introducing a new
   pattern.
10. Check Git status and preserve all existing user changes.
11. Determine the narrowest safe implementation scope.
12. Confirm the actual installed package versions before using version-specific
    APIs.

The agent MUST NOT begin implementation from assumptions when the repository
already contains the answer.

The agent MUST NOT rewrite working project structure merely to match a generic
Laravel example.

---

## 3. Laravel Setup and Official Guidance

### 3.1 Material setup or reconfiguration

For any session that installs, initializes, upgrades, or materially
reconfigures Laravel, PHP dependencies, the Laravel application structure, or
core development tooling, the agent MUST fetch and read:

```text
https://laravel.com/for/agents
```

The retrieved official guidance is the source of truth for setup during that
session.

The agent MUST:

- read the complete returned guidance before setup commands
- check prerequisites
- follow supported installation and configuration steps
- surface any required terminal restart or environment change
- re-fetch the guidance in a later session when another material setup or
  reconfiguration is requested

### 3.2 Existing application rule

If the repository already contains a Laravel application, the agent MUST NOT
reinstall Laravel or replace the application skeleton.

Evidence includes:

```text
artisan
composer.json requiring laravel/framework
bootstrap/app.php
app/
routes/
```

The existing application must be inspected and modified in place.

### 3.3 Guidance fetch failure

If the official setup guidance cannot be fetched:

- state that it could not be retrieved
- do not invent its contents
- continue only when the task can be completed safely from the existing
  repository, lock files, and approved project documentation
- do not perform a fresh Laravel installation or major framework
  reconfiguration without equivalent explicit user instructions

### 3.4 Dependency versions

The agent MUST verify package versions from `composer.lock` or another relevant
lock file.

The agent MUST NOT assume package APIs from memory when the installed version is
available locally.

---

## 4. Spec-Driven Development Workflow

Implementation MUST follow this sequence:

1. Constitution
2. Project overview and architecture
3. Feature specification
4. Clarification, when genuinely required
5. Implementation plan
6. Task breakdown
7. Implementation
8. Automated verification
9. Documentation and task-status updates
10. Final review

No production feature SHOULD be implemented before the behaviour is represented
in an approved feature specification.

Each implementation change MUST be traceable to at least one of:

- user story
- functional requirement
- business rule
- acceptance criterion
- approved architecture rule
- explicit user-requested defect fix

The agent MUST NOT add:

- speculative features
- hidden requirements
- frontend features
- unrelated refactors
- infrastructure that is only theoretically useful
- unapproved abstractions

When implementation reveals missing behaviour, the specification, plan, and
tasks must be updated before the feature is treated as complete.

---

## 5. Stable Product and Domain Rules

Unless a later approved specification explicitly changes them, these rules are
authoritative.

### 5.1 Repository boundary

- This repository is backend-only.
- The React administration dashboard is outside this repository.
- The Next.js public website is outside this repository.
- Do not generate React, Next.js, frontend routes, frontend components,
  localStorage code, or frontend tests in this repository.
- Laravel is the authoritative source for validation, authorization, pricing,
  availability, order state, and persisted data.

### 5.2 System type

- The application is a single-business platform.
- It is not a multi-tenant SaaS platform.
- It uses one Laravel application and one primary MySQL database.
- Do not introduce tenant databases, tenant middleware, tenant IDs, or
  multi-tenant packages without an approved architectural change.

### 5.3 Users and authentication

- The Super Admin is the only authenticated MVP role.
- Authenticated identities are stored in `users`.
- `users.type = 0` represents an administrator.
- `users.type` is not a replacement for roles and permissions.
- Laravel Sanctum personal access tokens secure administration APIs.
- Administrator authentication uses only `/api/v1/admin/auth/*`.
- The Access Token is returned in JSON, expires after 15 minutes, and is stored
  by React in memory only.
- The Refresh Token is stored in an HttpOnly, Secure, host-only cookie and is
  never returned in JSON.
- Refresh tokens use `refresh_tokens`; password recovery uses
  `password_resets`.
- Authentication profile output contains only `name`, `email`, `avatar`,
  `role`, and `permissions`. It does not return a user ID. `role` is one
  string.
- Password-recovery email is synchronous. Do not create authentication Queue
  Jobs, cleanup Commands, Cron tasks, or scheduler entries.
- Customers do not authenticate in the MVP.
- Do not create customer registration, login, password-reset, profile, or
  customer-token endpoints.

### 5.4 Roles and permissions

- The initial approved role is `super-admin`.
- `spatie/laravel-permission` is the authoritative roles and permissions
  mechanism.
- The architecture must remain ready for future roles without adding
  speculative MVP roles.
- Authorization must be enforced on the backend.
- UI visibility is never an authorization control.

### 5.5 Customers

- Customers are stored in `customers`, separately from `users`.
- A customer record does not grant system access.
- Guest checkout matches customers by normalized phone number.
- Raw phone formatting must not be used as the matching key.
- Guest input must not silently overwrite an existing administrator-managed
  customer record.
- Submitted customer details must be preserved in the order snapshot.
- Customers may have multiple addresses.
- A customer may have only one default address.
- Customer and address records are managed by administrators.

### 5.6 Categories and Subcategories

- The catalogue hierarchy is Category -> Subcategory -> Services.
- Root categories have no parent.
- Every subcategory belongs to exactly one root category.
- A subcategory cannot contain another subcategory.
- Third-level and circular hierarchy relationships are prohibited.
- A service may be unclassified, assigned to one root category only, or
  assigned to one root category and one subcategory.
- A service may never belong to a subcategory without also belonging to that
  subcategory's parent root category.
- Store the root category relationship directly on the service and keep the
  subcategory relationship nullable for services that stop at the root level.
- Public category or subcategory visibility and filtering must respect the
  active, non-deleted hierarchy records that are still valid for the service.

### 5.7 Services

A service may contain:

- Arabic and English names and descriptions
- one stable slug
- one subcategory
- publication status
- availability status
- pricing data
- fixed or ranged duration
- one main image
- multiple additional images
- one uploaded video maximum
- bilingual display-only specifications
- bilingual configurable order options
- bilingual required or optional order questions
- Arabic and English SEO content

Approved publication statuses:

```text
active
inactive
```

Approved availability statuses:

```text
available
unavailable
```

Publication and availability are separate concepts.

Approved pricing types:

```text
fixed
starting_from
quote_required
```

Approved duration types:

```text
fixed
range
```

Approved duration units:

```text
hour
day
week
```

### 5.8 Specifications and order options

- Service specifications are display-only bilingual label-value pairs.
- Service specifications are not customer selections.
- Service order options are customer-selectable bilingual values.
- Option values may be active yet temporarily unavailable.
- Unavailable option values must be rejected by the backend.
- A selected option value may have a quantity when approved.
- Options may affect service configuration and pricing.
- The backend validates every submitted option against the service.
- The frontend does not provide authoritative labels, availability, or prices.

### 5.9 Service order questions

- Administrators may define questions for each service from the dashboard.
- Questions may be required or optional.
- Supported types are approved by the feature specification and may include
  text, number, date, boolean, single-choice, and multiple-choice input.
- Question labels, help text, placeholders, and choices support Arabic and
  English.
- Stable question and option keys remain untranslated.
- Required answers are enforced by the backend.
- Answers belong to individual order items.
- Questions collect customer information and remain separate from service
  options.
- Customer answers are not automatically translated.
- Question, choice, and answer snapshots preserve historical order meaning.
- Arbitrary executable validation rules from the dashboard are prohibited.

### 5.10 Cart boundary

- The guest cart exists only in the Next.js frontend localStorage.
- The backend does not persist carts.
- Do not create cart tables, cart sessions, abandoned-cart tracking, cart
  merging, or cart-recovery APIs.
- The complete proposed cart must be revalidated during order submission.
- Active questions, required answers, and selected question choices must be
  revalidated.
- Frontend prices, totals, localized labels, and eligibility are untrusted
  input.

### 5.11 Orders

- Guests may submit orders without authentication.
- One order may contain fixed-price, starting-from, and quote-required services
  together.
- A service may be ordered with a quantity greater than one.
- Customer-uploaded files belong to individual order items.
- Service-question answers belong to individual order items.
- Orders preserve the resolved locale when required.
- Customers cannot edit, cancel, track, or view orders through a customer
  portal in the MVP.
- Only authorized administrators may update order state.
- Order cancellation requires a reason.
- An authorized administrator may cancel an `in_progress` order when business
  rules allow it, but the reason remains mandatory.

Approved order statuses:

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

Terminal states:

```text
completed
cancelled
rejected
```

Terminal orders cannot return to an active state in the MVP.

### 5.12 Quote-required pricing

- Quote-required pricing is handled inside `orders` and `order_items`.
- Do not create a separate quotation lifecycle or quote-to-order conversion.
- Do not introduce `quotes`, `quote_items`, quote versions, quote expiration, or
  customer quote approval without an approved product change.
- A quote-required order item may initially have no final price.
- An order with incomplete pricing may have a null final total.
- The backend may preserve a known subtotal for determinable items.
- When all required prices are set, the backend recalculates the final order
  total.

### 5.13 Order snapshots

Historical order meaning must not depend only on mutable catalogue records.

Order and order-item snapshots must preserve approved order-time data,
including where applicable:

- customer name
- email
- phone
- address
- service name
- service slug or reference
- pricing type
- base price
- final price
- quantity
- selected option labels and values
- selected option quantities
- question keys and Arabic and English labels
- question input types and required state
- selected question-choice labels
- customer answers
- duration values
- duration unit
- line total

Changing or deleting catalogue data must not rewrite historical snapshots.

### 5.14 Order number

Orders use:

```text
ORD-YYMMDD-NNNN
```

Example:

```text
ORD-260507-5896
```

Rules:

- internal primary keys remain auto-incrementing unsigned big integers
- `order_number` has a unique database index
- the four-digit suffix uses secure random generation
- generation retries on a unique-key collision
- retry count is bounded
- generation uses the configured business timezone

### 5.15 Files

- Service media is uploaded to the backend.
- A service may have multiple images.
- A service may have one video maximum.
- The MVP does not include video transcoding, compression, streaming, or
  automatic thumbnail generation.
- Guest order files are submitted during order creation.
- Guest order submission uses one multipart request.
- Temporary upload tokens and resumable uploads are outside the MVP.
- The initial approved order attachment types are PNG, JPEG, WebP, PDF, DOC,
  and DOCX.
- File extension alone is never sufficient validation.

### 5.16 Payments

- Online payment is outside the application.
- Do not add payment gateways, payment webhooks, taxes, currencies, invoices,
  refunds, or payment-provider reconciliation.
- `awaiting_payment` is an administrative workflow state.
- Payment confirmation occurs outside the application and is reflected by an
  authorized administrator.

### 5.17 Site content

The backend manages approved APIs for:

- site settings
- logo
- favicon
- address
- Google Maps value
- phone numbers
- contact emails
- social links
- fixed or slider hero content
- testimonials
- FAQs
- featured services
- best-selling service data
- Contact Us inbox

FAQs have no categories in the MVP.

Testimonials are created by administrators only.

Featured services are selected manually.

Best-selling services are calculated from completed order data.

### 5.18 Contact replies

- Administrators may reply to Contact Us messages.
- The reply is persisted before email delivery.
- A real email is sent through a queued Job.
- The database queue is the approved MVP queue driver.
- The Job must be dispatched after the relevant transaction commits.
- Email failure must not roll back an already saved reply.

### 5.19 Localization

- Arabic and English are active supported locales.
- Arabic is the initial default and English is the fallback.
- Frontend consumers send `Accept-Language`.
- Public catalogue and approved site content are bilingual.
- Public Resources return the resolved locale.
- Administration Resources may return both translations for editing.
- JSON keys, request fields, response fields, route paths, enum values,
  database columns, roles, permissions, stable keys, and error codes remain in
  English.
- Customer-entered content is not automatically translated.
- Do not translate stable machine identifiers.

---

## 6. Approved Laravel Architecture

### 6.1 Application style

- The application MUST remain an API-first conventional Laravel monolith.
- API version 1 is exposed under `/api/v1/...`.
- Approved top-level route groups are:

```text
/api/v1/admin/auth/*
/api/v1/admin/*
/api/v1/public/*
```

- Do not introduce Inertia.js.
- Do not introduce `app/Modules`.
- Do not introduce modular-monolith boundaries.
- Do not introduce Domain/Application/Infrastructure folder layers.
- Do not introduce microservices.
- Do not introduce repository interfaces over every Eloquent model.
- Do not introduce CQRS or event sourcing without an approved architectural
  decision.

### 6.2 Controllers

Controllers MUST remain thin.

Controllers SHOULD:

- accept dedicated validated Form Requests
- authorize the operation
- resolve route-bound resources
- call an Action when complex use-case orchestration is required
- call a focused Service when reusable business behaviour is required directly
- perform simple Eloquent CRUD directly when no additional layer is justified
- return API Resources or approved response objects

Controllers MUST NOT contain:

- long multi-step workflows
- reusable pricing rules
- status-transition maps
- customer-matching algorithms
- large Eloquent queries
- direct mail-delivery orchestration
- nested file-storage implementation details
- manual reusable validation arrays

### 6.3 Form Requests

Write operations MUST use dedicated Form Request classes.

Form Requests MUST:

- validate according to the approved feature specification
- authorize when appropriate
- validate nested order and option structures
- validate file count, extension, MIME type, and size
- normalize safe input deliberately
- use PHP enums for approved state values
- provide Arabic and English validation messages through translation files
- validate bilingual required content before publication where approved
- reject mutation fields not included in the approved contract

Phone normalization should use a focused reusable capability rather than
scattered string manipulation.

### 6.4 Actions: complex use-case orchestration

Actions are used for named application operations with meaningful
orchestration.

An Action is appropriate when the operation:

- coordinates multiple models or capabilities
- contains multiple ordered business steps
- requires a database transaction
- performs file-storage compensation
- applies workflow or state-transition rules
- dispatches queued or external side effects after commit
- needs one clear success or failure outcome

Examples:

```text
CreateGuestOrderAction
PriceQuoteRequiredOrderItemAction
ChangeOrderStatusAction
CancelOrderAction
ReplyToContactMessageAction
CreateServiceAction
UpdateServiceAction
```

Actions SHOULD:

- represent one use case
- remain transport-independent
- delegate reusable calculations and rules to focused Services
- return explicit domain or application results

Actions MUST NOT be created automatically for every endpoint.

### 6.5 Services: reusable business capabilities

Services are used for focused reusable business behaviour that may be consumed
by multiple Actions, Jobs, commands, or workflows.

Examples:

```text
PhoneNormalizer
OrderNumberGenerator
OrderPricingService
OrderStatusTransitionService
OrderAttachmentStorageService
ServiceMediaStorageService
ApiLocaleResolver
```

A Service SHOULD:

- own one cohesive capability
- avoid HTTP request and response dependencies
- remain independently testable
- avoid unrelated methods
- avoid duplicating Action orchestration

A Service MUST NOT exist only to forward validated data to an Eloquent model.

### 6.6 Simple CRUD: no unnecessary layer

Simple CRUD does not require an Action or Service when the operation only:

- uses validated request data
- creates, updates, views, activates, deactivates, or deletes one model
- has no multi-model workflow
- requires no transaction orchestration
- has no complex file handling
- triggers no external side effect
- contains no reusable business rule
- contains no meaningful state-transition logic

In those cases, the controller may perform the small Eloquent operation directly
and return an API Resource.

Possible examples:

```text
FAQs
Testimonials
Contact phone numbers
Contact emails
Social links
```

The following pass-through design is prohibited when it adds no behaviour:

```text
Controller
  -> CreateFaqAction
      -> FaqService
          -> Faq::create(...)
```

The architecture rule is:

```text
Actions = orchestration of complex use cases
Services = reusable business capabilities
Simple CRUD = no unnecessary layer
```

### 6.7 Query classes

Use Query classes selectively for complex read operations.

Examples:

```text
ListPublicServicesQuery
ListAdminServicesQuery
ListAdminOrdersQuery
ListCustomersQuery
DashboardMetricsQuery
BestSellingServicesQuery
```

A Query class is appropriate when it centralizes:

- approved filters
- approved sorts
- approved includes
- actor-specific visibility
- complex eager loading
- aggregates
- dashboard calculations

Simple relationship retrieval and simple single-model CRUD do not require a
Query class.

### 6.8 Models

Models SHOULD define:

- relationships
- casts
- attributes
- backed enum casts
- focused query scopes
- small predicates tied directly to model state

Models MUST NOT become:

- controllers
- workflow coordinators
- mail dispatchers
- complete order-creation services
- generic containers for unrelated business logic

Mass assignment must be guarded deliberately.

### 6.9 Enums

Use backed PHP enums for stable domain values.

Do not use native MySQL ENUM columns.

Do not compare important states through scattered string literals.

Centralize states such as:

- service publication
- service availability
- pricing type
- duration type
- duration unit
- order status
- pricing status
- contact-message status
- media type

### 6.10 Policies and permissions

- Sanctum authentication, Spatie permissions, Policies, scoped queries, and
  business-state checks are separate controls.
- Permission checks do not replace resource or workflow checks.
- Policies may be used where resource-level authorization improves clarity.
- Do not create Policies for public catalogue reads that require no
  authenticated actor.
- Jobs and commands must not bypass business authorization when acting on
  behalf of a user.

---

## 7. Database and Persistence Rules

- Use MySQL-compatible migrations.
- Use auto-incrementing unsigned big integer primary keys unless approved
  otherwise.
- Use foreign keys with intentional delete and update behaviour.
- Use unique constraints for true invariants.
- Use indexes for foreign keys, status filters, lookup fields, sorting, and
  high-value search paths.
- Use nullable columns only when null is a valid domain state.
- Store money with fixed-precision decimal or approved integer minor units.
- Never store money as floating point.
- Store timestamps consistently according to the approved timezone standard.
- Migrations should be reversible when reasonably possible.
- Do not edit already-deployed migrations to change production behaviour.
- Do not rewrite or delete production data without an explicit migration
  strategy and user approval.
- Do not use native MySQL ENUM.
- Prefer normalized relational tables for service options and option values.
- Use JSON only when the data is genuinely document-like and not required for
  relational validation, filtering, ordering, reporting, or integrity.
- Preserve immutable order snapshots.
- Do not physically delete orders or order items through normal application
  flows.
- Services, categories, and customers referenced by historical orders must
  follow the approved soft-delete or retention strategy.

### 7.1 Required integrity examples

Where approved by the feature specification, enforce:

- unique `orders.order_number`
- indexed normalized customer phone
- one main image per service
- one video maximum per service
- one default address per customer
- valid option group and option value relationships
- valid order-to-item and item-to-attachment relationships

Application validation does not replace database constraints for true
invariants.

### 7.2 Query quality

- Prevent N+1 queries through deliberate eager loading.
- Do not load unbounded collections into memory.
- Use server-side pagination for list endpoints.
- Select only required columns for expensive queries when useful.
- Allow-list filters, sorts, and includes.
- Use row locks, atomic updates, or unique constraints where concurrent updates
  can create invalid state.

---

## 8. API Rules

### 8.1 Route groups

Use only the approved top-level groups:

```text
/api/v1/admin/auth/*
/api/v1/admin/*
/api/v1/public/*
```

Do not create unversioned product endpoints.

### 8.2 Request and response conventions

- API request and response keys use `camelCase`.
- Database columns use `snake_case`.
- Use correct HTTP methods and status codes.
- Application-controlled statuses use the shared
  `App\Enums\HttpStatusCode` enum unless an approved architecture decision
  replaces it.
- Use API Resources or explicit response transformers.
- Keep response envelopes consistent.
- Keep machine-readable error codes stable and in English.
- Return Arabic or English user-facing messages according to locale resolution.
- Return resolved localized public content without changing machine keys.
- Do not expose internal filesystem paths, secrets, tokens, or stack traces.
- Do not expose administrator-only fields in public Resources.

### 8.3 Query parameters

`spatie/laravel-query-builder` must use explicit allow-lists for:

- filters
- sorts
- includes

The agent MUST NOT pass request-provided column names directly into database
queries.

### 8.4 Public order submission

Guest order creation uses:

```http
POST /api/v1/public/orders
Content-Type: multipart/form-data
```

The request may include:

- customer data
- address data
- multiple items
- service quantities
- selected options
- selected option quantities
- service-question answers
- selected question choices
- item-specific attachments

Every proposed item should use an approved request-only client reference to map
its uploaded files.

That reference:

- is not a database identifier
- is not an ownership value
- must be unique within the request
- must not be trusted outside request mapping

The backend must reload and revalidate all catalogue data.

---

## 9. Pricing and Order Integrity

### 9.1 Backend authority

The backend is authoritative for:

- pricing type
- current base price
- option price adjustments
- quantity
- line total
- known subtotal
- final total
- quote-required pricing completion

Frontend-provided prices and totals must be ignored or rejected according to the
API contract.

### 9.2 Quote-required items

A quote-required item may begin with:

```text
finalUnitPrice = null
lineTotal = null
pricingStatus = awaiting_quote
```

When the administrator provides a price:

- lock the required order and item rows when needed
- validate the item is eligible for pricing
- update approved final-pricing fields
- recalculate the line total
- recalculate the entire order
- update the order pricing state
- prevent a status transition that requires complete pricing while pricing
  remains incomplete

### 9.3 Transactions

Use `DB::transaction()` when a business action must succeed or fail as one
unit.

Required candidates include:

- guest order creation
- order-item quote pricing and order-total recalculation
- default-address switching
- service creation or update with related records
- contact-reply persistence and status update
- ordered featured-service updates

Rules:

- keep transactions short
- validate as much as possible before opening the transaction
- do not send email inside a transaction
- do not make external HTTP calls inside a transaction
- dispatch dependent Jobs after commit
- do not catch and suppress transaction failures

### 9.4 Filesystem compensation

Database rollback does not roll back stored files.

A workflow that stores files before completion MUST:

- track every created path
- delete created files when the database operation fails
- log cleanup failures safely
- avoid returning success with inconsistent database or filesystem state

---

## 10. File and Storage Rules

### 10.1 General validation

Every upload must validate:

- successful upload
- approved extension
- approved MIME type
- maximum file size
- maximum file count
- maximum request size where applicable
- correct service or order-item context

Do not trust:

- original filename
- extension alone
- browser-provided content type alone
- frontend restrictions

Generate stored filenames independently from the original filename.

Preserve the validated original filename only as metadata when needed.

### 10.2 Service media

- Service images and video use the approved configured disk.
- One service has one main image maximum.
- One service has one video maximum.
- Additional images use explicit sorting.
- Replacing or deleting media must follow the approved cleanup behaviour.
- Do not introduce video processing infrastructure in the MVP.

### 10.3 Order-item attachments

The current approved MVP uses the configured public filesystem disk for
order-item attachments.

Because this does not provide private authorization by itself, the agent MUST:

- use non-predictable generated names
- use non-user-controlled directories
- prevent directory listing
- ensure uploaded files cannot execute on the server
- never return raw paths or attachment URLs in guest order responses
- return attachment metadata or URLs only through authorized administration
  APIs

The agent MUST NOT silently change the approved public-storage model without
updating architecture and feature documentation.

---

## 11. Queue, Jobs, and Email Rules

The approved MVP queue driver is:

```env
QUEUE_CONNECTION=database
```

The initial approved asynchronous use case is Contact Us reply email delivery.

### 11.1 Contact reply flow

1. authorize the administrator
2. validate the reply
3. save the reply
4. update the contact-message state
5. commit the database transaction
6. dispatch `SendContactReplyMailJob` after commit
7. send the real email through the queue worker
8. preserve failed Jobs for inspection and retry

### 11.2 Job requirements

Jobs MUST:

- be retry-safe
- carry stable scalar identifiers rather than large serialized model graphs
- reload authoritative records when executing
- define appropriate retry count, backoff, and timeout
- avoid duplicate effects when retried
- expose failures through Laravel failed-job handling
- avoid claiming email delivery merely because the Job was dispatched

The database record is the authoritative saved reply.

Email failure must not delete or roll back that reply.

Redis and Horizon are outside the MVP.

---

## 12. Localization Rules

- API locale resolution reads `Accept-Language`.
- Supported locales are `ar` and `en`.
- Arabic is the configured initial default.
- English is the configured fallback.
- Locale must be resolved before validation errors are rendered.
- Public catalogue content, service questions, choices, and approved site
  content support both languages.
- Public Resources return resolved localized values.
- Administration Resources may return both translations for editing.
- Translation files contain user-facing system messages.
- Do not hard-code repeated Arabic or English messages.
- Do not translate error codes, enum values, permission identifiers, route
  paths, JSON keys, slugs, question keys, or option keys.
- Do not automatically translate customer-entered content.
- Queued Jobs preserve or reload the intended locale.
- Responses may include `Content-Language` and locale-aware cache responses use
  `Vary: Accept-Language`.

---

## 13. Security and Privacy

- Authentication and authorization are separate requirements.
- Enforce both on every protected backend operation.
- Never trust user IDs, customer IDs, service IDs, category IDs,
  subcategory IDs, question IDs, choice IDs, role names, statuses, prices,
  totals, localized labels, option availability, or ownership claims from API
  consumers without validation.
- Do not log passwords, access tokens, secrets, raw authorization headers,
  uploaded file contents, or unnecessary personal data.
- Keep secrets in environment variables.
- Apply rate limiting to:
  - login
  - public order creation
  - Contact Us submission
  - abuse-sensitive uploads
- Use generic authentication failure messages.
- Inactive administrators must not use protected APIs.
- Prevent mass assignment of protected fields.
- Production errors must not expose SQL, stack traces, class paths, or
  filesystem paths.
- Public endpoints must fail closed when catalogue eligibility cannot be
  established.

---

## 14. Testing Requirements

Every behaviour change MUST include appropriate automated backend tests.

The project uses Pest.

Testing follows behaviour and risk, not class count.

### 14.1 Test responsibility by architecture type

| Architectural Type | Primary Test Style | Main Behaviour |
| --- | --- | --- |
| API Controller and route | Feature Test | Authentication, authorization, validation, HTTP status, response contract |
| Complex Action | Focused application or Feature Test | Workflow, transaction, database state, files, and side effects |
| Reusable Service | Unit Test | Reusable calculations, normalization, and transition rules |
| Simple CRUD | API Feature Test | Validation, permission, persistence, and Resource output |
| Query Class | Feature or integration-style test | Filters, sorting, pagination, eager loading, visibility |
| Job and Mail flow | Unit or Feature Test | Dispatch, retry behaviour, identifiers, and mail side effects |

### 14.2 Actions

Complex Actions should be tested using real test-database behaviour and storage
fakes when those boundaries are part of the use case.

Do not mock away the database, transaction, or storage behaviour that the test
is intended to prove.

### 14.3 Services

Focused Services should receive fast unit tests for deterministic rules such as:

- phone normalization
- order-number formatting
- pricing calculations
- quote-completion rules
- status transitions
- cancellation rules
- locale resolution

Do not create a Unit Test only to prove that a pass-through Service calls
`Model::create()`.

### 14.4 Simple CRUD

Simple CRUD normally needs an API Feature Test proving:

- authentication
- permission
- validation
- database persistence
- resource transformation
- HTTP status
- response envelope

Do not create an Action, Service, or isolated test solely to increase the number
of layers.

### 14.5 Architecture-critical tests

The test suite must prove, where applicable:

- inactive administrators cannot authenticate or use protected APIs
- permissions are enforced
- Arabic and English locale resolution works
- bilingual public content resolves correctly
- required service questions are enforced
- invalid question and choice ownership is rejected
- customer answers are not translated
- inactive services are excluded from normal public catalogue responses
- unavailable services cannot be ordered
- unavailable option values cannot be selected
- frontend-supplied prices do not control totals
- customer matching uses normalized phone
- existing customers are not silently overwritten by guest input
- one order can contain priced and quote-required items
- incomplete pricing keeps the final order total incomplete
- order, option, question, choice, and answer snapshots preserve historical meaning
- attachments map to the correct order item
- invalid MIME types and extensions are rejected
- failed order creation cleans up stored files
- order-number collisions retry safely
- only authorized administrators change order status
- cancellation requires a reason
- terminal states cannot return to active states
- Contact Us reply persistence occurs before queued email dispatch
- queue failure does not roll back a saved reply
- important list endpoints avoid N+1 queries

Tests MUST verify observable behaviour rather than implementation details.

Use factories and named factory states for readable setup.

---

## 15. Required Verification Commands

The agent MUST inspect available Composer scripts and project tooling before
choosing commands.

Typical commands, only when supported by the repository, include:

```bash
php artisan test
vendor/bin/pest
php artisan test --filter=RelevantTest
vendor/bin/pest --filter=RelevantTest
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

For database or migration work, run the approved project-safe verification
commands against the test environment.

For a focused change:

1. run the relevant focused tests during implementation
2. run the broader affected suite before completion
3. run formatting and static analysis when configured
4. inspect the final diff

A task MUST NOT be reported as complete while required checks are failing.

When a command cannot run because of the environment, missing services, or an
unrelated existing failure, report:

- the exact command
- the exact failure
- whether the failure is related to the current change
- the impact on confidence

Do not claim a command passed unless it actually passed.

---

## 16. Dependency and Package Policy

- Prefer Laravel and existing first-party capabilities before external
  packages.
- Inspect existing dependencies before adding a package.
- Do not add a production dependency without approved feature need or explicit
  user instruction.
- Confirm compatibility with the pinned PHP and Laravel versions.
- Do not perform broad dependency upgrades for a narrow task.
- Do not modify lock files unless dependency installation or update is part of
  the task.
- Explain why a new dependency is required and why existing capabilities are
  insufficient.
- Use:
  - Laravel Sanctum for approved token authentication
  - Spatie Laravel Permission for roles and permissions
  - Spatie Laravel Query Builder for allow-listed API queries
- Do not replace approved packages silently.

---

## 17. Change Discipline

- Make the smallest coherent change that fully satisfies the task.
- Preserve approved architecture and naming.
- Do not perform unrelated refactors.
- Do not reformat unrelated files.
- Do not change public routes, request keys, response shapes, error codes,
  database semantics, or translation keys without specification support.
- Do not add speculative customer authentication, payments, notifications,
  caching, Redis, Horizon, nested category APIs, or separate quote entities.
- Preserve backward compatibility when required by the active specification.
- Remove dead code introduced by the current change.
- Do not delete unrelated legacy code without authorization.
- Add comments only for non-obvious intent, invariants, or trade-offs.
- Never add fake implementations, silent fallbacks, placeholder-success
  responses, or incomplete TODOs presented as completed work.
- Do not mark a feature complete when part of its approved scope is missing.

---

## 18. Git and Workspace Safety

The agent MUST preserve user work.

Without explicit user instruction, the agent MUST NOT run:

```text
git reset --hard
git checkout -- .
git restore .
git clean -fd
force push
interactive rebase
commit --amend
```

The agent MUST NOT:

- revert changes it did not create
- delete untracked user files
- overwrite modified files without inspection
- create commits, tags, branches, or pull requests unless requested
- stage unrelated files

Before finishing:

- inspect `git status`
- inspect the relevant diff
- confirm only intended files changed
- preserve all unrelated user changes

---

## 19. Generated Files and Build Artifacts

- Do not manually edit generated files when their source can be changed.
- Do not commit `.env`, real secrets, local logs, caches, `vendor/`,
  `node_modules/`, or compiled assets unless the repository explicitly tracks
  them.
- Do not edit `composer.lock` manually.
- Regenerate derived files only when required.
- Do not commit uploaded test artifacts or storage files unless an approved
  fixture requires them.

---

## 20. Completion Standard

A task is complete only when:

- it matches the active specification and governing documentation
- backend authentication and authorization are enforced
- validation and failure cases are implemented
- database integrity is preserved
- concurrency concerns are handled where relevant
- prices and totals are calculated by the backend
- snapshots preserve historical data where required
- file writes and rollback cleanup are consistent
- API responses expose only approved data
- Arabic and English messages and content preserve stable English machine
  contracts
- appropriate automated tests exist and pass
- formatting and static analysis pass when configured
- documentation and task status are updated
- the final diff contains no unrelated changes
- no known critical security or data-integrity issue remains

---

## 21. Final Response Format

When completing a coding task, report:

1. What changed.
2. Important implementation decisions.
3. Tests and verification commands run, with actual results.
4. Files changed.
5. Remaining limitations, risks, or manual steps.

Do not claim success for commands that were not run.

Do not claim email was delivered when only a queued Job was dispatched.

Do not claim a feature is complete when approved work remains.

---

## 22. Codex Instruction Verification

Place this file at the Git repository root as:

```text
AGENTS.md
```

Codex reads repository instructions when a session starts.

After adding or updating this file, start a new Codex session or restart the
current one.

To verify that Codex loaded the instructions, run from the repository root:

```bash
codex --ask-for-approval never "Summarize the active repository instructions."
```

For directory-specific rules, create a nested `AGENTS.md` or
`AGENTS.override.md` near the relevant files.

Narrower instructions may add or refine local rules but MUST NOT weaken:

- the constitution
- approved architecture
- security requirements
- authorization requirements
- data-integrity requirements
- active feature specifications
