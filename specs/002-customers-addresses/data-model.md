# Feature 002 Data Model — Customers and Addresses

**Feature:** `002-customers-addresses`  
**Date:** 2026-07-29  
**Status:** Design Baseline

## 1. Overview

Feature 002 introduces two persisted domain entities:

- `Customer`
- `CustomerAddress`

It also defines one reusable internal domain contract:

- customer/address resolution for future guest-order workflows

The model must preserve:

- one canonical customer per normalized phone
- one optional unique normalized email per customer
- multiple addresses per customer with a maximum of 20 active addresses
- zero default addresses when no active address exists
- exactly one active default address whenever active addresses exist
- soft deletion and controlled restoration
- future order snapshot stability

## 2. Entity: Customer

### Purpose

Represents an unauthenticated guest customer record managed by administrators
and reused by future guest-order workflows.

### Table

`customers`

### Core Fields

| Field | Type | Nullable | Notes |
|---|---|---:|---|
| `id` | unsigned big integer | No | Primary key |
| `name` | string(150) | No | Plain text, trimmed |
| `email` | string(255) | Yes | Stored normalized; unique when non-null |
| `phone` | string(30) | No | Display/international format |
| `phone_normalized` | string(20) | No | Canonical E.164 identity |
| `created_at` | timestamp | No | Standard timestamps |
| `updated_at` | timestamp | No | Standard timestamps |
| `deleted_at` | timestamp | Yes | Soft delete marker |

### Relationships

- `Customer` has many `CustomerAddress`
- future `Order` entities may reference `customer_id` for operational linkage,
  while preserving immutable customer snapshots separately

### Invariants

- `phone_normalized` is required and unique
- `email` is optional and unique when non-null
- customer records never carry authentication fields
- customer matching uses only `phone_normalized`
- customer delete is soft delete only
- customer restore does not automatically restore all addresses

### Normalization Rules

- `name`: trim outer whitespace
- `email`: trim, lowercase, convert empty string to `null`
- `phone`: backend-generated international display format
- `phone_normalized`: backend-generated E.164 format

### Excluded Fields

- password
- email verification fields
- role or permission fields
- active status flag
- preferred locale
- notes
- avatar

## 3. Entity: CustomerAddress

### Purpose

Represents a reusable service address belonging to one customer.

### Table

`customer_addresses`

### Core Fields

| Field | Type | Nullable | Notes |
|---|---|---:|---|
| `id` | unsigned big integer | No | Primary key |
| `customer_id` | unsigned big integer | No | FK to `customers.id` |
| `label` | string(100) | Yes | Presentation metadata only |
| `phone` | string(30) | No | Display/international format |
| `phone_normalized` | string(20) | No | E.164 format for the address phone |
| `country_code` | char(2) | No | Uppercase ISO alpha-2 |
| `city` | string(150) | No | Plain text |
| `area` | string(150) | Yes | Plain text or `null` |
| `street` | string(255) | No | Plain text |
| `notes` | text | Yes | Plain text or `null` |
| `address_hash` | char(64) | No | Backend-generated deterministic identity |
| `is_default` | boolean | No | Default `false` |
| `created_at` | timestamp | No | Standard timestamps |
| `updated_at` | timestamp | No | Standard timestamps |
| `deleted_at` | timestamp | Yes | Soft delete marker |

### Relationships

- `CustomerAddress` belongs to one `Customer`

### Invariants

- belongs to exactly one customer
- cannot be moved between customers by update
- first active address becomes default automatically
- no more than 20 active addresses per customer
- zero default addresses when no active address exists
- exactly one active default address whenever active addresses exist
- `address_hash` is generated only from canonical location identity
- deleted addresses do not count toward the active-address limit

### Address Identity Inputs

The backend-generated identity uses only:

- `country_code`
- normalized `city`
- normalized `area` (or empty canonical value)
- normalized `street`

### Address Identity Exclusions

The backend-generated identity does not include:

- label
- phone
- notes
- customer-entered display formatting

### Excluded Fields

- recipient name
- building
- floor
- apartment
- postal code

## 4. Derived and Internal Domain Concepts

### 4.1 Customer status filter

There is no dedicated persisted status field. List filtering derives status from
soft-delete state:

- `active` => `deleted_at IS NULL`
- `deleted` => `deleted_at IS NOT NULL`
- `all` => include both

### 4.2 Address default state

Default is persisted by `is_default`, but only active addresses may be default
in the final consistent state.

### 4.3 Guest resolution result

The future guest-order workflow will need an internal result containing:

- resolved customer id
- resolved or created customer instance
- resolved address id when applicable
- whether the record was reused, restored, or created

This is an internal application contract, not a public API resource.

## 5. Index and Constraint Design

### Customer

- primary key on `id`
- unique index on `phone_normalized`
- unique index on `email`
- index on `name`
- index on `created_at`
- index on `deleted_at`

### CustomerAddress

- primary key on `id`
- foreign key `customer_id -> customers.id`
- index on `customer_id`
- index on `(customer_id, deleted_at)`
- index on `(customer_id, is_default, deleted_at)`
- index on `(customer_id, address_hash)`
- index on `country_code`
- index on `city`

### Constraint strategy

- database uniqueness is the final protection for:
  - `customers.phone_normalized`
  - non-null `customers.email`
- address duplicate prevention is enforced through transaction-safe lookup and
  locking over the bounded per-customer address set
- one-default-address enforcement is handled through transactional state
  changes over the same bounded set

## 6. Lifecycle and State Transitions

### 6.1 Customer lifecycle

```text
Active
  -> Deleted    (soft delete; active addresses soft-deleted in same transaction)
Deleted
  -> Restored   (customer restored only; addresses remain deleted unless
                 restored explicitly or matched later)
```

### 6.2 CustomerAddress lifecycle

```text
Active non-default
  -> Active default
  -> Deleted

Active default
  -> Active non-default
  -> Deleted (with fallback default recalculation)

Deleted
  -> Restored active non-default or default, depending on active-address state
```

### 6.3 Guest resolution lifecycle

```text
submitted customer phone
  -> normalize
  -> resolve active customer
  -> else restore matching deleted customer
  -> else create customer

submitted address identity
  -> normalize
  -> resolve active address for resolved customer
  -> else restore matching deleted address
  -> else create address
```

## 7. Transaction Boundaries

The following operations require explicit transaction protection:

- customer create with normalized-phone race handling
- customer soft delete plus active-address soft delete
- customer restore
- address create when `isDefault=true`
- address update when identity or default state can change
- address delete when the deleted row is currently default
- address restore when limit/default recalculation applies
- set-default command
- guest customer resolution when a deleted or duplicate candidate exists
- guest address resolution when a deleted or duplicate candidate exists

## 8. Concurrency-Sensitive Operations

### Customer same-phone race

Goal: one canonical customer per normalized phone.

Protection:

- normalize before write
- create inside transaction
- rely on unique `phone_normalized`
- on duplicate-key collision, reload canonical customer and continue

### Default-address race

Goal: one active default address when active addresses exist.

Protection:

- lock customer row
- lock relevant active addresses for that customer
- clear old default(s)
- set intended default
- commit one consistent final state

### Address duplicate race

Goal: no duplicate active address identity for one customer.

Protection:

- lock the customer row first
- lock active and relevant deleted addresses ordered by `id`
- reuse or restore when a match exists
- otherwise create one new address
- commit only after duplicate, active-limit, and default-address invariants are
  valid

## 9. Existing Schema Compatibility and Migration Safety

The implementation must inspect the current MySQL schema and migration history
before creating migrations.

### When tables do not exist

Create the approved baseline `customers` and `customer_addresses` tables.

### When tables already exist

Use forward-only alteration migrations and preserve existing records.

Required sequence where applicable:

1. inspect existing columns, types, indexes, foreign keys, and soft-delete state;
2. map legacy fields to the approved target fields;
3. add nullable/backfill-safe columns first;
4. normalize and validate existing phone, email, and address identity values;
5. reconcile legacy duplicates before adding unique constraints;
6. add or correct indexes and foreign keys;
7. enforce non-null constraints only after successful backfill;
8. remove obsolete structures only through an explicitly approved,
   data-preserving migration.

Never:

- recreate an existing table under a second name;
- edit a migration that may already have run in another environment;
- discard customer or address data to satisfy the new contract;
- delete an old Model or relationship before checking cross-module references.

The implementation audit must document whether each table is created or altered
and how existing data is preserved.

## 10. Snapshot Obligations

Feature 002 does not itself create orders, but it must preserve the future
snapshot boundary:

- changing customer `name`, `email`, or `phone` must not rewrite existing order
  customer snapshots
- changing or deleting an address must not rewrite existing order address
  snapshots
- restoring customers or addresses must not mutate historical order records

## 11. Validation Baseline

### Customer

- `name`: required, plain text, max 150
- `email`: optional, normalized, unique when non-null
- `phone`: required, valid libphonenumber-compatible parse, no extension
- `phoneCountryCode`: optional ISO alpha-2 hint when needed for local parsing

### CustomerAddress

- `label`: optional, max 100
- `phone`: required, valid libphonenumber-compatible parse, no extension
- `phoneCountryCode`: optional parse hint when needed
- `countryCode`: required, uppercase ISO alpha-2
- `city`: required, plain text, max 150
- `area`: optional, plain text, max 150, empty => `null`
- `street`: required, plain text, max 255
- `notes`: optional, plain text, max 1000, empty => `null`
- `isDefault`: optional boolean on create/update where supported

## 12. Read Model Expectations

### Customer Resource

Safe public admin output includes:

- `id`
- `name`
- `email`
- `phone`
- `createdAt`
- `updatedAt`
- soft-delete metadata only if the contract allows it

Never expose:

- `phoneNormalized`
- auth fields
- internal status flags not in contract

### CustomerAddress Resource

Safe output includes:

- `id`
- `label`
- `phone`
- `countryCode`
- `city`
- `area`
- `street`
- `notes`
- `isDefault`
- `createdAt`
- `updatedAt`

Never expose:

- `phoneNormalized`
- `addressHash`
- foreign-customer identifiers beyond the nested route context unless the
  contract explicitly needs them
