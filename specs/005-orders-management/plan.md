# Implementation Plan: Orders Management

**Branch**: `005-orders-management`  
**Date**: 2026-08-01  
**Status**: Ready for Task Generation  
**Specification**: [spec.md](./spec.md)  
**Approved reference**: `docs/features/005-orders-management.md`

## 1. Summary

Feature 005 adds complete guest and administrator order management to the
Laravel 13 API:

- Public guest order creation with idempotency, rate limiting, localized
  responses, customer resolution, optional address snapshot, and item-level
  attachments in one multipart request.
- Admin order index, create, show, update, payment summary update, status
  transitions, and conditional hard delete.
- Nested admin order-item create, show, update, and delete without allowing
  service replacement on an existing item.
- Historical snapshots for customer, address, service, pricing selections,
  answers, and attachments so later domain changes do not rewrite past orders.
- Backend-controlled pricing, discounts, cumulative payment summary, and
  remaining amount calculations.
- Protected nested attachment download plus editable-state-controlled attachment
  upload and delete flows.
- Race-safe order number allocation and idempotency reservation.

The implementation remains inside the existing Laravel monolith. Controllers
stay thin; Form Requests own request validation; Resources own response
projection; Query classes own list behavior; Actions own transactional,
locking, snapshot, and filesystem-compensation workflows; Services own reusable
pricing, numbering, matching, and attachment capabilities.

## 2. Technical Context

| Area | Decision |
|---|---|
| Runtime | PHP 8.3, Laravel 13 |
| Database | MySQL with real MySQL test database; no SQLite assumptions |
| Authentication | Sanctum Bearer access tokens for Admin APIs |
| Authorization | Spatie Permission with exact order, item, and attachment permissions |
| Query contract | `spatie/laravel-query-builder` allow-lists for admin lists |
| Storage | Laravel Filesystem on configurable disk with protected-order attachment design approved by Feature 005 |
| Testing | Pest, Pint, Larastan/PHPStan, API Feature Tests, and targeted real MySQL race tests |
| Deployment | Hostinger-compatible PHP/MySQL; no Redis, scheduler, queue worker, Docker, or shell dependency added |
| API format | Shared success/error envelope, camelCase payload keys, the existing `App\\Enums\\HttpStatusCode` enum for HTTP status selection |
| Performance goals | Admin order index paginates by default to 15 and caps at 100; public order create handles up to 50 items and up to 3 attachments per item without partial persistence |
| Constraints | Backend-only scope, Arabic/English localization, exact permission checks, immutable snapshots, no customer portal, no payment gateway, no status-history table |
| Scale/Scope | One order contains 1-50 items. Each item supports up to 3 attachments at up to 10 MB per file. One create request supports at most 30 attachment files and 100 MB combined upload size. List endpoints remain bounded through allow-listed filters and pagination |

## 3. Scope and Contract Freeze

The plan implements exactly:

- **1 unauthenticated Public order operation**:
  - `POST /api/v1/public/orders`
- **10 protected Admin order operations**:
  - `GET /api/v1/admin/orders`
  - `POST /api/v1/admin/orders`
  - `GET /api/v1/admin/orders/{order}`
  - `PATCH /api/v1/admin/orders/{order}`
  - `DELETE /api/v1/admin/orders/{order}`
  - `PATCH /api/v1/admin/orders/{order}/status`
  - `GET /api/v1/admin/orders/{order}/payment`
  - `PATCH /api/v1/admin/orders/{order}/payment`
  - `GET /api/v1/admin/orders/{order}/items`
  - `POST /api/v1/admin/orders/{order}/items`
- **5 protected nested Admin item and attachment operations**:
  - `GET /api/v1/admin/orders/{order}/items/{orderItem}`
  - `PATCH /api/v1/admin/orders/{order}/items/{orderItem}`
  - `DELETE /api/v1/admin/orders/{order}/items/{orderItem}`
  - `POST /api/v1/admin/orders/{order}/items/{orderItem}/attachments`
  - `DELETE /api/v1/admin/orders/{order}/items/{orderItem}/attachments/{attachment}`
- **1 protected nested attachment download operation**:
  - `GET /api/v1/admin/orders/{order}/items/{orderItem}/attachments/{attachment}/download`

Integer API and database enums:

- `status`: `0=pending`, `1=confirmed`, `2=in_progress`, `3=completed`,
  `4=cancelled`
- `paymentStatus`: `0=unpaid`, `1=partially_paid`, `2=paid`
- `discountType`: `0=fixed`, `1=percentage`
- `orderPlace`: `0=website`, `1=whatsapp`

The OpenAPI contract in [contracts/openapi.yaml](./contracts/openapi.yaml) is
the implementation source for routes, payloads, responses, filters, security,
permissions, and error codes.

## 4. Constitution and Governance Gate

### 4.1 Pre-design result

PASS.

The active specification explicitly records the approved Feature 005 decisions
that govern this feature:

- external order enums use integer values
- order numbers use `ORD-YYYYMMDD-####`
- order attachments use protected storage and protected download endpoints
- order address payloads use `province`, `city`, and `address`

These are treated as approved feature decisions for Feature 005. Shared
documentation carrying older wording must be synchronized during the broader
documentation stream, but the active spec contains no unresolved blocker for
planning.

The user explicitly approved the governing exception on August 2, 2026, and
the higher-level governance documents were synchronized before implementation
continued.

### 4.2 Post-design result

PASS.

The design preserves:

- backend-only Laravel scope
- thin controllers and dedicated Form Requests
- exact Sanctum plus Spatie authorization boundaries
- explicit transaction and lock ownership for race-sensitive workflows
- immutable order snapshots
- allow-listed filters and sorts
- Arabic/English localization behavior
- required Pest, Pint, and Larastan/PHPStan quality gates

No new infrastructure dependency is introduced. One narrow, explicitly
user-approved governance exception is recorded for Feature 005 order-status and
hard-delete behavior.

## 5. Source Structure

```text
app/
├── Actions/
│   └── Orders/
│       ├── CreatePublicOrderAction.php
│       ├── CreateAdminOrderAction.php
│       ├── UpdateOrderAction.php
│       ├── ChangeOrderStatusAction.php
│       ├── UpdateOrderPaymentAction.php
│       ├── DeleteOrderAction.php
│       ├── AddOrderItemAction.php
│       ├── UpdateOrderItemAction.php
│       ├── DeleteOrderItemAction.php
│       ├── UploadOrderItemAttachmentsAction.php
│       └── DeleteOrderItemAttachmentAction.php
├── Enums/Orders/
│   ├── OrderStatus.php
│   ├── PaymentStatus.php
│   ├── DiscountType.php
│   └── OrderPlace.php
├── Http/
│   ├── Controllers/Api/V1/Admin/Orders/
│   ├── Controllers/Api/V1/Public/Orders/
│   ├── Requests/Api/V1/Admin/Orders/
│   ├── Requests/Api/V1/Public/Orders/
│   └── Resources/Api/V1/
│       ├── Admin/Orders/
│       └── Public/Orders/
├── Models/
│   ├── Order.php
│   ├── OrderItem.php
│   ├── OrderItemSelectedOption.php
│   ├── OrderItemSelectedOptionValue.php
│   ├── OrderItemAnswer.php
│   ├── OrderItemAttachment.php
│   ├── OrderIdempotencyKey.php
│   └── OrderNumberSequence.php
├── Policies/
├── Queries/Orders/
│   ├── AdminOrderIndexQuery.php
│   └── OrderItemIndexQuery.php
└── Services/
    └── Orders/
        ├── OrderNumberAllocator.php
        ├── OrderPricingService.php
        ├── OrderPaymentSummaryService.php
        ├── OrderStatusTransitionService.php
        ├── OrderSnapshotFactory.php
        ├── OrderAttachmentStore.php
        ├── PublicOrderIdempotencyService.php
        └── CustomerOrderResolver.php

database/
├── factories/
├── migrations/
└── seeders/

routes/api/v1/
├── admin.php
└── public.php

tests/
├── Concurrency/Orders/
├── Feature/Api/V1/Admin/Orders/
├── Feature/Api/V1/Public/Orders/
└── Feature/Database/Orders/
```

Simple single-row retrieval or serialization must not receive an Action unless
transaction, locking, compensation, or multi-model orchestration justifies it.

## 6. Database Design

The canonical relational model is detailed in
[data-model.md](./data-model.md).

### 6.1 Tables

```text
orders
order_number_sequences
order_idempotency_keys
order_items
order_item_selected_options
order_item_selected_option_values
order_item_answers
order_item_attachments
```

### 6.2 Foreign-key behavior

- `orders.customer_id` and `orders.customer_address_id` remain nullable to
  preserve historical snapshots if linked records are later removed or become
  unavailable.
- `orders.cancelled_by_admin_id` and `orders.created_by_admin_id` reference
  `users`.
- `order_items.service_id`, `order_item_selected_options.pricing_option_id`,
  `order_item_selected_option_values.pricing_option_value_id`, and
  `order_item_answers.service_order_field_id` use nullable historical links so
  snapshot columns remain authoritative after current catalogue deletion.
- Child snapshot tables hard delete with their parent order item.

### 6.3 Initial query indexes

The migration adds only indexes required by approved list and write paths:

- unique `orders.order_number`
- admin index support for `status`, `payment_status`, `order_place`,
  `customer_id`, `created_at`, and `total`
- admin search support for `customer_phone`, `customer_email`, and
  `customer_name`
- unique `order_number_sequences.business_date`
- unique `order_idempotency_keys.idempotency_key`
- unique `order_idempotency_keys.order_id`
- parent-scoped indexes for `order_items.order_id`,
  `order_item_selected_options.order_item_id`,
  `order_item_selected_option_values.order_item_selected_option_id`,
  `order_item_answers.order_item_id`, and `order_item_attachments.order_item_id`

Implementation must verify representative admin index queries with `EXPLAIN`
before introducing any extra indexes.

## 7. Core Design Decisions

### 7.1 Order numbering

Use a dedicated `order_number_sequences` table with one row per UTC business
date. Allocation occurs inside the create-order transaction:

1. Lock or create the current date sequence row.
2. Increment `last_sequence`.
3. Format `ORD-YYYYMMDD-####`.
4. Persist the order using the allocated number in the same transaction.

No `MAX(id)+1`, random suffixes, or post-commit number generation is allowed.

The four-digit daily sequence supports `0001` through `9999`. Attempting to
allocate the next value after `9999` returns `409
ORDER_NUMBER_SEQUENCE_EXHAUSTED` and creates no order.

### 7.2 Public idempotency

`order_idempotency_keys` stores:

```text
idempotency_key
request_fingerprint
order_id nullable
reserved_at
completed_at nullable
created_at
updated_at
```

The reservation and order are completed inside one MySQL transaction:

1. Canonicalize and hash the request before mutating domain records.
2. Attempt an atomic unique-key reservation for `idempotency_key`.
3. Lock the reservation row with `SELECT ... FOR UPDATE`.
4. When the row already contains `order_id`:
   - the same fingerprint returns the existing order with HTTP `200`;
   - a different fingerprint returns `409 IDEMPOTENCY_KEY_REUSED`.
5. When this transaction created the reservation, continue creating the
   customer/address state, allocate the UTC order number, and create the order.
6. Set `order_id` and `completed_at` before commit.
7. Any failure rolls back both the incomplete reservation and the order.

No incomplete reservation may be committed. `order_id` is nullable only during
the active transaction that owns a newly inserted reservation.

The service canonicalization:

- normalizes phone values and trims approved plain-text scalar inputs
- serializes associative keys in deterministic lexical order
- preserves business-significant array order for order items
- sorts set-like identifier arrays such as `valueIds` before hashing
- represents decimal inputs using normalized two-decimal strings
- includes each uploaded file's SHA-256 content digest, byte size, MIME type,
  and item position, while excluding temporary path and original filename
- hashes the canonical representation with SHA-256

The implementation must use an atomic MySQL insert strategy such as
`INSERT IGNORE` followed by a locked read, rather than a read-then-insert race.

### 7.3 Snapshot-first order model

Order creation and later admin edits always rebuild the relevant order-level or
item-level snapshot fields from current authoritative domain data. Reads return
localized projections from stored snapshots rather than joining current live
catalogue content as the source of truth.

### 7.4 Item answer replacement

`selectedOptions` replacement validates against the service's current eligible
pricing configuration.

Answer identifiers are intentionally different between create and update:

```text
Create Order / Add Order Item:
answers[].orderFieldId

Update existing Order Item:
answers[].orderItemAnswerId
```

Creation uses the current Service Order Field ID because the snapshot does not
exist yet. Item Update uses `order_item_answers.id`, which remains stable even
when the historical `service_order_field_id` link later becomes `null`.

`answers` on Item Update is a full replacement of existing answer snapshots:

- every submitted `orderItemAnswerId` must belong to the path Order Item
- foreign answer snapshot IDs return the nested non-disclosure `404`
- omitting a required stored answer snapshot fails validation
- optional stored answer snapshots may be omitted and are deleted
- an optional question that never produced a snapshot cannot be added later
- the question, field type, and required flag remain immutable snapshots
- only the answer text is replaced
- answer replacement never recalculates price

Supporting answers for previously unanswered optional questions later requires
a separate contract change that stores the full displayed question set.

### 7.5 Pricing and payment services

`OrderPricingService` owns:

- selected-option validation
- unit price calculation
- item total calculation
- subtotal calculation
- discount amount calculation
- total calculation

`OrderPaymentSummaryService` owns:

- payment-status derivation
- remaining amount calculation
- recalculation after order edits, item edits, discount edits, or payment edits

### 7.6 Attachment handling

Attachment writes are tracked synchronously. On any failed transactional
workflow, newly written files are deleted immediately. Guest responses never
expose raw storage paths or attachment URLs. Admin resources expose only safe
metadata plus the protected nested download endpoint.

Operational request limits:

```text
Maximum per item: 3 files
Maximum per file: 10 MB
Maximum attachment files in one Public/Admin create request: 30
Maximum combined attachment bytes in one Public/Admin create request: 100 MB
Maximum standalone Admin attachment-upload request: 3 files / 30 MB
```

The application validates these limits before persistence, while deployment
configuration (`post_max_size`, `upload_max_filesize`, web-server limits) must
be set at or above the documented contract.

For deletion workflows, database mutation is authoritative. After a successful
commit, physical-file deletion is attempted synchronously. A cleanup failure is
logged with safe identifiers, never raw public paths, and does not restore the
deleted database record. The implementation must expose the cleanup failure to
operations through structured logging without returning an internal path to the
client.

## 8. Transaction and Locking Design

### 8.1 Lock order

Every race-sensitive workflow uses the following canonical order:

```text
order_idempotency_keys row first when public create
→ customer when creation or mutation is required
→ customer address when creation or mutation is required
→ order_number_sequences row
→ order row
→ order_items by id ASC
→ order_item_selected_options by id ASC
→ order_item_selected_option_values by id ASC
→ order_item_answers by id ASC
→ order_item_attachments by id ASC
→ revalidate
→ mutate
→ complete idempotency reservation when applicable
→ commit
```

### 8.2 Atomic workflows

Actions requiring explicit database transactions:

- public order create
- admin order create
- order update when customer/address/discount/order place/status changes
- order status change
- order payment update
- add order item
- update order item
- delete order item
- upload order-item attachments
- delete order-item attachment
- eligible order hard delete

### 8.3 Revalidation rule

Any workflow that changes totals, snapshots, status, or nested ownership must
revalidate the locked current state before mutation. If the locked state is no
longer eligible, the action aborts with the approved business error.

## 9. API Design

### 9.1 Admin index behavior

`AdminOrderIndexQuery` wraps `spatie/laravel-query-builder` and allow-lists:

- `filter[search]`
- `filter[status]`
- `filter[paymentStatus]`
- `filter[orderPlace]`
- `filter[customerId]`
- `filter[serviceId]`
- `filter[createdFrom]`
- `filter[createdTo]`
- `filter[totalFrom]`
- `filter[totalTo]`
- `sort=createdAt`
- `sort=-createdAt`
- `sort=total`
- `sort=-total`

Default ordering remains:

```text
created_at DESC, id DESC
```

### 9.2 Request media types

- Public create: multipart form-data with bracket notation and file uploads
- Admin create: multipart form-data because initial nested item attachments are
  allowed
- Admin update, status, payment, and item update: JSON
- Admin attachment upload: multipart form-data

### 9.3 Response projection

- Public create returns one order summary resource only
- Admin index returns summarized rows only
- Admin show returns the full localized nested detail resource
- Protected download returns a file stream rather than JSON

## 10. Permission Mapping

| Area | Permission |
|---|---|
| Admin order list/show | `orders.view` |
| Admin create order | `orders.create` + `order-items.create`; add `order-item-attachments.create` when initial attachments are present |
| Admin update order | `orders.update` |
| Admin hard delete order | `orders.delete` |
| Admin status change | `orders.change-status` |
| Admin payment read/update | `orders.manage-payment` |
| Admin item list/show | `order-items.view` |
| Admin item create | `order-items.create` |
| Admin item update | `order-items.update` |
| Admin item delete | `order-items.delete` |
| Admin attachment upload | `order-item-attachments.create` |
| Admin attachment delete | `order-item-attachments.delete` |
| Admin attachment download | `orders.view` plus strict nested ownership |

Admin Create is one atomic operation:

```text
Without initial attachments:
orders.create + order-items.create

With any initial attachment:
orders.create + order-items.create + order-item-attachments.create
```

Missing any required permission rejects the complete create request before
customer, address, order, item, or file persistence.

## 11. Testing Strategy

The canonical verification scenarios are detailed in
[quickstart.md](./quickstart.md). Required automated coverage includes:

- public order creation success, validation, rate limit, idempotency replay,
  idempotency conflict, and localization
- admin order create, show, update, status, payment, and hard-delete rules
- admin nested item create, update, delete, and at-least-one-item guard
- attachment upload, delete, download, limit, and nested-ownership behavior
- snapshot persistence after linked domain edits
- pricing, discount, payment-status, and remaining-amount recalculation
- forbidden, unauthenticated, and non-disclosed nested resource outcomes
- real MySQL race tests for order-number allocation and idempotency reservation
- OpenAPI YAML parsing, unique operation IDs, complete internal `$ref`
  resolution, documented integer enum meanings, and response-schema coverage
- Postman key descriptions, permission notes, status-transition examples,
  payment examples, discount examples, and error examples
- answer-update tests using `orderItemAnswerId`, including a deleted current
  Service Order Field link, foreign snapshot ownership, required-snapshot
  omission, and attempted introduction of an unsnapshotted optional question
- idempotency reservation tests proving nullable `order_id` exists only inside
  the owning transaction, failed creates leave no reservation, and concurrent
  duplicate requests converge on one completed reservation and one order

## 12. Generated Planning Artifacts

- [research.md](./research.md)
- [data-model.md](./data-model.md)
- [quickstart.md](./quickstart.md)
- [contracts/openapi.yaml](./contracts/openapi.yaml)

## 13. Complexity Tracking

One explicitly user-approved governance exception is active for Feature 005:

- Feature 005 order flows use only `pending`, `confirmed`, `in_progress`,
  `completed`, and `cancelled`
- Feature 005 allows `completed -> cancelled`
- Feature 005 allows conditional order hard delete
- Feature 005 allows nested order-item hard delete

## 14. Feature 007 synchronized amendment

Feature 007 adds nullable `orders.completed_at`, maintained inside the existing locked status-transition transaction. `AdminOrderIndexResource` and `AdminOrderResource` serialize it as read-only `completedAt`; all mutation Form Requests prohibit the key. The authoritative Feature 005 OpenAPI and Postman contracts are updated in the same change.
