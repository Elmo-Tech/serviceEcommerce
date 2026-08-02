# Implementation Plan: Categories and Subcategories

**Feature:** `003-categories-subcategories`  
**Branch:** `003-categories-subcategories`  
**Date:** 2026-07-29  
**Spec:** [spec.md](./spec.md)  
**Status:** Ready for Task Generation

## Summary

Implement Feature 003 as the backend-owned catalog classification layer for
root Categories and nested Subcategories using one self-referencing
`categories` table. The design preserves the existing Laravel API-first
monolith structure, keeps all protected routes under `/api/v1/admin/*`, keeps
all public routes under `/api/v1/public/*`, and introduces only the minimum
additional abstractions justified by the approved feature rules:

- thin controllers for admin and public endpoints
- dedicated Form Requests for every mutation and query-validation surface
- focused Actions for create, update, delete, restore, and reorder workflows
- one `Category` Eloquent model with root/subcategory scopes
- targeted Query classes for admin listing and locale-aware public visibility
- reusable localized-slug normalization and public-visibility query logic
- MySQL transactions and row locking for reorder and dependency-sensitive races
- separate Admin index Resources with resolved-locale content and bilingual
  Admin detail/mutation Resources
- resolved-locale public output
- localized public response headers and alternate locale navigation metadata
- shared `200` success envelopes for successful soft deletion

This feature does not add Services CRUD, third-level hierarchy, multiple-image
media management, frontend code, caching, queues, Redis, or any change to Feature 001
authentication behavior.

## Pre-Implementation Existing-Code Audit

Before implementation begins, inspect the current repository and classify every
related artifact as:

```text
Reuse
Update
Replace
Delete only when unreferenced
Create only when missing
```

The audit must cover:

- existing category-like tables or legacy migrations
- any existing `Category` model or self-referencing catalog code
- existing public route registration under `routes/api.php`
- existing permission seeders and feature seeding flow
- existing localization keys related to categories or public catalog
- existing OpenAPI/Postman artifacts that already mention public categories
- existing tests or architecture assertions that reference future catalog routes

Observed current baseline before implementation:

- no `Category` model or category migration was detected in the current code
- admin customer routes already exist and define the protected-route pattern
- public routes are currently a placeholder block in [routes/api.php](./../../routes/api.php)
- the repository already uses feature-owned permission seeders and request/query
  validation patterns from Feature 002
- a route-surface conflict was found between the original Feature 003 public
  route wording and higher-level repository standards; the feature documents
  were synchronized during planning to use `/api/v1/public/categories*`

Required audit output:

1. inventory of any pre-existing category/catalog artifacts
2. chosen action for each artifact: reuse, update, replace, delete, or create
3. confirmation that no duplicate table, route, seeder, or model will be introduced
4. confirmation that the public route placeholder will be replaced or expanded
   without weakening the approved top-level `/api/v1/public/*` rule

Mandatory safety rules:

- do not create a parallel table when a legacy category table already exists
- do not edit an already-executed migration to change production behavior
- use forward-only migrations if any legacy category schema is discovered
- do not invent placeholder Service persistence just to satisfy
  `SUBCATEGORY_HAS_SERVICES`
- do not create public routes outside the approved `/api/v1/public/*` group

## Technical Context

**Language/Version**: PHP `^8.3`

**Primary Dependencies**:

- Laravel Framework `^13.8`
- Laravel Sanctum `^4.3`
- `spatie/laravel-permission` `^8.3`
- `spatie/laravel-query-builder` `^7.3`
- existing Laravel localization, soft-delete, validation, and API Resource stack

**Storage**:

- MySQL for the self-referencing `categories` table
- Laravel soft deletes for category/subcategory lifecycle
- Laravel file storage for one optional image per Category or Subcategory

**Testing**:

- Pest with a dedicated MySQL test database
- real MySQL concurrency tests for reorder and dependency-sensitive races
- Laravel Pint
- Larastan/PHPStan

**Target Platform**:

- Hostinger-compatible PHP/MySQL deployment
- backend-only Laravel monolith
- no Redis, queue worker, scheduler, or Cron dependency introduced by this feature

**Project Type**: Backend-only, API-first conventional Laravel monolith

**Performance Goals**:

- admin category and subcategory lists remain paginated and bounded by the
  shared API `perPage` cap (`<= 100`)
- public category and subcategory lists remain unpaginated but deterministic and
  efficient for the MVP’s expected catalog size
- reorder writes complete atomically for the requested branch without partial
  visible results
- localized public slug lookup remains index-backed through `slug_ar` and `slug_en`

**Constraints**:

- stable `/api/v1` JSON contracts and shared success/error envelopes
- admin routes must stay under `/api/v1/admin/*`
- public routes must stay under `/api/v1/public/*`
- Arabic and English localization with stable English machine identifiers
- one self-referencing table only; no separate `subcategories` table
- no third-level hierarchy
- no force delete, no cascade delete, no multiple-image media management, no
  icons, no video, no attachments, no rich text
- no raw SQL errors, no hidden record disclosure, no unsafe slug handling

**Scale/Scope**:

- catalog records are expected to remain manageable for unpaginated public
  reads, but admin reads must support operational pagination and search
- concurrency is concentrated around:
  - reorder within root-category scope
  - root-category delete racing with child creation or restore
  - subcategory restore racing with parent delete or scope mismatch

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

### Pre-Design Gate

- **Authority, precedence, and traceability — PASS**  
  The plan maps directly to
  [docs/features/003-categories-subcategories.md](./../../docs/features/003-categories-subcategories.md),
  the active [spec.md](./spec.md), and the shared standards.

- **Conflict and exception gate — PASS**  
  A public-route wording conflict was identified during planning and resolved by
  synchronizing Feature 003 artifacts to the approved `/api/v1/public/*`
  route-group standard. No unresolved exception remains.

- **Repository boundary — PASS**  
  The feature remains backend-only Laravel work with no frontend, Blade product
  UI, queue, Redis, or infrastructure expansion.

- **Architecture — PASS**  
  The planned structure keeps controllers thin, uses Form Requests and
  Resources, limits Actions to meaningful workflows, and avoids speculative
  repositories or modular layers.

- **API contract — PASS**  
  The route surface stays versioned, localized, and allow-listed, with stable
  `camelCase` keys and explicit success/error envelopes.

- **Authentication and authorization — PASS**  
  Protected routes keep the approved middleware chain and use explicit
  `categories.*` and `subcategories.*` permissions with scoped nested lookup.

- **Trust and content safety — PASS**  
  The backend owns parent scope, slug normalization, description-pair rules,
  visibility, and reorder validation; content stays plain text only.

- **Database integrity — PASS**  
  The design uses MySQL unique indexes, a self-referencing foreign key,
  transactions, and row locks for reorder and dependency-sensitive writes.

- **Files — PASS**  
  No file or media behavior is introduced.

- **Localization — PASS**  
  Arabic and English content and messages remain supported while machine
  identifiers and route segments remain English.

- **Testing and quality — PASS**  
  The plan includes Feature, architecture, and MySQL concurrency coverage plus
  Pint and PHPStan/Larastan gates.

- **Scope and operations — PASS**  
  No speculative Service CRUD, scheduler, queue, or caching behavior is added.

### Post-Design Gate

- **Authority, precedence, and traceability — PASS**  
  [research.md](./research.md), [data-model.md](./data-model.md),
  [contracts/openapi.yaml](./contracts/openapi.yaml), and
  [quickstart.md](./quickstart.md) all trace back to the approved Feature 003
  requirements.

- **Conflict and exception gate — PASS**  
  The design does not weaken higher-level route, authorization, localization,
  or database rules.

- **Repository boundary — PASS**  
  The design remains strictly inside the backend catalog domain.

- **Architecture — PASS**  
  The plan uses one `Category` model, justified Actions for mutation workflows,
  and focused query/slug helpers without speculative layers.

- **API contract — PASS**  
  Admin and public paths, mutation schemas, list query rules, resolved-locale
  public resources, and stable error codes are explicit.

- **Authentication and authorization — PASS**  
  Admin middleware order, scoped root-category resolution, and nested
  subcategory ownership are preserved.

- **Trust and content safety — PASS**  
  No trusted HTML, raw storage paths, unsafe slugs, or hidden-record disclosure
  is introduced.

- **Database integrity — PASS**  
  The design chooses index-backed localized slugs plus transactional reorder,
  delete, and restore checks with no unresolved hierarchy race.

- **Files — PASS**  
  Still no file responsibility introduced.

- **Localization — PASS**  
  Locale-specific slug lookup and response rendering are explicit and remain
  compatible with `Accept-Language`, `Content-Language`, and `Vary`.

- **Testing and quality — PASS**  
  The quickstart defines the grouped verification commands required before
  implementation can be completed.

- **Scope and operations — PASS**  
  No unsupported Hostinger operational dependency has been added.

## Core Design Decisions

### Domain Shape

- one self-referencing `categories` table stores both root Categories and
  Subcategories
- one `App\Models\Category` model distinguishes roots vs subcategories through
  `parent_id` scopes and predicates
- localized names, descriptions, and slugs live on the same row
- root categories expose `subcategoriesCount`; subcategories expose parent
  context and future-compatible `servicesCount` behavior without inventing
  nonexistent service data

### Route and Binding Strategy

- protected root-category routes live in `routes/api/v1/admin.php`
- public category routes live under `/api/v1/public/categories*`
- root-category route binding must resolve only `parent_id = null`
- nested subcategory resolution must scope through the route parent and return
  `404` on foreign nested mismatch
- restore flows must resolve soft-deleted rows explicitly rather than
  broadening normal bindings

### Mutation Strategy

- create/update/delete/restore/reorder workflows use focused Actions because
  they combine normalization, uniqueness, dependency rules, nested scope, and
  transaction boundaries
- a reusable localized slug service owns Arabic and English slug normalization
  and create-only auto-generation
- description pair normalization and unknown-field rejection stay in Form
  Requests, not controllers
- delete and restore workflows revalidate dependency and parent-state rules
  inside their mutation boundary
- successful Category and Subcategory soft deletion returns `200` through the
  shared success envelope with `data: null`

### Query and Localization Strategy

- Admin Category listing uses a dedicated Spatie Query Builder query with
  `filter[search]`, `filter[isActive]`, `filter[trashed]`, `sort`, `page`, and
  `perPage`
- nested Admin Subcategory listing uses the same Query Builder surface but
  remains strictly scoped to the route parent
- `filter[search]` searches both Arabic and English names, descriptions, and
  slugs
- `sort=name` maps to the resolved locale's name column; descending uses `-`
- Admin index Resources return localized `name`, `description`, and `slug`
  while retaining operational fields; detail and mutation Resources remain
  bilingual
- public reads use a small locale-aware query/service layer that:
  - resolves locale before slug lookup
  - uses only `slug_ar` for Arabic and `slug_en` for English
  - filters out inactive/deleted ancestors
  - keeps public lists unpaginated and ordered by `sort_order`, then `id`
  - emits `Content-Language` and `Vary: Accept-Language`
  - emits `meta.localeLinks` on slug-bound responses without exposing the other
    locale's name or description
- OpenAPI mutation schemas model description-key pairing for both create and
  PATCH contracts; backend normalization remains the final semantic validator

### Concurrency and Dependency Strategy

All hierarchy-sensitive Actions use one deterministic transaction protocol:

```text
root Category row
-> relevant child rows ordered by id ASC
-> revalidate
-> mutate
-> commit
```

Rules:

- no Action may lock a child before its root Category;
- `CreateSubcategoryAction` locks the route root Category before final parent
  validation and child insertion;
- `DeleteCategoryAction` locks the root Category, then its non-deleted children
  by `id ASC`, and only soft-deletes when the dependency set is still empty;
- `RestoreSubcategoryAction` locks the route parent, then the deleted child, and
  revalidates parent lifecycle and nested ownership inside the transaction;
- `DeleteSubcategoryAction` locks parent then child before dependency checks;
- `ReorderSubcategoriesAction` locks parent then all submitted child rows by
  `id ASC`;
- `ReorderCategoriesAction` locks submitted root rows by `id ASC`;
- missing, duplicate, root, or cross-parent reorder IDs fail before any ordering
  value is changed;
- deadlock retries may use the repository's existing bounded database retry
  policy, but retries must not weaken validation or return partial success;
- the Service dependency rule (`SUBCATEGORY_HAS_SERVICES`) remains a real
  Feature 004 boundary and must not be satisfied through fake data or
  placeholder persistence.

Required concurrency verification:

- root Category delete racing with Subcategory create;
- parent delete racing with Subcategory restore;
- two root reorders with overlapping IDs;
- two Subcategory reorders under the same parent;
- cross-parent reorder racing with a valid scoped reorder.

No accepted outcome may leave a non-deleted child beneath a deleted root or a
partially updated ordering set.

### Existing-Code Integration Strategy

- create the `categories` table only if no legacy table exists
- if legacy catalog artifacts are discovered, use forward-only migrations and
  update existing classes rather than introducing duplicate tables or route
  trees
- replace the public route placeholder in `routes/api.php` with a concrete
  include or grouped route file rather than scattering route definitions
- follow the existing feature-owned seeder pattern by adding a dedicated
  permissions seeder for categories/subcategories and integrating it into the
  repository seeding flow

## Project Structure

### Documentation (this feature)

```text
specs/003-categories-subcategories/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── openapi.yaml
└── tasks.md              # created later by /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Actions/
│   └── Categories/
│       ├── CreateCategoryAction.php
│       ├── UpdateCategoryAction.php
│       ├── DeleteCategoryAction.php
│       ├── RestoreCategoryAction.php
│       ├── ReorderCategoriesAction.php
│       ├── CreateSubcategoryAction.php
│       ├── UpdateSubcategoryAction.php
│       ├── DeleteSubcategoryAction.php
│       ├── RestoreSubcategoryAction.php
│       └── ReorderSubcategoriesAction.php
├── Http/
│   ├── Controllers/Api/V1/Admin/Categories/
│   │   ├── CategoryController.php
│   │   └── SubcategoryController.php
│   ├── Controllers/Api/V1/Public/
│   │   └── CategoryController.php
│   ├── Requests/Api/V1/Admin/Categories/
│   │   ├── ListCategoriesRequest.php
│   │   ├── StoreCategoryRequest.php
│   │   ├── UpdateCategoryRequest.php
│   │   ├── ReorderCategoriesRequest.php
│   │   ├── ListSubcategoriesRequest.php
│   │   ├── StoreSubcategoryRequest.php
│   │   ├── UpdateSubcategoryRequest.php
│   │   └── ReorderSubcategoriesRequest.php
│   └── Resources/Api/V1/
│       ├── Admin/Categories/
│       │   ├── CategoryIndexResource.php
│       │   ├── CategoryResource.php
│       │   ├── SubcategoryIndexResource.php
│       │   └── SubcategoryResource.php
│       └── Public/Categories/
├── Models/
│   └── Category.php
├── Queries/Categories/
│   ├── AdminCategoryIndexQuery.php
│   ├── AdminSubcategoryIndexQuery.php
│   └── PublicCategoryCatalogQuery.php
├── Services/Categories/
│   └── LocalizedSlugService.php
└── Support/

database/
├── factories/
│   └── CategoryFactory.php
├── migrations/
│   └── *_create_categories_table.php
└── seeders/
    └── CategoriesPermissionsSeeder.php

lang/
├── ar/
│   └── categories.php
└── en/
    └── categories.php

routes/
├── api.php
└── api/v1/
    ├── admin.php
    ├── auth.php
    └── public.php

tests/
├── Architecture/
│   └── CategoriesFeatureArchitectureTest.php
├── Feature/Api/V1/Admin/Categories/
│   ├── CategoryApiTest.php
│   └── SubcategoryApiTest.php
├── Feature/Api/V1/Public/Categories/
│   └── PublicCategoryApiTest.php
└── Concurrency/Categories/
    └── CategoryCriticalConcurrencyTest.php
```

**Structure Decision**:  
Use one Eloquent model and one migration as the domain backbone. Keep admin
listing logic in dedicated query objects, keep public visibility and localized
slug lookup in a small catalog query layer, and limit service extraction to the
localized slug capability that is reused across root and child workflows.

## Artifact Outputs

### Phase 0

- [research.md](./research.md): decisions for self-referencing persistence,
  localized slug generation, route scope, public visibility, permission
  seeding, and race handling

### Phase 1

- [data-model.md](./data-model.md): entity fields, invariants, visibility,
  transitions, deterministic parent-first lock order, and transaction boundaries
- [contracts/openapi.yaml](./contracts/openapi.yaml): canonical admin and
  `/api/v1/public/categories*` contract surface, strict description-pair
  schemas, localization headers, locale links, and `200` delete envelopes
- [quickstart.md](./quickstart.md): runnable validation guide and expected
  verification commands

## Complexity Tracking

No constitution exception is proposed for this feature.

| Violation | Why Needed | Risk and Mitigation | User Approval |
|-----------|------------|---------------------|---------------|
| None | Not applicable | Not applicable | Not applicable |
