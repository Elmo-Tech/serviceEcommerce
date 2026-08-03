# Feature Specification: Categories and Subcategories

**Feature Branch**: `003-categories-subcategories`

**Created**: 2026-07-29

**Status**: Ready for Planning

**Input**: User description: "Build Feature 003 from `docs/features/003-categories-subcategories.md` and use that file as the authoritative reference."

## Scope and Governing Context *(mandatory)*

**Approved source**: [docs/features/003-categories-subcategories.md](C:/xampp/htdocs/serviceEcommerce/docs/features/003-categories-subcategories.md)

**In scope**:

- Root Category administration
- Nested Subcategory administration under a root Category
- Bilingual Arabic and English names, descriptions, and slugs
- Optional bilingual description pair behavior
- Stable localized slug creation and manual slug editing
- Active and inactive state management
- Manual ordering through `sortOrder`
- Soft delete, restore, and dependency-blocked deletion
- Public read-only category and subcategory navigation
- Public visibility inheritance from parent active and deletion state
- Independent category and subcategory permissions
- Localized admin and public API contracts, OpenAPI, Postman, and tests

**Out of scope**:

- Services CRUD and any Service-specific business rules beyond approved deletion dependencies
- Multiple Category or Subcategory images, icons, videos, attachments, or
  separate media-management routes
- Third-level hierarchy or arbitrary-depth trees
- Moving a Subcategory to another Category
- Force deletion
- Automatic restoration of descendants
- Public write routes
- Frontend implementation
- Queue jobs, commands, cron, scheduler, Redis, or cache infrastructure

**Governing documents reviewed**:

- `.specify/memory/constitution.md`
- `AGENTS.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- `docs/02-standards/api-standards.md`
- `docs/02-standards/authorization-standards.md`
- `docs/02-standards/database-standards.md`
- `docs/02-standards/security-standards.md`
- `docs/02-standards/testing-standards.md`
- `docs/features/001-identity-authentication.md`
- `docs/features/002-customers-addresses.md`
- `docs/features/003-categories-subcategories.md`

**Known conflicts**:

- None

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Admin manages root Categories (Priority: P1)

An authenticated administrator needs to create, list, update, activate,
deactivate, reorder, soft-delete, and restore root Categories with bilingual
content so the catalog structure can be controlled safely from the dashboard.

**Why this priority**: Root Categories define the top-level catalog structure.
Without them, the public catalog and downstream Service classification cannot
exist.

**Independent Test**: Can be fully tested through protected Category routes by
creating root Categories, changing bilingual content and slugs, reordering
them, and verifying soft-delete and restore behavior.

**Acceptance Scenarios**:

1. **Given** an authenticated active administrator with `categories.create`,
   **When** they submit valid Arabic and English names with a valid bilingual
   description pair or no descriptions, **Then** one root Category is created
   with `parentId = null` and returned through the admin resource with both
   localized fields.
2. **Given** an existing root Category, **When** the administrator updates its
   names or descriptions without sending new slugs, **Then** the stored Arabic
   and English slugs remain unchanged.
3. **Given** a root Category that has no non-deleted Subcategories, **When**
   the administrator soft-deletes or restores it, **Then** only that Category
   changes state and no descendant is force-deleted or auto-restored.

---

### User Story 2 - Admin manages nested Subcategories safely (Priority: P1)

An authenticated administrator needs to create and manage Subcategories only
under valid root Categories while preventing third-level hierarchy, parent
tampering, cross-parent reorder mistakes, and invalid restore operations.

**Why this priority**: Subcategories are the second required catalog level and
must remain structurally correct before later Service features can depend on
them.

**Independent Test**: Can be fully tested through nested Subcategory routes by
creating and updating Subcategories under root Categories, verifying parent
immutability, deletion safeguards, and reorder behavior within one parent.

**Acceptance Scenarios**:

1. **Given** a non-deleted root Category and an authorized administrator,
   **When** they create a Subcategory through that nested route with valid
   bilingual content, **Then** the Subcategory is stored under that root
   Category only.
2. **Given** a Subcategory, **When** a request attempts to treat that
   Subcategory as a parent or sends `parentId` or `categoryId` in the body,
   **Then** the request is rejected and no third hierarchy level is created.
3. **Given** a deleted Subcategory whose parent Category is soft-deleted,
   **When** restoration is attempted, **Then** the request is blocked with
   `409 PARENT_CATEGORY_DELETED` and the Subcategory remains deleted.
4. **Given** a deleted Subcategory whose parent Category exists but is
   inactive, **When** restoration is attempted, **Then** the Subcategory is
   restored successfully, preserves its stored `isActive` value, and remains
   hidden from public APIs until the parent becomes active.

---

### User Story 3 - Public visitors browse only active localized catalog nodes (Priority: P2)

A public visitor needs to see only active, non-deleted Categories and
Subcategories with the correct localized name, description, and slug according
to `Accept-Language`, without learning about hidden records.

**Why this priority**: Public read behavior is the user-facing outcome of the
catalog structure and must respect localization and visibility inheritance.

**Independent Test**: Can be fully tested through public read-only routes by
calling Arabic and English list/detail endpoints and verifying visibility,
ordering, localized slugs, and hidden-record `404` behavior.

**Acceptance Scenarios**:

1. **Given** active non-deleted Categories and Subcategories, **When** a
   public request is made with Arabic or English `Accept-Language`, **Then**
   the response returns only the resolved localized name, description, and
   slug in deterministic order.
2. **Given** an inactive or deleted Category, **When** a public request targets
   that Category or one of its Subcategories, **Then** the API returns `404`
   and does not reveal the hidden record’s existence.
3. **Given** both localized descriptions are null, **When** a public detail
   response is returned, **Then** the localized `description` field is `null`
   rather than a copied or machine-translated value.
4. **Given** a valid English slug, **When** it is requested with Arabic
   locale resolution, **Then** the API returns the approved non-disclosing
   `404` and does not search `slug_en` as a fallback.
5. **Given** a visitor is viewing a slug-bound Arabic public
   Category or Subcategory response, **When** the response is returned,
   **Then** `meta.localeLinks.en` contains the corresponding English API URL
   and the `data` object still contains Arabic content only.

---

### Edge Cases

- What happens when only one localized description is sent on create?
- What happens when a PATCH request includes only one description key?
- How are both descriptions cleared intentionally during update?
- What happens when Arabic or English slug is omitted on create?
- What happens when an Arabic request uses an English slug, or an English
  request uses an Arabic slug?
- How does the system handle duplicate Arabic or English slugs?
- What happens when a Subcategory is requested through the wrong parent route?
- How does the system handle deleting a Category that still has non-deleted
  Subcategories?
- How does the system handle restoring a Subcategory whose parent was deleted
  or deactivated after the Subcategory was removed?
- What happens when reorder payloads include missing, duplicated, or
  cross-parent IDs?
- How does the system handle concurrent reorder or delete-vs-create races on
  the same hierarchy branch?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST represent root Categories and Subcategories in
  one self-referencing `categories` data set.
- **FR-002**: A root Category MUST have no parent.
- **FR-003**: A Subcategory MUST belong to exactly one non-deleted root
  Category.
- **FR-004**: The system MUST reject any third hierarchy level.
- **FR-005**: The MVP MUST NOT support moving a Subcategory to a different
  Category.
- **FR-006**: Arabic and English names MUST both be required for Categories
  and Subcategories.
- **FR-007**: Arabic and English descriptions MUST be optional as a bilingual
  pair: both may be omitted, but one localized description MUST NOT exist
  without the other.
- **FR-008**: Each localized description MUST be plain text, trimmed, limited
  to `2000` characters, converted from an empty string to `null`, and MUST NOT
  be machine-translated.
- **FR-008A**: On create, `descriptionAr` and `descriptionEn` MUST either both
  be omitted/null or both contain valid localized text.
- **FR-008B**: On update, when either description key is present, both
  `descriptionAr` and `descriptionEn` MUST be present in the request. Sending
  both as `null` MUST clear the bilingual description pair atomically.
- **FR-009**: Arabic and English slugs MUST exist for every record and each
  locale-specific slug MUST remain stable unless explicitly edited.
- **FR-010**: The system MAY generate a localized slug from the matching
  localized name during creation only when that localized slug is omitted.
- **FR-011**: The system MUST support manual ordering through `sortOrder`.
- **FR-012**: Categories and Subcategories MUST each have an independent
  active and inactive state.
- **FR-013**: Disabling a Category MUST hide its Subcategories and later
  Services publicly without mutating stored child active values.
- **FR-014**: Disabling a Subcategory MUST hide its later Services publicly
  without mutating stored descendant active values.
- **FR-015**: Categories and Subcategories MUST use soft deletion.
- **FR-016**: Force deletion MUST NOT be available in the MVP.
- **FR-017**: Category deletion MUST be blocked while any non-deleted
  Subcategory exists under it.
- **FR-018**: Subcategory deletion MUST be blocked while any non-deleted
  Service exists under it once the Service feature is integrated.
- **FR-019**: Deletion MUST NOT cascade to descendants.
- **FR-020**: Restoring a Category or Subcategory MUST affect only the
  requested record and MUST NOT auto-restore descendants.
- **FR-020A**: Restoring a Subcategory MUST be blocked only when its parent
  Category is missing, is not a root Category, or is soft-deleted. An inactive
  but non-deleted parent MUST NOT block restoration; the restored Subcategory
  remains publicly hidden while that parent is inactive.
- **FR-021**: Public APIs MUST expose only active, non-deleted Categories and
  Subcategories whose ancestor chain is also active and non-deleted.
- **FR-022**: Hidden public records MUST return `404`.
- **FR-023**: Admin detail, create, update, and restore APIs MUST return
  both Arabic and English names, descriptions, slugs, and the optional image
  URL.
- **FR-023A**: Admin Category and Subcategory index items MUST return only the
  resolved-locale `name`, `description`, and `slug`, plus approved operational
  fields. Index items MUST NOT return `nameAr`, `nameEn`, `descriptionAr`,
  `descriptionEn`, `slugAr`, or `slugEn`.
- **FR-024**: Public API content MUST return only the resolved-locale name,
  description, and slug.
- **FR-024A**: Slug-bound public responses MUST include
  `meta.localeLinks.ar` and `meta.localeLinks.en` containing alternate localized
  API URLs so the frontend can switch locale without guessing slugs. These
  links MUST NOT expose the other locale's name or description.
- **FR-025**: Admin list APIs MUST use `spatie/laravel-query-builder` and
  accept filters only through `filter[search]`, `filter[isActive]`, and
  `filter[trashed]`; sorting MUST use the allow-listed `sort` parameter and
  pagination MUST use `page` and `perPage`.
- **FR-025A**: `filter[search]` MUST search Arabic and English names,
  descriptions, and slugs regardless of the resolved response locale.
- **FR-025B**: `sort=name` MUST map to the name column for the resolved locale;
  descending sorts MUST use Query Builder's `-` prefix.
- **FR-026**: Public list APIs MUST remain read-only and unpaginated in the
  MVP.
- **FR-027**: Each Category and Subcategory MAY have one optional image.
- **FR-028**: The image MUST NOT be required on create or update.
- **FR-029**: A text `image` value, including an empty string, MUST be ignored;
  only an uploaded file MAY create or replace the stored image.
- **FR-030**: The feature MUST NOT introduce multiple images, icons, video, or
  attachment behavior for Categories or Subcategories.

### Actors and Authorization *(mandatory for protected behaviour)*

- **AR-001**: Category administration MUST use these independent stable
  permissions exactly: `categories.view`, `categories.create`,
  `categories.update`, `categories.delete`, `categories.restore`, and
  `categories.reorder`.
- **AR-002**: Subcategory administration MUST use these independent stable
  permissions exactly: `subcategories.view`, `subcategories.create`,
  `subcategories.update`, `subcategories.delete`,
  `subcategories.restore`, and `subcategories.reorder`.
- **AR-003**: Every protected Feature 003 route MUST preserve the Feature
  001 middleware order:
  `auth:sanctum -> EnsureUserIsAdministrator -> EnsureAdminIsActive ->
  permission -> endpoint`. The system MUST return the approved
  unauthenticated, inactive-admin, forbidden, and nested-resource
  non-disclosure outcomes.
- **AR-004**: Nested route ownership and parent-child scope MUST be enforced on
  the backend and MUST NOT depend on broad permission checks alone.

### Trust, Security, and Content Boundaries *(mandatory)*

- **TR-001**: The backend MUST revalidate all client-supplied names,
  descriptions, slugs, sort inputs, ordering payloads, and nested parent scope
  values.
- **TR-002**: Request bodies for nested Subcategory create and update MUST NOT
  accept or trust `parentId` or `categoryId`.
- **TR-003**: Unknown request fields MUST be rejected.
- **TR-004**: Names and descriptions MUST remain plain text and MUST NOT be
  treated as rich text, Markdown, or executable content.
- **TR-005**: Slugs MUST be normalized for safe URL use and MUST NOT be used to
  build filesystem paths or expose internal implementation details.
- **TR-006**: Public responses MUST NOT expose internal IDs, active flags,
  deleted flags, or raw exception details.

### Data Integrity and Concurrency *(mandatory when data changes)*

- **DI-001**: Arabic and English slugs MUST each be unique in their own locale
  column.
- **DI-002**: Parent references MUST point only to valid Category records and
  the two-level hierarchy invariant MUST be enforced by application rules.
- **DI-003**: Delete and restore operations MUST revalidate dependency and
  parent-state rules inside the mutation boundary.
- **DI-004**: Reordering Categories and Subcategories MUST be atomic and MUST
  NOT leave partial ordering results under concurrency.
- **DI-005**: Cross-parent Subcategory reorder attempts MUST be rejected and
  MUST NOT mutate stored ordering.
- **DI-006**: Changing current names or descriptions MUST NOT silently rewrite
  existing stored slugs.

### API Contract and Localization *(mandatory for API behaviour)*

- **API-001**: The exact protected Category route set is:
  `GET /api/v1/admin/categories`,
  `POST /api/v1/admin/categories`,
  `PATCH /api/v1/admin/categories/reorder`,
  `GET /api/v1/admin/categories/{category}`,
  `PATCH /api/v1/admin/categories/{category}`,
  `DELETE /api/v1/admin/categories/{category}`, and
  `POST /api/v1/admin/categories/{category}/restore`.
- **API-002**: The exact protected nested Subcategory route set is:
  `GET /api/v1/admin/categories/{category}/subcategories`,
  `POST /api/v1/admin/categories/{category}/subcategories`,
  `PATCH /api/v1/admin/categories/{category}/subcategories/reorder`,
  `GET /api/v1/admin/categories/{category}/subcategories/{subcategory}`,
  `PATCH /api/v1/admin/categories/{category}/subcategories/{subcategory}`,
  `DELETE /api/v1/admin/categories/{category}/subcategories/{subcategory}`,
  and
  `POST /api/v1/admin/categories/{category}/subcategories/{subcategory}/restore`.
- **API-003**: The exact public read-only route set is:
  `GET /api/v1/public/categories`,
  `GET /api/v1/public/categories/{categorySlug}`,
  `GET /api/v1/public/categories/{categorySlug}/subcategories`, and
  `GET /api/v1/public/categories/{categorySlug}/subcategories/{subcategorySlug}`.
  No public write route may exist.
- **API-004**: Requests and responses MUST use the shared envelope and
  `camelCase` JSON field naming. Unknown request fields MUST be rejected.
- **API-005**: Admin APIs MUST provide the approved create, list, show, update,
  delete, restore, and reorder behavior for root Categories and nested
  Subcategories only.
- **API-006**: Public APIs MUST provide localized list and detail behavior only
  for publicly visible Categories and Subcategories.
- **API-007**: Stable machine-readable codes, permissions, route segments,
  query keys, and JSON keys MUST remain English values.
- **API-008**: Locale MUST resolve from `Accept-Language` before public slug
  lookup and response rendering. Arabic is the default locale and English is
  the supported fallback locale.
- **API-009**: Public slug lookup MUST use only the slug column for the resolved
  locale: Arabic requests resolve through `slug_ar` and English requests
  resolve through `slug_en`. A slug belonging only to the other locale MUST
  return the approved non-disclosing `404`; cross-locale slug fallback is
  prohibited.
- **API-010**: Responses MUST preserve approved localization metadata such as
  `Content-Language` and `Vary: Accept-Language`.
- **API-011**: The stable error-code inventory for this feature MUST include,
  where applicable: `UNAUTHENTICATED`, `USER_INACTIVE`, `FORBIDDEN`,
  `VALIDATION_ERROR`, `CATEGORY_NOT_FOUND`, `SUBCATEGORY_NOT_FOUND`, `RESOURCE_NOT_FOUND`,
  `CATEGORY_HAS_SUBCATEGORIES`, `SUBCATEGORY_HAS_SERVICES`,
  `PARENT_CATEGORY_DELETED`, `CATEGORY_NOT_DELETED`,
  `SUBCATEGORY_NOT_DELETED`, `RATE_LIMITED`, and `INTERNAL_ERROR`.
- **API-012**: Status behavior MUST follow the approved contract:
  `201` for create; `200` for list, show, update, reorder, restore, and
  successful soft delete; `401` for unauthenticated; `403` for inactive admin
  or missing permission; `404` for missing/hidden public records and nested
  mismatch; `409` for dependency-blocked deletion, invalid restore state, or
  deleted parent; and `422` for validation failure. Successful delete responses
  MUST use the shared success envelope with `data: null`; Feature 003 MUST NOT
  return `204`.

### Verification Requirements *(mandatory)*

- **VR-001**: The feature MUST include protected API Feature Tests for Category
  and Subcategory success, validation, authorization, dependency, localization,
  localized Admin index projection, Query Builder `filter[key]` syntax,
  allow-listed sorting, description-pair create/update behavior, exact routes,
  permissions, and contract behavior.
- **VR-002**: The feature MUST include public API tests for Arabic and English
  list/detail localization, localized descriptions, locale-specific slug
  lookup, wrong-locale slug `404`, visibility inheritance, ordering,
  `Content-Language`, `Vary: Accept-Language`, and `meta.localeLinks`.
- **VR-003**: The feature MUST include real MySQL concurrency coverage for
  reorder and dependency-sensitive write races.
- **VR-004**: The feature MUST include architecture coverage proving there is
  no third-level route, no force-delete route, no media/upload route, and no
  out-of-scope infrastructure.
- **VR-005**: Acceptance requires synchronized OpenAPI and Postman coverage for
  the canonical admin and public operations.

### Key Entities *(include if feature involves data)*

- **Category**: A root catalog classification record with bilingual names,
  optional bilingual descriptions, localized slugs, ordering, active state,
  soft deletion state, and no parent.
- **Subcategory**: A child catalog classification record with the same
  bilingual content model as a Category but scoped to one root Category and
  prohibited from having children.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Administrators can create, update, reorder, soft-delete, and
  restore Categories and Subcategories using the approved routes without
  creating invalid hierarchy records.
- **SC-002**: 100% of public requests for inactive, deleted, or ancestor-hidden
  catalog nodes return non-disclosing `404` outcomes.
- **SC-003**: 100% of accepted Category and Subcategory records preserve both
  localized slugs until an explicit slug-edit action occurs.
- **SC-004**: Concurrency verification shows no partial reorder result and no
  invalid visible hierarchy after approved simultaneous write scenarios.
- **SC-005**: Every accepted description mutation leaves either two valid
  localized descriptions or two `null` descriptions; no record persists a
  one-language-only description.
- **SC-006**: Restoring a Subcategory under an inactive, non-deleted Category
  succeeds while the Subcategory remains absent from public responses until
  the parent becomes active.
- **SC-007**: Every slug-bound public detail response provides valid Arabic and
  English alternate API URLs in `meta.localeLinks`, while response content and
  lookup remain restricted to the resolved locale.
- **SC-008**: Every successful Category or Subcategory soft delete returns
  `200` with the shared success envelope and `data: null`.
- **SC-009**: 100% of Admin Category and Subcategory index items expose only
  one localized `name`, `description`, and `slug` according to
  `Accept-Language`, while detail and mutation responses remain bilingual.
- **SC-010**: All accepted Admin index filters use `filter[key]` syntax and all
  accepted sorts use the allow-listed Query Builder `sort` parameter.

## Assumptions

- Feature 004 will later introduce Services and will implement the observable
  `SUBCATEGORY_HAS_SERVICES` dependency rule promised by this feature.
- Public catalog responses do not need pagination in the MVP because the
  approved scope expects a manageable number of visible Categories and
  Subcategories.
- Search, filters, and sorting remain limited to Query Builder allow-lists.
  Filters use `filter[key]`, sorting uses `sort`, and pagination uses `page` and
  `perPage`.
- Admin index localization affects serialization and locale-aware name sorting;
  `filter[search]` still searches both Arabic and English content.
- Public routes use the exact authoritative paths under `/api/v1/public/categories`;
  the `/public` segment is mandatory for this feature.
- Updating one localized description requires submitting its translated pair;
  both values may be sent as `null` to clear them together.
- The existing Feature 001 authentication and middleware stack remains the
  protected route boundary for this feature.
