# Feature Specification: Hero Slider Management

**Feature Branch**: `[008-hero-slider-management]`

**Created**: 2026-08-03

**Status**: Ready for Planning

**Input**: Build the Hero Slider Management feature from
`docs/features/008-hero-slider-management.md` and preserve every approved
business rule in that reference.

## Scope and Governing Context *(mandatory)*

**Approved source**: `docs/features/008-hero-slider-management.md`

**In scope**:

- Administrator create, paginated index, show, partial update, permanent
  delete, activation, deactivation, image replacement, and position control
  for homepage Hero slides.
- A public, unpaginated, localized projection of active slides in deterministic
  display order.
- Exactly four `hero-slides.*` permissions and their idempotent assignment to
  `super-admin` through the normal permission flow.
- A maximum of 10 total slides, including inactive slides.
- Persistently complete Arabic and English title and description content and
  exactly one image for every slide.
- Transactional, lock-protected contiguous ordering for create, update, and
  delete, without a dedicated reorder operation.
- Secure image validation, storage, absolute URL projection, replacement,
  deletion, and database/filesystem compensation.
- MySQL-backed constraint, concurrency, file-failure, localization, API
  contract, Postman, formatting, static-analysis, and regression verification.

**Out of scope**:

- More than 10 slides; buttons, calls to action, or button fields.
- A reorder endpoint or `hero-slides.reorder` permission.
- Autoplay, delay, looping, arrows, dots, transitions, animation, custom CSS,
  theme colors, or overlay configuration.
- Mobile-specific images, videos, scheduling, visibility dates, targeting,
  A/B testing, analytics, or click tracking.
- Soft delete, public editing, caching, Redis, queues, or workers.
- React, Next.js, slider movement, rendering, or any other frontend behavior.

**Governing documents reviewed**:

- `.specify/memory/constitution.md`
- `AGENTS.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- `docs/02-standards/api-standards.md`
- `docs/02-standards/authorization-standards.md`
- `docs/02-standards/code-standards.md`
- `docs/02-standards/database-standards.md`
- `docs/02-standards/file-storage-standards.md`
- `docs/02-standards/localization-standards.md`
- `docs/02-standards/security-standards.md`
- `docs/02-standards/testing-standards.md`
- `docs/features/008-hero-slider-management.md`

**Known conflicts**:

- No blocking conflict remains. The reference previously named
  `App\Enums\StatusCode`; it has been corrected to the repository-authoritative
  `App\Enums\HttpStatusCode` without changing any HTTP behavior.
- Earlier shared documents use generic `hero-sections` examples and recommend
  a reorder capability when one is exposed. This approved feature specializes
  that terminology as `hero-slides` and explicitly exposes no reorder route or
  permission; create and update position fields provide the only move behavior.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Publish an Ordered Homepage Slide (Priority: P1)

An authenticated active administrator with create permission adds a complete
bilingual slide, an image, an active state, and optionally a display position.

**Why this priority**: Creating valid content is the minimum capability needed
to place a Hero slider on the public homepage.

**Independent Test**: Submit a valid multipart create request to an empty and a
populated slider, then verify the stored content, image, returned resource, and
the final contiguous position sequence.

**Acceptance Scenarios**:

1. **Given** fewer than 10 slides and no submitted position, **When** a valid
   slide is created, **Then** it is appended and returned with status 201.
2. **Given** slides at positions 1 through 3, **When** a valid slide is created
   at position 2, **Then** the new slide becomes 2 and the prior slides at 2
   and 3 move to 3 and 4 atomically.
3. **Given** a missing localized text field, missing image, invalid active
   value, unsafe image, or out-of-range position, **When** create is attempted,
   **Then** no row or file remains and the localized validation outcome is
   returned.
4. **Given** 10 active and inactive slides in total, **When** another create is
   attempted, **Then** it is rejected and the persisted count remains 10.

---

### User Story 2 - Manage Existing Slides Safely (Priority: P1)

An authorized administrator views both active and inactive slides, changes
only supplied fields, replaces an image when needed, moves a slide through its
position field, or permanently deletes it.

**Why this priority**: Administrators must keep content and display order
accurate without corrupting files or ordering.

**Independent Test**: Create several slides, exercise index, show, partial
update, upward and downward moves, image replacement, and deletion, then verify
resources, stored files, and positions after every operation.

**Acceptance Scenarios**:

1. **Given** active and inactive slides, **When** the Admin index is requested,
   **Then** it returns both languages, absolute image URLs, ascending positions,
   pagination, and an optional exact active-state filter.
2. **Given** a complete slide, **When** a partial update omits fields and image,
   **Then** omitted values and the existing image remain unchanged.
3. **Given** a replacement image, **When** the update commits, **Then** the new
   path is authoritative and the old file is deleted only after commit.
4. **Given** a slide is moved up or down, **When** update succeeds, **Then** all
   affected slides shift and the sequence remains unique and contiguous.
5. **Given** a slide is deleted, **When** the deletion commits, **Then** the row
   is permanently removed, later positions close the gap, and its image is
   deleted after commit.

---

### User Story 3 - Display Active Localized Slides Publicly (Priority: P1)

An unauthenticated visitor retrieves all active homepage slides in display
order and in the resolved Arabic or English language.

**Why this priority**: The public homepage is the consumer of the managed Hero
content and must receive a minimal, safe projection.

**Independent Test**: Seed active and inactive bilingual slides, request the
public endpoint with Arabic and English locale variants, and verify content,
headers, ordering, empty state, and excluded Admin fields.

**Acceptance Scenarios**:

1. **Given** mixed active states, **When** the public endpoint is requested,
   **Then** only active slides are returned in ascending persisted position.
2. **Given** `ar-EG` or `en-US`, **When** locale resolution occurs, **Then** the
   same neutral keys contain the matching Arabic or English values and the
   locale headers identify the resolved language.
3. **Given** no active slides, **When** the public endpoint is requested,
   **Then** the approved success envelope contains `data: []`.
4. **Given** any public response, **When** its fields are inspected, **Then** it
   contains only `title`, `description`, and an absolute `image` URL and does
   not expose IDs, activity, positions, dual-language keys, timestamps, or raw
   paths.

---

### User Story 4 - Enforce Independent Administration Permissions (Priority: P1)

Administrators receive only the Hero-slide operations granted by the four
stable permissions, while public reads remain open.

**Why this priority**: Content mutation and visibility must be enforced by the
backend rather than frontend controls or a hidden role bypass.

**Independent Test**: Exercise every Admin endpoint unauthenticated, inactive,
as a non-administrator, and with each permission absent or present; separately
verify the public endpoint without credentials.

**Acceptance Scenarios**:

1. **Given** no valid Admin authentication, **When** an Admin endpoint is
   requested, **Then** the existing Admin boundary rejects the request before
   endpoint execution.
2. **Given** an active administrator without the operation's permission,
   **When** that operation is requested, **Then** the shared 403 outcome is
   returned.
3. **Given** an active administrator with the exact permission, **When** the
   corresponding valid operation is requested, **Then** permission alone does
   not bypass validation, ordering, limit, or file rules.
4. **Given** the permission Seeder runs repeatedly, **When** synchronization
   completes, **Then** all four permissions exist once, super-admin receives
   them, and unrelated permissions remain unchanged.

### Edge Cases

- The first slide is created without a position, at position 1, or while two
  create requests race for the final available slot.
- A create position equals `count + 1`, exceeds it, is zero, negative,
  non-integer, repeated, array-shaped, or otherwise outside the exact contract.
- An update position equals the current position, moves from first to last, or
  from last to first.
- A move races another move, create, or delete against the same ordered set.
- The only slide is deleted, leaving an empty but valid sequence.
- Ten records include one or more inactive slides; deactivation does not free
  capacity, while permanent deletion does.
- Submitted text contains only whitespace, is null, or reaches/exceeds its
  exact title or description boundary after trimming.
- A file has an approved extension but mismatched MIME or actual content, a
  double extension, SVG content, exceeds 5 MiB, uses a malicious filename, or
  fails during upload.
- A new image is stored but the database transaction fails; the new file is
  removed and the prior row/file state remains authoritative.
- A database delete commits but physical image deletion fails; valid database
  state remains committed and the cleanup failure is logged safely.
- The public dataset contains inactive slides between active positions; array
  order still reflects the active subset's display order without returning
  position values.
- The route-bound slide does not exist or was deleted concurrently.
- Multipart `isActive="0"` is supplied and must not be treated as empty.
- A file is exactly 5 MiB or exceeds the boundary by one byte.
- Repeated `page`, `perPage`, or `filter[isActive]` query members are supplied.

## Requirements *(mandatory)*

### Functional Requirements

#### Endpoint, fields, and lifecycle

- **FR-001**: The feature MUST expose exactly these Admin routes:
  `GET /api/v1/admin/hero-slides`, `POST /api/v1/admin/hero-slides`,
  `GET /api/v1/admin/hero-slides/{heroSlide}`,
  `PATCH /api/v1/admin/hero-slides/{heroSlide}`, and
  `DELETE /api/v1/admin/hero-slides/{heroSlide}`.
- **FR-002**: The feature MUST expose exactly one public route:
  `GET /api/v1/public/hero-slides`; it MUST NOT expose public mutations or a
  dedicated reorder route in either route area.
- **FR-003**: An Admin slide resource MUST contain exactly `id`, `titleAr`,
  `titleEn`, `descriptionAr`, `descriptionEn`, `image`, `isActive`, and
  `position`; timestamps and button fields are not part of the contract.
- **FR-004**: Create MUST accept multipart fields `titleAr`, `titleEn`,
  `descriptionAr`, `descriptionEn`, `image`, `isActive`, and optional
  `position`; all except `position` are required.
- **FR-005**: PATCH MUST accept only the same fields, all optional in the
  request, preserve omitted values, and leave the persisted slide complete.
- **FR-006**: Required text fields MUST be trimmed before validation and
  persistence, reject null or empty-after-trim values, and enforce title
  lengths 1..150 and description lengths 1..1000.
- **FR-007**: `isActive` MUST be persistently required. Multipart input MUST
  accept only the exact scalar lexical values `"0"` and `"1"`, normalized
  respectively to inactive and active. The value `"0"` MUST be treated as
  present and valid, never as empty or false. Boolean words, signed values,
  decimals, zero-padded values, arrays, and whitespace-padded values such as
  `true`, `false`, `+1`, `1.0`, `01`, `isActive[]=1`, or `" 1 "` MUST be
  rejected.
- **FR-008**: A successful create MUST return status 201 and the complete Admin
  resource; successful show/update MUST return the complete Admin resource.
- **FR-009**: Delete MUST permanently remove the row, use status 200, and
  return `data: null` inside the shared success envelope; no soft-delete or
  restore behavior exists.

#### Image behavior

- **FR-010**: Every persisted slide MUST have exactly one image; create requires
  it, PATCH may omit it to preserve the current file, and no remove-image flag
  or image-less state exists.
- **FR-011**: Image input MUST allow only JPG/JPEG, PNG, or WebP with a
  maximum size of exactly 5 MiB (`5,242,880` bytes). A file exactly at the
  boundary MUST be accepted; a file one byte larger MUST be rejected. SVG and
  every other format MUST be rejected.
- **FR-012**: Validation MUST check upload success, extension, detected MIME,
  and actual image content and MUST reject mismatches, executable content,
  traversal attempts, and unsafe/double-extension input.
- **FR-013**: Stored image names and directories MUST be backend-controlled and
  unguessable; original client filenames MUST NOT determine stored names.
- **FR-014**: Images MUST use the configured public filesystem disk, and every
  Admin and public resource MUST return an absolute public URL while never
  returning a raw path, local absolute path, or internal directory.
- **FR-015**: Image replacement MUST store the new file first, commit its new
  path through the update transaction, and delete the old file only after
  commit; transaction failure MUST remove the new file and preserve the old
  database/file state.
- **FR-016**: Slide deletion MUST lock and delete the row and recompact ordering
  in one transaction, then delete the old image after commit; a post-commit
  cleanup failure MUST be logged without rolling back or corrupting the valid
  database state. The API response remains successful after commit, and logs
  MUST exclude the client original filename, credentials, and unnecessary
  absolute local paths.
- **FR-017**: Upload/storage failures MUST use the approved safe error contract
  and MUST NOT expose internal paths, filenames, SQL, stack traces, or
  filesystem details.

#### Count and ordering

- **FR-018**: At most 10 total slide rows MAY exist, counting active and
  inactive rows; changing activity MUST NOT change capacity and permanent
  deletion MUST free one slot.
- **FR-019**: The limit check MUST execute under the ordered-set transaction
  lock, so concurrent creates cannot persist more than 10 rows.
- **FR-020**: Every persisted position MUST be a positive integer starting at
  1, unique, gap-free, and contiguous across all slides regardless of active
  state.
- **FR-021**: Create without position MUST append at `current count + 1` under
  lock.
- **FR-022**: Create with position MUST accept only 1 through
  `current count + 1`, insert there, and shift every existing row at or after
  that position forward by one atomically.
- **FR-023**: PATCH without position MUST preserve the slide's position.
- **FR-024**: PATCH with position MUST accept only 1 through the current total
  count; the current position is a valid no-op, an upward move shifts the
  intervening rows forward, and a downward move shifts them backward.
- **FR-024A**: Submitted `position` values MUST use a canonical positive
  decimal-integer string with no sign, decimal point, leading zero,
  surrounding whitespace, array shape, or repeated value. Examples such as
  `01`, `+1`, `1.0`, `" 1 "`, `position[]=1`, or duplicate `position`
  fields MUST be rejected before ordering logic executes.
- **FR-025**: Delete MUST shift all later positions backward by one so an empty
  set or a contiguous 1..N sequence remains after commit.
- **FR-026**: Create, update, and delete MUST each execute their database
  mutation inside a transaction that locks the current Hero-slide ordered set
  before count calculation, append, insertion, movement, deletion, or gap
  closing.
- **FR-027**: Ordering behavior MUST prevent duplicate positions, gaps, lost
  moves, excess rows, and partial database/image states under concurrent
  create, update, and delete requests; the database MUST additionally enforce
  unique persisted position values.
- **FR-027A**: Planning MUST select and document a unique-index-safe position
  shifting algorithm for insert, upward move, downward move, and compaction.
  The algorithm MUST avoid transient unique-key collisions inside MySQL,
  using a proven update order, temporary offset, or equivalent transactional
  technique, and MUST be covered by boundary and concurrency tests.

#### Admin and public reads

- **FR-028**: Admin index MUST order only by `position` ascending and MUST NOT
  accept search or custom sort input.
- **FR-029**: Admin index MUST be paginated with default `perPage=15`, maximum
  `perPage=100`, and only `page`, `perPage`, and `filter[isActive]` as approved
  query parameters.
- **FR-030**: `filter[isActive]` MUST be optional, accept only exact scalar
  lexical values `"0"` or `"1"`, return all slides when omitted, and be
  applied through an explicit query-filter allow-list. Unknown, repeated,
  array-shaped, empty, whitespace-only, signed, decimal, or zero-padded query
  values MUST be rejected. The value `"0"` MUST remain valid.
- **FR-031**: Admin index MUST return the shared pagination metadata with
  `currentPage`, `lastPage`, `perPage`, and `total`, even though the current
  business limit is 10.
- **FR-032**: Admin index and show MUST return active and inactive content, both
  languages, IDs, activity, positions, and absolute image URLs, but never raw
  paths or unapproved fields.
- **FR-033**: Public index MUST require no authentication, return every active
  slide without pagination in ascending position order, and return `data: []`
  when none are active.
- **FR-034**: Each public item MUST contain exactly neutral keys `title`,
  `description`, and `image`; it MUST NOT expose IDs, `isActive`, `position`,
  dual-language keys, timestamps, or raw paths. Array order is the display
  order.
- **FR-035**: Slider movement, rendering, timing, animation, arrows, dots,
  looping, and autoplay MUST remain frontend responsibilities and MUST not add
  backend fields or settings.

#### Persistence and stable errors

- **FR-036**: The feature MUST persist slides in one `hero_slides` data set
  containing an unsigned-big-integer identity, bilingual title/description,
  image path, activity, position, and timestamps, with no additional Hero
  table required.
- **FR-037**: Persistence MUST enforce a primary key, unique position, and an
  index supporting active-state filtering in position order; the application
  transaction enforces the maximum count and contiguous sequence.
- **FR-038**: The feature MAY return stable codes `VALIDATION_ERROR`,
  `HERO_SLIDE_NOT_FOUND`, `HERO_SLIDE_LIMIT_REACHED`, and
  `HERO_SLIDE_IMAGE_UPLOAD_FAILED` only in the appropriate approved shared
  error envelope.
- **FR-039**: Unauthenticated Admin requests MUST resolve to 401, missing
  permission to 403, missing slide to 404, invalid input and the 10-slide limit
  to 422, and unexpected storage/database failures to a safe project-standard
  server outcome.
- **FR-040**: Application-controlled HTTP statuses MUST use
  `App\Enums\HttpStatusCode`; no additional HTTP-status enum may be created.
- **FR-041**: The Postman collection and API contract documentation MUST include
  every Feature 008 route, exact request/query fields, permission requirement,
  multipart image rules, activity values, ordering behavior, responses, and
  stable failure examples without adding collection variables beyond the
  repository-approved set.

### Actors and Authorization *(mandatory for protected behaviour)*

- **AR-001**: Admin index and show MUST require `hero-slides.view`; create MUST
  require `hero-slides.create`; update MUST require `hero-slides.update`; and
  delete MUST require `hero-slides.delete`.
- **AR-002**: The feature MUST introduce exactly those four stable permissions,
  no wildcard permission, no broad `manage`, and no reorder permission.
- **AR-003**: The existing idempotent permission Seeder MUST create the four
  permissions using the configured guard, clear permission cache as required,
  assign them to `super-admin`, and preserve unrelated permissions.
- **AR-004**: No hidden super-admin bypass may replace backend permission
  evaluation, and frontend visibility or supplied permission values are never
  authoritative.
- **AR-005**: Admin middleware order MUST remain `auth:sanctum`, administrator
  type, active administrator, exact permission, then endpoint execution.
- **AR-006**: Public index MUST remain unauthenticated; Admin permissions do
  not alter its active-only visibility rule.
- **AR-007**: Permission possession MUST not bypass bilingual completeness,
  image, maximum-count, ordering, query, or file-security rules.

### Trust, Security, and Content Boundaries *(mandatory)*

- **TR-001**: Every client-supplied text, activity, position, filter,
  pagination, route identity, and file value MUST be treated as untrusted and
  validated against the exact approved contract.
- **TR-002**: Unknown mutation fields, button/CTA fields, remove-image flags,
  client paths, filenames, IDs, timestamps, sort names, and backend-generated
  values MUST be rejected or excluded according to the shared exact-contract
  convention.
- **TR-003**: Images are public-file assets only after strict validation;
  public storage MUST NOT be described as private or authorization-protected,
  and accidental exposure MUST be reduced through safe paths and filenames.
- **TR-004**: Titles and descriptions are plain display text. The backend MUST
  trim and store the submitted text without HTML sanitization, markup
  transformation, or rich-text interpretation. API consumers MUST render the
  values as text rather than raw HTML. The backend MUST reject null bytes and
  control characters that are not approved line breaks, while ordinary angle
  brackets remain inert text.
- **TR-005**: Query input MUST use explicit allow-lists and MUST never become a
  request-provided database column or raw ordering expression.
- **TR-006**: Responses, logs, and localized errors MUST avoid secrets,
  client original filenames, credentials, unnecessary absolute local paths,
  stack traces, SQL, lock internals, and filesystem implementation details.
  Post-commit cleanup logs MAY contain only the minimum backend-generated
  correlation and storage identifier required for safe diagnosis.

### Data Integrity and Concurrency *(mandatory when data changes)*

- **DI-001**: A Hero slide identity is immutable; bilingual text, one image
  path, activity, and one unique position are persistently required.
- **DI-002**: The authoritative ordering invariant is either an empty set or
  exactly the integer sequence 1..N with N no greater than 10.
- **DI-003**: Create, move, and delete MUST modify the target and every affected
  position in one atomic, lock-protected transaction.
- **DI-004**: Concurrent mutations MUST serialize at the database boundary and
  may expose only a fully committed prior or resulting sequence.
- **DI-005**: Database uniqueness and the transactional count check MUST remain
  final defenses even when request validation has already succeeded.
- **DI-006**: File storage compensation MUST preserve the last committed row
  and image relationship; database rollback does not excuse leaked new files.
- **DI-007**: Activity changes and localized-content updates MUST not rewrite
  positions or unrelated slides unless an explicit valid move is requested.
- **DI-008**: Deletion has no historical snapshot obligation for this feature
  and is permanent, but must not alter unrelated feature data.

### API Contract and Localization *(mandatory for API behaviour)*

- **API-001**: All routes, methods, multipart fields, query keys, resources,
  pagination keys, success statuses, and errors MUST match FR-001 through
  FR-041 and the shared response envelopes.
- **API-002**: Admin index query shape MUST be limited to optional scalar
  `page`, `perPage`, and `filter[isActive]`; no `sort`, search, include, or
  other filter is approved.
- **API-002A**: Before normal query-bag validation, the original query string
  MUST be inspected to reject repeated approved members such as
  `page=1&page=2`, `perPage=10&perPage=20`, or
  `filter[isActive]=0&filter[isActive]=1`, because PHP request normalization
  may otherwise collapse them to one value. Array-shaped parameters,
  unknown top-level keys, and unknown nested filter keys MUST also be
  rejected.
- **API-003**: Public index MUST be an unpaginated array projection whose array
  order alone communicates display order.
- **API-004**: Controlled status responses MUST use `HttpStatusCode::OK`,
  `HttpStatusCode::CREATED`, `HttpStatusCode::UNAUTHORIZED`,
  `HttpStatusCode::FORBIDDEN`, `HttpStatusCode::NOT_FOUND`, and
  `HttpStatusCode::UNPROCESSABLE_ENTITY` as applicable.
- **LOC-001**: Arabic and English are the only resolved locales; locale variants
  such as `ar-EG` and `en-US` MUST resolve through the existing locale
  middleware to `ar` and `en` respectively.
- **LOC-002**: Public `title` and `description` MUST use only the resolved
  language, while Admin resources always return both approved translations.
- **LOC-003**: User-facing success, validation, authorization, not-found,
  limit, and upload-failure messages MUST be localized to the resolved locale.
- **LOC-004**: Public responses MUST include `Content-Language: ar|en` and
  `Vary: Accept-Language`; Admin `Accept-Language` affects messages and
  validation errors, not the dual-language resource shape.
- **LOC-005**: JSON keys, routes, permissions, error codes, query names,
  numeric activity/position values, and stored identifiers MUST remain stable
  English technical values and MUST not be translated.

### Verification Requirements *(mandatory)*

- **VR-001**: API tests MUST cover every Admin and public success path, exact
  route/method, shared envelope, status, exact response keys, and Postman/API
  contract example.
- **VR-002**: Authorization tests MUST cover unauthenticated, inactive,
  non-administrator, each missing/exact permission, middleware order, public
  access, Seeder idempotency, super-admin assignment, unrelated-permission
  preservation, cache reset, and absence of reorder/wildcard permissions.
- **VR-003**: Create tests MUST cover all required fields, trimming, exact text
  boundaries, plain-text rules, exact lexical activity and
  position values including valid `"0"`, omitted/submitted/boundary positions,
  append, insertion shifts, the 10-row limit including inactive rows, valid
  images, SVG, exact 5 MiB and 5 MiB-plus-one-byte
  boundaries, and MIME/content mismatch.
- **VR-004**: Admin read tests MUST cover pagination defaults/maximum,
  ascending fixed order, exact active filter including lexical zero,
  invalid/unknown/array-shaped query input, raw repeated-member detection,
  both languages, absolute URL, and raw-path/extra-field absence.
- **VR-005**: Update tests MUST cover omitted-field preservation, rejected
  clearing, image preservation/replacement, no image
  removal, current-position no-op, upward/downward moves, canonical position
  lexical rules, boundaries, unique-index-safe affected shifts, and missing
  slide.
- **VR-006**: Delete tests MUST cover permanent removal, status 200 with null
  data, position compaction, empty sequence, missing slide, and after-commit
  image removal.
- **VR-007**: File tests MUST cover allowed extensions/MIME/content, the
  exact 5 MiB and 5 MiB-plus-one-byte boundaries,
  unsafe names and paths, safe generated storage, absolute URLs,
  transaction-failure cleanup, old-file retention before commit, and logged
  post-commit cleanup failure without database corruption.
- **VR-008**: Public tests MUST cover active-only visibility, order, Arabic and
  English variants, headers, empty array, unpaginated output, exact neutral
  keys, and absence of IDs, activity, positions, dual languages, timestamps,
  and raw paths.
- **VR-009**: Dedicated MySQL concurrency tests MUST prove empty-set first-create
  serialization, the 10-slide limit, unique/gap-free positions,
  unique-index-safe competing moves, concurrent create/delete integrity,
  and rollback/file compensation after failed mutations.
- **VR-010**: Database verification MUST prove the primary key, unique
  position, active-position index, maximum-count workflow, reversible schema,
  and permanent-delete behavior.
- **VR-011**: Full MySQL Pest regression, Pint, and configured
  PHPStan/Larastan gates MUST pass before acceptance.

### Key Entities *(include if feature involves data)*

- **Hero Slide**: One homepage content item with immutable identity, complete
  Arabic and English title/description, one required stored image, independent
  active state, and one position within the global contiguous sequence.
- **Hero Slide Ordered Set**: The complete active-and-inactive collection used
  to enforce the 10-row maximum and the empty-or-1..N ordering invariant under
  concurrent mutations.
- **Admin Hero Slide Projection**: The bilingual management representation
  containing identity, content, absolute image URL, activity, and position.
- **Public Hero Slide Projection**: The localized active-only representation
  containing only title, description, and absolute image URL; array order is
  display order.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Authorized administrators can create, list, show, partially
  update, move, activate/deactivate, replace-image, and permanently delete Hero
  slides through the five approved Admin endpoints, while every unauthorized
  operation receives the approved access outcome.
- **SC-002**: The persisted set never exceeds 10 total rows, including inactive
  rows, across sequential and concurrent verification.
- **SC-003**: After every successful or failed create, move, and delete, the
  committed position set is empty or exactly 1..N with no duplicate or gap.
- **SC-004**: Every persisted slide contains both titles, both descriptions,
  one image, one activity value, and one position in 100% of accepted states.
- **SC-005**: Every accepted image is a JPG/JPEG, PNG, or WebP no larger than
  exactly 5 MiB, while SVG, mismatched content, unsafe
  files, and oversized uploads are rejected without a leaked new file.
- **SC-006**: Image replacement and deletion leave the database referencing
  exactly the last committed file; database failure preserves the previous
  state and post-commit cleanup failure does not corrupt it.
- **SC-007**: Admin index always uses ascending position order, returns the
  approved pagination structure, and accepts only the exact active-state
  filter plus pagination parameters.
- **SC-008**: Public index returns 100% of active slides and 0 inactive slides
  in approved display order, with `data: []` when no active slide exists.
- **SC-009**: Every public item contains exactly three approved neutral fields
  in the resolved language, and no API response exposes a raw storage path.
- **SC-010**: No button, slider-setting, reorder, video, schedule, targeting,
  analytics, soft-delete, cache, queue, or frontend implementation is added.
- **SC-011**: Every Feature 008 API, authorization, validation, file,
  localization, database, concurrency, and contract test passes on MySQL, and
  formatting and configured static analysis pass before release.
- **SC-012**: Non-canonical scalar mutation values, repeated or array-shaped
  Admin-index query parameters, and files larger than 5 MiB are rejected
  consistently without changing the
  last committed row, image, count, or ordering state.

## Assumptions

- The configured public filesystem disk and its URL generation are already
  available; this feature adds a Hero-slide-specific configured path/capability
  without changing the repository-wide public-storage decision.
- The existing locale middleware resolves supported language variants and
  supplies localized shared messages and response headers.
- The existing Admin authentication/type/active middleware and idempotent
  permission Seeder patterns remain authoritative and reusable.
- Public storage means possession of the complete URL may permit direct file
  retrieval; unguessable names and non-disclosure of raw paths are the approved
  MVP mitigation.
- The approved maximum of 10 makes locking the complete ordered set bounded.
  Planning MUST approve and document one MySQL-safe serialization mechanism
  that also protects an initially empty ordered set before implementation or
  task generation may proceed; a plain `SELECT ... FOR UPDATE` returning no
  rows is not sufficient evidence by itself.
- The suggested folders and class names in the reference are planning guidance;
  planning may select the narrowest conventional Laravel structure that
  preserves all observable contracts and transaction/file guarantees.
