# Service Commerce Backend - Project Overview

**Document Status:** Draft Foundation  
**Document Type:** Product Overview  
**Last Updated:** 2026-07-28  
**Project Phase:** Documentation and Specification

---

## 1. Overview

The Service Commerce Backend repository is a backend-only platform for managing
and selling configurable services through versioned RESTful JSON APIs.

The system is built with Laravel 13 and MySQL and acts as the authoritative
backend source of truth for:

- administrator authentication
- roles and permissions
- customers and customer addresses
- service categories and subcategories
- services
- service images and uploaded videos
- service specifications
- configurable service order options
- service-specific order questions and customer answers
- service availability and publication state
- fixed-price, starting-from, and quote-required pricing
- guest order submission
- orders and order items
- order-item attachments
- site settings
- hero sections
- contact-us inbox and replies
- testimonials
- frequently asked questions
- featured services
- best-selling services
- administrative dashboard and reporting APIs
- Arabic and English backend API messages
- bilingual Arabic and English public catalogue content
- stable English API keys and machine-readable error codes

The backend will later be consumed by:

- a React-based administration dashboard
- a Next.js public website

The React dashboard and Next.js website are outside the implementation scope of
this repository.

For administrator authentication, the React dashboard and Laravel API may be
deployed on different registrable domains. The approved browser flow is direct
HTTPS from the dashboard to the Laravel API using a browser-public API base
such as `VITE_API_BASE_URL=https://api.backend-example.net/api/v1`. The backend
uses exact allow-listed CORS, Bearer access tokens, and rotating refresh tokens
returned in JSON. Same-origin proxy/BFF authentication, authentication cookies,
and CSRF refresh flows are not part of the approved design.

---

## 2. Problem Statement

Service-based businesses often need more than a simple product catalogue.

A service may include:

- multiple images
- an uploaded video
- a fixed price, a starting price, or no final price until review
- a fixed or ranged execution duration
- display-only specifications
- configurable order options
- service-specific required or optional questions
- temporarily unavailable option values
- customer-provided files
- different quantities
- manual administrative review before confirmation

Without a dedicated backend platform, the business may face:

- service information being managed inconsistently
- unclear distinction between service publication and service availability
- unreliable pricing when configurable options are involved
- missing or disorganized customer files
- order data changing when service information is later edited
- no structured way to handle services that require quotations
- no central place to manage website content and contact enquiries
- difficulty identifying featured and best-selling services
- weak authorization boundaries in the administration dashboard

This backend provides one operational system for managing the service catalogue,
guest customers, orders, content, and administration data.

---

## 3. Product Goals

1. Provide a structured backend catalogue for configurable services.
2. Allow each service to have images, an uploaded video, specifications, order
   options, service-specific order questions, pricing rules, availability,
   duration, and SEO data.
3. Allow guest visitors to submit orders without creating an account.
4. Allow one order to contain fixed-price, starting-from, and quote-required
   services together.
5. Preserve the original service name, price, selected options, duration, and
   customer details as order snapshots.
6. Allow customer files to be attached to the relevant order item.
7. Give the Super Admin complete control over customers, categories,
   subcategories, services, orders, content, and site settings.
8. Support future role expansion through `spatie/laravel-permission` without
   overcomplicating the MVP.
9. Provide stable, versioned APIs for the React administration dashboard and
   Next.js public website.
10. Support Arabic and English for backend messages and approved public
    catalogue content.
11. Preserve stable English API keys, field names, enum values, and
    machine-readable error codes in every locale.
12. Express success through verifiable backend and API behaviour rather than
    frontend presentation.

---

## 4. Repository Scope

This repository is backend-only.

It includes:

- Laravel application code
- database migrations and seeders
- API routes
- authentication and authorization
- validation
- business logic
- file handling
- API resources
- automated backend tests
- API documentation

It does not include:

- React administration dashboard implementation
- Next.js public website implementation
- frontend routing
- frontend forms
- frontend state management
- localStorage implementation
- frontend SEO rendering
- frontend tests
- frontend design system
- frontend accessibility implementation

The frontend applications are external API consumers.

---

## 5. System Actors

The MVP has two operational actor types.

### 5.1 Super Admin

The Super Admin is the only authenticated role in the initial MVP.

The Super Admin can:

- authenticate to the administration APIs
- manage customers and customer addresses
- manage categories and subcategories
- manage services and service content
- manage service media
- manage service specifications
- manage service order options
- manage service-specific order questions and choices
- view and manage orders
- set or update prices for quote-required order items
- manage order-item attachments where permitted
- manage website settings and content
- manage contact enquiries and replies
- view dashboard and reporting data

The system uses `spatie/laravel-permission` even though the MVP initially has
only one role.

### 5.2 Guest Customer

A guest customer does not have a user account and cannot authenticate.

A guest customer can:

- browse active and available services through public APIs
- add services to a frontend-managed local cart
- select service options
- choose service quantities
- answer required and optional service questions
- submit customer details
- select or provide an address during order submission
- upload permitted files for the relevant order items
- submit an order

A guest customer cannot:

- register
- log in
- view previous orders
- track an order
- update an order after submission
- update stored customer information
- update stored customer addresses
- upload additional files after order submission
- cancel an order through an authenticated customer portal

Customer and address changes are managed by the Super Admin.

---

## 6. User and Customer Model

Administrators and customers are different domain concepts.

### 6.1 Users

The `users` table represents authenticated system users.

For the MVP:

- only administrators exist in the `users` table
- the Super Admin is assigned through roles and permissions
- a `type` tiny integer column identifies the approved administrator type
- `type = 0` represents administrators
- customer authentication is not part of the MVP

Typical user data includes:

- name
- email
- password
- avatar
- status
- type

### 6.2 Customers

The `customers` table stores guest customer records independently from
authenticated users.

Typical customer data includes:

- name
- nullable email that is unique when present
- phone
- E.164 normalized phone used for matching, with Egypt as the default country

A customer record does not grant system access.

### 6.3 Customer Addresses

A customer may have multiple addresses.

The following rules apply:

- one customer may have many addresses
- one address may be marked as the default address
- only one default address may exist per customer
- customer addresses are managed by the Super Admin
- an order stores an address snapshot and must not depend only on the current
  customer address record

---

## 7. Service Categories and Subcategories

The service catalogue uses an active two-level hierarchy:

```text
Category
  -> Subcategory
      -> Services
```

A root category groups one or more subcategories, and each service belongs to
one subcategory.

The following MVP rules apply:

- a category is a root-level catalogue classification
- a category may contain multiple subcategories
- a subcategory belongs to exactly one category
- a subcategory may contain multiple services
- each service belongs to exactly one subcategory
- a service belongs indirectly to the parent category of its subcategory
- categories and subcategories may be active or inactive
- category and subcategory names and slugs must follow approved uniqueness
  rules
- the data model may use a nullable `parent_id`, where root categories have no
  parent and subcategories reference one root category
- only two catalogue levels are supported in the MVP
- a subcategory cannot contain another subcategory
- public service lists support approved category and subcategory filters
- inactive categories or subcategories must not expose orderable services
  through normal public catalogue APIs
- deleting or deactivating a category or subcategory must preserve historical
  order data and follow the approved service-visibility rules

---

## 8. Core Service Concept

A service is the primary sellable entity in the platform.

Each service may contain:

- name
- slug
- short description
- full description
- one subcategory and its parent category relationship
- publication status
- availability status
- pricing type
- price where applicable
- execution duration
- one main image
- multiple additional images
- an uploaded video
- display-only specifications
- configurable order options
- required or optional order questions configured from the dashboard
- SEO title
- SEO description
- SEO tags
- manual featured state
- sorting information where applicable
- soft-delete support

A service must remain representable in historical orders even after it is
updated, deactivated, or soft deleted.

---

## 9. Service Statuses

Service publication and service availability are separate concepts.

### 9.1 Publication Status

The initial publication statuses are:

- `active`
- `inactive`

An inactive service is not available through normal public catalogue APIs.

### 9.2 Availability Status

The initial availability statuses are:

- `available`
- `unavailable`

An active but unavailable service may still be visible where the public API
contract allows it, but it must not be ordered unless a future specification
explicitly defines another behaviour.

The backend must not model `active` and `inactive` as two independent boolean
columns.

---

## 10. Service Pricing

The initial service pricing types are:

- `fixed`
- `starting_from`
- `quote_required`

### 10.1 Fixed Price

A fixed-price service has a defined base price.

### 10.2 Starting-From Price

A starting-from service has a base price that represents the minimum initial
price.

The final amount may later depend on selected options, quantities, or
administrative review.

### 10.3 Quote-Required Price

A quote-required service does not have a final customer-visible price at order
submission time.

The following rules apply:

- the service can be added to the same order as priced services
- the guest can submit the order normally
- the order is created without requiring a separate quotation conversion flow
- the relevant order item may initially have no final price
- the Super Admin reviews the order
- the Super Admin may set or update the affected order-item price
- the order total is recalculated by the backend
- the detailed approval and pricing rules are defined in the quote-related
  feature specification

The MVP does not implement:

- currency conversion
- multi-currency pricing
- tax calculation
- tax-inclusive or tax-exclusive price modes
- online payment processing

Money values must still use a precise decimal database type and must never use
floating-point storage.

---

## 11. Service Duration

A service duration is informational and does not create a booking or appointment.

A service may use:

- a fixed duration
- a duration range

Supported duration units are:

- `hour`
- `day`
- `week`

Examples:

```text
Fixed:
3 days

Range:
2 to 5 days
```

The service duration is copied into the order-item snapshot at order creation.

The MVP does not include:

- appointment scheduling
- calendar availability
- time-slot selection
- visit booking
- staff scheduling

---

## 12. Service Media

A service may have:

- one main image
- multiple additional images
- one or more uploaded videos where approved by the feature specification

Media records may include:

- file path
- media type
- original filename where needed
- MIME type
- file size
- sort order
- alt text for images
- active state where approved

The backend must validate actual MIME types and file size limits.

Service media is public catalogue content and may be exposed to the Next.js
website through public APIs.

Detailed media limits, accepted video formats, image dimensions, and storage
disk rules are defined in the relevant feature and file standards.

---

## 13. Service Specifications

Service specifications are display-only label-value pairs.

Examples:

```text
Material: Premium Cotton
Service Level: Advanced
Delivery Type: Digital
```

Each specification may contain:

- label
- value
- sort order
- active state where approved

Specifications:

- do not directly change the service price
- are not customer selections
- are returned as part of service details
- are maintained by the Super Admin

---

## 14. Service Order Options

Service order options represent configurable values selected during ordering.

Examples include:

```text
Length:
- 20
- 30
- 40

Colour:
- Red
- Black
- White
```

An option group may include:

- label
- selection rules
- required or optional state
- sort order
- active state

An option value may include:

- value
- availability state
- sort order
- quantity behaviour where applicable
- future price adjustment data where approved

The following initial rules apply:

- a service may have multiple option groups
- one or more values may be selected where the option configuration allows it
- selected values may support quantities where defined
- an option value may be temporarily unavailable
- unavailable values must not be accepted during order creation
- the backend must validate every selected option against the service
- the backend must not trust option labels, prices, or availability sent by the
  frontend
- selected options are stored as an order-item snapshot
- the detailed price-impact behaviour will be finalized in the dedicated
  feature specification

---


## 15. Service Order Questions

Each service may define questions or required input fields that the customer
must answer while configuring that service for an order.

Examples include:

```text
What name should appear on the design?
What dimensions are required?
What is the preferred delivery date?
Describe the requested design.
Do you already have a logo?
Select the intended usage.
```

The Super Admin manages the questions from the administration dashboard.

A question may include:

- a stable machine key
- Arabic and English labels
- Arabic and English help text
- Arabic and English placeholders
- input type
- required or optional state
- active state
- sort order
- approved validation configuration
- selectable choices when applicable

Initial supported input types may include:

```text
short_text
long_text
number
date
boolean
single_choice
multiple_choice
```

The following rules apply:

- every question belongs to one service
- required questions must be answered for every submitted item using that
  service
- the backend enforces required answers and answer types
- the frontend required marker is not sufficient enforcement
- inactive questions are excluded from new orders
- choice values must belong to the submitted question
- question definitions and choice labels are managed in Arabic and English
- customer answers are not automatically translated
- questions normally collect information and do not alter price
- service order questions remain separate from configurable service options
- files remain order-item attachments and are not represented as question
  answers
- question, choice, and answer snapshots preserve historical order meaning

Detailed fields, validation configuration, and answer payloads belong in the
dedicated Service Order Questions feature specification.

---

## 16. Service Quantity

A guest may request more than one unit of the same service.

The following rules apply:

- each order item has a service quantity
- quantity must be validated by the backend
- totals must be calculated by the backend
- the frontend-provided total must never be trusted
- selected option quantities and service quantity are separate concepts
- duplicate cart selections may be merged or kept separate according to the
  final order contract
- the order snapshot must preserve the submitted and validated quantity

---

## 17. Frontend Cart Boundary

The cart is not persisted by this backend.

The public Next.js website will manage the guest cart using localStorage.

The backend does not provide:

- cart tables
- persistent guest cart records
- authenticated cart ownership
- cart synchronization between devices
- cart recovery
- cart merging after login

At order submission, the backend receives the proposed cart contents and must
revalidate:

- service existence
- service publication status
- service availability
- selected options
- option availability
- active service-question definitions
- required and optional submitted answers
- question-choice ownership and availability
- service quantity
- current service pricing data
- quote-required pricing state
- uploaded file rules

The frontend cart is not an authoritative source of price, status,
availability, or order totals.

---

## 18. Orders

An order is submitted by a guest customer and becomes the main operational
record for fulfilment.

An order may contain:

- order number
- customer reference
- customer information snapshot
- address snapshot
- current status
- priced order items
- quote-required order items
- subtotal where determinable
- final total where determinable
- customer note where approved
- administrative note where approved
- creation and update timestamps

An order may contain fixed-price, starting-from, and quote-required services
together.

The backend must support totals that are not final until the Super Admin reviews
quote-required items.

---

## 19. Order Statuses

The initial order statuses are:

- `pending`
- `awaiting_review`
- `awaiting_payment`
- `confirmed`
- `in_progress`
- `completed`
- `cancelled`
- `rejected`

The intended operational meaning is:

### Pending

The order was submitted and is awaiting initial processing.

### Awaiting Review

The order requires administrative review, including possible quotation or file
review.

### Awaiting Payment

The final amount is known and payment is expected outside the application.

### Confirmed

The order has been accepted and confirmed.

### In Progress

The service work has started.

### Completed

The service work has been completed.

### Cancelled

The order was cancelled administratively.

### Rejected

The order was rejected and will not proceed.

Detailed transition permissions and validation rules will be defined in the
order workflow specification.

The MVP does not provide a customer-facing action to change, cancel, or update
an order.

---

## 20. Order Items and Snapshots

Each order item represents one requested service configuration.

An order item stores references to the current service records where useful, but
must also preserve immutable snapshots of important order-time data.

The snapshot includes, where applicable:

- service name
- service slug or reference
- pricing type
- base price
- final item price
- service quantity
- selected options
- selected option quantities
- option labels and values
- question keys, labels, types, and required state
- customer answers and selected question choices
- duration type
- duration values
- duration unit
- customer-provided notes
- calculated line total where determinable

Snapshot data ensures that later service changes do not alter historical order
meaning.

The backend must recalculate all price-related values and must not trust totals
submitted by the frontend.

---

## 21. Order-Item Attachments

Guest customers may upload files during order submission.

Attachments belong to the relevant order item rather than only to the order.

Initial permitted file types include:

- PNG
- JPEG
- WebP
- PDF
- DOC
- DOCX

The following rules apply:

- files are uploaded during checkout or order submission
- each file is associated with a specific order item
- the guest cannot upload additional files after submission
- the guest has no order portal for later file management
- the Super Admin can access permitted attachment data through authorized
  administration APIs
- file extension alone must not be trusted
- actual MIME type and file size must be validated
- generated storage filenames must avoid unsafe user-provided names
- file limits are defined in the dedicated attachment specification
- failed multi-step order creation must not leave inconsistent database records

The MVP does not require a separate private-storage architecture standard, but
attachment exposure must still follow the approved API authorization rules.

---

## 22. Order Creation Flow

The initial backend flow is:

1. The guest browses the service catalogue through public APIs.
2. The Next.js website stores the selected services in localStorage.
3. The guest chooses quantities and service options.
4. The frontend displays the active questions configured for each selected
   service.
5. The guest answers the required and optional service questions.
6. The guest provides customer and address information.
7. The guest uploads permitted files for the relevant order items.
8. The frontend submits the proposed order to the backend.
9. The backend validates services, hierarchy eligibility, options, quantities,
   questions, answers, availability, pricing, and files.
10. The backend identifies or creates the customer record according to the
    approved customer matching rules.
11. The backend creates or associates the customer address.
12. The backend creates the order and stores the resolved order locale.
13. The backend creates order items and catalogue snapshots.
14. The backend creates question, choice, and answer snapshots.
15. The backend stores the order-item attachments.
16. The backend calculates all determinable prices and totals.
17. Quote-required items remain pending administrative pricing.
18. The order receives its initial approved status.
19. The backend returns a stable success response and order reference.
20. The frontend displays the order-success page.

Order creation must be transaction-safe where database consistency requires it.

File cleanup behaviour for failed transactions must be defined in the
attachment specification.

---

## 23. Payment Boundary

Payment is outside the application scope.

The backend does not process:

- credit cards
- payment gateways
- online payment sessions
- payment webhooks
- refunds
- taxes
- invoices
- payment-provider reconciliation

The `awaiting_payment` status represents an operational state only.

Any payment confirmation is performed administratively outside the platform and
reflected by the Super Admin through order status management.

---

## 24. Site Settings

The backend provides administration APIs for site-wide settings.

Initial settings may include:

- site name
- primary logo
- favicon
- primary address
- Google Maps URL or embed-related value
- contact email addresses
- contact phone numbers
- social media links
- default public contact information
- footer-related content where approved

Repeated settings should use structured records rather than a single
unvalidated JSON blob where filtering, ordering, activation, or independent
editing is required.

### 24.1 Social Links

A social link may contain:

- type
- label
- URL
- icon identifier where approved
- sort order
- active state

### 24.2 Phone Numbers

A phone record may contain:

- label
- phone number
- sort order
- active state

### 24.3 Contact Emails

A contact email record may contain:

- label
- email
- purpose where approved
- sort order
- active state

SMTP credentials and mail-server secrets are environment configuration and are
not managed through public site settings.

---

## 25. Hero Sections

The public website may use either:

- a fixed hero section
- a slider containing multiple hero items

The administration API must support the approved hero mode and hero items.

A hero item may contain:

- title
- subtitle
- image
- button text
- button URL
- sort order
- active state

The frontend decides how the configured hero content is rendered.

---

## 26. Contact Inbox

The public website provides a contact-us form.

A contact enquiry may contain:

- name
- email
- phone
- subject
- message
- current status
- administrative note where approved
- reply content
- reply timestamp
- replied-by administrator reference

Initial enquiry statuses may include:

- `new`
- `read`
- `replied`
- `archived`

The Super Admin can:

- list enquiries
- search and filter enquiries
- view enquiry details
- mark an enquiry as read
- reply to an enquiry
- archive an enquiry

The exact mail-delivery behaviour for replies will be defined in the contact
feature specification.

---

## 27. Testimonials

Testimonials are created and managed only by the Super Admin.

A testimonial contains:

- customer name
- rating
- message
- active state
- sort order where approved

The MVP does not include:

- customer-submitted testimonials
- testimonial moderation queues
- customer accounts linked to testimonials
- public review verification

---

## 28. Frequently Asked Questions

FAQs are managed as a single ordered list without categories.

Each FAQ contains:

- question
- answer
- sort order
- active state

The public API returns only active FAQs according to the approved ordering
rules.

---

## 29. Featured and Best-Selling Services

Featured services and best-selling services are separate concepts.

### 29.1 Featured Services

Featured services are selected manually by the Super Admin.

The administration API must support:

- adding a service to the featured list
- removing a service from the featured list
- controlling display order
- preventing invalid or unavailable references according to the final
  specification

### 29.2 Best-Selling Services

Best-selling services are calculated automatically from completed orders.

The calculation must be based on approved completed order-item data.

The detailed calculation must define:

- whether ranking uses ordered quantity or completed line count
- the reporting time range
- tie-breaking
- inactive or soft-deleted service behaviour
- maximum result count

The public frontend does not determine best-selling status.

---

## 30. SEO Scope

Service SEO is managed by the backend.

Each service may include:

- slug
- SEO title
- SEO description
- SEO tags

The Next.js frontend is responsible for rendering page metadata.

The MVP backend does not manage SEO fields for:

- home page
- contact page
- FAQ page
- checkout page
- order-success page
- policy pages

SEO for non-service pages is generated or managed by the frontend.

The MVP does not include backend-managed pages for:

- Privacy Policy
- Terms and Conditions
- Refund or Cancellation Policy
- About Us

---

## 31. Public Website API Support

The backend provides the data needed for the external Next.js website to build:

- home page
- service listing page
- category and subcategory navigation
- service filters, including approved category and subcategory filters
- service details page, including localized questions and choices
- contact-us page
- FAQ page
- checkout page
- order-success page

The backend does not provide authenticated customer APIs for:

- registration
- login
- customer profile
- customer addresses
- customer order list
- order details
- order tracking
- customer order cancellation
- customer attachment management

---

## 32. Administration API Capabilities

### 32.1 Authentication

- Super Admin login
- logout
- current authenticated user
- profile update where approved
- password change
- inactive user blocking
- protected administration routes
- Arabic and English user-facing API messages
- stable English error codes

### 32.2 Roles and Permissions

- Super Admin role
- permission-based route protection
- database seeders for approved roles and permissions
- future role extensibility
- no unnecessary role-management UI assumptions in the overview

### 32.3 Customer Administration

- create customers
- view customer details
- update customer details
- activate or deactivate customers
- manage multiple addresses
- set one default address
- view orders associated with a customer

### 32.4 Category and Subcategory Administration

- create root categories
- update root categories
- activate or deactivate root categories
- create subcategories under one category
- update subcategories
- move a subcategory to another category where approved
- activate or deactivate subcategories
- soft delete categories and subcategories where approved
- list, search, filter, sort, and paginate categories and subcategories
- prevent hierarchy levels deeper than category and subcategory
- prevent invalid parent relationships and circular references
- view services associated with a category or subcategory

### 32.5 Service Administration

- create services
- update services
- activate or deactivate services
- change service availability
- manage pricing type and price
- manage duration
- manage media
- manage specifications
- manage order options
- manage service order questions and question choices
- manage Arabic and English service content
- manage SEO data
- soft delete services
- restore services where approved
- list services with pagination, filtering, sorting, and searching

### 32.6 Order Administration

- list orders
- search, filter, sort, and paginate orders
- view order details
- view customer and address snapshots
- view order items
- view selected option snapshots
- view service-question and customer-answer snapshots
- view order-item attachments
- set or update quote-required item prices
- recalculate totals
- change order status
- add administrative notes where approved
- update customer data separately when required

### 32.7 Content Administration

- site settings
- social links
- phone numbers
- contact emails
- logos and favicon
- hero mode and hero items
- contact inbox and replies
- testimonials
- FAQs
- featured services
- dashboard and reporting APIs

---

## 33. Dashboard and Reporting

The administration dashboard APIs may include:

- total services
- active services
- inactive services
- available services
- unavailable services
- total customers
- total orders
- orders by status
- recent orders
- pending orders
- awaiting-review orders
- awaiting-payment orders
- in-progress orders
- completed orders
- cancelled orders
- rejected orders
- new contact enquiries
- featured services
- best-selling services

Advanced analytics are outside the MVP unless separately approved.

---

## 34. Localization

The backend supports two active locales:

```text
ar
en
```

Arabic is the initial default locale and English is the fallback locale.

Frontend consumers send the desired locale through:

```http
Accept-Language: ar
```

or:

```http
Accept-Language: en
```

Localization applies to:

- success messages
- validation messages
- authentication messages
- authorization messages
- business-rule errors
- transactional email templates
- approved human-readable enum labels
- category and subcategory content
- service content
- specifications
- order options
- service order questions and choices
- hero content
- FAQs
- approved SEO content

Public APIs return content resolved to the requested locale.

Administration APIs may return Arabic and English values together for editing.

The following remain stable and in English:

- JSON keys
- request and response field names
- route paths
- enum values
- permission and role identifiers
- machine-readable error codes
- database column names
- PHP class and method names
- slugs and stable question or option keys

User-generated content, including customer answers, notes, addresses, and
Contact Us messages, is not automatically translated.

Localization must not change pricing, validation ownership, authorization,
workflow rules, or the machine-readable API contract.

---

## 35. API and Backend Principles

- APIs are versioned under routes such as `/api/v1/...`.
- Authentication and authorization are enforced on the backend.
- Public endpoints expose only approved catalogue and content data.
- Administration endpoints require authenticated permission checks.
- Laravel Form Requests are used for request validation.
- Laravel API Resources or approved transformers are used for response
  formatting.
- Business logic belongs in Actions, Services, or approved domain classes
  rather than large controllers.
- `spatie/laravel-permission` manages roles and permissions.
- `spatie/laravel-query-builder` manages approved filters, search behaviour,
  sorting, and includes.
- Filter, sort, and include fields must be explicitly whitelisted.
- The backend recalculates prices and totals.
- The frontend is never trusted as the source of price or availability.
- Multi-step writes use transactions where consistency requires them.
- Historical order snapshots must not change when service data changes.
- Services, categories, and subcategories used by historical orders must not
  be destructively removed.
- File validation must check MIME type and approved limits.
- API responses must follow the shared global API conventions.
- Raw HTTP status literals should be centralized according to the approved
  project standards.

---

## 36. MVP Scope

### 36.1 In Scope

The initial MVP includes backend capabilities for:

- Super Admin authentication
- roles and permissions
- users
- customers
- customer addresses
- service categories and subcategories
- services
- service publication and availability
- service pricing
- service duration
- service images
- uploaded service videos
- service specifications
- service order options
- guest order submission
- quote-required order pricing
- orders
- order items
- customer and address snapshots
- service and option snapshots
- order-item attachments
- order status management
- site settings
- social links
- phone numbers
- contact emails
- logo and favicon
- fixed and slider hero content
- contact inbox
- contact replies
- testimonials
- FAQs
- featured services
- best-selling services
- dashboard and basic reports
- Arabic and English API messages
- bilingual category, subcategory, service, question, option, FAQ, hero, and
  approved site content
- service order questions and choices
- order-item question and answer snapshots
- stored order and Contact Us locale where required
- automated backend tests
- API documentation

### 36.2 Out of Scope

The following items are not part of the initial MVP:

- React implementation
- Next.js implementation
- customer accounts
- customer registration
- customer login
- customer password reset
- customer profile management
- customer order history
- order tracking
- customer order editing
- customer order cancellation
- customer file uploads after submission
- persistent backend cart
- online payments
- payment gateways
- payment webhooks
- taxes
- multi-currency
- invoices
- refunds
- booking and scheduling
- appointment time slots
- notifications
- audit logs
- order status history
- customer-facing order conversation
- wishlist
- coupons
- loyalty points
- public customer reviews
- FAQ categories
- advanced analytics
- privacy policy management
- terms and conditions management
- refund or cancellation policy management
- About Us page management
- category hierarchy deeper than one subcategory level
- external marketplace integrations
- mobile application implementation

---

## 37. Technology Overview

### Backend

- Laravel 13
- PHP version compatible with Laravel 13
- conventional Laravel monolith
- MySQL
- versioned RESTful JSON APIs under `/api/v1/...`
- Laravel Sanctum for administration authentication
- `spatie/laravel-permission`
- `spatie/laravel-query-builder`
- Laravel Form Requests
- Laravel API Resources or approved transformers
- Actions and Services for business logic
- Laravel filesystem abstraction
- queues, jobs, events, and listeners only where approved
- automated backend tests

### External API Consumers

- React administration dashboard
- Next.js public website

Those consumers are not implemented in this repository.

### API Contract

All endpoints must follow shared global conventions for:

- response envelopes
- success responses
- validation errors
- business errors
- authentication errors
- authorization errors
- pagination
- filtering
- searching
- sorting
- includes
- HTTP methods
- HTTP status codes
- machine-readable error codes
- timestamp formatting
- localization behaviour

---

## 38. Product Principles

- Keep the MVP focused on selling and administering services.
- Do not introduce customer authentication in the MVP.
- Keep customers separate from authenticated administrator users.
- Treat the frontend cart as untrusted input.
- Recalculate prices, availability, and totals on the backend.
- Preserve order-time snapshots.
- Keep publication and availability as separate states.
- Allow quote-required and priced services in the same order.
- Store attachments against the relevant order item.
- Use soft deletion where historical order integrity requires it.
- Avoid speculative domain concepts and unused complexity.
- Keep the service catalogue limited to the approved Category -> Subcategory
  -> Service hierarchy.
- Do not introduce a third category level without an approved product and
  architecture change.
- Keep frontend implementation outside backend feature specifications.
- Support Arabic and English user-facing messages and public content.
- Keep machine-readable contracts stable across locales.
- Keep service options separate from service order questions.
- Preserve question, choice, and customer-answer snapshots.
- Define detailed behaviour in feature specifications before implementation.
- Avoid premature enterprise features.

---

## 39. Success Criteria

The initial product is successful when:

1. The Super Admin can authenticate securely through the API.
2. Protected administration APIs enforce permissions.
3. Inactive administrators cannot access protected APIs.
4. Customers exist independently from authenticated users.
5. A customer can have multiple addresses with one default address.
6. The Super Admin can manage root categories and their subcategories.
7. Every subcategory belongs to exactly one root category.
8. Every service belongs to exactly one subcategory.
9. Public service lists support approved category and subcategory filtering.
10. The system prevents category hierarchy deeper than two levels.
11. The Super Admin can manage service publication and availability separately.
12. A service can use fixed, starting-from, or quote-required pricing.
13. A service can have media, specifications, options, duration, SEO data, and
    service-specific order questions.
14. The Super Admin can configure required and optional questions and their
    choices in Arabic and English.
15. The backend rejects missing or invalid answers to required service
    questions.
16. Question, choice, and answer snapshots preserve historical order meaning.
17. Unavailable services and option values cannot be ordered.
18. The Next.js frontend can submit a guest order without customer
    authentication.
19. One order can contain priced and quote-required services together.
20. The backend validates the complete proposed cart at order submission.
21. The backend does not trust frontend prices, totals, labels, or eligibility.
22. Order items preserve service, option, question, answer, duration, quantity,
    and pricing snapshots.
23. Orders preserve customer, address, and resolved locale data.
24. Guest-uploaded files are associated with the correct order items.
25. The Super Admin can review and price quote-required order items.
26. The backend can represent orders whose final total is initially incomplete.
27. The Super Admin can move orders through the approved statuses.
28. Payment processing remains outside the application.
29. The Super Admin can manage bilingual site settings and public content.
30. Contact enquiries can be viewed, managed, and replied to in the preserved
    interaction locale.
31. Featured services are selected manually.
32. Best-selling services are calculated from completed order data.
33. Public catalogue endpoints expose only approved localized content.
34. Lists support approved pagination, filtering, sorting, and searching.
35. Historical records are not destructively changed by catalogue updates.
36. Arabic and English messages preserve stable English API keys, enum values,
    and error codes.
37. Critical authentication, authorization, localization, pricing, order,
    question, snapshot, and file rules are covered by automated backend tests.
38. The backend provides a stable foundation for the external React dashboard
    and Next.js website.

---

## 40. Documentation Boundaries

This document defines the product at a high level.

It does not replace:

- the engineering constitution
- architecture decisions
- coding standards
- database standards
- global API contracts
- authentication standards
- authorization standards
- file-handling standards
- localization standards
- feature specifications
- implementation plans
- task lists
- test plans
- deployment documentation

When a detailed feature specification conflicts with this document, the
conflict must be resolved explicitly and the affected documentation must be
updated before implementation.

---

## 41. Planned Feature Specifications

The initial feature specification sequence is:

```text
001 Identity and Authentication
002 Roles and Permissions
003 Customers
004 Service Categories and Subcategories
005 Services Catalog
006 Service Media and Specifications
007 Service Order Options
008 Service Order Questions
009 Quote Pricing and Review
010 Orders and Order Items
011 Order Attachments
012 Site Settings
013 Hero Sections
014 Contact Inbox
015 Testimonials
016 FAQs
017 Featured and Best-Selling Services
018 Dashboard and Reports
```

For the MVP, the `009 Quote Pricing and Review` feature represents quote-required pricing
and administrative review within the order flow. It does not require a separate
customer quotation account, separate customer portal, or mandatory quote-to-order
conversion entity unless the detailed feature specification later approves one.

---

## 42. Next Documentation Steps

1. Approve and maintain this backend-only project overview.
2. Define the engineering constitution for the repository.
3. Define architecture and coding standards.
4. Define global API conventions.
5. Define database, file, localization, testing, and security standards.
6. Create feature specifications in the approved sequence.
7. Generate implementation plans and tasks only after each feature
   specification is approved.
