# Quickstart: Validate Hero Slider Management

This guide validates Feature 008 after implementation. It does not replace the
full automated suite or define implementation code.

## 1. Prerequisites

- Current branch: `008-hero-slider-management`.
- PHP and dependencies available from a manifest/lock state that has first
  been reconciled and approved by the repository owner.
- Dedicated MySQL testing database configured; never point tests at production.
- Public filesystem disk configured for local/manual API verification.
- Database queue, Redis, scheduler, and frontend applications are not required.

Before dependency operations, run and record:

```powershell
php -v
composer validate --no-check-publish
composer check-platform-reqs
```

The Feature design gate may pass while the repository dependency gate remains
an external blocker. Verify the current `composer.json` and `composer.lock`
are consistent. Feature 008 itself must not resolve that
pre-existing repository-wide dependency discrepancy through an unscoped
Composer update. The declared Laravel 12/PHP 8.2 target and current Laravel
13/PHP 8.3 lock/vendor state must be reconciled separately before release.

## 2. Confirm artifacts and routes

```powershell
php artisan route:list --path=api/v1/admin/hero-slides
php artisan route:list --path=api/v1/public/hero-slides
```

Expected:

- Five Admin routes with exact `hero-slides.view/create/update/delete`
  permissions.
- One unauthenticated public GET.
- No reorder route.

Validate the implemented contract against
[contracts/openapi.yaml](contracts/openapi.yaml) and the Postman collection.

## 3. Run the targeted suite

```powershell
php artisan test --testsuite=Feature --filter=HeroSlide
php artisan test tests/Architecture/HeroSlidesFeatureArchitectureTest.php
php artisan test tests/Architecture/HeroSlidesOpenApiAndPostmanContractTest.php
php artisan test tests/Unit/Http/StrictMultipartPatchParserTest.php
php artisan test tests/Feature/Http/HeroSlideMultipartPatchTransportTest.php
```

Expected: create/read/update/delete, exact request shapes, permissions,
localization, image safety, envelopes, OpenAPI, and Postman assertions pass.

## 4. Run critical MySQL concurrency tests

```powershell
php artisan test tests/Concurrency/HeroSlides/HeroSlideCriticalConcurrencyTest.php
```

Expected after each synchronized race:

- total rows never exceed 10;
- positions are empty or exactly 1..N;
- unique position is never violated in committed state;
- competing moves do not create lost updates;
- create/delete races leave database/image relationships consistent;
- retryable deadlocks are retried only within the approved bound;
- exhausted retries return a safe outcome and leak no new file.

These tests must use MySQL/InnoDB. SQLite results are not sufficient evidence.

## 5. Manual Admin smoke flow

Use the Feature 008 Postman requests:

1. Create three slides, omitting position for append.
2. Create another slide at position 2 and verify positions 1..4.
3. List with `filter[isActive]=0` and `filter[isActive]=1`.
4. Update one localized field without sending image and verify preservation.
5. Replace the image and verify an absolute URL is returned.
6. Move the last slide to position 1 and verify contiguous order.
7. Delete position 2 and verify status 200, `data: null`, and positions 1..N.
8. Fill the set to 10 including inactive rows; verify the eleventh create is
   rejected and deletion frees one slot.
9. Submit an empty PATCH and an unknown-only PATCH; verify localized 422 and no
   row/file change.
10. Verify `isActive="0"` is valid while `01`, `+1`, `1.0`, `true`, arrays,
    duplicates, and whitespace-padded values are rejected.
11. Verify canonical `position="1"` is valid while `01`, `+1`, `1.0`, arrays,
    duplicates, and whitespace-padded values are rejected.
12. Upload an exact 5 MiB static image, then a 5 MiB-plus-one-byte file;
    verify the first is accepted and the second rejected.
13. Verify animated WebP, APNG, and SVG are rejected.
14. Send repeated `page`, `perPage`, and `filter[isActive]` query members and
    verify raw-query guarding rejects them.
15. Simulate post-commit old-file cleanup failure and verify the API remains
    successful, the database state remains committed, and safe logging occurs.

The image-replacement request must be sent as a real HTTP
`PATCH multipart/form-data` request through the running PHP 8.2/8.3 server,
not only through Laravel's in-process test client. Verify valid text-only and
image-replacement PATCH requests, empty PATCH rejection, duplicate scalar/file
parts, unknown parts, malformed/missing boundaries, header injection,
oversized raw bodies, exact 5 MiB and 5 MiB-plus-one-byte behavior, downstream
exception cleanup, and that unauthorized requests are rejected before parsing.
No temporary upload may remain.

## 6. Manual public smoke flow

```http
GET /api/v1/public/hero-slides
Accept-Language: ar-EG
```

Repeat with `en-US`. Verify:

- only active slides;
- ascending display order;
- exactly `title`, `description`, and `image` per item;
- Arabic/English projected values respectively;
- `Content-Language` and `Vary: Accept-Language` headers;
- no ID, activity, position, dual-language key, timestamp, or raw path;
- `data: []` when no active slide exists.

## 7. Quality gates

```powershell
php artisan test
vendor\bin\pint --test
vendor\bin\phpstan analyse
```

Expected: full regression, formatting, and configured static analysis pass.

## 8. Acceptance evidence

Feature 008 is ready only when:

- every checklist item in [requirements.md](checklists/requirements.md) remains
  satisfied;
- all routes and response schemas match the OpenAPI/Postman contracts;
- storage compensation and raw-path non-disclosure are proven;
- real MySQL concurrency tests prove count and ordering invariants;
- no out-of-scope reorder, button, slider setting, soft delete, cache, queue,
  video, scheduling, analytics, or frontend code exists.

## 9. Printing Demo Slides

The default database seed flow runs `PrintingHeroSlidesSeeder`. It creates five
active bilingual slides in contiguous positions, downloads five distinct
printing-themed landscape images cropped to `1600x600` from Pexels, validates
their actual PNG/JPEG/WebP content, and stores them under
`hero-slides/seed/pexels-printing-slider-v2` on the public disk.

```powershell
php artisan db:seed --class=PrintingHeroSlidesSeeder
```

The Seeder is idempotent, upgrades older Seeder-managed slide images to the
current image set, and preserves a slide image that an administrator has
replaced through the existing update API.
