# Data Model: Services Catalog

**Status:** Ready for Implementation  
**Database:** MySQL

## 1. Enum Mapping

| Domain | Column | API values |
|---|---|---|
| Service price | `services.price_type` | `0=fixed`, `1=start_from` |
| Pricing input | `service_pricing_options.input_type` | `0=select`, `1=multi_select`, `2=radio`, `3=checkbox` |
| Pricing option internal type | `service_pricing_options.option_type` | `0=add_on` |
| Order-field internal type | `service_order_fields.field_type` | `0=text` |
| Media type | `service_media.type` | `0=image`, `1=video` |

All use unsigned TINYINT columns and PHP integer-backed enums. Native MySQL ENUM is not used.

## 2. `services`

| Column | Type | Null | Default | Notes |
|---|---|---:|---|---|
| `id` | BIGINT UNSIGNED | no | auto | Primary key |
| `category_id` | BIGINT UNSIGNED | yes | null | Root category |
| `subcategory_id` | BIGINT UNSIGNED | yes | null | Child of `category_id` |
| `name_ar` | VARCHAR(150) | no | — | Plain text |
| `name_en` | VARCHAR(150) | no | — | Plain text |
| `short_description_ar` | VARCHAR(500) | no | — | Plain text |
| `short_description_en` | VARCHAR(500) | no | — | Plain text |
| `description_ar` | TEXT | no | — | Application max 5000 |
| `description_en` | TEXT | no | — | Application max 5000 |
| `slug_ar` | VARCHAR(180) | no | — | Normalized, stable |
| `slug_en` | VARCHAR(180) | no | — | Normalized, stable |
| `production_time_ar` | VARCHAR(255) | yes | null | Optional complete pair |
| `production_time_en` | VARCHAR(255) | yes | null | Optional complete pair |
| `price_type` | TINYINT UNSIGNED | no | — | `0|1` |
| `base_price` | DECIMAL(12,2) | no | — | Application rule `> 0` |
| `is_active` | TINYINT(1) | no | `0` | API boolean |
| `is_available` | TINYINT(1) | no | `1` | API boolean |
| `is_attachment_required` | TINYINT(1) | no | `0` | Requires at least one protected attachment on every newly created order item |
| `seo_title_ar` | VARCHAR(70) | yes | null | Optional pair |
| `seo_title_en` | VARCHAR(70) | yes | null | Optional pair |
| `seo_description_ar` | VARCHAR(180) | yes | null | Optional pair |
| `seo_description_en` | VARCHAR(180) | yes | null | Optional pair |
| `seo_tags_ar` | JSON | yes | null | Max 20 strings, app validated |
| `seo_tags_en` | JSON | yes | null | Max 20 strings, app validated |
| `deleted_at` | TIMESTAMP | yes | null | Soft delete |
| `created_at` | TIMESTAMP | no | — | UTC |
| `updated_at` | TIMESTAMP | no | — | UTC |

### Foreign keys

```text
fk_services_category:
  category_id -> categories.id ON DELETE RESTRICT

fk_services_subcategory:
  subcategory_id -> categories.id ON DELETE RESTRICT
```

The application validates root/child type, active state, non-deleted state, and parent match under locks.

### Unique constraints

```text
uq_services_slug_ar (slug_ar)
uq_services_slug_en (slug_en)
```

These support direct locale lookups. Cross-column uniqueness is enforced by `service_slug_reservations`.

### Query indexes

```text
idx_services_public_created
  (is_active, deleted_at, created_at, id)

idx_services_category_public
  (category_id, is_active, deleted_at, created_at, id)

idx_services_subcategory_public
  (subcategory_id, is_active, deleted_at, created_at, id)

idx_services_public_price
  (is_active, deleted_at, base_price, id)

idx_services_admin_type_created
  (price_type, deleted_at, created_at, id)

idx_services_admin_available_created
  (is_available, deleted_at, created_at, id)

idx_services_admin_active_created
  (is_active, deleted_at, created_at, id)
```

Foreign-key indexes are explicit even when MySQL would create an implicit supporting index, keeping migration intent visible.

## 3. `service_slug_reservations`

Internal integrity table; no API resource.

| Column | Type | Null | Notes |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | no | Primary key |
| `service_id` | BIGINT UNSIGNED | no | Owning service |
| `slug` | VARCHAR(180) | no | Normalized global slug value |
| `created_at` | TIMESTAMP | no | UTC |
| `updated_at` | TIMESTAMP | no | UTC |

Constraints:

```text
uq_service_slug_reservations_slug UNIQUE(slug)
idx_service_slug_reservations_service (service_id)
fk_service_slug_reservations_service:
  service_id -> services.id ON DELETE RESTRICT
```

Rules:

- Store the unique set of `slug_ar` and `slug_en` for each service.
- Same normalized value in both locale columns produces one reservation row.
- Soft-deleting a service retains reservation rows.
- Explicit slug update transactionally replaces obsolete reservations.

## 4. `service_specifications`

| Column | Type | Null | Default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | no | auto |
| `service_id` | BIGINT UNSIGNED | no | — |
| `label_ar` | VARCHAR(150) | no | — |
| `label_en` | VARCHAR(150) | no | — |
| `value_ar` | VARCHAR(1000) | no | — |
| `value_en` | VARCHAR(1000) | no | — |
| `sort_order` | INT UNSIGNED | no | `0` |
| `deleted_at` | TIMESTAMP | yes | null |
| `created_at` | TIMESTAMP | no | — |
| `updated_at` | TIMESTAMP | no | — |

```text
fk_service_specifications_service:
  service_id -> services.id ON DELETE RESTRICT

idx_service_specifications_parent_order
  (service_id, deleted_at, sort_order, id)
```

Maximum 30 non-deleted rows per service, enforced under a locked service row for create operations.

## 5. `service_order_fields`

| Column | Type | Null | Default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | no | auto |
| `service_id` | BIGINT UNSIGNED | no | — |
| `label_ar` | VARCHAR(150) | no | — |
| `label_en` | VARCHAR(150) | no | — |
| `field_type` | TINYINT UNSIGNED | no | `0` |
| `is_required` | TINYINT(1) | no | `0` |
| `sort_order` | INT UNSIGNED | no | `0` |
| `deleted_at` | TIMESTAMP | yes | null |
| `created_at` | TIMESTAMP | no | — |
| `updated_at` | TIMESTAMP | no | — |

```text
fk_service_order_fields_service:
  service_id -> services.id ON DELETE RESTRICT

idx_service_order_fields_parent_order
  (service_id, deleted_at, sort_order, id)
```

Maximum 20 non-deleted rows per service.

## 6. `service_pricing_options`

| Column | Type | Null | Default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | no | auto |
| `service_id` | BIGINT UNSIGNED | no | — |
| `name_ar` | VARCHAR(150) | no | — |
| `name_en` | VARCHAR(150) | no | — |
| `option_type` | TINYINT UNSIGNED | no | `0` |
| `input_type` | TINYINT UNSIGNED | no | — |
| `is_required` | TINYINT(1) | no | `0` |
| `sort_order` | INT UNSIGNED | no | `0` |
| `deleted_at` | TIMESTAMP | yes | null |
| `created_at` | TIMESTAMP | no | — |
| `updated_at` | TIMESTAMP | no | — |

```text
fk_service_pricing_options_service:
  service_id -> services.id ON DELETE RESTRICT

idx_service_pricing_options_parent_order
  (service_id, deleted_at, sort_order, id)
```

Maximum 10 non-deleted options per service. Only `price_type=1` services may own non-deleted pricing options.

## 7. `service_pricing_option_values`

| Column | Type | Null | Default |
|---|---|---:|---|
| `id` | BIGINT UNSIGNED | no | auto |
| `service_pricing_option_id` | BIGINT UNSIGNED | no | — |
| `label_ar` | VARCHAR(150) | no | — |
| `label_en` | VARCHAR(150) | no | — |
| `price_adjustment` | DECIMAL(12,2) | no | `0.00` |
| `is_active` | TINYINT(1) | no | `1` |
| `sort_order` | INT UNSIGNED | no | `0` |
| `deleted_at` | TIMESTAMP | yes | null |
| `created_at` | TIMESTAMP | no | — |
| `updated_at` | TIMESTAMP | no | — |

```text
fk_service_pricing_option_values_option:
  service_pricing_option_id -> service_pricing_options.id ON DELETE RESTRICT

idx_service_pricing_values_parent_active_order
  (service_pricing_option_id, deleted_at, is_active, sort_order, id)
```

Maximum 30 non-deleted values per option. Application rule: `price_adjustment >= 0`.

## 8. `service_media`

| Column | Type | Null | Default | Notes |
|---|---|---:|---|---|
| `id` | BIGINT UNSIGNED | no | auto | Primary key |
| `service_id` | BIGINT UNSIGNED | no | — | Parent |
| `type` | TINYINT UNSIGNED | no | — | `0=image`, `1=video` |
| `disk` | VARCHAR(50) | no | — | Configured public disk name |
| `path` | VARCHAR(500) | no | — | Relative path only |
| `stored_name` | VARCHAR(255) | no | — | Backend generated |
| `original_name` | VARCHAR(255) | no | — | Metadata only |
| `mime_type` | VARCHAR(150) | no | — | Validated MIME |
| `extension` | VARCHAR(20) | no | — | Validated extension |
| `size_bytes` | BIGINT UNSIGNED | no | — | File size |
| `alt_text_ar` | VARCHAR(255) | yes | null | Optional pair |
| `alt_text_en` | VARCHAR(255) | yes | null | Optional pair |
| `is_main` | TINYINT(1) | no | `0` | Images only |
| `created_at` | TIMESTAMP | no | — | UTC |
| `updated_at` | TIMESTAMP | no | — | UTC |

```text
fk_service_media_service:
  service_id -> services.id ON DELETE RESTRICT

idx_service_media_parent_type_main
  (service_id, type, is_main, id)
```

Invariants are enforced by locking the service row before every media write:

- Maximum 10 images.
- Maximum 1 video.
- Maximum 1 main image.
- Video cannot be main.

No generated-column constraint is mandatory because target MySQL support must be verified first. The service-row lock plus revalidation is the approved primary guarantee.

## 9. Soft Delete and Physical Delete Matrix

| Entity | Delete behavior | Restore API |
|---|---|---|
| Service | Soft delete | Yes; always inactive |
| Specification | Soft delete | No |
| Order field | Soft delete | No |
| Pricing option | Soft delete with values | No |
| Pricing option value | Soft delete | No |
| Media | Hard delete row and file | No |
| Slug reservation | Retained during service soft delete | Internal synchronization only |

## 10. Lock Order Matrix

| Workflow | Lock order |
|---|---|
| Assign/move/clear classification | affected roots ASC → affected subcategories ASC → service → revalidate |
| Restore service | current root → current subcategory → soft-deleted service → revalidate/clean |
| Delete root category | root → blocking services ASC → revalidate/delete |
| Delete subcategory | root → subcategory → blocking services ASC → revalidate/delete |
| Create/update child with maximum count | service → relevant children ASC → count/revalidate |
| Pricing option value actions | service → pricing option → values ASC |
| Upload/set-main/delete media | service → media ASC |
| Slug update | service reservation rows by slug ASC → unique insert/update |

When a pre-lock snapshot becomes stale and changes the affected hierarchy set, roll back and retry in a bounded loop.

## 11. Query Projections

### Admin index

Localized `name`, `shortDescription`, `slug`; operational price/status fields; main media; visible classification summaries; timestamps including `deletedAt`.

### Admin detail/create/update

Both locales, SEO pairs, production-time pair, active/non-deleted specifications, order fields, pricing options and values, and all media.

### Public list

Localized summary, integer `priceType`, fixed-precision `basePrice`, `isAvailable`, `isAttachmentRequired`, main image, and only publicly visible active classification.

### Public detail

Localized full content, active/non-deleted children, integer `inputType`, active values, all media in approved order, and localized SEO fallback.
