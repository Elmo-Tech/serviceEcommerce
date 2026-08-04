# Quickstart: Orders Management Validation

**Feature:** `005-orders-management`  
**Purpose:** Validate the feature end-to-end after implementation

## 1. Prerequisites

- Application configured with MySQL and storage settings
- Migrations run successfully
- Seeders run successfully, including permissions and super-admin setup
- Admin authentication feature working
- Service catalog data exists for at least:
  - one active available service
  - one active unavailable service
  - one inactive or deleted service
  - service pricing options and service order fields where needed

## 2. Prepare environment

Run from repository root:

```bash
php artisan migrate:fresh --seed
php artisan test --filter=Orders
```

If targeted tests are not yet split by namespace, run the relevant Pest files
covering:

- Public order creation
- Admin order management
- Admin order-item management
- Admin attachment management
- Orders concurrency

### Recorded verification commands run on August 2, 2026

The following commands were executed successfully against the local MySQL-backed
test environment during Feature 005 completion work:

```bash
php artisan test tests/Feature/Api/V1/Admin/Orders/AdminOrderIndexQueryTest.php tests/Feature/Api/V1/Admin/Orders/AdminOrderLocalizationTest.php tests/Feature/Api/V1/Admin/Orders/AdminOrderOwnershipTest.php tests/Feature/Api/V1/Admin/Orders/AdminOrderQueryPerformanceTest.php
vendor/bin/pint --test
php -d memory_limit=1G vendor/bin/phpstan analyse app tests --no-progress
php artisan test tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php tests/Feature/Api/V1/Public/Orders/PublicOrderIdempotencyTest.php tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php tests/Feature/Api/V1/Admin/Orders/AdminOrderIndexQueryTest.php tests/Feature/Api/V1/Admin/Orders/AdminOrderLocalizationTest.php tests/Feature/Api/V1/Admin/Orders/AdminOrderOwnershipTest.php tests/Feature/Api/V1/Admin/Orders/AdminOrderQueryPerformanceTest.php tests/Concurrency/Orders/OrderNumberAllocationConcurrencyTest.php tests/Concurrency/Orders/PublicOrderIdempotencyConcurrencyTest.php tests/Concurrency/Orders/OrderFinalItemDeletionConcurrencyTest.php tests/Concurrency/Orders/OrderFinancialRecalculationConcurrencyTest.php
```

Observed outcomes:

- `AdminOrderIndexQueryTest.php` passed
- `AdminOrderLocalizationTest.php` passed
- `AdminOrderOwnershipTest.php` passed
- `AdminOrderQueryPerformanceTest.php` passed
- combined Orders verification run passed with `9 tests` and `86 assertions`
- `vendor/bin/pint --test` passed after applying formatting fixes
- `phpstan analyse app tests --no-progress` passed with `0` errors
- combined end-to-end Orders validation run passed with `29 tests` and
  `262 assertions`

## 3. Validate public guest order creation

### Scenario A — Successful create

Send a multipart request to:

```text
POST /api/v1/public/orders
```

Validate:

- response uses the shared success envelope
- order number is returned
- status defaults to pending
- payment status defaults to unpaid
- totals are backend-calculated
- customer snapshot is stored
- optional address snapshot is stored when provided
- item snapshots, selected option snapshots, answer snapshots, and attachments
  are stored

### Scenario B — Idempotency replay

Repeat the exact same request with the same `Idempotency-Key`.

Validate:

- no duplicate order is created
- HTTP status is `200`, while the first successful create is `201`
- the returned order summary matches the first successful result

### Scenario C — Idempotency conflict

Reuse the same `Idempotency-Key` with a changed payload.

Validate:

- response is `409`
- stable error code is `IDEMPOTENCY_KEY_REUSED`

### Scenario D — Validation and eligibility failures

Validate:

- inactive or deleted service returns `SERVICE_NOT_FOUND`
- active unavailable service returns `SERVICE_UNAVAILABLE`
- service with `isAttachmentRequired = true` rejects Public/Admin item
  creation without files using `REQUIRED_SERVICE_ATTACHMENT_MISSING`, while
  the same item succeeds with one valid attachment
- invalid selected option or value returns the approved pricing error
- missing required answer returns the approved order-field error
- exceeding per-item count, 10 MB per-file size, 30-file create total, or
  100 MB combined create size returns the approved attachment error

## 4. Validate admin order management

### Scenario E — Admin create

Authenticate as super admin and create:

- one order for an existing customer
- one order for a new nested customer

Validate:

- Admin Create requires exactly one of `customerId` or `customer`
- `customerAddressId` and `address` cannot be sent together
- Admin Create always uses multipart form-data, including without files
- `orders.create` and `order-items.create` are both required
- initial attachments additionally require `order-item-attachments.create`
- missing a nested permission creates no customer, address, order, item, or file
- default financial state is pending/unpaid/zero paid amount

### Scenario F — Admin update

Update:

- customer note
- admin note
- discount
- order place
- customer/address snapshot data through approved payloads

Validate:

- a note-only PATCH may omit both `customerId` and `customer`
- changing customer accepts exactly one customer source and rejects both
- totals recalculate when discount changes
- unrelated fields remain unchanged

### Scenario G — Status transitions

Validate:

- allowed transitions succeed
- disallowed jumps fail with `INVALID_ORDER_STATUS_TRANSITION`
- cancellation requires a reason
- `availableStatusTransitions` matches the current state

### Scenario H — Payment summary

Update `paidAmount` across:

- zero
- partial payment
- exact payment
- overpayment

Validate:

- `paymentStatus` changes correctly
- `remainingAmount` recalculates correctly, including negative values on overpay

### Scenario I — Conditional hard delete

Validate:

- admin-created pending unpaid order with zero paid amount deletes successfully
- public-created order cannot be hard deleted
- paid or non-pending orders cannot be hard deleted

## 5. Validate nested item and attachment management

### Scenario J — Add and update items

Validate:

- item create recalculates totals
- item create uses multipart bracket notation and may include up to three
  `attachments[]` files in the same atomic request
- item create with attachments additionally requires
  `order-item-attachments.create`
- quantity-only update changes quantity and item total
- `selectedOptions` acts as full replacement
- Create Order/Add Item answers use `orderFieldId`
- Item Update answers use `orderItemAnswerId`
- `answers` acts as full replacement against stored answer snapshots
- updating by `orderItemAnswerId` still succeeds after the current Service
  Order Field is deleted and `service_order_field_id` becomes null
- an answer snapshot belonging to another Order Item returns non-disclosing
  `404`
- omitting a required stored answer snapshot fails validation
- an unanswered optional question with no snapshot cannot be introduced later
- `selectedOptions` replacement validates current Service pricing configuration
- answer replacement changes text only and does not recalculate price
- service identity cannot be replaced on existing item update

### Scenario K — Delete item

Validate:

- deleting a non-final item succeeds
- deleting the last item returns `ORDER_REQUIRES_AT_LEAST_ONE_ITEM`
- nested selected options, answers, and attachments are removed with the item

### Scenario L — Attachment APIs

Validate:

- upload allowed only while the order is editable
- delete allowed only while the order is editable
- protected download remains available in any order status for authorized admin
- nested ownership is enforced for upload, delete, and download

## 6. Validate localization

Call public and admin order responses with:

```text
Accept-Language: ar
Accept-Language: en
```

Validate:

- user-facing message localization changes
- localized snapshot labels change
- machine keys and error codes remain stable English values
- `Content-Language` and `Vary: Accept-Language` headers are correct

## 7. Validate concurrency and integrity

Run targeted real MySQL tests proving:

- daily order number allocation does not duplicate under concurrent create
- idempotency reservation does not allow duplicate committed public orders
- a failed Public Create leaves neither an order nor an idempotency reservation
- a completed idempotency row always contains both `order_id` and
  `completed_at`
- concurrent identical requests converge on one completed reservation and one
  order; the replay receives HTTP `200`
- concurrent conflicting payloads for one key produce one order and one
  `IDEMPOTENCY_KEY_REUSED` response
- item or order recalculation remains consistent under concurrent admin edits
- sequence `9999` succeeds and the next allocation returns
  `ORDER_NUMBER_SEQUENCE_EXHAUSTED`
- concurrent deletion attempts cannot remove the final item
- payment and item recalculation locks leave one consistent committed summary

## 8. Completion checklist

- All approved order routes exist and use correct middleware
- All required permissions are seeded and enforced
- Shared success/error envelope is preserved
- No guest response exposes raw storage paths or attachment URLs
- Snapshot persistence remains correct after related live records change
- Pint passes
- Larastan/PHPStan passes
- Relevant Pest suites pass
- OpenAPI YAML parses successfully
- Every operation has one unique `operationId`
- Every internal `$ref` resolves
- Every operation documents applicable `401`, `403`, `404`, `409`, `422`,
  `429`, and `500` responses
- Postman documents every key, integer enum meaning, permission, status
  transition, payment case, discount case, and important error


## 9. Validate HTTP and permission outcomes

For representative Admin endpoints, validate separately:

```text
401 UNAUTHENTICATED
403 FORBIDDEN
404 non-disclosing missing/foreign nested resource
409 business conflict
422 validation failure
500 internal error envelope
```

For Public Create also validate:

```text
429 RATE_LIMITED
200 idempotent replay
201 first successful creation
```

## 10. Validate idempotency fingerprint semantics

Repeat a Public Create request and verify:

- changing only multipart boundary does not change the fingerprint
- changing only original filename with identical content does not change the
  fingerprint
- changing file bytes changes the fingerprint
- reordering `valueIds` does not change the fingerprint
- reordering Order Items changes the fingerprint because item position is
  business-significant
- normalized equivalent phone formats produce the same customer/fingerprint
  representation


## 11. Validate answer identifier contracts

Creation and Add Item use the current Service Order Field ID:

```json
{
  "answers": [
    {
      "orderFieldId": 15,
      "answer": "200 × 100 cm"
    }
  ]
}
```

Existing Item Update uses the stored answer snapshot ID:

```json
{
  "answers": [
    {
      "orderItemAnswerId": 801,
      "answer": "250 × 120 cm"
    }
  ]
}
```

Validate that `801` is the `id` returned in the existing Item Detail
`answers[]` array, not the current Service Order Field ID.
