# Feature 003 Quickstart — Categories and Subcategories

**Feature:** `003-categories-subcategories`  
**Branch:** `003-categories-subcategories`  
**Status:** Implemented and Final Verification Passing

## 1. Purpose

This guide defines the validation scenarios and commands that now verify the
implemented Feature 003 behavior.

It remains a runnable acceptance guide, not the implementation itself.

## 2. Prerequisites

- PHP and Composer available for the Laravel 13 project
- MySQL available for local and testing databases
- `.env` configured for a local development database
- `.env.testing` or equivalent testing environment configured for a dedicated
  MySQL test database
- Feature 001 administrator authentication already working
- Super Admin seeded with the new Feature 003 permissions

## 3. Existing Implementation Audit

Before creating files or migrations, verify whether category/catalog artifacts
already exist.

```powershell
git status
php artisan route:list --path=api/v1/admin/categories
php artisan route:list --path=api/v1/public/categories
git grep -n "class Category"
git grep -n "categories"
```

If a legacy schema or route surface is discovered, record:

```text
existing artifact
current behavior/schema
target behavior/schema
action: reuse | update | replace | delete-if-unreferenced | create-if-missing
```

Stop before implementation if any of these are unresolved:

- a duplicate table or route tree would be introduced
- an executed migration would need editing
- a legacy category table cannot be mapped safely
- public routes would be added outside `/api/v1/public/*`

## 4. Setup Commands

From the repository root:

```powershell
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder
php artisan db:seed --class=Database\\Seeders\\SuperAdminSeeder
```

If Feature 003 permissions are seeded separately during implementation:

```powershell
php artisan db:seed --class=Database\\Seeders\\CategoriesPermissionsSeeder
```

Seed the printing catalogue (six Categories, twelve Subcategories, and their
externally sourced images) independently with:

```powershell
php artisan db:seed --class=Database\\Seeders\\PrintingCatalogSeeder
php artisan storage:link
```

The normal `php artisan db:seed` and `php artisan migrate:fresh --seed` flows
also run this seeder. The first run requires outbound HTTPS access to Wikimedia
Commons; later runs reuse valid files already stored under
`storage/app/public/categories/seed`.

## 5. Contract References

- API contract: [contracts/openapi.yaml](./contracts/openapi.yaml)
- Data model: [data-model.md](./data-model.md)
- Feature spec: [spec.md](./spec.md)

## 6. Validation Scenarios

### Scenario A — Create root Category with bilingual description pair

1. Authenticate through Feature 001 and obtain an access token.
2. Send `POST /api/v1/admin/categories` with:

```json
{
  "nameAr": "خدمات المنازل",
  "nameEn": "Home Services",
  "descriptionAr": "خدمات احترافية للمنزل تشمل الصيانة والتركيب.",
  "descriptionEn": "Professional home services including maintenance and installation.",
  "sortOrder": 10,
  "isActive": true
}
```

Expected outcome:

- `201 Created`
- shared success envelope
- admin response returns both localized names, descriptions, and slugs
- `parentId` is not client-controlled

### Scenario B — Reject one-language-only description payload

1. Send create or update with only `descriptionAr` or only `descriptionEn`.

Expected outcome:

- `422 Unprocessable Entity`
- `VALIDATION_ERROR`
- no partial description pair is persisted

### Scenario C — Create Subcategory only under a root Category

1. Create a root Category.
2. Send `POST /api/v1/admin/categories/{category}/subcategories`.
3. Attempt the same flow using a Subcategory as `{category}`.

Expected outcome:

- root-parent create succeeds
- third-level create fails
- no child of child record is created

### Scenario D — Keep slugs stable on name-only update

1. Create a Category with explicit or generated localized slugs.
2. Update only `nameAr` and/or `nameEn`.

Expected outcome:

- request succeeds
- stored `slugAr` and `slugEn` remain unchanged

### Scenario E — Block root Category deletion while Subcategories exist

1. Create a root Category and one active Subcategory.
2. Attempt to delete the root Category.

Expected outcome:

- `409 Conflict`
- code `CATEGORY_HAS_SUBCATEGORIES`
- neither row is soft-deleted

### Scenario F — Restore Subcategory under deleted vs inactive parent

1. Delete a Subcategory.
2. Delete its parent and attempt restore.
3. Restore the parent, set it inactive, and attempt restore again.

Expected outcome:

- deleted parent blocks restore with `PARENT_CATEGORY_DELETED`
- inactive but non-deleted parent allows restore
- restored Subcategory remains hidden publicly while parent is inactive

### Scenario G — Public localized slug lookup and non-disclosure

1. Create one active root Category and one active Subcategory.
2. Call `/api/v1/public/categories*` Arabic list/detail endpoints with
   Arabic slugs.
3. Call `/api/v1/public/categories*` English list/detail endpoints with English
   slugs.
4. Retry with the wrong locale slug or after deactivating/deleting the record.

Expected outcome:

- localized `name`, `description`, and `slug` resolve correctly
- wrong-locale slug returns `404`
- inactive or deleted records return non-disclosing `404`

### Scenario H — Atomic reorder

1. Create multiple root Categories and multiple Subcategories under one parent.
2. Reorder roots through `PATCH /api/v1/admin/categories/reorder`.
3. Reorder scoped children through
   `PATCH /api/v1/admin/categories/{category}/subcategories/reorder`.

Expected outcome:

- all selected rows update deterministically
- no partial reorder is visible on failure
- cross-parent IDs are rejected


### Scenario I — Public language-switch metadata and headers

1. Request a visible Category detail with `Accept-Language: ar`.
2. Inspect response headers and metadata.
3. Follow `meta.localeLinks.en` with `Accept-Language: en`.

Expected outcome:

- `Content-Language: ar` on the first response
- `Vary: Accept-Language`
- Arabic content only in `data`
- valid Arabic and English URLs in `meta.localeLinks`
- the English alternate URL resolves to English content
- guessing or using the wrong-locale slug still returns `404`

### Scenario J — Successful soft-delete envelope

1. Create a root Category without children and delete it.
2. Create a Subcategory without Services and delete it.

Expected outcome for both:

- `200 OK`
- shared success envelope
- `data: null`
- no `204 No Content` response

### Scenario K — Shared hierarchy-lock races

Run each pair through separate MySQL connections or workers.

#### Race 1: root delete vs Subcategory create

1. Start deleting one root Category.
2. Concurrently create a Subcategory under the same root.

Allowed outcomes:

- child create succeeds and root delete returns
  `CATEGORY_HAS_SUBCATEGORIES`; or
- root delete succeeds and child create is rejected.

Forbidden outcome:

- a non-deleted child exists under a deleted root.

#### Race 2: parent delete vs Subcategory restore

1. Start restoring one deleted Subcategory.
2. Concurrently delete its parent Category.

Allowed outcomes:

- restore completes while the parent remains eligible; or
- parent deletion wins and restore returns `PARENT_CATEGORY_DELETED`.

Forbidden outcome:

- a restored child exists under a deleted parent.

#### Lock evidence

The implementation must demonstrate:

```text
parent/root lock first
-> child locks by id ASC
-> revalidation
-> mutation
```

No hierarchy Action may acquire these locks in the opposite order.

### Scenario L — Localized Admin indexes and Query Builder filters

1. Call:

```http
GET /api/v1/admin/categories?filter[search]=home&filter[isActive]=true&filter[trashed]=without&sort=name&page=1&perPage=20
Accept-Language: en
```

2. Repeat with `Accept-Language: ar`.
3. Call the nested Subcategory index with the same filter syntax.

Expected outcome:

- index items contain `name`, `description`, and `slug`;
- index items do not contain `nameAr`, `nameEn`, `descriptionAr`,
  `descriptionEn`, `slugAr`, or `slugEn`;
- operational fields such as `id`, `sortOrder`, `isActive`, and timestamps
  remain available;
- English and Arabic requests project the matching locale;
- `filter[search]` can match either Arabic or English stored content;
- `sort=name` uses the resolved locale column;
- raw query keys such as `search`, `sortBy`, and `sortDirection` are rejected
  or ignored according to the shared unknown-query policy.

## 7. Automated Verification Commands

Run focused suites first:

```powershell
php artisan test tests\\Feature\\Api\\V1\\Admin\\Categories
php artisan test tests\\Feature\\Api\\V1\\Public\\Categories
php artisan test tests\\Concurrency\\Categories
php artisan test tests\\Architecture\\CategoriesFeatureArchitectureTest.php
```

Then run project quality gates:

```powershell
vendor\\bin\\pint --test
$env:TEMP="$PWD\\.phpstan-tmp"; $env:TMP="$PWD\\.phpstan-tmp"; vendor\\bin\\phpstan analyse app tests routes database --debug --no-progress --memory-limit=1G
php artisan test
```

## 8. Expected Verification Evidence

Implementation is ready for completion when:

- admin category and nested subcategory routes work with a valid Feature 001
  access token
- missing or invalid token returns `401 UNAUTHENTICATED`
- inactive admin returns `403 USER_INACTIVE`
- missing permission returns `403 FORBIDDEN`
- root and nested resources match [contracts/openapi.yaml](./contracts/openapi.yaml)
- no public response exposes internal IDs, flags, counts, or both language columns
- public lists remain unpaginated and ordered by `sortOrder`, then `id`
- Admin Category and Subcategory indexes project only localized
  `name`, `description`, and `slug`
- Admin filters use `filter[search]`, `filter[isActive]`, and
  `filter[trashed]`
- Admin sorting uses the allow-listed Query Builder `sort` parameter
- `filter[search]` searches both Arabic and English stored content
- delete/restore rules respect child and parent dependencies
- localized descriptions persist only as a valid pair or as two `null`s
- public wrong-locale slug requests return `404`
- public responses include `Content-Language` and
  `Vary: Accept-Language`
- slug-bound public responses include valid `meta.localeLinks`
- OpenAPI rejects one-key-only description pairs on create and PATCH
- successful Category and Subcategory delete returns `200` with `data: null`
- reorder is atomic under MySQL concurrency
- Category delete and Subcategory create contend on the same root lock
- parent delete and Subcategory restore contend on the same parent lock
- all hierarchy locks use parent first, then children ordered by `id ASC`
- no accepted race outcome leaves a visible/non-deleted child beneath a deleted
  root
- Postman and OpenAPI stay synchronized with the implemented routes
- Pint, PHPStan/Larastan, and Pest pass

## 9. Manual Notes

- This feature must not add Service CRUD.
- This feature must not add a `subcategories` table.
- This feature allows only the existing Category and Subcategory create/update
  routes to accept one optional image. It must not add separate media routes,
  multiple-image handling, icons, videos, or attachments.
- This feature must not create public routes outside `/api/v1/public/*`.

## 10. Current Implementation Notes

- Focused suites for database, admin APIs, public APIs, architecture, and
  concurrency are implemented.
- Public `meta.localeLinks` now return alternate localized URLs for category
  and subcategory detail responses.
- Admin list filters use only `filter[search]`, `filter[isActive]`, and
  `filter[trashed]`; sorting uses the allow-listed `sort` parameter.
- Successful soft delete returns the shared `200 OK` envelope with `data: null`
  for both root Categories and Subcategories.
- `vendor/bin/pint --test` passed during final verification.
- `php artisan test` passed for the full repository during final verification.
- `vendor/bin/phpstan analyse app tests routes database --debug --no-progress
  --memory-limit=1G` passed after pinning a workspace-local temp directory in
  the current Windows execution environment.
- Generic `vendor/bin/phpstan list` and bare `vendor/bin/phpstan analyse`
  invocations remained silent in this shell, so the explicit-path command above
  is the documented repository-safe verification command.
