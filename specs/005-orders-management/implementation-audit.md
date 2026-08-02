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
