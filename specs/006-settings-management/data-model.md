# Data Model: Settings Management

## 1. Setting

Represents the canonical singleton site configuration row.

### Fields

| Field | Database type | Required | Notes |
|---|---|---:|---|
| `id` | unsigned big integer | yes | Canonical singleton identity is always `1` |
| `site_name_ar` | varchar | yes | Persistent required field |
| `site_name_en` | varchar | yes | Persistent required field |
| `site_description_ar` | varchar(500) nullable | no | Empty submitted string normalizes to `null` |
| `site_description_en` | varchar(500) nullable | no | Empty submitted string normalizes to `null` |
| `slogan_ar` | varchar nullable | no | Empty submitted string normalizes to `null` |
| `slogan_en` | varchar nullable | no | Empty submitted string normalizes to `null` |
| `address_ar` | text nullable | no | Localized plain-text address |
| `address_en` | text nullable | no | Localized plain-text address |
| `public_email` | varchar | yes | Valid email; never `null` |
| `logo_path` | varchar nullable | no | Internal path on configured public disk |
| `footer_logo_path` | varchar nullable | no | Internal path on configured public disk |
| `favicon_path` | varchar nullable | no | Internal path on configured public disk |
| `created_at` | timestamp | yes | Internal only |
| `updated_at` | timestamp | yes | Internal only |

No `logo_disk`, `footer_logo_disk`, or `favicon_disk` columns are added. The
project-configured public disk is the single storage authority.

### Relationships

- has many `SettingPhone`, ordered by `position`
- has many `SettingSocialLink`, ordered by `position`

### Singleton Rules

- Exactly one row is authoritative: `settings.id = 1`.
- No Create or Delete API exists.
- No soft delete is used.
- Seeder creation is idempotent.
- Admin read/update may restore a missing row safely.
- Public read returns safe defaults without inserting a row.
- Admin mutations acquire `lockForUpdate` on `settings.id = 1`.

---

## 2. SettingPhone

Represents one public phone number normalized to 10 or 11 digits.

### Fields

| Field | Database type | Required | Notes |
|---|---|---:|---|
| `id` | unsigned big integer | yes | Internal only; never exposed |
| `setting_id` | unsigned big integer | yes | Foreign key to `settings.id` |
| `number` | varchar | yes | Canonical local format, e.g. `01012345678` |
| `has_whats` | unsigned tiny integer | yes | Only `0` or `1` |
| `position` | unsigned tiny integer | yes | Preserves submitted order |
| `created_at` | timestamp | yes | Internal only |
| `updated_at` | timestamp | yes | Internal only |

### Constraints and Indexes

- Foreign key: `setting_id → settings.id`, cascade on delete.
- Unique index: `(setting_id, number)`.
- Ordered lookup index: `(setting_id, position)`.
- Maximum three rows is enforced inside the locked replacement workflow.
- At most one `has_whats = 1` is enforced inside the locked replacement
  workflow.

### API Input/Output Boundary

- Admin update requests use `PhoneInput` and may contain approved formatted
  Egyptian local or international variants.
- Admin and Public responses use `PhoneOutput` and always return canonical
  normalized digits-only local format containing exactly 10 or 11 digits.

### Rules

- Normalize before validation, duplicate checking, and persistence.
- Accept formatted local or Egyptian country-prefixed variants and store a
  10-digit or 11-digit local canonical form.
- No item IDs enter or leave the API.
- Submitting `phones` replaces the full ordered collection.
- An explicitly submitted empty `phones` array clears all rows.

---

## 3. SettingSocialLink

Represents one visible public social link.

### Fields

| Field | Database type | Required | Notes |
|---|---|---:|---|
| `id` | unsigned big integer | yes | Internal only; never exposed |
| `setting_id` | unsigned big integer | yes | Foreign key to `settings.id` |
| `platform` | unsigned tiny integer | yes | Cast to integer-backed `SocialPlatform` enum |
| `url` | text | yes | Valid URL |
| `position` | unsigned tiny integer | yes | Preserves submitted order |
| `created_at` | timestamp | yes | Internal only |
| `updated_at` | timestamp | yes | Internal only |

### Constraints and Indexes

- Foreign key: `setting_id → settings.id`, cascade on delete.
- Unique index: `(setting_id, platform)`.
- Ordered lookup index: `(setting_id, position)`.

### Enum Boundary

The database stores the integer-backed enum value. API requests and responses
use only these stable English machine keys:

```text
facebook
instagram
linkedin
youtube
tiktok
x
telegram
pinterest
snapchat
```

### Rules

- Each platform appears at most once.
- Presence means visible; no `isActive` column exists.
- No item IDs enter or leave the API.
- Submitting `socialLinks` replaces the full ordered collection.
- An explicitly submitted empty `socialLinks` array clears all rows.

---

## 4. Branding File State

Branding state is stored as nullable paths on `settings`; no media table and no
per-row disk columns are introduced.

### Managed Fields

```text
logo_path
footer_logo_path
favicon_path
```

### State Transitions

```text
file omitted + remove flag omitted
→ preserve current path

new file submitted
→ store new file, commit new path, delete old file after commit

remove flag = 1
→ commit null path, delete old file after commit

file + matching remove flag
→ 422 VALIDATION_ERROR
```

If the database transaction fails after storing a new file, the new file is
deleted as compensation. If post-commit deletion of an old file fails, the
valid database state remains and the cleanup failure is logged.

---

## 5. Validation and Normalization

### Optional Scalar Text

- Omitted field: unchanged.
- Explicit empty string: normalize to `null`.
- No extra scalar clear flags.
- Persistent required fields cannot be cleared.

### Coordinates

- Both coordinates omitted, both empty, or both valid.
- Only one coordinate present is invalid.
- Persist with `decimal(10,7)`.
- Return decimal strings or `null`.

### SEO Keywords

- Arrays only.
- Trim each value.
- Reject empty values.
- Reject duplicates per locale after trim and case-insensitive comparison.
- Preserve original casing and submitted order otherwise.

### Concurrency

Every Admin mutation:

1. starts a database transaction,
2. resolves `settings.id = 1`,
3. acquires `lockForUpdate`,
4. validates and replaces ordered child collections,
5. commits paths and scalar state,
6. performs approved post-commit file cleanup.

Missing singleton recovery uses an idempotent insert followed by a locked
re-read, preventing duplicate-key failures under concurrent recovery.
