# Data Model: Hero Slider Management

## 1. HeroSlide

Represents one ordered homepage Hero content item.

### Fields

| Field | Storage type | Required | Rules |
|---|---|---:|---|
| `id` | unsigned big integer | yes | Auto-increment primary key; immutable |
| `title_ar` | varchar(150) | yes | Trimmed plain text; 1..150 characters; no null bytes/unapproved control characters |
| `title_en` | varchar(150) | yes | Trimmed plain text; 1..150 characters; no null bytes/unapproved control characters |
| `description_ar` | text | yes | Trimmed plain text; 1..1000 characters; approved line breaks only |
| `description_en` | text | yes | Trimmed plain text; 1..1000 characters; approved line breaks only |
| `image_path` | varchar(255) | yes | Backend-generated relative path on configured public disk; never returned raw |
| `is_active` | unsigned tiny integer / boolean cast | yes | `0` inactive, `1` active |
| `position` | unsigned tiny integer | yes | Unique; global sequence starts at 1 |
| `created_at` | timestamp | yes | Framework-managed; not in approved Resource |
| `updated_at` | timestamp | yes | Framework-managed; not in approved Resource |

### Relationships

None. The image is a filesystem asset referenced by `image_path`, not a child
database entity.

### Database constraints and indexes

- Primary key: `id`.
- Unique index: `position`.
- Composite index: `(is_active, position)`.
- No soft-delete column.
- No nullable business field.
- No native MySQL enum.
- Application advisory mutex, transaction, and row locks enforce total row
  count ≤10 and the gap-free sequence; the unique index is the final
  duplicate-position defense.

### Global ordered-set invariant

For a committed row count `N`:

```text
0 <= N <= 10
positions = [] when N = 0
positions = [1, 2, ..., N] when N > 0
```

Active state never changes membership in this ordered set.

## 2. Input projections

### CreateHeroSlideInput

| API key | Required | Validation/normalization |
|---|---:|---|
| `titleAr` | yes | trim; string; 1..150 |
| `titleEn` | yes | trim; string; 1..150 |
| `descriptionAr` | yes | trim; string; 1..1000 |
| `descriptionEn` | yes | trim; string; 1..1000 |
| `image` | yes | one static JPG/JPEG, PNG, or WebP; exact max 5 MiB (`5,242,880` bytes); animated WebP/APNG and SVG rejected |
| `isActive` | yes | exact raw multipart string `"0"` or `"1"`; normalize only after lexical validation |
| `position` | no | canonical positive decimal string, then integer 1..locked count+1; omission appends |

### UpdateHeroSlideInput

Same keys as create, all optional individually, but the PATCH object requires
at least one approved mutable property. When present, each uses the same field
rule. Required persisted text cannot be cleared. Image omission preserves the
current asset. Position omission preserves the current position; when present
it must first pass canonical lexical validation and then be 1..locked count.

### ListHeroSlidesInput

| API key | Required | Default | Rules |
|---|---:|---:|---|
| `page` | no | 1 | integer ≥1 |
| `perPage` | no | 15 | integer 1..100 |
| `filter[isActive]` | no | null | exact scalar lexical `"0"` or `"1"`; raw repeated-member detection |

No search, sort, include, or additional query member is accepted.

## 3. Output projections

### AdminHeroSlide

```text
id
titleAr
titleEn
descriptionAr
descriptionEn
image          absolute public URL
isActive       0 or 1
position       1..N
```

Admin index wraps this collection with `currentPage`, `lastPage`, `perPage`,
and `total` pagination metadata.

### PublicHeroSlide

```text
title          resolved Arabic or English value
description    resolved Arabic or English value
image          absolute public URL
```

Only active rows participate. Array order is display order. IDs, positions,
activity, timestamps, raw paths, and dual-language keys are excluded.

## 4. Mutation transitions

### Create

1. Validate content and image.
2. Store image under a generated safe name.
3. Lock ordered range and revalidate count/position.
4. Reject when locked count is 10.
5. Insert at requested position or append; shift the existing order through
   temporary positions.
6. Commit; on failure delete the new image.

### Update without move/image

1. Lock ordered range and resolve the target from locked rows.
2. Apply only present validated attributes.
3. Preserve position and image path.

### Update with move

1. Lock ordered range and resolve the target.
2. Build desired ID order by removing target and inserting it at requested
   one-based index.
3. Move rows to temporary band 100..110.
4. Assign final positions 1..N and commit.

### Update with image replacement

1. Store new image.
2. Commit new path with any other update/move.
3. Delete old image after commit.
4. Delete new image instead if the database transaction fails.

### Delete

1. Lock ordered range and target.
2. Remove row permanently.
3. Reassign remaining rows to 1..N through the temporary band.
4. Commit.
5. Delete old image after commit; log safe cleanup failure without reversing
   committed database state.

## 5. Concurrency model

- Every mutation attempt acquires one deterministic application/database-scoped
  MySQL/MariaDB named advisory mutex with a bounded wait before opening the
  transaction, then uses the same connection for the transaction and ordered
  row locks.
- The advisory mutex serializes the empty set and final-slot races; `FOR UPDATE`
  locks every current row for transactional mutation.
- Limit, target existence, and requested position are checked after the lock.
- Temporary positions 100..110 avoid conflicts with committed valid values
  1..10 and fit the unsigned-tiny-integer column.
- Readers observe only committed states; concurrent writers serialize.
- Dedicated MySQL tests must prove initial concurrent creates, final-slot
  creates, competing moves, and create/delete races.
- The advisory mutex is always released from `finally` on the same connection;
  timeout or acquisition failure returns a safe project-standard failure and
  performs no mutation.
- Retryable MySQL deadlock/serialization errors use bounded full-attempt
  retries. Each retry reacquires the mutex and starts a new transaction.
  Non-retryable or exhausted failures expose no database details and preserve
  the last committed row/image state.

## 6. Deletion and history

Hero slides are current website content and carry no order/history snapshot
obligation. Hard deletion is approved. Deletion does not cascade to another
table and must not alter unrelated records.
