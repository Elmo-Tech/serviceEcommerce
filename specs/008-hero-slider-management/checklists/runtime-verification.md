# Runtime Verification: Hero Slider Management

**Verified**: 2026-08-03  
**Scope**: Setup verification plus implementation/release evidence.

## T001, T002, and T005 — Repository, dependency, and contract audit

- [x] Existing Admin/Public middleware, response envelopes, Resources, Form
  Requests, permission Seeders, and locale boundaries were inspected and reused.
- [x] PHP is `8.3.29`. `composer check-platform-reqs` passes. `composer validate
  --no-check-publish` reports that the manifest's Laravel 12/PHP 8.2-compatible
  constraints do not match the installed/locked Laravel 13, Permission 8,
  Query Builder 7, and Pest 4 packages. No Composer update was run.
- [x] The Postman collection retains exactly `baseUrl`, `accessToken`, and
  `refreshToken`; protected Hero requests use `Bearer {{accessToken}}`.

## T003 — MySQL and test-support capabilities

- [x] **Dedicated test database is configured.** `phpunit.xml` forces
  `DB_CONNECTION=mysql` and `DB_DATABASE=service_commerce_test`; the matching
  `.env.testing.example` uses the same dedicated database. `.env.example`
  remains correctly aimed at the non-test `service_commerce` database.
- [x] **The configured test database is reachable and uses the required
  engine.** A read-only PDO runtime probe connected to
  `service_commerce_test` and returned MariaDB `10.4.28-MariaDB`, version
  comment `mariadb.org binary distribution`, and default storage engine
  `InnoDB`.
- [x] **Connection-scoped advisory locks are supported.** On one PDO
  connection, `GET_LOCK('feature008_runtime_probe', 0)` returned `1`, and the
  subsequent `RELEASE_LOCK('feature008_runtime_probe')` returned `1`.
  The observed connection ID was `8`. This proves the database capability;
  Feature 008 still needs tests that acquisition, mutation, and release use
  the same selected Laravel connection.
- [x] **Pest discovers concurrency tests.** `tests/Pest.php` binds the Laravel
  test case to `Concurrency` in addition to Feature, Architecture, and Unit.
- [x] **Existing process-concurrency helpers are usable as a pattern.** The
  repository has feature-specific runners under `tests/Support/` and matching
  multi-process suites under `tests/Concurrency/`. For example,
  `SettingsConcurrencyTest.php` starts two Symfony `Process` instances using
  `PHP_BINARY`, a runner script, a 20-second timeout, and JSON results;
  `SettingsConcurrencyRunner.php` boots the real Laravel application. Similar
  runners exist for categories, customers, services, orders, and auth.
  Installed `symfony/process` is `v7.4.13`.
- [x] **Filesystem fakes are available and already exercised.** Existing tests
  call `Storage::fake('public')` and `UploadedFile::fake()` for settings,
  services, categories, authentication, and order attachments. The test
  runtime selects the `public` disk, whose configured local root is
  `storage/app/public`.
- [x] **Logging spies are available and already exercised.** Existing tests
  use `Log::spy()` plus `Log::shouldHaveReceived(...)`, including the settings
  post-commit cleanup-failure scenario and authentication security logging.
  The normal configured stack resolves to the single-file channel, while
  tests can safely replace the facade with a spy.

## T004 — Direct multipart PATCH limitation and body bounds

- [x] **Installed source confirms the PHP version gate.** Installed
  `symfony/http-foundation` is `v7.4.14`. In
  `vendor/symfony/http-foundation/Request.php:286-311`,
  `Request::createFromGlobals()` handles PUT/DELETE/PATCH/QUERY specially.
  For `PHP_VERSION_ID < 80400`, it only parses
  `application/x-www-form-urlencoded` with `parse_str`; for other content
  types it reuses native `$_POST` and `$_FILES`. The native
  `request_parse_body()` path is entered only on PHP 8.4 or newer.
- [x] **A minimal real-server probe reproduces the limitation on the installed
  runtime.** PHP CLI/server version was `8.3.29`. A temporary PHP built-in
  server received a real curl `PATCH multipart/form-data` request containing
  scalar `titleEn` and an uploaded `image`. The probe reported all four bags
  empty: native `$_POST`, native `$_FILES`, Symfony request parameters, and
  Symfony files. This was a real socket request, not Laravel's in-process test
  client. Temporary probe files were removed and no listener remained.
- [x] **Current PHP upload limits do not implement the feature contract.** The
  loaded `C:\xampp\php\php.ini` reports `post_max_size=40M`,
  `upload_max_filesize=40M`, and `max_file_uploads=20`. These broad global
  limits cannot enforce Feature 008's one-file, exact 5 MiB
  (`5,242,880` bytes) requirement.
- [x] **The required parser bound is documented but still implementation
  specific.** `plan.md:302-308` and `research.md:182-190` require the scoped
  Hero PATCH parser to cap the raw body at one exact 5 MiB file plus a small,
  fixed multipart overhead, allow one file and approved scalar parts only,
  retain duplicate detection, and always clean its managed temporary upload.
  The approved artifacts do not assign a numeric byte value to that overhead;
  implementation must define one explicit bounded constant and prove exact
  5 MiB versus 5 MiB-plus-one file behavior without relying on the 40 MiB PHP
  settings.

## Commands and observations

```text
php -v
=> PHP 8.3.29 (cli), ZTS, Windows x64

composer show symfony/http-foundation --locked
=> v7.4.14

composer show symfony/process --locked
=> v7.4.13

PDO capability query
=> database=service_commerce_test
=> version=10.4.28-MariaDB
=> default_storage_engine=InnoDB
=> GET_LOCK(..., 0)=1
=> RELEASE_LOCK(...)=1

real HTTP PATCH multipart probe
=> nativePostKeys=[]
=> nativeFileKeys=[]
=> symfonyRequestKeys=[]
=> symfonyFileKeys=[]
```

The first `php artisan db:show --database=mysql --counts` diagnostic exceeded
its 30-second command budget while counting database objects; the bounded PDO
probe above then verified the required server, engine, database, and advisory
lock capabilities directly without schema or data mutation.

## Feature implementation verification

- [x] A raw multipart PATCH was passed through Laravel's HTTP kernel with scalar
  fields and a PNG file. The parser delivered both and the update committed.
- [x] An unauthenticated malformed multipart PATCH returned 401 before parser
  execution, proving the permission/parser middleware order.
- [x] The focused Feature 008 suite passed: 24 tests and 162 assertions.
- [x] PHPStan passed with zero errors and `vendor/bin/pint --test` passed.
- [x] Final full regression passed: 368 tests and 2,447 assertions in 155.70
  seconds.

## Release blocker

Composer manifest/lock drift remains. Platform requirements pass, but release
verification is blocked until the repository owner intentionally reconciles the
Laravel 12/PHP 8.2 manifest with the Laravel 13/PHP 8.3 lock/vendor state.

## T061 — Real PHP 8.3 HTTP-server smoke

- [x] A real PHP `8.3.29` built-in server was started on `127.0.0.1:8765`
  against the dedicated `service_commerce_test` database.
- [x] Authenticated direct `PATCH multipart/form-data` with text and PNG image
  returned 200 and committed the replacement.
- [x] Repeated `titleEn` multipart parts returned 422.
- [x] A 5 MiB-plus-one image returned 422. This probe exposed and fixed an
  initial `php://input` temporary-spooling warning that had produced 500; the
  middleware now converts unreadable raw content into the stable validation
  outcome.
- [x] Temporary parser files were 0 before and 0 after all requests.
- [x] The server was terminated, the exact two probe files were removed, the
  dedicated test database was migrated fresh, and the probe storage directory
  was removed.

## T062 — Quickstart evidence

- [x] Admin append/insertion/filter/show/partial update/image replacement/move/
  delete/limit/empty-and-unknown validation scenarios are covered by the
  focused Admin suites.
- [x] Lexical activity/position, exact 5 MiB, plus-one, MIME/content, animated
  image, raw-query duplicate, cleanup logging, and raw multipart edge cases are
  covered by focused API, Unit, Database, and HTTP transport suites.
- [x] Arabic/English active-only public projection, exact keys/headers,
  ordering, ten-row bound, and empty state are covered by the public suite.
- [x] Real MySQL process races cover empty/final-slot creates, competing moves,
  create/delete, replacement/delete, retry success, and retry exhaustion.

## T069 — Acceptance and scope audit

- [x] The reference, spec, plan, requirements checklist, routes, schema,
  OpenAPI, Postman, and implementation were compared. The implementation has
  one `hero_slides` table, five Admin routes, one public route, and exactly four
  permissions.
- [x] No reorder route/permission, frontend code, soft delete, settings table,
  cache, queue, video, scheduling, or analytics implementation was introduced.
