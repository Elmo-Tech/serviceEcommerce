# Research: Settings Management

## Decision 1: Use one explicit singleton resolver service

- **Decision**: Introduce a focused `SettingsResolver` that always resolves the
  singleton row by `settings.id = 1`, restores it only for Admin workflows when
  unexpectedly missing, and returns a safe default public projection for Public
  read without creating a row as a read side effect.
- **Rationale**: This matches the approved feature reference and avoids
  unscoped `first()` behavior, duplicate rows, or inconsistent recovery logic
  across controllers.
- **Alternatives considered**:
  - Query `Setting::query()->first()` everywhere: rejected because it weakens
    singleton guarantees.
  - Auto-create on every missing Public read: rejected because the feature
    explicitly forbids public read side effects.

## Decision 2: Use one transactional Action for Admin updates

- **Decision**: Implement `UpdateSettingsAction` as the single orchestration
  layer for scalar updates, child-collection replacement, file-path mutation,
  and rollback cleanup.
- **Rationale**: The feature has one clearly bounded multi-step write use case
  with explicit atomicity and filesystem-compensation requirements.
- **Alternatives considered**:
  - Put all logic in the controller: rejected because it would violate the
    repository architecture rules.
  - Split every small write into a separate Action: rejected because it adds
    unnecessary orchestration layers for one cohesive workflow.

## Decision 3: Normalize 10-digit and 11-digit phone numbers before all duplicate checks

- **Decision**: Use one focused phone normalizer service that converts accepted
  formatted local or Egyptian country-prefixed variants to a digits-only local
  format containing exactly 10 or 11 digits before validation and persistence.
- **Rationale**: The feature reference defines one canonical stored format and
  requires duplicate checks after normalization.
- **Alternatives considered**:
  - Keep E.164 in storage: rejected because the feature reference explicitly
    approves local Egyptian canonical output.
  - Validate only the raw submitted string: rejected because duplicates would
    slip through equivalent formatting.

## Decision 4: Keep child collections as replacement-based arrays without row IDs

- **Decision**: Treat `phones` and `socialLinks` as ordered full-replacement
  collections with optional clear flags and no item IDs in the API contract.
- **Rationale**: This is the exact approved contract and keeps the admin client
  simple while preserving backend authority.
- **Alternatives considered**:
  - Add row-level create/update/delete endpoints: rejected because they are outside the approved scope.
  - Return internal row IDs for editing: rejected because the feature
    explicitly forbids exposing them.

## Decision 5: Use safe generated filenames with post-commit deletion

- **Decision**: Store branding files through Laravel Filesystem using safe
  generated filenames, commit database changes first, then delete superseded
  files after commit; delete newly stored files on transaction failure.
- **Rationale**: This matches the repository file-storage rules and the feature
  reference’s compensation requirements.
- **Alternatives considered**:
  - Delete old files before commit: rejected because it risks data/file drift
    on rollback.
  - Persist raw client filenames: rejected because it weakens path safety and
    predictability.

## Decision 6: Inspect SVG content explicitly

- **Decision**: Add dedicated SVG-safety inspection for logo, footer logo,
  and favicon uploads. Unsafe SVG files are rejected; the backend does not
  silently sanitize, rewrite, or modify submitted SVG content.
- **Rationale**: Feature 006 explicitly allows SVG for branding files and
  requires content inspection beyond extension or MIME checks. Explicit
  rejection keeps validation predictable and avoids silently changing an
  administrator's uploaded asset.
- **Alternatives considered**:
  - Reuse the repository-wide “no SVG” default unchanged: rejected because the
    approved feature reference specifically opens this narrow file-type scope.
  - Trust MIME type plus extension only: rejected because the feature explicitly
    disallows that shortcut.

## Decision 7: Keep Public Settings as one localized projection

- **Decision**: Public read uses one localized Resource with neutral machine
  keys and required locale headers; Admin read/update always returns both
  languages.
- **Rationale**: This matches shared localization rules and the feature
  contract exactly.
- **Alternatives considered**:
  - Return both languages publicly: rejected because it leaks unneeded fields
    and contradicts the approved public contract.
  - Localize machine keys: rejected because machine identifiers must remain
    stable English values.



## Decision 8: Keep the OpenAPI document strictly OpenAPI 3.1

- **Decision**: Model nullable response values with JSON Schema type unions,
  require every approved response key, set exact-object boundaries with
  `additionalProperties: false`, and declare `security: []` explicitly for the
  Public operation.
- **Rationale**: The contract is consumed as OpenAPI 3.1, so OpenAPI 3.0
  `nullable` behavior and optional response keys could generate inaccurate
  clients and incomplete contract tests.
- **Alternatives considered**:
  - Keep `nullable: true`: rejected because it is not the OpenAPI 3.1 JSON
    Schema form.
  - Leave optional response properties out of `required`: rejected because the
    approved Resources always return all keys using `null` or empty arrays.

## Decision 9: Lock the singleton for every Admin mutation

- **Decision**: Resolve `settings.id = 1` inside the transaction with
  `lockForUpdate`, then replace phones and social links while that lock is held.
  Missing-row recovery uses an idempotent insert followed by a locked re-read.
- **Rationale**: Application validation alone cannot preserve the maximum-three
  phones, single-WhatsApp rule, or ordered full-replacement semantics under
  concurrent PATCH requests.
- **Alternatives considered**:
  - Validate before a transaction without a row lock: rejected because two
    concurrent requests could both validate stale state.
  - Depend only on duplicate-key errors: rejected because count and
    single-WhatsApp rules are not fully represented by ordinary unique indexes.


## Decision 10: Separate phone input and output contracts

- **Decision**: Use `PhoneInput` for Admin update requests and `PhoneOutput` for
  Admin/Public responses.
- **Rationale**: Requests must accept approved Egyptian local and international
  variants before normalization, while responses must always expose the
  canonical local format such as `01012345678`.
- **Alternatives considered**:
  - Reuse the canonical output schema for requests: rejected because it would
    incorrectly reject approved values such as `+201012345678`,
    `00201012345678`, and formatted local numbers.
  - Remove the canonical response pattern: rejected because response
    normalization is an approved stable contract.
