# Feature 004 — Services Catalog

**Canonical feature name:** `004-services-catalog`  
**Canonical reference path:** `docs/features/004-services-catalog.md`  
**Status:** Approved feature reference for specification and implementation planning  
**Backend:** Laravel 13 API  
**Admin client:** External React application  
**Public client:** External Next.js application  
**Primary language:** Arabic  
**Supported languages:** Arabic and English

---

## 1. Purpose

Feature 004 provides the complete service catalog used by the public website and the Admin Dashboard.

A service may:

1. Have no category and no subcategory.
2. Belong to one category only.
3. Belong to one category and one subcategory.

A service may never belong to a subcategory without also belonging to that subcategory's parent category.

The feature also introduces:

- Bilingual service content.
- Fixed and start-from pricing.
- Display specifications.
- Customer order fields.
- Pricing options and option values.
- Images and one optional video.
- SEO metadata.
- Public filtering and localized output.
- Soft delete and restore for services.
- Category and subcategory deletion protection.

---

## 2. Scope

### 2.1 Included

- Admin service CRUD.
- Service soft delete and restore.
- Admin service listing, filtering, pagination, and sorting.
- Specifications CRUD.
- Order fields CRUD.
- Pricing options CRUD.
- Nested pricing option value actions.
- Media index, upload, update, delete, and set-as-main.
- Public service list and detail.
- Category and subcategory assignment.
- Category and subcategory deletion guards.
- Localized public and Admin-index responses.
- SEO fields.
- Transactional service creation with nested data and files.

### 2.2 Excluded

- Orders and checkout.
- Customer accounts.
- Final order price persistence.
- Discounts and coupons.
- Negative price adjustments.
- Price replacement operations.
- Request-quote pricing.
- Media reordering.
- Specifications, order fields, or pricing-option restore APIs.
- Public sorting.
- Separate APIs for pricing option values.
- Rich-text editors.
- HTML or Markdown descriptions.
- Multiple categories or subcategories per service.

Order submission, answer storage, selected-option snapshots, and final order pricing belong to the future Orders feature.

---

## 3. Core Domain Rules

### 3.1 Classification

The valid classification states are:

```text
category_id = null, subcategory_id = null
category_id = value, subcategory_id = null
category_id = value, subcategory_id = value
```

The following state is invalid:

```text
category_id = null, subcategory_id = value
```

When a subcategory is supplied:

- A category is required.
- The category must exist.
- The category must not be soft deleted.
- The category must be active.
- The subcategory must exist.
- The subcategory must not be soft deleted.
- The subcategory must be active.
- The subcategory must belong to the submitted category.

When updating a service:

- Omitting `categoryId` leaves the current category unchanged.
- Sending `categoryId = null` clears both category and subcategory.
- Changing `categoryId` automatically clears the current subcategory.
- Sending `subcategoryId = null` clears only the subcategory.
- Sending a `subcategoryId` requires a valid active parent category relationship.

A service may be active without a category.

### 3.2 Later category deactivation

If a category or subcategory becomes inactive after being assigned:

- The service itself remains active.
- The service remains visible in the public all-services list.
- The inactive category or subcategory is not exposed in public service responses.
- Public filtering by the inactive category or subcategory returns an empty result.
- The service is not automatically reclassified.

### 3.3 Restore classification cleanup

A restored service always returns with:

```text
is_active = false
```

During restore:

- If the category was soft deleted, clear both `category_id` and `subcategory_id`.
- If the category still exists but the subcategory was soft deleted, keep the category and clear only `subcategory_id`.
- Non-deleted inactive classification relationships may remain, but the restored service stays inactive.

The restore operation and relationship cleanup must be atomic.

---

## 4. Service Data

### 4.1 Required bilingual fields

```text
nameAr
nameEn
shortDescriptionAr
shortDescriptionEn
```

These four fields are required for creation.

The full-description pair is optional:

```text
descriptionAr
descriptionEn
```

Both full descriptions may be omitted or `null`. When either full description
is provided, the matching other-locale value is required.

All content is plain text.

No HTML and no Markdown are allowed.

Recommended limits:

```text
nameAr / nameEn                          max 150
shortDescriptionAr / shortDescriptionEn max 500
descriptionAr / descriptionEn            max 5000
```

### 4.2 Slugs

```text
slugAr
slugEn
```

Rules:

- Generated automatically from the matching localized name during creation when omitted.
- Stable after creation.
- Renaming a service does not automatically change either slug.
- A slug changes only when explicitly submitted by an authorized Admin update.
- `slugAr` and `slugEn` are globally unique across services.
- A slug value may not collide with either locale slug of another service.
- Soft-deleted services continue reserving their slugs.
- Slug normalization must occur before uniqueness validation.

Recommended maximum length:

```text
180 characters
```

### 4.3 Production time

Optional complete bilingual pair:

```text
productionTimeAr
productionTimeEn
```

Example:

```json
{
  "productionTimeAr": "من 5 إلى 7 أيام عمل",
  "productionTimeEn": "From 5 to 7 business days"
}
```

Rules:

- Both may be omitted or null.
- When one is submitted, the other is required.
- Plain text only.
- Maximum 255 characters per locale.

### 4.4 Status fields

Database:

```text
is_active              TINYINT(1)
is_available           TINYINT(1)
is_attachment_required TINYINT(1)
```

API:

```json
{
  "isActive": true,
  "isAvailable": true,
  "isAttachmentRequired": false
}
```

Defaults:

```text
isActive = false
isAvailable = true
isAttachmentRequired = false
```

Meaning:

- `isActive = true`: visible through public APIs.
- `isActive = false`: hidden from public APIs.
- `isAvailable = true`: the service can be ordered.
- `isAvailable = false`: the service remains publicly visible when active, but ordering is blocked.
- `isAttachmentRequired = true`: every newly created order item for this service must include at least one valid protected attachment in the same multipart request.
- `isAttachmentRequired = false`: order-item attachments remain optional.

The flag is returned by Admin and Public service resources so both frontends can render the upload field correctly. Existing services default to optional attachments. Changing the flag does not rewrite or invalidate historical order items.

An active service may have no images.

Deleting the main image does not deactivate the service.

---

## 5. Pricing

### 5.1 Price type enum

Database and API use integer values.

```php
enum ServicePriceType: int
{
    case FIXED = 0;
    case START_FROM = 1;
}
```

Mapping:

```text
0 = fixed
1 = start_from
```

Database:

```text
price_type TINYINT UNSIGNED
base_price DECIMAL(12,2)
```

API example:

```json
{
  "priceType": 1,
  "basePrice": 1000
}
```

### 5.2 Base price

Rules:

```text
basePrice > 0
```

A service cannot have a zero or negative base price.

### 5.3 Fixed pricing

When:

```text
priceType = 0
```

Then:

- `basePrice` is the final service price.
- Pricing options are not allowed.
- Submitting pricing options returns `422 VALIDATION_ERROR`.

### 5.4 Start-from pricing

When:

```text
priceType = 1
```

Then:

- `basePrice` is the displayed starting price.
- Pricing options are optional.
- Every selected option value adds its non-negative adjustment to the base price.

Formula:

```text
calculatedPrice =
basePrice
+ sum(selected active option value priceAdjustment)
```

Example:

```text
Base price               1000
Medium size              +500
Express execution        +500
Extended warranty        +250
--------------------------------
Calculated price         2250
```

There is no subtraction and no replacement operation in this feature.

### 5.5 Price adjustment

```text
priceAdjustment >= 0
```

Zero is allowed.

Example:

```json
{
  "labelAr": "صغير",
  "labelEn": "Small",
  "priceAdjustment": 0,
  "isActive": true
}
```

The feature does not require every required option to contain a zero-adjustment value.

The backend must later calculate order prices from stored database values and must never trust a client-submitted final price.

---

## 6. Specifications

Specifications are read-only information displayed to the customer.

They do not collect customer input and do not affect price.

### 6.1 Shape

```json
{
  "labelAr": "الخامة",
  "labelEn": "Material",
  "valueAr": "خشب طبيعي",
  "valueEn": "Natural wood",
  "sortOrder": 1
}
```

### 6.2 Rules

All four localized fields are required:

```text
labelAr
labelEn
valueAr
valueEn
```

Recommended limits:

```text
labelAr / labelEn max 150
valueAr / valueEn max 1000
```

Per-service maximum:

```text
30 specifications
```

Display order:

```text
sort_order ASC, id ASC
```

There is no reorder endpoint.

`sortOrder` is managed through create or update.

### 6.3 Lifecycle

- Specifications may be submitted during `Create Service`.
- After service creation, specifications use independent CRUD APIs.
- `Update Service` does not update specifications.
- Specification deletion uses soft delete.
- Deleted specifications are not shown by Admin show, public detail, or specification index.
- There is no specification restore API.
- There is no trashed filter for specifications in the MVP.

---

## 7. Order Fields

Order fields are questions that the customer answers while ordering a service.

This feature defines their configuration only.

Answer storage belongs to the Orders feature.

### 7.1 Current field type

Only text fields are supported now.

The type is internal and is not accepted from or exposed to the frontend in this feature.

Recommended internal enum:

```php
enum ServiceOrderFieldType: int
{
    case TEXT = 0;
}
```

Database:

```text
field_type TINYINT UNSIGNED DEFAULT 0
```

The internal field type exists to allow future expansion without introducing a settings column.

Future types may include:

```text
textarea
number
date
file
select
```

### 7.2 Shape

```json
{
  "labelAr": "اكتب المقاسات المطلوبة",
  "labelEn": "Enter the required dimensions",
  "isRequired": true,
  "sortOrder": 1
}
```

The following fields are intentionally excluded:

```text
placeholderAr
placeholderEn
helpTextAr
helpTextEn
settings
```

### 7.3 Rules

```text
labelAr required, max 150
labelEn required, max 150
isRequired required boolean
sortOrder integer
```

Maximum answer length in the future Orders feature:

```text
2000 characters
```

Per-service maximum:

```text
20 order fields
```

Display order:

```text
sort_order ASC, id ASC
```

There is no reorder endpoint.

### 7.4 Lifecycle

- Order fields may be submitted during `Create Service`.
- After service creation, order fields use independent CRUD APIs.
- `Update Service` does not update order fields.
- Order field deletion uses soft delete.
- Deleted order fields are not shown by Admin show, public detail, or order-field index.
- There is no restore API.
- There is no trashed filter in the MVP.

---

## 8. Pricing Options

Pricing options define selectable values that affect a start-from service price.

### 8.1 Internal option type

The backend contains an internal option type field.

It is fixed to add-on in the MVP.

Recommended internal enum:

```php
enum ServicePricingOptionType: int
{
    case ADD_ON = 0;
}
```

Database:

```text
option_type TINYINT UNSIGNED DEFAULT 0
```

The frontend:

- Does not send `optionType`.
- Does not receive `optionType`.
- Does not need to know that the internal type is add-on.

### 8.2 Input type enum

Database and APIs use integer values.

```php
enum ServicePricingInputType: int
{
    case SELECT = 0;
    case MULTI_SELECT = 1;
    case RADIO = 2;
    case CHECKBOX = 3;
}
```

Mapping:

```text
0 = select
1 = multi_select
2 = radio
3 = checkbox
```

The input type must be exposed in Admin and public service details so the frontend knows how to render the option.

Selection behavior:

```text
select       one value
radio        one value
multi_select multiple values
checkbox     multiple values
```

`select` and `radio` differ only in presentation.

`multi_select` and `checkbox` differ only in presentation.

### 8.3 Option shape

```json
{
  "nameAr": "المقاس",
  "nameEn": "Size",
  "inputType": 2,
  "isRequired": true,
  "sortOrder": 1,
  "values": [
    {
      "labelAr": "صغير",
      "labelEn": "Small",
      "priceAdjustment": 0,
      "isActive": true,
      "sortOrder": 1
    },
    {
      "labelAr": "متوسط",
      "labelEn": "Medium",
      "priceAdjustment": 500,
      "isActive": true,
      "sortOrder": 2
    }
  ]
}
```

### 8.4 Selection validation

For `select` and `radio`:

- Required option: exactly one selected active value.
- Optional option: zero or one selected active value.

For `multi_select` and `checkbox`:

- Required option: one or more selected active values.
- Optional option: zero or more selected active values.

Only active, non-deleted values may be selected.

There are no default selections in the MVP.

### 8.5 Limits

```text
Maximum pricing options per service: 10
Maximum values per option:           30

Option nameAr/nameEn:                max 150
Value labelAr/labelEn:               max 150
priceAdjustment:                     DECIMAL(12,2), min 0
```

Display order:

```text
sort_order ASC, id ASC
```

There are no reorder endpoints.

### 8.6 Lifecycle

- Pricing options with values may be submitted during `Create Service`.
- After creation, pricing options use independent CRUD APIs.
- `POST Pricing Option` may include its values.
- `Update Service` does not update pricing options.
- Pricing option deletion soft deletes the option and all its values atomically.
- Deleted pricing options and values are excluded from all normal responses.
- There are no restore or trashed-filter APIs in the MVP.

### 8.7 Updating values through actionStatus

Pricing option values do not have independent endpoints.

They are managed through:

```http
PATCH /api/v1/admin/services/{service}/pricing-options/{pricingOption}
```

Example:

```json
{
  "values": [
    {
      "actionStatus": "create",
      "labelAr": "كبير",
      "labelEn": "Large",
      "priceAdjustment": 1000,
      "isActive": true,
      "sortOrder": 3
    },
    {
      "id": 15,
      "actionStatus": "update",
      "labelAr": "متوسط",
      "labelEn": "Medium",
      "priceAdjustment": 600,
      "isActive": true
    },
    {
      "id": 18,
      "actionStatus": "delete"
    }
  ]
}
```

Allowed values:

```text
""       no action
create   create a new value
update   update an existing value
delete   soft delete an existing value
```

Rules:

- `create` must not contain `id`.
- `create` requires all required value fields.
- `update` requires `id`.
- `update` modifies only submitted fields.
- `delete` requires `id`.
- Empty or omitted `actionStatus` produces no action.
- A no-action item does not require an `id`.
- Omitting `values` leaves all values unchanged.
- Sending `values: []` also leaves all values unchanged.
- All referenced values must belong to the pricing option in the route.
- All actions execute inside one database transaction.
- Duplicate IDs in the same request are invalid.
- Conflicting actions for the same value ID are invalid.

---

## 9. Media

Services use one unified media collection.

There is no separate main-image or gallery entity.

### 9.1 Media type enum

Database and APIs use integer values.

```php
enum ServiceMediaType: int
{
    case IMAGE = 0;
    case VIDEO = 1;
}
```

Mapping:

```text
0 = image
1 = video
```

Database:

```text
type TINYINT UNSIGNED
is_main TINYINT(1)
```

API:

```json
{
  "id": 12,
  "type": 0,
  "isMain": true,
  "url": "https://example.com/storage/services/12.webp",
  "altAr": "صورة الخدمة",
  "altEn": "Service image"
}
```

### 9.2 File rules

Images:

```text
Extensions/MIME: jpg, jpeg, png, webp
Maximum size:    5 MB per image
Maximum count:   10 images per service
```

Video:

```text
Extensions/MIME: mp4, webm
Maximum size:    100 MB
Maximum count:   1 video per service
```

A video can never be main.

### 9.3 Alt text

```text
altAr
altEn
```

Rules:

- Both optional.
- Both may be null.
- If one is submitted, the other is required.
- Plain text only.

### 9.4 Main image rules

- At most one image may be main.
- Multiple `isMain = true` images in one request return `422 VALIDATION_ERROR`.
- When images are uploaded and none is marked main, the first uploaded image becomes main automatically.
- Services may have no images.
- Active services may have no images.
- Setting main is allowed only for image media.
- Setting a new main image clears the previous main flag atomically.

When deleting the current main image:

- If another image remains, the oldest remaining image by `id ASC` becomes main.
- If no image remains, the service has no main image.
- The service remains active.

### 9.5 Media order

Responses return media in this order:

1. Main image.
2. Remaining images by `id ASC`.
3. Video.

There is no media reorder endpoint.

### 9.6 Uploading media during service creation

`Create Service` supports media upload in the same multipart request.

Example field names:

```text
media[0][file]
media[0][type]
media[0][isMain]
media[0][altAr]
media[0][altEn]
```

All other nested arrays also use multipart bracket notation.

There is no JSON `payload` field.

Examples:

```text
specifications[0][labelAr]
specifications[0][labelEn]
orderFields[0][labelAr]
pricingOptions[0][values][0][labelAr]
```

### 9.7 Media lifecycle

- Media upload uses independent APIs after service creation.
- Media update changes alt text only.
- Files are not replaced through the update endpoint.
- Uploading a second video while one exists returns `SERVICE_VIDEO_LIMIT_REACHED`.
- To replace a video, explicitly delete the old video and upload a new one.
- Media delete is a hard delete:
  - Delete the database row.
  - Delete the physical file.
- Soft deleting a service does not delete media rows or files.
- Restoring a service restores access to its existing media.
- Old files are deleted only after the relevant database operation succeeds.
- Failed uploads must clean up files written during the failed request.

---

## 10. SEO

Optional bilingual fields:

```text
seoTitleAr
seoTitleEn
seoDescriptionAr
seoDescriptionEn
seoTagsAr
seoTagsEn
```

Rules:

```text
seoTitleAr / seoTitleEn             max 70
seoDescriptionAr / seoDescriptionEn max 180
seoTagsAr / seoTagsEn               array
Maximum tags per locale             20
Maximum characters per tag          50
```

Pair rules:

- Both SEO titles may be omitted or null.
- When one title is submitted, the matching other-locale title is required.
- Both SEO descriptions may be omitted or null.
- When one description is submitted, the matching other-locale description is required.
- Both SEO tag arrays may be omitted or null.
- When one tag array is submitted, the other is required.

Public fallback behavior for rendering metadata:

```text
SEO title missing        use localized service name
SEO description missing  use localized short description
```

---

## 11. Admin API

All Admin routes use:

```text
/api/v1/admin
```

All route IDs must be numeric constrained.

All responses use the existing shared API response envelope.

All controllers use the project's existing `StatusCode` enum and must not hardcode HTTP integers.

### 11.1 Service routes

```http
GET    /api/v1/admin/services
POST   /api/v1/admin/services
GET    /api/v1/admin/services/{service}
PATCH  /api/v1/admin/services/{service}
DELETE /api/v1/admin/services/{service}
POST   /api/v1/admin/services/{service}/restore
```

### 11.2 Specification routes

```http
GET    /api/v1/admin/services/{service}/specifications
POST   /api/v1/admin/services/{service}/specifications
GET    /api/v1/admin/services/{service}/specifications/{specification}
PATCH  /api/v1/admin/services/{service}/specifications/{specification}
DELETE /api/v1/admin/services/{service}/specifications/{specification}
```

### 11.3 Order field routes

```http
GET    /api/v1/admin/services/{service}/order-fields
POST   /api/v1/admin/services/{service}/order-fields
GET    /api/v1/admin/services/{service}/order-fields/{orderField}
PATCH  /api/v1/admin/services/{service}/order-fields/{orderField}
DELETE /api/v1/admin/services/{service}/order-fields/{orderField}
```

### 11.4 Pricing option routes

```http
GET    /api/v1/admin/services/{service}/pricing-options
POST   /api/v1/admin/services/{service}/pricing-options
GET    /api/v1/admin/services/{service}/pricing-options/{pricingOption}
PATCH  /api/v1/admin/services/{service}/pricing-options/{pricingOption}
DELETE /api/v1/admin/services/{service}/pricing-options/{pricingOption}
```

Pricing option values have no independent routes.

### 11.5 Media routes

```http
GET    /api/v1/admin/services/{service}/media
POST   /api/v1/admin/services/{service}/media
PATCH  /api/v1/admin/services/{service}/media/{media}
DELETE /api/v1/admin/services/{service}/media/{media}
PATCH  /api/v1/admin/services/{service}/media/{media}/set-as-main
```

`PATCH /media/{media}` updates localized alt text only.

### 11.6 Status codes

Use the existing project `StatusCode` enum.

Expected behavior:

```text
POST create       201 Created
GET               200 OK
PATCH             200 OK
DELETE            200 OK with data: null
Restore           200 OK
Validation        422
Authentication    401
Authorization     403
Not found         404
Conflict          409
Rate limit        429
Internal error    500
```

Do not use `204 No Content`.

---

## 12. Create Service Contract

### 12.1 Request type

```http
Content-Type: multipart/form-data
```

All data uses multipart bracket notation.

No separate JSON payload field is used.

### 12.2 Supported nested arrays

Create Service may include:

```text
specifications
orderFields
pricingOptions with values
media
```

### 12.3 Example multipart shape

```text
nameAr
nameEn
shortDescriptionAr
shortDescriptionEn
descriptionAr
descriptionEn
slugAr
slugEn
categoryId
subcategoryId
priceType
basePrice
isActive
isAvailable
isAttachmentRequired
productionTimeAr
productionTimeEn

specifications[0][labelAr]
specifications[0][labelEn]
specifications[0][valueAr]
specifications[0][valueEn]
specifications[0][sortOrder]

orderFields[0][labelAr]
orderFields[0][labelEn]
orderFields[0][isRequired]
orderFields[0][sortOrder]

pricingOptions[0][nameAr]
pricingOptions[0][nameEn]
pricingOptions[0][inputType]
pricingOptions[0][isRequired]
pricingOptions[0][sortOrder]
pricingOptions[0][values][0][labelAr]
pricingOptions[0][values][0][labelEn]
pricingOptions[0][values][0][priceAdjustment]
pricingOptions[0][values][0][isActive]
pricingOptions[0][values][0][sortOrder]

media[0][file]
media[0][type]
media[0][isMain]
media[0][altAr]
media[0][altEn]
```

### 12.4 Atomic creation

Service creation is atomic across:

- Service row.
- Specifications.
- Order fields.
- Pricing options.
- Pricing option values.
- Media metadata.
- Uploaded files.

On any failure:

- Roll back database changes.
- Delete files uploaded by the failed request.
- Do not leave a partial service.

### 12.5 Nested create permissions

The base operation requires:

```text
services.create
```

When nested resources are included, the caller must also hold:

```text
specifications included  service-specifications.create
orderFields included     service-order-fields.create
pricingOptions included  service-pricing-options.create
media included           service-media.create
```

Missing a required nested-resource permission returns `403 FORBIDDEN`.

---

## 13. Update Service Contract

`PATCH Service` updates service-level fields only.

It does not update:

```text
specifications
orderFields
pricingOptions
pricing option values
media
```

These use their independent APIs.

Changing category and clearing subcategory occur atomically.

A service update that produces invalid required bilingual content or invalid pricing returns `422 VALIDATION_ERROR`.

The backend does not silently deactivate a service because of an invalid service-level update.

---

## 14. Admin Service Index

### 14.1 Localization

Admin service index returns the resolved locale only.

The locale is resolved from `Accept-Language`.

Example fields:

```json
{
  "id": 20,
  "name": "تصميم وتنفيذ مطبخ",
  "shortDescription": "تصميم مطابخ حسب الطلب.",
  "slug": "تصميم-وتنفيذ-مطبخ",
  "priceType": 1,
  "basePrice": 15000,
  "isActive": true,
  "isAvailable": true,
  "isAttachmentRequired": false,
  "mainMedia": null,
  "category": null,
  "subcategory": null,
  "createdAt": "2026-07-30T10:00:00Z",
  "updatedAt": "2026-07-30T10:00:00Z",
  "deletedAt": null
}
```

Admin show, create, and update responses return both locales and all active nested data.

### 14.2 Filters

Use `spatie/laravel-query-builder`.

Approved filters:

```text
filter[search]
filter[categoryId]
filter[subcategoryId]
filter[priceType]
filter[isActive]
filter[isAvailable]
filter[trashed]
```

`filter[search]` searches both languages:

```text
name_ar
name_en
short_description_ar
short_description_en
description_ar
description_en
slug_ar
slug_en
```

`filter[categoryId]` includes:

- Services assigned directly to the category.
- Services assigned to a subcategory under the same category.

`filter[subcategoryId]` includes only services assigned to that subcategory.

`filter[priceType]` accepts:

```text
0
1
```

Boolean filters accept API-compatible boolean values.

### 14.3 Sorting

Allowed Admin sorting only:

```text
sort=basePrice
sort=-basePrice
sort=createdAt
sort=-createdAt
```

Default:

```text
created_at DESC, id DESC
```

### 14.4 Pagination

```text
page
perPage
```

Defaults:

```text
default perPage = 15
maximum perPage = 100
```

### 14.5 Child indexes

Specification, order-field, pricing-option, and media indexes are unpaginated because their counts are strictly capped.

They do not expose deleted records and do not support trashed filters.

---

## 15. Public API

### 15.1 Routes

```http
GET /api/v1/public/services
GET /api/v1/public/services/{serviceSlug}
```

There are no nested public service endpoints under categories or subcategories.

### 15.2 Visibility

Public APIs return only services where:

```text
is_active = true
deleted_at IS NULL
```

Both available and unavailable active services are visible.

An unavailable service cannot be ordered.

Future order creation behavior:

```text
active + unavailable 409 SERVICE_UNAVAILABLE
inactive or deleted  404 SERVICE_NOT_FOUND
```

### 15.3 Public filters

Approved filters:

```text
filter[search]
filter[category]
filter[subcategory]
filter[priceFrom]
filter[priceTo]
filter[isAvailable]
```

`filter[category]` and `filter[subcategory]` use slugs, not IDs.

Slug lookup:

1. Search the current request locale column.
2. If not found, search the alternate locale column.
3. Response localization remains based on `Accept-Language`.

Inactive or deleted category/subcategory filters return:

```http
200 OK
```

with an empty data collection.

Price filters apply to:

```text
base_price
```

They do not attempt to calculate the maximum or selected-option price.

When:

```text
priceFrom > priceTo
```

return:

```text
422 VALIDATION_ERROR
```

Public search searches Arabic and English content regardless of the resolved response locale.

### 15.4 Pagination

```text
page
perPage
```

Defaults:

```text
default perPage = 12
maximum perPage = 50
```

### 15.5 Sorting

The public API does not accept a `sort` parameter.

The internal deterministic order is:

```text
created_at DESC, id DESC
```

### 15.6 Public list response

The list returns summary data only.

Example:

```json
{
  "id": 20,
  "name": "تصميم وتنفيذ مطبخ",
  "shortDescription": "تصميم مطابخ حسب الطلب.",
  "slug": "تصميم-وتنفيذ-مطبخ",
  "priceType": 1,
  "basePrice": 15000,
  "isAvailable": true,
  "mainMedia": {
    "id": 4,
    "type": 0,
    "url": "https://example.com/storage/services/4.webp",
    "alt": "تصميم وتنفيذ مطبخ"
  },
  "category": {
    "id": 1,
    "name": "خدمات المنازل",
    "slug": "خدمات-المنازل"
  },
  "subcategory": null
}
```

The list excludes:

```text
full description
specifications
order fields
pricing options
all media
SEO arrays
internal field type
internal option type
deleted fields
```

`isActive` is omitted because every public item is active.

`isAvailable` remains because the frontend needs to know whether ordering is allowed.

### 15.7 Public detail response

Public detail returns the resolved locale only for localized content.

It includes:

- Localized service name.
- Localized short description.
- Localized description.
- Localized slug.
- `priceType` as integer.
- `basePrice`.
- `isAvailable`.
- Localized production time.
- Active category when publicly visible.
- Active subcategory when publicly visible.
- Active, non-deleted specifications.
- Active service order fields.
- Active, non-deleted pricing options.
- Active, non-deleted pricing option values.
- Pricing option `inputType` integer.
- All media.
- Localized media alt text.
- Localized SEO metadata and fallbacks.

Operational values are not translated:

```text
priceType
inputType
isRequired
isActive
isAvailable
priceAdjustment
media type
```

The frontend maps integer enums and boolean fields to localized labels.

Internal fields are not exposed:

```text
optionType
fieldType
deletedAt
```

### 15.8 Response language headers

Public responses include:

```http
Content-Language: ar
```

or:

```http
Content-Language: en
```

`Vary` must include:

```http
Accept-Language
```

and may include other required headers such as `Origin`.

---

## 16. Permissions

### 16.1 Service permissions

```text
services.view
services.create
services.update
services.delete
services.restore
```

### 16.2 Specification permissions

```text
service-specifications.view
service-specifications.create
service-specifications.update
service-specifications.delete
```

### 16.3 Order field permissions

```text
service-order-fields.view
service-order-fields.create
service-order-fields.update
service-order-fields.delete
```

### 16.4 Pricing option permissions

```text
service-pricing-options.view
service-pricing-options.create
service-pricing-options.update
service-pricing-options.delete
```

Pricing option values inherit the pricing option permission because they have no independent endpoints.

### 16.5 Media permissions

```text
service-media.view
service-media.create
service-media.update
service-media.delete
service-media.set-main
```

### 16.6 Middleware order

Admin endpoints use the existing project middleware contract:

```text
auth:sanctum
EnsureUserIsAdministrator
EnsureAdminIsActive
permission
endpoint execution
```

Each action must use the exact action permission.

The super-admin receives all registered permissions through the idempotent permission seeder and has no hidden authorization bypass.

---

## 17. Soft Delete and Restore

### 17.1 Service

Service deletion uses soft delete.

Deleting a service:

- Does not delete specifications.
- Does not delete order fields.
- Does not delete pricing options.
- Does not delete option values.
- Does not delete media rows.
- Does not delete physical media files.

Restoring a service:

- Sets `isActive = false`.
- Cleans deleted category or subcategory relationships as defined earlier.
- Restores access to existing non-deleted nested data and media.
- Uses one transaction.

### 17.2 Child resources

Soft delete is used for:

```text
service specifications
service order fields
service pricing options
service pricing option values
```

No restore endpoints are included for child resources in the MVP.

No normal response exposes child soft-deleted rows.

### 17.3 Media

Media delete is a hard delete.

This is intentional because historical order records will later store immutable snapshots rather than depend on live media rows.

---

## 18. Category and Subcategory Deletion Protection

Feature 004 completes the deferred dependency checks from Feature 003.

### 18.1 Category deletion

Deleting a category is blocked when it has any non-deleted service assigned directly to it.

Response:

```http
409 Conflict
```

Code:

```text
CATEGORY_HAS_SERVICES
```

Feature 003 continues to block category deletion when non-deleted subcategories exist.

Soft-deleted services do not block category deletion.

### 18.2 Subcategory deletion

Deleting a subcategory is blocked when it has any non-deleted service.

Response:

```http
409 Conflict
```

Code:

```text
SUBCATEGORY_HAS_SERVICES
```

Soft-deleted services do not block subcategory deletion.

### 18.3 Concurrency

Deletion checks and service classification writes must be race safe.

Required lock order:

```text
category/root first
subcategory second when applicable
services by id ASC
revalidate
mutate
commit
```

At minimum, real MySQL concurrency tests must cover:

- Category delete versus service create assigned directly to that category.
- Subcategory delete versus service create assigned to that subcategory.
- Category delete versus service update moving into that category.
- Subcategory delete versus service update moving into that subcategory.
- Service restore versus category/subcategory deletion.

---

## 19. Transactions and File Safety

The following operations must be atomic:

- Create service with nested specifications, order fields, pricing options, values, and media.
- Update pricing option with `actionStatus` value operations.
- Delete pricing option and soft delete its values.
- Set media as main.
- Delete main media and select a fallback main image.
- Restore service and clean invalid deleted classification links.
- Change category and automatically clear subcategory.
- Category/subcategory deletion checks involving services.

File handling rules:

- Write files using the configured public storage disk.
- Track files written during the request.
- Commit database work before deleting superseded files.
- On rollback, remove files created by the failed request.
- Never delete an existing file before the replacement operation succeeds.
- Never trust MIME type from the filename alone.

---

## 20. Validation Summary

### 20.1 Service

```text
nameAr/nameEn                           required
shortDescriptionAr/shortDescriptionEn  required
descriptionAr/descriptionEn             optional nullable bilingual pair
priceType                               required, 0|1
basePrice                               required, numeric, >0
isActive                                boolean
isAvailable                             boolean
isAttachmentRequired                    boolean, defaults to false
categoryId                              nullable, active non-deleted category
subcategoryId                           nullable, requires valid category
```

### 20.2 Fixed price

```text
priceType = 0
pricingOptions prohibited
```

### 20.3 Start-from price

```text
priceType = 1
pricingOptions optional
priceAdjustment >= 0
```

When activating a service with pricing options, each existing option must contain at least one active, non-deleted value.

### 20.4 Specifications

```text
all bilingual label/value fields required
maximum 30 per service
```

### 20.5 Order fields

```text
labelAr/labelEn required
text type internal only
maximum 20 per service
```

### 20.6 Pricing options

```text
nameAr/nameEn required
inputType 0|1|2|3
maximum 10 per service
maximum 30 values per option
```

### 20.7 Media

```text
maximum 10 images
maximum 1 video
video cannot be main
maximum one main image
```

---

## 21. Shared Error Codes

Use the existing shared API envelope.

Feature-specific and reused codes include:

```text
UNAUTHENTICATED
USER_INACTIVE
FORBIDDEN
VALIDATION_ERROR
SERVICE_NOT_FOUND
SPECIFICATION_NOT_FOUND
ORDER_FIELD_NOT_FOUND
PRICING_OPTION_NOT_FOUND
PRICING_OPTION_VALUE_NOT_FOUND
SERVICE_MEDIA_NOT_FOUND
CATEGORY_NOT_FOUND
SUBCATEGORY_NOT_FOUND
CATEGORY_INACTIVE
SUBCATEGORY_INACTIVE
SUBCATEGORY_CATEGORY_MISMATCH
CATEGORY_HAS_SERVICES
SUBCATEGORY_HAS_SERVICES
PRICING_OPTIONS_NOT_ALLOWED_FOR_FIXED
SERVICE_IMAGE_LIMIT_REACHED
SERVICE_VIDEO_LIMIT_REACHED
INVALID_MAIN_MEDIA
SERVICE_UNAVAILABLE
RATE_LIMITED
INTERNAL_ERROR
```

Nested resource IDs must belong to the parent resources in the route.

A mismatched nested resource must return a non-disclosing not-found response rather than exposing that the resource exists under another service.

---

## 22. Response Envelope

Successful responses follow the project's shared structure.

Example:

```json
{
  "success": true,
  "message": "تم تنفيذ العملية بنجاح.",
  "data": {}
}
```

Delete response:

```json
{
  "success": true,
  "message": "تم الحذف بنجاح.",
  "data": null
}
```

Validation response:

```json
{
  "success": false,
  "message": "البيانات المرسلة غير صالحة.",
  "code": "VALIDATION_ERROR",
  "errors": {
    "basePrice": [
      "يجب أن يكون السعر أكبر من صفر."
    ]
  }
}
```

Messages use the locale resolved by the existing API localization middleware.

---

## 23. Testing Requirements

Use Pest and the project's real MySQL test database.

Required coverage includes:

### Services

- Create an unclassified service.
- Create a category-only service.
- Create a category-and-subcategory service.
- Reject subcategory without category.
- Reject inactive or deleted category assignment.
- Reject inactive or deleted subcategory assignment.
- Reject mismatched category and subcategory.
- Automatically clear subcategory when category changes.
- Create fixed service without pricing options.
- Reject pricing options for fixed service.
- Create start-from service with nested pricing options.
- Enforce base price greater than zero.
- Allow zero price adjustment.
- Soft delete and restore service as inactive.
- Clean deleted category/subcategory links during restore.

### Specifications

- Create during service creation.
- Independent index/show/create/update/delete.
- Enforce bilingual fields and limits.
- Hide soft-deleted specifications.

### Order fields

- Create during service creation.
- Independent index/show/create/update/delete.
- Do not expose internal field type.
- Hide soft-deleted fields.

### Pricing options

- Create with values.
- Update group without changing omitted values.
- `values: []` produces no value changes.
- Create/update/delete value actions.
- Reject duplicate or conflicting value actions.
- Enforce route ownership.
- Soft delete option and values atomically.
- Expose integer input type publicly.
- Enforce single versus multi selection rules in future pricing validation services.

### Media

- Create service with multipart media.
- Reject more than one main image.
- Automatically select first uploaded image when none is main.
- Reject video as main.
- Enforce image and video limits.
- Reject second video.
- Set image as main atomically.
- Delete main and choose oldest remaining image.
- Keep service active when no image remains.
- Hard delete media and physical file.
- Roll back database and files on failed atomic creation.

### Admin index

- All approved filters.
- Bilingual search.
- Allowed sorting only.
- Pagination limits.
- Localized index response.

### Public

- Return active services only.
- Return unavailable active services.
- Filter by category and subcategory localized slug.
- Use alternate-locale slug fallback.
- Return empty list for inactive category filters.
- Search both languages.
- Filter base price from/to.
- Reject invalid price range.
- Fixed internal ordering with no public sort.
- Localized detail with operational enums unchanged.

### Feature 003 integration

- Block category deletion with direct non-deleted services.
- Block subcategory deletion with non-deleted services.
- Ignore soft-deleted services in dependency checks.
- Real MySQL race tests for delete-versus-create/update/restore scenarios.

---

## 24. Implementation Constraints

- API-only backend.
- No auth cookies.
- Admin endpoints use Bearer Sanctum authentication.
- Public endpoints are unauthenticated.
- Use the existing CORS contract.
- Use the existing API locale middleware.
- Use `spatie/laravel-query-builder` for Admin and public list filters.
- Use existing API resources and shared response helpers.
- Use existing `StatusCode` enum.
- Use database transactions for all listed atomic workflows.
- Use public storage disk for service media.
- Do not introduce queue requirements.
- Do not introduce Swagger at runtime; OpenAPI may be maintained as a documentation artifact.
- No customer login or customer portal is introduced by this feature.

---

## 25. Approved Final Summary

```text
Service classification:
none
category only
category + subcategory

Price types:
0 fixed
1 start_from

Service states:
isActive boolean
isAvailable boolean

Specifications:
localized label/value display data

Order fields:
localized text questions
internal text field type
no settings

Pricing options:
internal add-on type
0 select
1 multi_select
2 radio
3 checkbox
non-negative price adjustments

Media:
0 image
1 video
up to 10 images
up to 1 video
one optional main image
no reorder

Public:
GET /api/v1/public/services
GET /api/v1/public/services/{serviceSlug}
localized output
bilingual search
category/subcategory slug filters
base-price range filters
no public sorting
```
