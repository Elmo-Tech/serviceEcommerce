# Implementation Plan: Services Catalog

**Branch**: `004-services-catalog`  
**Date**: 2026-07-30  
**Status**: Ready for Task Generation  
**Specification**: [spec.md](./spec.md)  
**Approved reference**: `docs/features/004-services-catalog.md`

## 1. Summary

Feature 004 introduces the complete services catalogue for the Laravel 13 API:

- Core service CRUD, soft delete, and restore.
- Optional category-only or category-plus-subcategory assignment.
- Bilingual service content, slugs, production time, and SEO.
- Fixed and start-from pricing.
- Independent APIs for specifications, order fields, pricing options, and media.
- Transactional pricing-option value actions through the parent option update.
- Transactional multipart service creation with nested records and files.
- Public localized service list and detail endpoints.
- Real service dependency guards for Feature 003 category/subcategory deletion.
- MySQL-backed concurrency protection for classification, slug uniqueness, main media, and video uniqueness.

The implementation remains inside the existing conventional Laravel monolith. Controllers stay thin; Form Requests own transport validation; Resources own response projection; Query classes own list behavior; Actions own multi-record, locking, transaction, or filesystem workflows.

## 2. Technical Context

| Area | Decision |
|---|---|
| Runtime | PHP 8.3, Laravel 13 |
| Database | MySQL, real MySQL test database; no SQLite assumptions |
| Authentication | Sanctum Bearer access tokens for Admin APIs |
| Authorization | Spatie Permission with exact action permissions |
| Query contract | `spatie/laravel-query-builder` allow-lists |
| Storage | Existing configurable public filesystem disk |
| Testing | Pest, Pint, Larastan/PHPStan, real MySQL concurrency tests |
| Deployment | Hostinger-compatible PHP/MySQL; no worker, Redis, scheduler, Docker, or shell-runtime dependency added |
| API format | Shared success/error envelope, camelCase payload keys, existing `StatusCode` enum |

## 3. Scope and Contract Freeze

The plan implements exactly:

- **26 protected Admin operations**.
- **2 unauthenticated Public operations**.
- Integer API/database enums:
  - `priceType`: `0=fixed`, `1=start_from`.
  - pricing `inputType`: `0=select`, `1=multi_select`, `2=radio`, `3=checkbox`.
  - media `type`: `0=image`, `1=video`.
  - internal order-field type: `0=text`.
  - internal pricing-option type: `0=add_on`.
- `basePrice > 0`.
- `priceAdjustment >= 0`.
- Optional-by-default `isAttachmentRequired` is persisted per service,
  accepted by Admin create/update, exposed by Admin/Public resources, and
  enforced by Feature 005 whenever a new order item is created.
- Full Arabic/English descriptions are nullable and optional as a pair; active
  services require bilingual names and short descriptions but not full descriptions.
- No quote-required pricing, negative adjustments, replacement pricing, media reorder, child restore, public sorting, order submission, or customer-answer storage.

The OpenAPI contract in [contracts/openapi.yaml](./contracts/openapi.yaml) is the implementation source for paths, request shapes, response schemas, security, permissions, headers, filters, and errors.

## 4. Constitution and Governance Gate

### 4.1 Pre-design result

All governing sources were reviewed. Feature 004 follows the approved synchronized classification model:

```text
no category
category only
category + matching subcategory
```

No unresolved conflict remains.

### 4.2 Post-design result

The corrected artifacts preserve:

- Existing Laravel folder boundaries.
- Existing authentication and middleware order.
- Existing shared API envelope and `StatusCode` enum.
- Existing public-storage decision.
- Existing localization rules.
- Exact permission namespaces.
- Real MySQL testing and lock-order requirements.

No exception or new infrastructure dependency is introduced.

## 5. Source Structure

```text
app/
├── Actions/
│   ├── Categories/
│   │   ├── DeleteCategoryAction.php
│   │   └── DeleteSubcategoryAction.php
│   └── Services/
│       ├── CreateServiceAction.php
│       ├── UpdateServiceClassificationAction.php
│       ├── RestoreServiceAction.php
│       ├── UpdateServicePricingOptionAction.php
│       ├── DeleteServicePricingOptionAction.php
│       ├── UploadServiceMediaAction.php
│       ├── SetServiceMainMediaAction.php
│       └── DeleteServiceMediaAction.php
├── Enums/Services/
│   ├── ServicePriceType.php
│   ├── ServicePricingInputType.php
│   ├── ServicePricingOptionType.php
│   ├── ServiceOrderFieldType.php
│   └── ServiceMediaType.php
├── Http/
│   ├── Controllers/Api/V1/Admin/Services/
│   ├── Controllers/Api/V1/Public/Services/
│   ├── Requests/Api/V1/Admin/Services/
│   └── Resources/Api/V1/
│       ├── Admin/Services/
│       └── Public/Services/
├── Models/
│   ├── Service.php
│   ├── ServiceSlugReservation.php
│   ├── ServiceSpecification.php
│   ├── ServiceOrderField.php
│   ├── ServicePricingOption.php
│   ├── ServicePricingOptionValue.php
│   └── ServiceMedia.php
├── Queries/Services/
│   ├── AdminServiceIndexQuery.php
│   └── PublicServiceIndexQuery.php
└── Services/
    ├── Files/
    └── Services/
        ├── ServiceSlugRegistry.php
        ├── ServiceMediaStore.php
        └── ServiceActivationValidator.php

database/
├── factories/
├── migrations/
└── seeders/

routes/api/v1/
├── admin.php
└── public.php

tests/
├── Architecture/
├── Concurrency/Services/
├── Feature/Api/V1/Admin/Services/
├── Feature/Api/V1/Public/Services/
└── Feature/Database/Services/
```

Simple single-row CRUD must not receive an Action unless orchestration, locking, compensation, or reuse justifies it.

## 6. Database Design

The canonical model is detailed in [data-model.md](./data-model.md).

### 6.1 Tables

```text
services
service_slug_reservations
service_specifications
service_order_fields
service_pricing_options
service_pricing_option_values
service_media
```

`services.name_ar` and `services.name_en` have direct unique indexes so Arabic and English service names cannot be reused, including by soft-deleted services. `service_slug_reservations` is an internal integrity table. It is not an API resource. It provides race-safe cross-locale global slug uniqueness because independent unique indexes on `slug_ar` and `slug_en` cannot prevent `service A.slug_ar = service B.slug_en`. Arabic slug normalization preserves Arabic/Unicode letters and only normalizes separators.

### 6.2 Foreign-key behavior

All service catalogue foreign keys use explicit `RESTRICT` behavior for physical deletion. Feature APIs use soft delete for services and configuration children; no force-delete API is added. Media is hard deleted explicitly before its row disappears.

### 6.3 Initial query indexes

The migration adds only indexes tied to approved queries:

- Public active list and deterministic creation order.
- Admin trashed/status/type filters.
- Category and subcategory filters.
- Base-price range and sorting.
- Parent-scoped child ordering.
- Parent-scoped active pricing values.
- Parent-scoped media type/main lookup.

The exact index names and column order are defined in `data-model.md`. Implementation must run `EXPLAIN` against representative list queries before considering additional indexes.

## 7. Cross-Locale Slug Integrity

### 7.1 Registry design

`service_slug_reservations` contains:

```text
id
service_id
slug
created_at
updated_at
```

Constraints:

```text
UNIQUE(slug)
INDEX(service_id)
```

The table stores the unique set of the service's normalized Arabic and English slugs. If both locale slugs normalize to the same value, one reservation row is sufficient.

### 7.2 Create/update algorithm

Inside the service transaction:

1. Normalize requested slugs.
2. Validate length and reserved-route rules.
3. Lock the service's current reservation rows ordered by `slug ASC` when updating.
4. Remove reservations no longer used by that service.
5. Insert missing normalized reservations.
6. Rely on the unique index as final race protection.
7. Persist `services.slug_ar` and `services.slug_en` in the same transaction.

A duplicate-key exception is converted into `422 VALIDATION_ERROR` on the matching slug field. Soft-deleting a service does not delete reservations.

## 8. Transaction and Locking Design

### 8.1 Universal hierarchy lock order

All hierarchy-sensitive operations use:

```text
affected root categories by id ASC
→ affected subcategories by id ASC
→ affected services by id ASC
→ revalidate
→ mutate
→ commit
```

For service update/restore, the action first reads a non-locking snapshot to determine current and target hierarchy IDs, then locks the sorted affected sets, locks the service, and revalidates. If the locked state introduces an unanticipated hierarchy ID, the action rolls back and retries a bounded number of times rather than violating the lock order.

### 8.2 Category/subcategory deletion

Category and subcategory delete Actions are updated to:

1. Lock the root category.
2. Lock the target subcategory when applicable.
3. Lock blocking service rows by `id ASC`.
4. Revalidate non-deleted dependencies.
5. Return `CATEGORY_HAS_SERVICES` or `SUBCATEGORY_HAS_SERVICES` when blocked.
6. Perform the existing Feature 003 mutation only after revalidation.

### 8.3 Media mutations

Every media write locks the parent service first, then relevant media rows by `id ASC`.

This serializes per-service media writes and guarantees:

- At most ten images.
- At most one video.
- At most one main image.
- Video never becomes main.
- Competing set-main requests cannot commit two main images.
- Competing video uploads cannot commit two videos.

The application lock is mandatory. A generated-column unique guard may be added only after target MySQL support is verified; it is not required by this plan.

### 8.4 Pricing-option values

`PATCH pricing-option` locks:

```text
service
→ pricing option
→ referenced existing values by id ASC
```

Then it validates duplicate IDs, conflicting actions, ownership, limits, and active-value activation rules before applying all actions atomically.

## 9. Filesystem Lifecycle

### 9.1 Atomic create compensation

Database transactions cannot roll back filesystem writes. `CreateServiceAction` therefore maintains an in-memory list of files created during the request.

Flow:

1. Validate the full request and all files before starting persistence when possible.
2. Begin the database transaction.
3. Create service and nested records.
4. Store files with backend-generated names and record every written path.
5. Create media rows.
6. Commit.
7. If any exception occurs before commit, roll back and delete every path written by that request.

### 9.2 Delete and replacement ordering

- Media delete first completes the locked database mutation, commits, then deletes the physical file.
- When deleting a main image, fallback-main selection happens inside the transaction before commit.
- If post-commit physical deletion fails, keep the committed database result, log a safe orphan candidate with request correlation, and return the response defined for the committed command. Do not recreate the deleted row and do not hide the cleanup failure from operational logs.
- Alt-text update never replaces a file.
- Uploading a second video is rejected; the old video is never silently replaced.

## 10. API and Query Design

### 10.1 Admin list

Use `AdminServiceIndexQuery` with exact allow-lists:

```text
filter[search]
filter[categoryId]
filter[subcategoryId]
filter[priceType]
filter[isActive]
filter[isAvailable]
filter[trashed]

sort=basePrice|-basePrice|createdAt|-createdAt
```

Admin search targets both locales. Unsupported filters/sorts are rejected by the established Query Builder behavior.

### 10.2 Public list

Use `PublicServiceIndexQuery` with:

```text
filter[search]
filter[category]
filter[subcategory]
filter[priceFrom]
filter[priceTo]
filter[isAvailable]
```

Public search targets Arabic and English text. Response projection follows `Accept-Language`. There is no public `sort`; internal order is `created_at DESC, id DESC`.

### 10.3 Localized slug fallback

Public service detail and category/subcategory filters:

1. Resolve request locale.
2. Match that locale slug column.
3. Fall back to the alternate locale slug column.
4. Keep response localization unchanged.

Inactive/deleted category filter matches return an empty list, not a hierarchy disclosure error.

### 10.4 Response projection

- Admin index: resolved locale plus operational fields.
- Admin show/create/update: both locales and all active/non-deleted children.
- Public list: localized summary only.
- Public detail: localized content, active children, all media, operational integer enums and booleans.
- Money responses: fixed-precision decimal strings.
- Public 200 responses: `Content-Language` and `Vary: Accept-Language`.

## 11. Multipart Create Parsing

`POST /admin/services` accepts only nested multipart bracket notation. No JSON `payload` field is accepted.

Laravel receives bracketed fields as nested arrays. Files remain `UploadedFile` instances at paths such as:

```text
media.0.file
```

The Form Request prepares booleans and integer enum values without mutating uploaded files. Nested child permissions are checked after structural validation and before persistence.

## 12. Permission Mapping

| Operation family | View | Create | Update | Delete | Other |
|---|---|---|---|---|---|
| Services | `services.view` | `services.create` | `services.update` | `services.delete` | `services.restore` |
| Specifications | `service-specifications.view` | `service-specifications.create` | `service-specifications.update` | `service-specifications.delete` | — |
| Order fields | `service-order-fields.view` | `service-order-fields.create` | `service-order-fields.update` | `service-order-fields.delete` | — |
| Pricing options | `service-pricing-options.view` | `service-pricing-options.create` | `service-pricing-options.update` | `service-pricing-options.delete` | Values inherit parent operation permission |
| Media | `service-media.view` | `service-media.create` | `service-media.update` | `service-media.delete` | `service-media.set-main` |

Nested content submitted during Create Service requires `services.create` plus the relevant nested create permission.

Middleware order remains:

```text
auth:sanctum
→ EnsureUserIsAdministrator
→ EnsureAdminIsActive
→ permission
→ endpoint
```

## 13. OpenAPI and Existing Feature 003 Contract

The corrected OpenAPI:

- Defines `bearerAuth` and applies it to all Admin operations.
- Defines all 28 unique operation IDs.
- Defines request and response schemas.
- Defines shared success/error envelopes.
- Defines Admin permissions through `x-permission`.
- Defines public language headers.
- Uses OpenAPI 3.1 nullable unions.

Feature 003 contract artifacts must also be updated so existing category and subcategory DELETE operations document:

```text
409 CATEGORY_HAS_SERVICES
409 SUBCATEGORY_HAS_SERVICES
```

No additional Feature 004 route is created for those guards.

## 14. Verification Strategy

### 14.1 Feature tests

Cover every operation for:

- Success.
- Validation.
- `401` unauthenticated.
- `403` permission/inactive Admin.
- Parent-scoped non-disclosing `404`.
- Relevant `409` conflicts.
- Localization and exact response projection.
- Unsupported filters/sorts.

### 14.2 Database tests

Cover:

- Cross-locale slug reservation uniqueness.
- Slug retention after soft delete.
- Foreign-key restrictions.
- Child soft-delete visibility.
- Decimal casts and integer enum casts.
- Required indexes through migration assertions where useful.

### 14.3 File tests

Cover MIME/extension/size/count limits, compensation, hard deletion, first-main selection, competing main selection, and video uniqueness.

### 14.4 Real MySQL concurrency tests

Mandatory races:

- Category delete vs direct service create.
- Subcategory delete vs classified service create.
- Category delete vs service move.
- Subcategory delete vs service move.
- Restore vs category/subcategory delete.
- Competing cross-locale slug reservations.
- Competing set-main requests.
- Competing video uploads.

### 14.5 Architecture tests

Assert:

- Exactly 26 Admin and 2 Public operations.
- Numeric route constraints.
- No pricing-option-value routes.
- No public writes or public sort.
- Exact middleware and permission map.

## 15. Documentation and Quality Gates

Before implementation acceptance:

- Synchronize OpenAPI and Postman.
- Synchronize Feature 003 deletion conflict responses.
- Run focused Pest suites.
- Run full Pest suite on MySQL.
- Run Pint.
- Run Larastan/PHPStan.
- Confirm route inventory against OpenAPI operation IDs.

## 16. Complexity Tracking

| Added complexity | Why it is required | Mitigation |
|---|---|---|
| `service_slug_reservations` internal table | Independent locale-column indexes cannot guarantee cross-locale uniqueness under races | Small internal table, one unique index, transactionally synchronized, no API surface |
| Lock-aware Actions | Hierarchy and media invariants cross rows and compete under concurrency | Exact universal lock orders and focused real-MySQL tests |
| Filesystem compensation | Database rollback cannot remove already-written files | Track only request-created files and delete them on failure |

No generic repository, event bus, queue, cache layer, or speculative abstraction is introduced.
