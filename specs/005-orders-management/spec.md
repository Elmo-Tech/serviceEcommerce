# Feature Specification: Orders Management

**Feature Branch**: `005-orders-management`

**Created**: 2026-08-01

**Status**: Ready for Planning

**Input**: User description: "[$speckit-specify](C:\\xampp\\htdocs\\serviceEcommerce\\.agents\\skills\\speckit-specify\\SKILL.md) let's build the new feature orders management using [005-orders-management.md](docs/features/005-orders-management.md) as referance to build it and commit to each line in it"

## Scope and Governing Context *(mandatory)*

**Approved source**: `docs/features/005-orders-management.md`

**In scope**:

- Public guest order creation through `POST /api/v1/public/orders`
- Admin order list, create, show, update, status change, payment summary, and
  conditional hard delete
- Admin nested order-item create, list, show, update, and delete
- Customer resolution by normalized phone during public and admin order create
- Optional customer-address selection or creation during admin order create and
  update
- Historical snapshots for customer, address, service, selected pricing
  options, selected values, answers, and attachments
- Backend-controlled subtotal, discount, total, payment-summary, and remaining
  amount calculation
- Public idempotency enforcement and rate limiting for order creation
- Localized admin and public order responses
- Order-item attachment upload, delete, and download flows
- Permission-separated admin order, item, and attachment operations

**Out of scope**:

- Customer authentication, customer portal, public order tracking, public order
  update, or public order cancellation
- Online payment gateways, payment webhooks, transaction history, refunds, tax,
  delivery fees, or extra fees
- Quote-request lifecycle tables or request-quote pricing
- Public attachment upload after initial order creation
- Public attachment URLs
- Order status-history or general audit-log tables
- Changing an existing order item's service identity
- Frontend implementation in React or Next.js

**Governing documents reviewed**:

- `.specify/memory/constitution.md`
- `AGENTS.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- `docs/02-standards/api-standards.md`
- `docs/02-standards/database-standards.md`
- `docs/02-standards/authorization-standards.md`
- `docs/02-standards/file-storage-standards.md`
- `docs/02-standards/localization-standards.md`
- `docs/02-standards/testing-standards.md`
- `docs/features/002-customers-addresses.md`
- `docs/features/005-orders-management.md`

**Approved feature decisions**:

- External order-domain enums use integer values in request and response
  contracts. Postman and OpenAPI MUST document every integer-to-meaning
  mapping.
- Order numbers use `ORD-YYYYMMDD-####`, with a race-safe daily sequence based
  on the UTC date. Allocated numbers are never reused.
- Order-item attachments use protected storage and authenticated nested
  download endpoints. Raw paths and public attachment URLs are forbidden.
- The order address contract is exactly `province`, `city`, and `address`.
- General order, item, and attachment mutation is allowed through
  `IN_PROGRESS`; payment remains editable in every status; protected attachment
  download remains available in every status.
- Public/Admin create requests are capped at 30 attachment files and 100 MB
  combined, while standalone Admin attachment upload is capped at 3 files and
  30 MB combined.
- Public idempotency reservations store `reserved_at`, allow nullable
  `order_id` only inside the owning transaction, and set `completed_at` in the
  same transaction as `order_id`.
- Existing item answer replacement uses `answers[].orderItemAnswerId`, which
  points to the stored `order_item_answers.id` snapshot row.
- Daily sequence exhaustion returns `ORDER_NUMBER_SEQUENCE_EXHAUSTED`.
- These decisions are approved for Feature 005 and replace older contradictory
  wording for this feature. Any shared document still carrying older wording
  MUST be synchronized during planning and documentation work, but this does
  not block `/speckit.plan`.
- The user explicitly approved a governance exception on August 2, 2026 so
  Feature 005 wins over conflicting repository defaults for:
  - the reduced status set `pending|confirmed|in_progress|completed|cancelled`
  - the explicit `completed -> cancelled` transition
  - conditional order hard delete
  - nested order-item hard delete

No unresolved clarification or governance blocker remains in this
specification.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Guest submits a multi-item order safely (Priority: P1)

A guest customer submits one localized order containing one or more services,
quantities, selected pricing values, answers, notes, and optional attachments
through one public API request while the backend independently resolves the
customer, validates eligibility, calculates pricing, preserves snapshots, and
protects against duplicate submissions.

**Why this priority**: Public order submission is the main business outcome of
the feature and unlocks the platform's service-selling workflow.

**Independent Test**: Can be fully tested by submitting multipart public orders
that create new customers, reuse matching customers, include valid and invalid
service selections, and verify idempotency, localization, snapshot
preservation, and backend-controlled totals.

**Acceptance Scenarios**:

1. **Given** a guest submits a valid multipart order with one or more eligible
   services, **When** the backend processes the request, **Then** it creates one
   order with one or more order items, stores the approved snapshots, and
   returns the approved summary fields in the shared success envelope.
2. **Given** a guest replays the same `Idempotency-Key` with the same canonical
   request fingerprint, **When** the request is received again, **Then** the API
   returns the already-created order result instead of creating a duplicate
   order.
3. **Given** a guest reuses the same `Idempotency-Key` with a different request
   fingerprint, **When** the request reaches the backend, **Then** the API
   rejects it with the approved idempotency-reuse outcome.
4. **Given** a guest submits a service that is deleted or inactive, **When**
   the order is validated, **Then** the API returns the approved
   service-not-found outcome and persists no partial order.
5. **Given** a guest submits a service that is active but unavailable, **When**
   the order is validated, **Then** the API returns the approved
   service-unavailable outcome and persists no partial order.

---

### User Story 2 - Admin manages the order lifecycle and money summary (Priority: P1)

An authorized administrator creates orders on behalf of customers, updates
editable order data, changes order status through the approved transition
matrix, manages cumulative paid amount, and conditionally hard deletes eligible
admin-created orders.

**Why this priority**: Back-office order control is required to operate the
service business after orders are submitted.

**Independent Test**: Can be fully tested by creating admin orders for existing
and new customers, editing notes and address state, applying and removing
discounts, updating paid amount, changing statuses through valid and invalid
paths, and verifying conditional hard-delete rules.

**Acceptance Scenarios**:

1. **Given** an authenticated administrator with `orders.create`, **When** they
   create an order for an existing customer or a new customer using approved
   payload shapes, **Then** the backend creates the order, items, snapshots, and
   default financial state.
2. **Given** an editable order and an administrator with `orders.update`,
   **When** they update allowed order-level fields without changing status,
   **Then** the backend persists only the submitted editable fields and
   recalculates totals when required.
3. **Given** an order and an administrator with `orders.change-status`, **When**
   they request an allowed next status, **Then** the backend applies the change
   and returns the updated order with the approved available transitions.
4. **Given** an invalid status jump or a cancellation request without a reason,
   **When** the backend validates the request, **Then** it returns the approved
   status-transition or cancellation validation error.
5. **Given** a pending unpaid admin-created order with zero paid amount,
   **When** an administrator with `orders.delete` deletes it, **Then** the order
   and its nested records are removed and the order number remains permanently
   reserved from reuse.

---

### User Story 3 - Admin manages nested order items and attachments (Priority: P1)

An authorized administrator adds, views, updates, and deletes nested order
items without changing the original service identity of an existing item, and
manages item attachments through protected nested APIs.

**Why this priority**: Orders remain operationally useful only if the admin can
adjust item-level selections, answers, quantities, and supporting files during
approved editable states.

**Independent Test**: Can be fully tested by adding items to editable orders,
replacing selected options and answers, deleting non-final items, uploading and
removing attachments within the approved state boundary, and verifying strict
nested ownership on every route.

**Acceptance Scenarios**:

1. **Given** an editable order and an administrator with `order-items.create`,
   **When** they add a valid new service item, **Then** the backend stores a new
   item snapshot and recalculates order totals.
2. **Given** an existing order item, **When** the administrator updates only
   the quantity, **Then** the backend updates quantity and item total without
   recomputing snapshot unit price.
3. **Given** an existing order item, **When** the administrator submits full
   replacement `selectedOptions`, **Then** the backend replaces the old pricing
   snapshots, validates current eligibility, and recalculates totals.
4. **Given** the only remaining order item on an order, **When** the
   administrator attempts to delete it, **Then** the backend rejects the
   request with the approved at-least-one-item outcome.
5. **Given** an order item belongs to an editable order, **When** an
   administrator uploads or deletes attachments, **Then** the backend enforces
   file rules, nested ownership, permissions, and the editable-state boundary;
   **And given** an item belongs to any order status, **When** an authorized
   administrator downloads its attachment, **Then** protected download remains
   available with strict nested ownership.

---

### User Story 4 - Admin reviews localized searchable order data (Priority: P2)

An authorized administrator lists and reviews orders through paginated,
filterable, sortable admin APIs that project localized snapshots without
changing stable machine identifiers or leaking hidden attachment storage data.

**Why this priority**: Operational teams need fast review and retrieval of
orders without depending on frontend-side filtering logic.

**Independent Test**: Can be fully tested by creating orders with different
statuses, payment states, places, totals, customers, and items, then verifying
allowed filters, sorts, pagination, localized detail projection, and forbidden
nested-resource disclosure behavior.

**Acceptance Scenarios**:

1. **Given** multiple orders exist, **When** an authorized administrator calls
   the admin index with approved filters and sorts, **Then** the API returns the
   paginated list using only allow-listed query parameters.
2. **Given** an order detail request with `Accept-Language: ar`, **When** the
   backend serializes localized snapshots, **Then** it returns Arabic-facing
   text fields, `Content-Language: ar`, and `Vary: Accept-Language`.
3. **Given** a nested item or attachment ID belongs to a different order item
   or order, **When** the administrator calls the wrong nested route, **Then**
   the backend returns the approved nested-resource non-disclosure outcome.

### Edge Cases

- What happens when a public request exceeds the allowed 5 attempts per minute
  for the same IP and normalized phone? The API must return the approved
  rate-limited outcome without creating a partial order.
- What happens when two create requests race for the same daily order number or
  the same idempotency key? Atomic reservation and locking must prevent
  duplicate order numbers and duplicate completed orders.
- What happens when an order update changes customer identity or address on an
  editable order? The backend must replace the order-level snapshot safely
  without mutating existing item snapshots.
- What happens when selected pricing values or required answers become invalid
  after the original order was created? Existing snapshots remain historical
  truth, but any new item or replacement selection must validate the current
  service state and current configuration.
- What happens when attachment upload succeeds on disk but the surrounding
  order mutation fails? The backend must compensate by deleting newly written
  files and leaving no orphaned partial order state.

- What happens when an item or discount edit makes `paidAmount` greater than
  the new `total`? The order remains valid, `paymentStatus` remains `PAID`, and
  `remainingAmount = total - paidAmount` may be negative.
- What happens when an address object is partially submitted? The API rejects
  it unless `province`, `city`, and `address` are all present.
- What happens when a completed or cancelled order attachment must be reviewed?
  Mutation remains blocked, but authorized protected download remains
  available.
- What happens when the Admin submits a status in the general Order PATCH?
  The request requires both `orders.update` and `orders.change-status` and uses
  the same transition workflow as the dedicated status endpoint.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST allow public guests to create orders only through
  `POST /api/v1/public/orders` using multipart form-data and a required UUID
  `Idempotency-Key` header.
- **FR-002**: The system MUST expose only the approved Admin order, status,
  payment, nested item, and nested attachment routes under
  `/api/v1/admin/orders*`.
- **FR-003**: Every order MUST contain at least one order item. Create requests
  MUST accept between 1 and 50 items, and the same service MAY appear more than
  once as independent items.
- **FR-004**: The backend MUST be the sole authority for service eligibility,
  customer resolution, option and answer validation, unit price, item total,
  subtotal, discount amount, total, payment status, and remaining amount.
- **FR-005**: Public Create MUST ignore or reject client-supplied `status`,
  `orderPlace`, discount fields, `paidAmount`, `paymentStatus`, prices, and
  totals. Admin clients MAY submit `status` only through the approved Order
  PATCH or Status endpoint and MAY submit cumulative `paidAmount` only through
  the Payment endpoint; the backend MUST validate or derive the resulting
  workflow and financial state.
- **FR-006**: Public Create MUST accept required customer `name` and Egyptian
  local `phone`, optional non-unique `email`, optional `customerNote`, optional
  address snapshot, and item quantities, selections, answers, notes, and
  initial attachments.
- **FR-007**: Admin Create MUST require exactly one of `customerId` or nested
  `customer`. Admin Update MAY omit both when the customer is unchanged; when
  changing the customer, it MUST accept exactly one of them. The two keys MUST
  never be submitted together. Both Create and Update MUST accept at most one
  of `customerAddressId` or nested `address`, and Update MAY omit both when the
  address is unchanged.
- **FR-008**: The accepted external phone shape MUST be 11 digits beginning with
  `01`, such as `01012345678`; matching MUST use a normalized local Egyptian
  representation after removing supported formatting and converting supported
  `+20` or `0020` forms.
- **FR-009**: Customer resolution MUST create a customer when no phone match
  exists, reuse the single match without silently overwriting its stored name or
  email, and use a unique phone-plus-email match when duplicate phone records
  exist; otherwise it MUST create a new customer rather than choose
  arbitrarily. Email MUST remain nullable and non-unique.
- **FR-010**: Every order MUST store independent historical snapshots for
  customer, optional address, service, selected pricing options and values,
  answered order fields, and safe attachment metadata.
- **FR-011**: Snapshot-backed responses MUST expose one resolved language using
  stable keys such as `name`, `slug`, `question`, and `label`, never parallel
  locale-suffixed response keys.
- **FR-012**: The address object MUST contain exactly `province`, `city`, and
  `address`. The address is optional, but all three keys are required whenever
  the object is submitted.
- **FR-013**: Public-submitted address data MUST be stored only as an order
  snapshot. Admin flows MAY select a saved customer address or create a new
  saved address and MUST also store an independent order snapshot.
- **FR-014**: Every order number MUST use `ORD-YYYYMMDD-####`, where the date and
  daily sequence are based on UTC; allocation MUST be race safe, unique, and
  never reused after deletion.
- **FR-015**: Payment tracking MUST store cumulative `paidAmount`, derive
  `paymentStatus`, and compute `remainingAmount = total - paidAmount` without a
  payment-transaction table.
- **FR-016**: Payment status MUST be calculated as `UNPAID` when
  `paidAmount = 0`, `PARTIALLY_PAID` when `0 < paidAmount < total`, and `PAID`
  when `paidAmount >= total`. A negative `remainingAmount` MUST be allowed.
- **FR-017**: An Admin with `orders.manage-payment` MUST be able to increase or
  decrease cumulative `paidAmount` in every order status, including
  `COMPLETED` and `CANCELLED`; clients MUST NOT submit `paymentStatus`.
- **FR-018**: Discounts MUST support `0 = FIXED` and `1 = PERCENTAGE`, require a
  non-empty reason, store both input value and calculated monetary amount, and
  reject a fixed discount above subtotal or a percentage above 100.
- **FR-019**: Removing a discount by submitting `discountType = null` MUST clear
  its type, value, amount, and reason. The feature MUST NOT add taxes, delivery
  fees, or additional amounts.
- **FR-020**: Order status MUST use `0 = PENDING`, `1 = CONFIRMED`,
  `2 = IN_PROGRESS`, `3 = COMPLETED`, and `4 = CANCELLED`.
- **FR-021**: The only allowed status transitions MUST be:
  `PENDING -> CONFIRMED|CANCELLED`,
  `CONFIRMED -> IN_PROGRESS|CANCELLED`,
  `IN_PROGRESS -> COMPLETED|CANCELLED`,
  and `COMPLETED -> CANCELLED`; `CANCELLED` MUST be terminal, skipping and
  backward transitions MUST be rejected, and cancellation MUST require a
  reason of at most 1000 characters.
- **FR-022**: General order, item, and attachment mutation MUST be allowed only
  while status is `PENDING`, `CONFIRMED`, or `IN_PROGRESS`. `COMPLETED` and
  `CANCELLED` MUST be locked except for the explicitly approved
  `COMPLETED -> CANCELLED` transition and payment updates in every status.
- **FR-023**: Admin order detail MUST include computed
  `availableStatusTransitions` as integer values valid for the current status.
- **FR-024**: The general Order PATCH MAY include `status`; when it does, it
  MUST require both `orders.update` and `orders.change-status` and MUST invoke
  the same transition workflow as the dedicated Status endpoint.
- **FR-025**: The system MUST allow nested item create, show, update, and delete,
  but MUST forbid changing `serviceId` on an existing item.
- **FR-026**: Item PATCH MUST preserve omitted keys, use the snapshotted
  `unitPrice` for quantity-only updates, treat supplied `selectedOptions` as
  full replacement, and treat supplied `answers` as full replacement.
  Selected-option replacement MUST validate against the current eligible
  service pricing configuration.
- **FR-027**: Answer replacement on an existing item MUST validate against the
  order item's stored question snapshots rather than the service's current
  order-field configuration. Only answered questions MUST remain stored, and
  every required snapshotted question MUST have a non-null, non-empty,
  non-whitespace answer of at most 2000 characters. Answer replacement MUST NOT
  recalculate price.
- **FR-028**: Deleting an item MUST hard delete its option, value, answer, and
  attachment records and physical files, recalculate order finances, and reject
  deletion of the final item with `409 ORDER_REQUIRES_AT_LEAST_ONE_ITEM`.
- **FR-029**: Quantity MUST be an integer of at least 1. This feature defines no
  additional business maximum beyond safe application and database bounds.
- **FR-030**: `customerNote`, `adminNote`, and `itemNote` MUST each allow at
  most 2000 characters.
- **FR-031**: Pricing validation MUST verify option and value ownership,
  active/non-deleted state, required multiplicity, and absence of duplicate
  option or value IDs; fixed-price services MUST reject pricing selections.
- **FR-032**: Each order item MUST allow at most 3 attachments, each no larger
  than 10 MB, with extensions limited to `png`, `jpg`, `jpeg`, `webp`, `pdf`,
  `doc`, and `docx`.
- **FR-033**: Public/Admin create requests MUST support at most 30 total
  attachment files and 100 MB combined attachment bytes, while standalone
  Admin attachment-upload requests MUST support at most 3 files and 30 MB
  combined.
- **FR-034**: Order-item attachments MUST use protected storage, MUST never
  expose raw paths or public URLs, and MUST be downloaded only through an
  authenticated nested Admin endpoint with `orders.view` and strict ownership
  validation.
- **FR-035**: Attachment upload and deletion MUST be allowed only in editable
  statuses, while protected attachment download MUST remain available in every
  order status.
- **FR-036**: Public users MAY upload item attachments only during initial order
  creation and MUST have no later attachment-management endpoint.
- **FR-037**: Public Create MUST be limited to 5 attempts per minute using a key
  combining client IP and normalized phone.
- **FR-038**: Public idempotency MUST permanently bind a unique key to a
  canonical request fingerprint and order: the same key and fingerprint return
  the existing result, while the same key with different content returns
  `409 IDEMPOTENCY_KEY_REUSED`.
- **FR-039**: The Admin index MUST support only the approved filters, only
  `createdAt`, `-createdAt`, `total`, and `-total` sorting, default to
  `created_at DESC, id DESC`, default to 15 rows per page, and cap `perPage` at
  100.
- **FR-040**: Order hard delete MUST be allowed only when
  `created_by_admin_id` is present, status is `PENDING`, payment status is
  `UNPAID`, and `paidAmount = 0`; public-created orders MUST never be hard
  deleted and allocated order numbers MUST remain consumed.
- **FR-041**: Public Create MUST return only the approved order summary and MUST
  not disclose customer matching, internal paths, or full item detail.
- **FR-042**: All external order-domain enum values MUST be integers, with
  stable meanings documented in OpenAPI and Postman.
- **FR-043**: The feature MUST NOT create order status history, general audit
  history, payment transactions, refunds, or public order tracking.
- **FR-044**: The Postman collection MUST document every header, path, query,
  body, multipart, and response key with its location, type, required/optional
  state, nullability, allowed values, default, meaning, and example.
- **FR-045**: Postman MUST explain every integer enum mapping, including order
  status, payment status, discount type, order place, service price type,
  pricing input type, field type, and `availableStatusTransitions`.
- **FR-046**: Postman MUST include valid, validation, authorization, not-found,
  and business-conflict examples appropriate to each endpoint, including every
  valid and invalid status path and the unpaid, partial, paid, negative
  remaining, fixed-discount, percentage-discount, and discount-removal cases.

### Actors and Authorization *(mandatory for protected behaviour)*

- **AR-001**: Public guests MAY create orders without authentication only
  through the approved public create endpoint.
- **AR-002**: Administrators MUST hold `orders.view`, `orders.create`,
  `orders.update`, `orders.delete`, `orders.change-status`, and
  `orders.manage-payment` independently for the corresponding order
  capabilities.
- **AR-003**: Administrators MUST hold `order-items.view`,
  `order-items.create`, `order-items.update`, and `order-items.delete`
  independently for nested item operations.
- **AR-004**: Administrators MUST hold `order-item-attachments.create` and
  `order-item-attachments.delete` independently for nested attachment mutation
  routes.
- **AR-005**: Protected attachment download MUST require authenticated active
  administrator access plus `orders.view` and strict nested ownership
  verification.
- **AR-006**: The system MUST return the approved unauthenticated `401`,
  forbidden `403`, and nested-resource non-disclosure `404` outcomes according
  to the shared authorization rules.
- **AR-007**: Admin Create Order MUST always require `orders.create` and
  `order-items.create`. When any initial item attachment is submitted, it MUST
  additionally require `order-item-attachments.create`. Missing any required
  nested permission MUST reject the whole atomic create operation.

### Trust, Security, and Content Boundaries *(mandatory)*

- **TR-001**: The backend MUST revalidate and reload every submitted service,
  pricing option, pricing option value, order field, quantity, and attachment
  context instead of trusting client-side state.
- **TR-002**: Customer, order, item, and answer text fields MUST remain plain
  text only and MUST NOT introduce trusted HTML or Markdown rendering.
- **TR-003**: The backend MUST treat idempotency keys, normalized phone values,
  order-number allocation, snapshot fields, and storage paths as backend-owned
  values that clients cannot authoritatively control.
- **TR-004**: Upload validation MUST enforce success, count, size, extension,
  MIME type, operation state, and nested order-item ownership before any file is
  accepted.
- **TR-005**: Failed transactional workflows that wrote files before failure
  MUST delete only the newly written files for that failed attempt, while
  committed delete workflows keep the database mutation authoritative and log
  any post-commit physical cleanup failure safely.
- **TR-006**: Public responses MUST NOT expose attachment storage paths,
  attachment URLs, or internal storage metadata.
- **TR-007**: Admin responses MUST expose only approved safe attachment
  metadata and protected download endpoints, never raw filesystem paths.

### Data Integrity and Concurrency *(mandatory when data changes)*

- **DI-001**: The data model MUST preserve unique order numbers, atomic
  idempotency reservation, valid foreign keys, immutable snapshots, and the
  invariant that an order cannot exist without at least one item.
- **DI-002**: Public order create, admin order create, item replacement,
  payment updates, status changes, and eligible hard deletes MUST execute
  inside explicit transaction boundaries.
- **DI-003**: UTC daily order-number allocation for
  `ORD-YYYYMMDD-####` and idempotency reservation MUST use relational locking,
  unique constraints, and deterministic retry handling that prevent duplicate
  committed results and never reuse an allocated number.
- **DI-004**: Public idempotency reservations MUST store `reserved_at`, allow
  nullable `order_id` only during the owning create transaction, set
  `completed_at` together with `order_id`, and prevent any committed partial
  reservation row.
- **DI-005**: Updating or deleting current customers, addresses, services,
  options, values, or order fields later MUST NOT rewrite historical order
  snapshots.
- **DI-006**: Failed transactional workflows that wrote files before failure
  MUST compensate by deleting newly created files so the database and
  filesystem remain consistent.

### API Contract and Localization *(mandatory for API behaviour)*

- **API-001**: The feature MUST use only the approved route areas
  `/api/v1/public/*` and `/api/v1/admin/*`, the shared JSON success and error
  envelopes, camelCase payload keys, and explicit allow-lists for filters,
  sorts, and pagination.
- **API-002**: The Admin index MUST support only
  `filter[search]`, `filter[status]`, `filter[paymentStatus]`,
  `filter[orderPlace]`, `filter[customerId]`, `filter[serviceId]`,
  `filter[createdFrom]`, `filter[createdTo]`, `filter[totalFrom]`,
  `filter[totalTo]`, `sort=createdAt`, `sort=-createdAt`, `sort=total`, and
  `sort=-total`.
- **API-003**: External domain enums MUST use the approved integer contract and
  MUST be documented as:
  `status 0..4`, `paymentStatus 0..2`, `discountType 0..1`,
  `orderPlace 0..1`, service `priceType 0..1`, pricing `inputType 0..3`, and
  order-field `fieldType = 0`.
- **API-004**: Order numbers MUST follow the approved
  `ORD-YYYYMMDD-####` UTC daily-sequence contract.
- **API-005**: `PATCH /api/v1/admin/orders/{order}` with `status` and
  `PATCH /api/v1/admin/orders/{order}/status` MUST share one transition
  contract; payment MUST remain isolated under the Payment endpoints.
- **API-006**: Public Create and Admin Create MUST always use
  `multipart/form-data` with bracket notation, whether or not files are
  included. Ordinary update endpoints MUST use JSON, attachment upload MUST use
  multipart form-data, and protected attachment download MUST return a file
  response rather than a public URL.
- **API-007**: OpenAPI and Postman MUST describe all request and response keys,
  enum meanings, allowed status transitions, permissions, editable-state
  rules, recalculation behavior, and important error outcomes.
- **LOC-001**: Public and Admin user-facing success and error messages MUST be
  localized to Arabic and English through the approved locale-resolution
  workflow.
- **LOC-002**: Order detail and localized snapshot projection MUST respond to
  `Accept-Language` and return the approved `Content-Language` and
  `Vary: Accept-Language` metadata.
- **LOC-003**: Stable machine identifiers such as JSON keys, route paths,
  permission names, integer enum meanings, and error codes MUST remain stable
  regardless of locale.

### Verification Requirements *(mandatory)*

- **VR-001**: API Feature Tests MUST cover Public Create success, customer
  resolution, address validation, 1-to-50 item bounds, service eligibility,
  option and answer validation, backend price authority, localization,
  snapshots, attachments, rate limiting, idempotent replay, and conflicting key
  reuse.
- **VR-002**: API Feature Tests MUST cover Admin Create requiring exactly one
  customer source, Admin PATCH omitting customer keys when unchanged, customer
  and address ambiguity rejection, discounts, status through both approved
  endpoints, conditional nested Create permissions, payment updates,
  conditional hard delete, nested item CRUD, and strict nested ownership.
- **VR-003**: Status tests MUST cover every allowed transition, every skipped or
  backward transition, cancellation from each non-cancelled status, required
  reason, cancelled terminal behavior, and available-transition projection.
- **VR-004**: Payment tests MUST cover unpaid, partially paid, paid, paid above
  total with a negative remaining amount, correction downward, and updates
  after completed and cancelled states.
- **VR-005**: Item tests MUST cover quantity-only snapshot pricing, option full
  replacement against current pricing configuration, answer full replacement
  against stored question snapshots, answered-question-only persistence,
  required snapshotted questions, immutable service identity, final-item
  deletion rejection, and financial recalculation.
- **VR-006**: File tests MUST prove extension, MIME, size, and combined-count
  enforcement; protected storage; absence of public URLs and raw paths;
  upload/delete editability; all-status protected download; strict ownership;
  physical deletion; and compensation after failure.
- **VR-007**: Real MySQL concurrency tests MUST cover UTC daily order-number
  allocation, concurrent identical and conflicting idempotency submissions,
  competing final-item deletions, status-versus-item mutation, payment updates,
  recalculation, and hard-delete eligibility races.
- **VR-008**: OpenAPI validation MUST verify the integer enum mappings, request
  schemas, response schemas, operation IDs, and protected attachment contract.
- **VR-009**: Postman review MUST verify that every key is explained and that
  enum, status-transition, payment, discount, validation, authorization,
  not-found, and conflict examples are present.
- **VR-010**: Feature completion MUST include passing Pest tests on MySQL,
  static analysis, formatting checks, API contract validation, and updated
  Postman documentation.

### Key Entities *(include if feature involves data)*

- **Order**: A guest- or admin-created service order that stores customer and
  address snapshots, workflow status, order place, notes, discount summary,
  cumulative payment summary, and immutable references to its order items.
- **Order Item**: One independently configurable service occurrence inside an
  order that stores service snapshot data, quantity, calculated unit price,
  item total, optional item note, selected-option snapshots, answer snapshots,
  and attachments.
- **Order Idempotency Reservation**: A persisted record that binds one public
  idempotency key to one canonical request fingerprint and one created order so
  duplicate guest submissions are safely resolved.
- **Order Attachment**: A nested order-item file record that preserves safe
  metadata, storage location, and protected retrieval semantics for authorized
  administration use.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Authorized administrators can complete the full review lifecycle
  for an order—create or receive, inspect, update, manage status, manage paid
  amount, and manage items—through documented APIs without relying on direct
  database edits.
- **SC-002**: Repeating the same public order submission with the same
  idempotency key and same canonical request creates no duplicate order and
  returns one consistent result.
- **SC-003**: Historical order detail remains readable and semantically correct
  after related customer, address, service, option, or answer definitions are
  later edited, deactivated, or deleted.
- **SC-004**: The admin order index supports operational review through the
  approved filters, sorts, and paginated response contract for at least the
  documented first 100 results per page.
- **SC-005**: 100% of documented Postman operations describe every request,
  response, header, path, query, and multipart key, and explain every integer
  enum meaning, valid status transition, permission requirement, and important
  error outcome without requiring backend source-code inspection.
- **SC-006**: Concurrent Public Create requests produce no duplicate completed
  orders, no duplicate UTC daily order numbers, and no duplicate result for one
  idempotency key.
- **SC-007**: Order-item attachments remain inaccessible through public URLs,
  while authorized Admin users can download them through the protected nested
  endpoint in every order status.

## Assumptions

- The implementation remains within the existing Laravel API monolith and
  versioned `/api/v1` route structure.
- Public ordering remains guest-only and does not introduce customer login or a
  customer portal.
- UTC is the canonical timezone for order-number dates and persisted API
  timestamps; clients may localize timestamps for display.
- Feature 002 customer and address services remain reusable after adopting the
  final `province`, `city`, and `address` shape.
- Integer enum contracts, UTC daily order numbering, protected order
  attachments, and the final address shape are approved Feature 005 decisions.
- Any older contradictory wording in shared project documentation is a
  documentation-sync task during planning, not a blocker to `/speckit.plan`.
