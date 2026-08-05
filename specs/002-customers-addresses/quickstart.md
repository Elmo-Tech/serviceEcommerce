# Feature 002 Quickstart — Customers and Addresses

**Feature:** `002-customers-addresses`  
**Branch:** `002-customers-addresses`  
**Status:** Validation Guide

## 1. Purpose

This guide defines the validation scenarios and commands that should pass once
Feature 002 is implemented.

It is a runnable acceptance guide, not the implementation itself.

## 2. Prerequisites

- PHP and Composer available for the Laravel 13 project
- MySQL available for local and testing databases
- `.env` configured for a local development database
- `.env.testing` or equivalent testing environment configured for a dedicated
  MySQL test database
- Feature 001 administrator authentication already working
- Super Admin seeded with the new Feature 002 permissions

## 3. Existing Implementation Audit

Create a Git checkpoint and inspect the existing implementation before running
new migrations or generating files.

```powershell
git status
git add .
git commit -m "chore: checkpoint before Feature 002 implementation"

php artisan route:list --path=api/v1/admin/customers
git grep -n "Customer"
git grep -n "CustomerAddress"
git grep -n "customers"
git grep -n "customer_addresses"
```

Also inspect the actual MySQL schema and migration history.

Record:

```text
existing artifact
current behavior/schema
target behavior/schema
action: reuse | update | replace | delete-if-unreferenced | create-if-missing
```

Stop before implementation when any of these are unresolved:

- duplicate table or route risk
- an executed migration would need editing
- legacy data cannot be mapped safely
- another module depends on a class or field planned for deletion
- existing duplicate normalized phone/email/address values block constraints

Do not create parallel customer/address tables or destroy existing data.

## 4. Setup Commands

From the repository root:

```powershell
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder
php artisan db:seed --class=Database\\Seeders\\SuperAdminSeeder
```

If Feature 002 permissions are seeded separately during implementation:

```powershell
php artisan db:seed --class=Database\\Seeders\\CustomerPermissionsSeeder
```

## 5. Contract References

- API contract: [contracts/openapi.yaml](./contracts/openapi.yaml)
- Data model: [data-model.md](./data-model.md)
- Feature spec: [spec.md](./spec.md)

## 6. Validation Scenarios

### Scenario A — Create customer with Egyptian local phone

1. Authenticate through Feature 001 and obtain an access token.
2. Send `POST /api/v1/admin/customers` with:

```json
{
  "name": "Mohamed Hassan",
  "email": "mohamed@example.com",
  "phone": "01001234567",
  "address": {
    "phone": "01001234567",
    "province": "Cairo",
    "city": "Nasr City",
    "address": "Nasr City | Street 10",
    "notes": "Ring bell"
  }
}
```

Expected outcome:

- `201 Created`
- `success=true`
- approved customer detail resource returned
- response includes `isDeleted`, `addressesCount`, and `addresses`
- when the nested `address` object is sent, the saved address is returned as
  the initial default address
- `phoneNormalized` is persisted internally but not exposed

### Scenario B — Reject duplicate normalized phone or duplicate non-null email

1. Create a first customer successfully.
2. Attempt a second create using:
   - the same phone in a different display format, or
   - the same email with different case/spacing.

Expected outcome:

- `422 Unprocessable Entity`
- stable code:
  - `CUSTOMER_PHONE_ALREADY_EXISTS`, or
  - `CUSTOMER_EMAIL_ALREADY_EXISTS`

### Scenario C — Manage addresses and default switching

1. Create the first address for a customer.
2. Confirm it becomes default automatically.
3. Create a second address with `isDefault=true`.
4. Confirm the old address loses default status and the new address becomes the
   only active default.

Expected outcome:

- all operations succeed with the shared success envelope
- exactly one active default address remains

### Scenario D — Enforce the 20-active-address limit

1. Create 20 active addresses for one customer.
2. Attempt to create or restore one more active address.

Expected outcome:

- `422 Unprocessable Entity`
- code `CUSTOMER_ADDRESS_LIMIT_EXCEEDED`

### Scenario E — Nested ownership protection

1. Create customer A and customer B.
2. Create an address for customer A.
3. Attempt to access that address through customer B’s nested route.

Expected outcome:

- `404 Not Found`
- stable code `CUSTOMER_ADDRESS_NOT_FOUND`

### Scenario F — Restore deleted customer and address

1. Soft-delete a customer and one address.
2. Restore the customer.
3. Confirm deleted addresses are not restored automatically.
4. Restore an address explicitly.

Expected outcome:

- customer restore succeeds
- address remains deleted until explicitly restored
- restored address respects duplicate/default/limit rules

### Scenario G — Guest-resolution matching behavior

Run focused domain tests proving:

- active customer reuse by normalized phone
- deleted customer restore by normalized phone
- active address reuse by canonical address identity
- deleted address restore by canonical address identity
- no auto-overwrite of saved customer name/email or address notes

## 7. Automated Verification Commands

Run the focused suites first:

```powershell
php artisan test tests\\Feature\\Api\\V1\\Admin\\Customers
php artisan test tests\\Feature\\Domain\\Customers
php artisan test tests\\Concurrency\\Customers
php artisan test tests\\Architecture\\CustomerFeatureArchitectureTest.php
```

Then run project quality gates:

```powershell
vendor\\bin\\pint --test
vendor\\bin\\phpstan analyse
php artisan test
```

## 8. Expected Verification Evidence

Implementation is ready for completion when:

- protected customer routes work with a valid Feature 001 access token
- missing/invalid token returns `401 UNAUTHENTICATED`
- inactive admin returns `403 USER_INACTIVE`
- missing permission returns `403 FORBIDDEN`
- customer list, customer detail, and address Resources match
  `contracts/openapi.yaml`
- customer and address resources never expose `phoneNormalized` or
  `addressHash`
- list defaults are `filter[status]=active`, `sort=-createdAt`, and
  `perPage=20`
- address lists support `filter[status]=active|deleted|all`
- shared `403` documentation and behavior distinguish `USER_INACTIVE` from
  `FORBIDDEN`
- customer restore documents and returns the approved duplicate-phone/email
  conflict outcome when uniqueness cannot be restored
- duplicate canonical customer and address identities are rejected or resolved
  correctly
- default-address and delete/restore invariants hold under MySQL concurrency
- Postman collection includes the protected customer and address endpoints
- Pint, PHPStan/Larastan, and Pest pass

## 9. Manual Notes

- This feature must not introduce any customer-authentication endpoint.
- This feature must not accept or return access or refresh tokens.
- This feature must not add public customer CRUD routes.
