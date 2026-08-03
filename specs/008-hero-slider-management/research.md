# Research: Hero Slider Management

## Decision 1: Use one Hero Slide table and model

- **Decision**: Persist all slide content, image path, state, and position in
  `hero_slides`; do not introduce a Hero singleton, media table, settings table,
  or soft-delete column.
- **Rationale**: One slide owns exactly one image and no child collections. The
  approved reference explicitly requires one table and hard deletion.
- **Alternatives considered**:
  - Separate Hero configuration and media tables: rejected as unapproved
    complexity.
  - Reuse general settings: rejected because this is an ordered CRUD resource
    with independent permissions and lifecycle.

## Decision 2: Separate orchestration from focused capabilities

- **Decision**: Use Create, Update, and Delete Actions; one ordering Service;
  one image Service; one Admin list Query; thin Admin/Public Controllers and
  separate Admin/Public Resources.
- **Rationale**: Mutations combine transactions, multiple row shifts, and file
  compensation, which justifies Actions. Ordering and image handling are
  cohesive reusable/testable capabilities. Public and Admin disclosure differs.
- **Alternatives considered**:
  - Put all logic in one controller: rejected by architecture and testability.
  - Create a generic repository layer: rejected as unnecessary indirection.
  - One Action per read: rejected because reads require no orchestration.

## Decision 3: Combine a database advisory mutex with ordered row locks

- **Decision**: Select one mutation connection, acquire one deterministic
  application/database-scoped MySQL/MariaDB named advisory lock with a bounded
  timeout before opening the transaction, execute the mutation transaction on
  the same connection, lock every current row by position and ID, and release
  the named lock in `finally` after commit or rollback.
- **Rationale**: Row locks alone do not exist when the set is empty. Depending
  only on isolation-specific gap-lock behavior is fragile across supported
  MySQL/MariaDB environments. The named mutex serializes the global set even
  for the first create; row locks retain transactional current-state protection.
- **Alternatives considered**:
  - Unlocked count plus insert: rejected because two requests can exceed 10.
  - Lock only existing IDs: rejected for the empty-set and final-slot races.
  - Depend only on a `position >= 1 FOR UPDATE` gap lock: rejected because its
    empty-range behavior depends on engine/isolation details.
  - Add a lock table/singleton row: rejected because the approved design says
    no additional Hero table.
  - Cache/distributed locks: rejected because cache/Redis are outside scope.

## Decision 4: Rewrite positions through a temporary non-overlapping band

- **Decision**: After the advisory and row locks, map current positions temporarily into
  100..110, apply the desired list operation, then assign exact final positions
  1..N.
- **Rationale**: Directly incrementing/decrementing unique indexed positions can
  collide transiently in MySQL even when the intended final set is unique. The
  maximum of 10 leaves a safe temporary band inside unsigned tiny integer.
- **Alternatives considered**:
  - Drop or defer the unique constraint: rejected because uniqueness is a true
    invariant.
  - Update rows directly in presumed safe order: rejected as fragile across
    insert/upward/downward/delete cases.
  - Use negative temporary positions: rejected because the approved column is
    unsigned.

## Decision 5: Use presence-aware partial updates

- **Decision**: Build update attributes only from keys actually present after
  validation; preserve the image and position unless supplied.
- **Rationale**: PATCH requires every field to be optional in the request while
  the stored record remains complete.
- **Alternatives considered**:
  - Merge null defaults: rejected because omitted fields would be cleared.
  - Require full bilingual payload on every update: rejected by the approved
    partial-update contract.

## Decision 6: Validate exact multipart activity values before normalization

- **Decision**: Accept only exact raw multipart strings `"0"` and `"1"`, then
  normalize them to the persisted activity representation. Reject integer-only
  in-process test shortcuts, `true`, `false`, `yes`, `on`, signs, decimals,
  leading zeros, whitespace-padded values, arrays, duplicates, and other forms.
- **Rationale**: Feature 008 defines the public input vocabulary as 0 and 1.
  Explicit normalization avoids PHP truthiness bugs, especially for `"0"`.
- **Alternatives considered**:
  - Laravel's broad boolean spellings: rejected because it widens the exact
    approved contract.
  - Cast any non-empty string: rejected because `"false"` would become true.

## Decision 7: Validate content and derive the stored extension from it

- **Decision**: Validate upload success, exact 5 MiB byte size, allowed client
  extension, detected MIME, decodable image content, and static-image status;
  reject SVG, animated WebP, APNG, and every other format. Select the stored
  extension from the detected JPEG/PNG/WebP MIME map and use a generated name.
- **Rationale**: This satisfies all three required validation dimensions and
  avoids trusting the client's filename or extension for storage.
- **Alternatives considered**:
  - Reuse the original extension in the stored name: rejected because the
    client controls it.
  - Convert every image to WebP: rejected because image conversion is outside
    scope.
  - Accept SVG with sanitation: rejected because SVG is prohibited.

## Decision 8: Keep database and filesystem outcomes compensating, not falsely atomic

- **Decision**: Store new files before the transaction; delete them on database
  failure; delete superseded/deleted old files after commit; log after-commit
  cleanup failures safely.
- **Rationale**: Database rollback cannot roll back a filesystem write. This
  sequence always leaves the database pointing to an existing last-committed
  file and matches the approved reference.
- **Alternatives considered**:
  - Delete the old image before commit: rejected because rollback would leave
    the old row broken.
  - Swallow cleanup failures silently: rejected because operations need safe
    observability.
  - Queue cleanup: rejected because queues are out of scope.

## Decision 9: Use exact Admin/Public projections

- **Decision**: Admin Resource returns both translations plus ID, activity, and
  position. Public Resource returns only localized title, description, and
  absolute image URL; the collection order communicates display order.
- **Rationale**: Admin editing needs bilingual state while public visitors need
  the minimum localized projection and no management metadata.
- **Alternatives considered**:
  - Reuse one Resource: rejected because it risks public data leakage.
  - Return position publicly: rejected because the approved contract excludes
    it and array order is sufficient.

## Decision 10: Use one focused allow-listed Admin index Query

- **Decision**: Validate exact query shape in a Form Request, then use Spatie
  Query Builder only for the exact `isActive` filter while forcing position
  ascending and bounded pagination.
- **Rationale**: This matches repository query patterns without accidentally
  exposing search, sort, include, or raw column selection.
- **Alternatives considered**:
  - Pass arbitrary query keys to Eloquent: rejected as unsafe.
  - Add custom sorting for future flexibility: rejected as out of scope.
  - Avoid pagination because the current limit is 10: rejected because Admin
    pagination is an explicit stable contract.

## Decision 11: Extend existing authorization and documentation patterns

- **Decision**: Add exactly four permissions through the existing idempotent
  Seeder/catalog, use existing Admin middleware order, add an OpenAPI 3.1
  contract, and update the existing Postman collection without new variables.
- **Rationale**: These are established repository integration points and the
  feature explicitly forbids a reorder permission or hidden bypass.
- **Alternatives considered**:
  - Policy-only authorization: rejected because operation permissions are
    route-level and there is no resource ownership rule.
  - New environment variables per request: rejected because the approved
    collection keeps only base URL and authentication tokens.

## Decision 12: Test ordinary files with fakes and critical races with real MySQL

- **Decision**: Use storage fakes for deterministic file tests and dedicated
  multi-process MySQL tests for create/create, move/move, and create/delete
  races.
- **Rationale**: SQLite or mocked transactions cannot prove InnoDB range-lock,
  unique-index, and next-key behavior.
- **Alternatives considered**:
  - Make every CRUD test concurrent: rejected as slow and unnecessary.
  - Test concurrency on SQLite: rejected because its locking semantics differ.

## Decision 13: Do not change dependencies in Feature 008

- **Decision**: Keep Feature 008 compatible with the declared Laravel 12/PHP
  8.2 target and the currently installed Laravel 13/PHP 8.3 code, while treating
  the stale `composer.json`/lock relationship as a pre-existing repository
  prerequisite that must be reconciled before final dependency verification.
- **Rationale**: Feature 008 requires no new package, and silently reconciling
  framework constraints would be unrelated material reconfiguration.
- **Alternatives considered**:
  - Run `composer update`: rejected because it can change the entire dependency
    graph outside this feature.
  - Plan against the older manifest constraints rather than installed code:
    rejected because repository rules require actual locked versions.

## Decision 14: Parse the exact multipart PATCH contract before Form Requests

- **Decision**: Add a narrowly scoped middleware and strict parser for the Hero
  PATCH route on PHP 8.2/8.3. Bound the raw body to one exactly 5 MiB file plus
  small fixed multipart overhead, validate boundary/header structure, accept
  only approved scalar parts and one image part, preserve duplicate-member
  detection, enforce at least one approved mutable part, materialize one
  managed temporary upload, and always clean the temporary file after
  downstream handling.
- **Rationale**: Installed Symfony source uses native `request_parse_body()`
  for non-POST multipart only on PHP 8.4+. PHP 8.2/8.3 otherwise leaves direct
  multipart PATCH form/file bags empty. An in-process Laravel PATCH test can
  simulate files and hide this production transport failure.
- **Alternatives considered**:
  - Use `POST` with `_method=PATCH`: rejected because it silently changes the
    exact wire contract and introduces an unapproved field.
  - Add a separate image endpoint: rejected because it is outside the five
    approved Admin routes.
  - Ignore the limitation and rely on Laravel tests: rejected because real
    FPM/Apache requests would not prove image replacement.
  - Add an unreviewed multipart package: rejected because the feature requires
    no dependency and the repository dependency state is already inconsistent.

## Decision 15: Keep the maximum-count outcome as VALIDATION_ERROR

- **Decision**: Reject an eleventh slide with HTTP 422 and stable code
  `VALIDATION_ERROR`. Use `HERO_SLIDE_NOT_FOUND` for missing slides and
  `HERO_SLIDE_IMAGE_UPLOAD_FAILED` for safe image-storage failure. Do not emit
  `HERO_SLIDE_LIMIT_REACHED` in the initial contract.
- **Rationale**: The maximum-count section explicitly states `422
  VALIDATION_ERROR`; the later error list says the listed codes only “may” be
  used. The explicit rule is therefore authoritative and unambiguous.
- **Alternatives considered**:
  - Return `HERO_SLIDE_LIMIT_REACHED`: rejected because it would contradict the
    exact maximum-count outcome.
  - Return 409: rejected because the approved status is 422.


## Decision 16: Reject empty PATCH and canonicalize position lexically

- **Decision**: A PATCH request must contain at least one approved mutable field.
  Validate raw multipart `position` as a canonical positive decimal string
  (`^[1-9][0-9]*$`) before converting it to an integer and checking the locked
  range.
- **Rationale**: Empty updates have no business effect, and broad integer
  coercion would incorrectly accept values such as `01`, `+1`, `1.0`, or
  whitespace-padded input.
- **Alternatives considered**:
  - Treat empty PATCH as a successful no-op: rejected because the approved
    corrected contract requires validation failure.
  - Rely only on Laravel's integer rule: rejected because lexical normalization
    can widen the wire contract.

## Decision 17: Use bounded deadlock retries around the full lock/transaction attempt

- **Decision**: Retry only repository-approved MySQL deadlock or serialization
  errors. Each attempt reacquires the advisory mutex and starts a fresh
  transaction on the same selected connection. Validation failures, missing
  targets, capacity limits, storage validation failures, and non-retryable SQL
  errors are never retried. Exhausted retries return a safe server outcome and
  trigger compensation of any newly stored file.
- **Rationale**: The advisory mutex greatly reduces contention but does not
  prove that every interaction with other database work is deadlock-free.
- **Alternatives considered**:
  - Retry every exception: rejected because it can repeat deterministic business
    failures and unsafe side effects.
  - Do not retry deadlocks: rejected by the corrected specification.

## Decision 18: Treat titles and descriptions as plain text

- **Decision**: Trim and persist titles/descriptions as plain text without HTML
  sanitization or rich-text transformation. Reject null bytes and unapproved
  control characters; consumers render values as text, not raw HTML.
- **Rationale**: Silent sanitization changes accepted content, while HTML
  interpretation would widen the feature into rich text.
- **Alternatives considered**:
  - Strip tags automatically: rejected because it mutates user input silently.
  - Render stored values as HTML: rejected as outside scope and unsafe.
