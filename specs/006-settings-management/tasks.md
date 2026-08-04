# Tasks: Settings Management

**Input**: Design documents from `/specs/006-settings-management/`

**Approved source**: `docs/features/006-settings-management.md`

**Prerequisites**: `plan.md` (required), `spec.md` (required for user stories),
`research.md`, `data-model.md`, `contracts/openapi.yaml`, and `quickstart.md`

**Tests**: Tests are mandatory implementation work. Feature 006 coverage must
include the applicable success, validation, authentication, authorization,
localization, persistence, response-contract, file, singleton-recovery, and
real-MySQL concurrency behaviour required by the approved specification.

**Organization**: Tasks are grouped by user story so each story can be
implemented and validated independently while preserving the repository's
existing Laravel conventions.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: May run in parallel only after all declared dependencies are
  complete and when changed files do not overlap.
- **[Story]**: User story mapping, for example `[US1]`.
- Every task includes the primary file paths it changes.
- Existing compatible artifacts must be reused or updated instead of recreated.
- Tests may be scaffolded early, but a test task is complete only when the
  corresponding behaviour passes.
- The project-standard `HttpStatusCode` enum remains the approved response
  status enum for this repository and must be reused consistently.

## Path Conventions

- Laravel application code: `app/`
- Versioned API routes: `routes/api.php` and `routes/api/v1/`
- Database artifacts: `database/migrations/`, `database/factories/`, and
  `database/seeders/`
- API Feature Tests: `tests/Feature/Api/V1/`
- Database and model verification: `tests/Feature/Database/`
- Concurrency tests: `tests/Concurrency/`
- Architecture and contract tests: `tests/Architecture/`
- Feature documentation: `specs/006-settings-management/`

---

## Phase 1: Setup — Repository Verification

**Purpose**: Confirm the exact repository integration points and established
patterns before creating schema or endpoint code.

- [X] T001 Audit the existing API response helpers, Admin/Public route
  registration, file-upload services, permission seeding flow, and translation
  structure that Feature 006 must extend in `app/Support/Api/`,
  `routes/api.php`, `routes/api/v1/admin.php`, `routes/api/v1/public.php`,
  `app/Services/`, `database/seeders/`, and `lang/`
- [X] T002 Verify the installed PHP, Laravel, Sanctum, Spatie Permission,
  Spatie Query Builder, Pest, Pint, and Larastan/PHPStan versions and existing
  conventions in `composer.json`, `composer.lock`, `README.md`, and
  `phpunit.xml`
- [X] T003 [P] Confirm the dedicated MySQL testing configuration, current
  filesystem fake patterns, logging assertions, and locale-testing helpers in
  `.env.example`, `phpunit.xml`, `tests/Pest.php`, and `tests/Support/`
- [X] T004 [P] Verify and document the existing `HttpStatusCode` enum usage so
  all Feature 006 success and application-controlled status responses follow
  the same repository convention in `app/Enums/`,
  `app/Http/Controllers/Api/`, and `app/Support/Api/`

**Checkpoint**: Repository conventions and safe extension points are confirmed.

---

## Phase 2: Foundational — Blocking Prerequisites

**Purpose**: Establish schema, enum, models, singleton recovery, seeders,
permissions, translations, and integrity tests required by every user story.

**⚠️ CRITICAL**: No user-story implementation begins until this phase passes.

- [X] T005 Create the approved singleton schema with scalar columns, nullable
  JSON SEO keyword columns, `decimal(10,7)` coordinates, ordered phone/social
  child tables, foreign keys, uniqueness rules, and required indexes in
  `database/migrations/*_create_settings_table.php`,
  `database/migrations/*_create_setting_phones_table.php`, and
  `database/migrations/*_create_setting_social_links_table.php`
- [X] T006 [P] Implement the integer-backed social-platform enum with the exact
  approved API machine keys in `app/Enums/Settings/SocialPlatform.php`
- [X] T007 Implement the Eloquent models, relationships, ordered relation
  loading, casts, and factories after T005 and T006 in
  `app/Models/Setting.php`, `app/Models/SettingPhone.php`,
  `app/Models/SettingSocialLink.php`,
  `database/factories/SettingFactory.php`,
  `database/factories/SettingPhoneFactory.php`, and
  `database/factories/SettingSocialLinkFactory.php`
- [X] T008 Implement deterministic singleton resolution for `settings.id = 1`
  after T007 in `app/Services/Settings/SettingsResolver.php`; Admin recovery
  must execute inside a database transaction using an idempotent insert such as
  `insertOrIgnore`, then re-read `settings.id = 1` under `lockForUpdate` before
  commit, while Public resolution must return approved safe defaults without
  writing to the database
- [X] T009 [P] Register the exact `settings.view` and `settings.update`
  permissions and integrate them idempotently with the existing super-admin
  permission flow in `database/seeders/SettingsPermissionsSeeder.php`,
  `database/seeders/RolesAndPermissionsSeeder.php`, and
  `database/seeders/DatabaseSeeder.php`
- [X] T010 Create an idempotent singleton `SettingsSeeder` after T007 and T009 that
  ensures `settings.id = 1` exists with approved placeholder-safe required
  values, does not create duplicate rows, and never overwrites Admin-managed
  values on repeated execution in
  `database/seeders/SettingsSeeder.php` and
  `database/seeders/DatabaseSeeder.php`
- [X] T011 [P] Add Arabic and English translations for settings validation,
  business rules, platform labels, file errors, and singleton recovery
  messages in `lang/ar/settings.php` and `lang/en/settings.php`
- [X] T012 Confirm the concrete route extension points for exactly three
  approved operations without registering incomplete handlers in
  `routes/api/v1/admin.php` and `routes/api/v1/public.php`
- [X] T013 Add database/model integrity coverage after T005–T007 for singleton
  identity, exact column/cast behaviour, foreign keys, ordered relations,
  unique normalized phones, unique social platforms, coordinate precision,
  and absence of per-row disk columns in
  `tests/Feature/Database/Settings/SettingsSchemaTest.php`
- [X] T014 Add idempotent seeder and resolver coverage after T008 and T010,
  including repeated seeding without overwrite, Admin missing-row recovery,
  Public missing-row defaults without persistence, and deterministic
  `settings.id = 1` resolution in
  `tests/Feature/Database/Settings/SettingsSingletonTest.php`

**Checkpoint**: Schema, models, singleton lifecycle, seeders, permissions,
translations, and database guarantees are ready.

---

## Phase 3: User Story 1 — Admin Reviews Settings (Priority: P1) 🎯 MVP

**Goal**: Deliver authenticated Admin read access to the full editable Settings
resource with safe singleton recovery.

**Independent Test**: Authenticate with `settings.view`, call
`GET /api/v1/admin/settings`, and verify the complete approved Admin contract
without internal IDs, timestamps, disk names, or raw storage paths.

### Tests for User Story 1 — Mandatory

- [X] T015 [P] [US1] Add the Admin settings read API suite covering success,
  `401`, `403`, shared envelope shape, bilingual fields, ordered child
  collections, absolute file URLs or `null`, decimal-string coordinates,
  available social platforms, and missing-singleton recovery in
  `tests/Feature/Api/V1/Admin/Settings/AdminSettingsReadTest.php`
- [X] T016 [P] [US1] Add the Admin settings route contract suite covering exact
  route registration, middleware order, `settings.view`, response schema
  parity, and absence of undocumented response fields for
  `GET /api/v1/admin/settings` in
  `tests/Architecture/SettingsAdminRouteContractTest.php`

### Implementation for User Story 1

- [X] T017 [P] [US1] Implement the complete Admin serializer with bilingual
  scalar fields, ordered phones, ordered social links, public file URLs,
  coordinate strings, keyword arrays, and `availableSocialPlatforms` in
  `app/Http/Resources/Api/V1/Admin/Settings/AdminSettingsResource.php`
- [X] T018 [US1] Implement the Admin read controller after T008 and T017 using
  deterministic singleton resolution and the existing `HttpStatusCode`
  response convention in
  `app/Http/Controllers/Api/V1/Admin/Settings/SettingsController.php`
- [X] T019 [US1] Register `GET /api/v1/admin/settings` after T018 in the
  approved Admin route group with the exact `settings.view` permission and
  established middleware order in `routes/api/v1/admin.php`

**Checkpoint**: Authorized Admin users can read the complete Settings resource.

---

## Phase 4: User Story 2 — Admin Updates Settings (Priority: P1)

**Goal**: Deliver atomic multipart updates for scalar values, ordered phones,
ordered social links, branding files, coordinates, SEO defaults, and explicit
clear/remove behaviour.

**Independent Test**: Submit multipart PATCH requests that exercise every
approved mutation and prove that failures leave no partial database or
filesystem state.

### Tests for User Story 2 — Mandatory

- [X] T020 [P] [US2] Add the comprehensive Admin update API suite in
  `tests/Feature/Api/V1/Admin/Settings/AdminSettingsUpdateTest.php` covering:
  partial success; omitted-field preservation; optional scalar `""` to `null`;
  required scalar empty/null rejection; full phone/social replacement;
  empty-array phone/social clearing; empty-value branding removal; phone
  maximum 3; social-link maximum 9;
  single-WhatsApp enforcement; invalid and duplicate normalized phones;
  duplicate platforms; approved `PhoneInput` variants using `+20`, `0020`,
  spaces, dashes, and parentheses; canonical `PhoneOutput`; `401`; `403`; and
  localized validation/business errors
- [X] T021 [P] [US2] Add the complete branding-file suite in
  `tests/Feature/Api/V1/Admin/Settings/AdminSettingsBrandingTest.php` covering:
  accepted JPG/JPEG/PNG/WEBP files; safe SVG acceptance without rewriting;
  unsafe SVG rejection without silent sanitization; exact extension, MIME, and
  content checks; exact logo/footer maximum 5 MB; exact favicon maximum 1 MB;
  client-supplied storage paths rejection; replacement; removal; transaction
  rollback cleanup of newly stored files; old-file deletion only after commit;
  post-commit deletion failure logging while retaining valid database state;
  and absolute URL serialization without raw paths
- [X] T022 [P] [US2] Add real MySQL concurrency coverage in
  `tests/Concurrency/Settings/SettingsConcurrencyTest.php` for singleton
  `lockForUpdate`, concurrent missing-singleton recovery using
  `insertOrIgnore` plus locked re-read, absence of duplicate-key leakage,
  concurrent PATCH serialization, maximum-three-phone preservation,
  single-WhatsApp enforcement, normalized-phone uniqueness, social-platform
  uniqueness, and no partial child replacement

### Implementation for User Story 2

- [X] T023 [P] [US2] Implement Egyptian phone normalization and duplicate
  comparison in `app/Services/Settings/EgyptianPhoneNormalizer.php`, accepting
  approved local/international formatting variants and returning canonical
  digits-only local output containing exactly 10 or 11 digits
- [X] T024 [P] [US2] Implement strict SVG content inspection in
  `app/Services/Settings/SvgSafetyInspector.php`; accept safe SVG unchanged and
  reject invalid XML/SVG, scripts, `javascript:`, event handlers,
  `foreignObject`, embedded HTML, and external-resource references without
  sanitizing or rewriting submitted content
- [X] T025 [P] [US2] Implement branding file storage, generated filenames,
  configured public-disk usage, absolute URL generation, replacement,
  rollback compensation, post-commit old-file cleanup, and safe cleanup
  logging in `app/Services/Settings/BrandingFileService.php`
- [X] T026 [US2] Implement multipart normalization and validation after
  T023–T025 in
  `app/Http/Requests/Api/V1/Admin/Settings/UpdateSettingsRequest.php`,
  including optional scalar empty-string-to-null semantics, omitted-field
  preservation, required-field protection, empty-array collection clearing,
  empty-value branding removal, coordinate pairing/clearing, SEO keyword duplicate rules,
  `PhoneInput` variants, child limits, enum validation, and exact file
  type/size/content boundaries
- [X] T027 [US2] Implement the transactional update workflow after T008 and
  T023–T026 in `app/Actions/Settings/UpdateSettingsAction.php`: store proposed
  files safely, begin transaction, recover and lock `settings.id = 1`, update
  submitted scalars only, replace/clear ordered child collections, commit
  branding paths, reload the complete resource, compensate newly stored files
  on rollback, and delete superseded files only after commit
- [X] T028 [US2] Extend the existing Admin settings controller after T018 and
  T027 to invoke the update action and return the complete updated resource
  using `HttpStatusCode::OK` in
  `app/Http/Controllers/Api/V1/Admin/Settings/SettingsController.php`
- [X] T029 [US2] Register `PATCH /api/v1/admin/settings` after T028 in the
  existing Admin route file and enforce the exact `settings.update`
  permission without changing the GET contract in `routes/api/v1/admin.php`;
  parse real multipart PATCH bodies through
  `app/Http/Middleware/ParseSettingsMultipartPatch.php` and
  `app/Services/Http/SettingsMultipartPatchParser.php`

**Checkpoint**: Admin Settings updates are atomic, contract-exact,
filesystem-safe, and concurrency-safe.

---

## Phase 5: User Story 3 — Public Reads Localized Settings (Priority: P2)

**Goal**: Deliver an unauthenticated localized Settings projection with stable
neutral keys and no Admin/internal data leakage.

**Independent Test**: Call `GET /api/v1/public/settings` using Arabic and
English locale variants and verify localized output, locale headers, safe
defaults, and no database write when the singleton is missing.

### Tests for User Story 3 — Mandatory

- [X] T030 [P] [US3] Add the Public settings API suite covering `ar`, `en`,
  `ar-EG → ar`, `en-US → en`, `Content-Language`,
  `Vary: Accept-Language`, stable neutral keys, localized scalar projection,
  ordered phones/social links, absolute file URLs, exclusion of
  Admin-only and dual-language fields, safe default output for a missing
  singleton, and proof that Public GET leaves the database unchanged in
  `tests/Feature/Api/V1/Public/Settings/PublicSettingsReadTest.php`
- [X] T031 [P] [US3] Add the Public route contract suite covering exact route
  registration, explicit no-auth access, locale middleware, response schema
  parity, and absence of undocumented fields for
  `GET /api/v1/public/settings` in
  `tests/Architecture/SettingsPublicRouteContractTest.php`

### Implementation for User Story 3

- [X] T032 [P] [US3] Implement the localized Public serializer with stable
  neutral keys, localized scalar values, ordered phones/social links, and
  public branding URLs in
  `app/Http/Resources/Api/V1/Public/Settings/PublicSettingsResource.php`
- [X] T033 [US3] Implement the Public controller after T008 and T032 using the
  non-persisting safe fallback and existing `HttpStatusCode::OK` convention in
  `app/Http/Controllers/Api/V1/Public/Settings/SettingsController.php`
- [X] T034 [US3] Register `GET /api/v1/public/settings` after T033 with no
  authentication middleware and the established locale middleware in
  `routes/api/v1/public.php`

**Checkpoint**: Public users receive safe localized Settings data without any
read-side persistence.

---

## Phase 6: Contract, Traceability, and Quality Gates

**Purpose**: Prove the frozen contract and implementation remain aligned, then
run all required project checks.

- [X] T035 [P] Add a complete OpenAPI 3.1 contract test in
  `tests/Architecture/SettingsOpenApiContractTest.php` that verifies:
  YAML parsing; `openapi: 3.1.0`; all local `$ref` values resolve; exactly
  three operations; unique `operationId` values; no `nullable` keyword;
  explicit `security: []` on Public GET; exact objects use
  `additionalProperties: false`; all approved response keys are required;
  nullable response values use JSON Schema null unions; PATCH phones reference
  `PhoneInput`; Admin/Public response phones reference `PhoneOutput`; and no
  undocumented request/response properties exist
- [X] T036 [P] Create a requirement-to-task traceability checklist mapping each
  approved Feature 006 requirement and quickstart scenario to its
  implementation task and automated test in
  `specs/006-settings-management/checklists/requirements-to-tasks.md`
- [X] T037 Synchronize Postman examples and quickstart instructions with the
  already-frozen specification and OpenAPI contract in
  `postman/Service-Commerce.postman_collection.json` and
  `specs/006-settings-management/quickstart.md`; implementation differences
  must be fixed in code and must not be used to modify `spec.md` or
  `contracts/openapi.yaml` without explicit approval
- [X] T038 Verify security, localization, middleware order, response envelopes,
  file boundaries, exact URL/path exposure, enum machine keys, and absence of
  internal IDs/timestamps/raw storage paths across
  `app/Http/Resources/Api/V1/Admin/Settings/`,
  `app/Http/Resources/Api/V1/Public/Settings/`, related Requests,
  Controllers, Actions, and Services
- [X] T039 Run all Feature 006 Pest suites against the dedicated MySQL test
  database, including `tests/Feature/Database/Settings/`,
  `tests/Feature/Api/V1/Admin/Settings/`,
  `tests/Feature/Api/V1/Public/Settings/`,
  `tests/Architecture/*Settings*`, and
  `tests/Concurrency/Settings/`
- [X] T040 Run the full application regression suite with `php artisan test`
  after Feature 006 tests pass to verify that migrations, seeders, shared
  routes, middleware, and API response changes did not break existing modules
- [X] T041 Run `vendor/bin/pint --test`
- [X] T042 Run `vendor/bin/phpstan analyse`
- [X] T043 Run every manual scenario in
  `specs/006-settings-management/quickstart.md` and record any approved
  environment-specific notes without changing the frozen behaviour

---

## Dependencies and Execution Order

### Phase Dependencies

- **Phase 1** has no dependencies.
- **Phase 2** depends on Phase 1 and blocks all user stories.
- **Phases 3–5** depend on Phase 2.
- **Phase 4** modifies the same Admin controller and Admin route file introduced
  in Phase 3, so T028 depends on T018 and T027, and T029 depends on T019 and
  T028.
- **Phase 6** depends on all implemented user stories.

### Foundational Dependencies

```text
T005 → T007
T006 → T007
T007 → T008
T007 + T009 → T010
T005 + T006 + T007 → T013
T008 + T010 → T014
```

T008 may begin after T007. T010 begins after T007 and T009 because T009 and T010 both update `database/seeders/DatabaseSeeder.php`.

### User Story Dependencies

```text
US1:
T008 + T017 → T018 → T019

US2:
T023 + T024 + T025 → T026
T008 + T023 + T024 + T025 + T026 → T027
T018 + T027 → T028
T019 + T028 → T029

US3:
T008 + T032 → T033 → T034
```

### Parallel Opportunities

- T003 and T004 may run in parallel.
- After T005 stabilizes schema shape, T006, T009, and T011 may run in parallel.
- After T007, T008 may begin independently; T010 waits for both T007 and T009.
- T015, T016, and T017 may be developed in parallel after Phase 2.
- T020, T021, and T022 may be scaffolded in parallel and completed against the
  implemented behaviour.
- T023, T024, and T025 may run in parallel.
- T030, T031, and T032 may run in parallel after Phase 2.
- T035 and T036 may run in parallel after the contract is frozen.

---

## Parallel Example: User Story 2

```bash
# Independent service work:
Task: "Implement app/Services/Settings/EgyptianPhoneNormalizer.php"
Task: "Implement app/Services/Settings/SvgSafetyInspector.php"
Task: "Implement app/Services/Settings/BrandingFileService.php"

# Independent test files:
Task: "Implement AdminSettingsUpdateTest.php"
Task: "Implement AdminSettingsBrandingTest.php"
Task: "Implement SettingsConcurrencyTest.php"
```

---

## Implementation Strategy

### MVP First

1. Complete Phase 1.
2. Complete Phase 2.
3. Complete User Story 1.
4. Validate Admin read independently.
5. Continue to the atomic update workflow and Public projection.

### Incremental Delivery

1. Setup and foundational infrastructure.
2. Admin read.
3. Admin atomic update.
4. Public localized read.
5. Contract verification, traceability, and quality gates.

### Multi-Developer Strategy

1. Complete Phases 1 and 2 together.
2. After Phase 2:
   - Developer A: US1 Admin read.
   - Developer B: US2 services, Request, Action, and tests.
   - Developer C: US3 Public resource, controller, and tests.
3. Serialize work that touches the shared Admin controller and Admin route file.
4. Finish contract and cross-cutting validation together.

---

## Notes

### Post-implementation Google Maps URL amendment — 2026-08-04

- [x] T072 Add nullable `googleMapsUrl` validation, normalization, persistence,
  and Admin/Public resource projection without restoring coordinates.
- [x] T073 Synchronize the OpenAPI and Postman contracts and cover update,
  clearing, invalid URL, Admin read, Public read, and safe-default behavior.

- `HttpStatusCode` is intentionally retained because it is the established
  enum used by the existing application modules.
- `[P]` never overrides a declared dependency or overlapping-file constraint.
- Tests are complete only when they pass against the approved MySQL test
  database and real filesystem/logging behaviour where applicable.
- The implementation must conform to the frozen `spec.md` and
  `contracts/openapi.yaml`; do not weaken or rewrite the contract to fit an
  implementation shortcut.
- Keep all API responses on the shared envelope.
- Do not add extra Settings domains, extra routes, customer authentication,
  analytics, queues, Redis, or unapproved integrations.
