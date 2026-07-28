# Service Commerce Backend - Code Standards

> **Scope:** These standards apply to the Laravel 13 backend of the Service
> Commerce platform.
>
> **Architecture:** API-first conventional Laravel monolith.
>
> **Status:** Project-wide mandatory standard.

---

## 1. Purpose

This document defines the coding, naming, structure, quality, security,
testing, and maintainability standards for the Service Commerce Backend.

It exists to ensure that code produced by developers and AI coding agents
remains predictable, secure, testable, and consistent with:

- `AGENTS.md`
- `.specify/memory/constitution.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- `docs/02-standards/api-standards.md`
- future approved files under `docs/02-standards/`
- the active Spec Kit feature specification, plan, and task list

Feature specifications remain the source of truth for product behaviour.

This file defines how approved behaviour must be implemented.

---

## 2. Technology Baseline

| Area | Standard |
|---|---|
| Backend language | The Laravel 13-compatible PHP version pinned by the repository |
| Backend framework | Laravel 13 |
| Backend architecture | Conventional Laravel monolith |
| API style | Versioned RESTful JSON APIs |
| Authentication | Laravel Sanctum personal access tokens |
| Database | MySQL |
| Roles and permissions | `spatie/laravel-permission` |
| API filtering | `spatie/laravel-query-builder` |
| Queue | Laravel database queue |
| PHP formatter | Laravel Pint |
| Static analysis | Larastan/PHPStan when configured |
| Tests | Pest on Laravel's PHPUnit-compatible testing foundation |

The actual versions in `composer.lock` are authoritative.

The agent MUST inspect installed versions before using package-specific APIs.

---

## 3. Core Engineering Principles

- Read approved specifications before implementation.
- Do not invent missing product behaviour during coding.
- Keep controllers thin.
- Enforce all business rules on the backend.
- Prefer Laravel conventions over custom frameworks.
- Keep the application a conventional Laravel monolith.
- Do not introduce `app/Modules`, package-per-feature structures,
  Domain/Application/Infrastructure layers, microservices, CQRS, or event
  sourcing without an approved architecture change.
- Validate every write operation.
- Authorize every protected operation.
- Protect database integrity and concurrency where relevant.
- Preserve order-time snapshots.
- Recalculate prices and totals on the backend.
- Treat frontend cart data as untrusted input.
- Fix root causes instead of hiding inconsistent state.
- Prefer explicit code over hidden magic.
- Avoid premature abstractions and unnecessary dependencies.
- Preserve existing repository conventions unless approved documentation
  changes them.
- Test behaviour and risk rather than class count.

---

## 4. Codex and AI Agent Rules

Before changing code, an AI coding agent MUST:

1. Read the root `AGENTS.md`.
2. Read any nested `AGENTS.md` or `AGENTS.override.md` that applies.
3. Read `.specify/memory/constitution.md`.
4. Read:
   - `docs/00-project-overview/project-overview.md`
   - `docs/01-architecture/backend-architecture.md`
   - applicable standards files
5. Read the active feature `spec.md`, `plan.md`, and `tasks.md`.
6. Inspect existing code near the requested change.
7. Inspect `composer.json`, `composer.lock`, `.env.example`, and relevant
   configuration.
8. Check Git status and preserve user changes.
9. Verify package versions instead of assuming APIs from memory.
10. Implement the narrowest safe scope.

For Laravel installation, initialization, upgrades, or material framework
reconfiguration, the agent MUST fetch and follow:

```text
https://laravel.com/for/agents
```

If the repository already contains Laravel, the agent MUST NOT reinstall
Laravel or replace its skeleton.

The agent MUST NOT:

- modify unrelated files
- rewrite existing user changes without a specification-backed reason
- add packages merely for convenience
- run destructive Git or database commands without explicit approval
- report tests as passing unless they were actually run
- claim an email was delivered when only a Job was queued
- mark incomplete work as complete

---

## 5. Conventional Laravel Project Structure

Use Laravel's normal structure and add focused subfolders only when they improve
navigation.

```text
app/
  Actions/
    Auth/
    Customers/
    Categories/
    Services/
    ServiceQuestions/
    Orders/
    ContactMessages/
    SiteSettings/

  Enums/
    Auth/
    Services/
    Orders/
    ContactMessages/

  Events/
  Exceptions/

  Http/
    Controllers/
      Api/
        V1/
          Auth/
          Admin/
          Public/

    Middleware/

    Requests/
      Api/
        V1/
          Auth/
          Admin/
          Public/

    Resources/
      Api/
        V1/
          Admin/
          Public/

  Jobs/
    Mail/

  Mail/
  Models/
  Policies/

  Queries/
    Categories/
    Services/
    ServiceQuestions/
    Customers/
    Orders/
    Dashboard/

  Rules/

  Services/
    Customers/
    Files/
    Localization/
    Orders/
    Pricing/

  Support/
    Api/
    Files/
    Money/
    Phone/
```

Rules:

- Do not create `app/Modules`.
- Do not mirror every feature with Domain/Application/Infrastructure folders.
- Group by technical responsibility first, then business area where useful.
- Do not create empty layers for simple CRUD.
- `app/Support` is for genuinely shared technical helpers and value-oriented
  utilities, not business workflows.
- Do not create a global `helpers.php` dumping ground.
- Avoid deeply nested directories that make classes difficult to locate.
- Keep naming consistent across Actions, Requests, Resources, Queries, and
  tests.

---

## 6. PHP Language Standards

Application PHP files SHOULD begin with:

```php
<?php

declare(strict_types=1);
```

Rules:

- Follow PSR-12 and Laravel conventions.
- Use typed parameters and return types.
- Use promoted constructor properties where clear.
- Use `readonly` where immutability is intended.
- Avoid `mixed` unless required at a framework boundary.
- Prefer early returns over deeply nested conditions.
- Use `final` for Actions, DTOs, pure Services, value objects, and classes not
  designed for inheritance.
- Prefer composition over inheritance.
- Use named arguments when they improve clarity.
- Do not suppress static analysis without a documented reason.
- Do not use dynamic properties.
- Avoid hidden mutation.
- Keep methods focused and reasonably small.
- Extract a method or class when it represents a real concept, not merely to
  reduce line count.
- Do not scatter raw numeric HTTP statuses when application code selects the
  status.
- Do not scatter raw status, pricing, availability, or permission strings.

---

## 7. Naming Standards

### 7.1 PHP Classes

Use singular and explicit names.

Examples:

```text
Customer
CustomerAddress
Category
Service
ServiceMedia
ServiceOptionGroup
ServiceOptionValue
ServiceOrderQuestion
ServiceOrderQuestionOption
Order
OrderItem
OrderItemAttachment

CreateGuestOrderAction
PriceQuoteRequiredOrderItemAction
CancelOrderAction
ReplyToContactMessageAction

StoreServiceRequest
StoreGuestOrderRequest
UpdateCustomerRequest

ServiceResource
OrderResource
PublicServiceResource

OrderPricingService
OrderNumberGenerator
PhoneNormalizer
```

Avoid vague names:

```text
Manager
GeneralService
CommonService
Handler
Processor
Utils
Helper
DataManager
```

### 7.2 Methods

Use verbs that describe behaviour.

Examples:

```text
execute()
calculate()
recalculate()
normalize()
generate()
cancel()
reject()
transition()
store()
replace()
delete()
canBeOrdered()
requiresQuote()
hasCompletePricing()
```

Avoid ambiguous methods such as:

```text
handleData()
process()
doWork()
manage()
runLogic()
```

unless a framework interface requires the name.

### 7.3 Variables

Use domain language consistently.

Examples:

```php
$customer
$normalizedPhone
$rootCategory
$subcategory
$service
$order
$orderItem
$selectedOptions
$knownSubtotal
$finalTotal
$cancellationReason
```

Avoid abbreviations that reduce clarity.

### 7.4 Database, API, and URL Naming

- Database tables and columns use `snake_case`.
- Public API request and response keys use `camelCase`.
- PHP enum cases use `UPPER_SNAKE_CASE`.
- Enum values exposed through APIs use documented lowercase snake-case.
- Permission names use stable dot notation.
- URL path segments use lowercase kebab-case.
- Error codes use `UPPER_SNAKE_CASE`.

Permission examples:

```text
customers.view
customers.create
customers.update

categories.view
categories.create
categories.update
categories.delete

services.view
services.create
services.update
services.delete
services.restore

orders.view
orders.price
orders.change-status
orders.cancel
orders.reject

contact-messages.view
contact-messages.reply
```

Exact permission names belong in the Roles and Permissions specification.

---

## 8. Domain Enum Standards

Use string-backed PHP enums for stable business states exposed through the API.

Recommended enums:

```text
UserType
UserStatus
CategoryStatus
ServicePublicationStatus
ServiceAvailabilityStatus
ServicePricingType
ServiceDurationType
ServiceDurationUnit
ServiceMediaType
ServiceOptionSelectionType
ServiceOrderQuestionType
OrderStatus
OrderPricingStatus
OrderItemPricingStatus
ContactMessageStatus
```

Example:

```php
enum ServicePricingType: string
{
    case FIXED = 'fixed';
    case STARTING_FROM = 'starting_from';
    case QUOTE_REQUIRED = 'quote_required';
}
```

Rules:

- Use PHP backed enums for controlled domain states.
- Do not use native MySQL `ENUM` columns.
- Persist business enum values in appropriate string columns.
- Do not scatter raw enum strings in controllers, Actions, Services, models, or
  tests.
- Cast enum-backed columns in Eloquent models.
- Validate with Laravel's Enum rule or approved equivalent.
- API Resources expose the documented enum value.
- Seeders, factories, and tests use enum cases.
- Do not reorder or silently reinterpret persisted meanings.
- State transitions must not live only inside enum labels.
- Use a dedicated workflow or transition Service for order status rules.
- Boolean fields should remain booleans when there are exactly two stable
  states and no meaningful workflow.
- Do not create enums for arbitrary free-form content.

### 8.1 User type

The MVP uses:

```text
users.type = 0
```

to classify administrator accounts.

This internal broad classification does not replace Spatie roles and
permissions.

The exact representation may use an integer-backed enum for the internal
`users.type` field if approved by the database standard.

Public authentication Resources must expose the approved contract value without
making `type` the authorization source.

---

## 9. HTTP Response Status Enum

Use the shared enum:

```text
App\Enums\HTTP_RESPONSE_CODE
```

Rules:

- Controllers, middleware, exception renderers, and response builders use the
  shared enum when application code selects an HTTP status.
- Response helpers SHOULD accept the enum and normalize it internally to the
  integer status.
- Do not scatter literals such as:
  - `200`
  - `201`
  - `401`
  - `403`
  - `404`
  - `409`
  - `422`
  - `429`
  - `500`
- Add new response status cases centrally.
- Tests MAY assert numeric HTTP status values because they verify the external
  contract.
- API documentation MAY use normal numeric status codes.
- The HTTP status enum is separate from machine-readable business error codes.

---

## 10. Eloquent Model Standards

Models may contain:

- relationships
- casts
- accessors and mutators with clear value semantics
- small query scopes
- small predicates tied directly to model state

Examples:

```text
isActive()
isAvailable()
requiresQuote()
isTerminal()
hasCompletePricing()
isRootCategory()
isSubcategory()
```

Models MUST NOT contain:

- HTTP concerns
- API response formatting
- long multi-record workflows
- mail delivery
- queued Job dispatch
- file upload orchestration
- current-request authorization logic
- complete order creation
- complete order pricing orchestration
- generic service-locator methods

Rules:

- Mass assignment MUST be explicit.
- Guard prices, totals, statuses, generated order numbers, roles, permissions,
  snapshot values, and system-managed fields.
- Define return types for relationships where practical.
- Use enum casts for controlled state.
- Use decimal casts or approved money value handling.
- Do not rely on model events for hidden critical business workflows.
- Model observers may be used only when the behaviour is global, predictable,
  documented, and safe.
- Do not use accessors that trigger database queries.
- Avoid storing derived values that can become inconsistent unless the feature
  requires them and protects updates.

---

## 11. Controller Standards

Controllers coordinate HTTP input and output only.

Allowed responsibilities:

1. Receive a typed Form Request.
2. Resolve route-bound resources.
3. Authorize the operation.
4. Call an Action for a complex use case.
5. Call a focused Service for directly reusable behaviour where appropriate.
6. Perform a small Eloquent operation for simple CRUD.
7. Return an API Resource through the approved response envelope.

Controllers MUST NOT:

- contain long business workflows
- calculate service or order prices
- perform customer matching
- implement category hierarchy validation repeatedly
- implement order transition maps
- call `DB::transaction()` for complex feature workflows
- build large Eloquent queries
- perform manual request validation
- send email directly
- store complex multipart files directly
- expose raw Eloquent models
- construct inconsistent JSON arrays
- catch every exception and convert it manually
- trust frontend totals or statuses

Prefer REST methods:

```text
index
store
show
update
destroy
```

Use single-purpose command controllers when clearer:

```text
CancelOrderController@store
RejectOrderController@store
ChangeOrderStatusController@store
PriceOrderItemController@store
ReplyToContactMessageController@store
RestoreServiceController@store
```

---

## 12. Form Request Standards

Every create, update, and sensitive command endpoint MUST use a dedicated Form
Request.

Examples:

```text
LoginRequest
UpdateProfileRequest
ChangePasswordRequest
StoreCustomerRequest
UpdateCustomerRequest
StoreCategoryRequest
StoreSubcategoryRequest
StoreServiceRequest
UpdateServiceRequest
StoreServiceOrderQuestionRequest
UpdateServiceOrderQuestionRequest
StoreGuestOrderRequest
PriceOrderItemRequest
ChangeOrderStatusRequest
CancelOrderRequest
ReplyToContactMessageRequest
```

Rules:

- Public API field names in validation rules use `camelCase`.
- Bilingual content input uses the approved `ar` and `en` object structure.
- Use only `$request->validated()` for business input.
- Uploaded files may be accessed through approved validated file fields.
- Normalize trimming, casing, slugs, booleans, and phone input only when the
  transformation is safe and documented.
- Use `prepareForValidation()` for transport-level normalization.
- Do not perform database writes in a Form Request.
- Do not dispatch Jobs in a Form Request.
- Do not hide business workflows inside custom validation rules.
- Custom Rules validate reusable constraints, not full use cases.
- Validation messages must use translation keys.
- Validation errors must match the API contract.
- Protected fields must not be accepted merely because they exist in the
  database.

### 12.1 Nested guest-order validation

Nested order input must validate:

- customer data
- address data
- item structure
- unique request-only `clientReference`
- service ID
- service quantity
- option group and value structure
- selected option quantities
- active question definitions
- required and optional answers
- answer types and configured limits
- selected question choices
- question and service ownership
- attachment count
- attachment mapping
- file size
- extension
- MIME type

Do not treat nested validation as proof that service, option, category, or
pricing state is currently eligible.

That business validation belongs in the order workflow.

---

## 13. API Resource Standards

All JSON data MUST pass through explicit API Resources or Resource Collections.

Rules:

- Do not return raw Eloquent models.
- Output explicit `camelCase` keys.
- Do not expose every database column automatically.
- Use `whenLoaded()` for optional relationships.
- Keep list Resources lightweight.
- Use detailed Resources for show endpoints.
- Map enums to approved external values.
- Return money as fixed-precision decimal strings.
- Return timestamps as ISO 8601 UTC.
- Use JSON booleans for boolean fields.
- Do not expose:
  - password hashes
  - access tokens except the one-time login token field
  - internal filesystem paths
  - private configuration
  - administrative notes through public APIs
  - internal customer metadata through public APIs
  - unapproved identifiers
- Resources MUST NOT trigger database queries.
- Public and administration Resources may differ.
- Public Resources return resolved Arabic or English content.
- Administration Resources may return both translations for editing.
- A Resource must not alternate unpredictably between a localized string and a
  translation object.
- Order creation responses expose only approved guest-safe result data.

---

## 14. Actions, Services, and Simple CRUD

### 14.1 Actions

Use an Action for a complex named use case.

Examples:

```text
CreateGuestOrderAction
MatchOrCreateCustomerAction
CreateServiceAction
UpdateServiceAction
CreateServiceOrderQuestionAction
UpdateServiceOrderQuestionAction
PriceQuoteRequiredOrderItemAction
ChangeOrderStatusAction
CancelOrderAction
ReplyToContactMessageAction
```

An Action SHOULD:

- have one clear business outcome
- accept explicit typed or validated input
- be callable without HTTP
- coordinate multiple steps or models
- own the required transaction boundary
- delegate reusable rules to focused Services
- track compensating file cleanup where required
- dispatch dependent Jobs after commit
- return a meaningful result
- be covered by focused application or Feature Tests

An Action MUST NOT be created automatically for every endpoint.

### 14.2 Services

Use Services for reusable business capabilities.

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
- be reusable across multiple use cases where appropriate
- remain independently testable
- avoid unrelated methods

A Service MUST NOT:

- become a giant `OrderService`
- duplicate Action orchestration
- exist only to call `Model::create()`
- act as a generic dependency container

### 14.3 Simple CRUD

Simple CRUD does not require an Action or Service when it:

- uses validated data
- affects one model
- has no multi-model workflow
- requires no transaction orchestration
- has no complex file handling
- has no external side effect
- contains no reusable business rule
- contains no meaningful state transition

Examples may include straightforward CRUD for:

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

The approved rule is:

```text
Actions = orchestration of complex use cases
Services = reusable business capabilities
Simple CRUD = no unnecessary layer
```

---

## 15. Category and Subcategory Code Standards

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
- Circular relationships are prohibited.
- Hierarchy depth is limited to two levels.
- Services belong directly to one subcategory.
- Do not store both a root category ID and subcategory ID on a service.
- Prefer a clear `subcategory_id` foreign key on `services`.
- The root category is resolved through the subcategory relationship.
- Public catalogue queries enforce active root category, active subcategory,
  and active service visibility.
- Order creation revalidates category and subcategory eligibility.
- Category moves or parent changes that can race must be transaction-safe.
- Deactivation must not rewrite historical order snapshots.
- Soft deletion follows the approved database and feature standards.

Recommended predicates or scopes may include:

```text
isRootCategory()
isSubcategory()
scopeRoots()
scopeSubcategories()
scopeActive()
```

Do not implement recursive unlimited category-tree utilities in the MVP.

---

## 16. Service Catalogue Code Standards

Service code must preserve the distinction between:

```text
publication status
availability status
```

Approved pricing types:

```text
fixed
starting_from
quote_required
```

Rules:

- Do not model publication and availability as interchangeable booleans.
- Use enum casts.
- Fixed and starting-from services require approved base-price validation.
- Quote-required services may have a null final order-time price.
- Do not use zero as a substitute for unknown price.
- Money uses fixed precision.
- Service duration uses approved type and unit enums.
- One service has one main image maximum.
- One service has one video maximum.
- Service specifications are display-only.
- Service options are selectable order configuration.
- Unavailable option values must be rejected during ordering.
- Public catalogue output must not make an ineligible service appear orderable.
- Service updates must not modify historical order snapshots.
- Soft-deleted services remain representable through order snapshots.

---


## 17. Service Order Question Code Standards

Service order questions are administrator-managed fields that collect
information for one service item.

They remain separate from service options.

### 17.1 Question classes

Recommended classes may include:

```text
ServiceOrderQuestion
ServiceOrderQuestionOption
StoreServiceOrderQuestionRequest
UpdateServiceOrderQuestionRequest
ServiceOrderQuestionResource
PublicServiceOrderQuestionResource
ServiceQuestionAnswerValidator
```

Create or update Actions are justified when the operation coordinates:

- question definition
- bilingual content
- choices
- validation configuration
- sorting
- activation
- transaction-safe replacement

Simple single-row updates may remain direct CRUD when no orchestration exists.

### 17.2 Question validation

The backend validates:

- question belongs to the service
- active required questions are answered
- answer matches the approved input type
- allow-listed validation configuration
- selected choices belong to the question
- selected choices are active
- single-choice and multiple-choice cardinality
- duplicate answers
- answers from another service

Do not use translated labels as identifiers.

Do not store or execute arbitrary Laravel rule strings submitted by the
dashboard.

### 17.3 Bilingual question content

Questions and choices use Arabic and English fields.

Examples:

```text
label_ar
label_en
help_text_ar
help_text_en
placeholder_ar
placeholder_en
```

Stable fields remain untranslated:

```text
question_key
option_key
input_type
is_required
validation_config keys
```

Public Resources resolve the active request locale.

Administration Resources expose the approved bilingual editing shape.

### 17.4 Answer persistence

Answers belong to individual order items.

Use typed answer fields or normalized answer-choice rows according to the
database standard.

Customer-entered answers are not automatically translated.

Snapshots preserve:

- question key
- Arabic and English labels
- input type
- required state
- Arabic and English selected-choice labels
- submitted answer

Question edits affect future orders only.

### 17.5 Testing

Tests cover:

- required and optional questions
- each supported input type
- invalid service ownership
- inactive questions
- invalid and inactive choices
- single and multiple selection rules
- bilingual public output
- bilingual administration input
- customer answer non-translation
- historical snapshot preservation

---

## 18. Order Workflow Code Standards

Order creation and mutation must use approved workflow components.

### 18.1 Guest order creation

`CreateGuestOrderAction` coordinates:

- customer phone normalization
- customer matching or creation
- address handling
- catalogue eligibility validation
- service quantity validation
- option ownership and availability validation
- service-question and answer validation
- question-choice ownership validation
- price calculation
- order number generation
- order creation
- order-item creation
- customer and address snapshots
- question, choice, and answer snapshots
- service and option snapshots
- item attachment storage
- total calculation
- pricing-state calculation
- filesystem cleanup on failure

The Action must not trust:

- frontend prices
- frontend totals
- frontend service status
- frontend option availability
- frontend question labels or required state
- frontend category or subcategory eligibility
- frontend snapshot labels
- frontend order status

### 18.2 Order status changes

All status changes use one approved transition component, such as:

```text
OrderStatusTransitionService
```

The component validates:

- current state
- requested target
- administrator permission
- pricing completeness
- terminal-state restrictions
- cancellation reason
- rejection reason where required

Controllers and models MUST NOT set order status directly for workflow
operations.

### 18.3 Quote-required pricing

Pricing an order item must:

- verify the item belongs to the route order
- verify the item requires quote pricing
- validate the price
- protect against concurrent updates
- update final item pricing
- recalculate the line total
- recalculate the order
- update order pricing status
- reject invalid terminal or workflow state

Do not create separate quote entities in the MVP.

---

## 19. Transaction and Concurrency Standards

Use `DB::transaction()` when multiple writes form one business operation.

Typical areas:

- guest order creation
- service creation or update with related media, specifications, and options
- quote-required item pricing and order recalculation
- order status changes
- default-address switching
- category or subcategory moves
- featured-service reordering
- Contact Us reply persistence and status update

Use `lockForUpdate()` or another approved mechanism when concurrent operations
can race.

Examples:

- two administrators price the same item
- two administrators change the same order status
- concurrent default-address changes
- concurrent featured-service reorder operations
- concurrent subcategory parent changes

Rules:

- Keep transactions short.
- Validate as much as possible before opening the transaction.
- Do not call external services inside a transaction.
- Do not send mail inside a transaction.
- Dispatch dependent Jobs after commit.
- Do not catch and suppress transaction exceptions.
- Retry deadlocks only through a controlled strategy.
- File writes require compensation because database rollback does not roll back
  the filesystem.

---

## 20. Query and Eloquent Standards

- Prevent N+1 queries using explicit eager loading.
- Paginate all potentially unbounded lists.
- Enforce a maximum `perPage`.
- Use `spatie/laravel-query-builder` with explicit allow-lists.
- Select only required columns for heavy lists where useful.
- Use model scopes for small reusable query intent.
- Use Query classes when filtering or aggregation becomes complex.
- Do not interpolate raw request values into SQL.
- Use `exists()` when only existence is needed.
- Use `withCount()` for counts without loading child collections.
- Avoid loading all order items or attachments for list endpoints.
- Public and administration visibility queries may differ.
- Category filtering must include services from child subcategories.
- Subcategory filtering must include only directly assigned services.
- Public service queries must enforce active root category, active subcategory,
  and approved service visibility.
- Search fields and indexes must match expected volume.

Recommended Query classes:

```text
ListPublicCategoriesQuery
ListPublicServicesQuery
ListAdminCategoriesQuery
ListAdminServicesQuery
ListCustomersQuery
ListOrdersQuery
DashboardMetricsQuery
BestSellingServicesQuery
```

---

## 21. Database and Migration Code Standards

These rules supplement the future
`docs/02-standards/database-standards.md`.

- Migrations MUST be deterministic.
- Use explicit foreign keys.
- Define intentional delete behaviour.
- Add unique constraints for true invariants.
- Add indexes for foreign keys, status fields, lookup fields, and common sorts.
- Avoid nullable columns without a valid domain meaning.
- Use precise decimal columns for money.
- Do not use float or double for money.
- Avoid native MySQL enum columns.
- Use soft deletes where historical integrity requires them.
- Do not physically delete orders or order items through normal flows.
- Store attachment metadata in MySQL, not file blobs.
- Use `phone_normalized` for customer matching.
- Add a unique index to `orders.order_number`.
- Enforce one main image and one video through database and application rules
  where practical.
- Preserve one default address through a transaction-safe invariant.
- Do not edit already-deployed migrations.
- Destructive schema changes require an explicit migration and deployment plan.

---

## 22. Events, Listeners, Jobs, and Mail

Use events only for meaningful completed facts.

Possible events:

```text
OrderCreated
OrderPriceCompleted
OrderStatusChanged
ContactMessageReceived
ContactMessageReplied
```

Rules:

- Events describe something that already happened.
- Do not create events for every method call.
- Events must not hide essential transactional writes.
- Use direct after-commit Job dispatch when only one asynchronous side effect is
  required.
- Add an event and listeners when multiple independent reactions justify it.
- Jobs must be retry-safe.
- Jobs should carry scalar identifiers.
- Jobs reload authoritative records.
- Jobs must not serialize large sensitive model graphs.
- Jobs define timeout, retry count, and backoff.
- Failed Jobs must remain visible.
- Queued email delivery must not be represented as delivered before actual
  confirmation.
- Business workflows belong in Actions or Services, not inside Jobs.

Approved initial asynchronous flow:

```text
ReplyToContactMessageAction
  -> save reply
  -> commit
  -> dispatch SendContactReplyMailJob
```

The approved queue driver is the database queue.

---

## 23. Error Handling Standards

- Expected domain failures use explicit domain exceptions or approved result
  objects.
- The global exception handler normalizes errors into the API standard.
- Do not catch `Throwable` merely to hide failure.
- Do not return success after an exception.
- Do not expose stack traces, SQL, filesystem paths, class names, or secrets.
- Log unexpected exceptions with request correlation.
- Validation, authentication, authorization, not-found, conflict, rate-limit,
  and server errors must have predictable envelopes.
- Business error codes remain stable and English.
- User-facing messages are localized.
- Do not invent error codes inside controllers.
- Do not leak whether an administrator email exists during login.
- Filesystem cleanup failures should be logged without hiding the primary
  failure.

---

## 24. Security Standards

- Use Sanctum personal access tokens according to the approved authentication
  flow.
- Hash passwords using Laravel's configured hasher.
- Use generic login errors.
- Rate-limit login, public order submission, Contact Us, and abuse-sensitive
  uploads.
- Enforce Spatie permissions on administration APIs.
- Treat authentication and authorization separately.
- Validate every nested resource relationship.
- Prevent mass assignment of protected fields.
- Never trust:
  - user IDs
  - roles
  - permissions
  - customer ownership
  - prices
  - totals
  - order statuses
  - category eligibility
  - option availability
  - file names
  - MIME type headers alone
- Do not log:
  - passwords
  - raw tokens
  - authorization headers
  - SMTP credentials
  - private file contents
  - unnecessary personal data
- Keep secrets in environment configuration.
- Apply least privilege to database, queue, mail, and storage credentials.
- Production errors must fail safely.
- Uploaded files must not execute on the server.

---

## 25. File and Media Code Standards

### 25.1 Shared upload rules

Every upload flow must:

1. validate upload success
2. validate count
3. validate file size
4. validate extension
5. validate MIME type
6. generate a server-controlled filename
7. use a server-controlled directory
8. store approved metadata
9. avoid raw path exposure
10. clean created files when a multi-step workflow fails

Do not trust the original filename.

Preserve the original name only as validated metadata when needed.

### 25.2 Service media

Service media code must enforce:

- one main image maximum
- multiple additional images
- one video maximum
- approved image and video types
- explicit sort order
- safe replacement cleanup
- no video transcoding
- no automatic video compression
- no thumbnail infrastructure unless approved

### 25.3 Order-item attachments

Order-item attachments:

- are uploaded during guest order creation
- belong to one order item
- use request-only `clientReference` mapping
- use approved file types
- are unavailable for later guest upload
- must not be returned through public guest APIs
- must not expose raw storage paths
- use generated filenames
- require server configuration that prevents execution

The MVP uses the configured public disk for order-item attachments.

This storage choice does not authorize public API exposure.

---

## 26. Logging and Observability

Structured logs SHOULD include safe context when available:

```text
request_id
route
method
http_status
duration_ms
authenticated_user_id
customer_id when operationally necessary
service_id
order_id
order_number
job_id
operation
```

Rules:

- Generate or propagate a request ID.
- Log unexpected failures at the appropriate level.
- Do not log full request bodies by default.
- Do not log raw tokens or authorization headers.
- Do not log passwords.
- Do not log attachment contents.
- Do not log unnecessary customer PII.
- Queue and mail failures must be visible.
- Database transaction and cleanup failures must not be swallowed.
- Public error responses must not contain internal log context.

---

## 27. Testing Standards

The project uses Pest.

Every behaviour change requires appropriate automated tests.

### 27.1 API Feature Tests

Cover applicable:

- authentication
- unauthenticated access
- permission enforcement
- validation
- success response shape
- HTTP statuses
- Arabic messages
- English error codes
- database persistence
- filtering
- sorting
- pagination
- includes
- public versus administration visibility
- rate limiting
- file validation

### 27.2 Action Tests

Complex Actions are tested through focused application or Feature Tests.

Use the test database and Storage fakes when those integrations are part of the
behaviour.

Examples:

```text
CreateGuestOrderAction
PriceQuoteRequiredOrderItemAction
CancelOrderAction
ReplyToContactMessageAction
```

Do not mock away the transaction, database, or storage behaviour the test is
intended to prove.

### 27.3 Service Unit Tests

Use unit tests for deterministic reusable behaviour.

Examples:

```text
PhoneNormalizer
OrderNumberGenerator
OrderPricingService
OrderStatusTransitionService
ApiLocaleResolver
```

Do not write unit tests merely to prove that a pass-through Service calls an
Eloquent method.

### 27.4 Simple CRUD Tests

Simple CRUD normally needs an API Feature Test that proves:

- authentication
- authorization
- validation
- persistence
- Resource output
- response envelope

Do not create an Action or Service solely to create another isolated test.

### 27.5 Regression Tests

Every bug fix must include a regression test that fails before the fix and
passes after it.

### 27.6 Architecture-critical tests

Critical tests include:

- third-level category rejection
- circular category relationship rejection
- service assignment only to subcategory
- inactive category or subcategory prevents ordering
- unavailable service rejection
- unavailable option rejection
- required service-question enforcement
- invalid question and choice ownership rejection
- bilingual question output
- price tampering rejection
- customer matching by normalized phone
- snapshot preservation
- attachment-to-item mapping
- file cleanup after failed order creation
- mixed priced and quote-required order
- incomplete total handling
- invalid order transition
- cancellation reason required
- terminal state protection
- contact reply Job dispatch after commit
- queued email not reported as delivered

Tests verify observable behaviour rather than private implementation details.

---

## 28. Localization Standards

Supported locales:

```text
ar
en
```

Arabic is the initial default locale.

English is the fallback locale.

Rules:

- Resolve locale from `Accept-Language`.
- Resolve locale before validation.
- Use translation keys for system messages.
- Do not hard-code repeated Arabic or English messages.
- Public catalogue and site content support both languages.
- Prefer explicit bilingual database fields for fixed two-locale content.
- Public Resources return resolved localized strings.
- Administration Resources accept and return the approved bilingual editing
  structure.
- Active content validates required Arabic and English fields.
- Customer names, addresses, notes, question answers, enquiry messages, and
  free-form replies are not automatically translated.
- Queued Jobs preserve or reload the intended locale.
- Do not translate:
  - error codes
  - API field names
  - routes
  - enum values
  - role and permission names
  - slugs
  - question keys
  - option keys
  - database columns
- Payload shape remains stable across locales.
- Localized labels never replace machine identifiers.

---

## 29. Package and Dependency Standards

Before adding a dependency:

1. Confirm Laravel or an installed package does not already provide the
   capability.
2. Verify compatibility with pinned PHP and Laravel versions.
3. Review maintenance and security posture.
4. Document why the dependency is required.
5. Add integration tests where appropriate.

Approved project packages include:

```text
laravel/sanctum
spatie/laravel-permission
spatie/laravel-query-builder
```

Do not add:

- repository packages for simple Eloquent CRUD
- a state-machine package for the small approved order workflow without a
  demonstrated need
- a category-tree package for a two-level hierarchy
- Redis or Horizon without an approved architecture change
- a quote package or separate quotation subsystem
- duplicate HTTP, storage, validation, or queue tooling without justification

Do not perform broad dependency upgrades for a narrow task.

---

## 30. Code Quality Commands

Use commands configured by the repository.

Typical commands may include:

```bash
php artisan test
vendor/bin/pest
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

Rules:

- Inspect Composer scripts before selecting commands.
- Run focused tests during development.
- Run the broader affected suite before completion.
- Run formatting and static analysis when configured.
- Do not change tool configuration merely to hide failures.
- Report every command actually run.
- Report commands that could not run and why.
- Do not claim a check passed unless it did.

---

## 31. Git and Pull Request Standards

- Keep changes focused.
- Do not mix unrelated formatting and behaviour changes.
- Do not commit secrets or `.env`.
- Do not commit local storage files, logs, caches, `vendor/`, or
  `node_modules/`.
- Do not force-push shared branches without approval.
- Do not create commits, tags, branches, or pull requests unless requested.
- Pull requests should reference the feature specification or task.
- Describe:
  - database changes
  - API changes
  - security impact
  - file-storage impact
  - queue impact
  - test evidence
- Breaking API changes require a new version or approved migration plan.
- Preserve unrelated user changes.

---

## 32. Documentation Standards

Update governing documentation when changing:

- public API contracts
- category hierarchy rules
- service pricing rules
- order statuses or transitions
- quote-required pricing behaviour
- roles or permissions
- database constraints
- error codes
- file rules
- authentication behaviour
- localization behaviour
- bilingual content requirements
- service-question and answer behaviour
- queue behaviour
- environment configuration

Do not duplicate global standards in every feature specification.

Feature specifications should reference global standards and define only
feature-specific behaviour.

When code and documentation disagree, the conflict must be resolved explicitly.

---

## 33. Definition of Done

A code change is complete only when:

- it implements an approved specification and task
- validation is complete
- authorization is complete
- backend business rules are enforced
- API responses follow global standards
- database integrity is preserved
- concurrency concerns are addressed where relevant
- prices and totals are backend-controlled
- snapshots preserve historical meaning
- file cleanup is consistent
- queued work is retry-safe
- automated tests cover critical behaviour
- formatting and static analysis pass where configured
- documentation is updated
- the final diff has no unrelated changes
- no known critical security or data-integrity issue remains

---

## 34. Prohibited Practices

The following are prohibited unless explicitly approved:

- business workflows inside controllers
- validation arrays inside controllers for mutation endpoints
- raw Eloquent models in API responses
- direct order-status mutation outside the transition component
- trusting frontend prices or totals
- trusting frontend category, option, question, or choice eligibility
- using translated labels as identifiers
- allowing missing required service-question answers
- linking a service directly to a root category
- creating a third category level
- separate quote entities in the MVP
- guest customer authentication in the MVP
- guest order tracking APIs in the MVP
- backend cart persistence in the MVP
- raw public storage paths in API responses
- unbounded list queries
- arbitrary filters or sorts
- native MySQL enums for business states
- float or double for money
- catching exceptions and silently continuing
- sending email inside database transactions
- claiming queued mail is delivered
- logging secrets or full sensitive payloads
- installing packages without justification
- creating a custom modular framework inside the Laravel monolith
- adding pass-through Actions or Services for simple CRUD
- starting implementation before the active specification is sufficiently clear

---

## 35. Code Review Checklist

- [ ] The change is linked to an approved specification or task.
- [ ] The implementation follows the conventional Laravel monolith.
- [ ] Controllers are thin.
- [ ] Form Requests validate all external mutation input.
- [ ] Actions are used only for complex orchestration.
- [ ] Services contain reusable focused behaviour.
- [ ] Simple CRUD avoids unnecessary layers.
- [ ] Category and subcategory rules are enforced.
- [ ] Services belong only to subcategories.
- [ ] Backend service, option, question, answer, and choice eligibility is
      revalidated.
- [ ] Required service questions are enforced.
- [ ] Bilingual content follows the localization standard.
- [ ] Frontend prices and totals are ignored.
- [ ] Order snapshots preserve approved values.
- [ ] Transactions and locks protect relevant races.
- [ ] File writes have rollback compensation where needed.
- [ ] Queries avoid N+1.
- [ ] Lists are paginated.
- [ ] Filters, sorts, and includes are allow-listed.
- [ ] API Resources expose only approved fields.
- [ ] Public responses do not expose administration-only data.
- [ ] Raw storage paths are not exposed.
- [ ] Error responses and codes follow API standards.
- [ ] Queued email is not represented as delivered.
- [ ] Tests cover success, validation, authorization, workflow, and failure.
- [ ] No unnecessary package or abstraction was introduced.
- [ ] Documentation was updated where required.
