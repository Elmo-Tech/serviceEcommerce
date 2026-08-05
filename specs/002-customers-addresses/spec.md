# Feature Specification: Customers and Addresses

**Feature Branch**: `002-customers-addresses`

**Created**: 2026-07-29

**Status**: Ready for Planning

**Input**: User description: "Build Feature 002 from `docs/features/002-customers-addresses.md` and use that file as the authoritative reference."

## Scope and Governing Context *(mandatory)*

**Approved source**: [docs/features/002-customers-addresses.md](C:/xampp/htdocs/serviceEcommerce/docs/features/002-customers-addresses.md)

**In scope**:

- Protected administrator APIs for customer CRUD, soft delete, and restore
- Protected nested administrator APIs for customer-address CRUD, soft delete,
  restore, and set-default
- Customer identity based on backend-normalized E.164 phone numbers
- Egyptian default-country parsing with support for valid international numbers
- Optional unique normalized customer email
- Multiple customer addresses with a maximum of 20 active addresses
- Exactly one active default address when active addresses exist
- Address deduplication through backend-generated address identity
- Automatic restoration of matching soft-deleted customers and addresses during
  future guest-order resolution flows
- Reusable customer and address matching capabilities for the future Orders
  feature
- Snapshot obligations that protect future order customer/address history
- Localized Arabic and English messages, validation, and stable English machine
  codes
- Real MySQL concurrency protection for customer identity and default-address
  invariants

**Out of scope**:

- Customer authentication, registration, passwords, tokens, dashboards, or
  profiles
- Public customer CRUD routes
- Customer export, merge UI, order-history endpoints, payment methods, wallets,
  or marketing preferences
- Customer avatar, files, recipient name, building, floor, apartment, or postal
  code fields
- Force delete for customers or addresses
- Any change to the Feature 001 token issuance, refresh, browser storage, CSP,
  or cookie model

**Governing documents reviewed**:

- `.specify/memory/constitution.md`
- `AGENTS.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- `docs/02-standards/api-standards.md`
- `docs/02-standards/authentication-standards.md`
- `docs/02-standards/authorization-standards.md`
- `docs/02-standards/code-standards.md`
- `docs/02-standards/database-standards.md`
- `docs/02-standards/localization-standards.md`
- `docs/02-standards/security-standards.md`
- `docs/02-standards/testing-standards.md`
- `docs/features/001-identity-authentication.md`
- `docs/features/002-customers-addresses.md`

**Known conflicts**:

- None

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Admin manages canonical customer records (Priority: P1)

An authenticated administrator needs to create, view, update, soft-delete, and
restore guest customer records without ever turning those customers into
authenticated accounts. Customer identity must stay canonical so the same
person is not duplicated under multiple raw phone formats.

**Why this priority**: Customer identity is the foundation for orders,
addresses, and historical snapshots. If canonical customer records are wrong,
every downstream feature becomes unreliable.

**Independent Test**: Can be fully tested by calling the protected customer
administration routes with valid and invalid customer payloads, then verifying
database uniqueness, soft-delete behaviour, and API response safety.

**Acceptance Scenarios**:

1. **Given** an authenticated administrator with the customer-create
   permission, **When** they create a customer using a valid Egyptian local
   phone number, **Then** the backend stores one canonical E.164 phone identity
   and returns only the approved safe customer fields.
2. **Given** an existing customer with a non-null normalized email, **When**
   another customer is submitted with the same normalized email, **Then** the
   request fails with a validation-style duplicate-email outcome and no second
   customer is created.
3. **Given** a soft-deleted customer, **When** an authorized administrator
   restores that customer, **Then** the customer becomes active again without
   automatically restoring every deleted address.

---

### User Story 2 - Admin manages multiple addresses with one default (Priority: P1)

An authenticated administrator needs to store multiple service addresses for a
customer, keep only one active default address, prevent duplicate saved
addresses, and recover deleted addresses safely.

**Why this priority**: Address quality directly affects future order fulfilment.
The default-address and duplicate-prevention rules are critical business
invariants, not optional convenience behaviour.

**Independent Test**: Can be fully tested by calling the nested customer
address routes, then verifying default switching, limit enforcement,
duplicate-address handling, and nested ownership protection.

**Acceptance Scenarios**:

1. **Given** a customer with no active addresses, **When** the administrator
   creates the first address, **Then** that address becomes the default
   automatically.
2. **Given** a customer with one current default address, **When** the
   administrator creates or selects another address as default, **Then** the
   previous default loses default status and exactly one active default remains.
3. **Given** a customer already has 20 active addresses, **When** the
   administrator attempts to add or restore another active address, **Then**
   the request is rejected with the approved address-limit outcome.

---

### User Story 3 - Future guest orders resolve customers and addresses safely (Priority: P2)

The future guest-order workflow needs reusable customer and address resolution
behaviour that reuses or restores canonical records without silently
overwriting saved customer identity or address details.

**Why this priority**: This feature exists not only for dashboard CRUD but also
to support correct guest-order matching and future immutable snapshots.

**Independent Test**: Can be fully tested through focused domain and
concurrency tests that call the customer and address matching capabilities
without requiring the Orders feature to exist yet.

**Acceptance Scenarios**:

1. **Given** an active customer already exists with the same normalized phone,
   **When** a future guest-order workflow resolves that phone with a different
   submitted name or email, **Then** the existing customer is reused and the
   saved customer identity is not overwritten automatically.
2. **Given** a matching soft-deleted customer or address exists, **When** the
   future guest-order workflow resolves the same canonical identity, **Then**
   the matching record is restored and no duplicate active record is created.
3. **Given** two requests resolve the same customer or default-address
   invariant at the same time, **When** the operations complete, **Then** the
   database still contains one canonical customer per normalized phone and one
   active default address per customer.

---

### Edge Cases

- What happens when a phone number is local-format, international-format,
  malformed, or includes an extension?
- How does the system handle two simultaneous requests that normalize to the
  same customer phone?
- How does the system handle two simultaneous default-address changes for the
  same customer?
- What happens when an address update changes the address identity so it
  collides with another active saved address for the same customer?
- What happens when the current default address is deleted and no active
  address remains?
- How does the system handle a nested address route that points to an address
  belonging to another customer?
- What happens when a deleted customer is restored but previously deleted
  addresses remain deleted?
- What happens when address restoration would exceed the 20-active-address
  limit?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST treat a customer as a guest domain record only
  and MUST NOT create customer passwords, tokens, login routes, dashboards, or
  authenticated customer sessions in this feature.
- **FR-002**: The system MUST provide protected administrator routes to list,
  create, show, update, soft-delete, and restore customers.
- **FR-003**: The system MUST store each customer with one `name`, optional
  `email`, display `phone`, canonical `phoneNormalized`, timestamps, soft
  deletion support, and MAY accept one optional initial address object during
  administrator customer creation only.
- **FR-004**: The system MUST normalize non-null customer email by trimming and
  lowercasing it, convert empty email to `null`, and enforce uniqueness for
  non-null email values only.
- **FR-005**: The system MUST parse customer phone numbers with Egypt as the
  default country, accept valid international numbers, reject invalid numbers
  and extensions, and store one canonical E.164 normalized phone value.
- **FR-006**: The system MUST return the approved invalid-phone validation
  outcome for customer phone parsing failures using the stable code
  `CUSTOMER_PHONE_INVALID` on the `phone` field.
- **FR-006A**: Administrator customer create or update requests that resolve to
  an already-owned normalized phone MUST fail with HTTP `422`, use the stable
  code `CUSTOMER_PHONE_ALREADY_EXISTS`, attach the error to `phone`, and MUST
  NOT create or update a conflicting customer record.
- **FR-007**: The system MUST use `phoneNormalized` as the only customer
  matching key and MUST NOT match customers by name, email, or address fields.
- **FR-008**: When an active customer already exists for a normalized phone,
  the future guest-order resolution flow MUST reuse that customer and MUST NOT
  overwrite the saved customer name or email automatically.
- **FR-009**: When a soft-deleted customer exists for a normalized phone, the
  future guest-order resolution flow MUST restore that customer instead of
  creating a duplicate and MUST NOT automatically restore all deleted
  addresses.
- **FR-010**: When no matching customer exists, the future guest-order
  resolution flow MUST create a new customer using the submitted name, optional
  normalized email, and backend-normalized phone.
- **FR-011**: The system MUST protect canonical customer creation against
  duplicate concurrent requests so that only one active customer can exist for
  a normalized phone.
- **FR-012**: The system MUST provide protected nested administrator routes to
  list, create, show, update, soft-delete, restore, and set-default customer
  addresses under a specific customer.
- **FR-013**: The system MUST store addresses only with the approved fields:
  `province`, `city`, `address`, optional `notes`, `addressHash`, default flag,
  timestamps, and soft deletion support. Phone belongs only to the customer.
- **FR-014**: The system MUST NOT add or persist address fields for recipient
  name, building, floor, apartment, or postal code in this feature.
- **FR-015**: The system MUST enforce a maximum of 20 active addresses per
  customer and MUST reject the twenty-first active address or address restore
  attempt with the stable code `CUSTOMER_ADDRESS_LIMIT_EXCEEDED`.
- **FR-016**: The system MUST generate the address identity on the backend from
  normalized `province`, `city`, and `address` only; it MUST NOT include notes
  or phone in the address identity.
- **FR-017**: The system MUST prevent duplicate active saved addresses for the
  same customer and MUST return the approved duplicate-address outcome with the
  stable code `CUSTOMER_ADDRESS_ALREADY_EXISTS` for manual administrator
  creation or identity-changing update collisions.
- **FR-018**: The first active address for a customer MUST become the default
  automatically.
- **FR-019**: When a new or existing address is selected as default, the system
  MUST ensure that all other active addresses for the same customer become
  non-default within the same atomic operation.
- **FR-020**: When the current default address is deleted, the system MUST
  choose the newest remaining active address ordered by `createdAt DESC`, then
  `id DESC`, as the fallback default within the same atomic operation. If no
  active addresses remain, the customer MUST have zero default addresses.
- **FR-021**: When a soft-deleted address matches the future guest-order
  submitted address identity, the system MUST restore that address instead of
  creating a duplicate and MUST NOT auto-update saved notes.
- **FR-022**: Customer soft deletion MUST soft-delete the customer and all
  active addresses inside the same database transaction. Customer restoration
  MUST restore the customer only and MUST NOT automatically restore deleted
  addresses.
- **FR-023**: Customer and address APIs MUST NOT change historical order
  customer or address snapshots once those snapshots exist in future features.
- **FR-024**: The system MUST provide approved search, soft-delete-aware list
  filtering, allow-listed sorting, and pagination for customer administration
  endpoints.
- **FR-025**: Address ownership MUST always be verified through the customer
  parent route, and an address belonging to another customer MUST return the
  approved nested not-found outcome.
- **FR-026**: Customer and address API Resources MUST expose only approved safe
  fields and MUST NOT expose `phoneNormalized`, `addressHash`, authentication
  fields, or token-related data. Egyptian phone values in responses MUST be
  returned as local national digits without country code or spaces.
- **FR-027**: All user-facing messages and validation errors for this feature
  MUST be available in Arabic and English while machine-readable field names,
  route names, permission names, and error codes remain stable English values.

### Actors and Authorization *(mandatory for protected behaviour)*

- **AR-001**: Every Feature 002 administration route MUST require the Feature
  001 authentication contract and the middleware order
  `auth:sanctum -> EnsureUserIsAdministrator -> EnsureAdminIsActive -> permission -> endpoint`.
- **AR-002**: The feature MUST define independent stable permissions for
  customer operations and address operations, including a dedicated
  set-default permission. The approved permission identifiers are:
  `customers.view`, `customers.create`, `customers.update`,
  `customers.delete`, `customers.restore`, `customer-addresses.view`,
  `customer-addresses.create`, `customer-addresses.update`,
  `customer-addresses.delete`, `customer-addresses.restore`, and
  `customer-addresses.set-default`.
- **AR-003**: The system MUST return `401 UNAUTHENTICATED` for missing, invalid,
  expired, revoked, or predecessor access tokens; `403 USER_INACTIVE` for an
  inactive administrator; `403 FORBIDDEN` for a missing permission; and the
  approved nested-resource `404` outcome for foreign customer-address access.
- **AR-004**: No public customer CRUD route, customer-authentication route,
  refresh-token input, or token-returning Feature 002 route may exist.

### Trust, Security, and Content Boundaries *(mandatory)*

- **TR-001**: The backend MUST derive and control email normalization, phone
  normalization, address identity, default-address state, customer/address
  ownership, and soft-delete or restore outcomes; request input MUST NOT be
  authoritative for tokens, administrator identity, permission state,
  `phoneNormalized`, `addressHash`, or historical snapshots.
- **TR-002**: Customer and address plain-text fields (`name`, `province`,
  `city`, `address`, `notes`) MUST remain plain text only, without trusted
  HTML or Markdown, and responses and logs MUST avoid sensitive leakage.
- **TR-003**: This feature MUST NOT introduce customer file uploads, avatars,
  exports, or any new browser token transport; Feature 001 remains the sole
  owner of authentication and token security.

### Data Integrity and Concurrency *(mandatory when data changes)*

- **DI-001**: The system MUST enforce unique normalized customer phone,
  unique non-null normalized customer email, valid customer-to-address foreign
  keys, and a maximum of 20 active addresses per customer. A customer MUST have
  zero default addresses when no active address exists and exactly one active
  default address whenever one or more active addresses exist.
- **DI-002**: Customer creation, customer restoration, address creation,
  address restoration, address identity changes, default-address changes, and
  guest-order matching resolution MUST use the approved transaction and lock or
  unique-constraint strategy required to prevent duplicate canonical records
  and conflicting defaults.
- **DI-003**: Editing, deleting, restoring, or deduplicating current customer
  and address records MUST NOT rewrite or invalidate future historical order
  snapshots that reference those records.

### API Contract and Localization *(mandatory for API behaviour)*

- **API-001**: The approved protected route set for this feature is:
  `GET /api/v1/admin/customers`,
  `POST /api/v1/admin/customers`,
  `GET /api/v1/admin/customers/{customer}`,
  `PATCH /api/v1/admin/customers/{customer}`,
  `DELETE /api/v1/admin/customers/{customer}`,
  `POST /api/v1/admin/customers/{customer}/restore`,
  `GET /api/v1/admin/customers/{customer}/addresses`,
  `POST /api/v1/admin/customers/{customer}/addresses`,
  `GET /api/v1/admin/customers/{customer}/addresses/{address}`,
  `PATCH /api/v1/admin/customers/{customer}/addresses/{address}`,
  `DELETE /api/v1/admin/customers/{customer}/addresses/{address}`,
  `POST /api/v1/admin/customers/{customer}/addresses/{address}/restore`, and
  `PUT /api/v1/admin/customers/{customer}/addresses/{address}/default`.
- **API-002**: Customer create and update requests MUST use `camelCase` request
  keys, accept the approved customer fields only, and reject unsupported
  customer-auth, role, permission, or protected identity fields. Customer
  create MAY accept one optional nested `address` object containing only
  `province`, `city`, `address`, and optional `notes`.
- **API-003**: Address create and update requests MUST use `camelCase` request
  keys and accept only `province`, `city`, `address`, `notes`, and `isDefault`
  as applicable. Address phone keys MUST be rejected as unsupported input.
- **API-004**: Success responses MUST follow the shared API envelope and return
  only approved customer or address data. Error responses MUST use the shared
  localized envelope with stable English machine codes where applicable,
  including `VALIDATION_ERROR`, `UNAUTHENTICATED`, `USER_INACTIVE`,
  `FORBIDDEN`, `RATE_LIMITED`, `INTERNAL_ERROR`, `CUSTOMER_NOT_FOUND`,
  `CUSTOMER_PHONE_ALREADY_EXISTS`, `CUSTOMER_EMAIL_ALREADY_EXISTS`,
  `CUSTOMER_PHONE_INVALID`, `CUSTOMER_DELETED`,
  `CUSTOMER_ADDRESS_NOT_FOUND`, `CUSTOMER_ADDRESS_ALREADY_EXISTS`,
  `CUSTOMER_ADDRESS_LIMIT_EXCEEDED`,
  `CUSTOMER_DEFAULT_ADDRESS_REQUIRED`, and
  `CUSTOMER_ADDRESS_RESTORE_CONFLICT`.
- **API-005**: `GET /api/v1/admin/customers` MUST support only the approved
  query parameters: `filter[search]`, `filter[status]`,
  `filter[hasAddresses]`, `filter[createdFrom]`, `filter[createdTo]`, `sort`,
  `page`, and `perPage`. The default `filter[status]` MUST be `active`;
  allowed status values are `active`, `deleted`, and `all`. Allowed customer
  sort values are `createdAt`, `-createdAt`, `name`, and `-name`; the default
  sort is `-createdAt`. The default `perPage` is `20` and the maximum is
  `100`. Nested address responses remain customer-scoped and safely bounded by
  the 20-active-address limit.
- **LOC-001**: Arabic and English MUST both be supported for success messages,
  validation messages, and business-rule errors in this feature, while
  permission identifiers, error codes, route paths, and JSON keys remain
  stable English values.
- **LOC-002**: Locale MUST resolve from `Accept-Language` before validation and
  response rendering; the feature MUST remain compatible with response metadata
  such as `Content-Language` and `Vary: Accept-Language` where the shared API
  implementation provides them.

### Verification Requirements *(mandatory)*

- **VR-001**: The feature MUST include consolidated API Feature Tests for the
  customer and address business areas. Related route, validation, permission,
  persistence, safe-resource, and representative localization scenarios MUST
  be grouped instead of producing one test file or one task per case.
- **VR-002**: The feature MUST include focused domain tests for customer and
  address matching plus only the critical real-MySQL concurrency tests for
  same-phone customer creation, one-default-address enforcement, and
  duplicate-address race protection where the selected database strategy
  supports direct enforcement.
- **VR-003**: Feature acceptance requires passing Pest, Pint, and PHPStan or
  Larastan quality gates, updated Postman documentation for the protected
  customer and address routes, and explicit proof that no public customer CRUD
  or token-handling behaviour was introduced.
- **VR-004**: Planning and task generation MUST keep tests consolidated into
  approximately five meaningful groups. Feature 002 MUST NOT duplicate Feature
  001 tests for Refresh Token rotation, predecessor-token revocation, token
  reuse detection, IP-only refresh throttling, browser token storage, CSP,
  cookies, CSRF, token entropy, or authentication log redaction. Do not
  generate one task per route, permission, validation rule, locale, or security
  assertion.

### Key Entities *(include if feature involves data)*

- **Customer**: A guest business customer record identified canonically by
  normalized phone, with one mutable display name, optional unique email, soft
  delete support, and no authentication capability.
- **Customer Address**: A customer-owned service address with one backend
  identity, one required phone, one required `province`, one required `city`,
  one required `address`, one default flag, and soft-delete support.
- **Customer and Address Resolution Contract**: The reusable domain behaviour
  that future guest-order flows use to resolve, create, reuse, or restore
  authoritative customer and address records without mutating historical order
  snapshots.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Administrators can create a customer with a valid Egyptian local
  phone or valid international phone and receive a successful protected API
  response without any customer-authentication fields being introduced.
- **SC-002**: Repeated or concurrent submissions for the same canonical phone
  result in one resolved customer identity rather than duplicate active
  customer records.
- **SC-003**: Each customer can hold up to 20 active saved addresses, and when
  one or more active addresses exist, exactly one active default address is
  preserved after create, update, delete, restore, or set-default operations.
- **SC-004**: Future guest-order resolution can reuse or restore matching
  customer and address records without automatically overwriting saved customer
  name, saved customer email, or saved address notes.
- **SC-005**: Customer and address management remains administrator-only, with
  no public customer CRUD routes, no customer authentication routes, and no
  Feature 002 token issuance or token acceptance added to the system.

## Assumptions

- Feature 001 administrator authentication, access-token rotation, and
  separate-domain Bearer-token contract are already active and reused without
  modification.
- Future guest-order features will call the customer and address resolution
  capabilities defined here instead of duplicating matching logic elsewhere.
- Customer list endpoints are paginated and searchable, while nested customer
  address collections remain safely bounded by the 20-active-address maximum.
- Historical customer and address snapshots will be persisted by the future
  Orders feature, but Feature 002 must define and preserve that boundary now.

## Planning and Task-Generation Constraint

The generated plan and tasks MUST remain focused on the customer/address
business domain.

Target testing task groups:

1. consolidated customer API and permission coverage
2. consolidated address API, ownership, and permission coverage
3. customer/address matching coverage
4. critical MySQL concurrency coverage
5. minimal authentication-boundary, localization, and architecture coverage

The generator MUST NOT expand these into dozens of security, validation, route,
permission, or locale subtasks unless a real implementation defect later
requires a focused regression test.
