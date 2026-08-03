# Feature 003 — Categories and Subcategories

**Project**: Service Commerce  
**Feature ID**: `003-categories-subcategories`  
**Document Path**: `docs/features/003-categories-subcategories.md`  
**Status**: Approved Reference for `/speckit.specify`  
**Depends On**:

- Feature 001 — Identity and Authentication
- Feature 002 — Customers and Addresses
- Project constitution
- Backend architecture
- API, code, database, authorization, localization, security, testing, and
  file-storage standards

---

## 1. Purpose

This feature introduces the service-catalog classification structure used by
the public website and the administration dashboard.

The catalog hierarchy is exactly:

```text
Category
└── Subcategory
    └── Service
```

This feature implements Categories and Subcategories only. Services are
implemented in a later feature, but this feature defines the relationship and
deletion rules that Services must respect.

The implementation uses one self-referencing `categories` table:

```text
parent_id = null
```

means a root Category.

```text
parent_id = root category id
```

means a Subcategory.

A third hierarchy level is prohibited.

---

## 2. Approved Product Decisions

The following decisions are final for this feature:

1. Categories and Subcategories use `SoftDeletes`.
2. Deleted records can be restored.
3. Force deletion is outside the MVP.
4. Deletion is blocked while the record has non-deleted dependent records.
5. A Category cannot be deleted while it has any non-deleted Subcategory.
6. A Subcategory cannot be deleted while it has any non-deleted Service.
7. No cascade delete is allowed.
8. Category and Subcategory content is bilingual in Arabic and English.
9. Every record has:
   - required Arabic name;
   - required English name;
   - optional Arabic description;
   - optional English description;
   - Arabic slug;
   - English slug.
10. Descriptions are an optional bilingual pair:
    - both descriptions may be omitted;
    - when one localized description is supplied, the matching description in
      the other language is required;
    - empty descriptions normalize to `null`.
11. Names and descriptions are entered and maintained by administrators. The
    backend must not machine-translate either field.
12. Descriptions are plain text only and are not used to generate slugs.
13. Slugs are stable, editable, and unique within their locale column.
14. Slugs may be generated automatically from the matching localized name when
    omitted during creation.
15. Updating a name or description does not silently change an existing slug.
16. Categories and Subcategories support manual ordering through `sort_order`.
17. Categories and Subcategories have an independent `is_active` flag.
18. Disabling a Category hides its Subcategories and Services from public APIs
    without changing their stored `is_active` values.
19. Disabling a Subcategory hides its Services from public APIs without changing
    their stored `is_active` values.
20. Each Category and Subcategory may have one optional image.
21. The image is not required on create or update.
22. Submitting a new image during update replaces the previous image.
23. Submitting `image` as a text value, including an empty string, does not
    change the stored image; only an uploaded file creates or replaces it.
24. No multiple images, icon, video, attachment, or other file-upload behavior
    belongs to this feature.
25. A Subcategory belongs to exactly one root Category.
26. Moving a Subcategory to another Category is outside the MVP.
27. Restoring a Category does not automatically restore its deleted
    Subcategories.
28. Restoring a Subcategory does not automatically restore deleted Services.
29. Public APIs expose only active, non-deleted records whose ancestors are also
    active and non-deleted.
30. Hidden, inactive, or deleted public records return `404`, not a status that
    reveals their existence.
31. Admin detail and mutation APIs return both Arabic and English names,
    descriptions, slugs, and the optional image URL. Admin Category and
    Subcategory index APIs return only the resolved-locale `name`,
    `description`, `slug`, and optional image URL, plus required operational
    fields.
32. Public APIs resolve the localized name, description, and slug from
    `Accept-Language`.
33. Arabic is the default locale and English is the fallback.
34. Stable machine keys, routes, permissions, and error codes remain English.

---

## 3. Scope

### 3.1 In Scope

- Root Category creation and management.
- Subcategory creation and management under a root Category.
- Arabic and English names, descriptions, and slugs.
- Automatic slug generation on creation when omitted.
- Manual slug editing.
- Active/inactive state management.
- Manual ordering.
- Admin listing, filtering, searching, pagination, deletion, and restoration.
- Public active catalog navigation.
- Root/child hierarchy validation.
- Deletion dependency protection.
- Public visibility inheritance.
- Permissions.
- API contracts.
- Localization.
- Postman documentation.
- Pest Feature, database, architecture, and concurrency tests.

### 3.2 Out of Scope

- Services CRUD.
- Service questions.
- Service options.
- Service specifications.
- Service pricing.
- Service media.
- Multiple Category or Subcategory images.
- Icons, videos, and attachments.
- Third-level classification.
- Arbitrary-depth trees.
- Drag-and-drop frontend implementation.
- Category merging.
- Subcategory parent transfer.
- Force deletion.
- Automatic restoration of descendants.
- Customer-specific categories.
- Category-level pricing.
- Category-level SEO metadata beyond localized slugs and ordinary bilingual
  descriptions.
- Public write operations.
- Frontend implementation.
- Cache infrastructure.
- Redis.
- Queue Jobs.
- Commands or Cron cleanup for this feature.

---

## 4. Actors

### 4.1 Super Admin

The authenticated `super-admin` can manage Categories and Subcategories when
the required permissions are assigned.

Authentication, administrator type, active-state checks, role checks, and
permission checks remain separate controls.

### 4.2 Public Visitor

A public visitor can retrieve only active, non-deleted Categories and
Subcategories whose required ancestor chain is also active and non-deleted.

Public visitors cannot create, update, delete, restore, or reorder records.

---

## 5. Hierarchy Rules

### 5.1 Root Category

A root Category has:

```text
parent_id = null
```

A root Category cannot belong to another Category.

### 5.2 Subcategory

A Subcategory has:

```text
parent_id = <root category id>
```

The parent must:

- exist;
- not be soft-deleted;
- have `parent_id = null`.

A Subcategory cannot use another Subcategory as its parent.

### 5.3 No Third Level

The system must reject any attempt to create:

```text
Category
└── Subcategory
    └── Child Category
```

The hierarchy is limited to exactly two classification levels.

### 5.4 Parent Immutability

The Subcategory parent is determined by the nested route.

The API must not accept:

```text
parentId
categoryId
```

inside create or update bodies for a Subcategory.

Moving a Subcategory between Categories requires a separately approved future
workflow.

---

## 6. Persistence Model

## 6.1 Table: `categories`

```text
categories
```

| Column | MySQL Direction | Null | Default | Rules |
|---|---|---:|---|---|
| `id` | `BIGINT UNSIGNED` primary key | No | auto | Internal identifier |
| `parent_id` | `BIGINT UNSIGNED` foreign key | Yes | `NULL` | Null for Category; root Category ID for Subcategory |
| `name_ar` | `VARCHAR(150)` | No | — | Required Arabic name |
| `name_en` | `VARCHAR(150)` | No | — | Required English name |
| `description_ar` | `TEXT` | Yes | `NULL` | Optional Arabic plain-text description |
| `description_en` | `TEXT` | Yes | `NULL` | Optional English plain-text description |
| `slug_ar` | `VARCHAR(180)` | No | — | Stable Arabic slug |
| `slug_en` | `VARCHAR(180)` | No | — | Stable English slug |
| `sort_order` | `INT UNSIGNED` | No | `0` | Manual ordering |
| `is_active` | `BOOLEAN` | No | `true` | Independent stored state |
| `image_disk` | `VARCHAR(50)` | Yes | `NULL` | Optional image storage disk |
| `image_path` | `VARCHAR(500)` | Yes | `NULL` | Optional image storage path |
| `created_at` | `TIMESTAMP` | Yes | framework | Laravel timestamp |
| `updated_at` | `TIMESTAMP` | Yes | framework | Laravel timestamp |
| `deleted_at` | `TIMESTAMP` | Yes | `NULL` | Soft delete |

### 6.2 Constraints and Indexes

Required database constraints:

- primary key on `id`;
- foreign key from `parent_id` to `categories.id`;
- `restrictOnDelete()` for the self-reference;
- unique index on `slug_ar`;
- unique index on `slug_en`;
- index on `parent_id`;
- index on `is_active`;
- index on `deleted_at`;
- composite index on:

```text
parent_id, is_active, sort_order, id
```

The database cannot fully enforce the two-level hierarchy by itself. The
application must validate that a Subcategory parent is a root Category and must
test this rule.

### 6.3 Cross-Locale Slug Behavior

Public route resolution is locale-specific:

```text
Accept-Language: ar
```

resolves the path slug against `slug_ar`.

```text
Accept-Language: en
```

resolves the path slug against `slug_en`.

Each slug column is globally unique across the shared `categories` table. This
means a localized slug cannot be reused by another Category or Subcategory in
the same locale.

### 6.4 Eloquent Model

Model:

```text
App\Models\Category
```

Required behavior:

- `SoftDeletes`;
- guarded or explicit fillable assignment;
- casts for:
  - `is_active` as boolean;
  - `sort_order` as integer;
- `parent()` belongs-to relationship;
- `children()` has-many relationship;
- `services()` relationship introduced or completed by Feature 004;
- root Category scope;
- Subcategory scope;
- active scope;
- public-visible scopes;
- deterministic order by `sort_order`, then `id`.

No separate `Subcategory` database model is required. A dedicated domain or
resource naming layer may distinguish Categories and Subcategories while using
the same `Category` Eloquent model.

---

## 7. Localized Content Rules

### 7.1 Required Names

Both localized names are mandatory:

```text
nameAr
nameEn
```

Rules:

- administrators provide both values;
- names are plain text;
- names are trimmed;
- empty names are invalid;
- the backend does not translate names automatically.

### 7.2 Optional Bilingual Descriptions

Descriptions use:

```text
descriptionAr
descriptionEn
```

Rules:

- descriptions are optional;
- both may be omitted and stored as `null`;
- if `descriptionAr` is supplied, `descriptionEn` is required;
- if `descriptionEn` is supplied, `descriptionAr` is required;
- empty strings normalize to `null`;
- descriptions are plain text only;
- HTML, Markdown, scripts, embeds, and trusted rich text are prohibited;
- each description has a maximum length of 2000 characters;
- the backend does not machine-translate descriptions;
- descriptions are not part of route identity and are not used for slug
  generation.

This pair rule prevents an item from having public descriptive content in only
one supported language.

### 7.3 Localized Slugs

Both localized slugs must exist in persistence:

```text
slugAr
slugEn
```

During creation, `slugAr` or `slugEn` may be omitted only when the backend can
generate it safely from the matching localized name.

Description content must never be used to generate a slug.

### 7.4 Arabic Slug

The Arabic slug:

- may contain normalized Arabic Unicode letters;
- may contain numbers;
- uses `-` between words;
- must not contain `/`, `?`, `#`, control characters, or unsafe URL schemes;
- must be normalized consistently before uniqueness validation;
- must not be transliterated into English automatically.

Example:

```text
خدمات-الصيانة
```

### 7.5 English Slug

The English slug:

- is lowercase;
- uses ASCII letters, numbers, and `-`;
- removes repeated separators;
- removes leading/trailing separators;
- is normalized consistently before uniqueness validation.

Example:

```text
maintenance-services
```

### 7.6 Stable Slugs

After creation:

- changing `nameAr` does not change `slugAr` automatically;
- changing `nameEn` does not change `slugEn` automatically;
- changing either description does not change either slug;
- a slug changes only when the admin explicitly submits a new slug;
- existing URLs remain stable unless the admin deliberately changes the slug.

Redirect history for changed slugs is outside the MVP.

---

## 8. Public Visibility Rules

## 8.1 Category Visibility

A root Category is publicly visible only when:

```text
deleted_at IS NULL
AND parent_id IS NULL
AND is_active = true
```

## 8.2 Subcategory Visibility

A Subcategory is publicly visible only when:

```text
subcategory.deleted_at IS NULL
AND subcategory.is_active = true
AND parent.deleted_at IS NULL
AND parent.is_active = true
AND parent.parent_id IS NULL
```

## 8.3 Future Service Visibility

Feature 004 must enforce:

```text
service is active and not deleted
AND subcategory is active and not deleted
AND root category is active and not deleted
```

Disabling an ancestor does not mutate descendant `is_active` values. Visibility
is calculated at query time.

## 8.4 Public Non-Disclosure

For public detail endpoints, all of the following return `404`:

- unknown slug;
- soft-deleted record;
- inactive record;
- active Subcategory with inactive parent;
- active Subcategory with deleted parent;
- slug supplied for the wrong resolved locale.

The public API must not disclose whether a hidden record exists.

---

## 9. Deletion and Restoration Rules

## 9.1 Delete Category

A root Category can be soft-deleted only when it has no non-deleted
Subcategories.

The dependency check includes active and inactive Subcategories as long as:

```text
deleted_at IS NULL
```

If a non-deleted Subcategory exists, return:

```text
HTTP 409 Conflict
code: CATEGORY_HAS_SUBCATEGORIES
```

No child record is deleted automatically.

## 9.2 Delete Subcategory

A Subcategory can be soft-deleted only when it has no non-deleted Services.

Feature 004 must provide the relationship and final query used by this check.

If a non-deleted Service exists, return:

```text
HTTP 409 Conflict
code: SUBCATEGORY_HAS_SERVICES
```

No Service is deleted automatically.

Before Feature 004 exists, the implementation must preserve the contract
boundary without inventing placeholder Service data.

## 9.3 Restore Category

Restoring a root Category:

- restores only that Category;
- does not restore deleted Subcategories;
- preserves child states;
- does not activate the Category automatically;
- preserves its stored `is_active` value.

Attempting to restore a record that is not deleted returns the approved
conflict contract.

## 9.4 Restore Subcategory

A Subcategory can be restored only when its root Category:

- exists;
- is a root Category;
- is not soft-deleted.

The parent may be inactive; restoration is still allowed, but the restored
Subcategory remains hidden publicly until the parent becomes active.

If the parent is deleted, return:

```text
HTTP 409 Conflict
code: PARENT_CATEGORY_DELETED
```

Restoring a Subcategory does not restore Services.

## 9.5 No Force Delete

No endpoint, Action, service, command, or task may force-delete Categories or
Subcategories in the MVP.

---

## 10. Ordering Rules

## 10.1 Storage

Every record has:

```text
sort_order
```

The public and default admin ordering is:

```text
sort_order ASC
id ASC
```

The ID tie-breaker is mandatory for deterministic output.

## 10.2 Root Category Reordering

The admin submits all or a validated subset of non-deleted root Category IDs in
the desired order.

The backend:

1. validates that every ID is unique;
2. validates that every ID belongs to a non-deleted root Category;
3. rejects Subcategory IDs;
4. locks the selected rows;
5. assigns deterministic values such as `10, 20, 30...`;
6. commits atomically.

## 10.3 Subcategory Reordering

The admin submits Subcategory IDs under a specific Category.

The backend:

1. validates uniqueness;
2. validates that every ID belongs to the route Category;
3. rejects IDs belonging to another Category;
4. rejects root Category IDs;
5. locks the selected rows;
6. updates ordering atomically.

A nested parent mismatch returns `404` to avoid acting on a foreign nested
resource.

## 10.4 Concurrency

Reordering uses a short MySQL transaction and row locking. Tests must prove
that a request cannot partially apply an ordering update.

No distributed lock or Redis is required.

---

## 11. Permissions

Create and assign these permissions through the approved Feature permission
seeder:

```text
categories.view
categories.create
categories.update
categories.delete
categories.restore
categories.reorder

subcategories.view
subcategories.create
subcategories.update
subcategories.delete
subcategories.restore
subcategories.reorder
```

Rules:

- each permission is independent;
- `view` does not imply mutation;
- `update` does not imply reorder;
- `delete` does not imply restore;
- Super Admin receives every permission registered by this Feature;
- the Feature seeder is idempotent;
- existing permissions are not removed;
- no `Gate::before` hidden bypass is used as the primary authorization model.

Public endpoints require no authentication permission.

---

## 12. Canonical Admin API Routes

All admin routes use:

```text
/api/v1/admin
```

They require:

```text
auth:sanctum
-> EnsureUserIsAdministrator
-> EnsureAdminIsActive
-> permission middleware
```

### 12.1 Categories

```http
GET    /api/v1/admin/categories
POST   /api/v1/admin/categories
PATCH  /api/v1/admin/categories/reorder
GET    /api/v1/admin/categories/{category}
PATCH  /api/v1/admin/categories/{category}
DELETE /api/v1/admin/categories/{category}
POST   /api/v1/admin/categories/{category}/restore
```

### 12.2 Nested Subcategories

```http
GET    /api/v1/admin/categories/{category}/subcategories
POST   /api/v1/admin/categories/{category}/subcategories
PATCH  /api/v1/admin/categories/{category}/subcategories/reorder
GET    /api/v1/admin/categories/{category}/subcategories/{subcategory}
PATCH  /api/v1/admin/categories/{category}/subcategories/{subcategory}
DELETE /api/v1/admin/categories/{category}/subcategories/{subcategory}
POST   /api/v1/admin/categories/{category}/subcategories/{subcategory}/restore
```

### 12.3 Route Binding Rules

- `{category}` must resolve only a root Category.
- `{subcategory}` must resolve only a Subcategory belonging to `{category}`.
- Nested mismatch returns `404`.
- Normal show/update/delete bindings exclude soft-deleted records.
- Restore bindings resolve only soft-deleted records.
- A restore request for a non-deleted record returns the approved conflict
  response.
- Request bodies must never override route ownership.

---

## 13. Canonical Public API Routes

```http
GET /api/v1/public/categories
GET /api/v1/public/categories/{categorySlug}
GET /api/v1/public/categories/{categorySlug}/subcategories
GET /api/v1/public/categories/{categorySlug}/subcategories/{subcategorySlug}
```

Rules:

- locale resolves before route-slug lookup;
- Arabic locale uses Arabic slugs;
- English locale uses English slugs;
- only publicly visible records resolve;
- public output is ordered by `sort_order`, then `id`;
- public routes expose no deleted/inactive metadata;
- public endpoints do not expose admin permissions or internal parent IDs.

---

## 14. Admin Request Contracts

## 14.1 Create Category

```http
POST /api/v1/admin/categories
Content-Type: application/json
```

```json
{
  "nameAr": "خدمات المنازل",
  "nameEn": "Home Services",
  "descriptionAr": "خدمات احترافية للمنزل تشمل الصيانة والتركيب.",
  "descriptionEn": "Professional home services including maintenance and installation.",
  "slugAr": "خدمات-المنازل",
  "slugEn": "home-services",
  "sortOrder": 10,
  "isActive": true
}
```

Rules:

- `nameAr` required;
- `nameEn` required;
- `descriptionAr` and `descriptionEn` are optional as a bilingual pair;
- supplying only one localized description is invalid;
- localized slugs may be omitted only for backend generation from the matching
  name;
- `sortOrder` optional;
- `isActive` optional, default `true`;
- `parentId` is forbidden;
- unknown fields are rejected.

## 14.2 Update Category

```http
PATCH /api/v1/admin/categories/{category}
```

Allowed fields:

```text
nameAr
nameEn
descriptionAr
descriptionEn
slugAr
slugEn
sortOrder
isActive
```

At least one allowed field is required.

`parentId` is forbidden.

## 14.3 Create Subcategory

```http
POST /api/v1/admin/categories/{category}/subcategories
```

```json
{
  "nameAr": "صيانة التكييف",
  "nameEn": "Air Conditioning Maintenance",
  "descriptionAr": "صيانة وفحص وتنظيف أنظمة التكييف.",
  "descriptionEn": "Maintenance, inspection, and cleaning of air-conditioning systems.",
  "slugAr": "صيانة-التكييف",
  "slugEn": "air-conditioning-maintenance",
  "sortOrder": 10,
  "isActive": true
}
```

The parent comes only from the route.

`descriptionAr` and `descriptionEn` follow the same optional bilingual-pair
rule as Category descriptions.

## 14.4 Update Subcategory

```http
PATCH /api/v1/admin/categories/{category}/subcategories/{subcategory}
```

Allowed fields:

```text
nameAr
nameEn
descriptionAr
descriptionEn
slugAr
slugEn
sortOrder
isActive
```

Forbidden:

```text
parentId
categoryId
```

## 14.5 Reorder

```json
{
  "orderedIds": [12, 5, 20]
}
```

Rules:

- required array;
- at least one ID;
- integer IDs;
- unique values;
- every ID must belong to the route scope;
- no deleted records;
- no cross-parent IDs;
- no partial update on failure;
- unknown fields are rejected.

---

## 15. Validation Rules

### Names

```text
nameAr:
required on create
string
trimmed
min 2
max 150

nameEn:
required on create
string
trimmed
min 2
max 150
```

Empty strings are invalid.

### Descriptions

```text
descriptionAr:
nullable as part of a bilingual pair
string
trimmed
maximum 2000
plain text
empty string becomes null
required when descriptionEn is non-null

descriptionEn:
nullable as part of a bilingual pair
string
trimmed
maximum 2000
plain text
empty string becomes null
required when descriptionAr is non-null
```

The request is valid when both descriptions are `null`, or when both contain
valid localized text.

Descriptions must not contain trusted HTML or Markdown and must not influence
slug generation.

### Slugs

```text
slugAr:
nullable on create only when generated
string
max 180
normalized Arabic URL slug
unique in categories.slug_ar, ignoring current row on update

slugEn:
nullable on create only when generated
string
max 180
lowercase ASCII URL slug
unique in categories.slug_en, ignoring current row on update
```

### State and Ordering

```text
isActive:
boolean

sortOrder:
integer
minimum 0
```

### Unknown and Privileged Fields

Reject fields including:

```text
id
parentId
categoryId
deletedAt
createdAt
updatedAt
servicesCount
subcategoriesCount
```

The backend owns IDs, hierarchy, timestamps, deletion state, and calculated
counts.

---

## 16. Admin Response Resources

`Accept-Language` resolves before Admin index serialization.

The index endpoints intentionally avoid returning duplicate Arabic and English
content columns. Detail, create, update, restore, and other single-resource
responses remain bilingual because the Admin edit form needs both languages.

## 16.1 Category Index Item

Arabic example:

```json
{
  "id": 1,
  "name": "خدمات المنازل",
  "description": "خدمات احترافية للمنزل تشمل الصيانة والتركيب.",
  "slug": "خدمات-المنازل",
  "sortOrder": 10,
  "isActive": true,
  "subcategoriesCount": 4,
  "createdAt": "2026-07-28T10:00:00Z",
  "updatedAt": "2026-07-28T10:00:00Z",
  "deletedAt": null
}
```

English requests return the same keys with English content.

## 16.2 Category Detail and Mutation Resource

```json
{
  "id": 1,
  "nameAr": "خدمات المنازل",
  "nameEn": "Home Services",
  "descriptionAr": "خدمات احترافية للمنزل تشمل الصيانة والتركيب.",
  "descriptionEn": "Professional home services including maintenance and installation.",
  "slugAr": "خدمات-المنازل",
  "slugEn": "home-services",
  "sortOrder": 10,
  "isActive": true,
  "subcategoriesCount": 4,
  "createdAt": "2026-07-28T10:00:00Z",
  "updatedAt": "2026-07-28T10:00:00Z",
  "deletedAt": null
}
```

`subcategoriesCount` counts non-deleted Subcategories unless the endpoint
explicitly documents a different admin filter.

## 16.3 Subcategory Index Item

Arabic example:

```json
{
  "id": 10,
  "name": "صيانة التكييف",
  "description": "صيانة وفحص وتنظيف أنظمة التكييف.",
  "slug": "صيانة-التكييف",
  "sortOrder": 10,
  "isActive": true,
  "createdAt": "2026-07-28T10:00:00Z",
  "updatedAt": "2026-07-28T10:00:00Z",
  "deletedAt": null
}
```

The parent is already identified by the nested route, so the Subcategory index
item does not repeat a Category summary.

## 16.4 Subcategory Detail and Mutation Resource

```json
{
  "id": 10,
  "nameAr": "صيانة التكييف",
  "nameEn": "Air Conditioning Maintenance",
  "descriptionAr": "صيانة وفحص وتنظيف أنظمة التكييف.",
  "descriptionEn": "Maintenance, inspection, and cleaning of air-conditioning systems.",
  "slugAr": "صيانة-التكييف",
  "slugEn": "air-conditioning-maintenance",
  "sortOrder": 10,
  "isActive": true,
  "category": {
    "id": 1,
    "nameAr": "خدمات المنازل",
    "nameEn": "Home Services",
    "descriptionAr": "خدمات احترافية للمنزل تشمل الصيانة والتركيب.",
    "descriptionEn": "Professional home services including maintenance and installation.",
    "slugAr": "خدمات-المنازل",
    "slugEn": "home-services"
  },
  "createdAt": "2026-07-28T10:00:00Z",
  "updatedAt": "2026-07-28T10:00:00Z",
  "deletedAt": null
}
```

`servicesCount` is introduced only after Feature 004 provides a real Service
relationship. Feature 003 must not return fabricated values.

---

## 17. Public Response Resources

## 17.1 Public Category

For Arabic:

```json
{
  "name": "خدمات المنازل",
  "description": "خدمات احترافية للمنزل تشمل الصيانة والتركيب.",
  "slug": "خدمات-المنازل",
  "subcategories": [
    {
      "name": "صيانة التكييف",
      "description": "صيانة وفحص وتنظيف أنظمة التكييف.",
      "slug": "صيانة-التكييف"
    }
  ]
}
```

For English:

```json
{
  "name": "Home Services",
  "description": "Professional home services including maintenance and installation.",
  "slug": "home-services",
  "subcategories": [
    {
      "name": "Air Conditioning Maintenance",
      "description": "Maintenance, inspection, and cleaning of air-conditioning systems.",
      "slug": "air-conditioning-maintenance"
    }
  ]
}
```

The list endpoint may return active Subcategories nested for catalog navigation.

## 17.2 Public Subcategory

```json
{
  "name": "Air Conditioning Maintenance",
  "description": "Maintenance, inspection, and cleaning of air-conditioning systems.",
  "slug": "air-conditioning-maintenance",
  "category": {
    "name": "Home Services",
    "description": "Professional home services including maintenance and installation.",
    "slug": "home-services"
  }
}
```

Public Resources return `description: null` when the bilingual description
pair is omitted.

## 17.3 Public Locale Metadata

Public response `data` remains projected into the resolved locale only.

Every public response includes:

```http
Content-Language: ar|en
Vary: Accept-Language
```

Public success envelopes include:

```json
{
  "meta": {
    "locale": "ar",
    "direction": "rtl"
  }
}
```

Slug-bound public routes also include alternate API links inside response
metadata so the frontend can switch language without guessing the translated
slug:

```json
{
  "meta": {
    "locale": "ar",
    "direction": "rtl",
    "localeLinks": {
      "ar": "/api/v1/public/categories/خدمات-المنازل",
      "en": "/api/v1/public/categories/home-services"
    }
  }
}
```

For Subcategory detail, each alternate link contains both the localized parent
slug and localized Subcategory slug.

`localeLinks` is navigation metadata only. It must not expose the other
language's name or description and must not weaken locale-specific slug lookup.


Public Resources must not expose:

- internal IDs;
- both language columns;
- `parent_id`;
- `is_active`;
- `sort_order`;
- deletion timestamps;
- admin counts;
- permissions.

---

## 18. Listing, Search, Filters, and Pagination

Admin Category and Subcategory indexes use `spatie/laravel-query-builder`.

## 18.1 Query Syntax

Filters use Query Builder's bracket syntax:

```text
filter[search]
filter[isActive]
filter[trashed]
```

Sorting uses Query Builder's native `sort` parameter:

```text
sort=sortOrder
sort=-createdAt
sort=name
sort=-name
```

Pagination remains:

```text
page
perPage
```

Example:

```http
GET /api/v1/admin/categories?filter[search]=home&filter[isActive]=true&filter[trashed]=without&sort=sortOrder&page=1&perPage=20
```

## 18.2 Allowed Filters

```text
filter[search]:
optional string, maximum 255

filter[isActive]:
optional boolean

filter[trashed]:
without | with | only
default: without
```

`filter[search]` is one custom Query Builder filter that searches:

```text
name_ar
name_en
description_ar
description_en
slug_ar
slug_en
```

Search checks both languages regardless of response locale.

## 18.3 Allowed Sorts

```text
sortOrder
name
createdAt
updatedAt
```

Prefixing an allowed sort with `-` means descending order.

`sort=name` maps to `name_ar` when the resolved locale is Arabic and to
`name_en` when the resolved locale is English.

Defaults:

```text
filter[trashed] = without
sort = sortOrder
page = 1
perPage = project default
```

`perPage` must be capped by the shared API standard.

Raw client column names such as `name_ar`, `deleted_at`, or arbitrary SQL
expressions are forbidden.

## 18.4 Admin Category Index Projection

The Category index returns localized `name`, `description`, and `slug` only.
It also returns operational fields required by the Dashboard:

```text
id
sortOrder
isActive
subcategoriesCount
createdAt
updatedAt
deletedAt
```

It must not return:

```text
nameAr
nameEn
descriptionAr
descriptionEn
slugAr
slugEn
```

## 18.5 Admin Subcategory Index Projection

The nested Subcategory index uses the same localized content projection and
Query Builder filter/sort contract.

It remains strictly scoped to the route Category and must never return a
Subcategory belonging to another parent.

## 18.6 Public Lists

Public lists:

- are not paginated in the MVP;
- contain only public-visible records;
- are ordered by `sort_order`, then `id`;
- do not accept admin filters;
- do not support `trashed`;
- do not reveal inactive records.

---

## 19. HTTP and Error Contracts

Use the shared success and error envelopes.

### Standard Success Envelope

```json
{
  "success": true,
  "message": "Localized message",
  "data": {}
}
```

### Standard Error Envelope

```json
{
  "success": false,
  "message": "Localized safe message",
  "code": "STABLE_ENGLISH_CODE",
  "errors": null
}
```

### Required Error Codes

```text
UNAUTHENTICATED
FORBIDDEN
VALIDATION_ERROR
CATEGORY_NOT_FOUND
SUBCATEGORY_NOT_FOUND
RESOURCE_NOT_FOUND
CATEGORY_HAS_SUBCATEGORIES
SUBCATEGORY_HAS_SERVICES
PARENT_CATEGORY_DELETED
CATEGORY_NOT_DELETED
SUBCATEGORY_NOT_DELETED
RATE_LIMITED
INTERNAL_ERROR
```

Nested parent mismatch uses the approved `404` contract and must not reveal the
foreign resource.

### Recommended Status Mapping

```text
200 OK:
list, show, update, reorder, restore, and successful soft delete

201 Created:
create

401 Unauthorized:
missing/invalid access token

403 Forbidden:
missing permission or invalid administration actor

404 Not Found:
missing/hidden public record or nested mismatch

409 Conflict:
dependency-blocked delete, invalid restore state, deleted parent

422 Unprocessable Content:
validation failure

429 Too Many Requests:
rate limit

500 Internal Server Error:
safe internal failure
```

Successful Category and Subcategory deletion MUST return the shared success
envelope with `data: null`; this feature does not use `204 No Content`.

---

## 20. Controllers, Requests, Actions, Resources, and Queries

## 20.1 Admin Controllers

Suggested conventional structure:

```text
app/Http/Controllers/Api/V1/Admin/Catalog/
├── CategoryController.php
└── SubcategoryController.php
```

Controllers must remain thin.

## 20.2 Public Controllers

```text
app/Http/Controllers/Api/V1/Public/Catalog/
├── CategoryController.php
└── SubcategoryController.php
```

Public and admin Resources must remain separate.

## 20.3 Form Requests

```text
app/Http/Requests/Api/V1/Admin/Catalog/
├── StoreCategoryRequest.php
├── UpdateCategoryRequest.php
├── ReorderCategoriesRequest.php
├── StoreSubcategoryRequest.php
├── UpdateSubcategoryRequest.php
└── ReorderSubcategoriesRequest.php
```

List query validation may use dedicated Requests when required by project
standards.

## 20.4 Actions

```text
app/Actions/Catalog/Categories/
├── CreateCategoryAction.php
├── UpdateCategoryAction.php
├── DeleteCategoryAction.php
├── RestoreCategoryAction.php
└── ReorderCategoriesAction.php

app/Actions/Catalog/Subcategories/
├── CreateSubcategoryAction.php
├── UpdateSubcategoryAction.php
├── DeleteSubcategoryAction.php
├── RestoreSubcategoryAction.php
└── ReorderSubcategoriesAction.php
```

Create/update may be direct CRUD only if the governing code standard permits
and no transaction or reusable rule justifies an Action. Delete, restore, and
reorder rules should remain explicit because they contain dependency or
transactional behavior.

## 20.5 Query Classes

Admin list queries may use focused query classes:

```text
app/Queries/Admin/Catalog/
├── CategoryListQuery.php
└── SubcategoryListQuery.php
```

Do not create repositories.

Public queries may use focused model scopes or a small catalog query service.

## 20.6 Resources

```text
app/Http/Resources/Api/V1/Admin/Catalog/
├── CategoryResource.php
└── SubcategoryResource.php

app/Http/Resources/Api/V1/Public/Catalog/
├── CategoryResource.php
└── SubcategoryResource.php
```

---

## 21. Transaction Boundaries

Use short MySQL transactions only where consistency requires them.

### Required Transactions

- reorder Categories;
- reorder Subcategories;
- delete Category dependency check plus deletion;
- delete Subcategory dependency check plus deletion;
- restore Subcategory with parent-state revalidation;
- any concurrent write where the governing plan identifies a race.

### Row Locking

Reorder Actions lock affected rows.

Delete Actions re-check dependencies inside the transaction before deleting.

Restore Subcategory locks or safely revalidates the parent to prevent restoring
under a concurrently deleted Category.

Avoid external work inside transactions.

This feature performs no email, file, network, or queue work.

---

## 22. Security Requirements

- Backend is authoritative for hierarchy and ownership.
- Never trust `parentId` or `categoryId` from request bodies.
- Nested route binding must enforce parent-child ownership.
- Unknown fields are rejected.
- Slugs are normalized before validation and persistence.
- Slugs are never used to construct filesystem paths.
- Public hidden records return `404`.
- Admin output may expose IDs but never raw SQL or internal exception data.
- Every admin route requires authentication, administrator type, active state,
  and the exact permission.
- CORS uses the configured allow-list.
- No wildcard credentialed admin CORS.
- Logs omit access tokens, Cookie/Authorization headers, and sensitive request
  bodies.
- Search and sorting use allow-listed fields only.
- Raw client sort columns are forbidden.
- Rate limits follow the shared standards.
- `APP_DEBUG=false` in production.

---

## 23. Localization Requirements

- Supported locales are `ar` and `en`.
- Arabic is default.
- English is fallback.
- Locale resolves before validation.
- Admin detail and mutation Resources return both localized names,
  descriptions, and slugs.
- Admin Category and Subcategory index Resources return resolved-locale
  `name`, `description`, and `slug` only.
- Public Resource content returns only the resolved-locale `name`,
  `description`, and `slug`.
- Slug-bound public responses may expose alternate localized API URLs only
  through `meta.localeLinks`.
- API messages use translation keys.
- Validation messages are translated.
- Stable codes, permissions, route segments, query parameters, and JSON keys
  remain English.
- Responses include the approved `Content-Language`.
- Responses include the approved `Vary: Accept-Language`.
- Public slug lookup uses the resolved locale column.
- User-provided names, descriptions, and slugs are preserved after approved
  normalization and are not machine-translated.
- Description localization is content localization, not API-message
  localization.
- The backend never copies one localized description into the other language.

---

## 24. Postman Requirements

The Postman collection must include all canonical admin and public operations.

Required coverage:

- create Category with bilingual name and description content;
- list Categories using `filter[key]` and `sort`, verifying localized index
  projection;
- show Category;
- update Category name, description, slug, state, and ordering;
- delete Category;
- blocked Category deletion;
- restore Category;
- reorder Categories;
- create Subcategory with bilingual name and description content;
- list nested Subcategories;
- show nested Subcategory;
- update Subcategory name, description, slug, state, and ordering;
- delete Subcategory;
- blocked Subcategory deletion after Feature 004 integration;
- restore Subcategory;
- reorder Subcategories;
- Arabic public list/detail;
- English public list/detail;
- bilingual-description pair validation;
- public localized description output;
- `/api/v1/public/categories*` route prefix;
- `Content-Language` and `Vary: Accept-Language` response headers;
- `meta.localeLinks` on slug-bound public responses;
- `200` success envelope for Category and Subcategory soft deletion;
- inactive/deleted public `404`;
- validation failures;
- unauthenticated and forbidden admin failures;
- nested parent mismatch;
- placeholder-only variables;
- no real credentials.

Postman examples must stay synchronized with OpenAPI and automated tests.

---

## 25. Testing Strategy

Use Pest with the dedicated MySQL test database.

SQLite-only evidence is insufficient.

## 25.1 Database Tests

Test:

- schema columns, including `description_ar` and `description_en`;
- nullable bilingual-description pair persistence;
- self foreign key;
- unique Arabic slug;
- unique English slug;
- indexes;
- SoftDeletes;
- model casts;
- root/child relationships;
- third-level prevention;
- no plaintext or unsafe data behavior.

## 25.2 Category Feature Tests

Test:

- admin list;
- search Arabic and English;
- filters;
- pagination;
- create with explicit slugs;
- create with generated slugs;
- duplicate slug validation;
- create with both localized descriptions;
- create with both descriptions omitted;
- reject a one-language-only description;
- description trimming, empty-to-null normalization, and max length;
- reject trusted HTML/Markdown description input according to the strict text
  contract;
- update name without changing slug;
- explicit slug update;
- active-state update;
- dependency-blocked delete;
- successful soft delete;
- restore;
- restore non-deleted conflict;
- reorder;
- invalid reorder IDs;
- permissions independently;
- unauthenticated, non-admin, inactive-admin, and forbidden cases;
- exact response fields;
- localization;
- unknown-field rejection.

## 25.3 Subcategory Feature Tests

Test:

- parent must be root;
- nested parent mismatch;
- create;
- update;
- immutable parent;
- create/update bilingual descriptions;
- reject partial bilingual descriptions;
- list scoping;
- active-state update;
- dependency-blocked delete after Service integration;
- successful soft delete;
- restore with active parent;
- restore with inactive parent;
- restore blocked by deleted parent;
- reorder within parent;
- cross-parent reorder rejection;
- third-level prevention;
- permissions independently;
- exact response fields;
- localization.

## 25.4 Public API Tests

Test:

- Arabic category list;
- English category list;
- Arabic slug resolution;
- English slug resolution;
- Arabic localized description output;
- English localized description output;
- `description: null` when both stored descriptions are null;
- active records only;
- deleted records hidden;
- inactive Category hidden;
- Subcategory hidden when parent inactive;
- Subcategory hidden when parent deleted;
- deterministic ordering;
- localized response fields only;
- no internal IDs or active/deleted metadata;
- public detail `404` non-disclosure.

## 25.5 Concurrency Tests

Use real MySQL tests for:

- atomic Category reorder;
- atomic Subcategory reorder;
- Category delete racing with Subcategory creation;
- Subcategory restore racing with parent deletion where practical.

The tests must prove no partial reorder and no invalid visible hierarchy.

## 25.6 Architecture Tests

Verify:

- no third hierarchy endpoint;
- no force-delete route;
- no separate category media or file-upload route; the optional image is sent
  directly with create or update multipart form-data;
- admin routes use authentication, administrator-type, active-state, and
  permission middleware;
- public routes have no write methods;
- controllers remain thin;
- no repositories;
- no Queue Job, Command, Cron, scheduler, Redis, or frontend code is introduced;
- routes and permissions match this reference.

---

## 26. Acceptance Scenarios

### Scenario A — Create Root Category

Given an authenticated active Super Admin with `categories.create`, when valid
Arabic and English names plus a valid bilingual description pair are
submitted, then one root Category is created with `parent_id = null` and
returned through the admin resource with both localized descriptions.

### Scenario B — Create Subcategory

Given an existing non-deleted root Category and an authorized admin, when a
valid nested create request containing Arabic and English names and
descriptions is submitted, then one Subcategory is created under that route
Category.

### Scenario C — Reject Third Level

Given a Subcategory, when the system is asked to use it as a parent, then the
request is rejected and no record is created.

### Scenario D — Stable Slugs

Given an existing Category, when only its Arabic or English name changes, then
the stored localized slugs remain unchanged.

### Scenario E — Bilingual Public Slugs

Given the same Category, when an Arabic request uses its Arabic slug it
resolves with the Arabic name and description, and when an English request uses
its English slug it resolves with the English name and description.

### Scenario F — Parent Visibility

Given an active Subcategory under an inactive Category, when a public request
is made, then the Subcategory is not returned and its detail endpoint returns
`404`.

### Scenario G — Block Category Deletion

Given a Category with a non-deleted Subcategory, when deletion is attempted,
then the API returns `409 CATEGORY_HAS_SUBCATEGORIES` and neither record is
deleted.

### Scenario H — Delete Without Cascade

Given a Category whose Subcategories are already soft-deleted, when the
Category is deleted, then only the Category is soft-deleted and no child is
force-deleted.

### Scenario I — Restore Category

Given a deleted Category, when restoration succeeds, then only the Category is
restored and its deleted Subcategories remain deleted.

### Scenario J — Restore Subcategory Under Deleted Parent

Given a deleted Subcategory whose parent is also deleted, when restoration is
attempted, then the API returns `409 PARENT_CATEGORY_DELETED`.

### Scenario K — Atomic Reorder

Given valid Category IDs, when they are reordered, then all selected ordering
values update atomically and public output reflects the new deterministic order.

### Scenario L — Nested Mismatch

Given a Subcategory belonging to Category A, when it is requested through
Category B, then the API returns `404` and performs no mutation.

### Scenario M — Optional Bilingual Description Pair

Given a Category or Subcategory with no descriptions, when both description
fields are omitted, then the record is valid and public output returns
`description: null`.

Given only one localized description is submitted, when validation runs, then
the request fails and no partial localized description is persisted.

---

## 27. Requirement Registry

### Functional Requirements

- **FR-001**: The system MUST represent Categories and Subcategories in one
  self-referencing `categories` table.
- **FR-002**: A root Category MUST have `parent_id = null`.
- **FR-003**: A Subcategory MUST have exactly one non-deleted root Category as
  its parent.
- **FR-004**: A third hierarchy level MUST be rejected.
- **FR-005**: Subcategory parent transfer MUST NOT be supported in the MVP.
- **FR-006**: Arabic and English names MUST be required.
- **FR-006A**: Arabic and English descriptions MUST be supported for both
  Categories and Subcategories.
- **FR-006B**: Descriptions MUST be optional as a bilingual pair: both may be
  null, but one localized description MUST NOT be persisted without the other.
- **FR-006C**: Descriptions MUST be trimmed plain text, limited to 2000
  characters per locale, with empty strings normalized to `null`.
- **FR-006D**: The backend MUST NOT machine-translate names or descriptions.
- **FR-007**: Arabic and English slugs MUST be persisted.
- **FR-008**: Localized slugs MAY be generated on create when omitted, using
  the matching localized name only and never a description.
- **FR-009**: Existing slugs MUST remain unchanged when only names or
  descriptions change.
- **FR-010**: Arabic and English slug columns MUST each be unique.
- **FR-011**: Category and Subcategory ordering MUST use `sort_order`.
- **FR-012**: Default output ordering MUST use `sort_order`, then `id`.
- **FR-013**: Category and Subcategory active states MUST be independent stored
  values.
- **FR-014**: Disabling a Category MUST hide descendants publicly without
  mutating descendant active states.
- **FR-015**: Disabling a Subcategory MUST hide descendant Services publicly
  without mutating Service active states.
- **FR-016**: Categories and Subcategories MUST use SoftDeletes.
- **FR-017**: Force deletion MUST NOT exist in the MVP.
- **FR-018**: Category deletion MUST be blocked by non-deleted Subcategories.
- **FR-019**: Subcategory deletion MUST be blocked by non-deleted Services.
- **FR-020**: Deletion MUST NOT cascade.
- **FR-021**: Restoration MUST affect only the requested record.
- **FR-022**: Subcategory restoration MUST be blocked while its parent is
  deleted.
- **FR-023**: Admin detail and mutation APIs MUST return both localized
  names, descriptions, and slugs.
- **FR-023A**: Admin Category and Subcategory index APIs MUST return only
  resolved-locale `name`, `description`, and `slug`, plus approved operational
  fields.
- **FR-024**: Public APIs MUST return only the resolved-locale name,
  description, and slug.
- **FR-025**: Public visibility MUST require active, non-deleted ancestors.
- **FR-026**: Hidden public records MUST return `404`.
- **FR-027**: Each Category and Subcategory MAY have one optional image.
- **FR-028**: The image MUST NOT be required on create or update.
- **FR-029**: A text `image` value, including an empty string, MUST be ignored;
  only an uploaded file MAY create or replace the stored image.
- **FR-030**: The feature MUST NOT introduce multiple images, icons, video,
  or attachment behavior for Categories or Subcategories.
- **FR-031**: Admin list APIs MUST support approved search, filters, sorting,
  and pagination.
- **FR-032**: Public lists MUST be ordered and unpaginated in the MVP.
- **FR-033**: Reordering MUST be atomic and scope validated.
- **FR-034**: Request bodies MUST NOT control hierarchy ownership.
- **FR-035**: Admin routes MUST apply authentication, administrator type,
  active-state, and permission controls independently.
- **FR-036**: Public routes MUST be read-only.
- **FR-037**: The Postman collection MUST cover all canonical operations.
- **FR-038**: Tests MUST use MySQL for hierarchy and concurrency behavior.

### Authorization Requirements

- **AUTH-001**: `categories.*` and `subcategories.*` permissions MUST remain
  independent.
- **AUTH-002**: Super Admin MUST receive every permission registered by this
  Feature.
- **AUTH-003**: Client-supplied hierarchy, timestamps, deletion state, and
  calculated counts MUST be rejected or ignored according to the request
  contract.
- **AUTH-004**: Nested route ownership MUST be backend enforced.
- **AUTH-005**: Public visibility MUST NOT be treated as admin authorization.

### Data Integrity Requirements

- **DATA-001**: Both slug columns MUST have unique database indexes.
- **DATA-002**: `parent_id` MUST reference `categories.id` with restrictive
  physical deletion behavior.
- **DATA-003**: Application validation MUST enforce the two-level hierarchy.
- **DATA-004**: Reordering MUST use transactions and row locks.
- **DATA-005**: Delete dependency checks MUST be revalidated inside the delete
  transaction.
- **DATA-006**: Restore Subcategory MUST revalidate parent state before commit.
- **DATA-007**: No request may create an orphaned Subcategory.
- **DATA-008**: `description_ar` and `description_en` MUST be nullable together
  or non-null together after request normalization.

### API Requirements

- **API-001**: Admin routes MUST use the exact canonical paths in this document.
- **API-002**: Public routes MUST use the exact canonical paths in this
  document.
- **API-003**: Requests and responses MUST use shared envelopes and camelCase
  JSON fields.
- **API-004**: Admin detail and mutation Resources MUST return both
  localized names, descriptions, and slugs.
- **API-004A**: Admin index Resources MUST use resolved-locale `name`,
  `description`, and `slug` keys and MUST NOT expose duplicate localized
  content columns.
- **API-004B**: Admin index filtering MUST use Query Builder parameters
  `filter[search]`, `filter[isActive]`, and `filter[trashed]`; sorting MUST use
  the allow-listed `sort` parameter.
- **API-005**: Public Resources MUST return resolved-locale `name`,
  `description`, and `slug` only.
- **API-006**: Nested parent mismatch MUST return `404`.
- **API-007**: Dependency-blocked deletion MUST return `409`.
- **API-008**: Unknown request fields MUST be rejected.
- **API-009**: Locale MUST resolve before public slug route lookup.

### Verification Requirements

- **VER-001**: Tests MUST cover every canonical admin and public operation.
- **VER-002**: Tests MUST prove third-level creation is impossible.
- **VER-003**: Tests MUST prove public ancestor visibility rules.
- **VER-004**: Tests MUST prove slugs remain stable after name-only or
  description-only updates.
- **VER-004A**: Tests MUST prove descriptions may be omitted as a pair, partial
  bilingual descriptions are rejected, and public Resources resolve the correct
  localized description.
- **VER-005**: Tests MUST prove deletion does not cascade.
- **VER-006**: Tests MUST prove restore does not restore descendants.
- **VER-007**: Tests MUST prove atomic reorder behavior on MySQL.
- **VER-008**: Tests MUST prove independent permissions.
- **VER-009**: Tests MUST prove nested parent mismatch behavior.
- **VER-010**: Architecture tests MUST prove no force-delete, third-level,
  file-upload, Queue, Command, Cron, scheduler, Redis, or frontend scope exists.
- **VER-011**: OpenAPI and Postman contracts MUST remain synchronized with
  implementation and tests.
- **VER-012**: Feature 004 integration MUST test the
  `SUBCATEGORY_HAS_SERVICES` deletion rule.

---

## 28. Suggested Implementation Sequence

1. Add migration and `Category` model.
2. Add hierarchy scopes and relationships.
3. Add permission seeder entries.
4. Add admin Requests and Resources.
5. Add Category admin routes and behavior.
6. Add Subcategory nested admin routes and behavior.
7. Add delete/restore dependency rules.
8. Add transactional reorder Actions.
9. Add public localized Resources and routes.
10. Add Arabic and English translations.
11. Add OpenAPI contracts.
12. Add Postman requests and examples.
13. Add database and Feature Tests.
14. Add MySQL concurrency tests.
15. Add architecture tests.
16. Run Pint, Larastan/PHPStan, and the full Pest suite.
17. Verify Definition of Done.

---

## 29. Definition of Done

Feature 003 is complete only when:

- one self-referencing table is used;
- no third hierarchy level can be created;
- names and slugs exist in Arabic and English;
- Arabic and English descriptions are supported as an optional bilingual pair;
- partial one-language-only descriptions are rejected;
- descriptions remain plain text and are never machine-translated;
- slug generation and stable update behavior are tested;
- Category and Subcategory admin CRUD is complete;
- Soft Delete and Restore are complete;
- force deletion does not exist;
- dependency-blocked deletion works;
- no cascade delete exists;
- restoring a record does not restore descendants;
- Category and Subcategory ordering is atomic;
- public visibility inheritance is correct;
- disabling ancestors does not mutate descendant flags;
- public Arabic and English slug resolution works;
- public Arabic and English description resolution works;
- public hidden records return `404`;
- admin search, filters, sorting, and pagination work;
- permissions are independently enforced;
- OpenAPI is valid;
- Postman is complete and contains no real secrets;
- MySQL Feature and concurrency tests pass;
- architecture tests pass;
- Pint passes;
- Larastan/PHPStan passes;
- no Queue Job, Command, Cron, scheduler, Redis, file upload, force-delete,
  customer-authentication, frontend, or unrelated scope is introduced;
- no approved project behavior is weakened.

---

## 30. Non-Negotiable Summary

```text
One categories table.
Two levels only.
Category -> Subcategory -> Service.
Arabic and English names.
Arabic and English descriptions as an optional complete pair.
Arabic and English slugs.
Descriptions are plain text and never machine-translated.
Stable localized slugs generated from names only.
Soft Delete and Restore.
No force delete.
No cascade delete.
Block Category delete when non-deleted Subcategories exist.
Block Subcategory delete when non-deleted Services exist.
Manual sort_order.
Independent is_active flags.
Inactive ancestor hides descendants publicly.
Text only.
Admin bilingual name, description, and slug output.
Public localized output.
MySQL transactions for reorder and dependency-sensitive writes.
Independent permissions.
No customer authentication.
No Queue, Cron, Redis, or frontend scope.
```
