# Feature 004 Implementation Audit

Date: 2026-08-01
Branch: `004-services-catalog`
Approved reference: `docs/features/004-services-catalog.md`
Status: Implementation in final verification

## Phase 1 Audit Summary

This audit records the current repository baseline before Feature 004 code
changes begin. Decisions use the required labels:

- Reuse
- Update
- Replace
- Delete-only-when-unreferenced
- Create-only-when-missing

## T001 — Existing Service-Related Surface Audit

### Routes

| Area | Current state | Decision | Notes |
|---|---|---|---|
| `routes/api.php` | Mounts `/api/v1/admin/auth`, `/api/v1/admin`, `/api/v1/public` via per-file groups | Reuse | Feature 004 must extend the existing V1 route layout only |
| `routes/api/v1/admin.php` | Contains customers + categories + subcategories under shared admin middleware | Update | Add service routes into the same middleware group and keep static routes before numeric params |
| `routes/api/v1/public.php` | Contains public category endpoints only | Update | Add public service list/detail endpoints without changing category contracts |

### Middleware and shared API behaviour

| Area | Current state | Decision | Notes |
|---|---|---|---|
| `bootstrap/app.php` | Registers `admin.auth.headers`, `admin.user_type`, `admin.active`, locale resolution, and shared exception rendering | Reuse | Service endpoints must use the same middleware and response conventions |
| `app/Enums/HttpStatusCode.php` | Shared enum-backed application status codes | Reuse | Use `HttpStatusCode::*` instead of raw integers |
| `app/Http/Middleware/ResolveApiLocale.php` and `ApplyAuthenticationResponseHeaders.php` | Locale resolved from `Accept-Language`; responses carry `Content-Language` + `Vary` | Reuse | Public/admin service responses must honor the same localization pipeline |

### Existing domain code near Feature 004

| Area | Current state | Decision | Notes |
|---|---|---|---|
| `app/Models/Category.php` | Root/subcategory hierarchy model with active, ordered, soft-delete scopes | Update | Add service relations/scopes only as needed for Feature 004 |
| `app/Actions/Categories/*` | Existing create/update/delete/restore/reorder workflows for categories and subcategories | Update | Delete actions need real service dependency guards |
| `app/Queries/Categories/*` | Existing Spatie Query Builder usage and localized sorting/filter patterns | Reuse | Mirror these conventions in service queries |
| `app/Http/Requests/Api/V1/Admin/Categories/*` | Existing admin request naming and validation structure | Reuse | Follow the same request organization for services |
| `app/Http/Resources/Api/V1/Admin/Categories/*` and `app/Http/Resources/Api/V1/Public/Categories/*` | Existing admin/public resource projection patterns | Reuse | Match envelope/resource style for services |
| `database/seeders/RolesAndPermissionsSeeder.php` | Seeds customers/categories/subcategories permissions and syncs to `super-admin` | Update | Add all Feature 004 permissions idempotently |
| `lang/` | Existing feature translations present for earlier modules | Update | Add service and media translation files |

### Tests and support artifacts

| Area | Current state | Decision | Notes |
|---|---|---|---|
| `tests/Feature/Api/V1/Admin/Categories/*` | Feature-style admin API test layout already established | Reuse | Add service suites in the same hierarchy |
| `tests/Feature/Api/V1/Public/Categories/PublicCategoryApiTest.php` | Public API conventions already covered | Reuse | Public service tests should follow same localization/assertion style |
| `tests/Concurrency/Categories/CategoryCriticalConcurrencyTest.php` | Real process-based concurrency tests already exist | Reuse | Extend pattern for service-sensitive category deletion and future service concurrency |
| `tests/Support/CategoryConcurrencyRunner.php` | Dedicated process runner for category concurrency | Update | Reuse if enough; otherwise add service-specific runner beside it |
| `tests/Architecture/*` | Existing route/postman contract tests present | Reuse | Add Feature 004 route contract coverage here |

### Service implementation presence

| Area | Current state | Decision | Notes |
|---|---|---|---|
| `app/Models/Service*.php` | Missing | Create-only-when-missing | No legacy service model exists |
| `app/Actions/Services/*` | Missing | Create-only-when-missing | Needed for transactional orchestration |
| `app/Queries/Services/*` | Missing | Create-only-when-missing | Needed for approved filters/sorts |
| `app/Http/Controllers/Api/V1/*/Services/*` | Missing | Create-only-when-missing | Admin + Public controllers required |
| `app/Http/Requests/Api/V1/Admin/Services/*` | Missing | Create-only-when-missing | Dedicated requests required |
| `app/Http/Resources/Api/V1/*/Services/*` | Missing | Create-only-when-missing | Required by contract |
| `database/migrations/*services*` | Missing | Create-only-when-missing | Feature 004 schema does not exist yet |
| `database/factories/*Service*` | Missing | Create-only-when-missing | Needed for tests |
| Postman service artifacts | Not found in repository audit scope | Update if discovered later | No repository-local Postman collection was identified during this audit |

### Delete-only-when-unreferenced findings

- No existing service-specific code was found that should be deleted.
- No current category/customer artifacts are obsolete for Feature 004.

## Final Implementation Notes

Implemented and verified in this branch:

- Core service CRUD, restore, and service-aware category/subcategory deletion
  guards.
- Specifications, order fields, pricing options/values, media workflows, and
  atomic nested multipart service creation.
- Public service list/detail APIs.
- Critical MySQL concurrency coverage for hierarchy races, slug reservation
  uniqueness, single-main-image enforcement, and single-video enforcement.
- Route/permission architecture coverage for all 26 Admin and 2 Public service
  operations.
- OpenAPI and Postman synchronization for Feature 004 plus the Feature 003
  category delete conflict update for `CATEGORY_HAS_SERVICES`.

Feature 004 contract synchronization artifacts updated:

- `specs/004-services-catalog/contracts/openapi.yaml`
- `postman/Service-Commerce.postman_collection.json`
- `specs/003-categories-subcategories/contracts/openapi.yaml`

### Exact verification commands used during implementation

```powershell
php artisan test tests\Feature\Api\V1\Admin\Services\ServiceApiTest.php
php artisan test tests\Feature\Api\V1\Admin\Services\ServiceComponentsApiTest.php
php artisan test tests\Feature\Api\V1\Admin\Services\ServiceMediaApiTest.php
php artisan test tests\Feature\Api\V1\Admin\Services\CreateServiceWithNestedDataTest.php
php artisan test tests\Feature\Api\V1\Public\Services\PublicServiceApiTest.php
php artisan test tests\Feature\Database\Services\ServiceSchemaTest.php
php artisan test tests\Concurrency\Services\ServiceCriticalConcurrencyTest.php
php artisan test tests\Architecture\ServicesAdminRouteContractTest.php
php artisan test tests\Architecture\ServicesFeatureArchitectureTest.php
php artisan test tests\Architecture\ServicesOpenApiAndPostmanContractTest.php
```

### Operational notes

- The service concurrency suite is intentionally serial and uses real MySQL
  processes via `tests/Support/ServiceConcurrencyRunner.php`.
- Do not run Feature 004 concurrency coverage in parallel with other suites.
- The hierarchy race fix depends on validating locked category/subcategory rows
  after lock acquisition rather than relying on a stale transaction snapshot.
- Service create and update transactions now use bounded retry attempts to
  recover from transient deadlocks during slug reservation races.

## T002 — Installed Version Verification

Verified from `composer.json`, `composer.lock`, `README.md`, and `phpunit.xml`:

| Package / runtime | Declared | Installed / active |
|---|---|---|
| PHP | `^8.3` | Lock and PHPUnit config align with PHP 8.3 |
| Laravel | `^13.8` | `laravel/framework v13.23.0` |
| Sanctum | `^4.3` | `laravel/sanctum v4.3.3` |
| Spatie Permission | `^8.3` | `spatie/laravel-permission 8.3.0` |
| Spatie Query Builder | `^7.3` | `spatie/laravel-query-builder 7.3.0` |
| Pest | `^4.7` | `pestphp/pest v4.7.5` |
| Pest Laravel plugin | `^4.1` | `pestphp/pest-plugin-laravel v4.1.0` |
| Larastan | n/a in `composer.json` excerpt, installed in lock | `larastan/larastan v3.10.0` |
| PHPStan | transitive/installed | `phpstan/phpstan 2.2.6` |

Observations:

- `README.md` is still the default Laravel README and does not add project
  constraints for Feature 004.
- `phpunit.xml` confirms the test database is MySQL
  (`service_commerce_test`) and not SQLite.

## T003 — Route Mounting, Middleware, Shared Responses, Locale, Status Enum

Verified baseline:

- `routes/api.php` mounts:
  - `/api/v1/admin/auth/*`
  - `/api/v1/admin/*`
  - `/api/v1/public/*`
- `routes/api/v1/admin.php` admin stack order is:
  - `admin.auth.headers`
  - `auth:sanctum`
  - `admin.user_type`
  - `admin.active`
- `bootstrap/app.php` centralizes locale resolution and exception rendering.
- Shared application status enum is `App\Enums\HttpStatusCode`.

Decision summary:

- Reuse the existing mount structure.
- Reuse the existing middleware order.
- Reuse shared response/exception handling and enum-backed status usage.
- Update only the route files to add Feature 004 endpoints.

## T004 — Category/Subcategory Deletion Workflow and Locking Audit

Verified current deletion behavior:

- `DeleteCategoryAction`:
  - locks root category
  - ensures active-for-mutation
  - locks active child subcategories ordered by `id`
  - blocks with `CATEGORY_HAS_SUBCATEGORIES`
  - soft deletes root when clear
- `DeleteSubcategoryAction`:
  - locks root category first
  - locks scoped subcategory second
  - soft deletes subcategory
  - currently performs no service dependency check
- `tests/Concurrency/Categories/CategoryCriticalConcurrencyTest.php` already
  proves the project expects real locking and process-level race checks.

Decision summary:

- Update both delete actions, not replace them.
- Preserve the approved lock order and extend it with blocking service-row
  locks ordered by `id ASC`.
- Reuse existing concurrency-test style and add focused service dependency
  coverage.

## T005 — Testing Configuration and File-Storage Pattern Audit

Verified baseline:

- `.env.example` uses MySQL and `FILESYSTEM_DISK=public`.
- `phpunit.xml` uses:
  - `DB_CONNECTION=mysql`
  - `DB_DATABASE=service_commerce_test`
  - `FILESYSTEM_DISK=public`
  - `QUEUE_CONNECTION=sync`
- `tests/Pest.php` extends `Feature`, `Architecture`, `Concurrency`, and `Unit`
  with the shared `Tests\TestCase`.
- Existing support runners:
  - `tests/Support/AdminAuthTestHelpers.php`
  - `tests/Support/CategoryConcurrencyRunner.php`
  - `tests/Support/CustomerConcurrencyRunner.php`
  - `tests/Support/PasswordRecoveryConcurrencyRunner.php`
  - `tests/Support/RefreshConcurrencyRunner.php`
- `docs/02-standards/file-storage-standards.md` confirms public-disk MVP
  storage, generated filenames, MIME + extension validation, no raw storage
  paths, and rollback compensation requirements.

Decision summary:

- Reuse the existing MySQL test configuration.
- Reuse the existing Pest suite structure.
- Reuse the public-disk storage decision.
- Update tests to use the existing filesystem-testing conventions and add
  service-media compensation coverage.

## Ignore-File Verification

Current repository ignore baseline:

- `.gitignore` exists and already excludes key PHP/Laravel generated paths such
  as `.env`, `vendor/`, `public/storage`, `.phpunit.result.cache`,
  `.phpunit.cache`, IDE folders, and logs.
- No Docker, ESLint, Prettier, npm publishing, Terraform, or Helm ignore files
  are currently required by the active PHP-only repository setup.

Decision:

- Reuse `.gitignore` as-is for Feature 004.
- Create additional ignore files only if a later task introduces the matching
  toolchain, which Feature 004 does not.
