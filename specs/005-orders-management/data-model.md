# Data Model: Orders Management

**Feature:** `005-orders-management`  
**Status:** Design Complete

## 1. Entity Overview

```text
Order
├── many OrderItems
├── optional linked Customer
├── optional linked CustomerAddress
├── optional cancelling Admin User
└── optional creating Admin User

OrderItem
├── belongs to Order
├── optional linked Service
├── many OrderItemSelectedOptions
├── many OrderItemAnswers
└── many OrderItemAttachments

OrderItemSelectedOption
└── many OrderItemSelectedOptionValues

OrderIdempotencyKey
└── belongs to one Order

OrderNumberSequence
└── one row per UTC business date
```

## 2. Orders

### Purpose

Represents the top-level guest or admin-created order, its workflow state,
financial summary, customer snapshot, address snapshot, and cancellation
metadata.

### Fields

| Field | Type | Null | Notes |
|---|---|---:|---|
| `id` | unsigned big integer | No | Primary key |
| `order_number` | varchar(17) | No | Unique formatted number `ORD-YYYYMMDD-####` |
| `customer_id` | unsigned big integer | Yes | Nullable current link |
| `customer_name` | varchar(150) | No | Snapshot |
| `customer_phone` | varchar(11) | No | Snapshot matching `^01[0-9]{9}$` |
| `customer_email` | varchar(255) | Yes | Snapshot; non-unique |
| `customer_address_id` | unsigned big integer | Yes | Nullable current link |
| `address_province` | varchar(150) | Yes | Snapshot; all-or-none with city/text |
| `address_city` | varchar(150) | Yes | Snapshot; all-or-none with province/text |
| `address_text` | varchar(1000) | Yes | Snapshot; all-or-none with province/city |
| `status` | tinyint unsigned | No | `OrderStatus` enum |
| `order_place` | tinyint unsigned | No | `OrderPlace` enum |
| `customer_note` | text | Yes | Plain text, max 2000 chars |
| `admin_note` | text | Yes | Plain text, max 2000 chars |
| `subtotal` | decimal(12,2) | No | Backend-calculated |
| `discount_type` | tinyint unsigned | Yes | `DiscountType` enum |
| `discount_value` | decimal(12,2) | No | Defaults `0.00` |
| `discount_amount` | decimal(12,2) | No | Defaults `0.00` |
| `discount_reason` | text | Yes | Required when discount exists |
| `total` | decimal(12,2) | No | Backend-calculated |
| `payment_status` | tinyint unsigned | No | `PaymentStatus` enum |
| `paid_amount` | decimal(12,2) | No | Defaults `0.00` |
| `cancellation_reason` | text | Yes | Required for cancellation |
| `cancelled_at` | timestamp | Yes | Cancellation timestamp |
| `cancelled_by_admin_id` | unsigned big integer | Yes | Admin user link |
| `created_by_admin_id` | unsigned big integer | Yes | Null for public orders |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

### Invariants

- `order_number` is unique and never reused.
- An order must always have at least one order item.
- `discount_reason` is non-null when discount type is non-null.
- `status = cancelled` requires `cancellation_reason`, `cancelled_at`, and
  `cancelled_by_admin_id`.
- `payment_status` is derived from `paid_amount` relative to `total`.
- `remainingAmount` is computed, not stored.
- Address snapshot columns are all null or all non-null.
- `status IN (0,1,2,3,4)`.
- `payment_status IN (0,1,2)`.
- `discount_type IS NULL OR discount_type IN (0,1)`.
- `order_place IN (0,1)`.
- All monetary columns except computed `remainingAmount` are non-negative.

### Relationships

- belongs to `Customer` optionally
- belongs to `CustomerAddress` optionally
- belongs to creating `User` optionally
- belongs to cancelling `User` optionally
- has many `OrderItem`
- has one `OrderIdempotencyKey` optionally

## 3. Order Number Sequence

### Purpose

Stores the last allocated daily order sequence for the UTC business date.

### Fields

| Field | Type | Null | Notes |
|---|---|---:|---|
| `id` | unsigned big integer | No | Primary key |
| `business_date` | date | No | Unique UTC business date |
| `last_sequence` | unsigned integer | No | Last allocated integer |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

### Invariants

- One row per UTC date.
- Allocation occurs only inside the order-create transaction.
- `last_sequence` is constrained to `0..9999`.
- Allocation after `9999` returns `ORDER_NUMBER_SEQUENCE_EXHAUSTED`.

## 4. Order Idempotency Key

### Purpose

Stores the permanent public idempotency reservation and replay contract.

### Fields

| Field | Type | Null | Notes |
|---|---|---:|---|
| `id` | unsigned big integer | No | Primary key |
| `idempotency_key` | char(36) | No | Unique UUID client key |
| `request_fingerprint` | char(64) | No | Lowercase SHA-256 hexadecimal digest |
| `order_id` | unsigned big integer | Yes | Unique linked order; null only during the owning create transaction |
| `reserved_at` | timestamp | No | UTC reservation timestamp |
| `completed_at` | timestamp | Yes | UTC completion timestamp set with `order_id` |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

### Invariants

- `idempotency_key` is unique.
- Non-null `order_id` is unique.
- A new reservation starts with `order_id = null` and
  `completed_at = null` inside the owning transaction.
- The same transaction must set both `order_id` and `completed_at` before
  commit.
- No row with only one of `order_id` or `completed_at` may commit.
- A failed order creation rolls back the reservation row.
- Same key + same fingerprint + completed order replays the same order.
- Same key + different fingerprint is a conflict.

## 5. Order Items

### Purpose

Represents one independently configurable occurrence of a service inside an
order.

### Fields

| Field | Type | Null | Notes |
|---|---|---:|---|
| `id` | unsigned big integer | No | Primary key |
| `order_id` | unsigned big integer | No | Parent order |
| `service_id` | unsigned big integer | Yes | Nullable current link |
| `service_name_ar` | varchar(255) | No | Snapshot |
| `service_name_en` | varchar(255) | No | Snapshot |
| `service_slug_ar` | varchar(255) | No | Snapshot |
| `service_slug_en` | varchar(255) | No | Snapshot |
| `price_type` | tinyint unsigned | No | Current approved service price-type mapping |
| `base_price` | decimal(12,2) | No | Snapshot |
| `unit_price` | decimal(12,2) | No | Calculated snapshot |
| `quantity` | unsigned integer | No | Minimum 1 |
| `item_total` | decimal(12,2) | No | Calculated snapshot |
| `item_note` | text | Yes | Plain text, max 2000 chars |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

### Invariants

- `quantity >= 1`
- service cannot be replaced by item update
- `item_total = unit_price * quantity`

## 6. Order Item Selected Options

### Purpose

Stores one selected pricing option snapshot for an order item.

### Fields

| Field | Type | Null | Notes |
|---|---|---:|---|
| `id` | unsigned big integer | No | Primary key |
| `order_item_id` | unsigned big integer | No | Parent item |
| `pricing_option_id` | unsigned big integer | Yes | Nullable current link |
| `option_name_ar` | string | No | Snapshot |
| `option_name_en` | string | No | Snapshot |
| `input_type` | tinyint unsigned | No | Snapshot of current input type |
| `is_required` | boolean | No | Snapshot |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

### Invariants

- Stored only for selected options.
- Replaced entirely when `selectedOptions` is supplied on item update.
- Unique current link per item:
  `UNIQUE(order_item_id, pricing_option_id)` when `pricing_option_id` is not
  null.

## 7. Order Item Selected Option Values

### Purpose

Stores one selected value snapshot under a selected option snapshot.

### Fields

| Field | Type | Null | Notes |
|---|---|---:|---|
| `id` | unsigned big integer | No | Primary key |
| `order_item_selected_option_id` | unsigned big integer | No | Parent selected option |
| `pricing_option_value_id` | unsigned big integer | Yes | Nullable current link |
| `value_label_ar` | string | No | Snapshot |
| `value_label_en` | string | No | Snapshot |
| `price_adjustment` | decimal(12,2) | No | Snapshot |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

### Invariants

- Stored only for selected values.
- Price adjustments are non-negative.
- Unique current link per selected option:
  `UNIQUE(order_item_selected_option_id, pricing_option_value_id)` when the
  value link is not null.

## 8. Order Item Answers

### Purpose

Stores answered order-field snapshots for one order item.

### Fields

| Field | Type | Null | Notes |
|---|---|---:|---|
| `id` | unsigned big integer | No | Primary key |
| `order_item_id` | unsigned big integer | No | Parent item |
| `service_order_field_id` | unsigned big integer | Yes | Nullable current link |
| `question_ar` | string | No | Snapshot |
| `question_en` | string | No | Snapshot |
| `field_type` | tinyint unsigned | No | Snapshot |
| `is_required` | boolean | No | Snapshot |
| `answer` | text | No | Plain text, max 2000 chars |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

### Invariants

- Stored only when an answer exists.
- Required answers reject null, empty, and whitespace-only values.
- Replaced entirely when `answers` is supplied on item update.
- Creation identifies the current question with `orderFieldId`.
- Item Update identifies the historical snapshot with
  `orderItemAnswerId = order_item_answers.id`.
- Answer replacement remains possible when `service_order_field_id` is null.
- Every submitted answer snapshot ID must belong to the path Order Item.
- Required stored answer snapshots cannot be omitted.
- Optional questions without an existing snapshot cannot be added later.
- Unique current link per item:
  `UNIQUE(order_item_id, service_order_field_id)` when the field link is not
  null.

## 9. Order Item Attachments

### Purpose

Stores safe metadata and internal storage references for item attachments.

### Fields

| Field | Type | Null | Notes |
|---|---|---:|---|
| `id` | unsigned big integer | No | Primary key |
| `order_item_id` | unsigned big integer | No | Parent item |
| `disk` | varchar(64) | No | Configured protected disk name |
| `path` | varchar(1024) | No | Internal storage path |
| `stored_name` | varchar(255) | No | Generated filename |
| `original_name` | varchar(255) | No | Metadata only |
| `mime_type` | varchar(127) | No | Validated MIME |
| `extension` | varchar(10) | No | Validated lowercase extension |
| `size_bytes` | unsigned bigint | No | Validated size |
| `created_at` | timestamp | No | |
| `updated_at` | timestamp | No | |

### Invariants

- Maximum 3 attachments per item.
- Maximum 10 MB per file.
- Public/Admin Create: maximum 30 files and 100 MB combined.
- Standalone Admin upload: maximum 3 files and 30 MB combined.
- Only approved types are allowed.
- Internal path never appears in guest or Admin JSON responses.

## 10. State Transitions

### Order status

```text
PENDING    -> CONFIRMED, CANCELLED
CONFIRMED  -> IN_PROGRESS, CANCELLED
IN_PROGRESS-> COMPLETED, CANCELLED
COMPLETED  -> CANCELLED
CANCELLED  -> no transitions
```

### Editability

```text
Editable statuses: PENDING, CONFIRMED, IN_PROGRESS
Locked statuses:   COMPLETED, CANCELLED
Payment editable:  all statuses
Attachment download: all statuses
```

## 11. Deletion Rules

- Orders are hard deleted only when admin-created, pending, unpaid, and zero
  paid amount.
- Order-item deletion is always hard delete together with nested snapshots and
  attachments.
- Public-created orders can never be hard deleted.
- Allocated order numbers are never reused after deletion.

## 12. Derived Fields

Computed at read or service level:

```text
remainingAmount = total - paidAmount
availableStatusTransitions = next approved statuses for current order state
```


## 13. Database Constraints and Indexes

### Unique constraints

```text
orders.order_number
order_number_sequences.business_date
order_idempotency_keys.idempotency_key
order_idempotency_keys.order_id
```

MySQL allows multiple nulls in the unique `order_id` index. Application
invariants prevent incomplete reservations from committing; every committed
reservation has a non-null unique `order_id` and matching `completed_at`.

Conditional/current-link uniqueness is enforced through validation plus the
strongest MySQL-compatible unique strategy supported by the project:

```text
order_item_selected_options(order_item_id, pricing_option_id)
order_item_selected_option_values(order_item_selected_option_id, pricing_option_value_id)
order_item_answers(order_item_id, service_order_field_id)
```

Nullable historical links remain allowed after current-domain deletion.

### Check or application invariants

Where the deployed MySQL version reliably enforces `CHECK`, migrations add the
following checks. The same rules are always enforced in application validation
and tests:

```text
orders.status IN (0,1,2,3,4)
orders.payment_status IN (0,1,2)
orders.discount_type IS NULL OR IN (0,1)
orders.order_place IN (0,1)
order_items.quantity >= 1
order_item_selected_option_values.price_adjustment >= 0
order_number_sequences.last_sequence BETWEEN 0 AND 9999
address_province/address_city/address_text are all NULL or all NOT NULL
order_idempotency_keys.order_id and completed_at are both NULL or both NOT NULL
```

### Query indexes

```text
orders(status, created_at, id)
orders(payment_status, created_at, id)
orders(order_place, created_at, id)
orders(customer_id, created_at, id)
orders(total, id)
orders(customer_phone)
orders(customer_email)
order_items(order_id, id)
order_items(service_id, order_id)
order_item_attachments(order_item_id, id)
```

Additional indexes require representative `EXPLAIN` evidence.
