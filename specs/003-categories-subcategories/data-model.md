# Feature 003 Data Model — Categories and Subcategories

**Feature:** `003-categories-subcategories`  
**Date:** 2026-07-29  
**Status:** Design Baseline

## 1. Overview

Feature 003 introduces one persisted domain entity that expresses two catalog
roles:

- `Category` (root node)
- `Subcategory` (child node)

The model must preserve:

- exactly two hierarchy levels
- bilingual names and localized slugs for every record
- optional bilingual descriptions stored as a complete pair or as two `null`s
- stable slugs that change only by explicit edit
- independent `is_active` state per row
- soft deletion without cascade delete or force delete
- public ancestor visibility inheritance
- atomic reorder behavior inside one scope

## 2. Entity: Category

### Purpose

Represents either:

- a root catalog Category when `parent_id = null`, or
- a Subcategory when `parent_id` references a root Category

### Table

`categories`

### Core Fields

| Field | Type | Nullable | Notes |
|---|---|---:|---|
| `id` | unsigned big integer | No | Primary key |
| `parent_id` | unsigned big integer | Yes | `null` for root Category; root Category id for Subcategory |
| `name_ar` | string(150) | No | Required Arabic name |
| `name_en` | string(150) | No | Required English name |
| `description_ar` | text | Yes | Optional Arabic plain-text description |
| `description_en` | text | Yes | Optional English plain-text description |
| `slug_ar` | string(180) | No | Unique Arabic slug |
| `slug_en` | string(180) | No | Unique English slug |
| `sort_order` | unsigned integer | No | Manual ordering; default `0` |
| `is_active` | boolean | No | Default `true` |
| `created_at` | timestamp | No | Standard timestamps |
| `updated_at` | timestamp | No | Standard timestamps |
| `deleted_at` | timestamp | Yes | Soft delete marker |

### Relationships

- `Category` belongs to optional parent `Category`
- `Category` has many child `Category` records
- a root Category has many Subcategories through `children()`
- a future Feature 004 service relationship will attach to Subcategories only

### Role Distinction

#### Root Category

- `parent_id = null`
- may be referenced as the parent of Subcategories
- can be deleted only when it has no non-deleted Subcategories

#### Subcategory

- `parent_id != null`
- parent must reference a root Category
- cannot itself own child categories
- can be deleted only when it has no non-deleted Services once Feature 004 is integrated

## 3. Invariants

### Hierarchy Invariants

- a root Category must have `parent_id = null`
- a Subcategory must have one parent row
- that parent must exist
- that parent must be a root Category
- a Subcategory cannot become a parent for another category
- request bodies cannot set or mutate `parentId` / `categoryId`

### Content Invariants

- `name_ar` and `name_en` are always required
- `description_ar` and `description_en` must either:
  - both be `null`, or
  - both contain valid plain text
- descriptions are trimmed and empty strings normalize to `null`
- descriptions do not generate slugs
- slugs are stable after create unless explicitly edited

### Slug Invariants

- `slug_ar` is unique across `categories.slug_ar`
- `slug_en` is unique across `categories.slug_en`
- Arabic and English slug normalization rules differ but both must remain URL-safe
- missing localized slugs may be generated from the matching localized name only during create

### Lifecycle Invariants

- all records use soft deletes
- force delete is unavailable
- deleting a parent does not delete descendants
- restoring a parent does not restore descendants
- restoring a Subcategory is blocked when its parent is deleted, missing, or no longer a root Category
- an inactive but non-deleted parent does not block restore, but still hides the Subcategory publicly

### Visibility Invariants

- public visibility requires the current row to be active and not deleted
- a Subcategory is publicly visible only when its parent is also active and not deleted
- descendant `is_active` values are not mutated when an ancestor is deactivated

## 4. Derived and Internal Domain Concepts

### 4.1 Node Kind

Kind is derived from `parent_id`:

- `parent_id = null` => root Category
- `parent_id != null` => Subcategory

This does not require a separate persisted enum.

### 4.2 Public Locale Projection

Public responses project:

- `name`
- `description`
- `slug`

from the resolved locale only:

- Arabic => `name_ar`, `description_ar`, `slug_ar`
- English => `name_en`, `description_en`, `slug_en`

### 4.3 Public Response Metadata

Public response content is projected into one locale only.

All public responses carry:

- `Content-Language: ar|en`
- `Vary: Accept-Language`
- `meta.locale`
- `meta.direction`

Slug-bound public responses additionally carry:

```text
meta.localeLinks.ar
meta.localeLinks.en
```

Each link is an alternate API URL under `/api/v1/public/categories*`.
Category detail links contain the localized Category slug. Subcategory detail
links contain both the localized parent slug and localized Subcategory slug.

This metadata is derived at read time and is not persisted.

### 4.4 Admin Counts

The admin resource may expose:

- `subcategoriesCount` for root Categories
- `servicesCount` for Subcategories only when a real service relationship exists

Feature 003 must not fabricate `servicesCount` before Feature 004.

### 4.5 Admin List Filters

Admin list behavior derives from query input rather than stored computed state:

- `search`
- `isActive`
- `trashed`
- `sortBy`
- `sortDirection`
- `page`
- `perPage`

`trashed` controls whether soft-deleted rows are included or isolated.

## 5. Index and Constraint Design

### Required Constraints

- primary key on `id`
- foreign key `parent_id -> categories.id`
- restrictive delete behavior on the self-reference
- unique index on `slug_ar`
- unique index on `slug_en`

### Required Indexes

- index on `parent_id`
- index on `is_active`
- index on `deleted_at`
- composite index on `(parent_id, is_active, sort_order, id)`
- optional supportive search indexes remain implementation-dependent, but raw
  non-indexed unbounded scans must be avoided where practical

### Constraint Strategy Notes

- the database can enforce self-reference existence but not the full two-level
  rule; application logic must ensure a parent is always a root Category
- localized slug uniqueness must be final database protection, not pre-check only
- dependency-blocked delete and restore-parent validation must be rechecked
  inside transactions

## 6. Read Model Expectations

### Admin Root Category Index Item

Index content is projected through `Accept-Language`:

- `id`
- resolved-locale `name`
- resolved-locale `description`
- resolved-locale `slug`
- `sortOrder`
- `isActive`
- `subcategoriesCount`
- `createdAt`
- `updatedAt`
- `deletedAt`

The index item does not expose `nameAr`, `nameEn`, `descriptionAr`,
`descriptionEn`, `slugAr`, or `slugEn`.

### Admin Root Category Detail/Mutation Resource

Safe bilingual output includes:

- `id`
- `nameAr`
- `nameEn`
- `descriptionAr`
- `descriptionEn`
- `slugAr`
- `slugEn`
- `sortOrder`
- `isActive`
- `subcategoriesCount`
- `createdAt`
- `updatedAt`
- `deletedAt`

### Admin Subcategory Index Item

Index content is projected through `Accept-Language`:

- `id`
- resolved-locale `name`
- resolved-locale `description`
- resolved-locale `slug`
- `sortOrder`
- `isActive`
- `createdAt`
- `updatedAt`
- `deletedAt`

The nested route already identifies the parent, so the index item does not
repeat the Category summary.

### Admin Subcategory Detail/Mutation Resource

Safe bilingual output includes:

- bilingual names, descriptions, and slugs;
- `sortOrder` and `isActive`;
- bilingual parent Category summary;
- timestamps and deletion metadata;
- `servicesCount` only after Feature 004 provides real persisted data.

### Admin Query Builder Contract

```text
filter[search]
filter[isActive]
filter[trashed]
sort
page
perPage
```

`filter[search]` searches:

```text
name_ar
name_en
description_ar
description_en
slug_ar
slug_en
```

Allowed sorts:

```text
sortOrder
name
createdAt
updatedAt
```

A `-` prefix means descending. `sort=name` resolves to `name_ar` or `name_en`
according to `Accept-Language`.

### Public Category Resource

Safe public `data` includes only resolved-locale content:

- `name`
- `description`
- `slug`
- nested visible `subcategories`

Public output never exposes:

- internal IDs
- both language columns
- `parent_id`
- `is_active`
- `sort_order`
- deletion metadata
- admin counts
- permissions

### Public Subcategory Resource

Safe public `data` includes:

- resolved-locale `name`
- resolved-locale `description`
- resolved-locale `slug`
- resolved-locale parent category summary

### Public Envelope Metadata

Public list responses include locale and direction metadata.

Category detail, Category-scoped Subcategory list, and Subcategory detail
responses also include Arabic and English alternate API URLs under
`meta.localeLinks`.

Public metadata never contains the alternate language's name or description.
Successful admin soft-delete responses contain `data: null` in the shared
`200` success envelope.

## 7. Lifecycle and State Transitions

### 7.1 Root Category Lifecycle

```text
Active
  -> Inactive
  -> Deleted      (only when no non-deleted Subcategories exist)

Inactive
  -> Active
  -> Deleted

Deleted
  -> Restored     (restores only this row; descendants remain deleted)
```

### 7.2 Subcategory Lifecycle

```text
Active
  -> Inactive
  -> Deleted      (only when no non-deleted Services exist once Feature 004 lands)

Inactive
  -> Active
  -> Deleted

Deleted
  -> Restored     (blocked if parent is missing, deleted, or not a root Category)
```

## 8. Transaction and Locking Protocol

### 8.1 Universal Hierarchy Lock Order

Every hierarchy-sensitive write follows this exact order:

```text
1. Begin a short MySQL transaction.
2. Lock the root Category row with FOR UPDATE.
3. Lock relevant child rows with FOR UPDATE ordered by id ASC.
4. Revalidate route ownership, node kind, lifecycle, and dependencies.
5. Apply all writes.
6. Commit.
```

Rules:

- no child row may be locked before its root Category;
- child locks use deterministic `id ASC` ordering;
- route IDs identify the intended scope, but ownership is revalidated after
  locks are acquired;
- a validation or dependency failure rolls back the complete transaction;
- no mutation may return partial success.

### 8.2 Operations Requiring the Protocol

- root Category delete;
- Subcategory create;
- Subcategory delete;
- Subcategory restore;
- nested Subcategory reorder;
- any future hierarchy mutation that reads parent state and writes a child.

Root Category reorder has no shared parent. It locks all submitted root rows by
`id ASC` before writing any `sort_order` value.

Root Category restore locks the target root row before validating that it is
deleted and before restoring it.

### 8.3 Operation-Specific Locks

#### Create Subcategory

```text
lock route root Category
-> verify root + not deleted
-> insert child
```

The parent lock is held through insertion so a concurrent root delete cannot
pass its dependency check and delete the parent.

#### Delete Root Category

```text
lock target root Category
-> lock non-deleted children by id ASC
-> reject when children exist
-> soft-delete root
```

#### Restore Subcategory

```text
lock route parent
-> lock target deleted child
-> verify nested ownership + parent state
-> restore child
```

An inactive non-deleted parent allows restore. A missing, non-root, or deleted
parent blocks restore.

#### Delete Subcategory

```text
lock route parent
-> lock target child
-> verify nested ownership
-> check real Service dependency when Feature 004 exists
-> soft-delete child
```

#### Reorder Subcategories

```text
lock route parent
-> lock submitted children by id ASC
-> verify exact scoped ID set
-> rewrite all sort_order values
```

## 9. Concurrency-Sensitive Operations

### 9.1 Root Delete vs Child Create

Both Actions lock the same root Category row first.

Valid final outcomes:

- child creation commits first, then root deletion is blocked with
  `CATEGORY_HAS_SUBCATEGORIES`; or
- root deletion commits first, then child creation is rejected because the
  parent is deleted.

Invalid final outcome:

- a non-deleted Subcategory beneath a deleted root Category.

### 9.2 Parent Delete vs Child Restore

Both Actions lock the same parent row first.

Valid final outcomes:

- restore completes against an eligible parent before any later valid parent
  mutation; or
- parent deletion wins and restore is blocked with
  `PARENT_CATEGORY_DELETED`.

Invalid final outcome:

- a restored Subcategory beneath a deleted parent.

### 9.3 Root Category Reorder

Protection:

- validate submitted IDs;
- lock submitted root rows ordered by `id ASC`;
- revalidate that every row is a root Category;
- rewrite all `sort_order` values atomically.

### 9.4 Subcategory Reorder

Protection:

- lock the route parent first;
- lock submitted child rows ordered by `id ASC`;
- validate that every row belongs to the route parent;
- reject root-category IDs, duplicate IDs, missing IDs, and foreign child IDs;
- rewrite all `sort_order` values atomically.

### 9.5 Deadlock Handling

A bounded retry may follow the repository's established database retry policy.

A retry must:

- rerun the complete transaction;
- reacquire locks in the same deterministic order;
- rerun all validation and dependency checks;
- never replay a partial response or bypass a business rule.

## 10. Validation Baseline

### Localized Names

- required on create
- string
- trimmed
- min length `2`
- max length `150`

### Localized Descriptions

- optional only as a full bilingual pair
- plain text only
- trimmed
- max length `2000`
- empty string => `null`
- update requests must send both description keys when either is present

### Localized Slugs

- nullable on create only when auto-generated
- max length `180`
- normalized by locale-specific rules
- unique in the matching locale column

### State and Ordering

- `isActive`: boolean
- `sortOrder`: integer, minimum `0`

### Forbidden Input

- `id`
- `parentId`
- `categoryId`
- `deletedAt`
- `createdAt`
- `updatedAt`
- `subcategoriesCount`
- `servicesCount`

Unknown fields must be rejected.

## 11. Existing-Schema Compatibility and Migration Safety

Implementation must inspect the schema before adding migrations.

### When no legacy category schema exists

Create one baseline `categories` table matching the approved design.

### When a legacy category schema exists

Use forward-only altering migrations and preserve existing data.

Required sequence:

1. inspect current columns, indexes, soft-delete state, and foreign keys
2. map legacy fields to the approved target fields
3. backfill missing localized slugs or description pairs safely before adding constraints
4. add or correct indexes and foreign keys
5. enforce final non-null and uniqueness constraints only after safe backfill

Never:

- create a duplicate table under a different name
- rewrite an already-executed production migration
- introduce a second route surface for the same feature
- create fake service rows to satisfy delete rules

## 12. Future Integration Boundary

Feature 004 will later attach `services` to Subcategories and complete:

- the `services()` relationship
- the real `SUBCATEGORY_HAS_SERVICES` delete-blocking query
- any real `servicesCount` admin exposure

Feature 003 must preserve this boundary cleanly without speculative schema.
