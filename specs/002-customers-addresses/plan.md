# Implementation Plan: Customers and Addresses

**Feature:** `002-customers-addresses`  
**Branch:** `002-customers-addresses`  
**Date:** 2026-07-29  
**Spec:** [spec.md](./spec.md)  
**Status:** Ready for Task Generation

## Summary

Implement Feature 002 as protected administrator-only customer and
customer-address management on top of the Feature 001 Bearer-token contract.
Implementation occurs inside the existing Laravel repository and existing
Feature branch; compatible legacy code is reused or updated rather than
blindly recreated.
The design centers on three backend-owned invariants:

1. one canonical customer per normalized E.164 phone;
2. one active default address per customer when active addresses exist;
3. no duplicate active saved address identity for the same customer.

The implementation will use:

- thin Laravel controllers under `/api/v1/admin/*`;
- Form Requests for all mutations and query validation;
- focused Actions for create/update/delete/restore/default workflows;
- reusable customer services for phone parsing, address normalization, and
  guest-order resolution;
- a customer index Query for bounded search/filter/sort pagination;
- MySQL transactions and row locks for customer identity and default-address
  races;
- explicit API Resources with stable `camelCase` output and localized messages.

This feature does not introduce customer authentication, public customer CRUD,
files, exports, or any change to Feature 001 token/session behavior.

## Pre-Implementation Existing-Code Audit

Before creating or changing application files, the implementation MUST inspect
the current repository and classify every existing customer/address artifact as:

```text
Reuse
Update
Replace
Delete only when unreferenced
Create only when missing
```

The audit must cover:

- existing `customers` and `customer_addresses` tables and migrations
- existing customer/address columns, indexes, foreign keys, and soft deletes
- existing Models, relationships, casts, scopes, and factories
- existing Controllers, Form Requests, Resources, Actions, Services, and Queries
- existing `/api/v1/admin/customers*` routes and route names
- existing permissions and seeders
- existing localization keys
- existing Postman/OpenAPI documentation
- existing Feature, domain, concurrency, and architecture tests
- references from future or unrelated modules that may already use customer data

Required audit output:

1. a concise inventory of existing artifacts;
2. the chosen action for each artifact: reuse, update, replace, delete, or create;
3. any data-preserving migration required to move the old schema to the approved
   schema;
4. confirmation that no duplicate table, route, permission, class, or migration
   will be introduced.

Mandatory safety rules:

- do not create a second customer or address table when an approved table
  already exists;
- do not rerun or rewrite an already-executed production migration;
- use additive or altering migrations for existing databases;
- do not drop columns, indexes, or data without explicit approved migration
  evidence;
- do not delete old classes until repository references are checked;
- preserve existing production customer/address data;
- create a Git checkpoint before implementation changes.

Task generation MUST place this audit before schema or source-code creation.

## Technical Context

**Language/Version**: PHP `^8.3`

**Primary Dependencies**:

- Laravel Framework `^13.8`
- Laravel Sanctum `^4.3`
- `spatie/laravel-permission` `^8.3`
- `spatie/laravel-query-builder` `^7.3`
- planned runtime addition: a libphonenumber-compatible package
  (`giggsey/libphonenumber-for-php`) wrapped behind a local service

**Storage**:

- MySQL for canonical customer and address persistence
- Laravel soft deletes for customer and address lifecycle
- no file storage responsibilities in this feature

**Testing**:

- Pest on a dedicated MySQL testing database
- real MySQL concurrency tests for customer and default-address races
- Laravel Pint
- Larastan/PHPStan

**Target Platform**:

- Hostinger-compatible PHP/MySQL deployment
- backend-only Laravel monolith
- no Redis, scheduler, worker, or shell dependency added by this feature

**Project Type**: Backend-only, API-first conventional Laravel monolith

**Performance Goals**:

- customer index responses remain bounded to a maximum `perPage` of `100`
- nested customer-address collections remain bounded by the business limit of
  `20` active addresses per customer
- representative protected customer index and detail requests should stay
  within normal admin CRUD latency expectations for MySQL-backed APIs

**Constraints**:

- stable `/api/v1` JSON contracts and shared success/error envelopes
- Feature 001 middleware order and Bearer-token contract are mandatory
- no public customer CRUD or customer authentication
- Arabic and English localization with stable English machine identifiers
- no raw `phoneNormalized`, `addressHash`, or token exposure
- no speculative policies, jobs, or exports

**Scale/Scope**:

- customer records are expected to grow to operational admin-list scale
  (thousands+), so index queries must be paginated and searchable
- each customer may have at most `20` active saved addresses
- write concurrency is concentrated around:
  - same normalized customer phone creation or restore
  - default-address switching
  - address create/update/restore identity collisions

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

### Pre-Design Gate

- **Authority, precedence, and traceability — PASS**  
  Every planned behavior maps directly to
  `docs/features/002-customers-addresses.md`, the active spec, and the shared
  standards.

- **Conflict and exception gate — PASS**  
  No unresolved conflict was found between Feature 002 and the constitution,
  AGENTS, architecture, or shared standards.

- **Repository boundary — PASS**  
  The feature remains backend-only Laravel work with no React, Next.js, Blade,
  customer portal, or infrastructure expansion.

- **Architecture — PASS**  
  The planned structure uses thin controllers, dedicated Form Requests,
  Resources, focused Actions/Services, one Query class, and no speculative
  layers.

- **API contract — PASS**  
  The plan keeps versioned `/api/v1/admin/*` routes, shared envelopes,
  `camelCase` keys, bounded pagination, and allow-listed query parameters.

- **Authentication and authorization — PASS**  
  Feature 001 remains the sole token/session owner; Feature 002 uses explicit
  Spatie permissions, scoped nested-resource access, and approved
  `401`/`403`/`404` outcomes.

- **Trust and content safety — PASS**  
  The backend owns normalization, identity, uniqueness, default resolution, and
  output filtering; all text stays plain text.

- **Database integrity — PASS**  
  The design protects unique normalized phones and emails, default-address
  invariants, soft-delete behavior, future snapshot stability, and race safety
  through MySQL constraints plus transactions/locks.

- **Files — PASS**  
  This feature introduces no file upload or file exposure behavior.

- **Localization — PASS**  
  Arabic and English messages remain supported while machine identifiers stay
  English.

- **Testing and quality — PASS**  
  The design includes consolidated Pest coverage on MySQL, critical concurrency
  tests, Pint, and Larastan/PHPStan.

- **Scope and operations — PASS**  
  No speculative features or unsupported Hostinger assumptions are introduced.

### Post-Design Gate

- **Authority, precedence, and traceability — PASS**  
  `research.md`, `data-model.md`, `contracts/openapi.yaml`, and
  `quickstart.md` all trace back to the approved Feature 002 decisions.

- **Conflict and exception gate — PASS**  
  No plan artifact weakens a higher-level standard or Feature 001 auth
  contract.

- **Repository boundary — PASS**  
  The design remains strictly backend admin/customer domain work.

- **Architecture — PASS**  
  Matching, normalization, and default resolution are isolated into justified
  Actions/Services without forcing policies or jobs where they add no value.

- **API contract — PASS**  
  The design fixes the route surface, list query allow-list, resource safety,
  and stable machine codes needed for task generation.

- **Authentication and authorization — PASS**  
  The route design preserves `auth:sanctum -> EnsureUserIsAdministrator ->
  EnsureAdminIsActive -> permission -> endpoint`.

- **Trust and content safety — PASS**  
  No token input/output, no raw normalized identity output, and no plain-text
  rules are weakened.

- **Database integrity — PASS**  
  The plan chooses a transaction + row-lock strategy for default and duplicate
  address protection, avoiding unresolved active-row uniqueness ambiguity.

- **Files — PASS**  
  Still no file responsibility introduced.

- **Localization — PASS**  
  Locale handling stays request-driven through `Accept-Language`, with shared
  metadata compatibility.

- **Testing and quality — PASS**  
  The quickstart and verification artifacts define the exact consolidated test
  groups and quality gates expected before completion.

- **Scope and operations — PASS**  
  No scheduler, worker, Redis, or unsupported operational dependency has been
  added.

## Core Design Decisions

### Domain Shape

- `customers` is the canonical guest-customer table.
- `customer_addresses` is a nested table with soft deletes and one default
  invariant.
- customer phone is owned only by `customers`; `customer_addresses` does not
  persist or expose a second phone number.
- customer identity is `phone_normalized` only.
- address identity is a backend-generated deterministic hash from normalized
  `province`, `city`, and `address`.

### Authorization Surface

- permissions are feature-owned and independent between customer and address
  operations;
- address defaulting uses a dedicated permission;
- nested ownership is enforced by customer-scoped address resolution instead of
  public/global address lookup.

### Race Strategy

- customer create/resolve: rely on unique `phone_normalized` plus transactional
  duplicate-key recovery;
- customer delete/restore: transactional parent + address lifecycle handling;
- address create/update/restore/set-default: transactional locking on the
  customer row plus relevant active/deleted addresses for the same customer;
- all address-mutating workflows use one lock order: lock the customer row
  first, then lock relevant address rows ordered by `id`, apply the invariant,
  and commit;
- generated-column uniqueness is intentionally not chosen for this first pass
  because the feature can satisfy its invariants with simpler, portable
  row-locking on a bounded per-customer set.

### Query Strategy

- `GET /api/v1/admin/customers` uses a dedicated Query object backed by
  allow-listed filters and sorts;
- address lists remain customer-scoped and lightweight rather than introducing
  a second complex index query.

### Existing Schema and Code Integration Strategy

The approved model is the target state, not permission to recreate existing
artifacts.

Implementation rules:

- when the tables do not exist, create the approved baseline migrations;
- when the tables already exist, create forward-only alteration migrations;
- inspect and preserve existing primary keys and external references;
- backfill normalized phone/address identity values before adding non-null or
  unique constraints;
- reconcile duplicate legacy values before enabling unique indexes;
- preserve valid existing routes and classes when they already satisfy the
  contract;
- replace conflicting behavior without changing the approved public API;
- remove obsolete code only after reference checks and passing regression tests.

The implementation report must record any legacy mismatch and the migration
used to resolve it.

## Project Structure

### Documentation (this feature)

```text
specs/002-customers-addresses/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── openapi.yaml
└── tasks.md              # created later by /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Actions/
│   ├── Customers/
│   │   ├── CreateCustomerAction.php
│   │   ├── UpdateCustomerAction.php
│   │   ├── DeleteCustomerAction.php
│   │   ├── RestoreCustomerAction.php
│   │   └── ResolveGuestCustomerAction.php
│   └── CustomerAddresses/
│       ├── CreateCustomerAddressAction.php
│       ├── UpdateCustomerAddressAction.php
│       ├── DeleteCustomerAddressAction.php
│       ├── RestoreCustomerAddressAction.php
│       ├── SetDefaultCustomerAddressAction.php
│       └── ResolveGuestCustomerAddressAction.php
├── Http/
│   ├── Controllers/Api/V1/Admin/Customers/
│   ├── Requests/Api/V1/Admin/Customers/
│   └── Resources/Api/V1/Admin/Customers/
├── Models/
│   ├── Customer.php
│   └── CustomerAddress.php
├── Queries/Customers/
│   └── CustomerIndexQuery.php
├── Services/Customers/
│   ├── PhoneNumberService.php
│   ├── AddressNormalizationService.php
│   ├── CustomerMatchingService.php
│   └── CustomerAddressService.php
└── Support/

database/
├── factories/
│   ├── CustomerFactory.php
│   └── CustomerAddressFactory.php
├── migrations/
│   ├── *_create_customers_table.php
│   └── *_create_customer_addresses_table.php
└── seeders/
    └── CustomerPermissionsSeeder.php

routes/
├── api.php
└── api/v1/
    ├── admin.php
    └── auth.php

tests/
├── Architecture/
│   └── CustomerFeatureArchitectureTest.php
├── Feature/Api/V1/Admin/Customers/
│   ├── CustomerApiTest.php
│   ├── CustomerAddressApiTest.php
│   └── CustomerAuthBoundaryTest.php
├── Feature/Domain/Customers/
│   ├── CustomerMatchingTest.php
│   └── CustomerAddressMatchingTest.php
└── Concurrency/Customers/
    └── CustomerCriticalConcurrencyTest.php
```

**Structure Decision**:  
Use Actions only for workflows with normalization, lifecycle changes, guest
resolution, or default-address orchestration. Keep list-query complexity in one
customer Query object. Avoid per-resource Policies unless a real scoped
authorization rule emerges beyond permission middleware and customer-scoped
address lookup.

## Artifact Outputs

### Phase 0

- existing-code audit inventory and reuse/update/replace decisions
- [research.md](./research.md): final design choices for phone parsing,
  pagination/query surface, address uniqueness/default strategy,
  authorization/scoping, and safe integration with the existing codebase

### Phase 1

- [data-model.md](./data-model.md): entity definitions, invariants, transitions,
  and transaction boundaries
- [contracts/openapi.yaml](./contracts/openapi.yaml): protected Feature 002 API
  contract surface
- [quickstart.md](./quickstart.md): runnable validation guidance and expected
  verification commands

## Complexity Tracking

No constitution exception is proposed for this feature.

| Violation | Why Needed | Risk and Mitigation | User Approval |
|-----------|------------|---------------------|---------------|
| None | Not applicable | Not applicable | Not applicable |
