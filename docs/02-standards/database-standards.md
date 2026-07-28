# Service Commerce Backend — Database Standards

## Purpose

This document defines the MySQL database design, naming, migrations,
relationships, constraints, indexing, integrity, privacy, concurrency, file
metadata, snapshots, and database-testing standards for the Service Commerce
Backend.

The project uses one primary MySQL database.

It is:

- a single-business application
- not a multi-tenant SaaS database
- backend-only
- guest-order based
- not a customer-authentication database model
- not an online payment database model
- not a booking or scheduling database model

These standards apply to:

- migrations
- Eloquent models
- factories
- seeders
- Actions
- Services
- Query classes
- commands
- Jobs
- reports
- imports
- exports
- integration code
- automated database tests

Feature specifications define exact tables and columns for each feature.

This document defines the mandatory global database rules those features must
follow.

---

## 1. Database Architecture Scope

### 1.1 Final Decision

```text
Database engine: MySQL
Database model: Single application database
Internal primary keys: Auto-incrementing unsigned big integers
Tenancy: Not applicable
Authenticated identities: users
Guest business customers: customers
Public order reference: ORD-YYMMDD-NNNN
Category hierarchy: Category -> Subcategory -> Services
Customer authentication: Outside the MVP
Payment persistence: Outside the MVP
```

### 1.2 Core Data Areas

The database may store approved records for:

- users
- Sanctum personal access tokens
- roles and permissions
- customers
- customer addresses
- root categories
- subcategories
- services
- service media
- service specifications
- service order options
- bilingual service-specific order questions
- bilingual service question choices
- orders
- order address snapshots
- order items
- order item option snapshots
- order item question-answer snapshots
- order item attachments
- site settings
- phone numbers
- contact emails
- social links
- hero sections
- testimonials
- FAQs
- featured services
- Contact Us enquiries
- Contact Us replies
- database queue records
- failed Jobs
- dashboard and reporting query data

The database MUST NOT introduce unapproved tables for:

- customer authentication
- persistent carts
- separate quotations
- quote versions
- quote-to-order conversion
- online payments
- payment webhooks
- taxes
- currencies
- invoices
- refunds
- appointments
- booking slots
- order status history
- general audit logs
- notification preferences
- loyalty points
- coupons
- wishlists
- third-level categories
- unlimited category trees

### 1.3 Authoritative Data Boundary

The database and backend are authoritative for:

- category and subcategory relationships
- service publication
- service availability
- service pricing type
- service price
- service duration
- service option ownership
- service option availability
- bilingual service order-question definitions
- required question state
- order totals
- order status
- pricing status
- snapshots
- attachment metadata

Frontend-localStorage data is never authoritative.

---

## 2. Related Documents

Database work MUST comply with:

- `AGENTS.md`
- `.specify/memory/constitution.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- `docs/02-standards/api-standards.md`
- `docs/02-standards/code-standards.md`
- the active feature `spec.md`, `plan.md`, and `tasks.md`

When these documents conflict, implementation MUST stop until the conflict is
resolved.

Do not silently create a schema that changes approved product behaviour.

---

## 3. Database Naming Conventions

### 3.1 Tables

Table names MUST:

- be plural
- use `snake_case`
- describe the domain clearly
- avoid prefixes such as `tbl_`
- avoid technical abbreviations when a clear domain term exists

Good:

```text
users
customers
customer_addresses
categories
services
service_media
service_specifications
service_option_groups
service_option_values
service_order_questions
service_order_question_options
orders
order_addresses
order_items
order_item_options
order_item_question_answers
order_item_question_answer_options
order_item_attachments
contact_messages
contact_message_replies
featured_services
```

Bad:

```text
tbl_service
Service
svc_q
productQuestionTbl
orderData
common_values
```

### 3.2 Columns

Columns MUST:

- use `snake_case`
- use explicit names
- avoid unclear abbreviations
- include units where ambiguity exists
- represent one concept

Good:

```text
subcategory_id
phone_normalized
pricing_type
duration_min
duration_max
size_bytes
known_subtotal
total_amount
question_label_ar_snapshot
answer_number
created_at
```

Bad:

```text
subcat
ph_norm
type
dur
size
amt
q_text
ans
```

### 3.3 Foreign Keys

Foreign keys use the singular relation name followed by `_id`.

Examples:

```text
customer_id
subcategory_id
service_id
service_option_group_id
service_option_value_id
service_order_question_id
service_order_question_option_id
order_id
order_item_id
replied_by_user_id
```

### 3.4 Index Names

Laravel-generated names are acceptable when they are clear and remain within
MySQL identifier limits.

Custom index names use:

```text
idx_{table}_{purpose}
uniq_{table}_{purpose}
fk_{table}_{relation}
```

Examples:

```text
idx_services_subcategory_status
idx_orders_status_created
idx_customers_phone_normalized
uniq_orders_order_number
uniq_service_questions_key
uniq_order_item_question_answer
```

### 3.5 Constraint Names

Explicit names should be used when:

- generated names are unclear
- generated names are too long
- a database exception must be mapped to a specific business rule
- the protected invariant is operationally important

Constraint names must describe the invariant.

---

## 4. Primary Keys and Public Identifiers

### 4.1 Internal Primary Keys

Use Laravel unsigned big-integer IDs:

```php
$table->id();
```

UUID or ULID primary keys MUST NOT be introduced by default.

### 4.2 Numeric API Identifiers

Numeric IDs may be exposed through authorized APIs when required.

Security relies on:

- authentication
- permissions
- resource authorization
- route-parent validation
- business-state validation

Security MUST NOT rely on hiding sequential IDs.

### 4.3 Public Order Number

Every order has a stable public reference:

```text
ORD-YYMMDD-NNNN
```

Example:

```text
ORD-260728-5896
```

Recommended column:

```php
$table->string('order_number', 30)->unique();
```

Rules:

- unique at database level
- generated by the backend
- never accepted from the frontend
- generated using the configured business timezone
- random suffix uses secure random generation
- duplicate-key collision triggers bounded retry
- generation MUST NOT rely on an unprotected `MAX(id) + 1`
- order number remains unchanged after creation
- order numbers are never reused

### 4.4 External Integration Identifiers

UUID or ULID columns may be added later only when an approved feature requires:

- externally shared callbacks
- webhook deduplication
- public signed links
- integration event identifiers

Do not add them speculatively.

---

## 5. Data Type Standards

### 5.1 Strings

Choose meaningful lengths.

Examples:

```php
$table->string('name', 150);
$table->string('slug', 180);
$table->string('email', 191)->nullable();
$table->string('phone', 30);
$table->string('phone_normalized', 30);
$table->string('order_number', 30);
$table->string('mime_type', 150);
$table->string('extension', 20);
$table->string('question_key', 100);
```

Do not default every string to `255` without considering the domain.

### 5.2 Text

Use:

- `text()` for descriptions, FAQ answers, testimonials, enquiry messages,
  service questions, help text, and moderate notes
- `longText()` only when the approved feature expects genuinely large content

Do not store file binaries in text or blob columns by default.

### 5.3 Boolean Values

Boolean names must be positive and explicit.

Examples:

```text
is_active
is_available
is_required
is_default
is_main
is_featured
is_replied
```

Example:

```php
$table->boolean('is_active')->default(true);
```

Avoid negative names such as:

```text
is_not_active
not_required
disable_visibility
```

Use an enum when the state has more than two meanings or participates in a
workflow.

### 5.4 Dates and Times

Rules:

- store timestamps in UTC
- return API timestamps in ISO 8601 UTC
- convert to display timezone outside the database
- do not store display-formatted dates
- use nullable timestamps only when absence is a valid state

Examples:

```php
$table->timestamp('replied_at')->nullable();
$table->timestamp('cancelled_at')->nullable();
$table->timestamp('completed_at')->nullable();
```

The date encoded in `order_number` uses the configured business timezone.

That does not change the UTC timestamp storage rule.

### 5.5 File Sizes

Store file size as:

```php
$table->unsignedBigInteger('size_bytes');
```

This keeps metadata consistent across image, video, document, and future cloud
storage adapters.

### 5.6 IP Addresses

When operationally required:

```php
$table->string('ip_address', 45)->nullable();
```

Do not store IP addresses without an approved operational or security purpose.

### 5.7 Sort Order

Ordered administrative data uses an unsigned integer:

```php
$table->unsignedInteger('sort_order')->default(0);
```

Examples:

- category order
- subcategory order
- media order
- specification order
- question order
- question-option order
- FAQ order
- testimonial order
- featured-service order


### 5.8 Bilingual Content Columns

The active content locales are:

```text
ar
en
```

For fixed two-locale, queryable public content, use explicit language-suffixed
columns.

Examples:

```text
name_ar
name_en
description_ar
description_en
label_ar
label_en
value_ar
value_en
help_text_ar
help_text_en
placeholder_ar
placeholder_en
seo_title_ar
seo_title_en
seo_description_ar
seo_description_en
```

Rules:

- public Resources resolve one locale
- administration Resources may return both translations
- stable slugs, keys, enum values, and IDs remain untranslated
- core bilingual fields are not stored in one uncontrolled translation JSON
  blob
- active or public records satisfy feature-defined translation completeness
- nullable translated fields are allowed only when the feature defines them as
  optional
- customer-generated content is not duplicated into translation columns
- Arabic is the initial application default and English is the fallback

---

## 6. Money Standards

### 6.1 Fixed Precision

Money MUST use fixed-precision decimal columns.

Recommended baseline:

```php
$table->decimal('base_price', 12, 2)->nullable();
$table->decimal('known_subtotal', 12, 2)->default(0);
$table->decimal('total_amount', 12, 2)->nullable();
```

Exact precision may be increased by the approved feature if required.

### 6.2 Prohibited Types

Do not use:

```text
FLOAT
DOUBLE
REAL
```

for money.

### 6.3 Null and Zero

`null` and zero have different meanings.

Examples:

```text
base_price = null
```

may mean a quote-required service has no catalogue price.

```text
total_amount = null
```

may mean order pricing is incomplete.

```text
total_amount = 0.00
```

means a known zero amount.

Do not use zero as a substitute for unknown price.

### 6.4 Currency Boundary

Currency and multi-currency are outside the MVP.

Do not add:

```text
currency_code
exchange_rate
tax_amount
tax_rate
```

without an approved product change.

### 6.5 Calculation Authority

Persisted totals must be calculated by backend code from authoritative database
records.

Do not persist totals received from the frontend as authoritative values.

---

## 7. Enum and Status Standards

### 7.1 PHP Backed Enums

Controlled business values use PHP backed enums.

Recommended string-backed enums:

```text
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
enum ServiceOrderQuestionType: string
{
    case SHORT_TEXT = 'short_text';
    case LONG_TEXT = 'long_text';
    case NUMBER = 'number';
    case DATE = 'date';
    case BOOLEAN = 'boolean';
    case SINGLE_CHOICE = 'single_choice';
    case MULTIPLE_CHOICE = 'multiple_choice';
}
```

The exact approved types belong in the related feature specification.

### 7.2 Database Columns

Persist API-visible business states in bounded string columns.

Example:

```php
$table->string('pricing_type', 30)->index();
$table->string('status', 30)->index();
$table->string('input_type', 30);
```

### 7.3 Native MySQL ENUM

Native MySQL `ENUM` columns are prohibited unless an explicit architecture
decision approves them.

Reasons:

- schema evolution friction
- duplicated rules between PHP and MySQL
- deployment risk when adding values
- weaker portability and testing flexibility

### 7.4 Validation and Casting

- Cast controlled columns to PHP enums in Eloquent.
- Validate using Laravel Enum rules or approved mappings.
- Use enum cases in seeders, factories, tests, Actions, and Services.
- Do not scatter raw status strings.

### 7.5 HTTP Status Enum

`App\Enums\HTTP_RESPONSE_CODE` is an application-code concern and is not
persisted in business tables.

Do not create database columns for HTTP status codes unless an integration
feature explicitly requires response logging.

---

## 8. Users, Roles, and Authentication Data

### 8.1 Users Table

The `users` table stores authenticated administrator identities.

Typical fields:

```text
id
name
email
password
avatar nullable
status
type
email_verified_at nullable
last_login_at nullable
remember_token nullable
created_at
updated_at
deleted_at nullable
```

Rules:

- customers are not stored in `users`
- only approved administrator user types authenticate
- `type = 0` represents administrator classification in the MVP
- `type` does not replace roles and permissions
- role data is managed by `spatie/laravel-permission`
- inactive users cannot authenticate or use protected APIs

### 8.2 Email

Rules:

- normalize email according to the authentication standard
- enforce database uniqueness
- do not reveal email existence during login failure
- preserve one canonical stored value unless a future requirement needs a
  separate display value

### 8.3 Password

- Store only Laravel-hashed passwords.
- Never store plaintext passwords.
- Never store reversible passwords.
- Never store passwords in audit, metadata, or queue payload columns.

### 8.4 Sanctum Tokens

Sanctum owns the personal access-token table.

Do not create a duplicate custom token table unless an approved authentication
architecture replaces Sanctum.

### 8.5 Roles and Permissions

Use Spatie package tables.

Do not duplicate permission state into:

```text
users.role
users.permissions
users.is_super_admin
```

unless a separately approved compatibility requirement exists.

---

## 9. Customer Data Standards

### 9.1 Separate Customers Table

Guest business customers are stored separately from authenticated users.

Recommended core direction:

```text
customers
---------
id
name
email nullable
phone
phone_normalized
created_at
updated_at
deleted_at nullable
```

A customer record does not grant authentication access.
Customer email is nullable and unique when present. Customer records do not
have an active-status field in Feature 002.

### 9.2 Phone Matching

Customer matching uses:

```text
phone_normalized
```

Rules:

- normalize before lookup
- normalize to E.164
- interpret local numbers using Egypt as the default country
- store original or display phone separately
- enforce a unique constraint on normalized phone
- do not compare raw phone strings directly
- do not silently overwrite an existing customer with guest-submitted data

### 9.3 Customer Snapshot Rule

Orders preserve submitted customer details independently from the current
customer record.

Updating a customer must not change historical orders.

### 9.4 Customer Deletion

Feature 002 uses soft deletion for customer records. Controlled anonymization
requires a separately approved legal-retention decision.

Do not physically delete a customer referenced by historical orders through a
normal CRUD endpoint.

---

## 10. Customer Address Standards

Recommended direction:

```text
customer_addresses
------------------
id
customer_id
label nullable
address
is_default
created_at
updated_at
deleted_at nullable
```

The exact address structure belongs in the Customers feature.

Rules:

- one customer may have multiple addresses
- one address belongs to one customer
- one customer may have no more than one default address
- address changes are administrator-managed
- submitted order address is copied into an order snapshot
- route-parent ownership must be validated

### 10.1 One Default Address

A composite unique index on:

```text
customer_id, is_default
```

is NOT sufficient because it would also prevent multiple non-default addresses.

Approved strategies include:

1. a transaction-safe application rule using row locks
2. a nullable generated slot column, where only the default row has a
   non-null marker, combined with a unique index

Conceptual generated-slot design:

```text
default_slot = 1 when is_default = true
default_slot = null otherwise
unique(customer_id, default_slot)
```

Use generated-column enforcement only after verifying target MySQL support and
migration behaviour.

Tests remain mandatory.

---

## 11. Category and Subcategory Standards

### 11.1 Two-Level Hierarchy

The approved hierarchy is:

```text
Category
  -> Subcategory
      -> Services
```

No third level is supported.

### 11.2 Categories Table

Recommended self-referencing structure:

```text
categories
----------
id
parent_id nullable
name_ar
name_en
slug
description_ar nullable
description_en nullable
image_path nullable
is_active
sort_order
created_at
updated_at
deleted_at nullable
```

Interpretation:

```text
parent_id = null
-> root category

parent_id = root category id
-> subcategory
```

### 11.3 Required Rules

- A root category has no parent.
- A subcategory belongs to exactly one root category.
- A subcategory cannot contain another subcategory.
- A category cannot reference itself.
- Circular relationships are invalid.
- Hierarchy depth is limited to two levels.
- Category and subcategory slugs follow approved uniqueness rules.
- Active categories and subcategories provide required Arabic and English names.
- Public Resources resolve `name` and `description` by request locale.
- Inactive hierarchy records affect public service eligibility.
- Soft-deleted hierarchy records are excluded from normal public queries.
- Historical orders remain meaningful after hierarchy changes.

### 11.4 Database-Enforceable Rules

The database can directly enforce:

- valid parent foreign key
- self-reference deletion behaviour
- unique slug rules
- `parent_id != id` through a CHECK constraint when verified on the target
  MySQL version

The database cannot directly enforce all cross-row rules such as:

- parent must be a root category
- maximum hierarchy depth
- circular chains involving multiple rows

Those rules require:

- validated application workflow
- transactions where parent changes can race
- automated tests

### 11.5 Services Relationship

Services should store:

```text
subcategory_id
```

The referenced category record must be a subcategory.

Do not store both:

```text
category_id
subcategory_id
```

on `services`.

Duplicated hierarchy references can become inconsistent.

The root category is resolved through the subcategory relation.

---

## 12. Service Table Standards

Recommended core direction:

```text
services
--------
id
subcategory_id
name_ar
name_en
slug
short_description_ar nullable
short_description_en nullable
description_ar
description_en
publication_status
availability_status
pricing_type
base_price nullable
duration_type
duration_min
duration_max nullable
duration_unit
seo_title_ar nullable
seo_title_en nullable
seo_description_ar nullable
seo_description_en nullable
seo_tags_ar nullable
seo_tags_en nullable
sort_order
created_at
updated_at
deleted_at nullable
```

Exact fields belong in the Services Catalog specification.

Rules:

- every service belongs to one subcategory
- service slug is unique according to the approved contract
- publication and availability are separate
- quote-required base price may be null
- fixed and starting-from prices follow feature validation
- service duration is informational
- soft deletion preserves order history
- service updates do not rewrite order snapshots
- active services satisfy required Arabic and English content
- public service content resolves according to the request locale
- stable slug and machine values remain untranslated

### 12.1 Service Eligibility

A service is normally orderable only when:

- root category is active
- subcategory is active
- service publication status is active
- service availability status is available
- selected options remain eligible
- required service questions can be answered validly

Eligibility must be revalidated during order creation.

---

## 13. Service Media Standards

Recommended table:

```text
service_media
-------------
id
service_id
type
disk
path
original_name
mime_type
extension
size_bytes
alt_text nullable
is_main
sort_order
created_at
updated_at
```

Approved media types:

```text
image
video
```

Rules:

- one main image maximum per service
- multiple additional images
- one video maximum per service
- no file binaries in MySQL
- generated storage names
- unique stored path
- file metadata remains explicit
- media replacement follows cleanup rules
- no video transcoding tables in the MVP

### 13.1 One Main Image and One Video

A simple unique index on:

```text
service_id, is_main
```

is invalid because it would prevent multiple non-main media rows.

Approved enforcement strategies include:

- transaction-safe application rules with row locks
- generated nullable slot columns with unique indexes

Conceptual slots:

```text
main_image_slot = 1 only for the main image, otherwise null
video_slot = 1 only for video media, otherwise null
```

Then enforce:

```text
unique(service_id, main_image_slot)
unique(service_id, video_slot)
```

Use this only after verifying the target MySQL version.

Application tests remain required.

---

## 14. Service Specification Standards

Recommended table:

```text
service_specifications
----------------------
id
service_id
label_ar
label_en
value_ar
value_en
sort_order
is_active
created_at
updated_at
```

Rules:

- specifications are display-only
- specifications are not customer-selectable
- specifications do not change price
- specifications are ordered
- inactive specifications are excluded from normal public output
- do not store all specifications as one uncontrolled JSON field

---

## 15. Service Order Option Standards

Service options configure the selected service.

They are different from service order questions.

### 15.1 Option Groups

Recommended table:

```text
service_option_groups
---------------------
id
service_id
label_ar
label_en
selection_type
is_required
is_active
sort_order
created_at
updated_at
```

### 15.2 Option Values

Recommended table:

```text
service_option_values
---------------------
id
service_option_group_id
value_key
label_ar
label_en
is_available
is_active
sort_order
price_adjustment nullable
created_at
updated_at
```

Rules:

- one service may have multiple option groups
- values belong to one group
- option labels support Arabic and English
- `value_key` is stable and untranslated
- option ownership is validated
- unavailable values cannot be ordered
- selected value quantities are validated when approved
- price adjustments use precise decimals
- option definitions remain relational
- do not store all options as one JSON structure

### 15.3 Options Versus Questions

Service options:

- configure the requested service
- may affect price
- may support quantities
- have availability
- are part of pricing and configuration validation

Service order questions:

- collect information from the customer
- may be required or optional
- appear as form questions in the frontend
- are defined by the administrator from the dashboard
- normally do not affect price
- produce answers saved against the order item

These concepts MUST NOT share one table.

---

## 16. Service Order Questions

### 16.1 Purpose

Each service may define questions or required input fields that the customer
must answer when ordering that service.

Examples:

```text
What name should appear on the design?
What dimensions are required?
What is the preferred delivery date?
Describe the requested design.
Do you already have a logo?
Select the intended usage.
```

The administrator manages these questions from the dashboard.

The public frontend renders the approved questions as input fields during order
configuration.

### 16.2 Recommended Table

```text
service_order_questions
-----------------------
id
service_id
question_key
label_ar
label_en
help_text_ar nullable
help_text_en nullable
placeholder_ar nullable
placeholder_en nullable
input_type
is_required
is_active
sort_order
validation_config nullable JSON
created_at
updated_at
deleted_at nullable
```

### 16.3 Question Key

`question_key` is a stable machine-oriented key within one service.

Examples:

```text
company_name
required_dimensions
preferred_delivery_date
design_description
has_existing_logo
intended_usage
```

Recommended database rule:

```text
unique(service_id, question_key)
```

Rules:

- key uses stable lowercase snake-case
- Arabic and English labels are user-facing content
- active questions provide required translations according to the feature
- key is not translated
- key must not be silently regenerated when the label changes
- key is not trusted as proof of question ownership
- the backend validates question ID and service ownership

### 16.4 Initial Input Types

The feature specification may approve types such as:

```text
short_text
long_text
number
date
boolean
single_choice
multiple_choice
```

Future types require specification approval.

File questions are not part of this table in the MVP.

Customer files remain order-item attachments.

### 16.5 Required Questions

`is_required = true` means:

- the customer must provide a valid answer for that service item
- the backend rejects order creation when the answer is missing
- whitespace-only text does not satisfy a required question
- an empty multiple-choice answer does not satisfy a required question
- the requirement is validated against the active question definition at order
  time

The frontend required marker is not sufficient enforcement.

### 16.6 Active State

Inactive questions:

- are not shown in normal public service details
- are not required for new orders
- remain preserved for historical orders through answer snapshots
- must not cause existing order answers to disappear

### 16.7 Validation Configuration

`validation_config` may store structured, allow-listed configuration such as:

```json
{
  "minLength": 3,
  "maxLength": 500
}
```

or:

```json
{
  "minValue": 1,
  "maxValue": 1000
}
```

or:

```json
{
  "minSelections": 1,
  "maxSelections": 3
}
```

Rules:

- do not store executable PHP
- do not store arbitrary Laravel validation-rule strings from the dashboard
- do not allow administrator-supplied raw regular expressions without a
  separately approved security design
- validate configuration according to `input_type`
- ignore or reject unsupported configuration keys
- feature code translates approved configuration into backend validation

JSON is acceptable here because the configuration:

- varies by field type
- is small
- is validated through an allow-list
- does not replace relational question definitions or choices

### 16.8 Question Deletion

Questions referenced by historical answers should use:

- soft deletion
- deactivation
- or restricted physical deletion

Normal dashboard deletion must not destroy historical order meaning.

---

## 17. Service Question Choices

Questions using:

```text
single_choice
multiple_choice
```

use normalized choice rows.

Recommended table:

```text
service_order_question_options
------------------------------
id
service_order_question_id
option_key
label_ar
label_en
is_active
sort_order
created_at
updated_at
deleted_at nullable
```

Rules:

- one choice belongs to one question
- option key is stable within the question
- Arabic and English labels are user-facing
- inactive choices cannot be selected in new orders
- selected historical choices remain preserved through snapshots
- do not store choice lists only inside `validation_config`
- do not store comma-separated values

Recommended uniqueness:

```text
unique(service_order_question_id, option_key)
```

---

## 18. Order Standards

Recommended direction:

```text
orders
------
id
order_number
customer_id
status
pricing_status
locale
known_subtotal
total_amount nullable
cancellation_reason nullable
rejected_reason nullable
admin_notes nullable
created_at
updated_at
```

Exact fields belong in the Orders feature.

Rules:

- order number is unique
- customer relation is server-controlled
- totals are backend-controlled
- final total may be null while pricing is incomplete
- cancellation reason is stored on the order
- rejected reason is stored when required
- no routine physical deletion
- no customer-authentication ownership field is implied
- no payment fields are introduced in the MVP
- `locale` stores the resolved `ar` or `en` interaction locale
- order locale supports later email or document generation
- locale does not alter pricing or authorization

### 18.1 Order Status

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

Terminal:

```text
completed
cancelled
rejected
```

Status mutation uses the approved workflow and concurrency rules.

### 18.2 No Status History Table

The MVP does not store a complete order status-history table.

Do not create one unless the product scope changes.

Consequences:

- current status is authoritative
- cancellation and rejection reasons remain directly on the order
- the database cannot answer who performed every historical transition
- reporting must not claim unavailable history

---

## 19. Order Address Snapshot

Recommended table:

```text
order_addresses
---------------
id
order_id
customer_name
email nullable
phone
address
created_at
updated_at
```

Rules:

- one order has one address snapshot
- snapshot is immutable through normal customer-address updates
- customer name, phone, email, and address preserve order-time input
- snapshot does not depend only on mutable customer records

Recommended uniqueness:

```text
unique(order_id)
```

---

## 20. Order Item Standards

Recommended direction:

```text
order_items
-----------
id
order_id
service_id nullable
service_name_ar_snapshot
service_name_en_snapshot
service_slug_snapshot
subcategory_name_ar_snapshot nullable
subcategory_name_en_snapshot nullable
category_name_ar_snapshot nullable
category_name_en_snapshot nullable
pricing_type_snapshot
base_unit_price_snapshot nullable
final_unit_price nullable
pricing_status
quantity
line_total nullable
duration_type_snapshot
duration_min_snapshot
duration_max_snapshot nullable
duration_unit_snapshot
customer_notes nullable
created_at
updated_at
```

Rules:

- one item belongs to one order
- service foreign key may remain nullable only when the retention strategy
  allows catalogue records to be removed
- snapshots remain mandatory
- quantity is positive
- price and total values are backend-controlled
- item pricing status is explicit
- quote-required prices may be null initially
- historical fields are not rewritten by service updates

---

## 21. Order Item Option Snapshots

Recommended table:

```text
order_item_options
------------------
id
order_item_id
service_option_group_id nullable
service_option_value_id nullable
group_label_snapshot
value_snapshot
quantity
unit_price_adjustment_snapshot nullable
total_price_adjustment_snapshot nullable
created_at
updated_at
```

Rules:

- snapshot labels preserve historical meaning
- catalogue foreign keys are optional references, not the historical source of
  truth
- selected option quantities remain explicit
- pricing adjustments use precise decimals
- current option changes do not rewrite order data

---

## 22. Order Item Question Answers

### 22.1 Purpose

Each order item stores answers to the questions defined for its service at order
time.

Answers belong to the order item, not only to the order.

This matters because one order may contain multiple services with different
required questions.

### 22.2 Recommended Answer Table

```text
order_item_question_answers
---------------------------
id
order_item_id
service_order_question_id
question_key_snapshot
question_label_ar_snapshot
question_label_en_snapshot
input_type_snapshot
is_required_snapshot
answer_text nullable
answer_number nullable
answer_date nullable
answer_boolean nullable
created_at
updated_at
```

Rules:

- one answer belongs to one order item
- one answer corresponds to one service question
- question definition must belong to the order item's service
- question snapshot fields are mandatory
- exactly one appropriate answer representation is populated for scalar types
- required active questions must have valid answers
- current question edits do not change historical answers
- answer values are not trusted from labels alone
- backend validates type and constraints before persistence

Recommended uniqueness:

```text
unique(order_item_id, service_order_question_id)
```

### 22.3 Scalar Answer Storage

Suggested mapping:

```text
short_text      -> answer_text
long_text       -> answer_text
number          -> answer_number
date            -> answer_date
boolean         -> answer_boolean
single_choice   -> answer option table
multiple_choice -> answer option table
```

Do not store numbers as uncontrolled text when numeric filtering or validation
is expected.

Do not store dates as display strings.

### 22.4 Choice Answer Table

Recommended table:

```text
order_item_question_answer_options
----------------------------------
id
order_item_question_answer_id
service_order_question_option_id nullable
option_key_snapshot
option_label_ar_snapshot
option_label_en_snapshot
created_at
```

Rules:

- one selected choice belongs to one answer
- current choice deletion does not remove the snapshot
- single-choice answers have one selected row
- multiple-choice answers may have multiple selected rows
- duplicate selected choices are prohibited
- selected choices must belong to the question

Recommended uniqueness:

```text
unique(
  order_item_question_answer_id,
  service_order_question_option_id
)
```

Application validation still enforces single-choice cardinality.

### 22.5 Why Answers Are Not One JSON Blob

Do not store all answers for an order item as one uncontrolled JSON object.

Normalized answers provide:

- question ownership validation
- required-field validation
- stable snapshots
- queryable answer types
- future reporting
- clearer migrations
- easier debugging
- protection against duplicate answers

A controlled JSON field may be approved later for a genuinely structured answer
type, but it must not replace the baseline relational model without a feature
decision.

### 22.6 Question Snapshot Immutability

The following must remain stable for the historical order:

- question key
- Arabic and English question labels
- input type
- required state
- Arabic and English selected-choice labels
- submitted answer

Changing a service question later must affect only future orders.

---

## 23. Order-Item Attachment Metadata

Recommended table:

```text
order_item_attachments
----------------------
id
order_item_id
disk
path
original_name
stored_name
mime_type
extension
size_bytes
checksum nullable
created_at
updated_at
```

Rules:

- one attachment belongs to one order item
- metadata only is stored in MySQL
- file binary remains in filesystem storage
- stored path is unique
- generated storage names are mandatory
- original name is metadata only
- public guest responses do not expose path or URL
- administration access requires authorization
- attachment mapping uses a request-only client reference during submission
- client reference is not a database ownership key
- failed order creation removes stored files

### 23.1 Public Disk Decision

The MVP uses the configured public filesystem disk for order-item attachments.

Database standards still require:

- unpredictable generated names
- non-user-controlled directories
- no path exposure in public APIs
- server-side execution prevention
- metadata-level authorization for administration output

Public disk does not mean public API visibility.

---

## 24. Site Content Tables

### 24.1 Singular Settings

Use a controlled table for singular site settings.

Avoid one unvalidated JSON object containing all site content.

Possible table:

```text
site_settings
```

Exact structure belongs in the Site Settings feature.

### 24.2 Repeated Contact Data

Repeated data uses relational rows:

```text
contact_phone_numbers
contact_emails
social_links
hero_sections
```

Each may include approved bilingual labels:

```text
label_ar
label_en
value or url
sort_order
is_active
```

### 24.3 FAQs

Recommended:

```text
faqs
----
id
question_ar
question_en
answer_ar
answer_en
sort_order
is_active
created_at
updated_at
```

FAQs have no categories in the MVP.

### 24.4 Testimonials

Recommended:

```text
testimonials
------------
id
customer_name
rating
message_ar
message_en
sort_order
is_active
created_at
updated_at
```

Testimonials are administrator-created.

### 24.5 Featured Services

Recommended:

```text
featured_services
-----------------
id
service_id
sort_order
created_at
updated_at
```

Recommended uniqueness:

```text
unique(service_id)
```

Manual ordering is stored explicitly.

---

## 25. Contact Message and Reply Standards

Recommended direction:

```text
contact_messages
----------------
id
name
email
phone nullable
subject
message
locale
status
admin_note nullable
created_at
updated_at
```

Reply records may use:

```text
contact_message_replies
-----------------------
id
contact_message_id
replied_by_user_id
reply
email_delivery_status
queued_at nullable
sent_at nullable
failed_at nullable
failure_message nullable
created_at
updated_at
```

The exact model belongs in the Contact Inbox feature.

Rules:

- enquiry preserves the resolved `ar` or `en` locale
- enquiry is persisted before email delivery
- reply is persisted before queued delivery
- database state distinguishes queued, sent, and failed when tracked
- Job dispatch does not imply sent
- SMTP failure does not delete the saved reply
- failure messages must not store secrets
- email work runs after commit

---

## 26. Queue Database Standards

The MVP uses Laravel database queues.

Required Laravel tables may include:

```text
jobs
job_batches when used
failed_jobs
```

Rules:

- use framework migrations compatible with installed Laravel
- do not edit framework queue tables casually
- Jobs carry stable scalar identifiers
- failed Jobs remain inspectable
- queue records are operational data, not business history
- deleting old failed Jobs follows an approved operations policy
- do not treat queue presence as proof of email delivery

---

## 27. Foreign Keys and Deletion Behaviour

### 27.1 Foreign Keys Are Required

Use foreign keys for relational integrity unless an approved architecture
decision documents why not.

### 27.2 Explicit Behaviour

Every foreign key must choose deletion behaviour intentionally.

Use:

- `cascadeOnDelete()` when the child has no meaning without the parent and
  deletion is approved
- `restrictOnDelete()` when deletion would destroy important history
- `nullOnDelete()` when the relationship is optional and snapshots preserve
  meaning

### 27.3 Recommended Direction

| Relation | Recommended Behaviour | Reason |
| --- | --- | --- |
| Category -> Subcategory | Restrict or soft delete | Prevent accidental hierarchy destruction |
| Subcategory -> Service | Restrict or soft delete | Preserve catalogue and order references |
| Service -> Media | Controlled cascade metadata plus storage cleanup | Media has no independent catalogue meaning |
| Service -> Specifications | Cascade in pre-production or controlled deletion | Child data belongs to service |
| Service -> Option Groups | Restrict when historical setup matters; otherwise controlled cascade | Avoid orphan configuration |
| Option Group -> Values | Controlled cascade | Values belong to group |
| Service -> Order Questions | Restrict or soft delete | Preserve historical question references |
| Question -> Choices | Restrict or soft delete | Preserve historical choice references |
| Customer -> Addresses | Controlled cascade only when customer deletion is approved | Addresses belong to customer |
| Customer -> Orders | Restrict | Orders must remain attributable |
| Order -> Address Snapshot | Cascade only if physical order deletion is an approved non-production operation | Snapshot belongs to order |
| Order -> Items | Restrict in production flows | Preserve operational history |
| Order Item -> Option Snapshots | Preserve with order item | Historical meaning |
| Order Item -> Question Answers | Preserve with order item | Historical answers |
| Order Item -> Attachments | Metadata cascade only with controlled storage cleanup | Prevent orphan metadata |
| Contact Message -> Replies | Preserve with enquiry | Operational record |
| User -> Contact Reply | `nullOnDelete` with actor snapshot if required | Preserve reply history |

Physical deletion of production orders and order items must not be available
through normal application flows.

---

## 28. Unique Constraints

Database unique constraints are mandatory for true invariants.

Initial candidates:

```text
users.email
orders.order_number
services.slug
categories.slug according to approved scope
customers.phone_normalized when one customer per phone is approved
featured_services.service_id
service_order_questions(service_id, question_key)
service_order_question_options(service_order_question_id, option_key)
order_addresses.order_id
order_item_question_answers(order_item_id, service_order_question_id)
```

Additional candidates:

```text
one selected choice per answer and option
one default address per customer through approved slot strategy
one main image per service through approved slot strategy
one video per service through approved slot strategy
```

Do not rely only on `Rule::unique()`.

Application validation improves error messages.

The database protects concurrency.

---

## 29. Indexing Standards

### 29.1 Index Documented Query Patterns

Indexes should support:

- customer lookup by normalized phone
- root category and subcategory listing
- service listing by subcategory
- service filtering by root category
- public active catalogue queries
- service slug lookup
- order-number lookup
- order status dashboards
- order date sorting
- customer order summaries
- quote-required item queues
- Contact Us status queues
- featured-service ordering
- best-selling service aggregation
- question retrieval ordered by service
- answer retrieval by order item

### 29.2 Suggested Index Direction

Subject to real query review:

```php
$table->index(['parent_id', 'is_active', 'sort_order']);
$table->index(['subcategory_id', 'publication_status', 'availability_status']);
$table->index(['service_id', 'is_active', 'sort_order']);
$table->index(['phone_normalized']);
$table->index(['customer_id', 'created_at']);
$table->index(['status', 'created_at']);
$table->index(['pricing_status', 'created_at']);
$table->index(['order_id', 'created_at']);
$table->index(['order_item_id', 'created_at']);
$table->index(['contact_message_id', 'created_at']);
```

### 29.3 Question Indexes

Recommended query-supporting indexes:

```php
$table->index([
    'service_id',
    'is_active',
    'sort_order',
]);

$table->index([
    'service_order_question_id',
    'is_active',
    'sort_order',
]);

$table->index([
    'order_item_id',
    'created_at',
]);
```

### 29.4 Avoid Over-Indexing

Every index increases:

- write cost
- migration time
- storage
- maintenance overhead

Before adding an index:

1. identify the actual query
2. review existing indexes
3. inspect `EXPLAIN` when volume is meaningful
4. choose the smallest useful index
5. verify leftmost-prefix order
6. test sorting and filtering together

### 29.5 Search

Do not add leading-wildcard search across large text columns without a plan.

Initial search may use:

- exact order number
- indexed phone-normalized lookup
- prefix or partial name search where acceptable
- service slug
- controlled name search

External search infrastructure requires measured need.

---

## 30. Timestamp and Soft-Delete Standards

### 30.1 Timestamps

Business tables normally include:

```php
$table->timestamps();
```

### 30.2 Soft Delete Candidates

Likely candidates:

```text
users
customers
customer_addresses
categories
services
service_order_questions
service_order_question_options
```

Other catalogue children may use soft deletes when historical or
administrative restoration requirements justify them.

### 30.3 Prefer Activation

For operational catalogue records, prefer:

```text
is_active = false
```

when the record may need to remain referenced or restorable.

### 30.4 Historical Tables

Orders, order items, snapshots, question answers, and attachment metadata should
not be routinely user-deleted.

---

## 31. Snapshot Standards

Snapshots protect historical order meaning.

### 31.1 Required Snapshot Areas

Approved snapshots include:

- customer details
- address
- Arabic and English service names
- service slug or reference
- Arabic and English category names where required
- Arabic and English subcategory names where required
- pricing type
- base price
- final price
- quantity
- duration
- selected option group label
- selected option value
- option quantities
- question key
- question label
- question input type
- question required state
- Arabic and English selected question-choice labels
- customer answers
- resolved order locale

### 31.2 Snapshot Rules

- Snapshots are written at order creation.
- Catalogue updates do not rewrite snapshots.
- Snapshot fields are server-generated from authoritative records and validated
  input.
- Frontend-supplied labels are not authoritative.
- Administrator quote pricing may update approved final pricing fields.
- Snapshot tables remain queryable without requiring deleted catalogue records.
- Snapshot fields should be explicit rather than one uncontrolled order JSON
  blob.

---

## 32. JSON Column Standards

### 32.1 Appropriate Uses

JSON may be used for:

- allow-listed service question validation configuration
- redacted external integration payload snapshots
- structured failure metadata
- non-queryable report parameters
- future idempotency request fingerprints where approved

### 32.2 Inappropriate Uses

JSON MUST NOT replace relational modelling for:

- categories and subcategories
- services
- service media
- service specifications
- service option groups
- service option values
- service question definitions
- service question choices
- all order items
- all selected options
- all customer answers
- order-item attachments
- customers and addresses

Data requiring joins, filtering, constraints, reporting, or frequent updates
belongs in relational tables.

### 32.3 JSON Validation

Every JSON column must have:

- a documented schema
- allow-listed keys
- type validation
- size limits
- safe defaults

Do not store executable code or arbitrary framework validation rules.

---

## 33. Migration Standards

### 33.1 Deterministic Migrations

Migrations MUST NOT depend on:

- external APIs
- current requests
- environment-specific user records
- random runtime data
- frontend applications
- current production content

### 33.2 Focused Responsibility

Good:

```text
create_customers_table
create_categories_table
create_services_table
create_service_order_questions_table
create_service_order_question_options_table
create_orders_table
create_order_item_question_answers_table
add_pricing_status_to_orders_table
```

Bad:

```text
create_all_commerce_tables
fix_database
update_everything
misc_changes
```

### 33.3 Reversible Where Safe

Implement `down()` safely when practical.

Irreversible or destructive changes require explicit documentation.

### 33.4 Production-Safe Changes

Require rollout planning for:

- dropping columns
- changing indexed column types
- renaming large-table columns
- adding non-null columns without safe defaults
- large backfills
- adding generated columns
- adding unique constraints to existing data
- changing foreign-key delete behaviour
- dropping production indexes

### 33.5 Existing Migrations

Do not edit already-deployed migrations.

Create a new migration unless:

- the repository is confirmed pre-release
- no environment has deployed the migration
- the task explicitly permits restructuring

### 33.6 Data Migrations

Large or resumable data transformations should use:

- controlled Artisan commands
- batches
- queued Jobs when appropriate

Do not run large application-level loops inside deployment migrations.

---

## 34. Seeder and Factory Standards

### 34.1 Seeders

Seeders should be idempotent where practical.

Use deterministic keys for:

- roles
- permissions
- Super Admin role assignment
- approved default settings
- required system configuration

Do not seed demo orders or fake customers into production by default.

### 34.2 Factories

Factories should provide clear states.

Examples:

```text
administrator
inactive_administrator
customer
customer_with_default_address

root_category
subcategory
inactive_category
inactive_subcategory

active_service
inactive_service
available_service
unavailable_service
fixed_price
starting_from
quote_required

required_question
optional_question
single_choice_question
multiple_choice_question

pending_order
awaiting_review_order
complete_pricing
requires_review_pricing
completed_order
cancelled_order
```

Tests should use factories instead of brittle repeated arrays.

### 34.3 Enum Usage

Seeders and factories use enum cases.

Do not hard-code scattered state strings or numeric codes.

---

## 35. Transaction Standards

### 35.1 Required Transactions

Use transactions for:

- guest order creation
- customer creation during guest checkout
- customer-address creation or association during checkout
- order-address snapshot creation
- order-item creation
- order option snapshots
- order question-answer snapshots
- attachment metadata creation
- service creation or update with related configuration
- quote-required item pricing and total recalculation
- order status transitions
- cancellation
- default-address switching
- category or subcategory moves
- featured-service reordering
- Contact Us reply persistence and status update

### 35.2 External Calls

Do not hold database transactions open while:

- sending email
- calling external APIs
- generating reports
- performing slow remote storage operations when avoidable

Persist authoritative state, commit, then dispatch approved asynchronous work.

### 35.3 Filesystem Compensation

Database rollback does not roll back files.

A workflow that stores files must:

- track created paths
- delete them when database work fails
- log cleanup failures
- avoid returning success with inconsistent state

### 35.4 Exception Handling

Use:

```php
DB::transaction(...)
```

and allow exceptions to trigger rollback.

Do not manually commit after partial failure.

Do not catch and suppress database exceptions.

---

## 36. Locking and Concurrency Standards

### 36.1 Row-Level Locks

Use `lockForUpdate()` when current state controls mutation validity.

Examples:

- two administrators pricing the same order item
- concurrent order status changes
- default-address changes
- featured-service reorder operations
- category parent changes
- service media main-image replacement
- concurrent video replacement

### 36.2 Order Pricing

Pricing an item should lock:

- the target order
- the target order item
- relevant order items needed for total recalculation

This prevents:

- stale totals
- overwritten prices
- incomplete pricing becoming complete incorrectly
- invalid status transitions

### 36.3 Order Number Collision

The database unique index is the final protection.

Generation code retries boundedly after duplicate-key collision.

### 36.4 Question Definitions During Checkout

Order creation must use one consistent view of:

- active service questions
- required state
- question choices
- validation configuration

When a concurrent dashboard update could cause inconsistent answers, the
workflow may:

- load and validate within the transaction
- lock relevant definitions
- or use another approved consistency strategy

The implementation plan must choose the narrowest justified mechanism.

### 36.5 Optimistic Concurrency

For editable administration resources, `updated_at` or a version column may be
used when lost updates become a demonstrated problem.

Do not add version columns everywhere preemptively.

---

## 37. Performance Standards

### 37.1 Avoid N+1

Use deliberate eager loading for:

- category trees
- service lists
- service details
- service questions and choices
- order details
- order item options
- order item question answers
- attachments
- Contact Us replies

### 37.2 Pagination

Pagination is mandatory for potentially unbounded:

- customers
- services
- orders
- contact enquiries
- testimonials when large
- FAQs when large
- failed Jobs in operational tooling
- report results

Small bounded reference collections may be unpaginated only when documented.

### 37.3 Select Required Columns

List endpoints should not load:

- full long descriptions
- all media metadata
- all option definitions
- all service questions
- all answers
- all attachment metadata

unless the endpoint contract requires them.

### 37.4 Counts and Aggregates

Use:

- `withCount`
- indexed aggregate queries
- dedicated Query classes

Do not load collections into PHP merely to count them.

### 37.5 Best-Selling Services

Best-selling calculations must define:

- completed-order requirement
- quantity or line-count basis
- time range
- result limit
- tie-breaking
- inactive and deleted service behaviour

Indexes must support the approved query.

### 37.6 Query Review

Review query count and plans for:

- public category navigation
- public service list
- service detail with questions
- guest checkout validation
- administration order list
- order detail
- dashboard summary
- best-selling query
- Contact Us queue

---

## 38. Data Privacy and Retention

### 38.1 Personal Data

Customer name, email, phone, address, enquiry content, and uploaded documents
may contain personal data.

Rules:

- collect only approved fields
- restrict administration access
- avoid PII in logs
- avoid copying PII into unnecessary tables
- mask data where full display is unnecessary
- never store customer answers in logs by default
- never place uploaded file contents in database logs

### 38.2 Customer Answers

Service question answers may contain sensitive business or personal data.

Rules:

- expose answers only through authorized order-detail APIs
- do not include answers in public guest responses unless explicitly approved
- do not log full answers
- do not use answers for unrelated profiling
- retention follows order-retention policy
- exports containing answers require authorization

### 38.3 Order Retention

Orders, items, snapshots, answers, and attachment metadata are retained
according to business policy.

Routine physical deletion is prohibited.

### 38.4 Customer Deletion

When required, use an approved strategy:

- deactivation
- soft deletion
- anonymization while preserving orders
- controlled deletion only when integrity permits

### 38.5 Temporary Files

The MVP does not use temporary-upload tokens.

Any temporary previews or exports introduced later require scheduled cleanup.

---

## 39. Backup, Restore, and Disaster Recovery

Requirements:

- automated database backups
- documented retention
- restricted backup access
- encryption at rest where supported
- regular restore tests
- separate storage backup for service media and order attachments
- recovery procedures preserving database-to-file consistency
- queue restoration considerations
- restoration validation for order snapshots and customer answers

A backup is not considered valid until restoration has been tested.

---

## 40. File and Database Consistency

### 40.1 Upload Flow

A safe upload flow:

1. validates the request and file
2. generates a storage name
3. stores the file
4. persists metadata
5. removes the file if metadata or workflow persistence fails

### 40.2 Replacement Flow

When replacing service media:

1. validate the new file
2. store the new file
3. persist new metadata and main/video state safely
4. commit the database change
5. remove the old file according to approved cleanup behaviour
6. record cleanup failure for retry when necessary

Do not delete the old file before the new authoritative record is safe.

### 40.3 Controlled Deletion

When file deletion is allowed:

1. authorize the operation
2. identify metadata and path through trusted records
3. update or remove metadata according to retention rules
4. delete the physical file
5. record storage failure for retry

### 40.4 Orphan Detection

Scheduled maintenance may identify:

- files without metadata
- metadata with missing files
- expired generated exports

Cleanup must not delete files based only on an unverified path scan.

---

## 41. Testing Requirements

Database tests must cover applicable behaviour.

### 41.1 Core Integrity

- user email uniqueness
- customer normalized-phone behaviour
- foreign-key delete rules
- order-number uniqueness
- enum casts
- money precision
- transaction rollback
- seeder idempotency
- migration rollback where safe

### 41.2 Category Hierarchy

- root category has no parent
- subcategory belongs to a root category
- self-parent is rejected
- third-level category is rejected
- circular relationship is rejected
- service belongs only to a subcategory
- inactive hierarchy affects public service eligibility

### 41.3 Service Questions

- question key unique within service
- Arabic and English question content persistence
- active question translation completeness
- same key may exist on another service when approved
- required and optional question state
- inactive questions excluded from new orders
- choice belongs to its question
- choice key uniqueness
- unsupported validation configuration rejected
- arbitrary Laravel validation strings rejected
- question soft deletion preserves historical answers

### 41.4 Question Answers

- one answer per order item and question
- question belongs to the order item's service
- required answer cannot be missing
- whitespace does not satisfy required text
- numeric answer stored in numeric column
- date answer stored as date
- boolean answer stored as boolean
- single choice accepts one option
- multiple choice accepts allowed count
- selected option belongs to question
- bilingual answer snapshots survive question translation edits
- bilingual choice snapshots survive choice edits or deletion
- customer answers are not translated
- duplicate answer options are rejected
- answers from one service cannot be submitted for another service item

### 41.5 Orders and Snapshots

- customer and address snapshots
- resolved order locale
- bilingual service snapshot
- option snapshot
- question and answer snapshot
- mixed fixed and quote-required items
- incomplete total uses null
- backend recalculates totals
- frontend price tampering has no effect
- order transaction rolls back all related rows
- failed workflow removes newly stored files

### 41.6 Concurrency

Use MySQL-backed tests where practical for:

- order-number collisions
- quote pricing
- status transitions
- default-address switching
- main-image selection
- video uniqueness
- category parent changes

SQLite tests are insufficient when behaviour depends on:

- MySQL collation
- generated columns
- CHECK support
- row locking
- composite indexes
- foreign-key behaviour

---

## 42. Initial Schema Direction

This is architectural direction, not a substitute for feature-specific schema
design.

```text
users
customers
customer_addresses

categories
services
service_media
service_specifications

service_option_groups
service_option_values

service_order_questions
service_order_question_options

orders
order_addresses
order_items
order_item_options
order_item_question_answers
order_item_question_answer_options
order_item_attachments

site_settings
contact_phone_numbers
contact_emails
social_links
hero_sections
testimonials
faqs
featured_services

contact_messages
contact_message_replies

jobs
failed_jobs
```

Spatie and Sanctum add their approved package tables.

Potential future tables must not be created before approval.

---

## 43. Review Checklist Before Merging Database Changes

- [ ] Table and column names follow conventions.
- [ ] Data types and lengths match real domain needs.
- [ ] Money uses fixed precision.
- [ ] Nullability has documented meaning.
- [ ] Foreign keys exist where practical.
- [ ] Delete behaviour is explicit.
- [ ] Business uniqueness is protected by database constraints.
- [ ] Query patterns have justified indexes.
- [ ] Native MySQL ENUM is avoided.
- [ ] PHP enum casts are defined.
- [ ] Category hierarchy remains two levels.
- [ ] Services link only to subcategories.
- [ ] Service options and service questions remain separate concepts.
- [ ] Required service questions are enforced by backend and database design.
- [ ] Question choices use relational rows.
- [ ] Answers belong to order items.
- [ ] Question and answer snapshots preserve Arabic and English historical
      meaning.
- [ ] Order and Contact Us locale fields are preserved where required.
- [ ] All answers are not hidden inside one uncontrolled JSON blob.
- [ ] Arbitrary executable validation rules are not stored.
- [ ] Migration is deterministic.
- [ ] Migration is safely reversible where practical.
- [ ] Production rollout risk was reviewed.
- [ ] Multi-step writes use transactions.
- [ ] Concurrency-sensitive mutations use locks or atomic constraints.
- [ ] File metadata and storage consistency are protected.
- [ ] No N+1 query is introduced.
- [ ] PII and customer answers are protected.
- [ ] Factories and tests are updated.
- [ ] Database and API contracts remain synchronized.

---

## 44. Non-Negotiable Database Rules

- One primary MySQL database.
- No tenant database model.
- No customer records inside `users`.
- No customer authentication schema in the MVP.
- No persistent backend cart schema.
- No separate quote tables in the MVP.
- No payment or currency tables in the MVP.
- No third category level.
- No service linked directly to a root category.
- No duplicate root-category and subcategory foreign keys on services.
- No native MySQL ENUM for business states.
- No float or double for money.
- No frontend-controlled order totals.
- No physical order deletion through normal application flows.
- No order snapshots dependent only on mutable catalogue records.
- No active public catalogue content missing mandatory Arabic or English fields.
- No translated labels used as identifiers.
- No mixing service options with service order questions.
- No missing required question answers.
- No all-answers JSON blob as the primary model.
- No arbitrary Laravel validation-rule strings stored from the dashboard.
- No raw file binaries in MySQL.
- No raw storage paths exposed through public APIs.
- No database migration that depends on external services or random runtime
  data.
