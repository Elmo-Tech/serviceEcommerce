# Implementation Plan: Hero Slider Management

**Branch**: `[008-hero-slider-management]` | **Date**: 2026-08-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/008-hero-slider-management/spec.md`

## Summary

Implement one conventional Laravel Hero Slide resource with five protected
Admin CRUD endpoints and one public localized index. Three transactional
Actions will coordinate image storage and a lock-protected ordered set;
focused ordering and image Services will own reusable integrity rules. The
database will enforce unique positions while a two-phase temporary-position
rewrite prevents unique-key collisions during insertion, movement, and
compaction. Public output will contain only active localized content, and all
contracts, permissions, concurrency behavior, file compensation, and Postman
examples will be verified against MySQL.

## Technical Context

**Language/Version**: PHP 8.3.29 currently active and Composer lock records
Laravel 13.23.0 locally, while `composer.json` declares PHP `^8.2` and Laravel
`^12.0`; this pre-existing drift must be reconciled outside Feature 008 before
final dependency verification

**Primary Dependencies**: Declared target Laravel `^12.0`, Sanctum `^4.3`,
`spatie/laravel-permission ^7.4`, and Query Builder `^6.3`; current lock/vendor
contain Laravel 13.23.0, Sanctum 4.3.3, Permission 8.3.0, and Query Builder
7.3.0. Design uses APIs common to both declared and installed ranges.

**Storage**: MySQL/InnoDB; one `hero_slides` table; Laravel Filesystem on the
configured public disk under a backend-controlled `hero-slides/` directory

**Testing**: Pest 4.7.5 with the dedicated MySQL test database; Laravel storage
fakes for ordinary file cases; real MySQL process-level tests for ordering and
limit races; Laravel Pint and Larastan 3.10/PHPStan quality gates

**Target Platform**: Existing Hostinger-compatible PHP/MySQL production
environment; no queue, scheduler, Cron, Redis, or additional process dependency

**Project Type**: Backend-only, API-first conventional Laravel monolith

**Performance Goals**: Admin and public reads use a fixed number of queries and
never perform per-slide reads; every mutation locks and rewrites at most 10
rows; public output returns at most 10 items; upload processing is bounded to
one static image no larger than exactly 5 MiB (`5,242,880` bytes)

**Constraints**: Exact `/api/v1/admin/hero-slides` and
`/api/v1/public/hero-slides` contracts; Arabic/English localization; four exact
permissions; maximum 10 active-plus-inactive rows; positions always empty or
1..N; unique index-safe shifts; secure public image storage; no reorder route,
button, frontend behavior, soft delete, cache, or queue

**Scale/Scope**: One global ordered set with 0..10 records; one required image
per record; Admin pagination remains default 15/max 100 for contract stability;
public index is deliberately unpaginated because its hard maximum is 10

**Pre-existing dependency prerequisite**: `composer validate --no-check-publish`
currently fails because the lock still contains Laravel 13, Permission 8,
Query Builder 7, and Pest 4 packages while the manifest requests Laravel 12,
Permission 7, Query Builder 6, and Pest 3 families. Feature 008 adds no package
and MUST NOT run an unscoped dependency update. **Feature design gate: PASS. Repository dependency gate: EXTERNAL BLOCKER.**
This drift does not change the feature design, but final implementation
acceptance remains blocked until the repository owner reconciles the intended
dependency baseline and clean dependency verification passes.

## Constitution Check

*GATE: PASS before Phase 0 and PASS after Phase 1 design.*

- **Authority, precedence, and traceability — PASS**: Every planned route,
  field, invariant, and exclusion traces to the approved Feature 008 reference
  and synchronized specification.
- **Conflict and exception gate — PASS**: The status-enum name was corrected in
  the governing feature reference. Generic earlier `hero-sections`/reorder
  examples are recommendations; the approved exact `hero-slides` contract
  specializes them without weakening a mandatory rule.
- **Repository boundary — PASS**: Work is Laravel API, database, storage,
  tests, OpenAPI, and Postman only; slider rendering remains external.
- **Architecture — PASS**: Thin Admin/Public Controllers use dedicated Form
  Requests and Resources. Three Actions are justified by transactions and file
  compensation; focused Services own ordering, storage, and strict multipart
  parsing. A narrowly scoped middleware is required so real PHP 8.2/8.3 HTTP
  requests can satisfy the approved multipart PATCH contract. The bounded
  Admin read uses one focused Query class.
- **API contract — PASS**: Routes are versioned, keys are camelCase, errors use
  shared envelopes/codes and `HttpStatusCode`, Admin pagination is bounded, and
  query input is explicitly allow-listed.
- **Authentication and authorization — PASS**: Existing Sanctum/type/active
  middleware precedes exact Spatie permissions; public read is intentionally
  unauthenticated; no role bypass or reorder permission is planned.
- **Trust and content safety — PASS**: Exact request fields, scalar validation,
  trimmed text, detected image content, generated filenames, safe URLs, and
  non-disclosure of raw paths are explicit.
- **Database integrity — PASS**: MySQL constraints, one table, transactions, a
  feature-scoped database advisory mutex plus ordered row locks,
  temporary-position rewrites, unique position, and hard deletion preserve all
  approved invariants, including when the table is empty.
- **Files — PASS**: One static validated JPEG/PNG/WebP image of at most exactly
  5 MiB, configured Laravel disk, MIME/content-derived extension, animated
  WebP/APNG rejection, rollback cleanup, post-commit old-file deletion, and
  safe cleanup logging are designed.
- **Localization — PASS**: Admin resources return both languages; public
  resources project one resolved language; messages and required locale headers
  are bilingual while machine identifiers remain stable English.
- **Testing and quality — PASS**: Feature, contract, architecture, file, Seeder,
  and dedicated MySQL concurrency coverage plus regression, Pint, and static
  analysis are planned.
- **Scope and operations — PASS**: No package, worker, Cron, Redis, cache,
  frontend, or speculative capability is introduced.

## Project Structure

### Documentation (this feature)

```text
specs/008-hero-slider-management/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── openapi.yaml
├── checklists/
│   └── requirements.md
└── tasks.md                       # generated later by speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Actions/HeroSlides/
│   ├── CreateHeroSlideAction.php
│   ├── UpdateHeroSlideAction.php
│   └── DeleteHeroSlideAction.php
├── Http/
│   ├── Controllers/Api/V1/Admin/HeroSlides/HeroSlideController.php
│   ├── Controllers/Api/V1/Public/HeroSlides/HeroSlideController.php
│   ├── Middleware/ParseHeroSlideMultipartPatch.php
│   ├── Requests/Api/V1/Admin/HeroSlides/
│   │   ├── ListHeroSlidesRequest.php
│   │   ├── StoreHeroSlideRequest.php
│   │   └── UpdateHeroSlideRequest.php
│   └── Resources/Api/V1/
│       ├── Admin/HeroSlides/AdminHeroSlideResource.php
│       └── Public/HeroSlides/PublicHeroSlideResource.php
├── Models/HeroSlide.php
├── Queries/HeroSlides/AdminHeroSlideIndexQuery.php
└── Services/HeroSlides/
    ├── HeroSlideImageService.php
    ├── HeroSlideOrderingService.php
    └── HeroSlideQueryShapeGuard.php

app/Services/Http/
└── StrictMultipartPatchParser.php

config/
└── filesystems.php                 # feature disk selector only if needed

database/
├── factories/HeroSlideFactory.php
├── migrations/*_create_hero_slides_table.php
└── seeders/RolesAndPermissionsSeeder.php

lang/{ar,en}/
├── hero_slides.php
└── validation.php                  # only when shared messages are insufficient

routes/api/v1/
├── admin.php
└── public.php

postman/
└── Service-Commerce.postman_collection.json

tests/
├── Architecture/HeroSlidesFeatureArchitectureTest.php
├── Architecture/HeroSlidesOpenApiAndPostmanContractTest.php
├── Concurrency/HeroSlides/HeroSlideCriticalConcurrencyTest.php
├── Unit/Http/StrictMultipartPatchParserTest.php
├── Unit/HeroSlides/HeroSlideOrderingServiceTest.php
├── Feature/Http/HeroSlideMultipartPatchTransportTest.php
└── Feature/Api/V1/
    ├── Admin/HeroSlides/
    │   ├── HeroSlideAuthorizationTest.php
    │   ├── HeroSlideCreateTest.php
    │   ├── HeroSlideIndexAndShowTest.php
    │   ├── HeroSlideUpdateTest.php
    │   └── HeroSlideDeleteTest.php
    └── Public/HeroSlides/PublicHeroSlideIndexTest.php
```

**Structure Decision**: Use the repository's conventional feature folders,
not a module or repository layer. Controllers remain transport adapters.
Actions exist only for the three multi-step mutations. Ordering and image
Services are independently testable capabilities. The single Admin list Query
centralizes the exact allow-listed filter and fixed sorting. No Policy is
needed because operation permissions are route-level and the resource has no
ownership dimension.

## Phase 0 Research Decisions

Detailed rationale and rejected alternatives are recorded in
[research.md](research.md). The binding decisions are:

1. Use one model/table and no Hero settings or media table.
2. Use three transactional mutation Actions, one ordering Service, one image
   Service, a focused Admin Query, two Resources, and thin Controllers.
3. Acquire one fixed MySQL/MariaDB advisory mutex for Hero ordering, then lock
   all current rows by position; this serializes even an empty ordered set.
4. Rebuild affected order through temporary positions above the valid range,
   then assign final 1..N positions, avoiding transient unique-index clashes.
5. Store the new file before the transaction, clean it on failure, and delete
   superseded/deleted files only after successful commit.
6. Derive stored extension from detected allowed MIME/content rather than the
   client filename.
7. Validate only exact multipart lexical values `"0"` and `"1"` for
   `isActive`, then normalize after validation; reject integer-only test
   shortcuts and every wider boolean spelling.
8. Keep public output as a localized active-only projection with required
   locale headers; keep Admin output bilingual.
9. Use the existing permission Seeder, route middleware, OpenAPI, Postman, and
   architecture-contract patterns without adding packages.
10. Treat the Composer manifest/lock discrepancy as pre-existing repository
    state: plan against installed lock versions, do not alter dependencies in
    Feature 008, and require dependency consistency to be checked before final
    implementation verification.
11. Parse multipart PATCH through one bounded Hero-route middleware on PHP
    8.2/8.3; preserve duplicate-field visibility and do not silently change the
    approved wire method to POST method override.

## Phase 1 Design

### 1. Persistence and indexes

Create `hero_slides` with the fields and invariants in
[data-model.md](data-model.md). `position` is an unsigned tiny integer with a
unique index; `(is_active, position)` supports the public/filter read. Hard
delete is intentional. Image disk is configuration, while only the relative
path is persisted as approved by the reference.

### 2. Ordered mutation algorithm

For every mutation attempt:

1. Validate and, when supplied, store the new image before database work.
2. Select one database connection and acquire the fixed Hero advisory mutex on
   that connection before opening the transaction.
3. Begin a transaction on the same connection, then execute `FOR UPDATE` over
   all current rows ordered by position and ID.
4. Revalidate row count, route-bound target, and requested position against the
   locked current state.
5. Build the desired ordered list of existing IDs plus the new row, or minus
   the deleted row.
6. Move existing rows to the reserved temporary band 100..110.
7. Insert/update/delete the target and assign final positions 1..N.
8. Commit or roll back.
9. Release the advisory mutex in `finally` on the same connection.
10. After a successful commit, delete the superseded/deleted old image.
11. After the final failed retryable attempt or any non-retryable pre-commit
    failure, delete only the newly stored file.

The advisory-lock name MUST be deterministic, application/database scoped, and
within the database engine's identifier limit, for example
`service-commerce:<database>:hero-slides`.

The planning constant for the temporary band is 100..110. It cannot overlap
the valid range, stays within 0..255, and is never externally observable. The
ordering Service will assert the locked input is already the expected 1..N
sequence before mutation, failing safely if existing data is corrupt.

Ordered-set writes use the repository-approved bounded deadlock retry policy.
Only retryable MySQL deadlock/serialization errors may be retried. Each retry
releases and reacquires the advisory mutex and restarts the full transaction;
validation errors, missing targets, the 10-slide limit, upload validation
failures, and non-retryable SQL errors are never retried. Exhausted retries
return a safe project-standard server outcome without database details and
without leaking a newly stored file.

### 3. Request and read design

- Store and Update Requests trim submitted text before rules run, treat it as
  plain text, reject unapproved control characters, validate only exact raw
  multipart `isActive` values `"0"`/`"1"`, and validate `position` as a
  canonical positive decimal string with no sign, leading zero, decimal point,
  surrounding whitespace, array shape, or duplicate field.
- Update uses presence-aware mapping so omitted keys remain unchanged; null and
  empty-after-trim required text are rejected. An update must contain at least
  one approved mutable field; empty PATCH and unknown-only PATCH requests return
  the shared localized `422 VALIDATION_ERROR` outcome.
- List Request rejects unknown top-level/filter keys and invalid scalar shapes,
  validates page/perPage, and preserves `filter[isActive]=0` as present. A
  focused `HeroSlideQueryShapeGuard` inspects the original query string before
  normalized validation so repeated `page`, `perPage`, and
  `filter[isActive]` members cannot be silently collapsed by PHP.
- Admin Query uses `AllowedFilter::exact('isActive', 'is_active')`, fixed
  `orderBy('position')`, and `paginate(perPage, page)` with 15/100 rules.
- Public query is direct, bounded, active-only, ascending, and unpaginated.
- Resource URL generation uses `Storage::disk(configuredDisk)->url(path)` and
  converts to the repository-approved absolute URL form.
- PHP below 8.4 does not natively populate Symfony form/file parameters for a
  multipart PATCH. Apply `ParseHeroSlideMultipartPatch` only to the Hero PATCH
  route before Form Request validation. Its parser limits the raw body to one
  5 MiB file plus a small fixed multipart overhead, accepts one file and only
  approved scalar fields, preserves duplicate-member detection, creates one
  managed temporary upload, and deletes any remaining temporary file in
  `finally`. It must reject empty PATCH, duplicate scalar/file parts, unknown
  parts, malformed or missing boundaries, header injection, oversized raw
  bodies, exact-file-size overflow, and unsupported parts before Action
  execution.
- Authentication, administrator type, active-state, and update-permission
  middleware MUST run before this feature parser; parsing still runs before
  Form Request resolution and endpoint execution.
- Do not use `_method=PATCH` or silently change the wire method. A real
  HTTP-server Postman/curl smoke test is mandatory because Laravel's in-process
  request builder alone cannot prove PHP SAPI multipart PATCH behavior.

### 4. Permissions and routing

Extend the existing permission catalog/Seeder idempotently with exactly:

```text
hero-slides.view
hero-slides.create
hero-slides.update
hero-slides.delete
```

Register Admin routes inside the existing authenticated/type/active group and
attach the operation permission at route level. Register the public GET in the
existing public locale boundary. Explicit numeric route constraints prevent
ambiguous binding. No reorder route, permission, or handler is created.

### 5. Files and failures

`HeroSlideImageService` will validate/store/delete and generate URLs through
Laravel Filesystem. Validation combines extension allow-list, detected MIME,
decodable image verification, upload success, static-image detection, and an
exact maximum of 5 MiB (`5,242,880` bytes). A file exactly at the boundary is
accepted; one byte larger is rejected. Animated WebP, APNG, SVG, and other
formats are rejected. Stored names use UUID/random values and an extension
selected from the detected MIME map.
Cleanup deletion is best-effort only after commit: failure is logged with safe
context (slide identity/disk category, not sensitive path details) and does not
turn a committed valid response into a database rollback.

The 10-row business limit uses status 422 with stable code
`VALIDATION_ERROR`, as required by the feature's explicit maximum-count rule.
`HERO_SLIDE_NOT_FOUND` identifies missing route resources and
`HERO_SLIDE_IMAGE_UPLOAD_FAILED` identifies safe storage failures. The optional
reference mention of `HERO_SLIDE_LIMIT_REACHED` does not override the explicit
limit outcome and is not emitted by the initial contract.

### 6. Contracts and documentation

[contracts/openapi.yaml](contracts/openapi.yaml) is the exact OpenAPI 3.1
contract. Update the existing Postman collection under an Admin Hero Slides
folder plus the public request, documenting every key and activity meaning.
Use only the existing `baseUrl`, `accessToken`, and `refreshToken` variables; Hero
examples introduce no new collection/environment variable.

### 7. Verification strategy

- Storage-fake Feature tests prove normal upload, replacement, rollback
  cleanup, post-commit deletion, URL/path safety, and validation.
- Architecture tests prove exact routes, middleware/permissions, no reorder,
  model/migration contracts, and `HttpStatusCode` usage.
- Contract tests compare routes and Postman requests against OpenAPI.
- Dedicated process-level MySQL tests synchronize competing requests and prove
  final count ≤10 and sequence exactly 1..N after create/create, move/move, and
  create/delete races.
- A real HTTP integration smoke test on PHP 8.2/8.3 submits PATCH multipart
  text plus image and proves bounded parsing, duplicate detection, request
  validation, and temporary-file cleanup outside test-request simulation.
- Dedicated parser tests cover valid text-only PATCH, valid replacement,
  empty PATCH, duplicate scalar/file parts, unknown parts, malformed and
  missing boundaries, header injection, exact 5 MiB, 5 MiB plus one byte,
  temporary-file cleanup, downstream exceptions, and proof that unauthorized
  requests are rejected before body parsing.
- Concurrency tests cover retryable deadlock retries and exhausted-retry safe
  failure in addition to create/create, move/move, and create/delete races.
- Run targeted Feature 008 tests first, then complete regression, Pint, and
  configured static analysis.

## Post-Design Constitution Check

**Feature design gate: PASS. Repository dependency gate: EXTERNAL BLOCKER.**
Phase 1 introduces no feature-level exception. The design remains bounded to 10
rows and one file per row, uses the configured public filesystem, preserves
shared API/localization/permission conventions, and provides database-level
and concurrency verification for every critical invariant.

## Complexity Tracking

No constitutional exception or unapproved complexity is required.
