# Feature 004 Quickstart — Services Catalog

**Feature:** `004-services-catalog`  
**Branch:** `004-services-catalog`  
**Status:** Implementation Complete Pending Final Repository Gates

## 1. Purpose

This guide defines the executable verification path after implementation. It verifies the approved specification, corrected data model, OpenAPI contract, Postman synchronization, file lifecycle, permissions, and MySQL concurrency invariants.

## 2. Prerequisites

- PHP 8.3 and Composer.
- Laravel 13 application dependencies installed.
- MySQL local and testing databases.
- Feature 001 Admin Bearer authentication operational.
- Feature 003 category/subcategory migrations and seeders applied.
- Public storage configured and storage link created where the deployment requires it.
- An active Admin with the exact Feature 004 permissions.

## 3. Setup

```powershell
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=Database\Seeders\RolesAndPermissionsSeeder
php artisan db:seed --class=Database\Seeders\SuperAdminSeeder
```

Confirm the testing connection points to MySQL, not SQLite.

## 4. Contract References

- [spec.md](./spec.md)
- [plan.md](./plan.md)
- [research.md](./research.md)
- [data-model.md](./data-model.md)
- [contracts/openapi.yaml](./contracts/openapi.yaml)

## 5. Route and Permission Preflight

Run:

```powershell
php artisan route:list --path=api/v1/admin/services
php artisan route:list --path=api/v1/public/services
```

Verify:

- Exactly 26 Admin operations.
- Exactly 2 Public operations.
- Every database ID parameter has a numeric route constraint.
- No pricing-option-value endpoints exist.
- No public write or public sort endpoint exists.
- Middleware order and permission names match OpenAPI `x-permission` values.
- Postman folders `Admin Services` and `Public Services` match the same route inventory.

## 6. Core Validation Scenarios

### A. Classification states

Create:

1. An unclassified service.
2. A category-only service.
3. A category-plus-matching-subcategory service.

Reject:

- Subcategory without category.
- Inactive or deleted category.
- Inactive or deleted subcategory.
- Subcategory from another category.

On category change, verify the old subcategory is cleared atomically.

### B. Pricing

- Create fixed service with `basePrice > 0` and no pricing options.
- Reject fixed service containing pricing options.
- Create start-from service with zero and positive `priceAdjustment` values.
- Reject `basePrice = 0` and negative adjustments.
- Verify `isAttachmentRequired` defaults to false, accepts boolean-like
  multipart strings, and is returned by Admin/Public list and detail resources.
- Create and activate a service without `descriptionAr`/`descriptionEn`, verify
  both response keys are null, and reject submitting only one locale.
- Verify response money values are strings such as `"1000.00"`.

### C. Multipart atomic create

Send `POST /api/v1/admin/services` using only bracket notation:

```text
specifications[0][labelAr]
orderFields[0][labelAr]
pricingOptions[0][values][0][labelAr]
media[0][file]
```

Do not send a JSON `payload` field.

Verify:

- Nested resources require their matching create permissions.
- First image becomes main when no image is explicitly main.
- Two explicit main images return `422`.
- Any failed nested rule rolls back rows and deletes newly uploaded files.

### D. Cross-locale slug uniqueness

Create service A with:

```text
slugAr = kitchen-design
```

Attempt service B with:

```text
slugEn = kitchen-design
```

Expect `422 VALIDATION_ERROR` backed by the reservation unique index.

Soft-delete service A and retry; it must still fail because deleted services retain slug reservations.

Run a real MySQL race with two requests claiming the same slug; exactly one may commit.

### E. Specifications and order fields

For each child API, verify index/show/create/update/delete, exact permission, parent scoping, count limit, deterministic ordering, and exclusion of soft-deleted rows. Child indexes are unpaginated and do not accept `filter[trashed]`.

### F. Pricing-option `actionStatus`

Patch one option with create/update/delete actions in one request. Verify:

- `create` has no ID.
- `update` and `delete` require IDs.
- Omitted or empty action status does nothing.
- Omitted `values` and `values: []` leave all values unchanged.
- Duplicate/conflicting IDs fail atomically.
- Cross-option IDs return a non-disclosing `404` or approved validation outcome.

### G. Media invariants

Verify:

- Image extension/MIME/5 MB limit.
- Video extension/MIME/100 MB limit.
- Maximum ten images.
- Maximum one video.
- Video cannot be main.
- Alt text is an optional complete bilingual pair.
- Alt update does not replace a file.
- Main deletion promotes the oldest remaining image by `id ASC`.
- No remaining image leaves the active service active and without main media.
- Media delete removes the row and physical file.

### H. Restore behavior

Restore a soft-deleted service:

- `isActive` becomes false.
- Deleted category clears category and subcategory.
- Existing category plus deleted subcategory keeps category and clears subcategory.
- Existing child configuration and media remain available.

### I. Admin list

Verify exact filters:

```text
filter[search]
filter[categoryId]
filter[subcategoryId]
filter[priceType]
filter[isActive]
filter[isAvailable]
filter[trashed]
```

Verify exact sorts:

```text
basePrice
-basePrice
createdAt
-createdAt
```

Admin search must match both locales. Unsupported filters and sorts must not be silently accepted.

### J. Public list/detail

Verify only:

```http
GET /api/v1/public/services
GET /api/v1/public/services/{serviceSlug}
```

List filters:

```text
filter[search]
filter[category]
filter[subcategory]
filter[priceFrom]
filter[priceTo]
filter[isAvailable]
```

Verify:

- Active/non-deleted services only.
- Unavailable active services remain visible.
- Search covers Arabic and English.
- Category/subcategory filters use localized slugs with alternate-locale fallback.
- Inactive/deleted hierarchy filters return `200` with empty data.
- `priceFrom > priceTo` returns `422`.
- Public `sort` is rejected/not allowed.
- Internal order is `created_at DESC, id DESC`.
- `Content-Language` is correct and `Vary` includes `Accept-Language`.

### K. Feature 003 deletion guards

Verify existing category/subcategory DELETE routes now return:

```text
409 CATEGORY_HAS_SERVICES
409 SUBCATEGORY_HAS_SERVICES
```

Only non-deleted services block. Update Feature 003 OpenAPI/Postman artifacts accordingly.

## 7. Required MySQL Concurrency Scenarios

Run races for:

- Category delete vs direct service create.
- Subcategory delete vs classified service create.
- Category delete vs service move.
- Subcategory delete vs service move.
- Restore vs hierarchy delete.
- Two services claiming the same cross-locale slug.
- Two set-main requests.
- Two video uploads.

Expected invariants:

- No non-deleted service references a deleted hierarchy row.
- One global service owns any normalized slug.
- At most one main image exists per service.
- At most one video exists per service.

## 8. Automated Commands

```powershell
php artisan test tests\Feature\Api\V1\Admin\Services
php artisan test tests\Feature\Api\V1\Public\Services
php artisan test tests\Feature\Database\Services
php artisan test tests\Concurrency\Services
php artisan test tests\Architecture\ServicesAdminRouteContractTest.php
php artisan test tests\Architecture\ServicesFeatureArchitectureTest.php
php artisan test tests\Architecture\ServicesOpenApiAndPostmanContractTest.php

vendor\bin\pint --test
$env:TEMP="$PWD\.phpstan-tmp"
$env:TMP="$PWD\.phpstan-tmp"
vendor\bin\phpstan analyse app tests routes database --debug --no-progress --memory-limit=1G
php artisan test
```

OpenAPI parse and internal `$ref` resolution are covered by:

```powershell
php artisan test tests\Architecture\ServicesOpenApiAndPostmanContractTest.php
```

## 9. Acceptance Evidence

Implementation is ready only when:

- All 28 operation IDs match implemented routes.
- OpenAPI and Postman are synchronized.
- Feature 003 delete conflicts are synchronized.
- Admin sorts and filters are exact.
- Public filters are exact and public sorting is absent.
- Nested create permissions are enforced.
- No partial DB rows or orphaned request-created files remain after failures.
- All concurrency invariants pass on MySQL.
- Pest, Pint, and Larastan/PHPStan pass.

## 10. Printing Demo Data

The default database seed flow runs `PrintingCatalogSeeder` followed by
`PrintingServicesSeeder`. The second seeder creates exactly 15 active and
available printing services, assigns every service to one of the seeded root
category/subcategory pairs, reserves both localized slugs, downloads a distinct
product-specific validated PNG/JPEG/WebP image from Pexels for every service,
and stores one main image per service on the public disk under
`services/seed/pexels-print-products-v2`.

```powershell
php artisan db:seed --class=PrintingServicesSeeder
```

Run `PrintingCatalogSeeder` first when invoking the service seeder directly on
an empty database. Both seeders are idempotent. Later runs upgrade older
seeder-managed images to the current image set, while a manually replaced main
service image remains authoritative.
