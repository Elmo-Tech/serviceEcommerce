# Runtime Verification: Hero Slider Management

**Verified**: 2026-08-03  
**Scope**: Setup tasks T003 and T004 only. No dependency, production-code, or
task-status changes were made.

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
