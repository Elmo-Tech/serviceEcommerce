# Feature 005 — Orders Management

**Canonical feature name:** `005-orders-management`  
**Canonical reference path:** `docs/features/005-orders-management.md`  
**Status:** Approved feature reference for specification and implementation planning  
**Backend:** Laravel 13 API  
**Admin client:** External React application  
**Public client:** External Next.js application  
**Supported languages:** Arabic and English  
**Default business timezone:** `UTC`

---

## 1. Purpose

Feature 005 implements order creation and management for the service-commerce platform.

An order may contain multiple order items.

Each order item represents one service with its own:

- Quantity.
- Service snapshot.
- Selected pricing-option snapshots.
- Answer snapshots.
- Item note.
- Attachments.
- Calculated unit price and item total.

The backend is the only pricing authority.

The feature supports:

- Public guest order creation.
- Admin order creation and management.
- Existing-customer selection or customer creation.
- Optional address snapshots.
- Multiple services in one order.
- Discounts.
- Manual payment-summary management.
- Order status transitions and cancellation.
- Item-level attachments through protected downloads.
- Idempotent public order creation.
- Historical snapshots that remain valid when catalogue data changes.

---

## 2. Scope

### 2.1 Included

- Public guest order creation.
- Admin order list, create, show, update, and conditional hard delete.
- Multiple order items per order.
- Order-item create, list, show, update, and delete.
- Customer resolution by normalized phone.
- Optional customer and address creation during Admin order creation.
- Customer and address snapshots.
- Service, pricing-option, value, and question snapshots.
- Backend price calculation.
- Fixed and percentage discounts.
- Manual paid-amount updates.
- Derived payment status and remaining amount.
- Order status changes and cancellation.
- Item attachments during initial order creation.
- Admin item-attachment upload, delete, and protected download.
- Public idempotency protection and rate limiting.
- Localized order responses.
- Admin filters, sorting, and pagination.
- Separate permissions for orders, items, and attachments.

### 2.2 Excluded

- Customer login or customer portal.
- Public order-detail or tracking endpoint.
- Public order update or cancellation.
- Online payment gateway integration.
- Payment transaction history.
- `order_payments` table.
- Refund workflow.
- Refunded payment status.
- Tax.
- Delivery fee.
- Additional amounts or additional fees.
- Order status-history table.
- Hard deletion of public-created orders.
- Changing the service identity of an existing order item.
- Public attachment upload after order creation.
- Public attachment URLs.
- Client-supplied final prices.
- Request-quote pricing.

---

## 3. Core Order Structure

An order contains at least one order item.

```text
Order
└── One or more Order Items
    ├── Service snapshot
    ├── Selected pricing-option snapshots
    ├── Selected value snapshots
    ├── Answer snapshots
    ├── Attachments
    └── Pricing totals
```

The same service may appear more than once in one order when the items have different quantities, options, answers, notes, or attachments.

Each occurrence is an independent order item.

---

## 4. Enums

Database columns use `TINYINT UNSIGNED`.

External APIs return the integer values.

PHP code uses integer-backed enums.

### 4.1 Order status

```php
enum OrderStatus: int
{
    case PENDING = 0;
    case CONFIRMED = 1;
    case IN_PROGRESS = 2;
    case COMPLETED = 3;
    case CANCELLED = 4;
}
```

```text
0 = pending
1 = confirmed
2 = in_progress
3 = completed
4 = cancelled
```

### 4.2 Payment status

```php
enum PaymentStatus: int
{
    case UNPAID = 0;
    case PARTIALLY_PAID = 1;
    case PAID = 2;
}
```

```text
0 = unpaid
1 = partially_paid
2 = paid
```

Refund is not included in this feature.

### 4.3 Discount type

```php
enum DiscountType: int
{
    case FIXED = 0;
    case PERCENTAGE = 1;
}
```

```text
0 = fixed
1 = percentage
```

### 4.4 Order place

```php
enum OrderPlace: int
{
    case WEBSITE = 0;
    case WHATSAPP = 1;
}
```

```text
0 = website
1 = whatsapp
```

Public-created orders are always `WEBSITE`.

Admin-created orders explicitly submit `orderPlace`.

`orderPlace` describes the business source and does not determine whether the record was technically created through an Admin API.

---

## 5. Order Number

Each order receives a unique number:

```text
ORD-YYYYMMDD-####
```

Example:

```text
ORD-20260801-0001
ORD-20260801-0002
```

Rules:

- The date is calculated using `UTC`.
- The sequence restarts daily.
- Number generation is race safe.
- Number allocation occurs inside the order-creation transaction.
- A number is never reused after order hard deletion.
- A dedicated daily sequence record or equivalent locked relational mechanism must be used.
- Random generation or `MAX(id) + 1` is forbidden.

---

## 6. Customer Resolution

### 6.1 Phone format

The accepted external Egyptian phone format is:

```text
01012345678
```

Validation:

```text
11 digits
starts with 01
```

The normalization service removes spaces, hyphens, and parentheses.

It also converts supported Egyptian international forms to the local form.

```text
+201012345678  -> 01012345678
00201012345678 -> 01012345678
010 1234 5678  -> 01012345678
```

### 6.2 Public customer resolution

Public order creation uses exact normalized-phone matching.

1. No matching customer:
   - Create a new customer.
   - Store the submitted name, phone, and optional email.
2. Exactly one matching customer:
   - Link the order to that customer.
   - Do not automatically update the customer's stored name or email.
3. Multiple matching customers:
   - If submitted phone and email identify exactly one record, link it.
   - Otherwise create a new customer.
   - Never select an arbitrary record.

Email is nullable and is not unique.

The order always stores its own customer snapshot.

Public responses never disclose whether the phone already existed.

### 6.3 Admin customer input

Admin Create requires exactly one of:

```text
customerId
customer
```

Admin Update may omit both when the linked customer is unchanged. When changing
the customer, it accepts exactly one of them.

They may never be submitted together.

Existing customer:

```json
{
  "customerId": 12
}
```

New customer:

```json
{
  "customer": {
    "name": "Mohamed Hassan",
    "phone": "01012345678",
    "email": "mohamed@example.com"
  }
}
```

The Admin API does not silently overwrite existing customer profile data.

---

## 7. Customer Snapshot

Every order stores:

```text
customer_id nullable
customer_name
customer_phone
customer_email nullable
```

The snapshot is the historical truth for the order.

Updating the customer profile later must not alter old orders.

Changing the order customer is allowed only while the order is editable.

When the customer changes:

- Store a new customer snapshot.
- Clear the old address link and snapshot unless a new valid address is submitted.
- Do not change order items.

---

## 8. Address Contract

The only address fields are:

```text
province
city
address
```

Removed fields include:

```text
label
phone
phoneCountryCode
countryCode
area
street
notes
```

Shape:

```json
{
  "province": "Cairo",
  "city": "Nasr City",
  "address": "15 Abbas El Akkad Street"
}
```

The address is optional.

When an `address` object is submitted, all three fields are required:

```text
province
city
address
```

### 8.1 Order address snapshot

Orders store:

```text
customer_address_id nullable
address_province nullable
address_city nullable
address_text nullable
```

### 8.2 Admin address input

Admin Create and Admin Update support exactly one of:

```text
customerAddressId
address
```

They may not be submitted together.

Saved address:

```json
{
  "customerAddressId": 91
}
```

The address must exist and belong to the current customer.

New address:

```json
{
  "address": {
    "province": "Cairo",
    "city": "Nasr City",
    "address": "15 Abbas El Akkad Street"
  }
}
```

For Admin operations:

- Save the new address under the customer.
- Store an independent snapshot in the order.

Remove address:

```json
{
  "address": null
}
```

This clears both relationship and snapshot.

### 8.3 Public address input

A Public order may submit an optional address.

The address is:

- Stored only as an order snapshot.
- Not added to saved customer addresses.
- Not used to update existing customer addresses.

---

## 9. Notes

Order fields:

```text
customerNote nullable
adminNote nullable
```

Order item field:

```text
itemNote nullable
```

Rules:

- Maximum 2000 characters.
- Public Create accepts `customerNote`.
- Public Create does not accept `adminNote`.
- Admin Create and Update accept both.
- Sending `null` clears a note.

---

## 10. Service Snapshot

Each order item keeps its own service snapshot.

Recommended fields:

```text
service_id nullable
service_name_ar
service_name_en
service_slug_ar
service_slug_en
price_type
base_price
unit_price
quantity
item_total
item_note nullable
```

Rules:

- `service_id` may use `ON DELETE SET NULL`.
- Snapshot columns remain when the service link becomes null.
- Later service changes never alter the snapshot.
- Order responses project one locale only.
- API returns `name` and `slug`, not locale-suffixed keys.

Arabic projection:

```json
{
  "serviceSnapshot": {
    "name": "تصميم وتنفيذ مطبخ",
    "slug": "تصميم-وتنفيذ-مطبخ",
    "priceType": 1,
    "basePrice": "5000.00"
  }
}
```

---

## 11. Pricing Option Snapshots

Use relational tables:

```text
order_item_selected_options
order_item_selected_option_values
```

Option snapshot fields:

```text
pricing_option_id nullable
option_name_ar
option_name_en
input_type
is_required
```

Value snapshot fields:

```text
pricing_option_value_id nullable
value_label_ar
value_label_en
price_adjustment
```

Only selected options and selected values are stored.

API localization maps:

```text
option_name_ar / option_name_en -> name
value_label_ar / value_label_en -> label
```

---

## 12. Order Field Answer Snapshots

Use:

```text
order_item_answers
```

Fields:

```text
service_order_field_id nullable
question_ar
question_en
field_type
is_required
answer
```

Rules:

- Store only questions that have an answer.
- Optional unanswered questions are not stored.
- Every required field must receive a valid answer.
- Required answers reject null, empty strings, and whitespace-only strings.
- Maximum answer length is 2000 characters.
- Current `field_type = 0` for text.
- Responses expose localized `question`.
- Updating answers does not recalculate price.

---

## 13. Pricing Authority

The backend ignores client-supplied prices and totals.

The frontend submits IDs, quantity, answers, notes, and files only.

```text
Unit Price =
Service Base Price
+ Sum of selected active value price adjustments
```

```text
Item Total =
Unit Price × Quantity
```

```text
Subtotal =
Sum of Item Totals
```

```text
Discount Amount =
Fixed value
or
Subtotal × Percentage / 100
```

```text
Total =
Subtotal - Discount Amount
```

There are no taxes, delivery fees, additional amounts, or additional fees.

All money values are stored with exact decimal precision and returned as fixed-precision strings.

---

## 14. Discount

Order fields:

```text
discount_type nullable
discount_value DECIMAL(12,2) default 0
discount_amount DECIMAL(12,2) default 0
discount_reason nullable
```

Fixed discount:

```text
discountValue > 0
discountValue <= subtotal
discountReason required
```

Percentage discount:

```text
discountValue > 0
discountValue <= 100
discountReason required
```

No discount:

```text
discount_type = null
discount_value = 0.00
discount_amount = 0.00
discount_reason = null
```

Remove discount:

```json
{
  "discountType": null
}
```

Item or discount changes recalculate subtotal, discount amount, total, payment status, and remaining amount.

---

## 15. Payment Summary

This feature stores cumulative payment only.

There is no payment transaction table.

```text
payment_status TINYINT UNSIGNED
paid_amount DECIMAL(12,2) default 0
```

`remainingAmount` is computed:

```text
remainingAmount = total - paidAmount
```

A negative remaining amount is allowed.

```text
paidAmount = 0
-> UNPAID

0 < paidAmount < total
-> PARTIALLY_PAID

paidAmount >= total
-> PAID
```

Rules:

- Admin submits cumulative `paidAmount`.
- API does not accept `paymentStatus`.
- Admin may increase or decrease `paidAmount`.
- Payment may be updated in any order status.
- Refund handling is deferred.
- Cancellation does not change payment values.

---

## 16. Order Status Workflow

Allowed transitions:

```text
PENDING -> CONFIRMED
PENDING -> CANCELLED

CONFIRMED -> IN_PROGRESS
CONFIRMED -> CANCELLED

IN_PROGRESS -> COMPLETED
IN_PROGRESS -> CANCELLED

COMPLETED -> CANCELLED

CANCELLED -> no transitions
```

No skipping and no backward transition.

Cancellation is Admin-only and requires:

```text
reason required
maximum 1000 characters
```

Stored:

```text
cancellation_reason
cancelled_at
cancelled_by_admin_id
```

Admin detail returns computed:

```json
{
  "availableStatusTransitions": [3, 4]
}
```

No status-history table is created.

---

## 17. Editability

Editable through:

```text
PENDING
CONFIRMED
IN_PROGRESS
```

Editable:

- Customer and snapshot.
- Address and snapshot.
- Notes.
- Discount.
- Order place.
- Items.
- Quantity.
- Answers.
- Selected options.
- Item notes.
- Attachments.

Locked:

```text
COMPLETED
CANCELLED
```

Exceptions:

- `COMPLETED -> CANCELLED`.
- Payment is editable in every status.

Existing item snapshots remain valid when the service later becomes inactive, unavailable, or deleted.

Adding a new item or replacing pricing selections requires the current service to be active, available, and non-deleted.

---

## 18. Service Eligibility

For new items:

```text
inactive service -> 404 SERVICE_NOT_FOUND
deleted service -> 404 SERVICE_NOT_FOUND
active unavailable service -> 409 SERVICE_UNAVAILABLE
```

There is no Admin override.

---

## 19. Pricing Selection Validation

Backend validates:

- Option belongs to service.
- Option is non-deleted.
- Value belongs to option.
- Value is active and non-deleted.
- No duplicate option IDs.
- No duplicate value IDs.
- Required single option: exactly one value.
- Optional single option: zero or one.
- Required multi option: one or more.
- Optional multi option: zero or more.

Fixed-price services reject pricing selections.

---

## 20. Quantity

```text
quantity integer
minimum 1
```

There is no configured business maximum.

---

## 21. Order Item Update

Endpoint:

```http
PATCH /api/v1/admin/orders/{order}/items/{orderItem}
```

The service cannot be changed.

To use a different service, add a new item and delete the old one.

PATCH rules:

- Omitted fields remain unchanged.
- `quantity` updates quantity only.
- `selectedOptions` is full replacement when supplied.
- `answers` is full replacement when supplied.
- `itemNote` supports nullable replacement.
- Create Order and Add Item use `answers[].orderFieldId`.
- Existing Item Update uses `answers[].orderItemAnswerId`.

Quantity-only update keeps snapshot `unitPrice`.

Selected-options update:

- Deletes old pricing snapshots.
- Validates current selections.
- Creates new snapshots.
- Recalculates item and order totals.

`selectedOptions: []` clears selections only when required-option rules remain valid.

Answers update:

- Deletes old answer snapshots.
- `orderItemAnswerId` addresses the stored `order_item_answers` snapshot row,
  not the current service order-field ID.
- Validates against the order item's stored question snapshots, not the
  service's current order-field configuration.
- Stores only answered questions.
- Requires every required snapshotted question.
- Optional stored answer snapshots may be omitted and are deleted.
- An optional question with no stored snapshot cannot be introduced later.
- Does not recalculate price.

---

## 22. Order Item Deletion

Order item deletion is hard delete.

It removes:

- Item.
- Option snapshots.
- Value snapshots.
- Answer snapshots.
- Attachment rows.
- Physical files.

Then recalculates all financial totals.

An order must keep at least one item.

Deleting the last item returns:

```text
409 ORDER_REQUIRES_AT_LEAST_ONE_ITEM
```

---

## 23. Order Hard Delete

Allowed only when all are true:

```text
created_by_admin_id IS NOT NULL
status = PENDING
payment_status = UNPAID
paid_amount = 0
```

`orderPlace` does not affect eligibility.

Public-created orders can never be hard deleted.

Hard delete removes order, items, snapshots, attachment metadata, physical files, and related idempotency data.

The order number is never reused.

---

## 24. Attachments

Attachments belong only to order items.

Limits:

```text
Maximum 3 attachments per order item
Maximum 10 MB per file
Maximum 30 attachment files in one Public/Admin create request
Maximum 100 MB combined attachment bytes in one Public/Admin create request
Maximum 3 attachment files in one standalone Admin upload request
Maximum 30 MB combined attachment bytes in one standalone Admin upload request
```

Allowed:

```text
png
jpg
jpeg
webp
pdf
doc
docx
```

The count includes existing and new files.

Order attachments use protected storage and never expose public URLs.

Protected download:

```http
GET /api/v1/admin/orders/{order}/items/{orderItem}/attachments/{attachment}/download
```

Requires:

- Authenticated active Admin.
- `orders.view`.
- Strict nested ownership.

Attachments may be added or deleted only through `IN_PROGRESS`.

Public users may upload attachments only during initial order creation.

For deletion workflows, the committed database mutation is authoritative.
Physical file deletion happens after commit. Cleanup failures are logged safely
without restoring deleted rows or exposing internal paths.

---

## 25. Public Idempotency

Public creation requires:

```http
Idempotency-Key: UUID
```

Rules:

- Mandatory.
- Permanently reserved while the order exists.
- Store canonical request fingerprint.
- Same key + same fingerprint returns existing order.
- Same key + different fingerprint returns `409 IDEMPOTENCY_KEY_REUSED`.
- Reservation and creation are atomic.
- Reservation stores `reserved_at`.
- `order_id` may be null only inside the owning create transaction.
- `completed_at` is set in the same transaction that sets `order_id`.
- Failed requests leave no completed orphan idempotency record and no committed
  partial reservation row.

---

## 26. Public Rate Limit

```text
5 attempts per minute
```

Rate-limit key combines client IP and normalized phone.

---

## 27. Admin API Routes

### Orders

```http
GET    /api/v1/admin/orders
POST   /api/v1/admin/orders
GET    /api/v1/admin/orders/{order}
PATCH  /api/v1/admin/orders/{order}
DELETE /api/v1/admin/orders/{order}
```

### Status

```http
PATCH /api/v1/admin/orders/{order}/status
```

### Payment

```http
GET   /api/v1/admin/orders/{order}/payment
PATCH /api/v1/admin/orders/{order}/payment
```

### Items

```http
GET    /api/v1/admin/orders/{order}/items
POST   /api/v1/admin/orders/{order}/items
GET    /api/v1/admin/orders/{order}/items/{orderItem}
PATCH  /api/v1/admin/orders/{order}/items/{orderItem}
DELETE /api/v1/admin/orders/{order}/items/{orderItem}
```

### Attachments

```http
POST   /api/v1/admin/orders/{order}/items/{orderItem}/attachments
DELETE /api/v1/admin/orders/{order}/items/{orderItem}/attachments/{attachment}
GET    /api/v1/admin/orders/{order}/items/{orderItem}/attachments/{attachment}/download
```

All numeric IDs use numeric route constraints.

---

## 28. Public API Route

```http
POST /api/v1/public/orders
```

No other Public order endpoint is included.

---

## 29. Public Create Order

```http
Content-Type: multipart/form-data
Idempotency-Key: UUID
```

Use bracket notation and no JSON `payload`.

Required:

```text
customer[name]
customer[phone]
items
```

Optional:

```text
customer[email]
address[province]
address[city]
address[address]
customerNote
```

Item fields:

```text
items[0][serviceId]
items[0][quantity]
items[0][selectedOptions][0][pricingOptionId]
items[0][selectedOptions][0][valueIds][0]
items[0][answers][0][orderFieldId]
items[0][answers][0][answer]
items[0][itemNote]
items[0][attachments][0]
```

Rules:

```text
items min 1
items max 50
```

Public cannot submit order place, status, payment, discount, admin note, or prices.

Defaults:

```text
orderPlace = WEBSITE
status = PENDING
paymentStatus = UNPAID
paidAmount = 0
discount = none
```

Response summary:

```json
{
  "orderNumber": "ORD-20260801-0001",
  "status": 0,
  "paymentStatus": 0,
  "subtotal": "5000.00",
  "discountAmount": "0.00",
  "total": "5000.00",
  "paidAmount": "0.00",
  "remainingAmount": "5000.00"
}
```

---

## 30. Admin Create Order

Always uses `multipart/form-data` with bracket notation, whether or not initial
item attachments are included.

Rules:

```text
customerId XOR customer
customerAddressId XOR address
address optional
items min 1
items max 50
```

Admin-created orders default to pending, unpaid, and zero paid amount.

Permissions:

```text
Always:
orders.create
order-items.create

When initial attachments are present:
order-item-attachments.create
```

Missing a required nested permission rejects the complete atomic create request.

---

## 31. Admin Update Order

```http
PATCH /api/v1/admin/orders/{order}
Content-Type: application/json
```

May update:

- Customer.
- Address.
- Customer note.
- Admin note.
- Discount.
- Order place.
- Status.
- Cancellation reason when cancelling.

Does not update:

- Items.
- Attachments.
- Payment.

Conditional permissions:

```text
without status:
orders.update

with status:
orders.update + orders.change-status
```

The base PATCH and dedicated Status endpoint use the same status action.

---

## 32. Status API

```http
PATCH /api/v1/admin/orders/{order}/status
```

Normal:

```json
{
  "status": 2
}
```

Cancel:

```json
{
  "status": 4,
  "reason": "Customer requested cancellation."
}
```

Permission:

```text
orders.change-status
```

OpenAPI and Postman must document the transition matrix.

---

## 33. Payment API

GET:

```http
GET /api/v1/admin/orders/{order}/payment
```

Response:

```json
{
  "total": "11250.00",
  "paymentStatus": 1,
  "paidAmount": "5000.00",
  "remainingAmount": "6250.00"
}
```

PATCH:

```http
PATCH /api/v1/admin/orders/{order}/payment
```

Request:

```json
{
  "paidAmount": "7000.00"
}
```

Permission:

```text
orders.manage-payment
```

---

## 34. Admin Add Item

```http
POST /api/v1/admin/orders/{order}/items
```

```json
{
  "serviceId": 20,
  "quantity": 2,
  "selectedOptions": [
    {
      "pricingOptionId": 7,
      "valueIds": [15]
    }
  ],
  "answers": [
    {
      "orderFieldId": 5,
      "answer": "200 × 100 cm"
    }
  ],
  "itemNote": "Use natural wood."
}
```

Attachments use the separate upload endpoint after item creation.

---

## 35. Admin Attachment APIs

Upload:

```http
POST /api/v1/admin/orders/{order}/items/{orderItem}/attachments
Content-Type: multipart/form-data
```

```text
files[0]
files[1]
files[2]
```

Standalone Admin upload limit:

```text
Maximum 3 files
Maximum 30 MB combined attachment bytes
```

Delete:

```http
DELETE /api/v1/admin/orders/{order}/items/{orderItem}/attachments/{attachment}
```

Download:

```http
GET /api/v1/admin/orders/{order}/items/{orderItem}/attachments/{attachment}/download
```

---

## 36. Admin Order Index

Filters:

```text
filter[search]
filter[status]
filter[paymentStatus]
filter[orderPlace]
filter[customerId]
filter[serviceId]
filter[createdFrom]
filter[createdTo]
filter[totalFrom]
filter[totalTo]
```

Searches:

```text
order_number
customer_name snapshot
customer_phone snapshot
customer_email snapshot
```

Sorting:

```text
sort=createdAt
sort=-createdAt
sort=total
sort=-total
```

Default:

```text
created_at DESC, id DESC
```

Pagination:

```text
default perPage = 15
maximum perPage = 100
```

Index row:

```json
{
  "id": 145,
  "orderNumber": "ORD-20260801-0007",
  "customerName": "Mohamed Hassan",
  "customerPhone": "01012345678",
  "status": 2,
  "paymentStatus": 1,
  "orderPlace": 1,
  "itemsCount": 2,
  "subtotal": "12500.00",
  "discountAmount": "1250.00",
  "total": "11250.00",
  "paidAmount": "5000.00",
  "remainingAmount": "6250.00",
  "createdAt": "2026-08-01T12:30:00Z"
}
```

Index does not expose `updatedAt`.

---

## 37. Admin Order Detail

Order detail uses the resolved request locale.

It returns one localized set of keys only.

```json
{
  "success": true,
  "message": "تم جلب الطلب بنجاح.",
  "data": {
    "id": 145,
    "orderNumber": "ORD-20260801-0007",
    "status": 2,
    "availableStatusTransitions": [3, 4],
    "orderPlace": 1,

    "customerId": 33,
    "customerSnapshot": {
      "name": "Mohamed Hassan",
      "phone": "01012345678",
      "email": "mohamed@example.com"
    },

    "customerAddressId": 91,
    "addressSnapshot": {
      "province": "Cairo",
      "city": "Nasr City",
      "address": "15 Abbas El Akkad Street"
    },

    "customerNote": "يرجى التواصل قبل الوصول.",
    "adminNote": "تم تأكيد الموعد هاتفيًا.",

    "subtotal": "12500.00",

    "discount": {
      "type": 1,
      "value": "10.00",
      "amount": "1250.00",
      "reason": "خصم عميل دائم"
    },

    "total": "11250.00",

    "payment": {
      "paymentStatus": 1,
      "paidAmount": "5000.00",
      "remainingAmount": "6250.00"
    },

    "cancellation": null,

    "items": [
      {
        "id": 301,
        "serviceId": 20,

        "serviceSnapshot": {
          "name": "تصميم وتنفيذ مطبخ",
          "slug": "تصميم-وتنفيذ-مطبخ",
          "priceType": 1,
          "basePrice": "5000.00"
        },

        "quantity": 2,
        "unitPrice": "6000.00",
        "itemTotal": "12000.00",
        "itemNote": "الخامة المطلوبة خشب طبيعي.",

        "selectedOptions": [
          {
            "id": 501,
            "pricingOptionId": 7,
            "name": "المقاس",
            "inputType": 2,
            "isRequired": true,
            "values": [
              {
                "id": 701,
                "pricingOptionValueId": 15,
                "label": "كبير",
                "priceAdjustment": "1000.00"
              }
            ]
          }
        ],

        "answers": [
          {
            "id": 801,
            "orderFieldId": 5,
            "question": "اكتب المقاسات المطلوبة",
            "fieldType": 0,
            "isRequired": true,
            "answer": "200 × 100 سم"
          }
        ],

        "attachments": [
          {
            "id": 901,
            "originalName": "kitchen-dimensions.pdf",
            "mimeType": "application/pdf",
            "extension": "pdf",
            "sizeBytes": 523410,
            "downloadEndpoint": "/api/v1/admin/orders/145/items/301/attachments/901/download",
            "createdAt": "2026-08-01T12:30:00Z"
          }
        ],

        "createdAt": "2026-08-01T12:30:00Z",
        "updatedAt": "2026-08-01T14:15:00Z"
      }
    ],

    "createdByAdmin": {
      "id": 3,
      "name": "System Administrator"
    },

    "createdAt": "2026-08-01T12:30:00Z",
    "updatedAt": "2026-08-01T14:15:00Z"
  }
}
```

---

## 38. Localization

Order responses resolve using `Accept-Language`.

Include:

```http
Content-Language: ar
Vary: Accept-Language
```

Database snapshots keep both locales.

Responses use:

```text
name
slug
question
label
```

They do not expose locale-suffixed keys.

---

## 39. Permissions

Orders:

```text
orders.view
orders.create
orders.update
orders.delete
orders.change-status
orders.manage-payment
```

Items:

```text
order-items.view
order-items.create
order-items.update
order-items.delete
```

Attachments:

```text
order-item-attachments.create
order-item-attachments.delete
```

Protected download uses `orders.view`.

Admin Create always requires `orders.create`.

Nested items require `order-items.create`.

Nested attachments require `order-item-attachments.create`.

---

## 40. Suggested Data Model

### orders

```text
id
order_number unique

customer_id nullable
customer_name
customer_phone
customer_email nullable

customer_address_id nullable
address_province nullable
address_city nullable
address_text nullable

status tinyint
order_place tinyint

customer_note nullable
admin_note nullable

subtotal decimal(12,2)
discount_type nullable tinyint
discount_value decimal(12,2)
discount_amount decimal(12,2)
discount_reason nullable
total decimal(12,2)

payment_status tinyint
paid_amount decimal(12,2)

cancellation_reason nullable
cancelled_at nullable
cancelled_by_admin_id nullable

created_by_admin_id nullable

created_at
updated_at
```

Orders are not soft deleted.

### order_number_sequences

```text
business_date unique
last_sequence
created_at
updated_at
```

### order_idempotency_keys

```text
id
idempotency_key unique
request_fingerprint
order_id unique
created_at
```

### order_items

```text
id
order_id
service_id nullable
service_name_ar
service_name_en
service_slug_ar
service_slug_en
price_type
base_price
unit_price
quantity
item_total
item_note nullable
created_at
updated_at
```

### order_item_selected_options

```text
id
order_item_id
pricing_option_id nullable
option_name_ar
option_name_en
input_type
is_required
created_at
updated_at
```

### order_item_selected_option_values

```text
id
order_item_selected_option_id
pricing_option_value_id nullable
value_label_ar
value_label_en
price_adjustment
created_at
updated_at
```

### order_item_answers

```text
id
order_item_id
service_order_field_id nullable
question_ar
question_en
field_type
is_required
answer
created_at
updated_at
```

### order_item_attachments

```text
id
order_item_id
disk
path
stored_name
original_name
mime_type
extension
size_bytes
created_at
updated_at
```

Storage paths are internal.

---

## 41. Transactions and Locking

Atomic workflows:

- Public order creation.
- Admin order creation.
- Customer creation.
- Admin address creation.
- Order number allocation.
- Idempotency reservation.
- Item and snapshot creation.
- Initial attachment creation.
- Order update and recalculation.
- Item update and replacement.
- Item deletion and recalculation.
- Payment update.
- Status transition.
- Conditional hard delete.

File compensation:

- Track newly written files.
- Roll back database changes on failure.
- Delete files created by failed requests.
- Never leave partial orders.
- Never expose storage paths.

Recommended lock order:

```text
customer when mutation is required
customer address when created
order number sequence
idempotency key
order
order items by id ASC
selected options by id ASC
answers by id ASC
attachments by id ASC
revalidate
mutate
commit
```

---

## 42. Error Codes

```text
ORDER_NOT_FOUND
ORDER_ITEM_NOT_FOUND
ORDER_ATTACHMENT_NOT_FOUND
ORDER_NOT_EDITABLE
ORDER_REQUIRES_AT_LEAST_ONE_ITEM
INVALID_ORDER_STATUS_TRANSITION
CANCELLATION_REASON_REQUIRED
ORDER_CANNOT_BE_DELETED

SERVICE_NOT_FOUND
SERVICE_UNAVAILABLE

PRICING_OPTION_NOT_FOUND
PRICING_OPTION_VALUE_NOT_FOUND
INVALID_PRICING_SELECTION

ORDER_FIELD_NOT_FOUND
REQUIRED_ORDER_FIELD_MISSING
INVALID_ORDER_FIELD_ANSWER

CUSTOMER_NOT_FOUND
CUSTOMER_ADDRESS_NOT_FOUND
CUSTOMER_ADDRESS_MISMATCH

IDEMPOTENCY_KEY_REQUIRED
IDEMPOTENCY_KEY_REUSED
ORDER_NUMBER_SEQUENCE_EXHAUSTED

ATTACHMENT_LIMIT_REACHED
ATTACHMENT_NOT_ALLOWED
ATTACHMENT_TOO_LARGE

UNAUTHENTICATED
USER_INACTIVE
FORBIDDEN
VALIDATION_ERROR
RATE_LIMITED
INTERNAL_ERROR
```

Nested foreign resources return non-disclosing not-found responses.

---

## 43. Status Codes

Use the existing project `HttpStatusCode` enum.

```text
POST create              201
Idempotent replay        200
GET                      200
PATCH                    200
DELETE                   200 with data: null
Authentication failure   401
Authorization failure    403
Not found                404
Business conflict        409
Validation failure       422
Rate limit               429
Internal error           500
```

Do not use `204`.

---

## 44. Testing Requirements

Use Pest with MySQL.

Cover:

- Public customer resolution.
- Phone normalization.
- Optional email and address.
- Public idempotency and rate limiting.
- Admin customer/address XOR rules.
- Atomic create with items and files.
- Backend-only pricing.
- Discount calculations.
- Payment calculations including negative remaining amount.
- Every allowed and forbidden status transition.
- Cancellation metadata.
- Localized detail with one locale only.
- Item full-replacement semantics.
- Required answers.
- Protected attachment download.
- Conditional hard delete.
- Order number concurrency.
- Idempotency concurrency.
- Item deletion race.
- Payment update race.
- File compensation.

---

## 45. Implementation Constraints

- Laravel 13 API-only backend.
- Sanctum Bearer authentication for Admin routes.
- Public Create is unauthenticated.
- Existing CORS and locale middleware remain authoritative.
- Existing `ApiResponse` and `HttpStatusCode` enum are required.
- Spatie Permission for authorization.
- Spatie Query Builder for Admin list filters and sorts.
- MySQL required.
- No SQLite assumptions.
- No queue required.
- No customer portal.
- No online payment integration.
- Order attachments are protected.
- Service media remains governed by Feature 004.
- All client inputs are untrusted.
- Backend always recalculates prices.

---

## 46. Approved Final Summary

```text
Order:
- Multiple Order Items.
- Public or Admin creation.
- Public creates PENDING website orders.
- Admin creates PENDING orders and specifies source.
- Minimum 1 item, maximum 50 during create.

Customer:
- Resolve by normalized Egyptian phone.
- External phone shape: 01012345678.
- Email nullable and non-unique.
- Always store order snapshot.
- Never silently overwrite existing customer data.

Address:
- province
- city
- address
- optional
- Admin may select or create saved address.
- Public stores snapshot only.

Pricing:
- Backend authority.
- subtotal - discount = total.
- Fixed or percentage discount.
- Discount reason required.
- No tax, delivery, or additional amount.

Payment:
- Admin submits cumulative paidAmount.
- Backend derives paymentStatus.
- remainingAmount = total - paidAmount.
- Negative remainingAmount allowed.
- No payment history or refunds.

Status:
0 pending
1 confirmed
2 in_progress
3 completed
4 cancelled

No skipping.
Cancellation allowed from any non-cancelled status.
No status-history table.

Editing:
- General/item/attachment editing through IN_PROGRESS.
- COMPLETED and CANCELLED locked.
- Payment editable in every status.
- Existing item service cannot be changed.

Attachments:
- Item level only.
- Maximum 3, 10 MB each.
- Protected download API.
- Public upload only during initial create.

Delete:
- Hard delete only Admin-created + PENDING + UNPAID + paidAmount 0.
- Public orders never hard deleted.
- Order numbers never reused.

Localization:
- Snapshots store Arabic and English.
- Responses expose one resolved locale:
  name, slug, question, label.

Public protection:
- Idempotency-Key required and permanently reserved.
- 5 attempts per minute by IP + normalized phone.
```

## Feature 007 completion timestamp amendment

- Admin Order index and show responses expose `completedAt` as an ISO-8601 UTC timestamp or `null`.
- `completedAt` records the first transition to `completed` and is preserved by the approved `completed -> cancelled` transition.
- The field is backend-controlled and is rejected from Public/Admin create, order update, status, payment, item, and attachment mutation requests.
- Existing completed orders are backfilled from their unchanged `updated_at` value by Feature 007's idempotent migration.
