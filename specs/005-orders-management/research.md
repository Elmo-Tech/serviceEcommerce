# Research: Orders Management

**Status:** Complete  
**Feature:** `005-orders-management`

This document freezes the implementation decisions required before task
generation. All decisions derive from the approved Feature 005 specification,
the active spec, and the existing project standards.

## Decision 1 — Use integer-backed PHP enums and `TINYINT UNSIGNED` columns

**Decision:**

```text
OrderStatus:   0 pending, 1 confirmed, 2 in_progress, 3 completed, 4 cancelled
PaymentStatus: 0 unpaid, 1 partially_paid, 2 paid
DiscountType:  0 fixed, 1 percentage
OrderPlace:    0 website, 1 whatsapp
```

**Why:** Feature 005 explicitly approves integer order-domain enums in request
and response contracts. Using backed enums and compact integer columns keeps
validation, casts, factories, resources, and tests aligned.

**Rejected:** External string enums and MySQL native `ENUM`.

## Decision 2 — Allocate order numbers through a daily sequence table

**Decision:** Add `order_number_sequences` keyed by UTC business date and
allocate `ORD-YYYYMMDD-####` inside the order-creation transaction.

**Why:** The feature requires monotonic daily sequence numbers, no reuse, and
race safety. A dedicated locked sequence row is simpler and safer than
computing from order counts.

**Rejected:** `MAX(id)+1`, random suffixes, post-commit numbering, and
application-only optimistic counters.

## Decision 3 — Persist public idempotency through a transactional reservation

**Decision:** Add `order_idempotency_keys` with:

```text
idempotency_key unique
request_fingerprint
order_id nullable and unique
reserved_at
completed_at nullable
created_at
updated_at
```

A new Public Create transaction first inserts the unique reservation, then
locks it. The transaction creates the order and fills `order_id` plus
`completed_at` before commit. A failed create rolls back both reservation and
order, so no incomplete reservation survives.

A concurrent request for the same key blocks on the unique reservation and,
after the owner commits, reads the completed row:

- same fingerprint: replay the linked order
- different fingerprint: `IDEMPOTENCY_KEY_REUSED`

**Why:** `order_id` cannot be required before the order exists. Temporarily
nullable `order_id` permits true pre-order reservation without allowing an
incomplete committed record.

**Rejected:** Non-null `order_id` on initial reservation, cache-only
reservation, read-then-insert logic, in-memory tracking, and weak
request-body-string comparison.

## Decision 4 — Use Actions for all mutating order workflows

**Decision:** Actions own create, update, status, payment, delete, item,
attachment, and idempotency-backed public workflows.

**Why:** These operations combine transactions, lock ordering, snapshot writes,
validation against current live records, and filesystem compensation.

**Rejected:** Fat controllers or simple pass-through services for complex
mutations.

## Decision 5 — Use dedicated pricing and payment-summary services

**Decision:** Separate reusable services compute pricing and payment state:

- `OrderPricingService`
- `OrderPaymentSummaryService`
- `OrderStatusTransitionService`

**Why:** Pricing, discount, payment summary, and state rules are reused across
public create, admin create, admin update, item update, item delete, and
payment update.

**Rejected:** Duplicating calculations inside each Action or embedding them in
Eloquent models.

## Decision 6 — Resolve customers through one focused order resolver

**Decision:** `CustomerOrderResolver` will orchestrate:

- normalized phone matching
- public create matching rules
- Admin Create requiring exactly one of `customerId` or `customer`
- Admin Update allowing both keys to be omitted when the customer is unchanged
  and rejecting the pair when both are supplied
- order-level customer snapshot filling
- address-link and address-snapshot replacement rules

**Why:** Customer resolution rules differ between guest and admin flows but
must remain consistent and independently testable.

**Rejected:** Scattered matching logic across controllers and Form Requests.

## Decision 7 — Keep public create on multipart bracket notation

**Decision:** Public order create accepts bracket notation such as:

```text
customer[name]
items[0][serviceId]
items[0][selectedOptions][0][valueIds][0]
items[0][attachments][0]
```

**Why:** This is the approved contract and allows Laravel to hydrate nested
arrays while preserving uploaded files as `UploadedFile` instances.

**Rejected:** A JSON `payload` field or multi-step temporary-upload workflow.

## Decision 8 — Use snapshot tables instead of JSON blobs for selections and answers

**Decision:** Use relational child tables for selected options, selected
values, and answers.

**Why:** The feature requires validation, nested ownership, selective
replacement, and clear historical meaning. Relational tables fit these needs
better than opaque JSON columns.

**Rejected:** Large JSON columns for all item configuration state.

## Decision 9 — Use synchronous filesystem compensation

**Decision:** Track every newly stored attachment path during the active Action
and delete those files if the surrounding workflow fails before success.

**Why:** MySQL transactions do not roll back the filesystem. Synchronous
compensation preserves consistency without adding unsupported queue or cleanup
infrastructure.

**Rejected:** Asynchronous cleanup jobs or accepting orphaned attachments.

## Decision 10 — Restrict attachment mutation to editable orders, but allow download in any status

**Decision:** Upload and delete attachment routes obey the editable-order gate.
Protected download remains available for authorized admins regardless of order
status.

**Why:** This exactly matches the approved feature behavior and supports review
of completed or cancelled work without reopening the order to mutation.

**Rejected:** Fully locking attachment download after completion or allowing
late public uploads.

## Decision 11 — Use Query classes for admin lists only

**Decision:** `AdminOrderIndexQuery` and `OrderItemIndexQuery` wrap allowed
filters, sorts, eager loading, and pagination.

**Why:** The admin list contract is rich enough to justify centralized query
objects, while single-order and single-item reads remain simple controller plus
Resource flows.

**Rejected:** Generic repositories or duplicated list logic in controllers.

## Decision 12 — Preserve historical links with nullable foreign keys

**Decision:** Snapshot rows keep nullable foreign-key references to current
domain records in addition to immutable copied fields.

**Why:** Historical meaning must survive service, customer, address, option,
value, or order-field deletion while still allowing useful admin navigation
when the current record exists.

**Rejected:** Required foreign keys that would block legitimate historical
deletion or pure snapshots with no remaining link to current data.


## Decision 13 — Use multipart form-data for both create endpoints

**Decision:** Public Create and Admin Create always use
`multipart/form-data` with bracket notation, even when the request contains no
files.

**Why:** One stable media type avoids frontend and Postman branching and keeps
the initial nested-attachment contract consistent.

**Rejected:** Switching between JSON and multipart according to whether files
are present.

## Decision 14 — Separate create answer IDs from historical answer-update IDs

**Decision:**

- `selectedOptions` replacement validates against the current eligible Service
  pricing configuration.
- Create Order and Add Item accept `answers[].orderFieldId`.
- Existing Item Update accepts `answers[].orderItemAnswerId`.
- `orderItemAnswerId` references the stored `order_item_answers.id`, not the
  nullable current `service_order_field_id`.
- Item Update validates strict ownership of every answer snapshot.
- Required stored answer snapshots must remain present in the full replacement.
- An optional question that had no answer and therefore no stored snapshot
  cannot be introduced later.
- Answer replacement changes answer text only and never recalculates price.

**Why:** The historical answer row remains addressable after the current
Service Order Field is deleted and its foreign key becomes null.

**Rejected:** Using `orderFieldId` to update an existing historical snapshot,
validating replacement against current Service Order Fields, or silently
creating a new question snapshot during update.

## Decision 15 — Canonicalize idempotency payloads and file content deterministically

**Decision:** The idempotency fingerprint is SHA-256 over a canonical
representation that:

- normalizes Egyptian phone input
- trims approved text values without changing internal text content
- sorts object keys lexically
- preserves Order Item array order
- sorts set-like `valueIds`
- formats decimals to two decimal places
- includes file SHA-256 content hash, byte size, MIME type, and item/file
  position
- excludes temporary paths and original filenames

**Why:** Retries must match by business content rather than transport-specific
temporary paths or filenames.

**Rejected:** Hashing raw multipart boundaries, raw request-body bytes, or only
scalar fields.

## Decision 16 — Add bounded aggregate upload limits

**Decision:**

```text
Per item: maximum 3 files
Per file: maximum 10 MB
Per Public/Admin create request: maximum 30 files and 100 MB combined
Per standalone Admin attachment upload: maximum 3 files and 30 MB combined
```

**Why:** Per-item validation alone permits a theoretical 150 files and 1.5 GB
request, which is not safe for synchronous PHP deployment.

**Rejected:** Depending only on `post_max_size` or accepting an unbounded total
request.

## Decision 17 — Treat committed database deletion as authoritative

**Decision:** Newly written files are removed on transaction failure. For
deletion workflows, database records are committed first, then physical files
are deleted synchronously. Physical cleanup failure is logged using safe IDs
and does not recreate committed database records.

**Why:** Filesystem deletion cannot participate in the MySQL transaction.
Restoring deleted relational state after commit would create a second,
error-prone compensation problem.

**Rejected:** Deleting old files before commit or exposing internal paths in the
API error.

## Decision 18 — Reject daily sequence exhaustion explicitly

**Decision:** The daily sequence supports `0001` through `9999`. The next
allocation attempt returns `409 ORDER_NUMBER_SEQUENCE_EXHAUSTED` and does not
create an order.

**Why:** The four-digit contract has a finite, explicit range.

**Rejected:** Producing a five-digit suffix that violates the approved order
number format or wrapping to a reused number.
