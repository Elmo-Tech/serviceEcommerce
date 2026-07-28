# Service Commerce Backend — Testing Standards

> **Scope:** Automated testing, API verification, quality gates, architecture
> checks, and critical regression coverage for the Laravel 13 Service Commerce
> Backend.
>
> **Primary Framework:** Pest
>
> **Primary Test Style:** API Feature Tests
>
> **Database:** Dedicated MySQL testing database
>
> **Status:** Project-wide mandatory standard.

---

## 1. Purpose

This document defines the mandatory testing standards for the Service Commerce
Backend.

The testing strategy is intentionally simple.

The project focuses mainly on:

```text
API Feature Tests
```

Additional test types are used only when they provide clear value:

```text
focused Unit Tests
Architecture Tests
Route Contract Tests
critical Database Tests
critical Security Regression Tests
critical MySQL Concurrency Tests
```

The goal is not to create the largest possible test suite.

The goal is to prove that:

- APIs behave according to contract
- business rules are enforced
- permissions are enforced
- authentication is secure
- pricing is backend-controlled
- order workflows are valid
- localization is stable
- file handling is safe
- critical concurrency rules remain correct
- architecture boundaries are not broken

---

## 2. Related Documents

Testing MUST comply with:

- `AGENTS.md`
- `.specify/memory/constitution.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- `docs/02-standards/api-standards.md`
- `docs/02-standards/code-standards.md`
- `docs/02-standards/database-standards.md`
- `docs/02-standards/localization-standards.md`
- `docs/02-standards/authentication-standards.md`
- `docs/02-standards/authorization-standards.md`
- `docs/02-standards/file-storage-standards.md`
- the active Feature specification
- the active `plan.md`
- the active `tasks.md`

When these documents conflict, implementation MUST stop until the conflict is
resolved.

Every Feature specification must define its required tests.

---

## 3. Final Testing Decisions

```text
Test framework: Pest
Primary focus: API Feature Tests
Testing database: Dedicated MySQL database
SQLite: Prohibited
Database reset: RefreshDatabase
Coverage percentage: No mandatory number initially
Business-rule coverage: Mandatory
CI: GitHub Actions
Code style gate: Laravel Pint
Static analysis gate: Larastan / PHPStan
Parallel testing: Optional initially
Test language: English
Test structure: Arrange, Act, Assert
Production data in tests: Prohibited
Snapshot testing: Avoided by default
Eloquent mocking in Feature Tests: Prohibited
Mail Jobs: Tested through Mail/Queue fakes where applicable
Other queue workflows: Not introduced by this standard
Concurrency tests: Critical cases only
```

---

## 4. Testing Philosophy

The project follows these principles:

1. Test behaviour, not implementation details.
2. Prefer API tests over controller unit tests.
3. Prove both success and important failure paths.
4. Use the real database behaviour where database behaviour matters.
5. Keep tests readable.
6. Avoid unnecessary abstraction inside tests.
7. Avoid duplicating the same assertion in many layers.
8. Do not test Laravel itself.
9. Do not mock Eloquent in Feature Tests.
10. Do not create tests only to increase a coverage percentage.
11. Every critical business rule must have an automated test.
12. Tests must be isolated and order-independent.

---

## 5. Test Types

The project uses a small number of clear test categories.

### 5.1 API Feature Tests

This is the main test type.

Use API Feature Tests for:

- authentication
- authorization
- validation
- API response structure
- CRUD operations
- business workflows
- pricing
- order creation
- question answers
- file uploads
- localization
- public catalogue behaviour
- administration behaviour
- database persistence
- route middleware behaviour

Most Features should be tested primarily through HTTP requests.

### 5.2 Focused Unit Tests

Use Unit Tests only for isolated pure logic.

Examples:

```text
money calculations
phone normalization
order-number formatting
duration calculations
enum helpers
validation-config parsing
localized content resolution
status transition rules
```

Do not create Unit Tests for:

- Controllers
- Eloquent CRUD
- simple model relationships already covered through Feature Tests
- Laravel validation behaviour itself
- trivial getters and setters

### 5.3 Architecture Tests

Use Pest Architecture Tests for simple architectural guarantees.

Examples:

- Controllers remain under approved namespaces.
- Models do not depend on HTTP classes.
- Form Requests extend the correct base class.
- Enums are backed enums where required.
- Actions follow approved naming.
- prohibited dependencies are absent.

Keep Architecture Tests focused.

Do not attempt to encode every coding standard as a fragile automated rule.

### 5.4 Route Contract Tests

Use Laravel's Route Collection to verify:

- approved route prefixes
- required middleware
- route names where applicable
- no forbidden routes
- permission middleware presence
- public and administration route separation

### 5.5 Critical Database Tests

Use database-focused tests for:

- unique constraints
- foreign keys
- soft-delete behaviour
- snapshot persistence
- aggregate recalculation
- transactional rollback
- MySQL-specific behaviour

Do not create a separate database test for every ordinary CRUD action when an
API Feature Test already proves it.

### 5.6 Security Regression Tests

Use focused regression tests for security-sensitive behaviour:

- token revocation
- refresh-token reuse
- permission denial
- nested-resource ownership
- price tampering
- path traversal
- unapproved file type
- hidden fields
- mass-assignment protection
- user enumeration
- raw path exposure
- customer data leakage

### 5.7 Critical Concurrency Tests

Use MySQL concurrency tests only for business-critical races.

Examples:

- same refresh token used twice
- order number collision
- two administrators pricing the same order item
- concurrent order status transition
- two service main images
- two service video uploads
- additional-image maximum limit

Do not add concurrency tests to ordinary CRUD.

---

## 6. Test Directory Structure

Recommended structure:

```text
tests/
  Pest.php
  TestCase.php

  Feature/
    Api/
      V1/
        Admin/
          Auth/
          Dashboard/
          Customers/
          Categories/
          Services/
          ServiceQuestions/
          Orders/
          ContactMessages/
          SiteSettings/
          HeroSections/
          Testimonials/
          Faqs/
          FeaturedServices/

        Public/
          Categories/
          Services/
          Orders/
          ContactMessages/
          SiteContent/

  Unit/
    Support/
    Pricing/
    Orders/
    Localization/

  Architecture/
    ArchitectureTest.php
    RouteContractTest.php

  Concurrency/
    Authentication/
    Orders/
    ServiceMedia/
```

The exact structure may evolve with implemented Features.

Do not create empty directories for unimplemented Features unless the repository
convention requires them.

---

## 7. Test Naming

Test files and test descriptions use English.

Good file names:

```text
LoginAdminTest.php
RefreshAdminSessionTest.php
CreateGuestOrderTest.php
PriceQuoteRequiredOrderItemTest.php
UpdateServiceQuestionTest.php
DownloadOrderAttachmentTest.php
```

Good test names:

```php
it('logs in an active administrator with valid credentials');
it('revokes the previous session when the administrator logs in again');
it('rejects an order when a required service question is missing');
it('returns 403 when the administrator lacks the required permission');
it('returns 404 when an order item does not belong to the route order');
```

Avoid:

```php
it('works');
it('test one');
it('can do stuff');
```

A test description should explain the behaviour being proved.

---

## 8. Arrange, Act, Assert

Tests follow:

```text
Arrange
Act
Assert
```

Example:

```php
it('rejects an unavailable service during guest order creation', function () {
    // Arrange
    $service = Service::factory()->unavailable()->create();

    // Act
    $response = $this->postJson('/api/v1/public/orders', [
        'items' => [
            [
                'serviceId' => $service->id,
                'quantity' => 1,
            ],
        ],
    ]);

    // Assert
    $response
        ->assertUnprocessable()
        ->assertJsonPath('code', 'SERVICE_UNAVAILABLE');
});
```

Rules:

- Keep one main behaviour per test.
- A test may contain multiple assertions for the same behaviour.
- Avoid testing many unrelated flows in one test.
- Use comments only when they improve clarity.
- Do not repeat comments when the structure is already obvious.

---

## 9. Testing Database

Tests use a dedicated MySQL database.

Example:

```env
APP_ENV=testing

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=service_commerce_testing
DB_USERNAME=testing_user
DB_PASSWORD=testing_password
```

Rules:

- Never point tests to development or production databases.
- Never use production credentials.
- Never use SQLite.
- CI creates or connects to a dedicated MySQL database.
- Local testing uses a clearly named testing database.
- The application should fail safely when testing configuration points to a
  suspicious database name.
- Tests may verify that the database name contains an approved testing marker
  before destructive migration commands run.

Recommended approved marker:

```text
_testing
```

---

## 10. Database Reset

API Feature Tests use:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
```

Recommended Pest setup:

```php
uses(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
```

Rules:

- Every test starts from a known state.
- Tests do not depend on records created by previous tests.
- Tests create only required data.
- Seed only the minimum required shared data.
- Feature permission Seeders may be run when authorization is under test.
- Do not run a large production-like Seeder for every test.
- Do not rely on auto-increment values unless the test explicitly needs them.

---

## 11. Factories

Use model factories for test data.

Required factory coverage grows with implemented Models.

Examples:

```text
UserFactory
CustomerFactory
CustomerAddressFactory
CategoryFactory
ServiceFactory
ServiceOptionGroupFactory
ServiceOptionValueFactory
ServiceOrderQuestionFactory
ServiceOrderQuestionOptionFactory
OrderFactory
OrderItemFactory
OrderItemAttachmentFactory
ContactMessageFactory
```

Factories should provide valid default records.

A default factory state should not create an invalid object unless the test
explicitly requests one.

---

## 12. Factory States

Use clear factory states for important business variations.

Examples:

```php
User::factory()->superAdmin();
User::factory()->inactive();

Category::factory()->root();
Category::factory()->subcategory();

Service::factory()->active();
Service::factory()->inactive();
Service::factory()->available();
Service::factory()->unavailable();
Service::factory()->fixedPrice();
Service::factory()->startingFrom();
Service::factory()->quoteRequired();

ServiceOrderQuestion::factory()->required();
ServiceOrderQuestion::factory()->optional();
ServiceOrderQuestion::factory()->inactive();

Order::factory()->pending();
Order::factory()->awaitingReview();
Order::factory()->completed();
Order::factory()->cancelled();
```

Rules:

- State names are explicit.
- Avoid generic states such as `special()` or `testMode()`.
- States set only data relevant to their meaning.
- A state should not create unrelated relationships unexpectedly.
- Reuse common states rather than repeating field arrays.

---

## 13. Seeders in Tests

Use Seeders only when the behaviour depends on shared system configuration.

Appropriate examples:

- Super Admin role
- implemented permissions
- required status registry
- required system configuration

Avoid:

- seeding hundreds of catalogue records
- seeding full demo data for one endpoint
- using production Seeder assumptions in every test

Rules:

- Seeders are idempotent.
- Tests should remain readable without hidden large data creation.
- Factories remain the primary tool for business records.
- Permission Seeders may be tested directly.

---

## 14. API Test Assertions

Every API test should verify the relevant parts of:

```text
HTTP status
success
message
code
data
errors
JSON structure
database state
side effects
```

Not every test must assert every field.

The assertions must match the behaviour being tested.

Example success:

```php
$response
    ->assertCreated()
    ->assertJsonPath('success', true)
    ->assertJsonPath('code', null)
    ->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'id',
        ],
    ]);
```

Example failure:

```php
$response
    ->assertForbidden()
    ->assertJsonPath('success', false)
    ->assertJsonPath('code', 'FORBIDDEN')
    ->assertJsonPath('errors', null);
```

---

## 15. Shared API Envelope

API tests should prove the shared response envelope remains stable.

Success shape:

```json
{
  "success": true,
  "message": "Localized message",
  "data": {}
}
```

Failure shape:

```json
{
  "success": false,
  "message": "Localized message",
  "code": "STABLE_ERROR_CODE",
  "errors": null
}
```

Validation shape:

```json
{
  "success": false,
  "message": "Localized validation message",
  "code": "VALIDATION_ERROR",
  "errors": {
    "fieldName": [
      "Localized field error"
    ]
  }
}
```

Rules:

- JSON keys remain stable.
- Error codes remain English.
- Messages follow locale.
- Tests should not compare every full response body.
- Use focused path and structure assertions.

---

## 16. Message Assertions

Do not compare localized message text in every business test.

Normal business tests should focus on:

```text
status
success
code
data
errors
database state
```

Compare exact messages only in focused localization tests.

This avoids duplicating every Feature test in Arabic and English.

---

## 17. Localization Tests

Every Feature with user-facing messages requires focused locale tests.

Required language coverage:

```text
ar
en
```

Test:

- `Accept-Language: ar`
- `Accept-Language: en`
- unsupported locale fallback
- message language
- `Content-Language` when required
- stable English error codes
- stable JSON keys
- stable enum values
- resolved public bilingual content

Do not run the entire Feature suite twice.

Recommended approach:

- business tests use the default test locale
- one or more focused tests verify Arabic
- one or more focused tests verify English
- one test verifies fallback

---

## 18. Authentication Testing

Authentication tests are mandatory.

Required login coverage:

- valid email and password
- normalized email
- incorrect password
- unknown email
- generic invalid-credentials response
- inactive account
- no email verification requirement
- rate limit of five attempts per minute
- successful login returns access token
- successful login sets an HttpOnly refresh cookie
- successful login does not return the refresh token in JSON
- access token is not set in a cookie
- `tokenExpiresIn` equals `900`
- `refreshTokenExpiresIn` equals `2592000`
- new login revokes previous access tokens
- new login revokes previous refresh tokens
- one active session remains

Required refresh coverage:

- valid refresh cookie
- required CSRF cookie and header
- allowed Origin
- access-token rotation
- refresh-cookie rotation
- refresh token remains absent from JSON
- old refresh token fails
- expired refresh token fails
- revoked refresh token fails
- malformed token fails
- inactive user fails
- refresh-token reuse is detected
- concurrent use allows only one success

Required logout coverage:

- protected route
- revokes all access tokens
- revokes all refresh tokens
- old access token fails
- old refresh token fails
- refresh and CSRF cookies are cleared

Required cookie-security coverage:

- refresh cookie is HttpOnly
- refresh cookie is Secure in production
- refresh cookie uses `SameSite=Strict`
- refresh cookie is host-only
- refresh cookie path is restricted to `/api/v1/admin/auth`
- CSRF cookie/header mismatch is rejected
- unapproved Origin is rejected
- wildcard production CORS is absent
- sensitive authentication responses use `Cache-Control: no-store`

Required password coverage:

- valid password change
- invalid current password
- weak new password
- confirmation mismatch
- all tokens revoked
- forgot-password code request
- six-digit code
- ten-minute expiry
- five verification attempts
- reset token creation
- reset token expiry
- password reset
- all tokens revoked after reset

---

## 19. Authorization Testing

Every protected route requires at least:

```text
authenticated with permission -> expected success
authenticated without permission -> 403 FORBIDDEN
unauthenticated -> 401 UNAUTHENTICATED
```

Nested resources additionally require:

```text
child belongs to another parent -> 404 NOT_FOUND
```

Examples:

- order item belongs to another order
- service question belongs to another service
- question choice belongs to another question
- attachment belongs to another order item
- option value belongs to another option group

Tests may create a temporary administrator without the permission.

The MVP may have only `super-admin`, but enforcement must still be proved.

---

## 20. Permission Seeder Tests

Test that:

- role is created
- permission is created
- Seeder is idempotent
- guard is correct
- permission is assigned to Super Admin
- existing permissions remain assigned
- partial Feature Seeder does not remove unrelated permissions
- permission cache is reset
- no wildcard permissions are introduced

Do not rely on the real Super Admin production password in tests.

---

## 21. Public API Tests

Public API tests should prove:

- no authentication required where approved
- inactive content is hidden
- unavailable content follows approved visibility rules
- hierarchy filters work
- localized content resolves
- pagination works
- sorting works
- invalid filters are rejected or ignored according to contract
- no administration fields leak
- no internal file paths leak
- no order attachment URL leaks
- guest order creation validates authoritative backend data

---

## 22. Catalogue API Tests

Categories:

- root category list
- subcategory list
- active visibility
- inactive parent hides child content
- two-level hierarchy
- no third level
- circular relationships rejected
- localized name and description
- category filtering

Services:

- list
- detail
- category filter
- subcategory filter
- active publication
- availability
- pricing type
- duration
- media
- specifications
- options
- service questions
- bilingual public content
- no raw storage paths
- no administration-only fields

---

## 23. Service Question Tests

Test:

- create question
- update question
- delete or deactivate question
- reorder questions
- required question
- optional question
- active question
- inactive question
- each supported input type
- bilingual labels
- bilingual help text
- bilingual placeholder
- choice ownership
- duplicate question key
- invalid validation configuration
- raw Laravel rule strings rejected
- public localized output
- required answer missing
- invalid answer type
- invalid question for service
- invalid choice for question
- inactive choice
- duplicate answers
- answer snapshots
- choice snapshots
- customer answers remain untranslated

---

## 24. Guest Order API Tests

Guest order creation requires broad API coverage.

Test:

- valid customer data
- normalized phone matching
- existing customer not overwritten
- new customer creation
- customer address creation
- multiple order items
- service quantities
- selected options
- selected option quantities
- required service questions
- optional answers
- selected question choices
- item-specific attachments
- fixed-price items
- starting-from items
- quote-required items
- mixed priced and quote-required order
- known subtotal
- incomplete final total
- order number
- snapshots
- initial status
- resolved locale

Failure tests:

- inactive service
- unavailable service
- inactive category
- inactive subcategory
- invalid service-subcategory relationship
- invalid option group
- invalid option value
- unavailable option value
- missing required answer
- invalid answer type
- invalid question ownership
- invalid choice ownership
- too many attachments
- invalid file type
- invalid file size
- frontend price tampering
- frontend total tampering
- unknown `clientReference`
- duplicate `clientReference`

---

## 25. Order Administration Tests

Test:

- list orders
- order detail
- filters
- pagination
- search
- localized response wrapper
- customer snapshot
- address snapshot
- service snapshot
- option snapshot
- question snapshot
- choice snapshot
- customer answer
- attachment metadata
- no raw attachment path

Pricing:

- permission required
- item belongs to order
- quote-required item
- valid amount
- invalid amount
- concurrent price update
- totals recalculated
- incomplete pricing state
- final total completion

Status:

- approved transition
- invalid transition
- terminal state
- cancellation
- cancellation reason
- rejection
- rejection reason
- permission separation
- concurrent transition conflict

---

## 26. File Upload Testing

Use:

```php
Storage::fake('public');
UploadedFile::fake();
```

Test:

- approved type
- rejected type
- size limit
- count limit
- generated filename
- original filename metadata
- public URL where allowed
- raw path hidden
- database metadata
- replacement
- synchronous old-file deletion
- rollback compensation
- cleanup failure logging
- no cleanup Job

Do not test real public symlinks in ordinary Feature Tests.

Deployment verification covers the actual storage link.

---

## 27. Administrator Avatar Tests

Test:

- jpg
- jpeg
- png
- webp
- maximum 2 MB
- invalid type
- generated filename
- profile URL
- raw path hidden
- replacement
- old file deletion
- database failure cleanup
- profile cannot update email
- profile cannot update roles
- profile cannot update permissions
- profile cannot update status

---

## 28. Service Media Tests

Images:

- one main image
- ten additional images
- eleventh rejected
- maximum 5 MB
- approved MIME types
- replacement
- reorder
- soft-deleted service keeps media
- public localized alt text

Video:

- one video
- second rejected unless replacing
- MP4
- WebM
- maximum 100 MB
- no transcoding
- no thumbnail
- no duration extraction

---

## 29. Order Attachment Tests

Test:

- approved formats
- maximum 10 MB
- five files per item
- sixth rejected
- item mapping
- generated filename
- original name metadata
- guest response hides URL
- administration metadata permission
- download permission
- foreign nested attachment returns 404
- cancellation retains attachment
- completion retains attachment
- no automatic deletion
- no cleanup Job

---

## 30. Mail Testing

The project uses queued email where approved.

Examples:

- forgot-password code email
- Contact Us reply email

Use:

```php
Mail::fake();
Queue::fake();
```

or the appropriate Laravel mail assertion for queued Mailables.

Test:

- Mailable is queued
- correct recipient
- correct locale
- required persisted state exists before dispatch
- code is not returned by API
- response does not claim delivery
- mail failure does not roll back already-approved persistence where the Feature
  requires persistence first

Queue testing in this standard applies to email delivery only.

Do not introduce queue tests for:

- file cleanup
- image optimisation
- video processing
- business-data caching
- speculative background work

---

## 31. Event and Notification Fakes

Use:

```php
Event::fake();
Notification::fake();
```

only when the Feature actually dispatches them.

Rules:

- Do not fake events globally when the tested workflow depends on listeners.
- Fake only the boundary under test.
- Assert specific events or notifications.
- Do not introduce events only to make tests easier.
- Do not test framework internals.

---

## 32. HTTP Integration Fakes

For future external integrations, use Laravel HTTP fakes.

Example:

```php
Http::fake();
```

Rules:

- Never call real external services in CI.
- Assert outgoing request structure where relevant.
- Test timeouts and failure responses.
- No external HTTP service is required for the current MVP authentication,
  orders, catalogue, or storage behaviour.
- Do not fake internal Laravel API calls.

---

## 33. Time Testing

Use:

```php
Carbon::setTestNow();
$this->travel();
$this->travelTo();
```

Test time-dependent behaviour:

- access-token expiry
- refresh-token expiry
- forgot-password code expiry
- reset-token expiry
- rate-limit windows
- order timestamps
- opportunistic authentication cleanup eligibility

Never use:

```php
sleep();
usleep();
```

inside tests.

Always restore time state after the test when the framework does not handle it
automatically.

---

## 34. Concurrency Testing

Concurrency tests use real MySQL behaviour.

Critical cases:

```text
same refresh token used twice
order-number collision
two administrators price the same order item
two status transitions on one order
two main-image uploads
two service-video uploads
additional-image limit race
```

Rules:

- Do not add concurrency tests for every CRUD endpoint.
- Keep concurrency tests in a dedicated folder.
- Document any process or database connection setup.
- Test the invariant, not timing assumptions.
- Avoid tests that pass only because one process is slow.
- Use locks, transactions, or parallel processes according to the Feature plan.
- CI must support the chosen concurrency test mechanism.

---

## 35. Query Performance Tests

Critical endpoints may include focused query-count checks.

Examples:

```text
public service list
public service detail
admin order list
admin order detail
dashboard summary
```

Test for:

- N+1 regression
- missing eager loading
- repeated translation queries
- repeated permission queries
- per-item attachment queries
- per-item question queries

Rules:

- No global fixed query limit for every endpoint.
- A critical endpoint may define an expected maximum.
- Query assertions should allow reasonable framework overhead.
- Performance tests must remain stable across supported environments.
- Use database query logging only within the test.

---

## 36. Architecture Tests

Recommended Pest Architecture Tests:

```php
arch('controllers do not depend on models directly where Actions are required')
    ->expect('App\Http\Controllers')
    ->not->toUse(['DB']);

arch('models do not depend on HTTP')
    ->expect('App\Models')
    ->not->toUse([
        'Illuminate\Http\Request',
        'Illuminate\Http\UploadedFile',
    ]);
```

Exact rules must match the approved architecture.

Good Architecture Test targets:

- Models do not depend on HTTP.
- Enums live in approved namespaces.
- Actions end with `Action`.
- Controllers end with `Controller`.
- Requests end with `Request`.
- Resources end with `Resource`.
- Policies end with `Policy`.
- prohibited debug helpers are absent from production namespaces.

Avoid fragile rules that block reasonable implementation without architectural
benefit.

---

## 37. Route Contract Tests

Inspect Laravel's Route Collection.

Verify:

- routes use `/api/v1`
- administrator authentication uses `/api/v1/admin/auth/*`
- no alternate top-level authentication route area
- no public register route
- no customer login route
- no user-management routes in initial MVP
- protected admin routes use `auth:sanctum`
- protected admin routes use active-user middleware
- Feature routes use permission middleware
- public routes do not accidentally require administrator permission
- sensitive order operations use dedicated permissions

Examples of required route permissions:

```text
orders.price
orders.change-status
orders.cancel
orders.reject
orders.attachments.view
orders.attachments.download
```

---

## 38. Mocking Standards

### 38.1 Do Not Mock Eloquent in Feature Tests

Prohibited:

```text
mocking Service::query()
mocking Order::find()
mocking model relationships
mocking database transactions
```

API Feature Tests use real factories and the MySQL testing database.

### 38.2 Allowed Fakes and Mocks

Allowed boundaries:

```text
Storage
Mail
Queue for mail only
Notifications
Events
external HTTP clients
time
```

Use mocks only when:

- the boundary is external
- the real dependency is slow or unavailable
- the test needs deterministic failure behaviour

Do not mock a class merely because it is convenient.

---

## 39. Snapshot Testing

Broad response snapshot testing is not used by default.

Avoid storing entire API responses as snapshots because:

- small valid changes create noisy failures
- localized text changes become expensive
- timestamps and IDs are unstable
- snapshots can hide unclear expectations

Prefer:

```php
assertJsonStructure()
assertJsonPath()
assertDatabaseHas()
assertDatabaseMissing()
assertModelExists()
assertModelMissing()
```

Snapshot testing may be used only when:

- the structure is large and stable
- the value is not sensitive
- the test remains readable
- the Feature plan justifies it

---

## 40. Production Data Prohibition

Tests MUST NOT use:

- production database dumps
- real customer records
- real customer files
- real customer emails
- real customer phone numbers
- production passwords
- production tokens
- production API keys
- real payment information
- exported production logs

Use:

```text
factories
fake files
reserved example domains
synthetic phone numbers
synthetic addresses
```

Recommended email domain:

```text
example.com
```

Do not copy the real Super Admin secret into tests.

---

## 41. Secrets in Tests

Testing secrets must be fake and isolated.

Rules:

- `.env.testing` is not production configuration.
- CI secrets are limited to required infrastructure credentials.
- Tokens generated by tests are temporary.
- Test output must not print raw passwords or tokens.
- Failed assertions should not expose authorization headers.
- Synchronous mail assertions must not expose forgot-password codes in test
  output.
- Never commit production credentials to fixtures.

---

## 42. Coverage Policy

The project does not enforce a numeric coverage threshold initially.

Required instead:

- every critical business rule has a test
- every protected command has permission coverage
- every critical failure path has a test
- every stable error code has at least one contract test where relevant
- every security-sensitive workflow has regression coverage
- every critical snapshot rule has a test
- every critical concurrency rule has a test

Coverage reports may still be generated for visibility.

Low-value tests must not be added only to increase a percentage.

---

## 43. CI Quality Gates

GitHub Actions runs on:

```text
push
pull_request
```

Required gates:

```text
dependency installation
application configuration
MySQL service readiness
migrations
Pest
Laravel Pint
Larastan / PHPStan
```

A failed required gate blocks merge.

Recommended command direction:

```bash
composer install --no-interaction --prefer-dist

php artisan migrate:fresh --env=testing

vendor/bin/pint --test

vendor/bin/phpstan analyse

php artisan test
```

Exact commands follow repository scripts when defined.

---

## 44. GitHub Actions MySQL

CI should provide a MySQL service.

The workflow should:

1. start MySQL
2. wait until healthy
3. create or connect to the testing database
4. copy testing environment configuration
5. generate application key
6. run migrations
7. run quality gates

Rules:

- Use supported MySQL version aligned with production.
- Do not use SQLite as a CI shortcut.
- Use test-only credentials.
- Cache dependencies safely.
- Do not cache database state between runs.
- Do not run tests against a shared persistent database.

---

## 45. Laravel Pint

Laravel Pint is a mandatory quality gate.

Run:

```bash
vendor/bin/pint --test
```

Rules:

- CI checks formatting.
- Developers may run `vendor/bin/pint` locally to fix issues.
- Generated code must follow repository formatting.
- A formatting failure blocks merge.
- Do not mix competing formatters without approval.

---

## 46. Larastan / PHPStan

Larastan is a mandatory quality gate.

Use a practical initial strictness level.

The exact level belongs in:

```text
phpstan.neon
```

Rules:

- Start at a level the repository can maintain.
- Increase gradually.
- Do not suppress broad directories.
- Ignore errors only with a documented reason.
- Fix real type issues instead of adding unnecessary ignores.
- Generated framework behaviour may use targeted extensions or stubs.
- Static analysis does not replace runtime tests.

---

## 47. Parallel Testing

Parallel testing is optional initially.

Possible command:

```bash
php artisan test --parallel
```

Do not make it mandatory until:

- testing databases are isolated correctly
- file fakes are isolated
- rate-limit storage is isolated
- token tests are isolated
- concurrency tests are separated
- test suite is stable

Normal CI may run tests sequentially at first.

A future performance improvement may enable parallel mode.

---

## 48. Test Isolation

Every test must be independent.

Prohibited assumptions:

- another test already created a role
- another test already seeded a category
- another test already stored a file
- tests run alphabetically
- tests run in one process
- IDs begin at a specific number
- current time is unchanged
- cache state remains from a previous test

Rules:

- create required data in the test or setup
- clear fakes and time state
- use `RefreshDatabase`
- reset permission cache where required
- avoid static mutable state
- avoid shared external resources

---

## 49. Test Order

Tests must pass:

```text
individually
as a directory
as the full suite
in a different order
```

A test that passes only after another test is invalid.

When debugging order dependency:

- run the failing test alone
- randomize order where supported
- inspect static state
- inspect cache
- inspect config mutation
- inspect time freezing
- inspect filesystem state

---

## 50. Test Helpers

Small reusable helpers are allowed.

Examples:

```text
authenticateSuperAdmin()
createAdminWithoutPermission()
assertApiSuccess()
assertApiError()
seedFeaturePermissions()
```

Rules:

- Helpers remain explicit.
- Helpers do not hide the main business setup.
- Helpers do not perform unrelated assertions.
- Helpers do not create large hidden object graphs.
- Feature-specific helpers live near the Feature tests when practical.

---

## 51. Custom Assertions

Custom assertions may be added for stable shared contracts.

Examples:

```php
$response->assertApiSuccess();
$response->assertApiError('FORBIDDEN');
$response->assertValidationErrorFor('email');
```

Rules:

- Keep custom assertions small.
- Error messages must remain useful.
- Do not hide important expected values.
- Do not replace clear built-in assertions unnecessarily.

---

## 52. Error-Code Tests

Stable error codes require contract coverage.

Examples:

```text
INVALID_CREDENTIALS
USER_INACTIVE
REFRESH_TOKEN_INVALID
FORBIDDEN
SERVICE_UNAVAILABLE
REQUIRED_SERVICE_ANSWER_MISSING
ORDER_PRICING_INCOMPLETE
FILE_TYPE_NOT_ALLOWED
```

Rules:

- Test the code, not only the message.
- Code remains unchanged across locales.
- HTTP status and code must match the API standard.
- Avoid asserting every possible validation message in business tests.
- Focused tests may verify key validation fields.

---

## 53. Pagination Tests

List endpoints should test:

- default pagination
- approved page size
- maximum page size
- page navigation
- empty page
- metadata structure
- filter interaction
- sorting interaction
- no duplicate records
- stable ordering where required

Do not assert full large response arrays unnecessarily.

---

## 54. Filtering and Sorting Tests

Test approved filters only.

Examples:

```text
category
subcategory
status
pricing type
availability
order status
pricing status
date range
search
```

Test:

- valid filter
- invalid filter
- combined filters
- sort direction
- unknown sort field
- locale-aware search where approved
- no unauthorized fields used for filtering

The exact invalid-filter behaviour follows the API standard.

---

## 55. Search Tests

Search tests should cover:

- exact match
- partial match
- case behaviour
- Arabic content
- English content
- no result
- pagination
- relevant indexed fields
- safe input

Do not add database-specific full-text assumptions unless explicitly designed.

---

## 56. Transaction Tests

Critical transactional workflows require rollback tests.

Examples:

- guest order creation
- token refresh rotation
- password reset
- service creation with media
- service media replacement
- order pricing
- status transition
- Contact Us reply persistence

Test:

- success commits all records
- failure rolls back database changes
- files created before failure are removed
- no partial snapshots remain
- no duplicate active tokens remain
- no invalid totals remain

---

## 57. Snapshot Persistence Tests

Historical order meaning requires tests for:

- customer name snapshot
- customer phone snapshot
- address snapshot
- service Arabic and English names
- category and subcategory names
- pricing type
- unit price
- selected options
- selected option labels
- question keys
- Arabic and English question labels
- question input type
- required state
- selected choice labels
- customer answers
- duration
- resolved locale

Update catalogue records after order creation and prove snapshots remain
unchanged.

---

## 58. Database Constraint Tests

Use focused tests for:

- unique normalized email
- unique normalized phone where approved
- category parent rules
- service subcategory foreign key
- question key unique within service
- option key unique within question
- refresh token hash unique
- one main image invariant
- one service video invariant
- order number unique
- foreign-key restrictions
- cascade rules
- soft-delete behaviour

Do not duplicate every migration line in tests.

Test constraints that protect important business invariants.

---

## 59. Soft Delete Tests

For soft-deleted resources, test:

- excluded from normal administration list where approved
- excluded from public API
- cannot be ordered
- restore behaviour
- delete permission
- restore permission
- historical snapshots remain
- media retention
- related order records remain intact

Do not assume all tables use soft deletes.

Test only approved resources.

---

## 60. Rate-Limit Tests

Test rate limiting for:

- login
- forgot password
- Contact Us when approved
- guest order submission when approved

Test:

- allowed attempts
- blocked attempt
- `429`
- stable error code
- localized message
- reset after time window
- success clearing relevant login attempts
- key isolation between emails or IPs where required

Use time travel instead of waiting.

---

## 61. Cache Behaviour in Tests

The MVP does not use application business-data caching.

Spatie permission cache is allowed.

Rules:

- clear permission cache after permission mutations
- do not depend on previous test cache
- do not add Redis only for tests
- rate-limit storage uses the configured testing driver
- tests should not cache API responses as authoritative state

---

## 62. Logging Tests

Test logging only for important security or cleanup failures.

Examples:

- refresh-token reuse
- failed old-file deletion
- inactive-user access
- unexpected storage failure

Rules:

- do not assert every ordinary log line
- never expose secret content
- test safe context where important
- do not create fragile tests tied to exact log formatting

---

## 63. Exception Tests

Test that expected exceptions become approved API responses.

Examples:

- authentication exception -> `401`
- authorization exception -> `403`
- model not found -> `404`
- validation exception -> `422`
- business conflict -> `409`
- rate limit -> `429`
- unexpected exception -> safe `500`

The API must not expose:

```text
stack trace
SQL
absolute file path
token value
password
internal exception message
```

---

## 64. Test Data Volume

Most tests should use the smallest useful data set.

Examples:

- one administrator
- one category and subcategory
- one service
- one order
- two order items when multi-item behaviour is under test

Use larger data sets only for:

- pagination
- query-count regression
- aggregate behaviour
- search
- performance-sensitive listing

Do not create thousands of records in normal API tests.

---

## 65. Test Performance

Keep the suite practical.

Improve performance by:

- focused data setup
- avoiding unnecessary Seeders
- using factories carefully
- avoiding external calls
- using fakes at external boundaries
- avoiding large fixtures
- keeping concurrency tests separate

Do not sacrifice database correctness by switching to SQLite.

---

## 66. Local Development Commands

Recommended commands:

```bash
php artisan test

php artisan test tests/Feature/Api/V1/Admin/Auth

php artisan test --filter="logs in an active administrator"

vendor/bin/pint --test

vendor/bin/phpstan analyse
```

Optional:

```bash
php artisan test --parallel
```

Developers should run the relevant Feature tests before the full suite.

---

## 67. Pull Request Requirements

A pull request that changes behaviour must include tests.

Required updates may include:

- new success test
- new validation test
- new permission test
- new failure test
- new localization test
- new regression test
- updated route contract test
- updated architecture test

A pull request must not rely on manual testing alone for critical backend
behaviour.

---

## 68. Bug Fix Requirements

Every confirmed backend bug should receive a regression test when practical.

Process:

1. reproduce the bug with a failing test
2. implement the fix
3. prove the test passes
4. run related Feature tests
5. run full quality gates when required

Do not add a regression test that only duplicates the implementation.

---

## 69. CI Failure Policy

The following block merge when mandatory:

```text
Pest failure
Pint failure
Larastan failure
migration failure
application boot failure
route contract failure
architecture test failure
```

Do not merge with:

```text
temporarily skipped critical test
commented-out assertion
ignored static-analysis error without reason
disabled permission middleware
```

A flaky test must be fixed, not repeatedly retried without investigation.

---

## 70. Skipped Tests

Skipping tests requires a documented reason.

Allowed temporary reasons:

- external dependency not available in CI
- known upstream framework issue
- approved incomplete migration

Rules:

- use a visible issue or task reference
- do not skip critical security tests
- do not skip to make CI green
- remove skips promptly
- skipped tests are reviewed in pull requests

---

## 71. Flaky Tests

Flaky tests are defects.

Common causes:

- uncontrolled time
- shared state
- random data collision
- cache leakage
- race assumptions
- real external calls
- filesystem leakage
- incorrect database isolation

Fix the cause.

Do not add arbitrary sleeps.

Do not rely on repeated CI execution.

---

## 72. Test Documentation

Each Feature specification should include:

- main API scenarios
- permissions
- important validation failures
- business-rule failures
- localization coverage
- file behaviour
- concurrency needs
- regression risks

The implementation plan should identify:

```text
Feature tests
Unit tests when needed
Architecture or route tests
Concurrency tests when critical
```

---

## 73. Code Review Checklist

- [ ] Test is primarily API-level where appropriate.
- [ ] Dedicated MySQL testing database is used.
- [ ] SQLite is not introduced.
- [ ] `RefreshDatabase` is used.
- [ ] Test name is clear and English.
- [ ] Arrange, Act, Assert structure is readable.
- [ ] Test proves one main behaviour.
- [ ] Factories or clear setup are used.
- [ ] Production data is absent.
- [ ] API status is asserted.
- [ ] Stable error code is asserted for failures.
- [ ] Database side effect is asserted.
- [ ] Permission success and failure are covered.
- [ ] Nested ownership is covered where applicable.
- [ ] Locale is tested in focused tests.
- [ ] No exact message assertion is duplicated unnecessarily.
- [ ] Eloquent is not mocked in Feature Tests.
- [ ] Storage is faked where appropriate.
- [ ] Queue fake is used only for queued mail behaviour.
- [ ] No `sleep()` exists.
- [ ] Critical time behaviour uses time travel.
- [ ] Snapshot testing is not used without reason.
- [ ] Test is order-independent.
- [ ] Test passes alone.
- [ ] Test passes in the full suite.
- [ ] Pint passes.
- [ ] Larastan passes.

---

## 74. Definition of Done

Testing work is complete only when:

- API success path is covered
- important validation failures are covered
- important business-rule failures are covered
- authorization success and denial are covered
- unauthenticated access is covered
- nested-resource ownership is covered
- database state is asserted
- stable error codes are asserted
- localization has focused Arabic and English coverage
- file side effects are covered where relevant
- mail queue behaviour is covered where relevant
- critical concurrency is covered where required
- architecture or route contracts are updated where relevant
- tests pass on MySQL
- Pint passes
- Larastan passes
- CI passes
- no production data or secrets exist in tests

---

## 75. Non-Negotiable Rules

- Use Pest.
- Focus primarily on API Feature Tests.
- Use a dedicated MySQL testing database.
- Do not use SQLite.
- Use `RefreshDatabase`.
- Do not require a numeric coverage percentage initially.
- Test every critical business rule.
- Test important success and failure paths.
- Test authentication thoroughly.
- Test authorization on every protected route.
- Test `401`, `403`, and nested `404` behaviour.
- Use factories and factory states.
- Test names are English.
- Use Arrange, Act, Assert.
- Keep tests isolated.
- Keep tests order-independent.
- Do not mock Eloquent in Feature Tests.
- Use real MySQL for database behaviour.
- Use real MySQL concurrency tests only for critical cases.
- Use `Storage::fake()` for upload tests.
- Use `UploadedFile::fake()` for upload tests.
- Use `Mail::fake()` for mail tests.
- Use `Queue::fake()` only for queued email behaviour in the current MVP.
- Use time travel instead of sleeping.
- Do not call real external services.
- Avoid broad response snapshots.
- Do not use production data.
- Do not use production secrets.
- Run Pest in CI.
- Run Pint in CI.
- Run Larastan in CI.
- Failed mandatory quality gates block merge.
- Add a regression test for important bug fixes.
- Do not disable a critical test to make CI pass.
