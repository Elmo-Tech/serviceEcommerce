# Specification Quality Checklist: Settings Management

**Purpose**: Validate contract completeness before `/speckit.plan`  
**Created**: 2026-08-02  
**Feature**: [spec.md](../spec.md)  
**Approved Source**: `docs/features/006-settings-management.md`  
**Readiness**: Ready for `/speckit.plan`

## Content Quality

- [x] The specification is grounded in the approved Feature 006 reference.
- [x] The feature scope is bounded and contains no unrelated setting groups.
- [x] User value and business behavior are separated from implementation tasks.
- [x] Approved API paths and multipart contract details are included where
  required.
- [x] No `[NEEDS CLARIFICATION]` markers remain.
- [x] No snapshot terminology from the Orders feature remains.
- [x] No contradictory field, request, response, or security rule remains.

## Exact Field Inventory

- [x] All localized identity fields are listed explicitly.
- [x] `logo`, `footerLogo`, and `favicon` are listed explicitly.
- [x] `publicEmail` is listed explicitly.
- [x] Address and map fields are listed explicitly.
- [x] Localized SEO title, description, and keyword arrays are listed.
- [x] `phones` and `socialLinks` shapes are fixed.
- [x] `defaultOgImage` and `ogImage` are explicitly excluded everywhere.

## Required and Nullable Semantics

- [x] `siteNameAr`, `siteNameEn`, and `publicEmail` are fixed as persistent
  required fields.
- [x] Required fields are not required in every PATCH request.
- [x] Omitted PATCH fields are defined as unchanged.
- [x] Required submitted fields cannot be empty or null.
- [x] Optional fields may be cleared.
- [x] Site-description maximum length is fixed at 500 characters.
- [x] Empty optional scalar text submitted through multipart is explicitly
  normalized to `null`.
- [x] Omitted optional scalar text remains unchanged.
- [x] No extra clear flags exist for optional scalar text fields.

## Singleton and Seeder Contract

- [x] Canonical singleton identity is fixed as `settings.id = 1`.
- [x] No Create Settings API exists.
- [x] No Delete Settings API exists.
- [x] No soft delete exists.
- [x] Seeder behavior is idempotent.
- [x] Seeder placeholders are approved.
- [x] Existing Admin-edited values are preserved according to repository
  seeding conventions.
- [x] Admin missing-record recovery is defined.
- [x] Public GET is prohibited from creating records as a side effect.
- [x] Missing-singleton Public GET returns the approved safe default
  representation.
- [x] Safe defaults preserve the exact approved Public response shape.
- [x] Unguarded `first()` singleton access is prohibited.

## Phone Contract

- [x] Phone item shape is exactly `{number, hasWhats}`.
- [x] Maximum count is exactly three.
- [x] Egyptian mobile numbers only are allowed.
- [x] `+20`, `0020`, spaces, dashes, and parentheses normalization is fixed.
- [x] Canonical stored format is fixed.
- [x] Duplicate checks occur after normalization.
- [x] `hasWhats` accepts exactly `0` or `1`.
- [x] Zero WhatsApp numbers is valid.
- [x] At most one WhatsApp number is allowed.
- [x] Phone order is preserved.
- [x] Phone row IDs are not accepted or returned.
- [x] Full replacement behavior is fixed.
- [x] An explicitly submitted empty `phones` array clears all phones.
- [x] Omitted `phones` behavior is fixed.
- [x] Omitted `phones` preserves the current collection.

## Social-Link Contract

- [x] Social item shape is exactly `{platform, url}`.
- [x] Every approved platform is listed.
- [x] Platform selection is enum-backed, not free text.
- [x] Unsupported platforms are rejected.
- [x] Duplicate platforms are rejected.
- [x] URL is required and validated.
- [x] Presence means visible; no `isActive` exists.
- [x] Submission order is preserved.
- [x] Social row IDs are not accepted or returned.
- [x] Full replacement behavior is fixed.
- [x] An explicitly submitted empty `socialLinks` array clears all links.
- [x] Omitted `socialLinks` behavior is fixed.
- [x] Omitted `socialLinks` preserves the current collection.
- [x] `availableSocialPlatforms` is fixed in the Admin response only.

## Branding and File Contract

- [x] Logo allowed formats are fixed.
- [x] Footer-logo allowed formats are fixed.
- [x] Favicon allowed formats are fixed.
- [x] Logo maximum size is fixed at 5 MB.
- [x] Footer-logo maximum size is fixed at 5 MB.
- [x] Favicon maximum size is fixed at 1 MB.
- [x] Omitted file behavior means keep current file.
- [x] Replacement-file behavior is fixed.
- [x] Empty `logo`, `footerLogo`, and `favicon` values remove the matching file.
- [x] File plus matching remove flag is rejected.
- [x] Client-controlled storage paths are rejected.
- [x] Responses return public URLs or null only.
- [x] New-file compensation after database failure is fixed.
- [x] Old-file deletion occurs after commit.
- [x] Post-commit cleanup failure preserves the valid database state and is
  logged.

## SVG Security Contract

- [x] SVG is allowed only for the approved branding fields.
- [x] Validation inspects content, not only extension or MIME.
- [x] Invalid XML/SVG is rejected.
- [x] Script content is prohibited.
- [x] JavaScript URLs are prohibited.
- [x] Event-handler attributes are prohibited.
- [x] `foreignObject`, `iframe`, `object`, and `embed` are prohibited.
- [x] External CSS, images, and resources are prohibited.
- [x] Stored SVG must be safe for public serving.

## Location Contract

- [x] `googleMapsUrl` validation is fixed.
- [x] Latitude and longitude must be submitted together.
- [x] Latitude range is fixed to `-90..90`.
- [x] Longitude range is fixed to `-180..180`.
- [x] Maps URL may exist without coordinates.
- [x] iframe/embed HTML is prohibited.
- [x] Coordinates return as decimal strings or null.

## SEO Contract

- [x] SEO title fields are localized.
- [x] SEO description fields are localized.
- [x] SEO keywords are localized arrays.
- [x] Keywords are not comma-separated strings.
- [x] Keywords must be non-empty after trimming.
- [x] Duplicate keywords are rejected after trimming and case-insensitive
  comparison.
- [x] Keyword order and original casing are otherwise preserved.
- [x] No Open Graph image exists in requests, persistence, or responses.

## API and Authorization Contract

- [x] Admin GET path is exact.
- [x] Admin PATCH path is exact.
- [x] Public GET path is exact.
- [x] PATCH content type is always multipart form-data.
- [x] `settings.view` authorization is exact.
- [x] `settings.update` authorization is exact.
- [x] Public GET is unauthenticated.
- [x] `401` and `403` outcomes are fixed.
- [x] Super-admin permissions are seeded without hidden bypass.
- [x] Shared envelope, `camelCase`, and existing `StatusCode::*` are required.

## Admin Response Contract

- [x] Admin response contains both Arabic and English values.
- [x] Admin response field inventory is fully enumerated.
- [x] Admin email key is `publicEmail`.
- [x] Admin returns `availableSocialPlatforms`.
- [x] Files return absolute public URLs or null.
- [x] Coordinates return decimal strings or null.
- [x] No phone/social IDs are exposed.
- [x] No timestamps, audit fields, or storage paths are exposed.

## Public Response Contract

- [x] Public response uses one localized projection.
- [x] Public neutral keys are fully enumerated.
- [x] Public email key is `email`.
- [x] Public default SEO nesting is fixed.
- [x] Missing-singleton Public response behavior is fixed to safe defaults
  without persistence.
- [x] Stable English machine keys are preserved across locales.
- [x] `Content-Language` is required.
- [x] `Vary: Accept-Language` is required.
- [x] No dual-language fields are exposed.
- [x] No internal IDs, timestamps, storage paths, or available-platform list are
  exposed.

## Atomicity and Data Integrity

- [x] Scalar, relational, and file-path updates are logically atomic.
- [x] Full request validation precedes destructive collection replacement.
- [x] Phone replacement is all-or-nothing.
- [x] Social replacement is all-or-nothing.
- [x] File/database compensation behavior is explicit.
- [x] Required fields cannot be left invalid.
- [x] Repeated updates cannot create extra Settings records.
- [x] Correctness does not depend on cache.

## Error and Security Boundaries

- [x] Stable feature error codes are listed.
- [x] Validation failures use the approved `422` behavior.
- [x] Internal SQL, exception, and path details are hidden.
- [x] Client filenames and MIME declarations are untrusted.
- [x] Safe generated filenames and path-traversal prevention are required.
- [x] Public and Admin storage-path leakage is prohibited.

## Verification Completeness

- [x] Admin read authorization tests are required.
- [x] Admin partial-update tests are required.
- [x] Required/optional field tests are required.
- [x] Phone normalization and replacement tests are required.
- [x] Social platform and replacement tests are required.
- [x] Coordinate tests are required.
- [x] SEO-array, duplicate-keyword, and no-OG-image tests are required.
- [x] Missing-singleton Public safe-default/no-write tests are required.
- [x] Multipart optional-scalar empty-string-to-null tests are required.
- [x] File type, size, replace, remove, and conflict tests are required.
- [x] Safe and unsafe SVG tests are required.
- [x] Filesystem compensation tests are required.
- [x] Atomic cross-section update tests are required.
- [x] Admin and Public response-contract tests are required.
- [x] Localization-header tests are required.
- [x] Database singleton and relational integrity tests are required.
- [x] MySQL, Pint, and PHPStan/Larastan quality gates are required.

## Final Readiness

- [x] Every approved business rule is represented in `spec.md`.
- [x] Every request mutation semantic is represented in `spec.md`.
- [x] Every Admin response field is represented in `spec.md`.
- [x] Every Public response field is represented in `spec.md`.
- [x] Every approved security boundary is represented in `spec.md`.
- [x] No unresolved ambiguity requires `/speckit.clarify`.
- [x] The specification is ready for `/speckit.plan`.
