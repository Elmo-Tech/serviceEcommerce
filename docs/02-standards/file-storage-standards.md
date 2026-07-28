# Service Commerce Backend — File Storage Standards

> **Scope:** File uploads, storage, metadata, replacement, deletion, public URLs,
> and consistency rules for the Laravel 13 Service Commerce Backend.
>
> **Filesystem:** Laravel Filesystem
>
> **MVP Storage Visibility:** Public disk for all approved files.
>
> **Status:** Project-wide mandatory standard.

---

## 1. Purpose

This document defines the mandatory file-storage standards for the Service
Commerce Backend.

It covers:

- administrator avatars
- service main images
- service additional images
- service video
- guest order-item attachments
- filesystem disks
- path naming
- file validation
- MIME validation
- size limits
- file-count limits
- metadata
- public URLs
- replacement
- deletion
- rollback compensation
- orphan handling
- security
- localization
- testing

These rules apply to:

- Controllers
- Form Requests
- Actions
- Services
- Models
- migrations
- API Resources
- factories
- tests
- console commands
- imports
- exports
- operational scripts

---

## 2. Related Documents

File-storage implementation MUST comply with:

- `AGENTS.md`
- `.specify/memory/constitution.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- `docs/02-standards/api-standards.md`
- `docs/02-standards/code-standards.md`
- `docs/02-standards/database-standards.md`
- `docs/02-standards/localization-standards.md`
- `docs/02-standards/authentication-standards.md`
- `docs/02-standards/authorization-standards.md`
- the active Feature specification
- the active `plan.md`
- the active `tasks.md`

When documents conflict, implementation MUST stop until the conflict is
resolved.

---

## 3. Final Storage Decisions

```text
Filesystem abstraction: Laravel Filesystem
MVP disk visibility: Public
Storage driver: Configurable through environment
Cloud storage: Not required in MVP
Order attachment disk: Public
Image optimisation: Not included
Image resizing: Not included
WebP conversion: Not included
Thumbnail generation: Not included
Video transcoding: Not included
Video compression: Not included
Video thumbnail generation: Not included
Video duration extraction: Not included
Antivirus scanning: Not included
Queued cleanup Jobs: Not included
```

The implementation MUST use Laravel Filesystem APIs.

Application code MUST NOT depend directly on local absolute filesystem paths.

---

## 4. Approved File Categories

The MVP supports:

```text
Administrator avatar
Service main image
Service additional image
Service video
Order-item attachment
```

No other file category is introduced without an approved Feature.

Examples of unapproved categories:

```text
customer profile image
invoice file
payment receipt
service-question file answer
temporary upload token
report export archive
public customer document library
```

---

## 5. Disk Configuration

All approved MVP files use a configurable public disk.

Recommended environment configuration:

```env
FILESYSTEM_DISK=public
```

Recommended application-specific configuration:

```env
SERVICE_MEDIA_DISK=public
ADMIN_AVATAR_DISK=public
ORDER_ATTACHMENT_DISK=public
```

Configuration may fall back to `FILESYSTEM_DISK`.

Rules:

- Disk names are read from configuration.
- Disk names are not hard-coded throughout Controllers.
- Feature-specific configuration belongs in approved config files.
- Switching to S3 later should not require rewriting domain logic.
- API Resources do not construct URLs by concatenating storage paths manually.
- Use Laravel Filesystem URL generation.

---

## 6. Public Storage Decision

All approved files use public filesystem storage in the MVP.

This means the physical file may be reachable when its full public URL is
known.

The backend MUST reduce accidental exposure by using:

- unguessable generated filenames
- non-user-controlled directories
- no raw storage path in public guest responses
- no original filename as stored filename
- no directory listing
- strict upload validation
- safe web-server configuration
- no executable file types
- authorization before returning order-attachment metadata or URL

Important limitation:

```text
Public storage is not equivalent to private authorization.
```

Because order attachments are stored publicly, knowing the exact URL may allow
direct retrieval without Laravel authorization.

The MVP accepts this trade-off.

Order attachments remain on the approved configured public disk for the MVP.
Any storage-model change requires an approved architecture and feature
amendment.

---

## 7. File Validation Principles

Every uploaded file MUST be validated server-side.

Validation must consider:

- upload success
- maximum size
- MIME type
- extension
- file count
- resource ownership
- operation permission
- category-specific rules

Rules:

- Do not trust the browser-provided MIME type alone.
- Do not trust the original extension alone.
- Do not trust the original filename.
- Do not trust frontend validation.
- Do not validate only by extension.
- Do not permit executable formats.
- Do not permit SVG in the MVP.
- Do not permit HTML files.
- Do not permit PHP or script files.
- Reject double-extension tricks when the detected type is invalid.
- Reject failed or partial uploads.

---

## 8. Administrator Avatar Standards

### 8.1 Allowed Formats

```text
jpg
jpeg
png
webp
```

Approved MIME types:

```text
image/jpeg
image/png
image/webp
```

### 8.2 Maximum Size

```text
2 MB per avatar
```

Laravel size rule:

```text
2048 KB
```

### 8.3 Quantity

```text
one avatar per administrator
```

### 8.4 Storage

Recommended path:

```text
avatars/{userId}/{randomName}.{extension}
```

Example:

```text
avatars/1/4f90e9d9-89eb-4c51-9f36-a5ea8b536b74.webp
```

### 8.5 API Behaviour

Administration profile APIs may return:

```json
{
  "avatar": "https://example.com/storage/avatars/1/..."
}
```

They MUST NOT return:

```text
absolute local path
storage/app/public/...
public/storage/...
raw internal filesystem path
```

### 8.6 Replacement

When replacing an avatar:

1. validate the new file
2. store the new file
3. update the user record
4. commit database changes
5. synchronously attempt to delete the old file
6. log deletion failure safely

No queued cleanup Job is used.

---

## 9. Service Image Standards

### 9.1 Allowed Formats

```text
jpg
jpeg
png
webp
```

Approved MIME types:

```text
image/jpeg
image/png
image/webp
```

### 9.2 Maximum Size

```text
5 MB per image
```

Laravel size rule:

```text
5120 KB
```

### 9.3 Image Roles

A service may have:

```text
one main image
up to ten additional images
```

Maximum total service images:

```text
11
```

This includes:

```text
1 main image
10 additional images
```

### 9.4 Main Image Rule

A service has no more than one main image.

Changing the main image must:

- remove the main flag from the previous image
- assign the main flag to the new image
- preserve transaction consistency
- avoid two simultaneous main images

Application-level transaction and locking may be required.

### 9.5 Additional Image Limit

The backend rejects adding an additional image when the service already has ten
active additional images.

Recommended error code:

```text
SERVICE_IMAGE_LIMIT_EXCEEDED
```

Recommended status:

```text
422 Unprocessable Content
```

### 9.6 Storage Path

Recommended path:

```text
services/{serviceId}/images/{randomName}.{extension}
```

Example:

```text
services/25/images/c3fdc2e2-c565-45f4-b17e-283fc75dc2ce.jpg
```

### 9.7 Public Output

Public Service Resources may return approved public URLs.

Example:

```json
{
  "mainImageUrl": "https://example.com/storage/services/25/images/...",
  "images": [
    {
      "id": 10,
      "url": "https://example.com/storage/services/25/images/...",
      "altText": "..."
    }
  ]
}
```

The Resource does not return the raw storage path.

---

## 10. Service Video Standards

### 10.1 Allowed Formats

```text
mp4
webm
```

Approved MIME types may include:

```text
video/mp4
video/webm
```

The exact detected MIME behaviour must be tested on the deployment environment.

### 10.2 Maximum Size

```text
100 MB
```

Laravel size rule:

```text
102400 KB
```

Web-server and PHP upload limits must support this value.

### 10.3 Quantity

```text
one video maximum per service
```

The backend rejects a second active video unless the operation is an approved
replacement.

Recommended error code:

```text
SERVICE_VIDEO_LIMIT_EXCEEDED
```

### 10.4 Storage Path

Recommended path:

```text
services/{serviceId}/videos/{randomName}.{extension}
```

### 10.5 Processing

The MVP does not perform:

- transcoding
- compression
- resolution conversion
- thumbnail extraction
- duration extraction
- codec conversion
- streaming segmentation

The backend stores the validated original file.

### 10.6 Public Output

Public Service Resources may return an approved public video URL.

Raw storage paths remain hidden.

---

## 11. Order-Item Attachment Standards

### 11.1 Ownership

Every order attachment belongs to one order item.

The attachment does not belong only to the order.

This supports requests containing multiple services and item-specific files.

### 11.2 Allowed Formats

```text
jpg
jpeg
png
webp
pdf
doc
docx
xls
xlsx
zip
```

Expected MIME families may include:

```text
image/jpeg
image/png
image/webp
application/pdf
application/msword
application/vnd.openxmlformats-officedocument.wordprocessingml.document
application/vnd.ms-excel
application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
application/zip
```

MIME detection can vary by platform.

The implementation must test supported files on the target PHP environment.

### 11.3 Maximum Size

```text
10 MB per attachment
```

Laravel size rule:

```text
10240 KB
```

### 11.4 Maximum Count

```text
5 attachments per order item
```

The count includes all attachments submitted for that item.

Recommended error code:

```text
ORDER_ITEM_ATTACHMENT_LIMIT_EXCEEDED
```

### 11.5 Mapping

Guest multipart submission uses a request-only item reference such as:

```text
clientReference
```

Conceptual fields:

```text
items[0][clientReference] = item-a
attachments[item-a][0] = file
```

Rules:

- `clientReference` maps files to the submitted item.
- It is not a database ownership identifier.
- It is not persisted as file authorization.
- Duplicate or unknown references are rejected.
- Files cannot be assigned to another order item after creation without an
  approved operation.

### 11.6 Storage Path

Recommended path:

```text
orders/{orderNumber}/items/{orderItemId}/attachments/{randomName}.{extension}
```

Example:

```text
orders/ORD-260728-5896/items/44/attachments/9da747cb-98a7-4603-a563-2614b2a8cc46.pdf
```

### 11.7 Public Storage Behaviour

Order attachments use public storage.

However:

- guest order responses do not return attachment URLs
- public order-success responses do not return paths
- only authorized administration APIs may return attachment metadata
- raw storage paths are never returned
- download or URL retrieval requires:
  - authenticated administrator
  - active account
  - `orders.attachments.view` or `orders.attachments.download`
  - valid order-item ownership

Because storage is public, authorization controls API disclosure but cannot
fully prevent direct access to an already-known URL.

### 11.8 Download Endpoint

Recommended route:

```http
GET /api/v1/admin/orders/{order}/items/{orderItem}/attachments/{attachment}/download
```

Required permission:

```text
orders.attachments.download
```

The endpoint must verify:

- order exists
- order item belongs to order
- attachment belongs to order item
- file metadata exists
- file exists on configured disk

The response may redirect to or return the approved public URL.

---

## 12. Service Questions and File Uploads

File upload is not a service-question type in the MVP.

Files remain:

```text
order-item attachments
```

Do not create:

```text
file question
upload question
attachment answer
```

inside `service_order_questions`.

This keeps:

- question answers relational
- attachment validation centralized
- file ownership tied to the order item
- file limits predictable

A future file-question Feature requires a dedicated specification.

---

## 13. Stored Filename Standards

Stored filenames are generated by the server.

Approved strategies:

```text
UUID
ULID
secure random identifier
```

Recommended format:

```text
{uuid}.{validatedExtension}
```

Rules:

- Do not use original filename as stored name.
- Do not include customer names.
- Do not include email addresses.
- Do not include phone numbers.
- Do not include service names.
- Do not include user-provided path segments.
- Preserve the approved extension.
- Generate names independently from the frontend.
- Filename collisions must be practically impossible.
- Storage path uniqueness should be protected by metadata rules where
  applicable.

---

## 14. Original Filename

The original filename is metadata only.

Recommended column:

```text
original_name
```

Rules:

- Preserve for administrator display and download naming.
- Sanitize before response headers.
- Do not use it as a storage path.
- Do not trust it for MIME type.
- Do not log it unnecessarily if it contains sensitive content.
- Apply a reasonable maximum length.
- Remove unsafe control characters.

---

## 15. File Metadata

Recommended metadata fields:

```text
disk
path
stored_name
original_name
mime_type
extension
size_bytes
checksum nullable
created_at
updated_at
```

Additional service-media fields may include:

```text
type
is_main
sort_order
alt_text_ar
alt_text_en
```

Rules:

- `disk` stores the configured logical disk name.
- `path` stores the relative disk path.
- `stored_name` stores only the generated filename when useful.
- `original_name` stores display metadata.
- `size_bytes` uses an unsigned big integer.
- Metadata is authoritative for application file references.
- Do not infer database records by scanning folders during normal requests.

---

## 16. Checksum

Checksum is optional in the MVP.

Recommended algorithm:

```text
SHA-256
```

Recommended usage:

- order-item attachments when integrity verification is useful
- imports
- operational diagnostics

Checksum is not required for:

- administrator avatars
- service images
- service video

unless the Feature explicitly enables it.

Rules:

- Checksum is not an antivirus result.
- Checksum is not an authorization mechanism.
- Do not expose checksum publicly by default.
- Avoid unnecessary large-file hashing when it adds latency without value.

---

## 17. Database Tables

### 17.1 Service Media

Recommended direction:

```text
service_media
-------------
id
service_id
type
disk
path
stored_name
original_name
mime_type
extension
size_bytes
checksum nullable
alt_text_ar nullable
alt_text_en nullable
is_main
sort_order
created_at
updated_at
```

### 17.2 Order Attachments

Recommended direction:

```text
order_item_attachments
----------------------
id
order_item_id
disk
path
stored_name
original_name
mime_type
extension
size_bytes
checksum nullable
created_at
updated_at
```

### 17.3 Administrator Avatar

The administrator avatar may be represented directly on `users`:

```text
avatar_disk nullable
avatar_path nullable
```

or through a dedicated media table if the Feature requires richer metadata.

For the MVP, direct nullable fields are acceptable.

Do not create a generic polymorphic media table merely for theoretical reuse.

---

## 18. Upload Configuration

PHP and web-server configuration must support approved limits.

Potential PHP settings:

```ini
upload_max_filesize = 100M
post_max_size = appropriate total request size
max_file_uploads = sufficient approved count
```

`post_max_size` must account for:

- multiple order items
- up to five files per item
- JSON fields
- multipart overhead

Rules:

- Application validation remains mandatory.
- Infrastructure limits must not be lower than approved application limits.
- Production configuration must be documented.
- A request rejected by PHP before Laravel may not receive the normal API
  response; deployment checks must prevent this mismatch.
- Do not increase limits globally without considering memory and request-time
  impact.

---

## 19. Upload Service Design

Recommended reusable capability:

```text
FileStorageService
```

Possible focused services:

```text
AdminAvatarStorageService
ServiceMediaStorageService
OrderAttachmentStorageService
```

Responsibilities may include:

- generated filename
- approved directory
- disk selection
- file storage
- metadata preparation
- URL generation
- deletion
- existence checks

Services MUST NOT decide:

- user permissions
- service publication
- order status
- customer ownership
- business workflow

Authorization and business rules remain outside storage helpers.

---

## 20. Upload Flow

A safe upload flow:

1. authenticate when required
2. authorize the operation
3. validate request fields
4. validate file count
5. validate file type and size
6. generate storage path
7. store file
8. persist metadata
9. return approved Resource data

For multi-record workflows such as guest order creation:

1. validate the proposed order
2. start database transaction
3. create customer and order records
4. create order items
5. store item files
6. create attachment metadata
7. commit
8. return success

If any later step fails:

- roll back database changes
- synchronously delete files created by that request
- log any cleanup failure

No cleanup Job is dispatched.

---

## 21. Database and Filesystem Transactions

A database transaction cannot roll back filesystem changes.

Therefore, every Action storing files must track:

```text
created file paths
replaced old file paths
deleted file outcomes
```

Rules:

- Newly created files are compensation candidates.
- On exception, delete every newly created file synchronously.
- Do not delete old authoritative files before the new database state is safe.
- Do not swallow cleanup exceptions silently.
- Cleanup failure does not convert a failed business operation into success.
- Log safe metadata for manual inspection.
- Do not log customer file contents.

---

## 22. Replacement Flow

Approved replacement sequence:

1. authorize replacement
2. validate new file
3. store new file
4. begin or continue database transaction
5. update metadata to the new file
6. commit database changes
7. synchronously attempt to delete the old file
8. log deletion failure if old file remains

Why delete the old file after commit:

- the old file remains valid if storing the new file fails
- the old file remains valid if database update fails
- successful metadata never points to a deleted old file

No queued cleanup Job is used.

A failed old-file deletion may leave an orphan.

The failure is handled through:

- safe logging
- manual operational cleanup
- a future Artisan audit or cleanup command when approved

---

## 23. Deletion Flow

Approved deletion sequence:

1. authenticate and authorize
2. load metadata through the parent resource
3. validate deletion is allowed
4. update or delete metadata according to retention rules
5. commit database changes
6. synchronously attempt physical deletion
7. log storage failure

For records requiring strong file presence until deletion succeeds, the Feature
may choose:

1. delete file first
2. delete metadata only after storage success

The chosen order must be documented by the Feature.

Do not remove files using unverified request paths.

---

## 24. No Cleanup Jobs

The MVP does not use queued Jobs for:

- failed old-file deletion
- orphan-file cleanup
- media replacement cleanup
- attachment cleanup
- checksum generation
- optimisation
- image processing
- video processing

Rules:

- Normal cleanup is synchronous and best-effort.
- Cleanup failures are logged.
- Operational cleanup may be manual.
- A future Artisan command may audit or remove verified orphans.
- Do not dispatch a Job merely because deletion failed.
- Email Jobs elsewhere in the project are unrelated to file cleanup.

---

## 25. Orphan Handling

Possible orphan conditions:

```text
file exists without metadata
metadata exists without file
old replaced file remains after deletion failure
request-created file remains after compensation failure
```

The MVP handles orphans through:

- safe logs
- operational review
- optional future Artisan command

An orphan command must:

- run explicitly
- support dry-run mode
- verify metadata before deletion
- restrict scanning to approved directories
- never delete based only on age
- never delete unknown files automatically
- report actions
- avoid exposing sensitive filenames in public output

Do not create the command until operationally required.

---

## 26. Service Soft Delete Behaviour

Soft-deleting a service does not delete:

- main image
- additional images
- video

Reasons:

- service may be restored
- administration may need historical access
- deletion should not break references unexpectedly

Rules:

- Soft-deleted service media is excluded from public output.
- Restoring the service restores normal access when the media still exists.
- Force deletion, if ever approved, performs controlled media cleanup.
- Ordinary service deletion permission does not imply physical media purge.

---

## 27. Order Attachment Retention

Order attachments are retained as long as the order exists.

The MVP has no automatic attachment-deletion schedule.

Rules:

- Status changes do not delete attachments.
- Cancellation does not delete attachments.
- Rejection does not delete attachments.
- Completion does not delete attachments.
- Soft deletion of an order, if ever introduced, does not automatically delete
  attachments.
- Physical order deletion is outside normal application flows.
- Retention-policy changes require a dedicated approved decision.

---

## 28. Administrator Avatar Retention

When an avatar is replaced:

- new avatar becomes authoritative
- old avatar is synchronously deleted after commit
- deletion failure is logged

When an administrator is deactivated:

- avatar is retained

When an administrator is physically deleted through an approved process:

- avatar may be cleaned through controlled deletion

The initial MVP has no user-management Feature, so ordinary administrator
deletion is not exposed.

---

## 29. Public URL Generation

Use Laravel Filesystem:

```php
Storage::disk($disk)->url($path)
```

Do not manually build:

```text
/storage/...
https://domain/storage/...
```

in domain logic.

Rules:

- URL generation is centralized.
- API Resources return approved URLs only.
- Raw disk and path may remain in administration metadata only when required;
  preferred output still uses an approved URL.
- Guest APIs never return order-attachment URLs.
- Service media and avatars may return public URLs.
- URLs are not authorization tokens.

---

## 30. API Resource Rules

### 30.1 Administrator Avatar

May return:

```text
avatar
```

Must not return:

```text
avatarPath
absolutePath
```

### 30.2 Service Media

May return:

```text
id
type
url
altText
isMain
sortOrder
```

Public output returns localized alt text.

### 30.3 Order Attachment

Authorized administration output may return:

```text
id
originalName
mimeType
extension
sizeBytes
downloadUrl
createdAt
```

Rules:

- `downloadUrl` points to an approved backend route or approved public URL.
- Guest output does not return attachment metadata.
- Do not return raw path.
- Do not return checksum by default.
- Do not return stored filename unless operationally required.

---

## 31. File Download Response

When returning a file through Laravel:

- authorize before response
- resolve metadata through parent relationships
- verify file exists
- use a safe download filename
- set appropriate content headers
- avoid inline execution for risky document types
- do not trust original filename for header safety

For public direct URLs:

- API authorization controls disclosure
- file-server access is public once URL is known

The limitation must remain understood.

---

## 32. MIME and Extension Mapping

The backend should maintain a central allow-list.

Conceptual configuration:

```php
return [
    'admin_avatar' => [
        'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'max_kilobytes' => 2048,
    ],

    'service_image' => [
        'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'max_kilobytes' => 5120,
    ],

    'service_video' => [
        'extensions' => ['mp4', 'webm'],
        'mime_types' => ['video/mp4', 'video/webm'],
        'max_kilobytes' => 102400,
    ],

    'order_attachment' => [
        'extensions' => [
            'jpg', 'jpeg', 'png', 'webp',
            'pdf', 'doc', 'docx',
            'xls', 'xlsx', 'zip',
        ],
        'max_kilobytes' => 10240,
        'max_per_order_item' => 5,
    ],
];
```

Do not duplicate allow-lists across Form Requests.

---

## 33. File Validation Errors

Recommended stable error codes:

```text
FILE_REQUIRED
FILE_UPLOAD_FAILED
FILE_TYPE_NOT_ALLOWED
FILE_EXTENSION_NOT_ALLOWED
FILE_SIZE_EXCEEDED
FILE_COUNT_EXCEEDED
SERVICE_IMAGE_LIMIT_EXCEEDED
SERVICE_VIDEO_LIMIT_EXCEEDED
ORDER_ITEM_ATTACHMENT_LIMIT_EXCEEDED
FILE_NOT_FOUND
FILE_STORAGE_FAILED
FILE_DELETE_FAILED
ATTACHMENT_NOT_FOUND
```

Rules:

- Messages are localized.
- Codes remain English.
- Validation errors attach to the correct request field.
- Internal storage exceptions do not expose filesystem paths.
- Unsupported files return `422`.
- Missing stored file in an authorized download may return `404`.
- Unexpected storage failures return safe `500` errors.

---

## 34. Localization

Supported locales:

```text
ar
en
```

Localization applies to:

- file-validation messages
- count-limit messages
- upload success messages
- replacement messages
- deletion messages
- missing-file errors
- media alt text
- authorized attachment labels where system-generated

The following are not translated:

- original user filename
- extension
- MIME type
- storage disk
- storage path
- generated filename
- checksum
- file size

Recommended translation keys:

```text
files.uploaded
files.replaced
files.deleted
files.errors.required
files.errors.upload_failed
files.errors.type_not_allowed
files.errors.extension_not_allowed
files.errors.size_exceeded
files.errors.count_exceeded
files.errors.storage_failed
files.errors.delete_failed
files.errors.not_found

service_media.errors.image_limit_exceeded
service_media.errors.video_limit_exceeded
order_attachments.errors.limit_exceeded
```

---

## 35. Security Standards

- Use generated filenames.
- Reject executable formats.
- Reject SVG in the MVP.
- Validate MIME and extension.
- Restrict file counts.
- Restrict file sizes.
- Do not trust original names.
- Do not expose absolute paths.
- Do not accept path from API input.
- Do not allow `../` path traversal.
- Do not pass request paths directly to `Storage::delete`.
- Do not permit directory listing.
- Configure the public storage directory to prevent script execution.
- Do not parse untrusted archive contents in the MVP.
- Do not extract ZIP files.
- Do not execute uploaded Office macros.
- Do not render uploaded HTML.
- Do not automatically open attachment content server-side.
- Do not store files in MySQL blobs.
- Do not log file contents.
- Do not return guest attachment URLs.
- Do not rely on public URL secrecy as strong authorization.
- Do not introduce antivirus claims when no scanner exists.

---

## 36. Web-Server Safety

The web server must not execute files under public storage.

Requirements:

- PHP execution disabled under `/storage`
- script handlers not applied to uploaded directories
- directory indexes disabled
- MIME handling reviewed
- content sniffing protections enabled where possible
- direct access follows approved public-storage decision

Recommended response header where infrastructure supports it:

```http
X-Content-Type-Options: nosniff
```

Deployment documentation must include the storage symlink:

```bash
php artisan storage:link
```

The symlink must point to:

```text
public/storage -> storage/app/public
```

---

## 37. No File Optimisation in MVP

The MVP stores approved original files.

It does not:

- resize images
- compress images
- change image quality
- convert to WebP
- create responsive variants
- create thumbnails
- strip metadata
- optimise PDFs
- compress video
- transcode video
- create streaming formats

Consequences:

- frontend consumers use original assets
- upload limits protect storage
- administrators should upload appropriately sized media
- future optimisation requires a dedicated Feature and migration strategy

---

## 38. No Antivirus Integration in MVP

The MVP does not integrate:

```text
ClamAV
cloud malware scanner
external antivirus API
content-disarm service
```

Rules:

- Do not claim files are virus-free.
- Use strict allow-lists and limits.
- Do not execute or extract uploaded files.
- Keep validation logic extensible.
- A future scanner may be inserted before final persistence through an approved
  architecture change.
- Scanner absence must be considered when accepting ZIP and Office files.

---

## 39. Storage Failure Handling

On storage failure:

- do not create successful metadata
- return safe API error
- do not expose disk path
- rollback database transaction where applicable
- remove already-created files synchronously when possible
- log safe operational context

Safe log fields may include:

```text
request_id
file_category
disk
resource_type
resource_id
operation
exception_class
```

Avoid logging:

```text
file contents
customer answers
raw attachment URL
absolute path
personal filenames unless needed
```

---

## 40. File Deletion Failure Handling

Because there are no cleanup Jobs:

1. complete the authoritative database operation according to the approved
   replacement or deletion flow
2. synchronously attempt deletion
3. catch storage deletion failure
4. log the orphan candidate safely
5. alert operational monitoring when available
6. leave manual cleanup for later

Do not:

- roll back a successful profile or service update solely because old-file
  cleanup failed after commit
- pretend the old file was deleted
- dispatch a cleanup Job
- delete unrelated files by pattern

---

## 41. Concurrency

Concurrency-sensitive file operations include:

- selecting a service main image
- replacing service video
- replacing avatar
- enforcing ten additional images
- deleting and reordering media

Use:

- database transactions
- row locks where justified
- unique constraints or generated-slot rules where approved
- current metadata reload before mutation

Examples:

```text
two simultaneous main-image updates
two simultaneous video uploads
two simultaneous additions at the ten-image limit
```

Tests should prove that constraints remain valid.

Filesystem operations themselves are not database-transactional.

---

## 42. Ordering Service Media

Service media uses:

```text
sort_order
```

Rules:

- main image may also have sort order
- additional images are manually ordered
- video ordering follows the Feature contract
- reorder endpoint requires:
  - authentication
  - active user
  - `service-media.reorder`
  - service ownership validation
- reorder does not rename files
- reorder does not move physical files
- duplicate or missing media IDs are rejected

---

## 43. Deletion Permissions

Relevant permissions may include:

```text
service-media.delete
orders.attachments.delete
```

The MVP does not require order-attachment deletion unless a Feature explicitly
introduces it.

Rules:

- file deletion permission is separate from view or update
- service soft deletion does not equal media deletion
- attachment download permission does not equal delete permission
- physical purge is never implied by generic resource deletion

---

## 44. Testing Requirements

File storage requires automated Pest tests.

### 44.1 Avatar Tests

- jpg upload
- jpeg upload
- png upload
- webp upload
- file over 2 MB rejected
- invalid MIME rejected
- invalid extension rejected
- original name not used as storage name
- public URL returned
- raw path not returned
- replacement stores new file
- replacement removes old file when deletion succeeds
- failed database update removes new file
- old-file deletion failure is logged
- no cleanup Job is dispatched

### 44.2 Service Image Tests

- main image upload
- additional image upload
- image over 5 MB rejected
- invalid image rejected
- one main image maximum
- replacing main image preserves one main image
- ten additional images accepted
- eleventh additional image rejected
- public URL returned
- raw path hidden
- soft-deleting service retains media
- restored service may use retained media

### 44.3 Service Video Tests

- mp4 upload
- webm upload
- file over 100 MB rejected
- invalid video rejected
- one video maximum
- approved replacement succeeds
- no transcoding occurs
- no thumbnail is created
- no duration extraction occurs
- public URL returned

### 44.4 Order Attachment Tests

- each approved extension
- invalid extension rejected
- invalid MIME rejected
- file over 10 MB rejected
- five attachments per item accepted
- sixth attachment rejected
- attachments map by `clientReference`
- unknown item reference rejected
- duplicate item reference handled safely
- attachment metadata belongs to correct order item
- guest response does not return URL
- authorized administrator can view metadata
- download requires permission
- foreign nested attachment returns `404`
- raw path is not returned
- cancellation retains attachment
- completion retains attachment
- no automatic deletion occurs

### 44.5 Compensation Tests

- database failure removes created avatar
- database failure removes created service image
- order creation failure removes all created attachments
- partial attachment failure cleans previously stored request files
- cleanup failure is logged
- no cleanup Job is dispatched
- failed operation does not leave successful metadata

### 44.6 Security Tests

- PHP upload rejected
- HTML upload rejected
- SVG upload rejected
- path traversal filename does not affect path
- double extension rejected when MIME invalid
- original filename control characters sanitized
- storage path cannot be supplied by request
- order attachment URL absent from guest response
- absolute path never appears in API
- unsupported archive extraction does not occur

### 44.7 Localization Tests

- Arabic validation message
- English validation message
- error code remains stable
- request field key remains unchanged
- file name is not translated
- public alt text resolves by locale

### 44.8 Concurrency Tests

Use MySQL-backed tests where applicable for:

- one main image
- one video
- ten additional-image limit
- replacement concurrency
- media reorder

---

## 45. Deployment Checklist

- [ ] `storage:link` exists.
- [ ] Public disk is writable.
- [ ] Uploaded directories are not executable.
- [ ] Directory listing is disabled.
- [ ] PHP upload limit supports 100 MB video.
- [ ] Web-server body limit supports approved multipart requests.
- [ ] `post_max_size` supports multiple item attachments.
- [ ] Public URL configuration is correct.
- [ ] `APP_URL` is correct.
- [ ] Storage persists across deployment.
- [ ] Persistent volume configuration is documented.
- [ ] Backup covers `storage/app/public`.
- [ ] Restore process covers database and public files.
- [ ] No cleanup worker is required.
- [ ] Logging captures deletion failures safely.

---

## 46. Code Review Checklist

- [ ] File category is approved.
- [ ] Disk comes from configuration.
- [ ] File size limit is correct.
- [ ] Extension allow-list is correct.
- [ ] MIME validation exists.
- [ ] Count limit is enforced.
- [ ] Stored name is server-generated.
- [ ] Original name is metadata only.
- [ ] Path contains no user-controlled segment.
- [ ] Database metadata is explicit.
- [ ] API hides raw path.
- [ ] Guest API hides order attachment URL.
- [ ] Required authorization is enforced.
- [ ] Nested attachment ownership is validated.
- [ ] Replacement stores new file before deleting old.
- [ ] Failed workflow compensates created files.
- [ ] Cleanup failure is logged.
- [ ] No cleanup Job is introduced.
- [ ] No image optimisation is introduced.
- [ ] No video processing is introduced.
- [ ] No antivirus claim is introduced.
- [ ] Tests cover success and failure.
- [ ] Deployment limits support the Feature.

---

## 47. Definition of Done

File-storage work is complete only when:

- validation limits are implemented
- generated filenames are used
- configured public disk is used
- metadata is persisted
- public URLs are generated safely
- raw paths are hidden
- count limits are enforced
- replacement is safe
- rollback compensation is implemented
- deletion failure is logged
- no cleanup Job is required
- public guest responses do not expose order attachments
- authorization protects administration attachment access
- tests pass
- deployment storage configuration is documented
- no known critical upload vulnerability remains

---

## 48. Non-Negotiable Rules

- Use Laravel Filesystem.
- Use configurable public storage in the MVP.
- Admin avatars use public storage.
- Service images use public storage.
- Service video uses public storage.
- Order-item attachments use public storage.
- One administrator avatar maximum.
- One service main image maximum.
- Ten service additional images maximum.
- One service video maximum.
- Avatar maximum is 2 MB.
- Service image maximum is 5 MB.
- Service video maximum is 100 MB.
- Order attachment maximum is 10 MB.
- Five order attachments maximum per order item.
- Generate stored filenames server-side.
- Preserve original filename as metadata only.
- Do not use original filename as storage name.
- Do not accept storage path from the request.
- Do not expose raw storage paths.
- Do not return order-attachment URLs to guest APIs.
- Do not use file upload as a service-question type.
- Do not optimise images in the MVP.
- Do not convert images to WebP automatically.
- Do not generate thumbnails.
- Do not process or transcode video.
- Do not integrate antivirus in the MVP.
- Do not extract uploaded ZIP files.
- Do not allow executable upload types.
- Do not allow SVG in the MVP.
- Store the new replacement file before deleting the old file.
- Delete old replaced files synchronously after successful commit.
- Compensate newly stored files when the workflow fails.
- Do not create cleanup Jobs.
- Log cleanup failures for operational handling.
- Soft-deleting a service does not delete its media.
- Order status changes do not delete attachments.
- Retain order attachments while the order exists.
- Public file URLs are not authorization tokens.
