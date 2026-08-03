# Service Commerce Backend - Backend Architecture

## Backend Technical Architecture Specification

**Document Status:** Architecture Baseline  
**Last Updated:** 2026-07-28  
**Applies To:** Laravel backend and all versioned JSON APIs

---

## Overview

The Service Commerce Backend is an API-first application responsible for
administrator authentication, authorization, customer management, service
catalogue management, configurable service ordering, order-item attachments,
quote-required pricing review, public website content, contact enquiries,
dashboard metrics, and reporting.

The backend is implemented with **Laravel 13** and **MySQL** as a
**conventional Laravel monolith**. It exposes stable, versioned RESTful JSON
APIs under `/api/v1/...`.

The backend is consumed by two external applications:

- a React administration dashboard
- a Next.js public website

Both frontend applications are outside this repository's implementation scope.

The administrator frontend and Laravel backend may have different physical
origins and registrable domains. Authenticated administrator browser traffic
uses direct browser-to-backend HTTPS requests with exact allow-listed CORS:

```text
Browser -> https://api.backend-example.net/api/v1/* -> Laravel /api/v1/*
```

The browser-visible backend origin is an approved part of the Admin Web build
configuration. Administrator authentication uses Bearer access tokens in the
`Authorization` header and rotating refresh tokens transported in JSON rather
than cookies. Same-origin proxy/BFF forwarding, authentication cookies, and
CSRF refresh flows are not part of the approved architecture.

This project is:

- a single-business platform
- not a multi-tenant SaaS platform
- backend-only
- guest-order based
- not an authenticated customer portal
- not an online payment platform
- not a booking or scheduling platform

The architecture preserves these product rules:

- the Super Admin is the only authenticated MVP role
- authenticated accounts are stored in `users`
- guest customers are stored separately in `customers`
- customers do not log in
- customer matching during guest checkout uses normalized phone numbers
- the catalogue uses two active levels: Category -> Subcategory
- each subcategory belongs to exactly one root category
- services may be unclassified, assigned directly to one root category only,
  or assigned to one root category and one subcategory
- a third category level is not supported in the MVP
- service publication and availability are separate states
- services may use fixed, starting-from, or quote-required pricing
- one order may contain priced and quote-required services together
- the cart is managed by the Next.js frontend through localStorage
- the backend never trusts frontend prices, totals, statuses, or availability
- order items preserve immutable snapshots
- customer and address data are preserved as order snapshots
- customer files belong to individual order items
- service videos are uploaded to the backend and limited to one video per
  service in the MVP
- payment happens outside the application
- only administrators may change or cancel submitted orders
- cancelling an order requires a reason
- order status history and general audit logging are outside the MVP
- public-content caching is not introduced initially
- Contact Us replies are sent as real emails using a database-backed queue
- Arabic and English are active API-message and public-content locales
- frontend consumers send the desired locale using `Accept-Language`
- stable JSON keys, enum values, permission identifiers, and error codes
  remain in English
- services may define required or optional order questions managed from the
  administration dashboard
- customer answers belong to individual order items and are preserved as
  snapshots

---

## 1. Architectural Style

### 1.1 Final Decision

```text
Architecture: API-first conventional Laravel monolith
Database: Single MySQL database
API Style: Versioned RESTful JSON APIs
Authentication: Laravel Sanctum personal access tokens
Authorization: Spatie Laravel Permission plus backend policies and rules
Filtering: Spatie Laravel Query Builder
Frontend Consumers: React Admin Dashboard and Next.js Public Website
Testing: Pest on top of Laravel's PHPUnit testing foundation
Queue: Database queue for approved asynchronous email work
Cache: Not introduced in the MVP
Avoid Initially: Microservices
Avoid Structurally: app/Modules and D/A/I folder layering
```

### 1.2 Why a Conventional Laravel Monolith

The platform's domains participate in shared workflows.

Guest order creation may involve:

- customer matching
- customer creation
- customer-address creation
- order creation
- order-number generation
- order-item creation
- service validation
- option validation
- price calculation
- snapshot creation
- attachment storage
- order-total calculation

These operations benefit from:

- one deployable Laravel application
- one transactional MySQL database
- one authentication and authorization system
- one response contract
- one file-storage abstraction
- one test suite
- one operational deployment

A conventional monolith provides the required consistency without introducing
premature service boundaries.

The backend MUST NOT introduce the following without a later approved
architecture decision:

```text
app/Modules
Domain/Application/Infrastructure folders
microservices
separate databases per capability
repositories for every Eloquent model
event sourcing
CQRS infrastructure
distributed transactions
```

### 1.3 API-First Boundary

Laravel is the authoritative source for:

- administrator authentication
- administrator authorization
- input validation and normalization
- category and subcategory eligibility
- service publication and availability
- service option eligibility
- customer matching
- customer persistence
- order-number generation
- order creation
- order pricing
- quote-required pricing state
- order status transitions
- order snapshots
- attachment metadata
- content management
- contact-reply persistence
- dashboard calculations
- Arabic and English API messages
- localized public catalogue content
- machine-readable error codes

The React and Next.js applications must not be trusted to enforce business
rules.

The following frontend behaviours are not security controls:

- hiding an administration button
- disabling an unavailable option
- calculating a total in JavaScript
- preventing a status selection in the UI
- filtering inactive categories, subcategories, or services in the browser
- restricting file types only through an HTML input

Every relevant rule must be enforced again by the backend.

---

## 2. Backend Capability Areas

### 2.1 Identity and Authentication

Responsibilities:

- Super Admin login
- Sanctum personal access-token issuance
- current authenticated user
- logout and current-token revocation
- inactive-user enforcement
- authentication rate limiting
- generic invalid-credentials responses
- token metadata and expiration rules when approved

### 2.2 Roles and Permissions

Responsibilities:

- Super Admin role
- permission seeding
- route-level permission middleware
- controller and policy authorization
- future role extensibility
- protection against relying only on the `users.type` column

The `users.type` column classifies the broad user type.

For the MVP:

```text
0 = administrator
```

Spatie roles and permissions remain the authoritative mechanism for access
control.

### 2.3 Customers

Responsibilities:

- customer creation
- customer updates by administrators
- customer activation and deactivation
- customer matching by normalized phone number
- customer order summaries
- customer address management

Customers do not authenticate.

### 2.4 Customer Addresses

Responsibilities:

- multiple addresses per customer
- one default address per customer
- administrator-managed address records
- guest-submitted address association during order creation
- order-address snapshot creation

### 2.5 Service Categories and Subcategories

Responsibilities:

- root-category management
- subcategory management
- category and subcategory activation and deactivation
- assigning each subcategory to exactly one root category
- assigning each service to the approved root-category and optional
  subcategory combination
- category and subcategory search, filters, sorting, and pagination
- public category and subcategory navigation
- soft deletion when allowed
- preventing hierarchy levels deeper than Category -> Subcategory
- preventing circular or invalid parent relationships
- preserving historical service and order relationships

The MVP exposes and uses both category levels.

### 2.6 Services

Responsibilities:

- service creation and update
- service activation and deactivation
- service availability management
- pricing type and price management
- fixed or ranged duration management
- SEO data
- main-image selection
- additional images
- one uploaded video
- service specifications
- configurable order options
- soft deletion
- public catalogue exposure

### 2.7 Service Specifications

Responsibilities:

- label-value specification management
- sorting
- activation where approved
- public service-detail output

Specifications are display-only and do not represent customer selections.

### 2.8 Service Order Options

Responsibilities:

- option-group management
- option-value management
- required or optional selection rules
- single or multiple value-selection rules where configured
- selected-value quantities
- option-value availability
- future price adjustments
- backend validation during order submission
- order-time option snapshots

### 2.9 Service Order Questions

Responsibilities:

- service-specific question management
- Arabic and English question labels
- Arabic and English help text and placeholders
- required or optional state
- input-type management
- choice management for single and multiple selection
- allow-listed validation configuration
- public question output
- backend answer validation during order submission
- order-item question, choice, and answer snapshots

Service order questions collect customer information.

They remain separate from service options, which configure the service and may
affect pricing.

### 2.10 Orders and Order Items

Responsibilities:

- guest order submission
- generated public order numbers
- customer matching or creation
- customer/address snapshots
- service validation
- quantity validation
- price recalculation
- quote-required item state
- mixed-priced order handling
- order-item snapshots
- administrator order management
- status transitions
- cancellation with a required reason

### 2.11 Order-Item Attachments

Responsibilities:

- multipart upload handling
- attachment-to-order-item mapping
- extension and MIME validation
- size and count validation
- randomized storage paths
- attachment metadata persistence
- administrator attachment access
- failed-order file cleanup

### 2.12 Site Content

Responsibilities:

- site settings
- contact emails
- phone numbers
- social links
- logo
- favicon
- Google Maps data
- hero configuration
- fixed hero mode
- slider hero mode
- testimonials
- FAQs
- featured services

### 2.13 Contact Inbox

Responsibilities:

- public contact submission
- enquiry status management
- administrator reply persistence
- real email reply delivery
- queued mail retry behaviour
- reply metadata

### 2.14 Dashboard and Reporting

Responsibilities:

- service metrics
- customer metrics
- order metrics
- contact-enquiry metrics
- featured service summaries
- best-selling service calculations
- recent operational data

---

## 3. Recommended Laravel Codebase Architecture

### 3.1 Folder Structure

The recommended structure keeps standard Laravel conventions and adds focused
folders only where they improve navigation:

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
    HeroSections/
    Testimonials/
    Faqs/
    FeaturedServices/

  Enums/
    Auth/
    Services/
    ServiceQuestions/
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
    Orders/
    Customers/
    Dashboard/

  Rules/

  Services/
    Customers/
    Files/
    Localization/
    Orders/
    Pricing/
    ServiceQuestions/

  Support/
    Api/
    Files/
    Money/
    Phone/

routes/
  api.php
  api/
    v1/
      auth.php
      admin.php
      public.php

config/
  auth.php
  sanctum.php
  permission.php
  services.php
  orders.php
  uploads.php
  localization.php

tests/
  Feature/
    Api/
      V1/
        Auth/
        Admin/
        Public/

  Unit/
    Orders/
    Pricing/
    ServiceQuestions/
    Support/
```

The backend MUST NOT introduce:

```text
app/Modules
app/Domain
app/Application
app/Infrastructure
one repository class for every model
one service class for every controller method
```

### 3.2 Controllers

Controllers must:

- accept validated Form Requests
- resolve route-bound resources
- invoke Actions or focused Services
- return API Resources or approved response objects
- remain small and transport-focused

Controllers must not contain:

- complex order workflows
- price calculations
- customer-matching rules
- large Eloquent query construction
- nested file-processing logic
- raw email-delivery logic
- status-transition maps
- reusable validation rules

### 3.3 Form Requests

Form Requests must:

- authorize the request when appropriate
- validate required fields
- validate nested arrays
- validate service-question answers and selected choices
- validate uploaded files
- normalize safe data in `prepareForValidation()`
- normalize phone numbers through approved support code
- use backed enums for allowed state values
- provide translation-key-based Arabic messages

Public order validation may be split into:

```text
StoreGuestOrderRequest
GuestCustomerDataRules
GuestAddressDataRules
GuestOrderItemRules
GuestOrderQuestionAnswerRules
GuestOrderAttachmentRules
```

Reusable rule objects are preferred when nested validation becomes difficult to
understand.

### 3.4 Actions

Actions represent named application use cases.

Examples:

```text
LoginAdminAction
LogoutAdminAction
CreateCustomerAction
UpdateCustomerAction
CreateServiceAction
UpdateServiceAction
CreateServiceOrderQuestionAction
UpdateServiceOrderQuestionAction
CreateGuestOrderAction
MatchOrCreateCustomerAction
CreateOrderAddressSnapshotAction
CalculateOrderPricingAction
PriceQuoteRequiredOrderItemAction
ChangeOrderStatusAction
CancelOrderAction
ReplyToContactMessageAction
```

Each Action should produce one clear business result.

Actions should primarily orchestrate complex use cases.

An Action is appropriate when the operation:

- coordinates multiple models or capabilities
- contains several ordered business steps
- requires a database transaction
- performs file-storage compensation
- triggers queued or external side effects after commit
- applies workflow or state-transition rules
- needs one explicit success or failure outcome

Actions should delegate reusable calculations, normalization, storage details,
and transition rules to focused Services.

Actions must not be created for simple CRUD operations that contain no
meaningful orchestration.

### 3.5 Services

Services are reserved for reusable business capabilities that may be used by
more than one Action, controller workflow, Job, or scheduled process.

Examples:

```text
PhoneNormalizer
OrderNumberGenerator
OrderPricingService
OrderStatusTransitionService
OrderAttachmentStorageService
ServiceQuestionAnswerValidator
LocalizedContentResolver
ApiLocaleResolver
```

A Service should:

- own one focused and reusable business capability
- avoid depending on HTTP request or response objects
- expose behaviour that can be tested independently
- remain reusable across multiple application use cases where appropriate

A Service must not:

- become a generic container for unrelated methods
- duplicate orchestration already owned by an Action
- exist only to forward data from a controller to an Eloquent model
- be created automatically for every controller or model

### 3.6 Simple CRUD Operations

Simple CRUD operations do not require an Action or Service when they only:

- use already validated request data
- create, update, view, activate, deactivate, or delete one model
- have no multi-model business workflow
- require no transaction orchestration
- require no complex file handling
- trigger no external side effects
- contain no reusable business rule
- contain no meaningful state-transition logic

In those cases, the controller may perform the small Eloquent operation
directly and return the approved API Resource or response object.

Possible examples include straightforward CRUD for:

```text
FAQs
Testimonials
Contact phone numbers
Contact emails
Social links
```

This rule does not mean controllers may contain growing business logic.

An Action must be introduced when the operation becomes a named business use
case involving orchestration, multiple steps, multiple models, transactions,
files, state changes, or side effects.

A Service must be introduced when focused reusable business logic appears.

The architecture must not create pass-through Actions or Services solely to
satisfy a folder convention.

Examples of unnecessary pass-through layers:

```text
Controller
  -> CreateFaqAction
      -> FaqService
          -> Faq::create(...)
```

when the complete behaviour is only:

```php
$faq = Faq::create($request->validated());
```

### 3.7 Eloquent Models

Models should define:

- relationships
- casts
- attributes
- small query scopes
- small predicates tied directly to model state

Examples of acceptable predicates:

```text
isActive()
isAvailable()
requiresQuote()
hasCompletePricing()
isTerminal()
```

Models must not orchestrate complete workflows such as guest order creation or
contact-email delivery.

### 3.8 Query Classes

Query classes are used selectively for complex read operations.

Recommended examples:

```text
ListPublicCategoriesQuery
ListPublicServicesQuery
ListAdminCategoriesQuery
ListAdminServicesQuery
ListAdminOrdersQuery
ListCustomersQuery
DashboardMetricsQuery
BestSellingServicesQuery
```

Simple relationship retrieval does not require a dedicated Query class.

Likewise, simple single-model CRUD does not require an Action, Service, or Query
class unless business complexity justifies it.

---

## 4. API Architecture

### 4.1 Versioning

All product APIs must be versioned:

```text
/api/v1/...
```

A breaking contract change requires:

- a new API version, or
- an explicitly approved compatibility strategy

### 4.2 Route Groups

The approved top-level route groups are:

```text
/api/v1/admin/auth/*
/api/v1/admin/*
/api/v1/public/*
```

### 4.3 Authentication Routes

Examples:

```text
POST  /api/v1/admin/auth/login
POST  /api/v1/admin/auth/refresh
POST  /api/v1/admin/auth/logout
GET   /api/v1/admin/auth/profile
PATCH /api/v1/admin/auth/profile
PUT   /api/v1/admin/auth/change-password
```

Authentication routes that require a logged-in administrator use:

```text
auth:sanctum
```

### 4.4 Administration Routes

Examples:

```text
/api/v1/admin/customers/*
/api/v1/admin/customer-addresses/*
/api/v1/admin/categories/*
/api/v1/admin/categories/{category}/subcategories/*
/api/v1/admin/services/*
/api/v1/admin/services/{service}/order-questions/*
/api/v1/admin/orders/*
/api/v1/admin/site-settings/*
/api/v1/admin/hero-sections/*
/api/v1/admin/contact-messages/*
/api/v1/admin/testimonials/*
/api/v1/admin/faqs/*
/api/v1/admin/featured-services/*
/api/v1/admin/dashboard/*
```

Administration routes use:

- `auth:sanctum`
- active-user middleware
- role or permission middleware
- policies or Action-level authorization where required

### 4.5 Public Routes

Examples:

```text
GET  /api/v1/public/services
GET  /api/v1/public/services/{service:slug}
GET  /api/v1/public/services/{service:slug}/order-questions
GET  /api/v1/public/categories
GET  /api/v1/public/categories/{category:slug}/subcategories
GET  /api/v1/public/site-settings
GET  /api/v1/public/hero-sections
GET  /api/v1/public/testimonials
GET  /api/v1/public/faqs
GET  /api/v1/public/featured-services
GET  /api/v1/public/best-selling-services
POST /api/v1/public/orders
POST /api/v1/public/contact-messages
```

Public mutation endpoints must have stricter rate limiting than ordinary public
read endpoints.

### 4.6 HTTP Standards

Endpoints must:

- use correct HTTP methods
- return JSON unless returning an approved file response
- use appropriate HTTP status codes
- use the shared `App\Enums\HttpStatusCode` enum for
  application-controlled response statuses
- use consistent validation, authentication, authorization, not-found,
  conflict, rate-limit, and server-error shapes
- use API Resources or approved transformers
- paginate list endpoints
- expose only documented filters, sorts, and includes
- reject unknown mutation fields when they are not part of the contract
- return stable machine-readable error codes

---

## 5. Authentication Architecture

### 5.1 Approved Authentication Model

The administration dashboard uses Laravel Sanctum personal access tokens.

Login returns:

- the Access Token in JSON
- a 15-minute access-token lifetime
- the exact authentication profile: `name`, `email`, `avatar`, `role`, and
  `permissions`

The authentication profile does not return a user ID. `role` is one string.
React stores the Access Token in memory only.

The custom Refresh Token:

- expires after 30 days
- is stored in an HttpOnly, Secure, host-only cookie
- is never returned in JSON
- uses the `refresh_tokens` table

Password recovery uses `password_resets`, sends recovery email synchronously
after commit, and uses opportunistic cleanup. Authentication Queue Jobs,
cleanup Commands, Cron tasks, and scheduler entries are not used.

Protected requests send:

```http
Authorization: Bearer {token}
```

### 5.2 Token Rules

The MVP rules are:

- only administrator users may authenticate
- inactive users cannot receive or use authenticated access
- invalid credentials return a generic Arabic message
- login is rate limited
- login enforces the approved one-session policy
- refresh rotates both Access and Refresh Tokens
- logout revokes all Access and Refresh Tokens
- password change and password reset revoke all Access and Refresh Tokens
- tokens are stored hashed by Sanctum
- raw tokens are not stored by the application
- role and permission checks remain required even when token abilities exist

### 5.3 Token Expiration

The Access Token lifetime is 15 minutes. The Refresh Token lifetime is 30 days.
Feature 001 controls the exact rotation, reuse-detection, and revocation
contract.

### 5.4 Customer Authentication Boundary

The MVP provides no customer:

- registration
- login
- token
- password reset
- current-user endpoint
- authenticated order endpoint

A customer database record is business data, not an authentication identity.

---

## 6. Authorization Architecture

Authorization is enforced through:

1. Sanctum authentication
2. active-user middleware
3. Spatie role and permission middleware
4. Laravel Policies where resource authorization is useful
5. business-rule validation inside Actions and Services
6. query scoping that prevents unauthorized data loading

Initial role:

```text
super-admin
```

Rules:

- the backend must not rely only on React UI visibility
- the backend must not rely only on `users.type`
- direct API requests must still be authorized
- route model binding must not bypass policy checks
- mass assignment must not allow status, ownership, price, or role changes
  outside approved actions
- public APIs must never expose administrator-only fields

---

## 7. Identifier Architecture

### 7.1 Internal Identifiers

Primary database identifiers use auto-incrementing unsigned big integers unless
a feature has an approved reason to use another identifier.

Examples:

```text
users.id
customers.id
services.id
orders.id
order_items.id
```

Internal IDs are suitable for:

- Eloquent relationships
- joins
- indexes
- internal administration APIs

### 7.2 Public Order Number

Every order has a generated public order number using:

```text
ORD-YYMMDD-NNNN
```

Example:

```text
ORD-260507-5896
```

Where:

```text
YY   = two-digit year
MM   = two-digit month
DD   = two-digit day
NNNN = four random digits
```

### 7.3 Collision Protection

Four random digits may repeat, especially as daily order volume grows.

The architecture therefore requires:

- a unique database index on `orders.order_number`
- generation through `OrderNumberGenerator`
- cryptographically secure random generation
- retry on duplicate-key collision
- a bounded retry count
- a controlled server error if uniqueness cannot be achieved
- generation using the configured business timezone

The random suffix is not treated as a secret.

### 7.4 Route Exposure

Public order submission responses return the public order number.

The frontend does not need the internal order ID unless an approved contract
requires it.

---

## 8. Customer Matching Architecture

### 8.1 Matching Key

Guest customers are matched by normalized phone number.

The flow is:

1. receive the submitted phone
2. normalize it to the approved canonical format
3. search using the normalized phone
4. reuse the customer when a match exists
5. create a new customer when no match exists
6. preserve submitted customer data in the order snapshot

### 8.2 Phone Normalization

Raw phone strings must not be compared directly.

Normalization produces E.164 and must account for approved input variations
such as:

- spaces
- hyphens
- brackets
- local prefixes
- international prefixes

Egypt is the default country when a local-format number has no explicit country
hint. Valid international numbers remain supported.

Recommended storage:

```text
phone
phone_normalized
```

The normalized value has a unique index because Feature 002 requires one
customer per canonical phone number. Customer email is nullable and unique
when present.

### 8.3 Existing Customer Behaviour

When the phone matches an existing customer:

- the existing customer is associated with the order
- guest-submitted customer data is preserved in the order snapshot
- the backend does not silently overwrite the canonical customer record
- an administrator may later update the customer record

This prevents guest input from unexpectedly changing administrator-maintained
customer data.

---

## 9. Customer Address Architecture

Recommended customer-address records:

```text
customer_addresses
- id
- customer_id
- label nullable
- address
- is_default
- created_at
- updated_at
- deleted_at nullable
```

The exact address-field structure is defined in the Customers specification.

A database constraint or transaction-safe application rule must ensure that a
customer has no more than one default address.

### 9.1 Order Address Snapshot

Orders preserve the submitted address independently from the mutable customer
address record.

Recommended architecture:

```text
order_addresses
- id
- order_id
- customer_name
- email
- phone
- address
- created_at
- updated_at
```

A one-to-one `order_addresses` table keeps snapshot data explicit and prevents
historical orders from changing when customer data changes.

---

## 10. Catalogue Architecture

### 10.1 Category and Subcategory Model

The catalogue uses one self-referencing category table or an equivalent
two-level relational model.

Recommended self-referencing structure:

```text
categories
- id
- parent_id nullable
- name
- slug
- is_active
- sort_order
- deleted_at nullable
- created_at
- updated_at
```

Interpretation:

```text
parent_id = null
-> root category

parent_id = root category id
-> subcategory
```

Rules:

- a root category has no parent
- a subcategory belongs to exactly one root category
- a root category may contain multiple subcategories
- a subcategory cannot contain another subcategory
- a category cannot reference itself
- circular parent relationships are invalid
- hierarchy depth is limited to two levels
- category and subcategory names and slugs follow approved uniqueness rules
- active state is enforced for both levels
- soft deletion must preserve historical references where required

### 10.2 Service-to-Subcategory Relationship

Each service belongs directly to one subcategory.

Recommended field:

```text
services.subcategory_id
```

The service table should not store both `category_id` and `subcategory_id`,
because duplicated hierarchy references can become inconsistent.

The root category is resolved through the service's subcategory.

Conceptual relationships:

```text
Category
  hasMany Subcategories

Subcategory
  belongsTo Category
  hasMany Services

Service
  belongsTo Subcategory
```

Service creation and update must validate that:

- `subcategory_id` exists
- the selected record is a subcategory, not a root category
- the subcategory is not deleted
- the parent root category exists
- hierarchy depth remains valid

### 10.3 Catalogue Eligibility

A service is publicly orderable only when all applicable catalogue records are
eligible.

The backend must validate:

- root category is active
- subcategory is active
- service publication status is active
- service availability status is available

Deactivating a root category prevents services in all child subcategories from
being orderable through normal public APIs.

Deactivating a subcategory prevents its services from being orderable through
normal public APIs.

Historical order snapshots remain valid when catalogue records are later
deactivated or soft deleted.

### 10.4 Service Statuses

Use PHP backed enums and string database columns.

Publication status:

```text
active
inactive
```

Availability status:

```text
available
unavailable
```

Native MySQL ENUM columns must not be used.

### 10.5 Pricing Types

Use a PHP backed enum:

```text
fixed
starting_from
quote_required
```

Rules:

- fixed services require an approved base price
- starting-from services require an approved base price
- quote-required services may have a null order-time unit price
- money values use precise decimal columns
- money values must not use floating-point columns
- the frontend never supplies an authoritative price

### 10.6 Duration

Recommended fields:

```text
duration_type
duration_min
duration_max nullable
duration_unit
```

Supported duration types:

```text
fixed
range
```

Supported units:

```text
hour
day
week
```

Validation examples:

- fixed duration requires `duration_min`
- fixed duration does not use `duration_max`
- range duration requires both minimum and maximum
- maximum must be greater than or equal to minimum

### 10.7 SEO

Service SEO fields include:

```text
slug
seo_title
seo_description
seo_tags
```

Slug rules:

- generated automatically when missing
- editable by the administrator
- globally unique among services
- no slug-history or redirect system in the MVP

### 10.8 Soft Deletes

Services use soft deletion.

A service referenced by historical order items must not be physically removed
through normal application flows.

Public APIs exclude soft-deleted services.

Administration APIs may expose trashed services only through an explicitly
approved filter or endpoint.

---

## 11. Service Media Architecture

Recommended table:

```text
service_media
- id
- service_id
- type
- disk
- path
- original_name
- mime_type
- extension
- size_bytes
- alt_text nullable
- is_main
- sort_order
- created_at
- updated_at
```

Media types:

```text
image
video
```

MVP rules:

- one main image per service
- multiple additional images
- one video maximum per service
- video is uploaded to the backend
- no video transcoding
- no video compression
- no automatic thumbnail generation
- no adaptive streaming
- images and video use the configured public disk
- file limits are centralized in `config/uploads.php`

Database and application rules must prevent:

- more than one main image
- more than one video
- unsupported MIME types
- unsupported extensions
- oversized files

---

## 12. Service Specification Architecture

Recommended table:

```text
service_specifications
- id
- service_id
- label
- value
- sort_order
- is_active
- created_at
- updated_at
```

Specifications:

- are ordered
- are display-only
- do not alter price
- are not copied as selectable options
- may be included in the service snapshot only when the detailed order
  specification requires it

---

## 13. Service Option Architecture

### 13.1 Normalized Tables

Use normalized relational tables:

```text
service_option_groups
service_option_values
```

Recommended group fields:

```text
service_option_groups
- id
- service_id
- label
- selection_type
- is_required
- is_active
- sort_order
- created_at
- updated_at
```

Recommended value fields:

```text
service_option_values
- id
- service_option_group_id
- value
- is_available
- is_active
- sort_order
- price_adjustment nullable
- created_at
- updated_at
```

### 13.2 Selection Types

The detailed feature specification defines supported selection types.

The architecture can support:

```text
single
multiple
```

Selected values may also contain a quantity where the option design allows it.

### 13.3 Why Normalized Tables

Relational option tables provide:

- enforceable relationships
- availability checks
- efficient administration
- explicit sorting
- easier future price adjustments
- easier validation
- easier reporting than one uncontrolled JSON field

### 13.4 Order-Time Option Snapshots

Use a normalized snapshot table:

```text
order_item_options
- id
- order_item_id
- service_option_group_id nullable
- service_option_value_id nullable
- group_label_snapshot
- value_snapshot
- quantity
- unit_price_adjustment_snapshot nullable
- total_price_adjustment_snapshot nullable
- created_at
- updated_at
```

The foreign keys may become null if catalogue records are later removed, while
snapshot fields preserve historical meaning.

---


## 14. Service Order Question Architecture

### 14.1 Final Responsibility

A service may define fields that the guest must complete while configuring an
order item.

These fields are represented as questions managed by the administrator.

The architecture supports:

```text
short_text
long_text
number
date
boolean
single_choice
multiple_choice
```

The exact approved list belongs in the Service Order Questions feature.

### 14.2 Question Definitions

Recommended table direction:

```text
service_order_questions
- id
- service_id
- question_key
- label_ar
- label_en
- help_text_ar nullable
- help_text_en nullable
- placeholder_ar nullable
- placeholder_en nullable
- input_type
- is_required
- is_active
- sort_order
- validation_config nullable JSON
- deleted_at nullable
- created_at
- updated_at
```

Rules:

- one question belongs to one service
- `question_key` is stable and untranslated
- labels and supporting content are bilingual
- required state is enforced by the backend
- validation configuration uses allow-listed keys
- arbitrary executable Laravel rules are not stored
- file upload is not a question type in the MVP
- questions referenced by historical orders are deactivated or soft deleted

### 14.3 Choice Definitions

Choice questions use:

```text
service_order_question_options
- id
- service_order_question_id
- option_key
- label_ar
- label_en
- is_active
- sort_order
- deleted_at nullable
- created_at
- updated_at
```

Choice IDs and keys remain machine-stable.

Translated labels are presentation content and are not trusted for ownership
validation.

### 14.4 Answer Validation

During order submission, the backend must:

- load active questions for each service
- validate required answers
- validate answer type
- validate allow-listed limits
- validate selected choice ownership
- reject inactive choices
- reject answers for another service
- reject duplicate answers
- preserve customer-entered content without automatic translation

### 14.5 Answer Snapshots

Answers belong to individual order items.

Recommended direction:

```text
order_item_question_answers
order_item_question_answer_options
```

Snapshots preserve:

- stable question key
- Arabic and English question labels
- input type
- required state
- Arabic and English choice labels
- submitted answer

Current question or choice edits must not rewrite historical orders.

---

## 15. Frontend Cart Boundary

The backend does not persist carts.

The Next.js application stores guest cart data in localStorage.

The backend does not provide:

- cart tables
- cart sessions
- cart ownership
- cart recovery
- cross-device carts
- cart merging
- abandoned-cart tracking

At order submission, the backend validates the complete proposed cart again.

The backend revalidates:

- service existence
- root-category eligibility
- subcategory eligibility
- service publication status
- service availability
- service quantity
- option-group ownership
- selected option values
- option availability
- option quantities
- active service-question definitions
- required and optional answers
- selected question-choice ownership
- pricing type
- current price
- file rules

Frontend-calculated totals are ignored.

---

## 16. Guest Order Upload Strategy

### 16.1 Approved MVP Strategy

The MVP uses one multipart order request:

```http
POST /api/v1/public/orders
Content-Type: multipart/form-data
```

This request contains:

- customer data
- address data
- order items
- selected options
- service-question answers
- selected question choices
- quantities
- item-specific files

### 16.2 Why Multipart Was Selected

A temporary-upload API would require:

- a separate upload endpoint
- temporary upload records
- upload tokens
- token expiration
- token ownership or request-binding rules
- scheduled cleanup
- consumed-token protection
- recovery rules for abandoned uploads

The project currently:

- has no customer accounts
- has no persistent cart
- expects files only during checkout
- does not yet require resumable uploads
- does not yet require very large files

A single multipart request is therefore the simpler MVP boundary.

### 16.3 Item-to-File Mapping

Every submitted order item must include a frontend-generated request reference.

Example conceptual structure:

```text
items[0][client_reference] = item-1
items[0][service_id] = 10
items[0][quantity] = 2
items[0][attachments][] = file-a.pdf
items[0][attachments][] = file-b.webp

items[1][client_reference] = item-2
items[1][service_id] = 20
items[1][quantity] = 1
items[1][attachments][] = file-c.docx
```

The request reference exists only to map uploaded files to the correct proposed
order item.

It is not a database identifier and is not trusted as ownership data.

### 16.4 When to Reconsider Temporary Uploads

A separate temporary-upload flow should be reconsidered only if the project
later requires:

- large files
- resumable uploads
- upload progress independent from checkout
- slow or unstable client connections
- upload reuse
- abandoned checkout recovery
- direct-to-cloud uploads

That change requires a separate architecture decision.

---

## 17. Order Creation Architecture

### 17.1 Main Action

Guest order creation is coordinated by:

```text
CreateGuestOrderAction
```

The Action delegates focused responsibilities rather than implementing all
logic directly.

Possible collaborators:

```text
PhoneNormalizer
MatchOrCreateCustomerAction
CreateOrAssociateCustomerAddressAction
OrderNumberGenerator
ValidateSubmittedServiceConfigurationAction
ServiceQuestionAnswerValidator
OrderPricingService
OrderAttachmentStorageService
CreateOrderAddressSnapshotAction
```

### 17.2 Order Creation Flow

1. validate the multipart request
2. normalize the customer phone
3. resolve or create the customer
4. resolve or create the submitted customer address
5. load all referenced services with their subcategories and root categories
   in a controlled query
6. reject missing, inactive, or deleted root categories
7. reject missing, inactive, or deleted subcategories
8. reject missing, inactive, or unavailable services
9. validate quantities
10. validate all option groups and values
11. reject unavailable option values
12. load active service questions and choices
13. validate required and optional question answers
14. reject answers or choices that do not belong to the service
15. calculate all determinable prices
16. generate a unique order number
17. create the order and preserve the resolved locale
18. create the address snapshot
19. create order items
20. create order-item option snapshots
21. create question, choice, and answer snapshots
22. store item attachments
23. create attachment metadata
24. calculate known and final totals
25. set the initial order pricing state and status
26. commit the transaction
27. return the order number and approved success response

### 17.3 Database Transactions

Use `DB::transaction()` for database state that must succeed or fail together.

The transaction includes:

- customer creation when required
- customer-address creation when required
- order creation
- address snapshot creation
- order-item creation
- option-snapshot creation
- question, choice, and answer snapshot creation
- attachment metadata creation
- total persistence

### 17.4 Filesystem Compensation

Database transactions cannot automatically roll back filesystem writes.

The order Action must therefore track every stored file path.

If the workflow fails:

- the database transaction rolls back
- all files stored during the failed request are deleted
- cleanup failures are logged
- no successful order response is returned

File-storage code must be centralized to make this compensation testable.

### 17.5 Transaction Boundaries

Rules:

- validate all possible input before starting the transaction
- preload required service and option records before expensive writes
- keep the transaction as short as practical
- do not send email inside the transaction
- do not dispatch work that may run before commit
- do not catch and suppress transaction exceptions
- use after-commit dispatch for queued side effects when required

---

## 18. Order and Order-Item Architecture

### 18.1 Recommended Order Fields

Conceptual fields include:

```text
orders
- id
- order_number
- customer_id
- status
- pricing_status
- locale
- known_subtotal
- total_amount nullable
- cancellation_reason nullable
- rejected_reason nullable
- admin_notes nullable
- created_at
- updated_at
```

Exact database fields are defined in the Orders feature specification.

### 18.2 Recommended Order-Item Fields

Conceptual fields include:

```text
order_items
- id
- order_id
- service_id nullable
- service_name_snapshot
- service_slug_snapshot
- pricing_type_snapshot
- base_unit_price_snapshot nullable
- final_unit_price nullable
- quantity
- line_total nullable
- duration_type_snapshot
- duration_min_snapshot
- duration_max_snapshot nullable
- duration_unit_snapshot
- customer_notes nullable
- created_at
- updated_at
```

### 18.3 Snapshot Rules

Order item snapshots are immutable through normal catalogue-update flows.

Changing a service later must not change:

- historical service name
- historical pricing type
- historical price
- historical duration
- historical selected options
- historical quantities
- historical line totals

Administrator order-pricing actions may update approved final pricing fields
without replacing the original base snapshot.

### 18.4 Question and Answer Snapshot Tables

Recommended answer fields:

```text
order_item_question_answers
- id
- order_item_id
- service_order_question_id nullable
- question_key_snapshot
- question_label_ar_snapshot
- question_label_en_snapshot
- input_type_snapshot
- is_required_snapshot
- answer_text nullable
- answer_number nullable
- answer_date nullable
- answer_boolean nullable
- created_at
- updated_at
```

Choice answers use:

```text
order_item_question_answer_options
- id
- order_item_question_answer_id
- service_order_question_option_id nullable
- option_key_snapshot
- option_label_ar_snapshot
- option_label_en_snapshot
- created_at
```

Customer answers are stored as submitted and are not translated automatically.

---

## 19. Quote-Required Pricing Architecture

### 19.1 Final Decision

Quote-required pricing is handled inside orders and order items.

The MVP does not introduce:

```text
quotes
quote_items
quote_versions
quote_approvals
quote_to_order conversions
```

### 19.2 Why a Separate Quote Entity Is Not Used

A separate quotation domain is useful when the product requires:

- a quotation before an order exists
- quotation versions
- customer approval or rejection
- quotation expiration
- negotiation
- quote-to-order conversion
- authenticated customer access
- downloadable quotation documents

The current project does not require those behaviours.

The approved business flow is:

1. the guest submits an order
2. the order may contain quote-required items
3. the administrator reviews the order
4. the administrator sets prices for those items
5. the backend recalculates the order
6. the order proceeds to payment outside the application

Creating separate quote tables would duplicate customer, item, option, and file
data without serving an approved MVP workflow.

### 19.3 Pricing State

Recommended item pricing state:

```text
not_required
awaiting_quote
priced
```

Rules:

- fixed and starting-from items may begin as `not_required` or another
  specification-approved state
- quote-required items begin as `awaiting_quote`
- quote-required item prices are nullable until set
- administrator pricing moves an item to `priced`
- line totals are recalculated by the backend

Recommended order pricing state:

```text
complete
requires_review
```

If any order item has incomplete pricing:

```text
orders.pricing_status = requires_review
orders.total_amount = null
```

The order may still store:

```text
known_subtotal
```

for determinable item totals.

When all items have final prices:

```text
orders.pricing_status = complete
orders.total_amount = recalculated final total
```

### 19.4 Feature Naming

Feature `008 Quote Requests` should be interpreted as:

```text
Quote Pricing and Review
```

It is not a separate pre-order quotation system.

Renaming the feature document is recommended to avoid misleading future
implementation.

---

## 20. Order Status Architecture

Initial statuses:

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

Use a PHP backed enum and a string database column.

Native MySQL ENUM must not be used.

### 20.1 Transition Service

All transitions are centralized in:

```text
OrderStatusTransitionService
```

The service validates:

- current status
- requested status
- administrator permission
- pricing completeness
- required reason
- terminal-state restrictions
- required timestamps or metadata

### 20.2 Indicative Flow

```text
pending
  -> awaiting_review
  -> awaiting_payment
  -> confirmed
  -> in_progress
  -> completed
```

Alternative transitions may include:

```text
pending -> rejected
pending -> cancelled

awaiting_review -> rejected
awaiting_review -> cancelled

awaiting_payment -> cancelled

confirmed -> cancelled

in_progress -> cancelled
```

The final transition matrix belongs to the Orders feature specification.

### 20.3 Cancellation Rules

Only an authorized administrator can cancel an order.

Cancellation requires a non-empty reason.

The reason is stored on the order because order status history is outside the
MVP.

An administrator may cancel an `in_progress` order when the business requires
it, but the reason remains mandatory.

Terminal states are:

```text
completed
cancelled
rejected
```

A terminal order cannot return to an active state in the MVP.

### 20.4 Rejection Rules

Only an authorized administrator may reject an order.

Rejection requires a reason when the feature specification approves it.

Rejected orders are terminal in the MVP.

### 20.5 No Status-History Table

The MVP does not introduce `order_status_histories`.

Consequences:

- only the current status is authoritative
- the system does not preserve every previous status
- the system does not report time spent in each status
- the system does not identify who performed every historical transition

The architecture must not claim those capabilities.

Cancellation and rejection reasons are stored directly on the order because
they remain operationally important.

---

## 21. Order-Item Attachment Architecture

### 21.1 File Types

Initial supported types:

```text
png
jpg
jpeg
webp
pdf
doc
docx
```

### 21.2 Recommended Metadata

```text
order_item_attachments
- id
- order_item_id
- disk
- path
- original_name
- stored_name
- mime_type
- extension
- size_bytes
- checksum nullable
- created_at
- updated_at
```

### 21.3 Storage Decision

Order-item attachments use the configured public filesystem disk in the MVP.

Because public-disk files do not provide authorization-based access control,
the architecture applies these mitigations:

- randomized non-predictable stored filenames
- non-user-controlled directories
- no directory listing
- raw storage paths are not returned by public order APIs
- attachment data is returned only through authorized administration APIs
- uploaded filenames are never used directly as stored filenames
- executable content is rejected
- server configuration must not execute uploaded files

This is the approved public-storage product decision. Any storage-model change
requires an approved architecture and feature amendment.

### 21.4 Validation

Every attachment must validate:

- successful upload
- allowed MIME type
- allowed extension
- maximum file size
- maximum files per order item
- maximum total request size
- valid mapped order item
- non-empty file

MIME validation and extension validation are both required.

### 21.5 Access

Public order creation responses do not expose attachment URLs.

Administration resources may expose approved file URLs or metadata only to
authorized administrators.

---

## 22. Contact Email and Queue Architecture

### 22.1 Why a Queue Is Needed

The Contact Us reply feature sends a real email.

Sending email directly during the HTTP request may:

- make the API response slow
- fail because of temporary SMTP problems
- couple database success to mail-server availability
- provide no retry mechanism
- create a poor administrator experience

The architecture therefore stores the reply first and sends the email
asynchronously.

### 22.2 Approved Queue Driver

The MVP uses:

```env
QUEUE_CONNECTION=database
```

Redis and Laravel Horizon are not required initially.

The database queue is sufficient for:

- low to moderate contact-reply email volume
- retrying temporary mail failures
- separating API response time from SMTP delivery
- keeping infrastructure simple

### 22.3 Reply Flow

1. administrator submits a reply
2. backend validates permission and content
3. reply data is stored
4. contact-message status becomes `replied`
5. transaction commits
6. `SendContactReplyMailJob` is dispatched after commit
7. a queue worker sends the email
8. failed jobs are available for retry and inspection

The database record is the authoritative reply record.

Email delivery failure does not roll back the saved reply.

### 22.4 Required Operations

Deployment must run a queue worker.

The project must define:

- retry count
- backoff
- timeout
- failed-job storage
- worker restart procedure during deployment

### 22.5 Queue Scope

The queue is not a general requirement for every action.

The MVP initially uses it for approved asynchronous email delivery.

Additional queued work requires a demonstrated need.

---

## 23. Event Architecture

Events are introduced only when they provide a real decoupling benefit.

Potential events:

```text
OrderCreated
OrderPriceCompleted
OrderStatusChanged
ContactMessageReceived
ContactMessageReplied
```

The MVP must not create empty events and listeners merely for architectural
appearance.

For Contact Us email delivery, either of these approaches is acceptable:

```text
ReplyToContactMessageAction
  -> dispatch SendContactReplyMailJob after commit
```

or:

```text
ContactMessageReplied event
  -> queued mail listener
```

The simpler direct after-commit Job dispatch is preferred until multiple
independent listeners are required.

---

## 24. Localization Architecture

### 24.1 Supported Locales

Active locales:

```text
ar
en
```

Arabic is the initial default.

English is the fallback.

Frontend consumers send:

```http
Accept-Language: ar
```

or:

```http
Accept-Language: en
```

Regional values such as `ar-EG` and `en-GB` may resolve to their supported base
locale.

### 24.2 Locale Resolution

Recommended components:

```text
ApiLocaleMiddleware
ApiLocaleResolver
LocalizedContentResolver
```

Rules:

- supported locales are configured centrally
- locale is resolved before validation
- missing or unsupported headers use the configured default
- fallback uses the configured English fallback
- responses may include `Content-Language`
- cacheable localized responses use `Vary: Accept-Language`
- queued Jobs reload or preserve the intended locale

### 24.3 Localized System Messages

Localized backend output includes:

- success messages
- validation messages
- authentication messages
- authorization messages
- business-rule errors
- transactional email wrappers
- approved enum labels

Translation keys remain internal and are not exposed in production responses.

### 24.4 Localized Catalogue and Site Content

The MVP supports bilingual Arabic and English content for approved fields,
including:

- categories and subcategories
- services
- specifications
- option groups and values
- service order questions and choices
- hero content
- FAQs
- approved site labels
- SEO titles and descriptions

For two fixed locales, explicit columns such as the following are preferred:

```text
name_ar
name_en
description_ar
description_en
label_ar
label_en
```

Public Resources return resolved content for the request locale.

Administration Resources may return both translations for editing.

### 24.5 Stable Machine Contract

The following remain stable and in English:

- JSON keys
- route paths
- request and response fields
- enum values
- permission and role identifiers
- error codes
- database columns
- slugs
- question keys
- option keys
- PHP class and method names

Localization must not change pricing, eligibility, authorization, ownership, or
workflow behaviour.

### 24.6 User-Generated Content and Historical Locale

The backend does not automatically translate:

- customer names
- addresses
- order notes
- service-question answers
- uploaded filenames
- Contact Us messages
- administrator free-form replies

Orders and Contact Us enquiries should preserve the resolved locale when needed
for later email or document generation.

Question and choice snapshots preserve the bilingual system labels required for
historical display.

---

## 25. Query, Filtering, and Search Architecture

`spatie/laravel-query-builder` is used for approved list endpoints.

Rules:

- allowed filters are explicitly declared
- allowed sorts are explicitly declared
- allowed includes are explicitly declared
- request-provided database column names are never trusted
- administration and public query contracts may differ
- sensitive administration relationships are not public includes
- pagination limits are capped

Examples:

```text
AllowedFilter::partial('name')
AllowedFilter::exact('publication_status')
AllowedFilter::exact('availability_status')
AllowedFilter::exact('subcategory_id')
AllowedFilter::exact('status')
```

Public and administration service queries must support the approved semantic
filters:

```text
categoryId
subcategoryId
```

Filter behaviour:

- `subcategoryId` returns services assigned directly to that subcategory
- `categoryId` returns services belonging to all subcategories under the
  selected root category
- a root category ID must not be accepted as a service subcategory ID
- an inactive hierarchy record must not make an otherwise hidden service
  publicly orderable

Complex list construction belongs in Query classes.

N+1 queries are not acceptable in important lists and detail endpoints.

---

## 26. Best-Selling Service Architecture

Best-selling services are derived from completed orders.

Recommended query component:

```text
BestSellingServicesQuery
```

The exact calculation is defined in the feature specification.

The query must explicitly define:

- completed-order status requirement
- whether ranking uses item quantity or line count
- time range
- result limit
- tie-breaking
- inactive service handling
- soft-deleted service handling

The frontend does not submit or store best-selling state.

---

## 27. Featured Service Architecture

Featured services are administrator-controlled.

Recommended relationship:

```text
featured_services
- id
- service_id
- sort_order
- created_at
- updated_at
```

A dedicated relationship table is preferred when:

- manual order matters
- featured membership changes independently
- metadata may be added later

A simple service boolean is acceptable only if the final feature confirms that
ordering and additional metadata are unnecessary.

Because manual sorting is required, the relationship table is the recommended
architecture.

---

## 28. Site Settings Architecture

Avoid one uncontrolled settings JSON object for all site data.

Use appropriate structures for:

- singular site settings
- repeated phone numbers
- repeated contact emails
- repeated social links
- hero items

Recommended capabilities:

```text
site_settings
contact_phone_numbers
contact_emails
social_links
hero_sections
```

Secrets must not be stored as editable site settings.

The following remain environment-managed:

- SMTP password
- database credentials
- Sanctum secrets
- filesystem credentials
- external service credentials

---

## 29. Cache Architecture

No application-level cache is required in the MVP.

The backend queries the database for:

- site settings
- FAQs
- hero content
- featured services
- public service lists

Caching may be introduced later only after:

- measuring repeated-query cost
- identifying a real bottleneck
- defining invalidation behaviour
- defining cache-key and locale behaviour

Redis is not introduced solely for theoretical future performance.

---

## 30. Transaction and Concurrency Strategy

Use transactions for workflows that must remain consistent.

Required transaction candidates:

- guest order creation
- administrator quote-price update and total recalculation
- default-address switching
- service creation with related initial records
- contact-reply persistence and status update
- featured-service reordering when performed as one operation

### 30.1 Row Locks

Use row-level locking when concurrent requests could produce invalid state.

Examples:

- two administrators price the same quote-required item simultaneously
- concurrent order-status updates
- concurrent default-address changes
- concurrent featured-service ordering updates

### 30.2 Order Pricing Lock

Pricing or status updates should lock the order row and required order items
while recalculating totals.

This prevents:

- stale totals
- overwritten pricing
- invalid status transitions
- incomplete pricing being treated as complete

### 30.3 Transaction Rules

- keep transactions short
- do not send email inside transactions
- dispatch queued work after commit
- do not make remote HTTP calls inside transactions
- do not suppress exceptions
- clean up filesystem writes when database work fails

---

## 31. Error Handling

The application normalizes API errors centrally for:

- validation
- unauthenticated requests
- forbidden actions
- missing resources
- duplicate-resource conflicts
- unavailable services
- unavailable option values
- incomplete quote pricing
- invalid order transitions
- rate limits
- file upload failures
- unexpected server errors

Production responses must not expose:

- stack traces
- SQL queries
- database errors
- filesystem paths
- mail credentials
- tokens
- secrets
- internal class names when unnecessary

Application-controlled HTTP status codes use:

```text
App\Enums\HttpStatusCode
```

Machine-readable business error codes remain stable and in English.

---

## 32. Security Baseline

Required controls:

- Sanctum token authentication
- backend role and permission checks
- active-user enforcement
- strict CORS configuration
- rate limiting
- password hashing through Laravel defaults
- validated and controlled mass assignment
- validation of nested order data
- backend price recalculation
- backend availability validation
- uploaded-file MIME and extension checks
- randomized stored filenames
- upload-directory execution prevention
- PII minimization in logs
- environment-managed secrets
- generic authentication errors
- no raw access-token logging
- no sensitive request-body logging
- no trust in localStorage cart data

Public endpoints that create records must be protected against abuse through
approved throttling rules.

The backend must fail closed when authentication, authorization, or data
eligibility cannot be established.

---

## 33. Testing Architecture

### 33.1 Test Framework

The project uses Pest on top of Laravel's PHPUnit-compatible testing
infrastructure.

Tests remain compatible with:

- Laravel feature testing
- database refresh helpers
- HTTP assertions
- storage fakes
- mail fakes
- queue fakes
- event fakes where used

Testing follows behaviour and risk rather than class count.

The project must not create pass-through classes merely to make isolated tests
possible.

The primary testing responsibility of each architectural type is:

| Architectural Type | Primary Test Style | Main Responsibility Under Test |
| --- | --- | --- |
| API Controller and route | Feature Test | Authentication, authorization, validation, HTTP status, and response contract |
| Complex Action | Focused application or Feature Test | Workflow orchestration, transactions, database changes, files, and side effects |
| Reusable Service | Unit Test | Reusable business rules, calculations, normalization, and transition logic |
| Simple CRUD | API Feature Test | Validation, authorization, persistence, and returned resource |
| Query Class | Feature or integration-style test | Filters, sorting, pagination, eager loading, and result visibility |
| Job or Mail workflow | Unit or Feature Test | Dispatch, retry-safe identifiers, and observable side effects |

Implementation details should not be mocked when the database, filesystem,
queue, or mail boundary is part of the behaviour being verified.

Mocks and fakes should be used selectively for external or asynchronous
boundaries, not to replace meaningful application integration.

### 33.2 Feature Tests

Feature tests cover:

- Sanctum login
- invalid login
- inactive administrator rejection
- current-token logout
- permission enforcement
- Arabic and English locale resolution
- bilingual public content resolution
- public category and subcategory visibility
- root-category creation
- subcategory creation under a root category
- invalid third-level category rejection
- circular category relationship rejection
- service assignment to a root category only
- service assignment to a root category and one of its subcategories
- subcategory assignment rejection when the parent root category is absent or
  mismatched
- category and subcategory service filtering
- inactive parent-category exclusion
- inactive subcategory exclusion
- public service visibility
- inactive service exclusion
- unavailable service rejection
- unavailable option rejection
- required service-question answer validation
- invalid question or choice ownership rejection
- bilingual question and choice snapshots
- customer matching by normalized phone
- order-number generation
- order-number uniqueness retry
- multipart guest order creation
- multiple order items
- item-specific attachment mapping
- fixed and quote-required items in one order
- frontend price tampering rejection
- total recalculation
- service snapshots
- option snapshots
- address snapshots
- attachment validation
- failed-order file cleanup
- administrator quote pricing
- invalid status-transition rejection
- cancellation-reason enforcement
- terminal-state protection
- contact reply persistence
- queued contact email dispatch
- best-selling service calculation
- list filtering, sorting, and pagination
- simple CRUD endpoints without requiring pass-through Action or Service tests

Simple CRUD behaviour is normally considered sufficiently covered when the API
Feature Test proves:

- authentication and permission enforcement
- validation behaviour
- database persistence
- resource transformation
- expected HTTP status and response envelope

A separate Action test or Service test must not be added when no Action or
Service exists and no reusable business logic is present.

Complex Actions should be tested through focused application tests that exercise
their real database and storage behaviour where that integration is part of the
use case.

### 33.3 Unit Tests

Unit tests cover reusable and deterministic business capabilities such as:

- phone normalization
- order-number formatting
- order-number retry logic
- pricing calculations
- quote-completion rules
- status-transition matrix
- cancellation rules
- locale resolution
- pure snapshot mapping

Unit tests are especially appropriate for focused Services and pure value
objects.

Unit tests must not be added merely to assert that a pass-through class forwards
the same data to Eloquent.

### 33.4 Integration Tests

Integration tests are used where necessary for:

- configured filesystem behaviour
- database queue behaviour
- mail transport integration in controlled environments
- production-specific storage adapters when introduced

### 33.5 Architecture-Critical Cases

The test suite must prove that:

- a subcategory belongs to one root category
- a third category level cannot be created
- circular category relationships are rejected
- a service may be unclassified, root-category only, or root-category plus one
  matching subcategory
- category filtering includes services from all child subcategories
- subcategory filtering includes only directly assigned services
- inactive root categories prevent child services from being ordered
- inactive subcategories prevent their services from being ordered
- Arabic and English locale resolution works
- bilingual public catalogue content resolves correctly
- required service questions are enforced
- invalid question and choice ownership is rejected
- customer answers are not automatically translated
- frontend-supplied prices do not control order totals
- inactive or unavailable services cannot be ordered
- unavailable option values cannot be selected
- order, option, question, choice, and answer snapshots preserve historical
  meaning
- attachments remain linked to the intended order item
- mixed priced and quote-required orders are accepted
- incomplete quote pricing keeps the final total incomplete
- an administrator cannot move an incompletely priced order to a state that
  requires complete pricing
- cancellation requires an administrator and a reason
- completed, cancelled, and rejected orders cannot return to active states
- guest customer matching uses normalized phone numbers
- an existing customer is not silently overwritten by guest input
- failed order creation does not leave orphaned attachment files
- Contact Us reply email dispatch occurs after persistence
- localized messages do not change English error codes or response keys

---

## 34. Backend Technology Stack

| Layer | Technology | Role |
| --- | --- | --- |
| Backend Framework | Laravel 13 | APIs, validation, authorization, storage, mail, queues, and testing |
| Runtime | PHP version supported by Laravel 13 and pinned by the repository | Application runtime |
| Database | MySQL | Primary transactional data store |
| Authentication | Laravel Sanctum | Super Admin personal access-token authentication |
| Authorization | Spatie Laravel Permission plus Policies and backend rules | Roles, permissions, and resource authorization |
| ORM | Eloquent | Relationships, persistence, query scopes, and resource loading |
| API Queries | Spatie Laravel Query Builder | Whitelisted filters, sorts, and includes |
| API Transformation | Laravel API Resources | Stable resource and collection output |
| Validation | Laravel Form Requests | Input validation and normalization |
| Business Logic | Actions and focused Services | Use-case orchestration and reusable domain logic |
| File Storage | Laravel Filesystem | Service media and order-item attachment storage |
| Email | Laravel Mail | Contact reply delivery |
| Queue | Laravel database queue | Asynchronous contact-reply email jobs |
| Scheduling | Not required for authentication | No authentication cleanup task or scheduler entry |
| Testing | Pest with Laravel's PHPUnit foundation | Feature, unit, and integration tests |
| Formatting | Laravel Pint | PHP code formatting |

---

## 35. Scope

### 35.1 In Scope

- API-first Laravel backend
- single MySQL database
- versioned JSON APIs
- Sanctum token authentication
- Super Admin authorization
- roles and permissions
- customers
- multiple customer addresses
- customer matching by phone
- root service categories
- service subcategories
- active two-level Category -> Subcategory hierarchy
- category and subcategory administration APIs
- category and subcategory public navigation
- category and subcategory service filtering
- service catalogue
- service media
- one uploaded service video
- service specifications
- service order options
- bilingual service order questions and choices
- order-item question and answer snapshots
- Arabic and English catalogue content
- public localStorage cart boundary
- multipart guest checkout
- orders and order items
- order snapshots
- option snapshots
- customer/address snapshots
- quote-required item pricing inside orders
- order-item attachments
- administrator status changes
- cancellation with reason
- site settings
- hero sections
- testimonials
- FAQs
- featured services
- best-selling services
- contact inbox
- queued real-email replies
- dashboard and reporting queries
- Arabic API-message localization
- automated backend tests
- API documentation

### 35.2 Out of Scope

- React implementation
- Next.js implementation
- customer registration
- customer authentication
- customer order history
- order tracking
- customer order editing
- customer order cancellation
- backend cart persistence
- temporary-upload API
- resumable uploads
- direct-to-cloud uploads
- authorization-protected attachment download endpoints
- separate quotation entities
- quote versions
- customer quote approval
- quote-to-order conversion
- online payment
- taxes
- multi-currency
- invoices
- refunds
- booking or scheduling
- order status history
- general audit log
- notifications module
- Redis
- Laravel Horizon
- application-level caching
- video transcoding
- video compression
- video thumbnail generation
- category hierarchy deeper than Category -> Subcategory
- third-level categories
- recursive unlimited category trees
- modular-monolith folders
- microservices
- WebSocket infrastructure
- advanced analytics
- AI features

---

## 36. Success Criteria

1. Super Admin authentication uses Sanctum personal access tokens.
2. Inactive administrators cannot authenticate or use protected routes.
3. Administration APIs enforce roles, permissions, and policies.
4. Public and administration APIs are separated under approved route groups.
5. Auto-incrementing internal IDs are used consistently.
6. Root categories and subcategories form an active two-level hierarchy.
7. Services support the approved unclassified, root-category-only, and
   root-category-plus-subcategory assignments.
8. Third-level and circular category relationships are rejected.
9. Public services support category and subcategory filtering.
10. Inactive categories or subcategories prevent affected services from being
    normally orderable.
11. Services may define bilingual required or optional order questions.
12. Required question answers are validated by the backend.
13. Question choices are validated against their question and service.
14. Customer answers belong to individual order items.
15. Question, choice, and answer snapshots preserve historical meaning.
16. Every order receives a unique `ORD-YYMMDD-NNNN` number.
17. Order-number collisions are protected by a unique index and bounded retry.
18. Guest customers are matched by normalized phone number.
19. Guest input does not silently overwrite existing customer records.
20. Customer and address snapshots remain unchanged when canonical data changes.
21. The public frontend may submit one multipart order containing multiple items,
    answers, and item-specific files.
22. Attachments map to the correct order item.
23. Inactive or unavailable services and options are rejected.
24. Frontend-supplied prices and totals do not control persisted totals.
25. Fixed, starting-from, and quote-required services may exist in one order.
26. Quote pricing remains inside order items.
27. Incomplete pricing preserves a null final total and explicit pricing state.
28. Administrator pricing recalculates item and order totals safely.
29. Only authorized administrators may change order status.
30. Cancellation requires a reason and terminal states remain terminal.
31. Failed order creation leaves no inconsistent records or untracked files.
32. One main image and one maximum video are enforced per service.
33. Public order responses do not expose attachment paths.
34. Contact replies are stored before email delivery is queued.
35. Temporary mail failure does not roll back the saved reply.
36. Arabic and English locales resolve consistently.
37. Public catalogue, questions, options, and approved site content are returned
    in the requested supported locale.
38. User-generated customer content is not automatically translated.
39. Stable English API keys, enum values, permission identifiers, and error
    codes remain unchanged across locales.
40. Lists use allow-listed filters, sorts, includes, and pagination.
41. Important endpoints avoid N+1 queries.
42. Architecture-critical rules are covered by automated Pest tests.
43. The backend remains a conventional Laravel monolith without unnecessary
    layers.

---

## 37. Documentation Boundaries

This document defines backend architecture and architectural responsibilities.

It does not replace:

- the project overview
- engineering constitution
- coding standards
- database standards
- API contract
- authentication standard
- authorization standard
- file standard
- localization standard
- feature specifications
- implementation plans
- task lists
- test plans
- deployment runbooks

Detailed feature specifications define exact:

- endpoints
- request fields
- response fields
- validation limits
- database columns
- permission names
- status-transition matrix
- file-size limits
- supported MIME types
- query parameters
- error codes

When a feature specification conflicts with this architecture baseline, the
conflict must be resolved explicitly and the affected documentation must be
updated before implementation.
