# Specification Quality Checklist: Orders Management

**Purpose**: Validate specification completeness and quality before proceeding to planning  
**Created**: 2026-08-01  
**Feature**: [spec.md](../spec.md)  
**Readiness**: Ready for `/speckit.plan`

## Content Quality

- [x] Focused on user value, business rules, and externally observable behavior
- [x] All mandatory sections are completed
- [x] No unresolved `[NEEDS CLARIFICATION]` markers remain
- [x] Approved API contract details are included only where required to remove ambiguity
- [x] Scope and exclusions are explicit

## Requirement Completeness

- [x] Public and Admin order flows are independently testable
- [x] Customer phone normalization and deterministic resolution are specified
- [x] Address shape is fixed to `province`, `city`, and `address`
- [x] Order item limits, note limits, answer limits, and cancellation-reason limit are explicit
- [x] Backend pricing authority and discount calculations are explicit
- [x] Cumulative payment behavior and negative `remainingAmount` are explicit
- [x] Full order-status transition matrix is explicit
- [x] General editing, status changes, payment changes, and attachment download boundaries are separated
- [x] Item PATCH full-replacement and partial-update semantics are explicit
- [x] Attachment count, size, extensions, protected storage, and download rules are explicit
- [x] Idempotency replay and conflicting-key behavior are explicit
- [x] Admin filters, sorts, pagination defaults, and maximum are explicit
- [x] Conditional order hard-delete rules are explicit
- [x] Admin Create requires one customer source, while Admin PATCH may omit both customer keys
- [x] Public and Admin Create always use multipart form-data with bracket notation
- [x] Answer replacement validates against stored question snapshots

## Approved Feature Decisions

- [x] External domain enums use documented integer values
- [x] Order numbers use `ORD-YYYYMMDD-####`
- [x] Daily order-number date and sequence use UTC
- [x] Allocated order numbers are never reused
- [x] Order-item attachments use protected storage and protected download APIs
- [x] The address contract includes `province`, `city`, and `address`
- [x] Older contradictory shared-document wording is a planning sync task, not a specification blocker

## Authorization and Security

- [x] Order, item, attachment, status, payment, and delete permissions are separated
- [x] General Order PATCH with `status` requires both update and status permissions
- [x] Admin Create always requires `orders.create` and `order-items.create`
- [x] Admin Create with initial attachments additionally requires `order-item-attachments.create`
- [x] Nested Order -> Item -> Attachment ownership is required
- [x] Public responses do not disclose customer existence, raw paths, or public attachment URLs
- [x] Backend revalidates all client identifiers and calculations
- [x] File compensation and transaction boundaries are specified

## API and Localization

- [x] Public and Admin route areas are explicit
- [x] Multipart and JSON request boundaries are explicit
- [x] Both Public Create and Admin Create always use multipart form-data
- [x] Localized snapshots return one language through stable keys
- [x] `Content-Language` and `Vary: Accept-Language` behavior is specified
- [x] Every external integer enum mapping is defined
- [x] Status and Payment endpoint responsibilities are unambiguous

## Postman Documentation

- [x] Every header, path, query, body, multipart, and response key must be explained
- [x] Key type, required state, nullability, allowed values, default, meaning, and example are required
- [x] Every enum integer must include its human meaning
- [x] The full status transition matrix must be documented
- [x] Valid, validation, authorization, not-found, and conflict examples are required
- [x] Payment, negative remaining, fixed discount, percentage discount, and discount removal examples are required

## Verification and Readiness

- [x] Acceptance scenarios cover all primary user stories
- [x] Edge cases cover concurrency, partial address input, negative remaining amount, and file compensation
- [x] MySQL concurrency coverage is required
- [x] Attachment security and all-status protected download are testable
- [x] Success criteria are measurable
- [x] No material contradiction remains inside the Feature 005 specification
- [x] The specification is ready for `/speckit.plan`

## Notes

- UTC is the canonical timezone for order-number dates and API timestamps.
- On 2026-08-01, Feature 005 synchronization explicitly confirmed:
  30 files / 100 MB combined create-request limits, 3 files / 30 MB standalone
  Admin upload limits, `reserved_at` plus `completed_at` idempotency fields,
  `orderItemAnswerId` update semantics, `ORDER_NUMBER_SEQUENCE_EXHAUSTED`, and
  `App\Enums\HttpStatusCode` naming.
- Planning must propagate the approved Feature 005 decisions into any shared
  project document that still contains older wording.
- That documentation synchronization is part of planning and is not a blocker
  to generating the Feature 005 plan.
