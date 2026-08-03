# Service Commerce Backend — Authorization Standards

> **Scope:** Administrator roles, permissions, route protection, resource
> authorization, and privilege enforcement for the Laravel 13 Service Commerce
> Backend.
>
> **Authorization Package:** `spatie/laravel-permission`
>
> **Status:** Project-wide mandatory standard.

---

## 1. Purpose

This document defines the mandatory authorization rules for the Service
Commerce Backend.

It covers:

- administrator roles
- feature permissions
- permission naming
- permission creation
- Super Admin permission assignment
- route permission middleware
- Policies
- resource-level authorization
- nested-resource authorization
- command authorization
- attachment access
- order pricing
- order state operations
- authorization error responses
- localization
- database integrity
- caching
- seeders
- testing
- code review

Authentication answers:

```text
Who is the caller?
```

Authorization answers:

```text
What is the authenticated caller allowed to do?
```

These responsibilities MUST remain separate.

---

## 2. Related Documents

Authorization implementation MUST comply with:

- `AGENTS.md`
- `.specify/memory/constitution.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- `docs/02-standards/api-standards.md`
- `docs/02-standards/code-standards.md`
- `docs/02-standards/database-standards.md`
- `docs/02-standards/localization-standards.md`
- `docs/02-standards/authentication-standards.md`
- the active Feature specification
- the active `plan.md`
- the active `tasks.md`

When these documents conflict, implementation MUST stop until the conflict is
resolved.

Feature specifications define the exact operations requiring permissions.

This standard defines how those permissions are designed and enforced.

---

## 3. Final Authorization Decisions

```text
Authorization package: spatie/laravel-permission
Initial roles: super-admin only
Initial user management: Not included
Permission creation: Incremental with each Feature
Route protection: Spatie permission middleware
Super Admin access: Explicit assignment of every registered permission
Hidden role/type bypass: Not used as the primary authorization mechanism
Permission naming: Stable lowercase dot notation
Unauthenticated response: HTTP 401
Unauthorized response: HTTP 403
Missing resource response: HTTP 404
Supported message locales: ar and en
```

Important distinction:

```text
users.type = 0
```

classifies an administrator account.

It MUST NOT be used as the authorization decision.

Authorization decisions use:

- Spatie roles
- Spatie permissions
- route middleware
- Policies
- explicit resource and workflow checks

---

## 4. Initial Role Model

The MVP has one role:

```text
super-admin
```

Rules:

- The first Super Admin is created by the approved Seeder.
- The Super Admin receives every currently registered permission.
- There is no administration UI for creating users or roles in the initial MVP.
- There is no endpoint for assigning roles or permissions.
- There is no public role-management API.
- Future roles require a dedicated approved Feature.
- Future roles MUST use the same permission identifiers rather than inventing a
  parallel authorization system.

The absence of additional roles does not justify skipping permission checks.

Every protected Feature MUST still define and enforce its permissions so future
roles can be introduced safely.

---

## 5. Super Admin Permission Strategy

### 5.1 Explicit Permission Assignment

The `super-admin` role MUST be explicitly assigned all currently registered
permissions through Spatie.

Approved concept:

```php
$superAdminRole->givePermissionTo($newPermissions);
```

or through an approved central synchronization workflow that preserves all
implemented permissions.

### 5.2 No Primary Gate Bypass

Do not use a broad hidden bypass such as:

```php
Gate::before(
    fn (User $user): ?bool =>
        $user->hasRole('super-admin') ? true : null
);
```

as the primary authorization design.

Reasons:

- route permission middleware must remain meaningful
- missing permission registration should be detectable
- tests should prove route-to-permission mapping
- future roles should reuse the same model
- Super Admin permissions should be visible through package APIs

A narrowly justified emergency or platform-level override requires an approved
architecture decision.

### 5.3 New Feature Behaviour

When a new Feature introduces permissions:

1. create the permission definitions idempotently
2. use the configured Spatie guard
3. assign the new permissions to `super-admin`
4. preserve all previously assigned permissions
5. reset Spatie's permission cache safely
6. test the new route protection
7. update the permission registry and documentation

Do not remove older permissions when adding a new Feature.

Feature seeders MUST NOT use an unsafe partial:

```php
$superAdminRole->syncPermissions($featurePermissions);
```

because it could remove permissions owned by other Features.

Prefer:

```php
$superAdminRole->givePermissionTo($featurePermissions);
```

A central aggregate seeder may use `syncPermissions()` only when it owns the
complete authoritative registry for all implemented Features.

---

## 6. Permission Guard

All roles and permissions must use one guard matching the authenticated
administrator model.

The exact guard is the configured project guard.

In a standard Sanctum application this is commonly:

```text
web
```

Rules:

- Inspect `config/auth.php`.
- Inspect `config/permission.php`.
- Inspect the installed Spatie package version.
- Do not assume `api` merely because the application exposes an API.
- Do not mix `web` and `api` permission guards for the same administrator
  model.
- Role and permission seeders use the same guard.
- Tests assert the configured guard.
- Guard mismatches must fail visibly rather than being bypassed.

Recommended central configuration:

```php
'guard_name' => config('auth.defaults.guard'),
```

The exact implementation must match the repository configuration.

---

## 7. Permission Naming Standard

Permission identifiers use stable lowercase dot notation.

Format:

```text
{resource}.{action}
```

Examples:

```text
customers.view
customers.create
customers.update
customers.delete
customers.restore

services.view
services.create
services.update
services.delete
services.restore

orders.view
orders.price
orders.change-status
orders.cancel
orders.reject
```

Multi-word resource or action segments use kebab-case:

```text
contact-messages.view
contact-messages.reply
featured-services.reorder
orders.change-status
orders.attachments.view
```

Rules:

- Permission names remain in English.
- Permission names are never translated.
- Permission names are never renamed casually.
- Permission names are not generated from localized labels.
- Permission names must describe one capability.
- Avoid ambiguous permission names such as:
  - `manage`
  - `access`
  - `admin`
  - `all`
  - `full-control`
- A narrowly scoped `.manage` permission may be used only for a truly atomic
  simple Feature whose approved operations are intentionally inseparable.
- Sensitive commands require separate permissions.
- Do not use numeric permission identifiers in API contracts.
- Do not expose internal permission database IDs as authorization values.

---

## 8. Permission Lifecycle

Permissions are introduced with the Feature that needs them.

Rules:

- Do not create every theoretical permission before the Feature exists.
- Do not create permission records for unapproved routes.
- Each Feature owns:
  - permission names
  - route mapping
  - Seeder or registry entry
  - tests
  - documentation
- Permission creation is idempotent.
- New permissions are assigned to `super-admin`.
- Removed Feature operations require an explicit deprecation and cleanup plan.
- Permission renames require a data migration.
- Do not delete a permission merely because no current route uses it without
  checking:
  - role assignments
  - external consumers
  - scheduled Jobs
  - documentation
  - deployment order

---

## 9. Recommended Permission Registry

A central registry may expose implemented permission names.

Example location:

```text
app/Support/Authorization/PermissionRegistry.php
```

Conceptual structure:

```php
final class PermissionRegistry
{
    public const DASHBOARD = [
        'dashboard.view',
    ];

    public const CUSTOMERS = [
        'customers.view',
        'customers.create',
        'customers.update',
        'customers.delete',
        'customers.restore',
    ];

    public static function all(): array
    {
        return array_values(array_unique([
            ...self::DASHBOARD,
            ...self::CUSTOMERS,
        ]));
    }
}
```

Rules:

- A registry is optional when feature-owned Seeders are clear and consistent.
- Do not create a registry that becomes a second authorization engine.
- The Spatie database remains the runtime authorization source.
- Registry values remain stable technical identifiers.
- Tests may compare implemented route permissions against the registry.

---

## 10. Authentication Routes and Permissions

Authentication self-service routes use authentication and active-account
middleware but normally do not require feature permissions.

Routes:

```http
POST  /api/v1/admin/auth/login
POST  /api/v1/admin/auth/refresh
POST  /api/v1/admin/auth/logout

GET   /api/v1/admin/auth/profile
PATCH /api/v1/admin/auth/profile
PUT   /api/v1/admin/auth/change-password

POST  /api/v1/admin/auth/forgot-password
POST  /api/v1/admin/auth/verify-forgot-password-code
POST  /api/v1/admin/auth/reset-password
```

Rules:

- Login, refresh, and forgot-password routes are public authentication routes.
- Logout, profile, profile update, and password change require valid active
  administrator authentication.
- Profile operations affect only the current authenticated user.
- Profile update cannot change roles, permissions, type, status, or email.
- Do not add permissions such as:
  - `auth.login`
  - `profile.view`
  - `profile.change-password`
  unless a future product requirement needs delegated restrictions.
- Authentication middleware remains mandatory.

---

## 11. Route Permission Middleware

Protected administration routes use Spatie's permission middleware.

Conceptual example:

```php
Route::middleware([
    'auth:sanctum',
    'admin.active',
    'permission:orders.view',
])->get('/orders', IndexOrderController::class);
```

Mutation example:

```php
Route::middleware([
    'auth:sanctum',
    'admin.active',
    'permission:orders.price',
])->put(
    '/orders/{order}/items/{orderItem}/price',
    PriceOrderItemController::class,
);
```

Rules:

- Authentication middleware runs before permission middleware.
- Active-user middleware runs before protected application behaviour.
- Each protected route declares the permission required by its operation.
- Do not rely only on controller checks when a stable route permission exists.
- Do not rely only on hidden dashboard buttons.
- Do not protect broad route groups with an unrelated permission.
- Read and mutation permissions remain separate.
- Sensitive command routes use their specific permission.
- Route middleware does not replace resource-level Policies.
- Route middleware does not replace business workflow validation.

### 11.1 Middleware Registration

Spatie middleware aliases must be registered according to:

- installed package version
- installed Laravel version
- repository bootstrap conventions

Common aliases include:

```text
role
permission
role_or_permission
```

Do not copy bootstrap syntax from another Laravel version without checking the
installed repository.

---

## 12. Permission Middleware Semantics

Use one required permission for one operation where practical.

Good:

```text
permission:services.update
```

Avoid broad OR checks such as:

```text
permission:services.update|services.delete
```

unless the operation intentionally accepts either capability.

Rules:

- Avoid implicit permission inheritance.
- `services.update` does not imply `services.delete`.
- `orders.change-status` does not imply `orders.cancel`.
- `orders.view` does not imply attachment download.
- `site-settings.update` does not imply access to orders.
- Multiple permissions on one route require an explicit product reason.
- Middleware parameters remain technical identifiers and are not translated.

---

## 13. Policies

Policies handle resource-specific authorization that middleware alone cannot
express.

Examples:

```text
ServicePolicy
CategoryPolicy
CustomerPolicy
OrderPolicy
ContactMessagePolicy
```

A Policy may verify:

- resource is within the approved administrative scope
- nested child belongs to route parent
- resource is not in a prohibited state
- deleted resource may be restored
- attachment belongs to the authorized order item
- operation is allowed for the resource type

Policies MUST NOT contain:

- long business workflows
- price calculations
- database transactions
- email dispatch
- file storage
- status-transition orchestration
- translation construction

Business state transitions belong in Actions or Services.

### 13.1 Middleware and Policy Together

Typical protected operation:

```text
permission middleware
-> route binding
-> Policy
-> Action
-> business workflow validation
```

Example:

```text
orders.price permission
-> OrderItem belongs to Order
-> Policy allows access to the item
-> pricing Action validates order state
```

---

## 14. Resource-Level Authorization

Route permission answers:

```text
May the actor perform this class of operation?
```

Resource authorization answers:

```text
May the actor perform it on this specific resource?
```

Rules:

- Numeric IDs are not authorization.
- Route model binding is not authorization.
- Resource existence is not authorization.
- A valid parent ID does not prove child ownership.
- A permission does not bypass nested-resource validation.
- Resources loaded through a protected route must still be constrained to the
  intended parent or query scope.
- Do not fetch a child globally and then assume the route parent is correct.

Good:

```php
$orderItem = $order->items()->findOrFail($orderItemId);
```

or use scoped route binding where appropriate.

Bad:

```php
$orderItem = OrderItem::findOrFail($orderItemId);
```

when the route also contains an order ID but membership is not checked.

---

## 15. Query Authorization

List queries MUST enforce authorization before pagination and output.

Rules:

- Apply permission middleware before entering the controller.
- Apply any resource scope before sorting and pagination.
- Do not retrieve all rows and filter unauthorized records in PHP.
- Do not include unauthorized aggregates.
- Dashboard counts must follow the same authorization scope.
- Search results must not leak unauthorized records.
- Relationship includes must not expose unrelated or restricted data.
- Count endpoints require the same view permission as their underlying
  resource unless a dedicated permission is approved.

The initial `super-admin` role sees all administrative records because it owns
all current permissions.

Future roles may introduce narrower scopes through Feature specifications.

---

## 16. Dashboard Authorization

Recommended permission:

```text
dashboard.view
```

This permission protects the administration dashboard summary APIs.

Rules:

- Dashboard cards are backend data, not public metadata.
- Each metric must be computed from authorized data.
- Dashboard permission does not imply CRUD access to the underlying resource.
- A future role may view dashboard metrics without receiving mutation
  permissions.
- Sensitive report details may require a separate reporting permission.

Possible future permission:

```text
reports.view
```

Do not create it until an approved reporting Feature exists.

---

## 17. Customer Permissions

Recommended permissions when the Customers Feature is implemented:

```text
customers.view
customers.create
customers.update
customers.delete
customers.restore
```

Rules:

- `customers.view` protects list and detail.
- `customers.create` protects administrator-created customers.
- `customers.update` protects editable customer and address data according to
  the Feature contract.
- `customers.delete` protects approved soft-delete or deactivation behaviour.
- `customers.restore` protects restoration.
- Physical deletion of customers referenced by orders is not authorized through
  ordinary CRUD.
- Permission does not allow changing historical order snapshots.
- Customer address nested routes must verify the address belongs to the
  customer.
- No customer authentication permission is introduced.

If the Feature uses `deactivate` rather than `delete`, use:

```text
customers.deactivate
customers.restore
```

The Feature specification decides the exact stable names before implementation.

---

## 18. Category and Subcategory Permissions

Recommended permissions when the Catalogue Categories Feature is implemented:

```text
categories.view
categories.create
categories.update
categories.delete
categories.restore
```

These permissions cover root categories and nested subcategories.

Rules:

- A separate `subcategories.*` namespace is unnecessary unless the Feature
  provides independently delegated operations.
- Creating a subcategory uses `categories.create`.
- Updating a subcategory uses `categories.update`.
- Deleting a subcategory uses `categories.delete`.
- Permission does not bypass:
  - two-level hierarchy rule
  - circular-reference prevention
  - active-state checks
  - related-service restrictions
- Restore is separate from update.
- Reordering may use:

```text
categories.reorder
```

when the Feature introduces a dedicated reorder endpoint.

Do not include reorder under update when independent delegation is useful.

---

## 19. Service Permissions

Recommended base permissions when the Services Feature is implemented:

```text
services.view
services.create
services.update
services.delete
services.restore
```

Rules:

- `services.view` protects administration lists and details.
- `services.create` protects service creation.
- `services.update` protects core service changes.
- `services.delete` protects soft deletion.
- `services.restore` protects restoration.
- Delete and restore remain separate.
- Permission does not bypass service-to-subcategory validation.
- Permission does not allow updating historical order snapshots.
- Publication and availability changes may use `services.update` initially.
- A future approval workflow may introduce separate permissions.

Possible future permission:

```text
services.publish
```

Do not create it until publication delegation is a real requirement.

---

## 20. Service Media Permissions

When service media has separate endpoints, recommended permissions are:

```text
service-media.view
service-media.create
service-media.update
service-media.delete
service-media.reorder
```

A simpler Feature may authorize media reads through `services.view` while using
dedicated media mutation permissions.

Rules:

- Main-image replacement is not implied by unrelated service access.
- Video replacement follows the same permission family.
- File validation and storage safety remain mandatory.
- Permission does not bypass one-main-image or one-video constraints.
- Delete permission is separate from update.
- Reorder is separate when exposed through a dedicated endpoint.

The exact set is created with the Service Media Feature.

---

## 21. Service Specification Permissions

Recommended permissions:

```text
service-specifications.view
service-specifications.create
service-specifications.update
service-specifications.delete
service-specifications.reorder
```

For a simple nested implementation, view may be covered by `services.view`.

Rules:

- Feature specifications choose the smallest useful permission set.
- Do not collapse delete into update.
- Reorder remains separate when it has a dedicated command.
- Permission does not allow modifying unrelated service records.

---

## 22. Service Option Permissions

Recommended permissions:

```text
service-options.view
service-options.create
service-options.update
service-options.delete
service-options.reorder
```

Rules:

- Option-group and option-value operations may share this namespace.
- Nested ownership must be validated:
  - group belongs to service
  - value belongs to group
- Permission does not bypass availability, pricing, or selection-type rules.
- Delete remains separate from update.
- Public option output requires no administrator permission.

---

## 23. Service Order Question Permissions

Recommended permissions when the Service Order Questions Feature is
implemented:

```text
service-questions.view
service-questions.create
service-questions.update
service-questions.delete
service-questions.reorder
```

These permissions apply to:

- question definitions
- bilingual labels
- help text
- placeholders
- required state
- validation configuration
- question choices

Rules:

- Choice mutations use the corresponding question permission unless the
  Feature explicitly needs independent delegation.
- Delete is separate from update.
- Reorder is separate when a dedicated endpoint exists.
- Permission does not bypass:
  - question-to-service ownership
  - choice-to-question ownership
  - supported input types
  - allow-listed validation configuration
  - bilingual completeness
  - historical snapshot rules
- Customer answers are not editable through service-question permissions.
- Order answer visibility belongs to order permissions.

---

## 24. Order Permissions

Orders require granular permissions because operations have different business
risk.

Recommended permissions:

```text
orders.view
orders.price
orders.change-status
orders.cancel
orders.reject
```

Optional permissions introduced only when their endpoints exist:

```text
orders.update-notes
orders.export
```

Rules:

- `orders.view` protects order list and detail.
- `orders.price` protects quote-required item pricing.
- `orders.change-status` protects normal approved workflow transitions.
- `orders.cancel` protects cancellation.
- `orders.reject` protects rejection.
- Cancellation and rejection are not covered by generic status-change
  permission.
- Pricing is not covered by `orders.update`.
- Viewing is not enough to mutate.
- Permission does not bypass workflow rules.
- Terminal status protection remains mandatory.
- Cancellation and rejection reasons remain mandatory where required.
- Price and total recalculation remain backend-controlled.

---

## 25. Order Pricing Authorization

Required permission:

```text
orders.price
```

Typical route protection:

```text
auth:sanctum
admin.active
permission:orders.price
```

Resource and workflow checks still verify:

- order exists
- order item belongs to order
- item requires quote pricing
- order permits pricing
- item is not in a prohibited terminal state
- submitted amount is valid
- concurrent update is safe

The permission does not authorize:

- changing unrelated service catalogue prices
- editing customer answers
- changing order status automatically
- bypassing recalculation
- accepting frontend totals

---

## 26. Order Status Authorization

### 26.1 Normal Transitions

Required permission:

```text
orders.change-status
```

This permission covers approved ordinary transitions such as:

```text
pending -> awaiting_review
awaiting_review -> awaiting_payment
awaiting_payment -> confirmed
confirmed -> in_progress
in_progress -> completed
```

The exact transition map belongs in the Orders Feature.

### 26.2 Cancellation

Required permission:

```text
orders.cancel
```

Rules:

- cancellation reason is required
- terminal-state restrictions apply
- cancellation is not implied by `orders.change-status`
- cancellation is not implied by `orders.update`

### 26.3 Rejection

Required permission:

```text
orders.reject
```

Rules:

- rejection reason is required where approved
- rejection is not implied by `orders.change-status`
- terminal-state restrictions apply

### 26.4 Workflow Enforcement

Permissions answer whether the administrator may request the command.

The Order Status Transition Service decides whether the command is valid for the
current order.

---

## 27. Order Attachment Permissions

Required permissions:

```text
orders.attachments.view
orders.attachments.download
```

Rules:

- Metadata viewing and file downloading are distinct.
- `orders.view` does not automatically grant download.
- Attachment must belong to the requested order item.
- Order item must belong to the requested order.
- Raw filesystem paths are never returned.
- Download uses an authorized backend endpoint or approved temporary URL.
- Permission does not bypass:
  - storage existence
  - path validation
  - MIME handling
  - retention rules
  - execution prevention
- Public guest APIs do not expose attachment metadata or download access.

A future attachment-delete operation requires a separate permission:

```text
orders.attachments.delete
```

Do not introduce it until deletion behaviour is approved.

---

## 28. Contact Message Permissions

Recommended permissions:

```text
contact-messages.view
contact-messages.update
contact-messages.delete
```

Rules:

- `view` protects list and detail.
- `update` protects the explicit status mutation endpoint.
- `delete` protects permanent deletion.
- Permission does not claim delivery.
- Administrator free-form reply content is not automatically translated.
- Contact message locale controls the email wrapper where approved.

If the Feature exposes deletion, add:

```text
contact-messages.delete
```

only after retention behaviour is approved.

---

## 29. Site Settings Permissions

Recommended permissions:

```text
site-settings.view
site-settings.update
```

This is appropriate because site settings are normally a singular resource.

Rules:

- `view` protects administration retrieval.
- `update` protects approved setting changes.
- Do not create meaningless `create` or `delete` permissions for a singular
  settings record.
- Public site-settings output remains public and separately filtered.
- Permission does not allow changing unrelated Feature content.
- Secret configuration is not exposed through site settings.

---

## 30. Hero Section Permissions

Recommended permissions when implemented:

```text
hero-sections.view
hero-sections.create
hero-sections.update
hero-sections.delete
hero-sections.reorder
```

Rules:

- Reorder is separate when exposed.
- Delete remains separate.
- Public active hero content remains public.
- Administration permissions do not bypass bilingual completeness.
- Inactive content remains excluded from public output.

---

## 31. Testimonial Permissions

Recommended permissions:

```text
testimonials.view
testimonials.create
testimonials.update
testimonials.delete
testimonials.reorder
```

Rules:

- Testimonial content is administrator-managed in the MVP.
- Delete remains separate.
- Reorder remains separate when exposed.
- Public active testimonials require no administrator permission.
- Permission does not bypass rating or bilingual-content validation.

---

## 32. FAQ Permissions

Recommended permissions:

```text
faqs.view
faqs.create
faqs.update
faqs.delete
faqs.reorder
```

Rules:

- FAQs are not categorized in the MVP.
- Delete remains separate.
- Reorder remains separate when exposed.
- Public active FAQs remain public.
- Permission does not bypass bilingual-content requirements.

---

## 33. Featured Service Permissions

Recommended permissions:

```text
featured-services.view
featured-services.update
featured-services.reorder
```

Depending on endpoint design, creation and removal may use:

```text
featured-services.create
featured-services.delete
```

Recommended final design:

- use `create` to feature a service
- use `delete` to remove a service from the featured list
- use `reorder` to change manual order
- use `view` for administration retrieval
- avoid a vague `manage` permission

Full proposed set:

```text
featured-services.view
featured-services.create
featured-services.delete
featured-services.reorder
```

An `update` permission is unnecessary unless the pivot record has editable
fields beyond order.

Rules:

- Feature permission does not bypass service existence.
- Soft-deleted or ineligible services must not appear publicly.
- Best-selling services are computed and are not modified through featured
  permissions.

---

## 34. Public APIs

Public routes do not require administrator permissions.

Examples:

```text
public categories
public services
public service questions
public site content
public Contact Us submission
public guest order submission
```

Public routes still require:

- validation
- rate limiting where applicable
- catalogue eligibility checks
- file validation
- business-rule enforcement
- safe Resource output

Absence of administrator permission middleware does not mean absence of
security.

Guest order submission does not accept:

- user role
- permission
- administrator ID
- order status
- authoritative price
- attachment authorization flag

---

## 35. Jobs and Queued Authorization

HTTP permission middleware does not automatically apply to queued Jobs.

Rules:

- A Job must not perform a new privileged command based only on a submitted user
  ID.
- The authoritative operation should be authorized before persistence and Job
  dispatch.
- Jobs handling already-approved side effects reload authoritative records.
- A Job that performs a new sensitive mutation must call an approved
  authorization boundary.
- Do not serialize role or permission names from untrusted request input.
- Contact reply email Job sends a persisted, already-authorized reply.
- Jobs do not reassign permissions.
- Jobs do not grant roles.

---

## 36. Actions and Authorization

Actions may be invoked outside HTTP.

Therefore:

- Do not assume route middleware is always present.
- Sensitive Actions should accept an explicit actor when authorization is part
  of the use case.
- The application boundary must authorize before mutation.
- Resource and workflow invariants remain inside the Action or dedicated
  Service.
- Permission checks must use Spatie APIs or Laravel authorization components.
- Do not inspect raw role strings manually when package methods exist.

Conceptual Action signature:

```php
public function execute(
    User $actor,
    Order $order,
    OrderItem $orderItem,
    Money $price,
): OrderItem
```

The implementation may verify:

```php
$actor->can('orders.price');
```

when the Action is intentionally authorization-aware.

Alternatively, an authorized command handler may call a workflow-only Action.

The Feature plan must document the chosen boundary.

---

## 37. Controllers and Authorization

Controllers may:

1. receive a validated request
2. use route middleware authorization
3. call `$this->authorize(...)` for resource-specific checks
4. invoke an Action
5. return an API Resource

Controllers MUST NOT:

- check role names with raw string comparisons
- trust role or permission fields from requests
- hide authorization only in frontend behaviour
- authorize after performing the write
- return unauthorized raw models
- convert every authorization failure manually
- duplicate the global forbidden response structure

---

## 38. Form Requests and Authorization

Form Requests MAY implement:

```php
public function authorize(): bool
```

for resource-specific authorization when that is the established repository
pattern.

Rules:

- Do not use Form Request authorization as a replacement for route permission
  middleware.
- Do not query unrelated large datasets in `authorize()`.
- Do not perform writes.
- Do not perform workflow transitions.
- Do not use translated labels for authorization.
- Route-bound parent-child relationships may be checked safely.
- Return `false` only when the global handler will produce the approved
  localized response.

---

## 39. API Resources and Field Authorization

Resources expose only approved fields.

Rules:

- Do not return all fields and expect the React dashboard to hide unauthorized
  content.
- Prefer separate endpoints or explicit conditional fields for sensitive data.
- Attachment download URLs require download permission.
- Raw storage paths are never returned.
- Customer answers appear only in authorized order-detail output.
- Permission names may be returned to the authenticated administrator so the
  dashboard can render available actions.
- Returned permissions are technical identifiers.
- Dashboard visibility is convenience only; backend checks remain mandatory.

---

## 40. Authorization Response Standards

### 40.1 Unauthenticated

When no valid access token exists:

```text
HTTP 401 Unauthorized
code: UNAUTHENTICATED
```

Arabic example:

```json
{
  "success": false,
  "message": "يجب تسجيل الدخول للوصول إلى هذا المورد.",
  "code": "UNAUTHENTICATED",
  "errors": null
}
```

English example:

```json
{
  "success": false,
  "message": "Authentication is required to access this resource.",
  "code": "UNAUTHENTICATED",
  "errors": null
}
```

### 40.2 Authenticated but Forbidden

When the user is authenticated but lacks permission:

```text
HTTP 403 Forbidden
code: FORBIDDEN
```

Arabic example:

```json
{
  "success": false,
  "message": "ليس لديك صلاحية لتنفيذ هذا الإجراء.",
  "code": "FORBIDDEN",
  "errors": null
}
```

English example:

```json
{
  "success": false,
  "message": "You do not have permission to perform this action.",
  "code": "FORBIDDEN",
  "errors": null
}
```

Rules:

- Permission name is not exposed in normal public errors.
- Missing role details are not exposed.
- Response shape remains identical across locales.
- Global exception handling normalizes Spatie authorization exceptions.

---

## 41. HTTP 403 Versus 404

### 41.1 Use 403

Return `403 FORBIDDEN` when:

- the administrator is authenticated
- the route exists
- the resource is known or valid
- the administrator lacks the required permission
- the operation is forbidden by authorization

### 41.2 Use 404

Return `404 NOT_FOUND` when:

- the resource does not exist
- a nested child does not belong to the route parent
- scoped route binding cannot find the resource
- revealing an unrelated resource would expose data across an authorization
  boundary
- the Feature contract explicitly uses non-disclosure semantics

Example:

```text
/orders/10/items/999
```

returns `404` when item `999` does not belong to order `10`, even if item `999`
exists under another order.

### 41.3 Do Not Use 404 to Hide Every Permission Failure

Using `404` for every missing permission makes APIs harder to debug and creates
inconsistent contracts.

Default rule:

```text
missing permission -> 403
missing or foreign nested resource -> 404
```

---

## 42. Localization

Supported locales:

```text
ar
en
```

Rules:

- Authorization messages use translation keys.
- Permission identifiers remain English.
- Role identifiers remain English.
- Error codes remain English.
- HTTP status remains language-independent.
- API keys remain `camelCase`.
- Route middleware permission parameters remain untranslated.
- The frontend may display localized labels separately.
- Localized labels never replace technical permission identifiers.

Recommended translation keys:

```text
authorization.unauthenticated
authorization.forbidden
authorization.resource_not_found
authorization.inactive_user
```

Optional permission-label translations may live in:

```text
lang/ar/permissions.php
lang/en/permissions.php
```

Example metadata:

```json
{
  "name": "orders.price",
  "label": "تسعير الطلب"
}
```

The `name` remains authoritative.

---

## 43. Permission Labels

Permission labels are optional presentation metadata.

They may be used by:

- future role-management UI
- permission reference pages
- developer tooling
- administration menus

Rules:

- Labels support Arabic and English.
- Labels are not stored as authorization values.
- Spatie checks use the technical permission name.
- Label changes do not rename permissions.
- Missing label does not disable permission.
- Do not add label columns to Spatie tables unless an approved Feature requires
  database-managed labels.
- Translation files are preferred for the fixed two-language MVP.

---

## 44. Database Standards

Spatie owns its package tables, commonly including:

```text
roles
permissions
model_has_roles
model_has_permissions
role_has_permissions
```

Rules:

- Use package migrations compatible with the installed version.
- Do not create duplicate custom role tables.
- Do not create a `role` string column on users as a second source of truth.
- Do not create a JSON permissions column on users.
- Do not insert role or permission relationships manually through raw SQL in
  application code.
- Use Spatie APIs.
- Use one consistent guard.
- Add no team or tenant columns because the MVP is not multi-tenant.
- Do not enable Spatie Teams unless an approved architecture change requires
  it.
- Permission names must be unique per guard.
- Role names must be unique per guard.

---

## 45. Seeder Standards

### 45.1 Role Seeder

The role Seeder creates:

```text
super-admin
```

idempotently.

### 45.2 Feature Permission Seeders

Each implemented Feature may provide an idempotent Seeder.

Conceptual example:

```php
final class OrderPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'orders.view',
            'orders.price',
            'orders.change-status',
            'orders.cancel',
            'orders.reject',
            'orders.attachments.view',
            'orders.attachments.download',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                guardName: config('auth.defaults.guard'),
            );
        }

        Role::findByName(
            'super-admin',
            config('auth.defaults.guard'),
        )->givePermissionTo($permissions);
    }
}
```

The exact package method signature must match the installed version.

### 45.3 Cache Reset

After permission creation or changes:

```php
app(PermissionRegistrar::class)->forgetCachedPermissions();
```

Use the installed Spatie API.

### 45.4 Idempotency

Repeated seeding must:

- not create duplicate roles
- not create duplicate permissions
- not remove unrelated permissions
- preserve Super Admin access
- use the configured guard
- fail clearly on guard mismatch

### 45.5 Production Safety

Do not use `syncPermissions()` with a partial Feature list.

Do not silently rename permissions during seed.

Do not delete permissions during ordinary deployment without a migration plan.

---

## 46. Permission Cache

Spatie permission caching may be used according to package defaults.

Rules:

- Permission cache is different from application business-data caching.
- Redis is not required.
- Clear permission cache after Seeder or deployment permission changes.
- Do not clear the cache on every request.
- Tests should clear cache between permission mutations when needed.
- Do not manually cache authorization decisions in controllers.
- Do not store permission results in frontend-localStorage as authoritative
  state.

---

## 47. Role and Permission Assignment

The initial MVP assigns roles and permissions only through Seeders.

Rules:

- No role-assignment endpoint.
- No permission-assignment endpoint.
- No user-management screen.
- Profile update cannot modify role or permission state.
- Request payloads containing roles or permissions are rejected or ignored
  according to the endpoint contract.
- Mass assignment does not include role or permission fields.
- Future assignment requires:
  - dedicated Feature
  - permission matrix
  - authorization rules
  - audit requirements
  - tests
  - documentation

---

## 48. Future Role Design

Future roles may include examples such as:

```text
order-manager
catalog-manager
content-manager
support-agent
```

These are not part of the MVP.

When introduced:

- roles receive only required permissions
- least privilege is mandatory
- existing permission identifiers are reused
- no role receives automatic access based on `users.type`
- resource scopes are documented
- role assignment requires separate permissions
- privilege-escalation protection is tested
- Super Admin retains all implemented permissions

Do not create future roles before product approval.

---

## 49. Security Standards

- Enforce authorization on the backend.
- Use Spatie package APIs.
- Do not trust frontend permission lists.
- Do not trust hidden buttons.
- Do not trust user-supplied role names.
- Do not trust user-supplied permission names.
- Do not trust user IDs for assignment operations.
- Do not use `users.type` as permission.
- Do not use email domain as permission.
- Do not use numeric ID secrecy as authorization.
- Do not perform writes before authorization.
- Do not return unauthorized data and expect consumers to discard it.
- Do not log complete permission payloads unnecessarily.
- Do not expose role or permission database IDs as authorization tokens.
- Do not allow privilege mutation through mass assignment.
- Do not add wildcard permissions without approval.
- Do not grant broad permissions merely to fix a failed test.
- Do not catch authorization exceptions and return success.
- Do not disable middleware for convenience.

---

## 50. Permission Wildcards

Wildcard permissions are prohibited in the MVP.

Examples not allowed:

```text
services.*
orders.*
*
```

Reasons:

- harder least-privilege analysis
- accidental future privilege expansion
- weaker route-to-permission traceability
- confusing testing behaviour

A future wildcard design requires:

- approved architecture decision
- explicit package configuration
- documented matching semantics
- regression tests
- migration plan

---

## 51. Logging and Observability

Authorization-related logs MAY include safe context:

```text
request_id
user_id
route
permission
resource_type
resource_id
http_status
operation
```

Rules:

- Permission-denied logs use an appropriate level.
- Do not log access tokens.
- Do not log authorization headers.
- Do not log customer answer content.
- Do not log attachment contents.
- Do not expose permission-check internals in API responses.
- Repeated forbidden operations may be monitored for security.
- Normal permission denials must not flood production logs.

No general audit-log table is introduced by this standard.

A future authorization-management Feature may require append-only audit records.

---

## 52. Testing Requirements

Authorization requires automated Pest tests.

### 52.1 Role and Seeder Tests

- `super-admin` role is created
- role creation is idempotent
- permissions use the configured guard
- Feature permission Seeder is idempotent
- new Feature permissions are assigned to Super Admin
- old permissions remain assigned
- no duplicate permission records
- permission cache is reset
- partial Feature Seeder does not remove unrelated permissions
- no user-management routes exist

### 52.2 Authentication Boundary Tests

- unauthenticated protected request returns `401`
- invalid token returns `401`
- inactive administrator is blocked
- authenticated administrator continues to permission check
- self-profile routes require authentication
- profile routes do not require unrelated Feature permissions

### 52.3 Permission Middleware Tests

For every protected route:

- user with permission succeeds
- user without permission receives `403`
- stable code is `FORBIDDEN`
- Arabic message is correct
- English message is correct
- route declares the expected permission
- changing frontend permission state has no backend effect

Even though the MVP has only Super Admin, tests may create a temporary
permission-less administrator or test role to prove enforcement.

### 52.4 Resource Authorization Tests

- foreign nested order item returns `404`
- foreign service question returns `404`
- option value from another group is rejected
- attachment from another order item is rejected
- resource-specific Policy is enforced
- permission alone does not bypass parent-child validation
- unauthorized data is not returned in Resource output

### 52.5 Customer Permission Tests

When implemented:

- view
- create
- update
- delete or deactivate
- restore
- address ownership
- historical order snapshot protection

### 52.6 Category Permission Tests

When implemented:

- view
- create
- update
- delete
- restore
- reorder when applicable
- subcategory uses category permissions
- hierarchy rules remain enforced

### 52.7 Service Permission Tests

When implemented:

- view
- create
- update
- delete
- restore
- permission does not bypass subcategory ownership
- public routes remain public
- deleted service cannot be mutated through ordinary update

### 52.8 Service Question Permission Tests

When implemented:

- view
- create
- update
- delete
- reorder
- question belongs to service
- choice belongs to question
- permission does not bypass bilingual validation
- permission does not allow editing historical answers

### 52.9 Order Permission Tests

- `orders.view` permits list and detail only
- view does not permit pricing
- `orders.price` permits quote pricing
- price permission does not bypass order-item ownership
- `orders.change-status` permits approved normal transition
- change-status does not permit cancellation
- `orders.cancel` permits cancellation
- cancellation requires reason
- `orders.reject` permits rejection
- rejection requires approved reason
- permissions do not bypass terminal-state rules
- unauthorized pricing, cancellation, and rejection return `403`

### 52.10 Attachment Permission Tests

- metadata requires `orders.attachments.view`
- download requires `orders.attachments.download`
- order view alone does not download
- foreign attachment returns `404`
- raw path is never returned
- public guest cannot download through administration endpoint

### 52.11 Content Permission Tests

When implemented:

- site settings view/update
- hero CRUD and reorder
- testimonials CRUD and reorder
- FAQ CRUD and reorder
- featured-service create/delete/reorder
- each Feature permission is isolated
- one Feature permission does not grant another Feature access

### 52.12 Localization Tests

- unauthenticated Arabic response
- unauthenticated English response
- forbidden Arabic response
- forbidden English response
- codes remain unchanged
- permission names remain untranslated
- permission labels do not replace identifiers
- payload structure remains stable

### 52.13 Privilege-Escalation Tests

- profile update cannot assign roles
- profile update cannot assign permissions
- request role field is rejected or ignored as contracted
- request permission field is rejected or ignored
- users.type cannot grant permission
- missing middleware causes architecture test failure where implemented
- direct route access cannot bypass permission
- frontend-hidden operation can still be called only with backend permission

---

## 53. Architecture Tests

The repository SHOULD include architecture or route-contract tests that verify:

- all `/api/v1/admin/*` Feature routes use `auth:sanctum`
- active-user middleware protects authenticated administration routes
- protected routes declare a permission middleware
- authentication self-service routes are exempt only where documented
- public routes do not accidentally use administrator permissions
- permission strings exist in the registered Feature permission set
- no wildcard permission is introduced
- no `/api/v1/admin/users/*` routes exist in the initial MVP
- no role or permission mutation routes exist
- sensitive order commands use their dedicated permissions

The exact technique may inspect:

- Laravel route collection
- middleware lists
- permission registry
- Feature contract metadata

---

## 54. Documentation Requirements

Every protected Feature must document:

- permission names
- protected routes
- operation-to-permission mapping
- resource-level Policy checks
- `403` cases
- `404` non-disclosure cases
- localized messages
- Seeder changes
- tests
- future role considerations where relevant

API documentation must not imply that authentication alone grants access.

Feature specifications must distinguish:

```text
authentication requirement
permission requirement
resource requirement
workflow requirement
```

---

## 55. Permission Matrix Template

Each Feature should include a matrix similar to:

| Route | Operation | Permission | Policy/Scope | Failure |
|---|---|---|---|---|
| `GET /admin/orders` | List orders | `orders.view` | Authorized query scope | `403` |
| `GET /admin/orders/{order}` | View order | `orders.view` | Order Policy | `403/404` |
| `PUT /admin/orders/{order}/items/{item}/price` | Price item | `orders.price` | Item belongs to order | `403/404/409` |
| `PUT /admin/orders/{order}/status` | Change status | `orders.change-status` | Workflow Service | `403/409` |
| `POST /admin/orders/{order}/cancel` | Cancel | `orders.cancel` | Workflow Service | `403/409/422` |

The exact paths follow the Feature API contract.

---

## 56. Code Review Checklist

- [ ] Authentication and authorization are separated.
- [ ] Route uses the correct permission middleware.
- [ ] Permission name follows stable dot notation.
- [ ] Permission is created with the Feature.
- [ ] Permission uses the configured guard.
- [ ] Super Admin receives the new permission.
- [ ] Existing permissions are not removed.
- [ ] Spatie permission cache is reset after seeding.
- [ ] Delete and restore permissions are separate.
- [ ] Sensitive order commands use dedicated permissions.
- [ ] Order attachment view and download are separate.
- [ ] Resource parent-child ownership is validated.
- [ ] Permission does not bypass business workflow rules.
- [ ] Public routes expose only public data.
- [ ] Unauthenticated access returns `401`.
- [ ] Missing permission returns `403`.
- [ ] Missing or foreign nested resource returns `404`.
- [ ] Error code is stable.
- [ ] Arabic and English messages exist.
- [ ] Role and permission identifiers are not translated.
- [ ] No role or permission fields are mass assignable.
- [ ] No wildcard permission was introduced.
- [ ] No hidden role/type bypass was introduced.
- [ ] No user-management route was introduced.
- [ ] Tests cover allowed and forbidden behaviour.
- [ ] Documentation and permission matrix are updated.

---

## 57. Definition of Done

Authorization work is complete only when:

- Feature permissions are defined
- permissions are named consistently
- permissions are seeded idempotently
- Super Admin receives all implemented permissions
- routes use permission middleware
- resource-level authorization is implemented
- nested resources are scoped safely
- workflow checks remain enforced
- unauthenticated and forbidden responses follow the API standard
- Arabic and English messages exist
- permission identifiers remain stable
- tests prove success and denial
- no privilege-escalation path exists
- no unrelated permission is granted
- documentation is synchronized

---

## 58. Non-Negotiable Rules

- Use `spatie/laravel-permission`.
- The initial role is `super-admin`.
- Permissions are created incrementally with each Feature.
- Super Admin is explicitly assigned every implemented permission.
- Protected routes use Spatie permission middleware.
- Do not use `users.type` as authorization.
- Do not use a hidden Super Admin bypass as the primary design.
- Do not trust frontend permission state.
- Do not trust role or permission fields from requests.
- Do not translate permission names.
- Do not translate role names.
- Do not use wildcard permissions.
- Do not use one broad permission for unrelated capabilities.
- Delete and restore are separate permissions.
- Order pricing has a dedicated permission.
- Order status change has a dedicated permission.
- Order cancellation has a dedicated permission.
- Order rejection has a dedicated permission.
- Order attachment metadata and download have separate permissions.
- Missing authentication returns `401`.
- Missing permission returns `403`.
- Missing or foreign nested resources return `404`.
- Permission does not bypass resource ownership.
- Permission does not bypass workflow rules.
- Profile update cannot change roles or permissions.
- No user-management Feature exists in the initial MVP.
- No role-management API exists in the initial MVP.
- No permission-management API exists in the initial MVP.
- No raw permission database IDs are used as authorization values.
- No partial Feature Seeder removes permissions from other Features.
