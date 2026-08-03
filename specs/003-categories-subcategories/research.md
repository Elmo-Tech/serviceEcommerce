# Feature 003 Research — Categories and Subcategories

**Feature:** `003-categories-subcategories`  
**Date:** 2026-07-29  
**Status:** Complete

## Decision 1: Use one self-referencing `categories` table and one `Category` model

**Decision**: Represent both root Categories and Subcategories in a single
self-referencing `categories` table backed by one `App\Models\Category` model
with root/subcategory scopes and parent/children relationships.

**Rationale**:

- The approved feature reference explicitly requires one self-referencing table.
- A single model keeps hierarchy rules centralized and avoids duplicate
  serialization or migration logic across parallel tables.
- It aligns with the repository’s preference for the simplest Laravel shape
  that still enforces correctness.

**Alternatives considered**:

- **Separate `categories` and `subcategories` tables**: rejected because it
  contradicts the approved feature decision and adds unnecessary schema and
  synchronization complexity.
- **A dedicated `Subcategory` model over the same table**: rejected because the
  current domain difference is scope-based, not persistence-based.

## Decision 2: Keep public catalog routes under `/api/v1/public/*`

**Decision**: Plan and contract the public catalog surface under
`/api/v1/public/categories*` rather than bare `/api/v1/categories*`.

**Rationale**:

- Higher-level repository governance already fixes public route groups under
  `/api/v1/public/*`.
- The current `routes/api.php` file already mounts a public prefix placeholder
  under `/api/v1/public`.
- Synchronizing Feature 003 to the higher-level standard avoids a route-surface
  exception and keeps the project consistent.

**Alternatives considered**:

- **Bare `/api/v1/categories*` public routes**: rejected because it conflicts
  with the approved architecture and API standards.
- **Requesting a route-group exception**: rejected because the feature does not
  need one once its wording is synchronized to the existing standard.

## Decision 3: Use focused Actions for create, update, delete, restore, and reorder

**Decision**: Implement root-category and nested-subcategory mutation workflows
through focused Actions instead of placing hierarchy, slug, and dependency
logic directly in controllers.

**Rationale**:

- Create/update must coordinate description-pair normalization, localized slug
  generation or explicit slug edits, and route-owned parent scope.
- Delete, restore, and reorder all need explicit transaction boundaries and
  race-safe revalidation.
- The Actions remain meaningful use-case boundaries rather than pass-through
  layers.

**Alternatives considered**:

- **Controller-only mutations**: rejected because the feature has enough
  reusable orchestration and transaction logic to justify Actions.
- **A generic repository/service stack**: rejected because it would add layers
  with no approved business value.

## Decision 4: Centralize localized slug rules in one reusable service

**Decision**: Use one reusable `LocalizedSlugService` to normalize Arabic and
English slugs and to auto-generate missing localized slugs from the matching
localized name during creation only.

**Rationale**:

- Slug rules are shared between root Categories and Subcategories.
- The feature requires different normalization behavior for Arabic and English
  slugs, plus stable no-auto-regeneration behavior on update.
- Both localized slug normalizers preserve safe Unicode letters, including
  Arabic and Latin letters, so either localized name generates a usable slug
  without translation or transliteration.
- Centralizing this logic reduces drift between create/update workflows and
  improves testability.

**Alternatives considered**:

- **Inline slug generation in each controller or request**: rejected because it
  duplicates logic and makes update stability easier to break.
- **Use one generic Laravel slug helper without locale-specific guardrails**:
  rejected because the feature requires Arabic-safe and English-safe rules that
  differ in behavior.

## Decision 5: Use dedicated admin queries and a small public catalog query layer

**Decision**: Use dedicated query classes for admin category and subcategory
lists, and a small locale-aware query layer for public list/detail reads.

**Rationale**:

- Admin lists have allow-listed search, filters, sorting, trashed-state
  behavior, pagination, and counts.
- Public list/detail reads have a different concern set: locale-specific slug
  lookup plus ancestor visibility inheritance.
- Splitting these concerns keeps the implementation focused without creating an
  unnecessary query class per endpoint.

**Alternatives considered**:

- **One monolithic query class for all admin and public routes**: rejected
  because the actor rules and response shapes differ materially.
- **Controller-built queries**: rejected because the filter and visibility
  rules are substantial enough to justify reuse and testing.

## Decision 6: Protect hierarchy writes with one deterministic MySQL lock protocol

**Decision**: Every hierarchy-sensitive mutation uses a short MySQL transaction
and the same lock order:

```text
1. Lock the root Category row first.
2. Lock relevant child rows ordered by id ASC.
3. Revalidate hierarchy, lifecycle, dependency, and route-scope rules.
4. Apply the mutation.
5. Commit.
```

A child row must never be locked before its root Category row.

Operation-specific application:

- **Create Subcategory**:
  - lock the route root Category first;
  - revalidate that it exists, is a root, and is not soft-deleted;
  - create the child while the parent lock is held.
- **Delete root Category**:
  - lock the target root Category first;
  - lock its non-deleted children ordered by `id ASC`;
  - reject with `CATEGORY_HAS_SUBCATEGORIES` when any child exists;
  - otherwise soft-delete the parent.
- **Restore Subcategory**:
  - lock the route parent first;
  - lock the target Subcategory second;
  - revalidate parent lifecycle and nested ownership;
  - restore only the target child.
- **Delete Subcategory**:
  - lock the route parent first;
  - lock the target child second;
  - revalidate nested ownership and the real Service dependency when Feature
    004 is available.
- **Reorder Subcategories**:
  - lock the route parent first;
  - lock every submitted child ordered by `id ASC`;
  - reject missing, duplicate, root, or cross-parent IDs before updating order.
- **Reorder root Categories**:
  - no shared parent exists, so lock submitted root rows ordered by `id ASC`.

**Rationale**:

- `Category delete` and `Subcategory create` must contend on the same root row;
  a child-dependency recheck alone does not prevent a child from being created
  immediately after the check.
- `Parent delete` and `Subcategory restore` must also contend on the same root
  row to prevent restoration beneath a concurrently deleted parent.
- Parent-first locking plus deterministic child ordering prevents circular lock
  acquisition between nested workflows.
- The current repository and standards favor MySQL-backed integrity over
  speculative distributed locking.

**Required race outcomes**:

- concurrent root delete and child create may end with either:
  - the child created and root deletion blocked; or
  - the root deleted and child creation rejected;
  - but never a non-deleted child beneath a deleted root.
- concurrent parent delete and child restore may end with either:
  - child restoration completing before a later valid parent operation; or
  - restoration being rejected after parent deletion;
  - but never a restored child beneath a deleted parent.

**Alternatives considered**:

- **Dependency pre-check without locking the parent**: rejected because it
  leaves a check-then-insert race.
- **Different lock orders per Action**: rejected because they increase deadlock
  risk and make correctness difficult to verify.
- **Redis or external distributed locks**: rejected because the feature does
  not justify unsupported infrastructure expansion.

## Decision 7: Keep permission registration feature-owned and idempotent

**Decision**: Add a dedicated `CategoriesPermissionsSeeder` that registers:

- `categories.view`
- `categories.create`
- `categories.update`
- `categories.delete`
- `categories.restore`
- `categories.reorder`
- `subcategories.view`
- `subcategories.create`
- `subcategories.update`
- `subcategories.delete`
- `subcategories.restore`
- `subcategories.reorder`

**Rationale**:

- The repository already uses feature-owned permission seeders in Feature 002.
- A dedicated seeder keeps the permission inventory explicit and testable.
- Idempotent creation plus cache reset matches current Spatie permission usage.

**Alternatives considered**:

- **Hard-code permissions in a controller or policy**: rejected because
  permissions are authoritative persisted configuration.
- **Fold the permissions into an unrelated existing seeder silently**: rejected
  because feature-owned seeders keep traceability clearer.

## Decision 8: Preserve the Service dependency boundary without fake data

**Decision**: Feature 003 will plan for the `SUBCATEGORY_HAS_SERVICES` contract
without inventing placeholder service rows, fake counts, or speculative service
infrastructure.

**Rationale**:

- The feature reference explicitly says the real service relationship is
  completed by Feature 004.
- The current feature still needs a clean delete/restore boundary and API error
  contract for future integration.
- Avoiding speculative implementation keeps Feature 003 inside approved scope.

**Alternatives considered**:

- **Create placeholder `services` persistence now**: rejected because that is
  unapproved scope expansion.
- **Ignore the future delete contract entirely**: rejected because the feature
  must define and document the boundary for future integration.

## Decision 9: Use localized response metadata for frontend language switching

**Decision**: Public response content remains projected into one resolved
locale, while slug-bound responses include `meta.localeLinks.ar` and
`meta.localeLinks.en`.

**Rationale**:

- A frontend viewing an Arabic slug cannot infer the corresponding English
  slug safely.
- Returning both names or descriptions would weaken the resolved-locale public
  contract.
- Alternate API URLs in metadata solve language switching without exposing
  bilingual content in `data`.
- Root public lists do not need record-specific alternate links; category
  detail, category-scoped Subcategory list, and Subcategory detail do.

**Headers**:

- every public response includes `Content-Language`;
- every public response includes `Vary: Accept-Language`.

**Alternatives considered**:

- **Return both localized content columns publicly**: rejected because public
  content must remain resolved-locale only.
- **Have the frontend transliterate or guess the other slug**: rejected because
  localized slugs are independent administrator-owned values.
- **Refetch all Categories solely to switch a detail page locale**: rejected as
  unnecessary client work.

## Decision 10: Model description pairing in OpenAPI and validate semantics in Laravel

**Decision**: OpenAPI create and update schemas require both description keys
whenever either is sent and reject non-empty-string/null mismatches. Laravel
Form Requests normalize blank strings to `null` and enforce the final pair
invariant.

**Rationale**:

- The API contract should reject one-key-only description requests before
  implementation.
- JSON Schema can express key dependency and the main valid pair shapes.
- Backend normalization remains necessary for whitespace and trim semantics.

## Decision 11: Return a `200` success envelope for soft deletion

**Decision**: Successful Category and Subcategory soft deletion returns
`200 OK` with the shared success envelope and `data: null`.

**Rationale**:

- The project uses a shared JSON envelope for successful mutations.
- A single contract avoids frontend branching between `200` JSON responses and
  `204` empty responses.
- OpenAPI, Postman, tests, and implementation can assert one exact behavior.

**Alternative considered**:

- **`204 No Content`**: rejected for Feature 003 because it conflicts with the
  shared mutation envelope used by this module.

## Decision 12: Localize Admin index projections and use native Query Builder syntax

**Decision**: Category and Subcategory Admin index endpoints serialize one
resolved locale only:

```text
name
description
slug
```

They retain approved operational fields such as `id`, state, ordering, counts,
timestamps, and deletion metadata. Admin detail and mutation responses remain
bilingual for edit forms.

Admin index requests use `spatie/laravel-query-builder`:

```text
filter[search]
filter[isActive]
filter[trashed]
sort
page
perPage
```

**Search decision**: `filter[search]` is one allow-listed custom filter that
searches Arabic and English names, descriptions, and slugs regardless of
response locale.

**Sorting decision**:

- `sort=sortOrder`
- `sort=-createdAt`
- `sort=name`
- `sort=-name`

`sort=name` maps to the resolved locale's name column.

**Rationale**:

- Returning both languages in large index responses duplicates content that the
  Dashboard does not display simultaneously.
- Retaining `id`, state, ordering, counts, and timestamps keeps row actions and
  operational controls possible.
- Detail and mutation responses still provide both languages where Admin forms
  need them.
- Native Query Builder syntax keeps filtering and sorting consistent with the
  installed package and prevents ad-hoc query parameter parsing.

**Alternatives considered**:

- **Remove `id` and operational fields from index rows**: rejected because the
  Dashboard needs stable identifiers and state for edit, delete, restore, and
  reorder actions.
- **Search only the active response locale**: rejected because Admin search
  must locate records using either stored language.
- **Keep `sortBy` and `sortDirection`**: rejected because Query Builder's native
  `sort` parameter already models ascending and descending allow-listed sorts.
