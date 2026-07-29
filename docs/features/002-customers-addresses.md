# Feature 002 — Customers and Addresses

> **Project:** Service Commerce Backend
>
> **Feature ID:** `002`
>
> **Feature Name:** Customers and Addresses
>
> **Target Framework:** Laravel 13
>
> **Primary Actor:** Administrator
>
> **Customer Type:** Guest customer without authentication
>
> **Default Country:** Egypt (`EG`, `+20`)
>
> **Status:** Approved implementation reference
>
> **Intended Use:** Authoritative reference for planning, implementation,
> `/speckit.specify`, Codex, code review, Postman documentation, and acceptance
> testing.

---

## 1. Feature Summary

This Feature defines the customer and address domain used by the Service
Commerce Backend.

It provides:

- administrator customer management
- administrator address management
- customer creation without authentication
- customer matching by normalized phone
- optional unique customer email
- Egyptian default phone interpretation
- support for international customer phone numbers
- multiple addresses per customer
- one default active address
- address matching and deduplication
- automatic restoration of matching soft-deleted customers
- automatic restoration of matching soft-deleted addresses
- customer and address soft deletion
- customer and address restoration
- reusable services for future guest order creation
- customer and address snapshots for future orders
- authorization permissions
- search, filters, sorting, and pagination
- localized validation and API messages
- MySQL concurrency protection
- Pest API tests

This Feature does not create a customer account.

A customer does not receive:

```text
password
access token
refresh token
login endpoint
customer dashboard
role
permission
```

---

## 2. Business Goal

Guest customers must be represented consistently even when they submit
multiple orders.

The system must:

1. identify a returning customer primarily by phone number
2. prevent duplicate customers for the same normalized phone
3. allow an administrator to manage customer records
4. support multiple service addresses
5. preserve one default active address
6. avoid duplicate saved addresses
7. preserve historical order data through snapshots
8. avoid silently overwriting saved customer data from a guest order
9. preserve unique non-null customer email values as a data-quality invariant

---

## 3. Authoritative Decisions

```text
Customer authentication: Not included
Customer registration: Not included
Customer password: Not included
Customer matching key: phone_normalized
Customer phone format: E.164
Default country: Egypt
Default calling code: +20
International customers: Supported
Customer name: One field
Customer email: Optional and unique when present
Customer phone: Required and unique after normalization
Customer notes: Not included
Customer active flag: Not included
Customer soft delete: Included
Preferred locale on customer: Not included
```

Address decisions:

```text
Multiple addresses: Supported
Maximum active addresses: 20
Default address: One active address maximum
First address: Becomes default automatically
Address soft delete: Included
Address restoration: Included
Address hash: Included
Recipient name: Not included
Building: Not included
Floor: Not included
Apartment: Not included
Postal code: Not included
Address notes: Included as plain text
```

Permission decisions:

```text
Customer permissions: Independent
Address permissions: Independent
Set-default permission: Independent
```

---

## 4. Related Standards

Implementation must comply with:

```text
AGENTS.md
docs/00-project-overview/project-overview.md
docs/01-architecture/backend-architecture.md
docs/02-standards/api-standards.md
docs/02-standards/code-standards.md
docs/02-standards/database-standards.md
docs/02-standards/localization-standards.md
docs/02-standards/authentication-standards.md
docs/02-standards/authorization-standards.md
docs/02-standards/file-storage-standards.md
docs/02-standards/testing-standards.md
docs/02-standards/security-standards.md
docs/features/001-identity-authentication.md
```

Where a higher-level governing document intentionally leaves a detail
configurable, this Feature's more specific approved business decision controls
Feature 002. This Feature MUST NOT weaken, bypass, or contradict the
constitution, backend architecture, or shared cross-cutting standards. Any
genuine exception requires the governing files to be approved and amended
before planning or implementation.

---

## 4.1 Administrator Authentication Contract

All Feature 002 administration routes MUST use the authentication and session
contract defined by Feature 001.

The protected middleware order MUST be:

```text
auth:sanctum
-> EnsureUserIsAdministrator
-> EnsureAdminIsActive
-> permission middleware
-> endpoint
```

Administrator requests MUST send the current Sanctum Access Token using:

```http
Authorization: Bearer {accessToken}
```

The Administrator identity MUST be resolved only from the authenticated Bearer
Access Token:

```php
$request->user()
```

Feature 002 requests MUST NOT accept an Administrator identity or
authentication state from request input.

Do not accept:

```text
accessToken
refreshToken
administratorId
adminId
userId
role
roles
permissions
isActive
authentication metadata
```

Feature 002 MUST NOT:

- issue Access Tokens
- issue Refresh Tokens
- rotate Refresh Tokens
- accept Refresh Tokens
- create authentication cookies
- require CSRF
- use browser `Origin` as authentication
- introduce Proxy/BFF authentication
- introduce customer authentication
- return `accessToken` or `refreshToken` in any Feature 002 response

Token issuance, refresh, rotation, reuse detection, login replacement, and
session revocation remain exclusively owned by Feature 001.

When the Admin Frontend receives an expired or revoked Access Token:

1. the Feature 002 request returns `401 UNAUTHENTICATED`
2. the frontend calls Feature 001
   `POST /api/v1/admin/auth/refresh`
3. the frontend receives the replacement token pair
4. the frontend retries the Feature 002 request using the new Access Token

Feature 002 endpoints never receive or process the Refresh Token.

The previous Access Token becomes unusable after a successful Feature 001
refresh. Only the replacement Access Token may authorize Feature 002 routes.

Administration requests use:

```text
Authorization: Bearer {currentAccessToken}
Accept: application/json
Accept-Language: ar|en
```

They MUST NOT depend on:

```text
Cookie
X-CSRF-TOKEN
withCredentials=true
refreshToken in a Feature 002 request
```

---

## 5. Scope

### 5.1 Included

- customers table
- customer addresses table
- customer administration APIs
- customer-address administration APIs
- phone parsing and normalization
- E.164 storage
- Egyptian default-country handling
- international phone support
- optional unique email
- customer soft delete
- customer restoration
- address soft delete
- address restoration
- one default address
- address hash
- duplicate prevention
- matching services for use by the Orders Feature
- snapshot contracts for the Orders Feature
- permissions
- API Resources
- search and pagination
- automated tests

### 5.2 Excluded

```text
Customer login
Customer registration
Customer password
Customer access token
Customer refresh token
Customer dashboard
Customer email verification
Customer role
Customer permission
Customer preferred locale
Customer export
Customer order-history endpoint
Customer notes
Customer files
Customer avatar
Customer marketing preferences
Customer payment methods
Customer wallet
Force delete
Bulk customer operations
Customer merge UI
```

---

## 6. Actors

### 6.1 Administrator

The administrator can manage customers and addresses through protected
administration routes.

Each operation requires its own Spatie permission.

Authentication is inherited from Feature 001.

The exact middleware order is:

```text
auth:sanctum
-> EnsureUserIsAdministrator
-> EnsureAdminIsActive
-> operation permission
-> endpoint
```

Permission checks MUST run only after the request has been authenticated, the
authenticated user has been confirmed as an Administrator, and the
Administrator account has been confirmed active.

### 6.2 Guest Order Workflow

The future Guest Order Feature will use internal domain services from this
Feature to:

- normalize customer phone
- find or create customer
- restore a matching deleted customer
- find or create address
- restore a matching deleted address
- obtain authoritative customer and address IDs
- create historical snapshots

There is no public customer CRUD API.

---

# Customer Model

## 7. Customer Table

Table name:

```text
customers
```

### 7.1 Required Columns

```text
id
name
email nullable
phone
phone_normalized
created_at
updated_at
deleted_at nullable
```

### 7.2 Recommended Types

```text
id:
BIGINT UNSIGNED primary key

name:
VARCHAR(150)

email:
VARCHAR(255) nullable

phone:
VARCHAR(30)

phone_normalized:
VARCHAR(20)

created_at:
TIMESTAMP

updated_at:
TIMESTAMP

deleted_at:
TIMESTAMP nullable
```

### 7.3 Indexes

```text
UNIQUE(email)
UNIQUE(phone_normalized)
INDEX(name)
INDEX(created_at)
INDEX(deleted_at)
```

MySQL permits multiple `NULL` values in a nullable unique email column.

Therefore:

- customers without email are allowed
- two customers cannot share the same non-null normalized email
- customers remain guest domain records without authentication credentials

### 7.4 Catalogue Boundary

Customer records and addresses do not define catalogue hierarchy. The approved
catalogue relationship remains:

```text
Category -> Subcategory -> Service
```

### 7.5 Excluded Columns

Do not add:

```text
password
email_verified_at
remember_token
is_active
preferred_locale
admin_notes
last_login_at
last_login_ip
role
type
avatar
```

---

## 8. Customer Name

Use one field:

```text
name
```

Rules:

- required
- plain text
- trimmed
- maximum 150 characters
- no HTML
- no Markdown
- no first-name/last-name split
- guest order submissions may contain a different name from the saved customer
- guest order matching must not overwrite the saved name automatically

---

## 9. Customer Email

The customer email is:

```text
optional
unique when present
stored normalized
```

Normalization:

```php
mb_strtolower(trim($email))
```

Rules:

- an empty string becomes `null`
- non-null email receives a unique database constraint
- email is not used for customer matching in the MVP
- email is not used for current authentication
- email uniqueness preserves a clean path for possible future email login
- guest order matching must not overwrite the saved email automatically
- administrator update may change the email after validation
- duplicate non-null email returns a validation error

Recommended stable error code:

```text
CUSTOMER_EMAIL_ALREADY_EXISTS
```

---

## 10. Customer Phone

The customer phone is required.

Store:

```text
phone
phone_normalized
```

### 10.1 `phone`

`phone` is the approved display value returned to the administration frontend.

Recommended stored representation:

```text
internationally formatted display value
```

Example:

```text
+20 100 123 4567
```

### 10.2 `phone_normalized`

`phone_normalized` is the machine identity value.

Format:

```text
E.164
```

Example:

```text
+201001234567
```

Rules:

- required
- unique
- indexed
- never accepted directly as authoritative frontend input
- calculated by the backend
- used for matching
- used for concurrency protection
- not normally returned in API Resources

---

## 11. Phone Parsing

Use a libphonenumber-compatible implementation.

The implementation must:

- parse local and international numbers
- validate country calling rules
- normalize valid numbers to E.164
- produce an approved international display format
- reject invalid numbers
- reject phone extensions in the MVP

Do not normalize by removing symbols only.

A string of digits is not automatically a valid phone number.

---

## 12. Default Country

The default country is:

```text
Egypt
ISO Alpha-2: EG
Calling code: +20
```

When a phone number is local and no phone-country hint is provided:

```text
interpret it as an Egyptian number
```

Example:

```text
01001234567
```

normalizes to an E.164 Egyptian number when valid.

---

## 13. International Customers

Customers from other countries are supported.

Recommended request field:

```text
phoneCountryCode
```

Format:

```text
ISO 3166-1 Alpha-2
```

Examples:

```text
EG
FR
IT
SA
AE
```

Rules:

- optional when phone already contains a valid international calling code
- optional for Egyptian local numbers
- default is `EG`
- required when a non-Egyptian local-format number cannot be interpreted
- used only for phone parsing
- not stored as a separate customer profile field in this Feature
- normalized result remains E.164

---

## 14. Invalid Phone

Invalid phone response:

```text
HTTP 422
code: CUSTOMER_PHONE_INVALID
```

The error attaches to:

```text
phone
```

The API does not expose library internals or parsing exceptions.

---

## 15. Customer Matching

Customer matching uses only:

```text
phone_normalized
```

Do not match by:

```text
name
email
address
```

### 15.1 Existing Active Customer

When an active customer has the same normalized phone:

- use the existing customer
- do not create another customer
- do not automatically update name
- do not automatically update email
- preserve submitted guest data for the future order snapshot

### 15.2 Existing Deleted Customer

When a soft-deleted customer has the same normalized phone during a guest order:

- restore the existing customer automatically
- use the restored customer
- do not create a duplicate
- do not automatically restore all addresses
- allow address matching to restore only the matching submitted address
- do not automatically overwrite saved name or email

### 15.3 Missing Customer

When no active or deleted customer matches:

- create a customer
- use normalized phone
- store submitted name
- store optional normalized unique email
- return the created customer to the order workflow

---

## 16. Customer Matching Concurrency

Two simultaneous requests with the same phone must not create two customers.

Protection:

1. normalize phone
2. search active and deleted records
3. attempt create inside the calling transaction
4. rely on unique index for final protection
5. catch duplicate-key race
6. reload the existing customer by `phone_normalized`
7. continue using the resolved customer

Do not solve the race with a prior `exists()` check only.

A real MySQL concurrency test is required.

---

# Customer Address Model

## 17. Address Table

Table name:

```text
customer_addresses
```

### 17.1 Required Columns

```text
id
customer_id
label nullable
phone
phone_normalized
country_code
city
area nullable
street
notes nullable
address_hash
is_default
created_at
updated_at
deleted_at nullable
```

### 17.2 Removed Address Fields

Do not add:

```text
recipient_name
building
floor
apartment
postal_code
```

These fields are not part of the MVP.

### 17.3 Recommended Types

```text
id:
BIGINT UNSIGNED primary key

customer_id:
BIGINT UNSIGNED

label:
VARCHAR(100) nullable

phone:
VARCHAR(30)

phone_normalized:
VARCHAR(20)

country_code:
CHAR(2)

city:
VARCHAR(150)

area:
VARCHAR(150) nullable

street:
VARCHAR(255)

notes:
TEXT nullable

address_hash:
CHAR(64)

is_default:
BOOLEAN
default false

deleted_at:
TIMESTAMP nullable
```

### 17.4 Foreign Key

```text
customer_id -> customers.id
```

Recommended database delete behaviour:

```text
restrict physical deletion
```

Ordinary deletion uses soft deletes.

### 17.5 Indexes

```text
INDEX(customer_id)
INDEX(customer_id, deleted_at)
INDEX(customer_id, is_default, deleted_at)
INDEX(customer_id, address_hash)
INDEX(country_code)
INDEX(city)
```

Recommended uniqueness:

```text
customer_id + address_hash + active-state strategy
```

Because MySQL partial unique indexes are not directly available, the exact
active-row uniqueness mechanism must be chosen in the implementation plan.

Acceptable approaches include:

- transaction plus indexed lookup plus application invariant
- generated active-hash column with unique index
- restore matching deleted row before creating a new row

The implementation must not depend on an unsafe pre-check alone.

---

## 18. Address Fields

### 18.1 Label

```text
label
```

Rules:

- optional
- plain text
- maximum 100 characters

Examples:

```text
Home
Office
Main Branch
```

Label is presentation metadata.

It is not part of address identity.

### 18.2 Phone

Address phone is required.

It may differ from the customer's primary phone.

Store:

```text
phone
phone_normalized
```

Use the same phone parsing rules as the customer phone.

Address phone is not part of the address hash.

A submitted guest-order phone may be preserved in the order snapshot even when
it differs from the saved address phone.

### 18.3 Country Code

```text
countryCode
```

Rules:

- required
- ISO Alpha-2
- uppercase
- exactly two characters
- default may be `EG` in forms
- stored as `country_code`

### 18.4 City

```text
city
```

Rules:

- required
- plain text
- trimmed
- maximum 150 characters

### 18.5 Area

```text
area
```

Rules:

- optional
- plain text
- trimmed
- maximum 150 characters
- empty string normalizes to `null`

### 18.6 Street

```text
street
```

Rules:

- required
- plain text
- trimmed
- maximum 255 characters

### 18.7 Notes

```text
notes
```

Rules:

- optional
- plain text
- maximum 1000 characters
- empty string normalizes to `null`
- not part of address identity
- not automatically used to update an existing address during guest matching

---

## 19. Address Limit

Maximum active addresses per customer:

```text
20
```

The twenty-first active address is rejected.

Response:

```text
HTTP 422
code: CUSTOMER_ADDRESS_LIMIT_EXCEEDED
```

Deleted addresses do not count toward the active limit.

Restoring an address counts toward the limit.

---

## 20. Multiple Addresses

A customer may own multiple active addresses.

Each address belongs to exactly one customer.

Rules:

- address routes are nested under customer
- address ownership is verified
- an address belonging to another customer returns `404`
- moving an address between customers is not supported
- customer ID cannot be changed through address update

---

## 21. Default Address

A customer may have:

```text
zero default addresses when no active address exists
one default active address when active addresses exist
```

A customer must never have more than one default active address.

### 21.1 First Address

The first active address becomes default automatically.

The request cannot prevent this.

### 21.2 New Default Address

When a new address is created with:

```json
{
  "isDefault": true
}
```

the backend:

1. starts a transaction
2. locks relevant active customer addresses where needed
3. removes default from the previous address
4. creates the new address as default
5. commits

### 21.3 Setting Existing Address as Default

Use a dedicated command endpoint and permission.

The operation:

- verifies address ownership
- verifies address is active
- clears previous default
- marks selected address default
- runs transactionally

### 21.4 Removing Default State

Do not allow the administration frontend to leave active addresses without a
default.

When changing the default:

- select another address through the set-default endpoint
- do not send `isDefault=false` for the only current default without selecting
  another active address

### 21.5 Deleting Default Address

When the default address is soft-deleted:

- select the newest remaining active address as default
- use deterministic ordering:
  - `created_at DESC`
  - then `id DESC`
- when no active address remains, no default exists

The delete and fallback selection run in one transaction.

---

## 22. Address Normalization

Before calculating identity:

- trim text
- normalize internal whitespace
- uppercase country code
- normalize case according to the approved deterministic strategy
- convert empty optional fields to `null`

Recommended identity values:

```text
country_code
city
area
street
```

Removed fields are not included because they do not exist:

```text
building
floor
apartment
postal_code
recipient_name
```

Do not include:

```text
label
notes
phone
phone_normalized
```

in address identity.

---

## 23. Address Hash

Calculate:

```text
SHA-256
```

from a canonical representation of:

```text
country_code
city
area
street
```

Conceptual canonical input:

```text
EG|cairo|nasr city|10 example street
```

Rules:

- generated only by backend
- never trusted from request
- stored in `address_hash`
- indexed with `customer_id`
- not returned in API Resources
- recalculated whenever identity fields change
- label, notes, and phone changes do not change address identity

---

## 24. Address Matching

Address matching occurs within one resolved customer.

Lookup key:

```text
customer_id + address_hash
```

### 24.1 Existing Active Address

When a matching active address exists:

- reuse it
- do not create a duplicate
- do not automatically overwrite label
- do not automatically overwrite phone
- do not automatically overwrite notes
- preserve the latest submitted values in the future order snapshot

### 24.2 Existing Deleted Address

When a matching deleted address exists during guest order creation:

- restore it automatically
- ensure active-address limit permits restoration
- maintain default-address invariant
- reuse it
- do not automatically overwrite label, phone, or notes

### 24.3 Missing Address

When no matching address exists:

- enforce active-address limit
- create it
- first address becomes default
- requested `isDefault=true` changes the current default
- return the resolved address to the future order workflow

---

## 25. Address Matching Concurrency

Two simultaneous requests must not create duplicate active addresses for the
same customer and normalized location.

Protection must include:

- transaction
- indexed lookup
- customer or related-address locking where justified
- final database-backed invariant
- retry/reload handling for duplicate-key race when generated uniqueness is
  used

A real MySQL concurrency test is required for the matching invariant when the
chosen schema supports direct database enforcement.

---

# Soft Delete and Restore

## 26. Customer Soft Delete

Route deletion performs a soft delete.

Rules:

- force delete is prohibited
- customer orders remain untouched
- historical order snapshots remain available
- active customer addresses are soft-deleted in the same transaction
- address records are not physically deleted
- deleted customer is excluded from normal active queries
- deleted customer may be restored manually
- matching guest order may restore the customer automatically

Required permission:

```text
customers.delete
```

---

## 27. Customer Restore

Manual restore:

- restores customer only
- does not restore all addresses automatically
- confirms normalized phone and email constraints remain valid
- fails safely when another active record now owns a unique value

Required permission:

```text
customers.restore
```

A matching guest-order workflow may restore the deleted customer automatically
through the domain matching service.

---

## 28. Address Soft Delete

Deleting an address:

- verifies ownership
- soft-deletes the address
- handles default fallback
- preserves historical order snapshots
- does not physically remove the row

Required permission:

```text
customer-addresses.delete
```

---

## 29. Address Restore

Manual restore:

- verifies the parent customer is active
- enforces maximum 20 active addresses
- verifies no equivalent active address exists
- restores the address
- makes it default only when no other active address exists
- otherwise restores as non-default unless the set-default command is invoked

Required permission:

```text
customer-addresses.restore
```

A matching guest order may restore the matching deleted address automatically.

---

# Order Integration Contract

## 30. Internal Customer Matching Service

Recommended service:

```text
CustomerMatchingService
```

Responsibilities:

- normalize phone
- validate phone
- normalize optional email
- find active customer
- find deleted matching customer
- restore deleted matching customer
- create customer when missing
- handle phone duplicate-key race
- return resolved customer

The service must not:

- create an order
- update saved name automatically from guest order
- update saved email automatically from guest order
- assign authentication credentials
- send email
- manage roles or permissions

---

## 31. Internal Address Service

Recommended service:

```text
CustomerAddressService
```

Responsibilities:

- normalize address fields
- normalize address phone
- calculate address hash
- find matching active address
- find matching deleted address
- restore matching deleted address
- create address when missing
- enforce maximum active addresses
- maintain one default address
- handle matching concurrency
- return resolved address

The service must not create an order.

---

## 32. Guest Order Resolution Flow

The future Orders Feature should use this sequence:

1. validate guest customer input
2. validate address input
3. begin order transaction
4. normalize customer phone
5. resolve customer by phone
6. normalize address
7. calculate address hash
8. resolve customer address
9. create order with customer ID
10. create order with customer-address ID
11. persist customer snapshot from submitted guest data
12. persist address snapshot from submitted guest data
13. create order items and remaining snapshots
14. commit

Files and other order concerns are defined by later Features.

---

## 33. Customer Snapshot Contract

The future order stores customer snapshot values:

```text
customer_name
customer_email nullable
customer_phone
customer_phone_normalized
```

The snapshot values come from the accepted order submission.

When the saved customer exists with different name or email:

- do not overwrite saved customer
- preserve submitted name and email in the order snapshot
- preserve submitted normalized phone in the snapshot

---

## 34. Address Snapshot Contract

The future order stores:

```text
address_phone
address_phone_normalized
address_country_code
address_city
address_area nullable
address_street
address_notes nullable
```

Do not include removed fields:

```text
recipient_name
building
floor
apartment
postal_code
```

The snapshot is independent of future address edits.

---

## 35. Snapshot Rules

Store both:

```text
customer_id
customer_address_id
```

and historical snapshot columns.

Rules:

- IDs preserve current relational linkage
- snapshots preserve historical meaning
- customer edits do not modify old orders
- address edits do not modify old orders
- customer deletion does not remove order snapshots
- address deletion does not remove order snapshots
- Customer Feature APIs cannot edit order snapshots
- Orders Feature defines any future controlled order-data correction

---

# Administration API

## 36. Customer Routes

```http
GET    /api/v1/admin/customers
POST   /api/v1/admin/customers
GET    /api/v1/admin/customers/{customer}
PATCH  /api/v1/admin/customers/{customer}
DELETE /api/v1/admin/customers/{customer}
POST   /api/v1/admin/customers/{customer}/restore
```

Rules:

- routes use the Feature 001 Sanctum Bearer Access Token contract
- routes are protected by `auth:sanctum`
- authenticated user must be an Administrator
- active Administrator required
- each operation uses its independent permission after authentication,
  Administrator classification, and active-state checks
- Feature 002 never accepts or processes a Refresh Token
- no authentication cookie or CSRF dependency
- no force-delete route
- no bulk route
- no export route

---

## 37. Address Routes

```http
GET    /api/v1/admin/customers/{customer}/addresses
POST   /api/v1/admin/customers/{customer}/addresses
GET    /api/v1/admin/customers/{customer}/addresses/{address}
PATCH  /api/v1/admin/customers/{customer}/addresses/{address}
DELETE /api/v1/admin/customers/{customer}/addresses/{address}
POST   /api/v1/admin/customers/{customer}/addresses/{address}/restore
PUT    /api/v1/admin/customers/{customer}/addresses/{address}/default
```

Rules:

- routes use the Feature 001 Sanctum Bearer Access Token contract
- nested ownership required
- address belonging to another customer returns `404`
- each operation uses an independent address permission after authentication,
  Administrator classification, and active-state checks
- Feature 002 never accepts or processes a Refresh Token
- no authentication cookie or CSRF dependency
- no public address API
- no address transfer endpoint

---

## 38. Method Override

These endpoints contain no file upload requirement.

The preferred request is ordinary JSON:

```http
PATCH /api/v1/admin/customers/{customer}
PATCH /api/v1/admin/customers/{customer}/addresses/{address}
```

Laravel method override may be accepted consistently where the frontend uses:

```text
_method=PATCH
```

but it is not required by this Feature.

---

# Permissions

## 39. Customer Permissions

Create with this Feature:

```text
customers.view
customers.create
customers.update
customers.delete
customers.restore
```

Mapping:

```text
list and detail -> customers.view
create -> customers.create
update -> customers.update
soft delete -> customers.delete
restore -> customers.restore
```

---

## 40. Address Permissions

Create independently:

```text
customer-addresses.view
customer-addresses.create
customer-addresses.update
customer-addresses.delete
customer-addresses.restore
customer-addresses.set-default
```

Mapping:

```text
address list/detail -> customer-addresses.view
address create -> customer-addresses.create
address update -> customer-addresses.update
address soft delete -> customer-addresses.delete
address restore -> customer-addresses.restore
set default -> customer-addresses.set-default
```

Rules:

- address permissions are not implied by customer permissions
- customer permissions are not implied by address permissions
- Super Admin receives all new permissions
- Feature Seeder must not remove permissions from other Features
- permission identifiers remain English

---

## 41. Route Permission Matrix

| Route | Permission |
|---|---|
| `GET /admin/customers` | `customers.view` |
| `POST /admin/customers` | `customers.create` |
| `GET /admin/customers/{customer}` | `customers.view` |
| `PATCH /admin/customers/{customer}` | `customers.update` |
| `DELETE /admin/customers/{customer}` | `customers.delete` |
| `POST /admin/customers/{customer}/restore` | `customers.restore` |
| `GET /admin/customers/{customer}/addresses` | `customer-addresses.view` |
| `POST /admin/customers/{customer}/addresses` | `customer-addresses.create` |
| `GET /admin/customers/{customer}/addresses/{address}` | `customer-addresses.view` |
| `PATCH /admin/customers/{customer}/addresses/{address}` | `customer-addresses.update` |
| `DELETE /admin/customers/{customer}/addresses/{address}` | `customer-addresses.delete` |
| `POST /admin/customers/{customer}/addresses/{address}/restore` | `customer-addresses.restore` |
| `PUT /admin/customers/{customer}/addresses/{address}/default` | `customer-addresses.set-default` |

All route paths are prefixed by:

```text
/api/v1
```

---

# Customer Requests

## 42. Create Customer Request

```json
{
  "name": "Customer Name",
  "email": "customer@example.com",
  "phone": "01001234567",
  "phoneCountryCode": "EG"
}
```

Validation:

```text
name:
required
string
maximum 150

email:
nullable
string
email
maximum 255
unique when normalized

phone:
required
string
maximum 30

phoneCountryCode:
nullable
string
size 2
valid country code
default EG
```

Status:

```text
201 Created
```

---

## 43. Update Customer Request

```json
{
  "name": "Updated Customer Name",
  "email": "updated@example.com",
  "phone": "+201001234567",
  "phoneCountryCode": "EG"
}
```

Rules:

- every field is optional
- at least one mutable field should be present
- email empty string becomes null
- phone change recalculates normalized phone
- unique phone enforced
- unique non-null email enforced
- snapshots remain unchanged
- type/authentication fields are rejected

Status:

```text
200 OK
```

---

## 44. Duplicate Customer Phone

Admin create/update duplicate:

```text
HTTP 422
code: CUSTOMER_PHONE_ALREADY_EXISTS
```

The error attaches to:

```text
phone
```

---

## 45. Duplicate Customer Email

Admin create/update duplicate non-null email:

```text
HTTP 422
code: CUSTOMER_EMAIL_ALREADY_EXISTS
```

The error attaches to:

```text
email
```

---

# Address Requests

## 46. Create Address Request

```json
{
  "label": "Home",
  "phone": "01001234567",
  "phoneCountryCode": "EG",
  "countryCode": "EG",
  "city": "Cairo",
  "area": "Nasr City",
  "street": "Example Street",
  "notes": "Call before arrival",
  "isDefault": true
}
```

Validation:

```text
label:
nullable
string
maximum 100

phone:
required
string
maximum 30

phoneCountryCode:
nullable
string
size 2
default to address countryCode, then EG

countryCode:
required
string
size 2
valid ISO Alpha-2

city:
required
string
maximum 150

area:
nullable
string
maximum 150

street:
required
string
maximum 255

notes:
nullable
string
maximum 1000

isDefault:
nullable
boolean
```

Status:

```text
201 Created
```

---

## 47. Update Address Request

```json
{
  "label": "Office",
  "phone": "+201001234567",
  "phoneCountryCode": "EG",
  "countryCode": "EG",
  "city": "Giza",
  "area": null,
  "street": "Updated Street",
  "notes": "",
  "isDefault": true
}
```

Rules:

- mutable fields are optional
- identity-field changes recalculate address hash
- empty optional text becomes null
- setting `isDefault=true` invokes default-address invariant
- do not allow the operation to leave multiple defaults
- do not allow `customerId` change
- do not accept `addressHash`
- order snapshots remain unchanged

---

## 48. Set Default Request

Route:

```http
PUT /api/v1/admin/customers/{customer}/addresses/{address}/default
```

Body:

```text
Empty
```

Response:

```text
200 OK
```

The selected address must be active.

---

# API Resources

## 49. Customer List Resource

Example:

```json
{
  "id": 12,
  "name": "Customer Name",
  "email": "customer@example.com",
  "phone": "+20 100 123 4567",
  "isDeleted": false,
  "addressesCount": 2,
  "createdAt": "2026-07-28T10:00:00Z"
}
```

Rules:

- return `id` for administration navigation
- return display phone only
- do not return `phoneNormalized`
- do not return password/auth fields
- `addressesCount` counts active addresses unless contract states otherwise

---

## 50. Customer Detail Resource

Example:

```json
{
  "id": 12,
  "name": "Customer Name",
  "email": "customer@example.com",
  "phone": "+20 100 123 4567",
  "isDeleted": false,
  "deletedAt": null,
  "addressesCount": 2,
  "addresses": [
    {
      "id": 30,
      "label": "Home",
      "phone": "+20 100 123 4567",
      "countryCode": "EG",
      "city": "Cairo",
      "area": "Nasr City",
      "street": "Example Street",
      "notes": "Call before arrival",
      "isDefault": true,
      "isDeleted": false,
      "deletedAt": null,
      "createdAt": "2026-07-28T10:00:00Z"
    }
  ],
  "createdAt": "2026-07-28T10:00:00Z",
  "updatedAt": "2026-07-28T10:00:00Z"
}
```

Rules:

- details may include active addresses
- deleted-address inclusion requires an explicit query filter
- no address hash
- no normalized phone
- no order list in this Feature

---

## 51. Address Resource

Fields:

```text
id
label
phone
countryCode
city
area
street
notes
isDefault
isDeleted
deletedAt
createdAt
updatedAt
```

Do not return:

```text
customerId when already nested unless useful
phoneNormalized
addressHash
removed address fields
```

---

# Listing, Search, Filters, and Sorting

## 52. Customer List Route

```http
GET /api/v1/admin/customers
```

Supported query parameters:

```text
filter[search]
filter[status]
filter[hasAddresses]
filter[createdFrom]
filter[createdTo]
sort
page
perPage
```

---

## 53. Search

Search approved fields:

```text
name
email
phone
phone_normalized
```

Rules:

- search is trimmed
- maximum query length is bounded
- phone-like search may be normalized when possible
- use bound parameters
- no raw SQL fragments
- no order snapshot search in this Feature

---

## 54. Status Filter

Approved values:

```text
active
deleted
all
```

Default:

```text
active
```

Rules:

- `deleted` uses only trashed customers
- `all` includes active and deleted
- invalid value returns validation error

---

## 55. Address Presence Filter

```text
filter[hasAddresses]=true
filter[hasAddresses]=false
```

Counts active addresses only.

---

## 56. Date Filters

```text
filter[createdFrom]
filter[createdTo]
```

Rules:

- valid date format according to API standard
- inclusive approved boundaries
- invalid range rejected
- database timestamps remain UTC

---

## 57. Sorting

Approved customer sort values:

```text
createdAt
-createdAt
name
-name
```

Default:

```text
-createdAt
```

Unknown sort field is rejected.

---

## 58. Pagination

```text
default perPage: 20
maximum perPage: 100
```

Response follows the shared pagination envelope.

No unpaginated customer list endpoint is provided.

---

## 59. Address List

Address list may be unpaginated because the active limit is 20.

Supported filter:

```text
filter[status]=active|deleted|all
```

Recommended ordering:

```text
is_default DESC
created_at DESC
id DESC
```

---

# Deletion Behaviour

## 60. Customer With Orders

A customer with orders may be soft-deleted.

Rules:

- orders remain
- customer foreign key remains
- snapshots remain
- no force delete
- new guest order matching phone automatically restores the customer
- admin may restore manually

---

## 61. Customer Delete Transaction

1. authorize `customers.delete`
2. load customer
3. begin transaction
4. soft-delete active addresses
5. soft-delete customer
6. commit
7. return success

Example response:

```json
{
  "success": true,
  "message": "تم حذف العميل بنجاح.",
  "data": null
}
```

---

## 62. Customer Restore Transaction

1. authorize `customers.restore`
2. load customer with trashed
3. validate phone uniqueness
4. validate non-null email uniqueness
5. restore customer only
6. preserve addresses as deleted
7. return customer detail

---

## 63. Address Delete Transaction

1. authorize `customer-addresses.delete`
2. load address through customer
3. begin transaction
4. determine whether it is default
5. soft-delete address
6. select newest active fallback when needed
7. mark fallback default
8. commit

---

## 64. Address Restore Transaction

1. authorize `customer-addresses.restore`
2. verify customer is active
3. load deleted address through customer
4. enforce active limit
5. check equivalent active address
6. restore
7. if no active default exists, make restored address default
8. otherwise restore as non-default
9. commit

---

# Errors

## 65. Stable Error Codes

```text
CUSTOMER_NOT_FOUND
CUSTOMER_PHONE_ALREADY_EXISTS
CUSTOMER_EMAIL_ALREADY_EXISTS
CUSTOMER_PHONE_INVALID
CUSTOMER_DELETED
CUSTOMER_ADDRESS_NOT_FOUND
CUSTOMER_ADDRESS_ALREADY_EXISTS
CUSTOMER_ADDRESS_LIMIT_EXCEEDED
CUSTOMER_DEFAULT_ADDRESS_REQUIRED
CUSTOMER_ADDRESS_RESTORE_CONFLICT
VALIDATION_ERROR
UNAUTHENTICATED
USER_INACTIVE
FORBIDDEN
RATE_LIMITED
INTERNAL_ERROR
```

Authentication and authorization boundaries:

Missing, invalid, expired, or revoked Administrator Access Token:

```text
HTTP 401
code: UNAUTHENTICATED
```

A predecessor Access Token used after a successful Feature 001 refresh:

```text
HTTP 401
code: UNAUTHENTICATED
```

Authenticated Administrator whose account is inactive:

```text
HTTP 403
code: USER_INACTIVE
```

Authenticated active Administrator without the required operation permission:

```text
HTTP 403
code: FORBIDDEN
```

Feature 002 MUST preserve these boundaries and MUST NOT convert an inactive
Administrator into `FORBIDDEN` or a permission failure into
`UNAUTHENTICATED`.

---

## 66. Nested Address Non-Disclosure

When an address exists but belongs to another customer:

```text
HTTP 404
code: CUSTOMER_ADDRESS_NOT_FOUND
```

Do not reveal that the address exists under another customer.

---

## 67. Missing Customer

```text
HTTP 404
code: CUSTOMER_NOT_FOUND
```

For restore routes, query with trashed before deciding not found.

---

## 68. Duplicate Address

When an administrator attempts to create an address whose normalized hash
already belongs to an active address for that customer:

```text
HTTP 422
code: CUSTOMER_ADDRESS_ALREADY_EXISTS
```

The response may include the existing address ID only when the approved API
contract explicitly allows it.

Default recommendation:

```text
do not return existing ID in the error
```

---

# Localization

## 69. Supported Locales

```text
ar
en
```

Locale source:

```http
Accept-Language
```

Translate:

- customer API messages
- address API messages
- validation errors
- phone validation errors
- duplicate errors
- delete/restore messages
- default-address errors

Do not translate:

- IDs
- email values
- phone values
- ISO country codes
- error codes
- permission names
- request keys
- response keys

---

## 70. Recommended Translation Keys

```text
customers.created
customers.retrieved
customers.listed
customers.updated
customers.deleted
customers.restored
customers.errors.not_found
customers.errors.phone_invalid
customers.errors.phone_exists
customers.errors.email_exists
customers.errors.deleted

customer_addresses.created
customer_addresses.retrieved
customer_addresses.listed
customer_addresses.updated
customer_addresses.deleted
customer_addresses.restored
customer_addresses.default_updated
customer_addresses.errors.not_found
customer_addresses.errors.already_exists
customer_addresses.errors.limit_exceeded
customer_addresses.errors.default_required
customer_addresses.errors.restore_conflict
```

---

# Security and Privacy

## 71. Customer Data in Logs

Logs may include:

```text
request_id
customer_id
address_id
operation
route
HTTP status
```

Do not log:

```text
customer name
email
phone
address
notes
guest order payload
```

---

## 72. Administration Visibility

Authorized administrators may view full customer email, phone, and address
fields required for service operations.

Rules:

- no public customer list
- no public customer detail
- no public address list
- no customer data in guest confirmation beyond approved order confirmation
- API Resources use explicit fields
- raw normalized identity values remain internal where possible

---

## 73. Plain Text

All customer and address text is plain text.

Do not accept or render:

```text
HTML
Markdown
scripts
embedded content
```

Frontend renders values as text.

---

## 73.1 Authentication Data Boundary

Feature 002 request validation and mutation DTOs MUST reject or ignore
authentication-related fields according to the project's strict unknown-field
policy.

Feature 002 MUST NOT accept:

```text
accessToken
refreshToken
administratorId
adminId
userId
role
roles
permissions
isActive
tokenExpiresIn
refreshTokenExpiresIn
```

The acting Administrator is always obtained from the current authenticated
request context.

Feature 002 resources and envelopes MUST NOT return:

```text
accessToken
refreshToken
tokenType
tokenExpiresIn
refreshTokenExpiresIn
authentication cookies
internal Sanctum token metadata
```

Customer and address records remain guest-domain data and never become
authentication principals in this Feature.

---

## 74. Mass Assignment

Do not mass assign:

```text
phone_normalized
address_hash
customer_id during update
deleted_at
created_at
updated_at
```

Use validated DTOs or explicit arrays.

Backend calculates normalized and identity fields.

---

# Architecture

## 75. Controllers

Recommended controllers:

```text
CustomerController
CustomerRestoreController
CustomerAddressController
CustomerAddressRestoreController
SetDefaultCustomerAddressController
```

`CustomerController` may contain:

```text
index
store
show
update
destroy
```

`CustomerAddressController` may contain:

```text
index
store
show
update
destroy
```

Restore and set-default commands remain focused controllers.

---

## 76. Form Requests

Recommended:

```text
ListCustomersRequest
CreateCustomerRequest
UpdateCustomerRequest
CreateCustomerAddressRequest
UpdateCustomerAddressRequest
ListCustomerAddressesRequest
```

Delete, restore, show, and set-default may use route validation and Policies
without empty Form Requests unless repository conventions require them.

---

## 77. Actions

Recommended:

```text
CreateCustomerAction
UpdateCustomerAction
DeleteCustomerAction
RestoreCustomerAction
CreateCustomerAddressAction
UpdateCustomerAddressAction
DeleteCustomerAddressAction
RestoreCustomerAddressAction
SetDefaultCustomerAddressAction
```

Matching Actions or Services:

```text
ResolveGuestCustomerAction
ResolveGuestCustomerAddressAction
```

---

## 78. Services

Recommended reusable services:

```text
PhoneNumberService
CustomerMatchingService
AddressNormalizationService
CustomerAddressService
```

### PhoneNumberService

- parse phone
- apply default country
- validate
- return display value
- return E.164

### AddressNormalizationService

- normalize identity fields
- canonicalize country code
- normalize whitespace
- generate SHA-256 hash

### CustomerMatchingService

- resolve by phone
- restore deleted match
- create missing customer
- handle concurrency race

### CustomerAddressService

- resolve by hash
- restore deleted match
- create missing address
- maintain default invariant
- enforce address limit

---

## 79. Policies

Recommended:

```text
CustomerPolicy
CustomerAddressPolicy
```

Policies or scoped binding verify:

- administrator permission already checked by route middleware
- nested address belongs to customer
- deleted-state operation is appropriate
- requested command targets correct resource

Business orchestration remains in Actions.

---

## 80. API Resources

Recommended:

```text
CustomerListResource
CustomerDetailResource
CustomerAddressResource
```

Do not return Eloquent models directly.

---

## 81. Queries

Recommended:

```text
CustomerIndexQuery
```

Responsibilities:

- search
- status filter
- address-presence filter
- date filters
- sorting
- pagination
- active address count

Use a Query class because the list has multiple filters and aggregates.

---

# Database Transactions and Concurrency

## 82. Required Transactions

Use transactions for:

```text
customer matching during guest order
customer soft delete with addresses
customer restore uniqueness validation
address create with default handling
address update that changes identity/default
address delete with fallback default
address restore
set default
```

---

## 83. Locks

Use row locks where needed for:

```text
default-address changes
active-address limit
address restoration
concurrent matching
customer restoration
```

The exact lock strategy belongs in the implementation plan.

Avoid locking more rows than required.

---

## 84. Unique Constraints

Database constraints protect:

```text
customer email when non-null
customer normalized phone
```

Address identity requires the approved MySQL-compatible active uniqueness
strategy.

Application validation improves messages.

Database constraints provide final race protection.

---

# Testing

## 85. Lean Testing Strategy

Feature 002 uses a focused test suite. The goal is to protect business-critical
behaviour without generating a separate test or task for every validation rule,
route, permission, locale, or security variation.

Use:

```text
Pest
MySQL testing database
RefreshDatabase
API feature tests
focused domain tests
critical concurrency tests only
```

Do not use SQLite for the database invariants covered by this Feature.

### Test planning rule

The implementation plan and `tasks.md` MUST consolidate related scenarios.

Do not create:

- one task per validation rule
- one test file per route
- one task per permission
- one task per locale
- one task per security assertion
- duplicated Feature 001 token-security tests
- exhaustive penetration-testing tasks
- repeated tests for the same middleware behaviour on every endpoint

Prefer one implementation task and one focused test task per business area.

Recommended test groups:

```text
CustomerApiTest.php
CustomerAddressApiTest.php
CustomerMatchingTest.php
CustomerAddressMatchingTest.php
CustomerCriticalConcurrencyTest.php
CustomerAuthBoundaryTest.php
```

The final file names may follow existing repository conventions, but the suite
must remain consolidated.

---

## 86. Required Customer API Coverage

One consolidated customer API suite must cover:

- create a customer with a valid Egyptian local phone
- create a customer with a valid international phone
- reject an invalid phone
- enforce unique normalized phone
- allow multiple customers with `email = null`
- reject duplicate non-null normalized email
- update mutable fields
- list with the main search, status, sorting, and pagination behaviour
- soft-delete customer and active addresses
- restore customer without automatically restoring addresses
- return explicit Resources without normalized phone or authentication fields
- enforce the required customer permission

Detailed validation permutations do not require separate tests when Laravel
validation and a representative boundary test already cover the rule.

---

## 87. Required Address API Coverage

One consolidated address API suite must cover:

- create an address with the approved fields
- first active address becomes default
- creating or selecting a new default clears the previous default
- maximum 20 active addresses
- duplicate normalized address is rejected
- update identity fields recalculates the address hash
- delete the default address and choose the deterministic fallback
- restore a deleted address
- nested address belonging to another customer returns
  `CUSTOMER_ADDRESS_NOT_FOUND`
- removed fields are not persisted
- Resource does not expose `phoneNormalized` or `addressHash`
- enforce the required address permission, including the independent
  set-default permission

Do not create a separate test file for every address route.

---

## 88. Required Matching Coverage

Focused domain tests must cover:

### Customer matching

- active customer is resolved by normalized phone
- saved name and email are not overwritten by guest-order input
- matching deleted customer is restored
- missing customer is created

### Address matching

- active matching address is reused
- saved label, phone, and notes are not overwritten
- matching deleted address is restored
- first active restored/created address becomes default when appropriate
- active-address limit remains enforced

Snapshot field availability may be verified in these domain tests without
creating separate snapshot test suites before the Orders Feature exists.

---

## 89. Critical Concurrency Coverage

Only the following concurrency tests are mandatory for Feature 002:

1. simultaneous customer creation with the same normalized phone resolves to
   one customer
2. simultaneous default-address changes leave one active default
3. simultaneous matching address creation does not leave duplicate active
   addresses when the selected database strategy supports enforcement

These tests run against MySQL.

Do not create concurrency tests for every CRUD operation.

---

## 90. Minimal Authentication and Authorization Coverage

Feature 001 owns token issuance, refresh rotation, token reuse detection,
cookies/CSRF exclusion, and token-storage security.

Feature 002 MUST NOT duplicate the full Feature 001 security suite.

Use one focused auth-boundary suite that proves:

```text
missing/invalid Access Token -> 401 UNAUTHENTICATED
inactive Administrator -> 403 USER_INACTIVE
active Administrator without required permission -> 403 FORBIDDEN
valid active Administrator with permission -> representative route succeeds
```

Also verify once at architecture level:

- Feature 002 routes use
  `auth:sanctum -> EnsureUserIsAdministrator -> EnsureAdminIsActive ->
  permission`
- no public customer CRUD route exists
- no customer authentication route exists
- Feature 002 does not issue or accept Refresh Tokens
- Feature 002 does not return Access Tokens or Refresh Tokens

The following belong to Feature 001 and MUST NOT become Feature 002 tasks:

- Refresh Token rotation tests
- predecessor Access Token revocation tests
- token reuse-detection tests
- IP-only refresh throttling tests
- authentication cookie/CSRF test matrices
- frontend token-storage tests
- CSP tests
- token entropy tests
- authentication log-redaction suites

A single integration smoke check using a valid Feature 001 Access Token is
sufficient for Feature 002.

---

## 91. Minimal Localization Coverage

Use focused localization tests only:

- one successful Arabic response
- one successful English response
- one Arabic validation or domain error
- one English validation or domain error
- stable error code remains English

Do not duplicate the complete business suite in both languages.

---

## 92. Minimal Architecture Coverage

One architecture test must verify:

- exact customer and address administration routes
- protected middleware order
- customer and address permissions remain independent
- nested ownership is enforced
- no public customer CRUD
- no customer authentication
- no force-delete route
- matching logic is outside Controllers
- Resources do not expose normalized identity or authentication fields

---

## 93. Recommended Test Task Grouping

Planning and task generation should produce approximately these test tasks:

```text
1. customer API and customer permission tests
2. address API, nested ownership, and address permission tests
3. customer/address matching tests
4. critical MySQL concurrency tests
5. minimal auth-boundary, localization, and architecture tests
```

Do not expand these into dozens of security or validation subtasks unless an
actual implementation defect requires a focused regression test.

---

# Acceptance Scenarios

## 100. Manual Customer Creation

Given an authenticated administrator with `customers.create`  
When a valid customer with an Egyptian local phone is submitted  
Then the phone is normalized to E.164  
And one customer is created  
And the response returns the display phone  
And the normalized phone is not exposed.

---

## 101. Future Email Login Readiness

Given customer email is optional  
When one customer has no email  
And another customer also has no email  
Then both records are valid.

Given a non-null email already exists  
When another customer submits the same normalized email  
Then the request fails with `CUSTOMER_EMAIL_ALREADY_EXISTS`.

---

## 102. Returning Guest Customer

Given an active customer exists with the same normalized phone  
When a future guest order uses that phone with a different name or email  
Then the existing customer is used  
And saved name and email are not overwritten  
And submitted data is preserved for the order snapshot.

---

## 103. Deleted Returning Customer

Given a customer is soft-deleted  
When a future guest order submits the same normalized phone  
Then the customer is restored  
And no duplicate customer is created  
And all old addresses are not restored automatically.

---

## 104. First Address

Given a customer has no active address  
When an address is created  
Then it becomes the default automatically.

---

## 105. New Default Address

Given a customer has one default address  
When another address is created with `isDefault=true`  
Then the old address becomes non-default  
And the new address becomes default  
And exactly one active default remains.

---

## 106. Duplicate Address

Given a customer already has an active address  
When another request contains the same normalized country, city, area, and
street  
Then no duplicate address is created through matching  
And administrator manual creation returns
`CUSTOMER_ADDRESS_ALREADY_EXISTS`.

---

## 107. Deleted Address Reuse

Given a matching address is soft-deleted  
When the future guest order submits that location  
Then the matching address is restored  
And no duplicate is created.

---

## 108. Default Address Deletion

Given a customer has multiple active addresses  
And one is default  
When the default address is deleted  
Then the newest remaining active address becomes default.

---

## 109. Historical Snapshot

Given an order stores customer and address snapshots  
When the administrator updates or deletes the customer or address  
Then the order snapshot remains unchanged.

---

# Implementation File Map

## 110. Recommended Files

```text
app/
  Actions/
    Customers/
      CreateCustomerAction.php
      UpdateCustomerAction.php
      DeleteCustomerAction.php
      RestoreCustomerAction.php
      ResolveGuestCustomerAction.php

    CustomerAddresses/
      CreateCustomerAddressAction.php
      UpdateCustomerAddressAction.php
      DeleteCustomerAddressAction.php
      RestoreCustomerAddressAction.php
      SetDefaultCustomerAddressAction.php
      ResolveGuestCustomerAddressAction.php

  Http/
    Controllers/
      Api/
        V1/
          Admin/
            Customers/
              CustomerController.php
              CustomerRestoreController.php
              CustomerAddressController.php
              CustomerAddressRestoreController.php
              SetDefaultCustomerAddressController.php

    Requests/
      Api/
        V1/
          Admin/
            Customers/
              ListCustomersRequest.php
              CreateCustomerRequest.php
              UpdateCustomerRequest.php
              ListCustomerAddressesRequest.php
              CreateCustomerAddressRequest.php
              UpdateCustomerAddressRequest.php

    Resources/
      Api/
        V1/
          Admin/
            Customers/
              CustomerListResource.php
              CustomerDetailResource.php
              CustomerAddressResource.php

  Models/
    Customer.php
    CustomerAddress.php

  Policies/
    CustomerPolicy.php
    CustomerAddressPolicy.php

  Queries/
    Customers/
      CustomerIndexQuery.php

  Services/
    Customers/
      PhoneNumberService.php
      CustomerMatchingService.php
      AddressNormalizationService.php
      CustomerAddressService.php

database/
  migrations/
    create_customers_table.php
    create_customer_addresses_table.php

  factories/
    CustomerFactory.php
    CustomerAddressFactory.php

  seeders/
    CustomerPermissionsSeeder.php

lang/
  ar/
    customers.php
    customer_addresses.php

  en/
    customers.php
    customer_addresses.php

tests/
  Feature/
    Api/
      V1/
        Admin/
          Customers/
            CustomerApiTest.php
            CustomerAddressApiTest.php
            CustomerAuthBoundaryTest.php

    Domain/
      Customers/
        CustomerMatchingTest.php
        CustomerAddressMatchingTest.php

    Concurrency/
      Customers/
        CustomerCriticalConcurrencyTest.php

  Architecture/
    CustomerFeatureArchitectureTest.php
```

Migration filenames follow Laravel timestamp conventions.

---

# Implementation Order

## 111. Recommended Sequence

1. create customer migration
2. create customer-address migration
3. add Models and relationships
4. add phone-number service
5. add address-normalization service
6. add factories and states
7. create permissions Seeder
8. create customer Resources
9. implement customer administration routes
10. implement address administration routes
11. implement soft-delete and restore Actions
12. implement default-address Action
13. implement customer matching service
14. implement address matching service
15. add localization
16. update Postman collection
17. add consolidated customer and address API tests
18. add focused matching tests
19. add only the critical MySQL concurrency tests
20. add one minimal auth/localization/architecture test group
21. run Pint, Larastan, and Pest

---

# Planning and Task Generation Constraint

## 111.1 Lean Task Rule

When this Feature is processed by `/speckit.specify`, `/speckit.plan`, or task
generation:

- consolidate related implementation work
- keep testing tasks grouped by business area
- do not generate one task per validation case
- do not generate one security task per route
- do not repeat Feature 001 authentication internals
- keep Feature 002 focused on customers, addresses, matching, default-address
  invariants, permissions, and critical concurrency
- security work is limited to applying the existing Feature 001 middleware
  contract and one focused integration test group

Target:

```text
small number of meaningful implementation tasks
approximately five consolidated testing tasks
no duplicated authentication-security programme
```

---

# Definition of Done

## 112. Completion Criteria

The Feature is complete only when:

- customers table exists
- customer-address table exists
- customer email is nullable and unique when present
- customer phone is normalized to E.164
- Egyptian local phone handling works
- international phone handling works
- customer phone is unique
- customer matching uses normalized phone only
- deleted matching customer restores automatically
- admin customer CRUD and restore routes exist
- force delete does not exist
- customer addresses support the approved fields only
- removed address fields are absent
- maximum 20 active addresses is enforced
- address hash is generated by backend
- matching active address is reused
- matching deleted address restores automatically
- one active default address is enforced
- customer deletion soft-deletes active addresses
- customer restoration does not restore all addresses
- customer and address permissions are independent
- Super Admin receives all Feature permissions
- snapshot contracts are documented
- customer and address updates do not alter historical snapshots
- public customer CRUD does not exist
- customer authentication does not exist
- all Admin routes follow the Feature 001 Bearer Access Token contract
- middleware order is
  `auth:sanctum -> EnsureUserIsAdministrator -> EnsureAdminIsActive ->
  permission -> endpoint`
- missing, invalid, expired, revoked, or predecessor Access Tokens return
  `UNAUTHENTICATED`
- inactive Administrator returns `USER_INACTIVE`
- missing permission returns `FORBIDDEN`
- Feature 002 never accepts Refresh Tokens
- Feature 002 never returns Access Tokens or Refresh Tokens
- no authentication cookie, CSRF, Origin-authentication, or Proxy/BFF
  dependency exists
- MySQL concurrency tests pass
- Arabic and English messages exist
- Postman documentation is updated
- Pest passes
- Pint passes
- Larastan passes

---

# Non-Negotiable Rules

## 113. Mandatory Rules

- Customer is a guest domain record, not an authenticated account.
- Feature 002 Admin routes must use the Feature 001 Sanctum Bearer Access Token
  contract.
- Resolve the acting Administrator only from the authenticated request context.
- Apply middleware in this order:
  `auth:sanctum -> EnsureUserIsAdministrator -> EnsureAdminIsActive ->
  permission -> endpoint`.
- Do not accept `accessToken`, `refreshToken`, `administratorId`, `adminId`,
  `userId`, roles, permissions, or active state from Feature 002 request input.
- Do not issue, rotate, accept, or return Access Tokens or Refresh Tokens in
  Feature 002.
- Do not add authentication cookies, CSRF, Origin-authentication, Proxy/BFF, or
  `withCredentials=true` dependencies.
- Do not create customer passwords.
- Do not create customer login routes.
- Use one customer `name` field.
- Customer email is optional.
- Non-null customer email is unique.
- Normalize customer email to lowercase.
- Customer phone is required.
- Normalize customer phone to E.164.
- Default phone country is Egypt.
- Support valid international phone numbers.
- Do not accept phone extensions.
- Match customers by normalized phone only.
- Do not match customers by name.
- Do not match customers by email.
- Do not auto-update saved customer name from guest orders.
- Do not auto-update saved customer email from guest orders.
- Restore a matching soft-deleted customer automatically.
- Use a database unique constraint for normalized phone.
- Do not add customer notes.
- Do not add customer active flag.
- Use customer soft deletes.
- Do not force delete customers.
- Support multiple addresses.
- Enforce maximum 20 active addresses.
- Enforce one active default address.
- First active address becomes default.
- Use independent customer-address permissions.
- Use a separate set-default permission.
- Do not add recipient name.
- Do not add building.
- Do not add floor.
- Do not add apartment.
- Do not add postal code.
- Address phone is required.
- Address country code is required.
- Address city is required.
- Address street is required.
- Address notes are plain text.
- Generate address hash on the backend.
- Address hash uses country, city, area, and street.
- Do not include label in address hash.
- Do not include notes in address hash.
- Do not include phone in address hash.
- Restore a matching soft-deleted address automatically.
- Do not auto-update saved address details during guest matching.
- Use customer and address snapshots in orders.
- Do not modify historical snapshots from Customer APIs.
- Do not expose normalized phone or address hash unnecessarily.
- Do not log customer personal data.
- Do not create public customer CRUD.
- Do not create customer export in this Feature.
- Do not create customer order-history route in this Feature.
- Test duplicate phone creation with real MySQL concurrency.
- Keep Feature 002 tests consolidated and focused.
- Do not duplicate Feature 001 token, refresh, browser-storage, CSP, or
  authentication security test suites.
- Do not generate one task per validation rule, permission, route, or security
  assertion.
