# Research: Services Catalog

**Status:** Complete  
**Feature:** `004-services-catalog`

This document freezes the implementation decisions required before task generation. All decisions derive from the approved Feature 004 specification and existing project standards.

## Decision 1 — Store direct nullable category and subcategory foreign keys

**Decision:** `services` contains nullable `category_id` and `subcategory_id`.

**Why:** The feature must represent unclassified, category-only, and category-plus-subcategory services. A subcategory-only foreign key cannot represent category-only services.

**Rejected:** JSON/polymorphic classification and subcategory-only inference.

## Decision 2 — Use integer-backed PHP enums and TINYINT columns

**Decision:**

```text
ServicePriceType:          0 fixed, 1 start_from
ServicePricingInputType:   0 select, 1 multi_select, 2 radio, 3 checkbox
ServiceMediaType:          0 image, 1 video
ServiceOrderFieldType:     0 text (internal)
ServicePricingOptionType:  0 add_on (internal)
```

**Why:** Exact mappings are approved, stable, compact, and shared by validation, casts, resources, factories, and tests.

**Rejected:** MySQL native ENUM and external string enums.

## Decision 3 — Add an internal slug reservation table

**Decision:** Add `service_slug_reservations` with unique normalized `slug` and `service_id`.

**Why:** Two ordinary unique indexes on `services.slug_ar` and `services.slug_en` do not stop one service's Arabic slug from matching another service's English slug. Application-only pre-checks are also race-prone. The reservation table gives one cross-locale unique namespace enforced by MySQL.

**Behavior:**

- Store the unique set of both normalized locale slugs.
- One reservation is enough when both locale slugs normalize to the same value.
- Keep reservations when the service is soft deleted.
- Synchronize reservations and service columns in one transaction.
- Convert duplicate-key errors into field-level `422 VALIDATION_ERROR`.

**Rejected:** Advisory locks, a singleton lock row, and generated-column tricks that cannot compare values across two different unique columns reliably.

## Decision 4 — Use Actions only for orchestrated workflows

**Decision:** Actions own service create, classification update, restore, pricing-option nested actions, pricing-option delete, media upload/set-main/delete, and hierarchy deletion guards.

**Why:** These operations require transactions, ordered locks, multiple models, or filesystem compensation.

**Rejected:** Fat controllers and Actions for every trivial single-row CRUD operation.

## Decision 5 — Use Query classes for Admin and Public list behavior

**Decision:** Dedicated Query classes wrap `spatie/laravel-query-builder` allow-lists and locale-aware projections.

**Why:** Bilingual search, localized projection, classification filters, price range, trashed handling, and visibility shaping are too complex for controllers.

**Rejected:** Generic repository abstractions and repeated controller queries.

## Decision 6 — Parse nested multipart bracket notation directly

**Decision:** Create Service accepts nested multipart fields such as `pricingOptions[0][values][0][labelAr]` and `media[0][file]`. No JSON-string `payload` field is accepted.

**Why:** This is the approved contract and Laravel converts bracket notation into nested arrays while retaining uploaded files as `UploadedFile` instances.

**Implementation note:** Boolean and integer preparation must not stringify or replace nested file objects. Validation and permission checks happen before persistence.

## Decision 7 — Keep media on the approved public disk

**Decision:** Use the configured public disk, generated names, server-side MIME/extension/size/count validation, and public URLs through Resources.

**Why:** This preserves the approved MVP storage model and avoids new infrastructure.

**Rejected:** Private signed storage, temporary-upload systems, queues, and asynchronous cleanup.

## Decision 8 — Compensate filesystem writes synchronously

**Decision:** Track every file created by a transactional request. On any pre-commit failure, roll back the database and delete those newly created files.

**Ordering:**

- New writes may occur inside the DB transaction but are tracked.
- Existing files are never deleted before the corresponding database mutation commits.
- Media hard-delete commits the row/main-fallback mutation first, then deletes the physical file.

**Why:** MySQL transactions cannot roll back filesystem state.

## Decision 9 — Serialize all media writes by locking the parent service

**Decision:** Every media mutation locks the service row first and media rows by `id ASC` second.

**Why:** This single per-service serialization point guarantees count limits, one video, and one main image under concurrency without depending on unverified generated-column support.

**Rejected:** Count-then-insert without locking and relying only on application validation.

## Decision 10 — Use one hierarchy lock order everywhere

**Decision:**

```text
root categories by id ASC
→ subcategories by id ASC
→ services by id ASC
→ revalidate
→ mutate
→ commit
```

For service update/restore, read a snapshot, derive affected current/target hierarchy rows, lock in canonical order, lock the service, and revalidate. Retry in a bounded loop only when the locked state changed the affected set.

**Why:** A universal order prevents deadlocks and closes delete-versus-classification races.

## Decision 11 — Keep child configuration soft-deleted and media hard-deleted

**Decision:** Specifications, order fields, pricing options, and values use soft delete without restore APIs. Media deletion removes the row and file permanently. Service soft delete retains all child data and media.

**Why:** This matches the approved contract and preserves service restore behavior.

## Decision 12 — Use explicit query-supporting indexes, then verify with EXPLAIN

**Decision:** Add indexes only for approved list/filter/order and parent-scoped child queries. The initial index set is documented in `data-model.md`.

**Why:** The catalogue has bounded children but potentially large service lists. Indexes must serve known predicates without over-indexing writes.

## Decision 13 — Use alternate-locale slug fallback only for lookup

**Decision:** Public service detail and category/subcategory filters first match the request locale slug, then the alternate locale slug. Response content remains in the requested locale.

**Why:** This tolerates clients using the other localized URL without changing language projection.

**Rejected:** Switching response locale based on the matching slug.

## Decision 14 — Hide inactive classification without hiding the service

**Decision:** If an assigned category or subcategory later becomes inactive, an active service remains visible in All Services, but hidden classification records are omitted. Filtering by an inactive/deleted classification returns an empty collection.

**Why:** Services are valid without classification, so hierarchy visibility must not silently deactivate them.

## Decision 15 — Keep Feature 003 routes but extend their conflict contract

**Decision:** Feature 004 adds service dependency checks inside the existing Feature 003 category/subcategory delete workflows. It does not add duplicate category routes.

**Contract updates:**

```text
CATEGORY_HAS_SERVICES
SUBCATEGORY_HAS_SERVICES
```

**Why:** Ownership of category routes remains in Feature 003 while Feature 004 supplies the real service dependency.

## Decision 16 — Return fixed-precision money strings

**Decision:** API responses serialize `basePrice` and `priceAdjustment` as fixed two-decimal strings. Requests accept validated numeric values according to their media type (`multipart` or JSON).

**Why:** This avoids binary floating-point ambiguity and matches the shared API standard.
