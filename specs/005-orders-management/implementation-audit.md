# Feature 005 Implementation Audit

**Feature:** `005-orders-management`  
**Branch:** `005-orders-management`  
**Audit date:** 2026-08-01  
**Status:** Phase 1 setup audit completed

## 1. Repository Baseline

| Area | Finding | Decision |
|---|---|---|
| Route mounting | `routes/api.php` already mounts `/api/v1/admin/auth`, `/api/v1/admin`, and `/api/v1/public` | Reuse |
| Admin middleware | `routes/api/v1/admin.php` already applies `admin.auth.headers`, `auth:sanctum`, `admin.user_type`, and `admin.active` in the approved order | Reuse |
| Public routes | `routes/api/v1/public.php` already holds public catalogue endpoints under the approved versioned group | Reuse |
| API envelope | Shared API response helpers already exist and must remain authoritative for success and error envelopes | Reuse |
| Application-controlled HTTP status enum | Shared enum is `App\Enums\HttpStatusCode`, not `App\Enums\StatusCode` | Reuse and correct documentation drift |
| Customer domain reuse | Existing customer matching, phone normalization, and address services already provide the reuse baseline for Feature 005 | Reuse |
| Service domain reuse | Existing service, pricing option, pricing option value, specification, and order-field models provide the live catalogue baseline | Reuse |
| Test harness | Pest, dedicated MySQL test database, and feature/concurrency suite structure already exist | Reuse |
| File storage patterns | Existing repository standards require validated uploads, safe filenames, and compensation-aware workflows | Reuse and extend |
| Feature documents | Feature 005 plan, research, data model, quickstart, and OpenAPI already capture the latest attachment/idempotency/answer-update constraints | Reuse |
| Feature docs drift | `docs/features/005-orders-management.md`, `spec.md`, `plan.md`, `tasks.md`, and checklist wording still had partial stale `StatusCode` or missing-sync wording | Update |

## 2. Exact Version Snapshot

| Package / Tool | Verified version |
|---|---|
| PHP runtime target | 8.3 |
| `laravel/framework` | `v13.23.0` |
| `laravel/sanctum` | `v4.3.3` |
| `spatie/laravel-permission` | `8.3.0` |
| `spatie/laravel-query-builder` | `7.3.0` |
| `pestphp/pest` | `v4.7.5` |
| `larastan/larastan` | `v3.10.0` |
| `laravel/pint` | `v1.29.3` |

## 3. Route and Middleware Baseline

### Admin area

- Mounted from `routes/api.php` into `routes/api/v1/admin.php`
- Existing middleware order:
  1. `admin.auth.headers`
  2. `auth:sanctum`
  3. `admin.user_type`
  4. `admin.active`

### Public area

- Mounted from `routes/api.php` into `routes/api/v1/public.php`
- Existing public catalogue routes confirm the approved `/api/v1/public/*` boundary

### Feature 005 route ownership map

| Route area | Planned controller ownership |
|---|---|
| `GET/POST/PATCH/DELETE /api/v1/admin/orders*` | `app/Http/Controllers/Api/V1/Admin/Orders/OrderController.php` |
| `PATCH /api/v1/admin/orders/{order}/status` | `app/Http/Controllers/Api/V1/Admin/Orders/OrderStatusController.php` |
| `GET/PATCH /api/v1/admin/orders/{order}/payment` | `app/Http/Controllers/Api/V1/Admin/Orders/OrderPaymentController.php` |
| `GET/POST/PATCH/DELETE /api/v1/admin/orders/{order}/items*` | `app/Http/Controllers/Api/V1/Admin/Orders/OrderItemController.php` |
| `POST/DELETE/GET download /api/v1/admin/orders/{order}/items/{orderItem}/attachments*` | `app/Http/Controllers/Api/V1/Admin/Orders/OrderItemAttachmentController.php` |
| `POST /api/v1/public/orders` | `app/Http/Controllers/Api/V1/Public/Orders/OrderController.php` |

## 4. Existing Domain Assets to Reuse

### Customer and address assets

- `app/Services/Customers/AddressNormalizationService.php`
- `app/Services/Customers/CustomerAddressService.php`
- `app/Services/Customers/CustomerMatchingService.php`
- `app/Services/Customers/PhoneNumberService.php`
- `app/Actions/Customers/ResolveGuestCustomerAction.php`
- `app/Models/Customer.php`
- `app/Models/CustomerAddress.php`

### Service and catalogue assets

- `app/Models/Service.php`
- `app/Models/ServiceMedia.php`
- `app/Models/ServiceOrderField.php`
- `app/Models/ServicePricingOption.php`
- `app/Models/ServicePricingOptionValue.php`
- `app/Models/ServiceSpecification.php`
- `app/Models/ServiceSlugReservation.php`

### Testing and configuration assets

- `tests/Pest.php`
- `phpunit.xml`
- `.env.example`
- existing admin/public API test directory structure

## 5. Documentation Drift Identified and Synchronized

The following constraints are approved and now treated as the current Feature 005 implementation baseline:

- Public/Admin create requests support at most **30 attachment files** and **100 MB combined**
- Standalone Admin attachment upload supports at most **3 files** and **30 MB combined**
- Daily sequence exhaustion returns `ORDER_NUMBER_SEQUENCE_EXHAUSTED`
- Public idempotency reservations use `reserved_at`, nullable `order_id` only inside the owning transaction, and `completed_at`
- Existing item answer replacement uses `answers[].orderItemAnswerId`
- Application-controlled HTTP statuses use `App\Enums\HttpStatusCode`

Repository search on 2026-08-01 confirmed the remaining active Feature 005 drift was limited to stale `StatusCode` wording and missing synchronization inside the feature docs/tasks layer, which this audit batch resolves.

## 6. Worktree Preservation Notes

Existing user changes were preserved during this audit, including:

- `.specify/feature.json`
- `docs/features/005-orders-management.md`
- `specs/005-orders-management/`

No unrelated repository structure was replaced or reset.

## 7. Query Performance Verification (T055)

### 7.1 Bounded query-count verification

The following Feature 005 read surfaces now have explicit bounded-query
coverage in
`tests/Feature/Api/V1/Admin/Orders/AdminOrderQueryPerformanceTest.php`:

| Surface | Verified bound | Result |
|---|---:|---|
| `GET /api/v1/admin/orders` | `<= 5` `SELECT` queries | PASS |
| `GET /api/v1/admin/orders/{order}` | `<= 8` `SELECT` queries | PASS |
| `GET /api/v1/admin/orders/{order}/items` | `<= 7` `SELECT` queries | PASS |
| `GET /api/v1/admin/orders/{order}/items/{orderItem}` | `<= 7` `SELECT` queries | PASS |

Corrective change applied during this verification:

- `app/Http/Resources/Api/V1/Admin/Orders/AdminOrderAttachmentResource.php`
  no longer dereferences `orderItem` per attachment just to build the protected
  download endpoint. It now uses the already-known route order identifier plus
  `order_item_id`, removing a nested attachment metadata N+1 risk.

Additional query-shape correction:

- `app/Queries/Orders/AdminOrderIndexQuery.php` no longer pre-applies
  `orderedLatest()` before Spatie Query Builder sorting. This restored correct
  `sort=createdAt` and `sort=total` behavior while keeping the documented
  default `-createdAt` sort.

### 7.2 EXPLAIN results captured on August 2, 2026

Representative `EXPLAIN` runs were executed against the local Laravel
application database after adding the corrective index migration
`2026_08_02_120000_add_orders_created_at_id_index.php`.

| Query shape | Selected key | Access type | Estimated rows | Notes |
|---|---|---|---:|---|
| `filter[status]` + default sort | `orders_status_created_id_idx` | `ref` | `1` | Expected composite index usage |
| `filter[paymentStatus]` + default sort | `orders_payment_status_created_id_idx` | `ref` | `1` | Expected composite index usage |
| `filter[orderPlace]` + default sort | `orders_place_created_id_idx` | `ref` | `1` | Expected composite index usage |
| `filter[customerId]` + default sort | `orders_customer_created_id_idx` | `ref` | `1` | Expected composite index usage |
| `filter[createdFrom]` + `filter[createdTo]` + default sort | `orders_created_id_idx` | `range` | `1` | Fixed from prior full scan + filesort |
| `filter[totalFrom]` + `filter[totalTo]` + default sort | `orders_created_id_idx` | `index` | `1` | MySQL preferred the new order-supporting index for bounded sorted reads |
| `filter[search]` + default sort | `orders_created_id_idx` | `index` | `1` | Wildcard `%term%` predicates remain non-sargable; bounded scan accepted for MVP |
| `filter[serviceId]` + default sort | outer `orders` scan + inner `order_items_service_order_idx` | `ALL` + `ref` | `1` / `1` | Inner materialized lookup is indexed; outer ordering remains bounded by pagination |

### 7.3 Corrective index change

One additional production-safe migration was required:

- `database/migrations/2026_08_02_120000_add_orders_created_at_id_index.php`

This migration adds:

- `orders_created_id_idx` on `orders(created_at, id)`

Reason:

- the approved `createdFrom/createdTo` admin index query shape previously
  produced `type=ALL` with `Using filesort`
- the new composite index converted that shape to `type=range` and removed the
  filesort in observed `EXPLAIN` output

### 7.4 Remaining accepted tradeoffs

- `filter[search]` intentionally uses `%...%` wildcard matching across
  `order_number`, `customer_name`, `customer_phone`, and `customer_email`.
  Under the approved MVP contract this remains a bounded, paginated scan rather
  than a full-text search feature.
- `filter[serviceId]` currently benefits from the
  `order_items_service_order_idx` lookup on the nested table. The outer orders
  query remains acceptable for current bounded admin pagination and did not
  justify a broader query rewrite during this task.

## 8. Security, Authorization, Localization, and HttpStatusCode Audit (T057)

Manual verification was completed on Sunday, August 2, 2026 across:

- `app/Actions/Orders/`
- `app/Http/Controllers/Api/V1/Admin/Orders/`
- `app/Http/Controllers/Api/V1/Public/Orders/`
- `app/Http/Resources/Api/V1/`

### 8.1 Authorization and nested ownership

- Admin order routes remain protected by explicit Spatie permission middleware
  in `routes/api/v1/admin.php`
- Public order creation remains limited to the one approved unauthenticated
  endpoint
- Nested item and attachment ownership remains centralized in
  `App\Services\Orders\OrderNestedResourceResolver`
- Protected attachment download remains authenticated Admin-only and does not
  expose a public URL

### 8.2 Attachment boundary verification

- Public order responses expose only the approved summary resource and do not
  include attachment storage metadata
- Admin order resources expose only safe attachment metadata:
  `id`, `originalName`, `mimeType`, `extension`, `sizeBytes`, `downloadEndpoint`,
  and timestamps
- No raw attachment filesystem path is serialized from the orders resources
- Attachment storage paths remain internal to actions and storage services

### 8.3 Localization and response metadata

- Admin and Public order controllers return JSON through
  `ApiResponse::withAuthenticationHeaders(...)`
- This preserves `Content-Language` and `Vary: Accept-Language` behavior for
  localized responses
- Localized snapshot projection remains handled in the order/item resources
  without changing stable machine keys

### 8.4 HttpStatusCode enum verification

- Orders actions and controllers use `App\Enums\HttpStatusCode` for
  application-controlled status selection
- Direct JSON responses in order controllers continue to pass
  `HttpStatusCode::OK->value` explicitly where the shared response helper is
  not used
- No order-domain controller regression to raw magic status integers was found

### 8.5 Audit result

PASS.

No new security, authorization, localization-header, or response-status drift
was found in the Feature 005 Orders surfaces during this audit pass.

## 9. Requirement-to-Test Traceability Matrix (T056)

### 9.1 Functional Requirements

| Requirement | Concrete Pest coverage |
|---|---|
| FR-001 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Public/Orders/PublicOrderIdempotencyTest.php`, `tests/Architecture/PublicOrdersRouteContractTest.php` |
| FR-002 | `tests/Architecture/AdminOrdersRouteContractTest.php`, `tests/Architecture/AdminOrderItemsRouteContractTest.php` |
| FR-003 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php` |
| FR-004 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |
| FR-005 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |
| FR-006 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php` |
| FR-007 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |
| FR-008 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Public/Orders/PublicOrderIdempotencyTest.php`, `tests/Feature/Api/V1/Admin/Customers/CustomerApiTest.php`, `tests/Feature/Api/V1/Admin/Customers/CustomerAddressApiTest.php` |
| FR-009 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Public/Orders/PublicOrderIdempotencyTest.php`, `tests/Feature/Api/V1/Admin/Customers/CustomerApiTest.php` |
| FR-010 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php` |
| FR-011 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderLocalizationTest.php`, `tests/Feature/Api/V1/Public/Services/PublicServiceApiTest.php` |
| FR-012 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Customers/CustomerAddressApiTest.php` |
| FR-013 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php` |
| FR-014 | `tests/Concurrency/Orders/OrderNumberAllocationConcurrencyTest.php`, `tests/Feature/Database/Orders/OrderSchemaTest.php` |
| FR-015 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php` |
| FR-016 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |
| FR-017 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php` |
| FR-018 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |
| FR-019 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |
| FR-020 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php` |
| FR-021 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |
| FR-022 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php` |
| FR-023 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderLocalizationTest.php` |
| FR-024 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |
| FR-025 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemRulesTest.php` |
| FR-026 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemRulesTest.php` |
| FR-027 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemRulesTest.php` |
| FR-028 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemRulesTest.php` |
| FR-029 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php` |
| FR-030 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php` |
| FR-031 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php` |
| FR-032 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php` |
| FR-033 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php` |
| FR-034 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderOwnershipTest.php`, `tests/Architecture/OrdersOpenApiAndPostmanContractTest.php` |
| FR-035 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |
| FR-036 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Public/Orders/PublicOrderIdempotencyTest.php` |
| FR-037 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Public/Orders/PublicOrderIdempotencyTest.php`, `tests/Concurrency/Orders/PublicOrderIdempotencyConcurrencyTest.php` |
| FR-038 | `tests/Feature/Api/V1/Public/Orders/PublicOrderIdempotencyTest.php`, `tests/Concurrency/Orders/PublicOrderIdempotencyConcurrencyTest.php` |
| FR-039 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderIndexQueryTest.php`, `tests/Architecture/AdminOrdersRouteContractTest.php` |
| FR-040 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |
| FR-041 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Architecture/PublicOrdersRouteContractTest.php` |
| FR-042 | `tests/Architecture/OrdersOpenApiAndPostmanContractTest.php`, `tests/Architecture/AdminOrdersRouteContractTest.php`, `tests/Architecture/PublicOrdersRouteContractTest.php` |
| FR-043 | `tests/Architecture/OrdersOpenApiAndPostmanContractTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php` |
| FR-044 | `tests/Architecture/OrdersOpenApiAndPostmanContractTest.php`, `tests/Architecture/IdentityAuthenticationPostmanTest.php` |
| FR-045 | `tests/Architecture/OrdersOpenApiAndPostmanContractTest.php` |
| FR-046 | `tests/Architecture/OrdersOpenApiAndPostmanContractTest.php`, `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |

### 9.2 Authorization and trust requirements

| Requirement | Concrete Pest coverage |
|---|---|
| AR-001 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Architecture/PublicOrdersRouteContractTest.php` |
| AR-002 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php`, `tests/Architecture/AdminOrdersRouteContractTest.php` |
| AR-003 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemRulesTest.php`, `tests/Architecture/AdminOrderItemsRouteContractTest.php` |
| AR-004 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php`, `tests/Architecture/AdminOrderItemsRouteContractTest.php` |
| AR-005 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderOwnershipTest.php` |
| AR-006 | `tests/Feature/Api/V1/Admin/Auth/AuthenticationFoundationTest.php`, `tests/Feature/Api/V1/Admin/Auth/ResponseSafetyTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php` |
| AR-007 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |

| Requirement | Concrete Pest coverage |
|---|---|
| TR-001 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php` |
| TR-002 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php` |
| TR-003 | `tests/Feature/Api/V1/Public/Orders/PublicOrderIdempotencyTest.php`, `tests/Concurrency/Orders/PublicOrderIdempotencyConcurrencyTest.php`, `tests/Concurrency/Orders/OrderNumberAllocationConcurrencyTest.php` |
| TR-004 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php` |
| TR-005 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php`, `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php` |
| TR-006 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderOwnershipTest.php` |
| TR-007 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderOwnershipTest.php` |

### 9.3 Data integrity and API contract requirements

| Requirement | Concrete Pest coverage |
|---|---|
| DI-001 | `tests/Feature/Database/Orders/OrderSchemaTest.php`, `tests/Concurrency/Orders/OrderNumberAllocationConcurrencyTest.php`, `tests/Concurrency/Orders/PublicOrderIdempotencyConcurrencyTest.php` |
| DI-002 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |
| DI-003 | `tests/Concurrency/Orders/OrderNumberAllocationConcurrencyTest.php`, `tests/Concurrency/Orders/PublicOrderIdempotencyConcurrencyTest.php` |
| DI-004 | `tests/Feature/Api/V1/Public/Orders/PublicOrderIdempotencyTest.php`, `tests/Concurrency/Orders/PublicOrderIdempotencyConcurrencyTest.php` |
| DI-005 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemRulesTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderLocalizationTest.php` |
| DI-006 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php`, `tests/Concurrency/Orders/PublicOrderIdempotencyConcurrencyTest.php` |

| Requirement | Concrete Pest coverage |
|---|---|
| API-001 | `tests/Architecture/PublicOrdersRouteContractTest.php`, `tests/Architecture/AdminOrdersRouteContractTest.php`, `tests/Architecture/AdminOrderItemsRouteContractTest.php` |
| API-002 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderIndexQueryTest.php`, `tests/Architecture/AdminOrdersRouteContractTest.php` |
| API-003 | `tests/Architecture/OrdersOpenApiAndPostmanContractTest.php`, `tests/Architecture/AdminOrdersRouteContractTest.php`, `tests/Architecture/PublicOrdersRouteContractTest.php` |
| API-004 | `tests/Concurrency/Orders/OrderNumberAllocationConcurrencyTest.php`, `tests/Feature/Database/Orders/OrderSchemaTest.php` |
| API-005 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php` |
| API-006 | `tests/Architecture/PublicOrdersRouteContractTest.php`, `tests/Architecture/AdminOrdersRouteContractTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php` |
| API-007 | `tests/Architecture/OrdersOpenApiAndPostmanContractTest.php`, `tests/Architecture/PublicOrdersRouteContractTest.php`, `tests/Architecture/AdminOrdersRouteContractTest.php` |

### 9.4 Localization and verification requirements

| Requirement | Concrete Pest coverage |
|---|---|
| LOC-001 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Public/Orders/PublicOrderIdempotencyTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderLocalizationTest.php` |
| LOC-002 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderLocalizationTest.php`, `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php` |
| LOC-003 | `tests/Architecture/AdminOrdersRouteContractTest.php`, `tests/Architecture/PublicOrdersRouteContractTest.php`, `tests/Architecture/OrdersOpenApiAndPostmanContractTest.php` |

| Requirement | Concrete Pest coverage |
|---|---|
| VR-001 | `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`, `tests/Feature/Api/V1/Public/Orders/PublicOrderIdempotencyTest.php` |
| VR-002 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |
| VR-003 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |
| VR-004 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php` |
| VR-005 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemRulesTest.php` |
| VR-006 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderOwnershipTest.php` |
| VR-007 | `tests/Concurrency/Orders/OrderNumberAllocationConcurrencyTest.php`, `tests/Concurrency/Orders/PublicOrderIdempotencyConcurrencyTest.php` |
| VR-008 | `tests/Architecture/OrdersOpenApiAndPostmanContractTest.php` |
| VR-009 | `tests/Architecture/OrdersOpenApiAndPostmanContractTest.php` |
| VR-010 | `tests/Feature/Api/V1/Admin/Orders/AdminOrderIndexQueryTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderLocalizationTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderOwnershipTest.php`, `tests/Feature/Api/V1/Admin/Orders/AdminOrderQueryPerformanceTest.php`, `tests/Architecture/OrdersOpenApiAndPostmanContractTest.php` |

### 9.5 Traceability conclusion

PASS.

No additional non-concurrency Pest files were identified as strictly missing
for the currently approved Feature 005 contract. The remaining open work is
limited to the concurrency suite cluster and final end-to-end scenario
execution.
