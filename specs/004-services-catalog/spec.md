# Feature Specification: Services Catalog

**Feature Branch**: `004-services-catalog`

**Created**: 2026-07-30

**Status**: Draft

**Input**: User description: "[$speckit-specify](C:\\xampp\\htdocs\\serviceEcommerce\\.agents\\skills\\speckit-specify\\SKILL.md) let's build the 4th feature use this file [004-services-catalog.md](docs/features/004-services-catalog.md) as referance to it and commit to each line in it"

## Scope and Governing Context *(mandatory)*

**Approved source**: `docs/features/004-services-catalog.md`

**In scope**:

- Admin CRUD for services with bilingual core content, classification, pricing,
  availability, publication, production time, and SEO data
- Admin soft delete and restore for services
- Admin list, search, filter, sort, and paginate services
- Admin CRUD for service specifications
- Admin CRUD for service order fields
- Admin CRUD for service pricing options, including transactional nested value
  mutations through the parent pricing-option update flow
- Admin media index, upload, alt-text update, delete, and set-main operations
- Public localized service list and detail endpoints
- Service assignment to no category, a root category only, or a root category
  with one matching subcategory
- Category and subcategory deletion guards that account for non-deleted
  services
- Transactional multipart service creation with nested records and media

**Out of scope**:

- Order submission, customer answers, option snapshots, and final order pricing
- Quote-required pricing
- Public sorting
- Media reordering
- Restore APIs for specifications, order fields, pricing options, or pricing
  option values
- Separate public nested service routes under categories or subcategories
- Rich text, HTML, or Markdown service content
- Multiple categories or multiple subcategories per service
- Frontend implementation in React or Next.js

**Governing documents reviewed**:

- `.specify/memory/constitution.md`
- `AGENTS.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- `docs/02-standards/api-standards.md`
- `docs/02-standards/database-standards.md`
- `docs/02-standards/file-storage-standards.md`
- `docs/02-standards/authorization-standards.md`
- `docs/02-standards/testing-standards.md`
- `docs/02-standards/localization-standards.md`
- `docs/features/004-services-catalog.md`

**Known conflicts**:

- None. Governing documents were synchronized on 2026-07-30 so the approved
  service-classification model now allows unclassified, root-category-only, and
  root-category-plus-subcategory services.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Admin manages core services (Priority: P1)

An authenticated Super Admin creates, views, updates, soft deletes, restores,
and lists services with the approved bilingual content, pricing, classification,
status, and SEO rules.

**Why this priority**: Without the core service entity, the catalogue cannot be
managed or exposed to later nested resources or public browsing.

**Independent Test**: Can be fully tested by creating services in each approved
classification mode, listing them through the admin index, updating service-only
fields, soft deleting, restoring, and verifying dependency guards and localized
index projection.

**Acceptance Scenarios**:

1. **Given** an authenticated administrator with `services.create`, **When**
   they create a service without category or subcategory using valid bilingual
   content and valid pricing, **Then** the API returns `201 Created` with the
   shared success envelope and the service is persisted as unclassified.
2. **Given** an authenticated administrator with `services.create`, **When**
   they create a service with a root category only, **Then** the service is
   stored with that category and a null subcategory.
3. **Given** an authenticated administrator with `services.create`, **When**
   they create a service with a root category and a subcategory that belongs to
   that category, **Then** the service is stored with both links.
4. **Given** an authenticated administrator, **When** they submit a
   subcategory without its parent category or with a mismatched parent category,
   **Then** the API returns `422` with a stable validation or business-rule
   error.
5. **Given** an existing service with slugs, **When** the administrator updates
   the names without explicitly supplying new slugs, **Then** both slugs remain
   unchanged.
6. **Given** a soft-deleted service, **When** an administrator with
   `services.restore` restores it, **Then** the service returns with
   `isActive = false` and invalid deleted classification links are cleared
   atomically according to the approved restore rules.

---

### User Story 2 - Admin manages service components and media safely (Priority: P1)

An authenticated Super Admin manages specifications, order fields, pricing
options and values, and service media through protected admin APIs that enforce
transactional integrity, permission isolation, and file-safety rules.

**Why this priority**: A service catalogue is incomplete without the managed
configuration and media that explain the service and affect future ordering.

**Independent Test**: Can be fully tested by creating a service, then using the
dedicated child-resource endpoints to create, list, update, and delete
specifications, order fields, pricing options and values, and media while
verifying limits, ownership, and safe file handling.

**Acceptance Scenarios**:

1. **Given** a start-from service and an administrator with
   `service-pricing-options.create`, **When** they create a pricing option with
   values, **Then** the option and its values are persisted and returned in the
   approved order.
2. **Given** a fixed-price service, **When** an administrator submits pricing
   options during creation or later through the pricing-option APIs, **Then**
   the API returns `422 VALIDATION_ERROR`.
3. **Given** an existing pricing option, **When** the administrator updates it
   using mixed value `actionStatus` items, **Then** all create, update, and
   delete actions execute transactionally and invalid duplicate or conflicting
   IDs are rejected.
4. **Given** a service without images, **When** the administrator uploads one
   or more valid images and none is marked main, **Then** the first uploaded
   image becomes main automatically.
5. **Given** a service with a main image, **When** the current main image is
   deleted and other images remain, **Then** the oldest remaining image by
   `id ASC` becomes main atomically.
6. **Given** a service with one video already stored, **When** the
   administrator uploads a second video, **Then** the API rejects the request
   with the approved video-limit error.

---

### User Story 3 - Public visitors browse active localized services (Priority: P2)

A public visitor browses only active, non-deleted services through localized
list and detail endpoints that expose summary or detail data according to the
approved contract while keeping technical identifiers stable.

**Why this priority**: Public read APIs are the business-facing outcome of the
catalogue, but they depend on the admin-managed catalogue already existing.

**Independent Test**: Can be fully tested by creating a mix of active,
inactive, available, unavailable, classified, and unclassified services, then
calling the public list and detail endpoints with both locales and approved
filters.

**Acceptance Scenarios**:

1. **Given** active services in multiple classification states, **When** a
   public caller requests `GET /api/v1/public/services`, **Then** only
   non-deleted active services are returned with localized summary fields and no
   admin-only or deleted fields.
2. **Given** an active service assigned to an inactive category or inactive
   subcategory, **When** a public caller requests the all-services list,
   **Then** the service may still appear but the inactive classification record
   is not exposed in the response.
3. **Given** a public caller filters by category or subcategory slug,
   **When** the slug matches only the alternate locale column, **Then** the
   filter still resolves correctly while response localization follows
   `Accept-Language`.
4. **Given** a public caller sends `priceFrom > priceTo`, **When** they request
   the public list, **Then** the API returns `422 VALIDATION_ERROR`.
5. **Given** an active unavailable service, **When** a public caller opens its
   detail endpoint, **Then** the service remains visible and exposes
   `isAvailable = false`.

---

### User Story 4 - Category and subcategory deletion remains protected (Priority: P2)

An authenticated administrator cannot delete a category or subcategory that is
still referenced by a non-deleted service, and concurrent classification writes
cannot bypass this protection.

**Why this priority**: Feature 003 explicitly deferred real service dependency
checks, so Feature 004 must complete the hierarchy-safety contract.

**Independent Test**: Can be fully tested by creating classified services,
attempting category and subcategory deletion, soft deleting the services, and
running MySQL-backed concurrency checks around delete-versus-create,
delete-versus-update, and restore races.

**Acceptance Scenarios**:

1. **Given** a category with a non-deleted service assigned directly to it,
   **When** an administrator deletes the category, **Then** the API returns
   `409 CATEGORY_HAS_SERVICES`.
2. **Given** a subcategory with a non-deleted service assigned to it, **When**
   an administrator deletes the subcategory, **Then** the API returns
   `409 SUBCATEGORY_HAS_SERVICES`.
3. **Given** the only referencing services are soft deleted, **When** the
   administrator deletes the category or subcategory, **Then** the deletion is
   allowed.
4. **Given** concurrent category or subcategory deletion and service
   classification writes, **When** both requests race, **Then** no committed
   outcome leaves a non-deleted service linked to a deleted hierarchy record.

### Edge Cases

- What happens when a service update changes `categoryId` to a new root
  category while omitting `subcategoryId`? The system must atomically keep the
  new category and clear the old subcategory.
- What happens when a restore operation finds a deleted category but a
  non-deleted subcategory ID still stored on the service? The system must clear
  both links because the parent category is no longer valid.
- How does the system handle two uploaded images both marked `isMain = true` in
  one request? The request must fail with `422 VALIDATION_ERROR`.
- How does the system handle `values: []` on pricing-option update? It must
  leave all existing values unchanged.
- How does the system handle active service publication when a start-from
  pricing option has no active values? Activation must be rejected when the
  approved activation rule requires at least one active value per existing
  option.
- How does the system handle public filters for inactive or deleted category or
  subcategory slugs? The API must return `200 OK` with an empty collection
  rather than exposing hidden hierarchy state.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST allow administrators with `services.create` to
  create services in exactly three classification states: unclassified, root
  category only, and root category plus one matching subcategory.
- **FR-002**: The system MUST reject any create or update request that attempts
  to assign a subcategory without its parent category, a deleted category, an
  inactive category, a deleted subcategory, an inactive subcategory, or a
  subcategory that belongs to a different root category.
- **FR-003**: The system MUST require bilingual plain-text values for
  `nameAr`, `nameEn`, `shortDescriptionAr`, `shortDescriptionEn`,
  `descriptionAr`, and `descriptionEn` during service creation.
- **FR-004**: The system MUST support optional bilingual pairs for production
  time, SEO title, SEO description, and SEO tags, where one locale value cannot
  be stored without the matching other-locale value.
- **FR-005**: The system MUST support only two price types in this feature:
  fixed and start-from.
- **FR-006**: The system MUST require `basePrice > 0` and MUST reject pricing
  options for fixed-price services.
- **FR-007**: The system MUST allow pricing options only for start-from
  services and MUST restrict every value `priceAdjustment` to a non-negative
  amount.
- **FR-008**: The system MUST generate localized slugs from the matching
  localized names on create when omitted and MUST keep slugs stable unless the
  administrator explicitly submits new slugs.
- **FR-009**: The system MUST enforce global slug uniqueness across both locale
  slug columns, including soft-deleted services.
- **FR-010**: The system MUST default new services to `isActive = false` and
  `isAvailable = true` unless approved input explicitly changes them.
- **FR-011**: The system MUST support service soft delete and restore, and a
  restored service MUST always return inactive.
- **FR-012**: The system MUST, during service restore, clear both category and
  subcategory when the category was deleted and clear only the subcategory when
  the category remains but the subcategory was deleted.
- **FR-013**: The system MUST allow up to 30 soft-deletable specifications per
  service, each with required bilingual label and value pairs and deterministic
  display ordering by `sortOrder ASC, id ASC`.
- **FR-014**: The system MUST allow up to 20 soft-deletable service order
  fields per service, each with required bilingual labels, boolean
  `isRequired`, and deterministic display ordering by `sortOrder ASC, id ASC`.
- **FR-015**: The system MUST keep the order-field type internal to the backend
  and fixed to text in this feature, without accepting or exposing it in the
  external API contract.
- **FR-016**: The system MUST allow up to 10 soft-deletable pricing options per
  service and up to 30 soft-deletable values per option.
- **FR-017**: The system MUST expose the pricing option `inputType` integer in
  admin and public service detail responses so frontend consumers can render the
  approved selection mode.
- **FR-018**: The system MUST update pricing-option values only through the
  parent pricing-option update route using the approved `actionStatus` contract
  and one database transaction.
- **FR-019**: The system MUST support transactional multipart service creation
  that can create the base service, specifications, order fields, pricing
  options, pricing-option values, media metadata, and physical files as one
  atomic workflow.
- **FR-020**: The system MUST support service images in approved image formats
  and one optional service video in approved video formats, all on the
  configured public disk.
- **FR-021**: The system MUST enforce a maximum of 10 images and 1 video per
  service, MUST forbid videos from being main media, and MUST keep at most one
  main image.
- **FR-022**: The system MUST automatically select the first uploaded image as
  main when images are uploaded without any explicit main flag.
- **FR-023**: The system MUST hard delete media rows and physical files when a
  service-media delete operation succeeds, while service soft delete MUST retain
  existing media rows and files.
- **FR-024**: The system MUST expose admin service-index rows in the resolved
  locale only, while admin detail, create, and update responses MUST return both
  locales and all active non-deleted child records required by the contract.
- **FR-025**: The system MUST provide a public list endpoint and public detail
  endpoint that return only active, non-deleted services.
- **FR-026**: The public list endpoint MUST support only the approved filters:
  `filter[search]`, `filter[category]`, `filter[subcategory]`,
  `filter[priceFrom]`, `filter[priceTo]`, and `filter[isAvailable]`.
- **FR-027**: The admin list endpoint MUST support only the approved filters:
  `filter[search]`, `filter[categoryId]`, `filter[subcategoryId]`,
  `filter[priceType]`, `filter[isActive]`, `filter[isAvailable]`, and
  `filter[trashed]`.
- **FR-028**: The admin list endpoint MUST support only the approved sorts:
  `basePrice`, `-basePrice`, `createdAt`, and `-createdAt`.
- **FR-029**: The public list endpoint MUST NOT accept a client-controlled
  `sort` parameter and MUST use deterministic internal ordering.
- **FR-030**: The system MUST block root-category deletion with
  `CATEGORY_HAS_SERVICES` when any non-deleted service is assigned directly to
  that category, and MUST block subcategory deletion with
  `SUBCATEGORY_HAS_SERVICES` when any non-deleted service is assigned to that
  subcategory.

### Actors and Authorization *(mandatory for protected behaviour)*

- **AR-001**: Only authenticated active administrators may access
  `/api/v1/admin/services*` endpoints.
- **AR-002**: Core service routes MUST enforce separate permissions:
  `services.view`, `services.create`, `services.update`, `services.delete`, and
  `services.restore`.
- **AR-003**: Specification routes MUST enforce separate permissions under the
  `service-specifications.*` namespace.
- **AR-004**: Order-field routes MUST enforce separate permissions under the
  `service-order-fields.*` namespace.
- **AR-005**: Pricing-option routes and nested value mutations MUST enforce
  separate permissions under the `service-pricing-options.*` namespace.
- **AR-006**: Service-media routes MUST enforce separate permissions under the
  `service-media.*` namespace, including a distinct `service-media.set-main`
  permission for the set-main command.
- **AR-007**: Public service list and detail routes MUST remain unauthenticated.
- **AR-008**: Missing or invalid authentication MUST return `401
  UNAUTHENTICATED`, missing permission MUST return `403 FORBIDDEN`, and foreign
  nested child resources MUST return non-disclosing `404` responses.
- **AR-009**: Nested pricing-option values and media resources MUST always be
  validated against the parent service or pricing-option in the route and MUST
  not be accessible cross-parent.

### Trust, Security, and Content Boundaries *(mandatory)*

- **TR-001**: The backend MUST treat all submitted classification IDs, slugs,
  booleans, prices, sort orders, nested child IDs, `actionStatus` values, media
  types, alt text, and SEO fields as untrusted input and MUST revalidate them
  server-side.
- **TR-002**: The backend MUST reject HTML and Markdown in service content,
  production time, media alt text, specification values, pricing-option labels,
  order-field labels, and SEO text; all such content is plain text only.
- **TR-003**: The backend MUST generate storage filenames and paths itself and
  MUST never accept request-controlled storage paths or expose raw storage
  paths.
- **TR-004**: Service image uploads MUST allow only approved image formats up
  to 5 MB each; service video uploads MUST allow only approved video formats up
  to 100 MB; limits and MIME allow-lists must be enforced server-side.
- **TR-005**: The backend MUST not trust any client-supplied final calculated
  price and MUST derive future selectable-price outcomes from `basePrice` plus
  stored active value adjustments only.
- **TR-006**: Public responses MUST not expose internal field types, internal
  option types, deleted rows, raw file paths, or authorization-only metadata.

### Data Integrity and Concurrency *(mandatory when data changes)*

- **DI-001**: Services, specifications, order fields, pricing options, and
  pricing-option values MUST use relational ownership that prevents cross-parent
  access and preserves child visibility rules under soft delete.
- **DI-002**: Service slugs across both locales MUST remain globally unique and
  reserved even by soft-deleted services.
- **DI-003**: The create-service workflow with nested records and media MUST use
  one transaction and synchronous file compensation so no partial service or
  orphaned newly uploaded files are left on failure.
- **DI-004**: Pricing-option updates that mutate nested values through
  `actionStatus` MUST execute atomically and reject duplicate IDs or conflicting
  actions in one request.
- **DI-005**: Media set-main, main-image fallback on delete, and video
  uniqueness MUST preserve the one-main-image and one-video invariants under
  concurrent requests.
- **DI-006**: Category and subcategory deletion checks involving services MUST
  use race-safe locking and revalidation so no non-deleted service remains
  linked to a deleted category or subcategory.
- **DI-007**: Service soft delete MUST not delete media, specifications, order
  fields, pricing options, or pricing-option values; child visibility in normal
  responses must still exclude deleted rows according to the approved contract.

### API Contract and Localization *(mandatory for API behaviour)*

- **API-001**: Admin routes for this feature MUST live only under
  `/api/v1/admin/services/*` and public routes only under
  `/api/v1/public/services/*`.
- **API-002**: Create and update responses MUST use the shared success envelope;
  validation failures MUST use `422 VALIDATION_ERROR`; hierarchy deletion
  conflicts MUST use `409` with stable English machine codes.
- **API-003**: Money fields returned by the API, including `basePrice` and
  value `priceAdjustment`, MUST use fixed-precision decimal strings.
- **API-004**: Admin list pagination MUST use `page` and `perPage` with default
  `perPage = 15` and maximum `perPage = 100`; public list pagination MUST use
  default `perPage = 12` and maximum `perPage = 50`.
- **API-005**: Public filter slugs for category and subcategory MUST resolve by
  the request locale first and may fall back to the alternate locale column
  without changing response localization.
- **LOC-001**: Public service list and detail responses MUST resolve localized
  content by `Accept-Language` and return `Content-Language` plus
  `Vary: Accept-Language`.
- **LOC-002**: Admin create, update, and show responses MUST preserve both
  Arabic and English editing values for approved bilingual fields.
- **LOC-003**: Machine identifiers such as route segments, JSON keys, enum
  integers, permission names, and error codes MUST remain stable English
  contract values.
- **LOC-004**: Public detail responses MUST return the resolved locale only for
  localized service content, media alt text, specifications, order fields, and
  pricing-option labels and values.

### Verification Requirements *(mandatory)*

- **VR-001**: API Feature Tests MUST cover success, validation, unauthenticated,
  forbidden, not-found, business-rule, persistence, localization, and contract
  cases for admin services, specifications, order fields, pricing options and
  values, media, and public services.
- **VR-002**: File tests MUST cover allowed uploads, rejected MIME or size
  violations, one-main-image enforcement, one-video enforcement, main-image
  fallback, and filesystem compensation during failed transactional creation.
- **VR-003**: Route or architecture tests MUST prove that only the approved
  admin and public service routes exist, that permissions are mapped correctly,
  and that no public write routes are introduced.
- **VR-004**: Real MySQL concurrency tests MUST prove category/subcategory
  deletion guards, service classification writes, main-image selection, and
  video uniqueness under race conditions.
- **VR-005**: Final acceptance evidence for this feature requires passing Pest,
  Pint, and Larastan/PHPStan, plus synchronized OpenAPI and Postman artifacts.

### Key Entities *(include if feature involves data)*

- **Service**: The primary catalogue record containing bilingual service
  content, localized slugs, pricing type, base price, classification links,
  publication and availability booleans, optional production time, and optional
  SEO content.
- **Service Specification**: A soft-deletable bilingual label/value pair owned
  by one service and displayed to public visitors without affecting pricing.
- **Service Order Field**: A soft-deletable bilingual customer-input prompt
  owned by one service, limited in this feature to the internal text field
  type.
- **Service Pricing Option**: A soft-deletable configurable pricing group owned
  by one service, exposing one approved selection-mode input type to API
  consumers and owning one or more pricing-option values.
- **Service Pricing Option Value**: A soft-deletable bilingual selectable value
  owned by one pricing option with a non-negative price adjustment and active
  flag.
- **Service Media**: An image or video record owned by one service with public
  storage metadata, optional bilingual alt text for images, and main-image
  semantics for images only.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Administrators can create and retrieve services successfully in
  all three approved classification states using the documented API contract.
- **SC-002**: Public callers can retrieve only active non-deleted services in
  the requested locale, and hidden or inactive records never leak through the
  public endpoints.
- **SC-003**: Invalid classification, invalid fixed-pricing option usage,
  invalid media rules, and invalid price-range filters are rejected with the
  approved stable error outcomes in 100% of tested contract scenarios.
- **SC-004**: Service creation with nested records and media leaves no partial
  persisted service data and no orphaned newly uploaded files in all automated
  rollback scenarios.
- **SC-005**: Category and subcategory deletion guards prevent deletion when a
  non-deleted service still depends on the target hierarchy record in every
  tested success, conflict, and concurrency scenario.
- **SC-006**: The final repository quality gates for this feature pass, and the
  implemented API contract remains synchronized across specification, OpenAPI,
  Postman, and automated tests.

## Assumptions

- The repository continues using the approved single-database Laravel monolith,
  Sanctum admin authentication, Spatie Permission, and Spatie Query Builder
  patterns already established by Features 001 through 003.
- Feature 004 is responsible only for service-catalogue configuration and
  public browsing, not for order-time answer storage or quote-required pricing.
- The existing category/subcategory feature remains the authoritative hierarchy
  source, and Feature 004 only adds service dependency checks and service-side
  classification usage on top of it.
- Public storage remains the approved MVP media storage model, with its known
  authorization trade-offs already accepted in the governing file-storage
  standard.
