# Tasks: Hero Slider Management

**Input**: Design documents from `/specs/008-hero-slider-management/`

**Approved source**: `docs/features/008-hero-slider-management.md`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`,
`contracts/openapi.yaml`, and `quickstart.md`

**Tests**: Tests are mandatory implementation work. Feature 008 coverage must
include exact routes and request shapes, authentication, each permission,
localization, file validation and compensation, ordered-set integrity,
maximum-count races, real HTTP multipart PATCH behavior, OpenAPI/Postman
contracts, MySQL constraints, and critical concurrency.

**Organization**: Tasks are grouped by the four approved user stories. Shared
schema, model, permissions, image storage, ordered-set serialization, query
shape, and Resource foundations are completed first. Each story is independently
testable with factories and direct persistence, even when another story's
endpoint has not yet been implemented.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: May run in parallel only after its declared prerequisites are
  complete and when changed files do not overlap.
- **[Story]**: User story traceability label.
- Every task names its primary file path.
- A test task is complete only when the corresponding behavior passes.
- Application-controlled statuses use `App\Enums\HttpStatusCode`.
- No task may introduce a reorder route/permission, button, slider setting,
  frontend code, soft delete, cache, queue, video, scheduling, or analytics.

## Path Conventions

- Laravel code: `app/`
- Versioned routes: `routes/api/v1/`
- Persistence: `database/migrations/`, `database/factories/`, and
  `database/seeders/`
- Feature tests: `tests/Feature/Api/V1/`
- Database tests: `tests/Feature/Database/`
- Concurrency tests: `tests/Concurrency/`
- Architecture/contract tests: `tests/Architecture/`
- Feature artifacts: `specs/008-hero-slider-management/`

---

## Phase 1: Setup — Repository and Runtime Verification

**Purpose**: Confirm exact extension points and record pre-existing runtime
risks before implementation changes.

- [X] T001 Audit existing Admin/Public route groups, middleware order, API envelopes, Resources, Form Requests, Action/Service patterns, permission Seeders, and locale headers in `routes/api/v1/admin.php`, `routes/api/v1/public.php`, `app/Support/Api/`, `app/Http/Requests/`, `app/Http/Resources/`, `app/Actions/`, `app/Services/`, and `database/seeders/`
- [X] T002 Execute `php -v`, `composer validate --no-check-publish`, and `composer check-platform-reqs`; record the declared Laravel 12/PHP 8.2 versus current Laravel 13/PHP 8.3 lock/vendor drift without running Composer update in `specs/008-hero-slider-management/checklists/runtime-verification.md`
- [X] T003 [P] Verify the dedicated MySQL test database, engine/version, advisory-lock support, process concurrency helpers, storage fakes, and logging spies in `phpunit.xml`, `.env.example`, `tests/Pest.php`, `tests/Support/`, and existing `tests/Concurrency/`
- [X] T004 [P] Confirm from installed Symfony source and a minimal real server probe that PHP 8.2/8.3 does not natively parse direct multipart PATCH files; record parser and body-limit evidence in `specs/008-hero-slider-management/checklists/runtime-verification.md`
- [X] T005 [P] Inspect existing OpenAPI 3.1 and Postman conventions and confirm that the collection has only the approved `baseUrl`, `accessToken`, and `refreshToken` variables in `specs/006-settings-management/contracts/openapi.yaml` and `postman/Service-Commerce.postman_collection.json`

**Checkpoint**: Runtime drift, MySQL capabilities, multipart PATCH limitation,
and repository conventions are documented without changing dependencies.

---

## Phase 2: Foundational — Blocking Shared Infrastructure

**Purpose**: Establish the one-table model, permissions, secure image storage,
global ordering mutex, bounded mutation retry policy, and shared contract components required by every story.

**⚠️ CRITICAL**: No user-story route is registered until this phase passes.

- [X] T006 Create the reversible `hero_slides` migration with required bilingual fields, `image_path`, `is_active`, unique `position`, and composite `(is_active, position)` index in `database/migrations/*_create_hero_slides_table.php`
- [X] T007 [P] Implement guarded attributes, boolean casting, active/ordered scopes, and no soft-delete behavior in `app/Models/HeroSlide.php`
- [X] T008 [P] Add deterministic valid active/inactive slide states and safe one-based positions in `database/factories/HeroSlideFactory.php`
- [X] T009 [P] Implement the exact eight-key bilingual Admin projection and configured-disk absolute image URL in `app/Http/Resources/Api/V1/Admin/HeroSlides/AdminHeroSlideResource.php`
- [X] T010 [P] Add Arabic and English success, validation, not-found, limit, upload-failure, and cleanup-safe messages in `lang/ar/hero_slides.php` and `lang/en/hero_slides.php`
- [X] T011 [P] Add exactly `hero-slides.view`, `hero-slides.create`, `hero-slides.update`, and `hero-slides.delete` through an idempotent feature Seeder and normal super-admin synchronization in `database/seeders/HeroSlidesPermissionsSeeder.php` and `database/seeders/RolesAndPermissionsSeeder.php`
- [X] T012 Implement one-file static JPG/JPEG/PNG/WebP validation, exact
  5 MiB (`5,242,880` bytes) limit, upload-success checks, detected MIME and
  decodable-content verification, animated WebP/APNG/SVG rejection,
  backend-generated MIME-derived filenames, configured public-disk storage,
  absolute URL generation, delete-result inspection, and safe cleanup logging
  in `app/Services/HeroSlides/HeroSlideImageService.php`

- [X] T013 Implement the fixed MySQL/MariaDB advisory mutex,
  deterministic application/database-scoped lock name, same-connection
  acquisition and `finally` release, ordered `FOR UPDATE` read,
  count/sequence assertions, temporary band 100..110, and final 1..N rewrite
  primitives in `app/Services/HeroSlides/HeroSlideOrderingService.php`
- [X] T014 Implement the repository-approved bounded mutation retrier for only
  retryable MySQL deadlock/serialization failures. Each retry MUST rerun the
  complete mutation attempt so the Action reacquires the advisory mutex and
  starts a fresh transaction; validation, not-found, limit, storage, and
  non-retryable SQL failures MUST NOT retry. Exhausted retries MUST return a
  safe server outcome and preserve new-file compensation in
  `app/Services/HeroSlides/HeroSlideMutationRetrier.php`

- [X] T015 [P] Implement original-query-string guarding for unknown, repeated, array-shaped, empty, and non-canonical `page`, `perPage`, and `filter[isActive]` input in `app/Services/HeroSlides/HeroSlideQueryShapeGuard.php`
- [X] T016 [P] Add real MySQL schema coverage for required columns, types, primary key, unique position, composite active-position index, and absence of soft deletes/additional Hero tables in `tests/Feature/Database/HeroSlides/HeroSlideSchemaTest.php`
- [X] T017 [P] Add storage-fake and focused image-content coverage for
  static JPG/JPEG/PNG/WebP acceptance, static WebP acceptance, animated
  WebP/APNG/SVG rejection, detected MIME mismatch, unsafe filenames,
  generated MIME-derived extension, exactly 5 MiB acceptance, 5 MiB plus one
  byte rejection, failed writes/deletes, absolute URLs, and raw-path
  non-disclosure in `tests/Unit/HeroSlides/HeroSlideImageServiceTest.php`

- [X] T018 Add real MySQL ordered-set service coverage after T013 for
  empty state, temporary unique-index-safe rewrites, corrupt-sequence
  rejection, deterministic advisory-lock naming, advisory-lock timeout,
  same-connection release, and final 1..N state in
  `tests/Feature/Database/HeroSlides/HeroSlideOrderingServiceTest.php`

- [X] T019 [P] Add the frozen OpenAPI 3.1 structural test for six operations, exact fields, pagination/filter input, multipart schemas, public headers, stable error outcomes, and absence of reorder in `tests/Architecture/HeroSlidesOpenApiContractTest.php`

**Checkpoint**: Shared persistence, projection, permissions, image, ordering,
retry, query-shape, and frozen-contract foundations are ready.

---

## Phase 3: User Story 1 — Publish an Ordered Homepage Slide (Priority: P1) 🎯 MVP

**Goal**: Create one complete bilingual slide with one safe image, optional
insertion position, exact active state, maximum-count enforcement, and atomic
ordering/file behavior.

**Independent Test**: Use an authorized create request against empty and
populated sets; verify append, insertion shifts, response, image, validation,
10-row limit, rollback cleanup, and concurrent final-slot protection.

### Tests for User Story 1 — Mandatory

- [X] T020 [P] [US1] Add create API coverage for required/trimmed
  bilingual plain-text fields, control-character rejection, exact length
  boundaries, exact multipart lexical `isActive="0"`/`"1"` including valid
  zero, optional canonical position, 201 response, exact Admin resource, and
  unknown-field rejection in
  `tests/Feature/Api/V1/Admin/HeroSlides/HeroSlideCreateTest.php`

- [X] T021 [P] [US1] Add image-create coverage for valid static
  JPG/JPEG/PNG/WebP, static WebP, missing image, SVG, animated WebP, APNG,
  exactly 5 MiB, 5 MiB plus one byte, MIME/content mismatch, safe generated
  storage, absolute URL, raw-path absence, failed write, and no leaked file on
  validation/database failure in
  `tests/Feature/Api/V1/Admin/HeroSlides/HeroSlideCreateImageTest.php`

- [X] T022 [P] [US1] Add create-ordering and limit coverage for append, positions 1 and count+1, middle insertion, zero/negative/out-of-range and non-canonical positions, automatic shifts, inactive rows counting toward 10, deletion-freed capacity setup, and 422 `VALIDATION_ERROR` in `tests/Feature/Api/V1/Admin/HeroSlides/HeroSlideCreateOrderingTest.php`

### Implementation for User Story 1

- [X] T023 [US1] Implement trimming, plain-text/control-character
  validation, exact request keys, required bilingual text/image/activity,
  exact raw multipart `isActive="0"`/`"1"`, canonical position lexical
  validation before normalization, static-image/size validation, and
  presence-safe validated data in
  `app/Http/Requests/Api/V1/Admin/HeroSlides/StoreHeroSlideRequest.php`

- [X] T024 [US1] Implement store-new-image and execute the complete
  create mutation through `HeroSlideMutationRetrier`: acquire the advisory
  mutex, start a fresh transaction, perform the locked maximum/count check,
  append/insertion rewrite, and row creation on every attempt; return 422
  `VALIDATION_ERROR` for the limit and delete the new file after the final
  failed attempt or any non-retryable pre-commit failure in
  `app/Actions/HeroSlides/CreateHeroSlideAction.php`

- [X] T025 [US1] Implement the thin Admin `store` endpoint with the shared success envelope and `HttpStatusCode::CREATED` in `app/Http/Controllers/Api/V1/Admin/HeroSlides/HeroSlideController.php`
- [X] T026 [US1] Register only `POST /api/v1/admin/hero-slides` with numeric conventions and exact `hero-slides.create` middleware after the established Admin boundary in `routes/api/v1/admin.php`
- [X] T027 [US1] Add process-runner create modes for empty-set and final-slot races in `tests/Support/HeroSlideConcurrencyRunner.php`
- [X] T028 [US1] Add dedicated MySQL concurrency coverage proving
  concurrent empty-set/final-slot creates never exceed 10, never
  duplicate/gap positions, release locks, clean losing-request files,
  successfully retry an injected retryable deadlock/serialization failure,
  and return a safe leak-free outcome after retry exhaustion in
  `tests/Concurrency/HeroSlides/HeroSlideCriticalConcurrencyTest.php`

- [X] T029 [US1] Run the complete US1 create, image, ordering, schema, permission-create, and create-concurrency tests and record the checkpoint in `specs/008-hero-slider-management/checklists/implementation-progress.md`

**Checkpoint**: User Story 1 independently creates safe ordered slides and is
the suggested MVP slice.

---

## Phase 4: User Story 2 — Manage Existing Slides Safely (Priority: P1)

**Goal**: List/show all slides, filter/paginate them, partially update content,
activity, image, and position, and permanently delete with compaction and file
compensation.

**Independent Test**: Seed slides directly with the factory, then exercise the
five management behaviors without relying on the create endpoint.

### Tests for User Story 2 — Mandatory

- [X] T030 [P] [US2] Add Admin index/show coverage for default/max pagination, fixed ascending order, exact raw-query filter shape including lexical zero, active/inactive results, both languages, exact fields, missing 404, absolute URL, and no raw path in `tests/Feature/Api/V1/Admin/HeroSlides/HeroSlideIndexAndShowTest.php`
- [X] T031 [P] [US2] Add PATCH coverage for presence-aware
  bilingual/activity updates, omitted preservation, empty PATCH and
  unknown-only PATCH rejection with localized 422, rejected null/empty text,
  exact lexical scalar values including valid `"0"`, position
  omission/current no-op/upward/downward moves, affected shifts,
  non-canonical/invalid positions, and missing slide in
  `tests/Feature/Api/V1/Admin/HeroSlides/HeroSlideUpdateTest.php`

- [X] T032 [P] [US2] Add image-replacement coverage for static
  JPEG/PNG/WebP acceptance, animated WebP/APNG/SVG rejection, exact 5 MiB and
  plus-one-byte boundaries, omission preservation, successful new-path commit
  then old-file deletion, no remove operation, failed database mutation
  cleanup, failed post-commit cleanup logging, and valid committed
  response/state in
  `tests/Feature/Api/V1/Admin/HeroSlides/HeroSlideUpdateImageTest.php`

- [X] T033 [P] [US2] Add permanent-delete coverage for 200/null data, row removal, following-position compaction, only-slide empty state, missing 404, post-commit file deletion, and logged cleanup failure in `tests/Feature/Api/V1/Admin/HeroSlides/HeroSlideDeleteTest.php`

### Implementation for User Story 2

- [X] T034 [P] [US2] Implement exact Admin list query validation, raw shape guard invocation, default page 1/perPage 15, max 100, and exact optional active filter in `app/Http/Requests/Api/V1/Admin/HeroSlides/ListHeroSlidesRequest.php`
- [X] T035 [P] [US2] Implement fixed position-ascending pagination with only `AllowedFilter::exact('isActive', 'is_active')` and no search/sort/include in `app/Queries/HeroSlides/AdminHeroSlideIndexQuery.php`
- [X] T036 [US2] Implement a bounded strict raw multipart parser for
  PHP 8.2/8.3 that validates boundary/header structure, detects header
  injection and missing closing boundaries, preserves duplicate scalar and
  duplicate image parts, permits only approved scalar parts and one image,
  creates one managed temporary upload, enforces one exact 5 MiB file plus
  fixed overhead, and rejects empty/malformed/oversized bodies in
  `app/Services/Http/StrictMultipartPatchParser.php`

- [X] T037 [US2] Implement route-scoped multipart PATCH middleware
  after Admin update permission and before Form Request resolution, merge
  parsed fields and file, avoid parsing unauthorized requests, and clean any
  remaining temporary file in `finally` including downstream exceptions in
  `app/Http/Middleware/ParseHeroSlideMultipartPatch.php`

- [X] T038 [P] [US2] Add strict parser unit coverage for valid
  text-only/image multipart input, empty body, duplicate scalar and image
  parts, unknown parts, malformed boundary, missing closing boundary, header
  injection, binary boundary-like content, exact 5 MiB, 5 MiB plus one byte,
  fixed raw-body overhead, temporary-file cleanup, and parser exceptions in
  `tests/Unit/Http/StrictMultipartPatchParserTest.php`
- [X] T039 [P] [US2] Add HTTP-kernel transport and middleware coverage for
  direct multipart PATCH field/file delivery, middleware order, unauthorized
  rejection before parser execution, parsed field/file merging, empty and
  unknown-only PATCH rejection, downstream exception cleanup, and no remaining
  temporary upload in
  `tests/Feature/Http/HeroSlideMultipartPatchTransportTest.php`

- [X] T040 [US2] Implement exact optional PATCH fields, require at
  least one approved mutable field, return localized 422 for empty and
  unknown-only PATCH, trim and validate plain text, enforce exact lexical
  activity/canonical position rules, preserve persisted completeness, validate
  static image input, and reject unknown/remove-image fields in
  `app/Http/Requests/Api/V1/Admin/HeroSlides/UpdateHeroSlideRequest.php`

- [X] T041 [US2] Implement locked target re-resolution and execute the
  complete update through `HeroSlideMutationRetrier`: reacquire mutex/start a
  fresh transaction per retry, apply presence-aware updates,
  unique-index-safe optional movement, and image-path commit; delete the new
  file after final failed/non-retryable pre-commit failure and delete the old
  image only after successful commit with safe cleanup logging in
  `app/Actions/HeroSlides/UpdateHeroSlideAction.php`

- [X] T042 [US2] Implement locked target re-resolution and execute the
  complete delete through `HeroSlideMutationRetrier`: reacquire mutex/start a
  fresh transaction per retry, permanently remove the row, perform
  unique-index-safe compaction, and commit; delete the old image only after
  commit and log cleanup failure safely in
  `app/Actions/HeroSlides/DeleteHeroSlideAction.php`

- [X] T043 [US2] Implement Admin `index`, `show`, `update`, and `destroy` methods, exact pagination meta, shared envelopes, not-found handling, and `HttpStatusCode::OK` in `app/Http/Controllers/Api/V1/Admin/HeroSlides/HeroSlideController.php`
- [X] T044 [US2] Register Admin GET collection/show, PATCH, and DELETE routes with numeric `{heroSlide}`, exact view/update/delete permissions, and the PATCH parser in the approved middleware order in `routes/api/v1/admin.php`
- [X] T045 [US2] Extend the process runner with upward/downward move and create/delete modes in `tests/Support/HeroSlideConcurrencyRunner.php`
- [X] T046 [US2] Extend real MySQL concurrency coverage for competing
  moves, create/delete races, final 1..N ordering, count limit, committed
  file-path state, no leaked replacement file, successful retry of an injected
  retryable deadlock/serialization failure, and safe state/file compensation
  after retry exhaustion in
  `tests/Concurrency/HeroSlides/HeroSlideCriticalConcurrencyTest.php`

- [X] T047 [US2] Run the complete Admin management, parser, database-ordering, file-compensation, and concurrency tests and record the checkpoint in `specs/008-hero-slider-management/checklists/implementation-progress.md`

**Checkpoint**: User Story 2 independently manages factory-seeded slides while
preserving all database and filesystem invariants.

---

## Phase 5: User Story 3 — Display Active Localized Slides Publicly (Priority: P1)

**Goal**: Return all active slides in display order through one unauthenticated,
localized, minimal public projection.

**Independent Test**: Seed mixed active/inactive bilingual slides directly and
request Arabic/English variants plus the empty state.

### Tests for User Story 3 — Mandatory

- [X] T048 [P] [US3] Add public API coverage for unauthenticated access, active-only selection, ascending array order, unpaginated maximum-10 result, Arabic/English and locale-variant projection, exact three keys, absolute URL, required headers, empty array, and Admin/raw-path field absence in `tests/Feature/Api/V1/Public/HeroSlides/PublicHeroSlideIndexTest.php`

### Implementation for User Story 3

- [X] T049 [P] [US3] Implement exact localized `title`, `description`, and absolute `image` projection with no ID/activity/position/dual-language/raw path in `app/Http/Resources/Api/V1/Public/HeroSlides/PublicHeroSlideResource.php`
- [X] T050 [US3] Implement the thin bounded active-only, position-ascending, unpaginated public index and shared localized success envelope in `app/Http/Controllers/Api/V1/Public/HeroSlides/HeroSlideController.php`
- [X] T051 [US3] Register only `GET /api/v1/public/hero-slides` inside the existing public locale boundary with no Admin authentication/permission in `routes/api/v1/public.php`
- [X] T052 [US3] Run the public Hero suite in Arabic and English and record the checkpoint in `specs/008-hero-slider-management/checklists/implementation-progress.md`

**Checkpoint**: User Story 3 independently serves the safe localized homepage
projection.

---

## Phase 6: User Story 4 — Enforce Independent Administration Permissions (Priority: P1)

**Goal**: Prove each Admin operation requires only its exact permission after
the established Admin boundary, with no hidden bypass or reorder permission.

**Independent Test**: Seed a permission-less active administrator, then grant
one permission at a time and exercise every endpoint while public read remains
open.

### Tests for User Story 4 — Mandatory

- [X] T053 [P] [US4] Add Seeder coverage for idempotent exact permission creation, configured guard, permission-cache reset, super-admin assignment, unrelated-permission preservation, and absence of wildcard/reorder in `tests/Feature/Database/Permissions/HeroSlidesPermissionsSeederTest.php`
- [X] T054 [P] [US4] Add route architecture coverage for all six operations, exact methods/paths, numeric binding, Admin middleware order, exact per-route permissions, public openness, and no reorder route in `tests/Architecture/HeroSlidesFeatureArchitectureTest.php`
- [X] T055 [P] [US4] Add API authorization coverage for 401, non-admin/inactive rejection, each missing 403, each exact-permission success, and proof that permission does not bypass validation/order/file rules in `tests/Feature/Api/V1/Admin/HeroSlides/HeroSlideAuthorizationTest.php`

### Implementation for User Story 4

- [X] T056 [US4] Audit and correct the final route-to-permission mapping and Seeder integration without introducing a super-admin bypass or unrelated grant in `routes/api/v1/admin.php`, `database/seeders/HeroSlidesPermissionsSeeder.php`, and `database/seeders/RolesAndPermissionsSeeder.php`
- [X] T057 [US4] Run permission Seeder, route architecture, Admin authorization, and public-access tests and record the checkpoint in `specs/008-hero-slider-management/checklists/implementation-progress.md`

**Checkpoint**: All four permissions are isolated and backend-enforced.

---

## Phase 7: Polish, Contracts, Documentation, and Quality Gates

**Purpose**: Synchronize all external contracts, prove real-server transport,
and run every repository gate.

- [X] T058 Update `postman/Service-Commerce.postman_collection.json` with an `Admin Hero Slides` folder for five Admin requests and a `Public Hero Slides` folder for public GET, documenting every key, 0/1 meaning, image/position rules, responses, permissions, and failures while preserving only the three approved collection variables
- [X] T059 Add Postman/OpenAPI synchronization coverage for exact folders, methods, URLs, multipart bodies, query examples, scripts, response examples, variable set, public projection, and absence of reorder in `tests/Architecture/HeroSlidesOpenApiAndPostmanContractTest.php`
- [X] T060 Complete exact implementation-to-OpenAPI contract assertions for routes, permissions, request fields, schemas, response keys, headers, statuses, and stable codes in `tests/Architecture/HeroSlidesOpenApiContractTest.php`
- [X] T061 Execute a real PHP 8.2/8.3 HTTP-server Postman/curl smoke flow for direct PATCH multipart text/image replacement, duplicate/oversized rejection, and temporary-file cleanup; record commands and results in `specs/008-hero-slider-management/checklists/runtime-verification.md`
- [X] T062 Re-run every manual scenario and expected outcome in `specs/008-hero-slider-management/quickstart.md` and record only environment-specific evidence without weakening the frozen contract
- [X] T063 Run all Feature 008 API, Unit, Database, Architecture,
  parser, transport, and permission tests under
  `tests/Feature/Api/V1/Admin/HeroSlides/`,
  `tests/Feature/Api/V1/Public/HeroSlides/`,
  `tests/Unit/HeroSlides/`, `tests/Unit/Http/`,
  `tests/Feature/Database/HeroSlides/`,
  `tests/Feature/Database/Permissions/`, `tests/Feature/Http/`, and
  `tests/Architecture/*HeroSlides*`

- [X] T064 Run the dedicated real MySQL suite in `tests/Concurrency/HeroSlides/HeroSlideCriticalConcurrencyTest.php`
- [X] T065 Run the full regression suite with `php artisan test`
- [X] T066 Run `vendor/bin/pint --test`
- [X] T067 Run `vendor/bin/phpstan analyse`
- [X] T068 Re-run `composer validate --no-check-publish` and `composer check-platform-reqs`; do not run an unscoped Composer update, and mark release verification blocked in `specs/008-hero-slider-management/checklists/runtime-verification.md` if the manifest/lock drift remains
- [X] T069 Complete the Feature 008 acceptance and scope audit against `docs/features/008-hero-slider-management.md`, `specs/008-hero-slider-management/spec.md`, `specs/008-hero-slider-management/plan.md`, and `specs/008-hero-slider-management/checklists/requirements.md`

---

## Dependencies and Execution Order

### Phase Dependencies

- **Phase 1** has no dependency and records runtime risks first.
- **Phase 2** depends on Phase 1 and blocks all user stories, including completion of the bounded mutation retrier.
- **US1, US2, US3, and US4** depend on Phase 2.
- **US1 and US2** may be developed in parallel after Foundation because US2
  seeds slides directly; both share files and therefore require coordination.
- **US3** may run in parallel with Admin stories after Foundation.
- **US4** tests depend on the Admin routes from US1 and US2, although permission
  Seeder implementation is foundational.
- **Phase 7** depends on all desired stories being complete.

### Foundational Dependencies

```text
T006 → T007 + T008 + T016
T007 + T009 + T012 + T013 + T014 → US1 + US2
T013 → T018
T011 → T053 + T055 + T056
T010 + T011 → Admin mutation stories
T015 → T034
```

### User Story Dependencies

```text
US1: T023 → T024 → T025 → T026
US1 concurrency: T013 + T014 + T024 → T027 → T028

US2 reads: T015 → T034 → T035 → T043 → T044
US2 PATCH transport: T036 → T037 → T038 + T039 → T040 → T041
US2 delete: T013 + T014 → T042
US2 integration: T035 + T041 + T042 → T043 → T044
US2 concurrency: T014 + T041 + T042 → T045 → T046

US3: T049 → T050 → T051
US4: T026 + T044 + T051 + T053 + T054 + T055 → T056
```

### Parallel Opportunities

- T003, T004, and T005 may run in parallel after T001.
- T007–T011, T015–T017, and T019 use separate files and may proceed in
  parallel after their explicit schema/service dependencies.
- T020, T021, and T022 may be written in parallel.
- T030–T033 may be written in parallel.
- T034 and T035 may proceed in parallel with T036–T039.
- US3 may proceed in parallel with US1/US2 after Foundation.
- T053, T054, and T055 may be prepared in parallel and executed when routes
  exist.
- T059 and T060 may proceed in parallel after T058 and endpoint integration.

---

## Parallel Examples

### User Story 1

```text
T020: Create contract/validation tests
T021: Create image/storage tests
T022: Create ordering/limit tests
```

### User Story 2

```text
T030: Admin index/show tests
T031: Partial update/move tests
T032: Image replacement/compensation tests
T033: Permanent delete/compaction tests
```

### User Story 3

```text
T048: Public endpoint tests
T049: Public Resource implementation
```

### User Story 4

```text
T053: Permission Seeder tests
T054: Route architecture tests
T055: Endpoint authorization tests
```

---

## Implementation Strategy

### MVP First — User Story 1

1. Complete Setup and Foundation.
2. Implement and test create with safe image storage, append/insertion, and
   maximum-count concurrency.
3. Stop at T029 and verify US1 independently.

### Incremental Delivery

1. **US1**: Safe ordered creation.
2. **US2**: Admin read/update/delete, direct multipart PATCH, and compaction.
3. **US3**: Public active localized projection.
4. **US4**: Complete permission isolation proof.
5. **Phase 7**: OpenAPI/Postman synchronization, real-server smoke,
   regression, formatting, static analysis, dependency verification, and final
   scope audit.

### Release Boundary

The complete Feature 008 contract is the recommended release unit. US1 is a
demonstrable MVP slice, but production release should include all four stories,
the real-server PATCH proof, MySQL concurrency evidence, and a reconciled or
explicitly blocked Composer manifest/lock state.
