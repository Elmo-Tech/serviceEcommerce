# Service Commerce Backend — Localization Standards

## Purpose

This document defines the mandatory localization standards for the Service
Commerce Backend.

The repository is a backend-only Laravel 13 application.

Localization is part of:

- the API contract
- validation behaviour
- authentication and authorization responses
- business-rule errors
- transactional email
- public catalogue content
- service order questions
- service option labels
- site content
- backend-generated exports and reports when approved

Localization is not a frontend-only responsibility.

The backend supports Arabic and English while preserving one stable
machine-readable API contract.

---

## 1. Repository Scope

These rules apply to backend responsibilities including:

- API success messages
- API error messages
- authentication messages
- authorization messages
- validation messages
- business-rule errors
- category and subcategory content
- service content
- service specification labels and values
- service option labels and values
- service order questions
- question help text and placeholders
- question-choice labels
- order status labels when explicitly returned
- site settings and public content
- hero content
- FAQs
- Contact Us email templates
- administrator reply emails
- backend-generated exports and reports
- localized metadata endpoints

The following remain outside this backend repository:

- React translation libraries
- Next.js translation libraries
- frontend locale persistence
- frontend RTL layout implementation
- frontend route localization
- frontend date, number, and currency display formatting
- frontend SEO rendering
- browser language detection
- frontend language switcher UI

External consumers are responsible for their interface translations.

The backend is responsible for localized data and messages returned through its
approved APIs.

---

## 2. Related Documents

Localization work MUST comply with:

- `AGENTS.md`
- `.specify/memory/constitution.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- `docs/02-standards/api-standards.md`
- `docs/02-standards/code-standards.md`
- `docs/02-standards/database-standards.md`
- the active feature's `spec.md`, `plan.md`, and `tasks.md`

When these documents conflict, implementation MUST stop until the governing
documents are synchronized.

The supported locale list MUST NOT be changed inside one Feature without
updating this standard and the governing project documentation.

---

## 3. Supported Locales

The backend MUST support:

| Locale | Language | Direction | Status |
|---|---|---|---|
| `ar` | Arabic | RTL | Active |
| `en` | English | LTR | Active |

Locale identifiers:

- remain lowercase
- remain stable
- are not translated
- are not represented as numeric domain values
- are not replaced by display language names in API fields

Recommended enum:

```php
<?php

declare(strict_types=1);

namespace App\Enums\Localization;

enum SupportedLocale: string
{
    case AR = 'ar';
    case EN = 'en';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function direction(): string
    {
        return match ($this) {
            self::AR => 'rtl',
            self::EN => 'ltr',
        };
    }
}
```

A centralized configuration array may be used instead when that is the approved
project convention.

Do not duplicate supported locale arrays across:

- middleware
- Form Requests
- controllers
- Jobs
- Mail classes
- Resources
- tests

---

## 4. Default and Fallback Locale

The initial application default locale is:

```text
ar
```

The fallback locale is:

```text
en
```

Expected configuration:

```env
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
```

Rules:

- Default and fallback locales MUST remain configurable.
- Do not hard-code `ar` or `en` throughout application code.
- Missing Arabic translations may fall back to English.
- Missing English translations must still be treated as a release-quality
  defect.
- Fallback behaviour MUST NOT expose raw translation keys.
- Feature code MUST NOT implement its own unrelated fallback order.

Resolution fallback:

```text
explicit supported request locale
-> stored locale preference when approved and available
-> application default locale
-> application fallback locale
```

For guest requests, no authenticated user preference exists.

The request locale and persisted order or enquiry locale therefore become
important when asynchronous work is later performed.

---

## 5. Locale Resolution

The backend MUST resolve locale before generating:

- validation errors
- authentication responses
- authorization responses
- business errors
- API success messages
- localized Resources
- localized email
- localized option metadata

Resolution order:

1. Explicit `Accept-Language` header.
2. Authenticated administrator preference when that capability is implemented
   and no explicit supported header was supplied.
3. Application default locale.
4. Application fallback locale.

Examples:

```http
Accept-Language: ar
```

```http
Accept-Language: en
```

Regional locale values MAY be normalized when the mapping is unambiguous.

Examples:

```text
ar-EG -> ar
ar-SA -> ar
en-GB -> en
en-US -> en
```

Unsupported or malformed header values fall back safely.

They do not produce a server error.

When an endpoint explicitly accepts a `locale` field or query parameter:

- it MUST be validated
- unsupported values return `422`
- the field does not override security or authorization
- the behaviour must be documented in the feature contract

A middleware such as:

```text
ResolveApiLocale
```

SHOULD call:

```php
app()->setLocale($resolvedLocale);
```

before validation and controller execution.

---

## 6. Response Locale Metadata

The backend SHOULD return:

```http
Content-Language: ar
```

or:

```http
Content-Language: en
```

according to the resolved locale.

Responses that may be cached by language SHOULD include:

```http
Vary: Accept-Language
```

Rules:

- Do not add locale metadata to every item.
- Do not duplicate the locale inside every Resource.
- The API MAY expose response-level locale metadata when useful.

Example:

```json
{
  "success": true,
  "message": "تم جلب الخدمات بنجاح.",
  "data": [],
  "meta": {
    "locale": "ar",
    "direction": "rtl"
  }
}
```

The `meta.locale` and `meta.direction` fields are optional and must be
consistent when implemented.

---

## 7. Stable Machine Contract

Localization MUST NOT change the machine-readable contract.

The following remain stable and in English:

- route paths
- JSON keys
- request field names
- response field names
- query parameter names
- enum values
- permission identifiers
- role identifiers
- database columns
- translation keys
- machine-readable error codes
- HTTP status codes
- order numbers
- IDs
- file extensions
- MIME types

Example Arabic response:

```json
{
  "success": false,
  "message": "الخدمة غير متاحة حالياً.",
  "code": "SERVICE_UNAVAILABLE",
  "errors": null
}
```

Example English response:

```json
{
  "success": false,
  "message": "The service is currently unavailable.",
  "code": "SERVICE_UNAVAILABLE",
  "errors": null
}
```

Only the human-readable `message` changes.

---

## 8. Translation Key Standards

Translation keys MUST be:

- stable
- descriptive
- written in English
- independent from the translated sentence
- organized by feature
- expressed using dot notation

Examples:

```text
auth.login_success
auth.logout_success
auth.authentication_failed
auth.user_inactive

api.authentication_required
api.permission_denied
api.resource_not_found
api.validation_failed
api.rate_limited
api.internal_error

customers.created
customers.updated
customers.deactivated

categories.created
categories.updated
categories.subcategory_created
categories.errors.third_level_forbidden
categories.errors.inactive

services.created
services.updated
services.errors.unavailable
services.errors.not_orderable

service_questions.created
service_questions.updated
service_questions.errors.required_answer_missing
service_questions.errors.invalid_answer_type

orders.created
orders.cancelled
orders.errors.pricing_incomplete
orders.errors.invalid_status_transition

attachments.errors.invalid_type
attachments.errors.size_exceeded

contact_messages.created
contact_messages.reply_saved
contact_messages.reply_queued
```

Bad:

```text
تم إنشاء الطلب بنجاح
The order has been created successfully.
```

Good:

```text
orders.created
```

Rules:

- Do not expose translation keys in production responses.
- Do not rename stable keys casually.
- Key changes require updating all usages and tests.
- Do not use one generic key when feature-specific meaning is required.
- Avoid keys based on controller or class names.

---

## 9. Translation File Organization

Recommended Laravel structure:

```text
lang/
├── ar/
│   ├── api.php
│   ├── auth.php
│   ├── validation.php
│   ├── customers.php
│   ├── categories.php
│   ├── services.php
│   ├── service_questions.php
│   ├── orders.php
│   ├── attachments.php
│   ├── contact_messages.php
│   ├── site_content.php
│   ├── mail.php
│   ├── permissions.php
│   └── enums.php
└── en/
    ├── api.php
    ├── auth.php
    ├── validation.php
    ├── customers.php
    ├── categories.php
    ├── services.php
    ├── service_questions.php
    ├── orders.php
    ├── attachments.php
    ├── contact_messages.php
    ├── site_content.php
    ├── mail.php
    ├── permissions.php
    └── enums.php
```

Rules:

- Only create files needed by implemented Features.
- Do not create empty files merely to match the example.
- Equivalent keys MUST exist in both locales.
- File and key structure SHOULD remain identical between `ar` and `en`.
- Feature-specific translation files are preferred over one giant file.
- Shared generic API messages belong in `api.php`.

---

## 10. Translation File Examples

English authentication messages:

```php
<?php

declare(strict_types=1);

return [
    'login_success' => 'Login successful.',
    'logout_success' => 'Logout successful.',
    'authentication_failed' => 'The provided credentials are invalid.',
    'user_inactive' => 'This account is inactive.',
];
```

Arabic authentication messages:

```php
<?php

declare(strict_types=1);

return [
    'login_success' => 'تم تسجيل الدخول بنجاح.',
    'logout_success' => 'تم تسجيل الخروج بنجاح.',
    'authentication_failed' => 'بيانات تسجيل الدخول غير صحيحة.',
    'user_inactive' => 'هذا الحساب غير نشط.',
];
```

English order messages:

```php
<?php

declare(strict_types=1);

return [
    'created' => 'The order has been created successfully.',
    'cancelled' => 'The order has been cancelled successfully.',
    'errors' => [
        'pricing_incomplete' => 'The order pricing is not complete.',
        'invalid_status_transition' => 'The order cannot move to the requested status.',
    ],
];
```

Arabic order messages:

```php
<?php

declare(strict_types=1);

return [
    'created' => 'تم إنشاء الطلب بنجاح.',
    'cancelled' => 'تم إلغاء الطلب بنجاح.',
    'errors' => [
        'pricing_incomplete' => 'تسعير الطلب غير مكتمل.',
        'invalid_status_transition' => 'لا يمكن نقل الطلب إلى الحالة المطلوبة.',
    ],
];
```

Translations SHOULD be reviewed for:

- correctness
- clarity
- natural phrasing
- domain appropriateness
- consistency

Literal word-for-word translation is not always the best translation.

---

## 11. Backend Message Rules

Every backend user-facing message MUST use a translation key.

Bad:

```php
return ApiResponse::success(
    data: $order,
    message: 'Order created successfully.',
);
```

Good:

```php
return ApiResponse::success(
    data: $order,
    message: __('orders.created'),
);
```

This applies to:

- controllers
- Actions
- Services
- Form Requests
- Exceptions
- Jobs
- Listeners
- Mail classes
- Notifications
- API Resources when returning localized labels
- backend-generated reports
- console output intended for business users

Technical logs may use English diagnostic text.

Logs MUST NOT include:

- passwords
- access tokens
- authorization headers
- secrets
- uploaded file contents
- full customer answers
- unnecessary personal data

---

## 12. API Response Contract

Localized responses MUST follow:

```text
docs/02-standards/api-standards.md
```

Arabic success:

```json
{
  "success": true,
  "message": "تم إنشاء الخدمة بنجاح.",
  "data": {
    "id": 25
  }
}
```

English success:

```json
{
  "success": true,
  "message": "The service has been created successfully.",
  "data": {
    "id": 25
  }
}
```

Arabic business error:

```json
{
  "success": false,
  "message": "يجب الإجابة عن جميع الأسئلة المطلوبة.",
  "code": "REQUIRED_SERVICE_ANSWER_MISSING",
  "errors": null
}
```

English business error:

```json
{
  "success": false,
  "message": "All required service questions must be answered.",
  "code": "REQUIRED_SERVICE_ANSWER_MISSING",
  "errors": null
}
```

Rules:

- `message` is localized.
- `code` is not translated.
- Field names are not translated.
- HTTP status is identical across locales.
- Response structure is identical across locales.
- Business meaning must remain equivalent.

---

## 13. Validation Localization

Mutating APIs MUST use Laravel Form Requests.

Validation messages MUST exist in:

- Arabic
- English

Rules:

- Do not hard-code validation messages in controllers.
- Use Laravel standard validation translations when adequate.
- Add feature-specific messages when they clarify business constraints.
- Validation error keys remain API field names.
- Validation field keys are never translated.
- Nested array indexes remain unchanged.

Correct Arabic response:

```json
{
  "errors": {
    "items.0.answers.2.value": [
      "هذا الحقل مطلوب."
    ]
  }
}
```

Correct English response:

```json
{
  "errors": {
    "items.0.answers.2.value": [
      "This field is required."
    ]
  }
}
```

Incorrect:

```json
{
  "errors": {
    "الإجابات": [
      "هذا الحقل مطلوب."
    ]
  }
}
```

### 13.1 Localized Attribute Names

Validation attributes SHOULD be defined in both locales.

English:

```php
'attributes' => [
    'email' => 'email address',
    'phone' => 'phone number',
    'subcategoryId' => 'subcategory',
    'serviceId' => 'service',
    'quantity' => 'quantity',
    'clientReference' => 'item reference',
    'cancellationReason' => 'cancellation reason',
],
```

Arabic:

```php
'attributes' => [
    'email' => 'البريد الإلكتروني',
    'phone' => 'رقم الهاتف',
    'subcategoryId' => 'القسم الفرعي',
    'serviceId' => 'الخدمة',
    'quantity' => 'الكمية',
    'clientReference' => 'مرجع عنصر الطلب',
    'cancellationReason' => 'سبب الإلغاء',
],
```

---

## 14. Authentication and Authorization Messages

Authentication rules:

- Login success is localized.
- Logout success is localized.
- Invalid credentials use one generic localized message.
- Email existence must not be revealed.
- Inactive-account behaviour follows the approved authentication contract.
- Missing or invalid Sanctum token uses a localized authentication-required
  message.
- Permission failure uses a localized forbidden message.
- Access tokens are never translated.
- `Bearer` is never translated.
- Permission identifiers are never translated.
- Error codes remain unchanged.

Stable codes may include:

```text
INVALID_CREDENTIALS
USER_INACTIVE
UNAUTHENTICATED
FORBIDDEN
RATE_LIMITED
```

The active feature specification decides whether inactive and invalid
credentials share one generic public message.

---

## 15. Business Error Localization

Every business error uses:

1. localized message
2. stable English code
3. correct HTTP status
4. global error envelope

Examples:

```text
CATEGORY_INACTIVE
SUBCATEGORY_INACTIVE
CATEGORY_HIERARCHY_INVALID
SERVICE_UNAVAILABLE
OPTION_VALUE_UNAVAILABLE
REQUIRED_SERVICE_ANSWER_MISSING
INVALID_SERVICE_ANSWER
ORDER_PRICING_INCOMPLETE
ORDER_STATUS_CONFLICT
ORDER_CANCELLATION_REASON_REQUIRED
ATTACHMENT_TYPE_NOT_ALLOWED
```

The code is identical in Arabic and English.

Feature specifications MUST document:

- translation key
- machine code
- HTTP status
- triggering condition
- interpolation values
- required tests

---

## 16. Exception Localization

Domain exceptions SHOULD carry:

- stable machine code
- translation key
- safe interpolation data
- HTTP status enum

Illustrative pattern:

```php
throw new BusinessRuleException(
    code: 'REQUIRED_SERVICE_ANSWER_MISSING',
    messageKey: 'service_questions.errors.required_answer_missing',
    status: HTTP_RESPONSE_CODE::UNPROCESSABLE_ENTITY,
);
```

The global exception handler resolves the final message using the active locale.

Exceptions MUST NOT expose:

- SQL
- stack traces
- filesystem paths
- class names
- access tokens
- secret configuration
- internal storage paths
- private customer answers

---

## 17. Localized Public Content

The system is bilingual, not only the API messages.

Public catalogue and site content managed by the administrator MUST support:

- Arabic
- English

This includes approved fields for:

- category names
- category descriptions
- subcategory names
- subcategory descriptions
- service names
- short descriptions
- full descriptions
- service specification labels and values
- service option-group labels
- service option-value labels
- service order-question labels
- question help text
- question placeholders
- question-choice labels
- hero titles and subtitles
- hero button text
- FAQ questions and answers
- site contact labels
- SEO titles
- SEO descriptions
- approved site-content fields

### 17.1 Recommended Storage Strategy

Because the project has two fixed active locales, explicit columns are
recommended for queryable public content.

Examples:

```text
name_ar
name_en
description_ar
description_en
short_description_ar
short_description_en
label_ar
label_en
value_ar
value_en
help_text_ar
help_text_en
placeholder_ar
placeholder_en
seo_title_ar
seo_title_en
seo_description_ar
seo_description_en
```

Benefits:

- explicit schema
- simple validation
- simple Resources
- easy indexing
- predictable fallback
- no uncontrolled translation JSON
- no translation package dependency

Do not create one uncontrolled JSON column such as:

```json
{
  "ar": "...",
  "en": "..."
}
```

for every core catalogue field unless a later architecture decision changes the
storage standard.

### 17.2 Translation Tables

Separate translation tables may be approved later when:

- more locales are added
- language count becomes dynamic
- translation workflows require independent records
- translation metadata becomes necessary

Do not introduce translation tables merely for theoretical scalability while
the approved locale set remains two fixed languages.

---

## 18. Localized Field Naming

Database localized columns use language suffixes:

```text
name_ar
name_en
description_ar
description_en
label_ar
label_en
```

API Resources normally return one resolved field:

Arabic:

```json
{
  "name": "تطوير المواقع",
  "description": "خدمة متكاملة لتطوير المواقع."
}
```

English:

```json
{
  "name": "Website Development",
  "description": "A complete website development service."
}
```

Administration APIs MAY return both translations for editing:

```json
{
  "name": {
    "ar": "تطوير المواقع",
    "en": "Website Development"
  },
  "description": {
    "ar": "خدمة متكاملة لتطوير المواقع.",
    "en": "A complete website development service."
  }
}
```

The exact administration shape belongs in the feature contract.

Rules:

- Public APIs should return resolved localized content.
- Administration create and update APIs should accept both translations.
- Do not mix resolved string and translation object unpredictably in one
  endpoint.
- Resource shapes must be documented.

---

## 19. Translation Completeness for Publication

Core public catalogue records SHOULD be complete in both Arabic and English
before becoming active.

Required bilingual content may include:

- category name
- subcategory name
- service name
- service descriptions
- active service-question label
- active choice label
- active option label
- active FAQ question and answer
- active hero text

Rules:

- Draft or inactive records MAY be incomplete when the feature specification
  allows it.
- Activation or publication SHOULD validate required Arabic and English fields.
- Missing translations in one locale must not silently publish an unusable
  public experience.
- Exact required bilingual fields belong in each feature specification.
- The backend enforces completeness; the dashboard alone is not sufficient.

---

## 20. Slugs and Stable Identifiers

Slugs are stable machine and URL identifiers.

The MVP SHOULD use one stable slug per resource unless a feature explicitly
approves localized slugs.

Examples:

```text
website-development
graphic-design
business-cards
```

Rules:

- Do not automatically translate slugs per request.
- Do not change slug based on `Accept-Language`.
- Do not use localized display names as authorization identifiers.
- Slug updates must follow the Services or Categories feature contract.
- Localized SEO title and description may differ while the slug remains stable.
- Question keys and option keys remain stable and untranslated.

---

## 21. Service Specifications and Options

### 21.1 Specifications

Service specification labels and values are system-managed public content.

They SHOULD support both locales:

```text
label_ar
label_en
value_ar
value_en
```

Public API returns the resolved language.

Specifications remain display-only.

### 21.2 Option Groups and Values

Option-group labels and option-value labels support both locales.

Example administration payload:

```json
{
  "label": {
    "ar": "اللون",
    "en": "Colour"
  }
}
```

Choice identifiers, IDs, availability, quantity rules, and price adjustments
are not translated.

The backend validates selected IDs, not selected translated labels.

---

## 22. Service Order Question Localization

Service order questions are defined by administrators and displayed to guest
customers.

Localized fields should include:

```text
label_ar
label_en
help_text_ar
help_text_en
placeholder_ar
placeholder_en
```

Choice questions should include:

```text
option_key
label_ar
label_en
```

Rules:

- `question_key` is stable and untranslated.
- `option_key` is stable and untranslated.
- `input_type` is untranslated.
- `is_required` is a boolean and untranslated.
- Validation configuration keys are untranslated.
- Question labels must be available in the resolved locale.
- Required answer validation does not change by locale.
- Translated labels do not determine question ownership.
- Submitted answers are validated using question IDs and stable keys.

### 22.1 Answer Snapshots

Historical order snapshots should preserve the localized question values needed
for future display.

Recommended direction:

```text
question_label_ar_snapshot
question_label_en_snapshot
option_label_ar_snapshot
option_label_en_snapshot
```

Alternatively, a feature may preserve:

- the requested order locale
- the resolved label snapshot for that locale
- the stable question key

However, when administrators must view old orders in both languages, preserving
both language snapshots is preferred.

The chosen snapshot strategy must be explicit in the Orders and Service
Questions feature specifications.

---

## 23. Order Locale

Guest order submission SHOULD persist the resolved order locale.

Recommended field:

```text
orders.locale
```

Allowed values:

```text
ar
en
```

Purpose:

- preserve the language used during order submission
- localize later operational email
- render order-related backend documents
- retain question-context language when needed

Rules:

- locale is resolved by the backend
- frontend locale input is validated
- locale does not change pricing or authorization
- locale is not a substitute for bilingual snapshots
- changing application default later does not rewrite historical order locale

---

## 24. Contact Enquiry Locale

Public Contact Us submission SHOULD preserve the resolved locale.

Recommended field:

```text
contact_messages.locale
```

Purpose:

- send the reply email in the appropriate language
- choose the correct email subject and wrapper
- preserve the enquiry interaction language

The administrator's typed reply content is not automatically translated.

The surrounding email template is localized.

---

## 25. User-Generated Content

The backend MUST NOT automatically translate user-generated content.

This includes:

- customer names
- customer addresses
- customer notes
- service-question answers
- uploaded filenames
- Contact Us messages
- administrator free-form replies
- administrative notes
- testimonial text copied from a real customer
- customer-provided names
- custom design instructions

These values are returned as stored, subject to:

- authorization
- sanitization
- output safety
- privacy rules

Localization applies to surrounding labels and messages, not to user-authored
content.

An administrator may manually enter translated content only when the feature
explicitly provides separate translation fields.

---

## 26. Roles and Permissions

Spatie role and permission identifiers MUST NOT be translated.

Examples:

```text
super-admin

services.view
services.create
services.update
orders.view
orders.price
orders.cancel
contact-messages.reply
```

Authorization checks always use technical identifiers.

A metadata endpoint MAY return a separate label:

Arabic:

```json
{
  "name": "orders.cancel",
  "label": "إلغاء الطلب"
}
```

English:

```json
{
  "name": "orders.cancel",
  "label": "Cancel order"
}
```

The localized label never replaces the permission name.

---

## 27. Enum Labels

Enum values remain stable English contract values.

Example:

```json
{
  "status": "awaiting_review"
}
```

When an approved endpoint requires a localized label, return it separately.

Arabic:

```json
{
  "status": "awaiting_review",
  "statusLabel": "بانتظار المراجعة"
}
```

English:

```json
{
  "status": "awaiting_review",
  "statusLabel": "Awaiting review"
}
```

Rules:

- `status` remains unchanged.
- `statusLabel` is localized.
- Do not replace enum value with the label.
- Do not add label fields automatically to every endpoint.
- Labels belong in `enums.php` or feature files.
- All locale mappings must cover the same enum cases.

Possible localized enum groups:

```text
service_publication_status
service_availability_status
service_pricing_type
duration_type
duration_unit
order_status
order_pricing_status
question_input_type
contact_message_status
```

---

## 28. Dates, Times, Numbers, and Money

The backend returns machine-stable values.

Example:

```json
{
  "createdAt": "2026-07-28T10:30:00Z",
  "basePrice": "150.00",
  "quantity": 2,
  "orderNumber": "ORD-260728-5896"
}
```

Rules:

- Timestamps remain ISO 8601 UTC.
- Do not localize timestamp strings in normal API responses.
- Decimal separator remains `.` in JSON money strings.
- Do not add Arabic numerals to machine values.
- Do not translate IDs.
- Do not translate order numbers.
- Do not translate MIME types or extensions.
- Do not change quantity or price semantics by locale.
- Frontend consumers format values for display.
- Backend-generated emails and reports may format values for the selected
  locale when explicitly required.

---

## 29. Emails and Queued Jobs

Transactional email SHOULD use the intended recipient or interaction locale.

For guest-driven flows:

```text
stored order/contact locale
-> application default locale
-> fallback locale
```

Jobs MUST carry or reload the intended locale.

Bad:

```php
SendContactReplyMailJob::dispatch(
    contactMessageId: $id,
    body: 'Your message has been answered.',
);
```

Good:

```php
SendContactReplyMailJob::dispatch(
    contactMessageId: $id,
);
```

The Job reloads:

- contact message
- reply
- stored locale
- recipient data

Then resolves:

```text
mail.contact_reply.subject
mail.contact_reply.introduction
mail.contact_reply.footer
```

Rules:

- Do not dispatch pretranslated system sentences when structured data is
  sufficient.
- Preserve locale explicitly because queued execution happens after the request.
- Administrator free-form reply text remains unchanged.
- Email template wrapper is localized.
- Job dispatch means queued, not delivered.
- Email failure messages in APIs follow the request locale.
- Technical failure logs may remain English.

---

## 30. Backend-Generated Exports and Reports

When an approved export or report is localized:

- locale must be explicit
- headings may be translated
- enum labels may be translated
- system labels may be translated
- raw IDs remain unchanged
- order numbers remain unchanged
- timestamps remain machine-consistent or follow documented report formatting
- customer-entered values remain unchanged
- report metadata should identify the locale
- exported filenames may be localized only when documented

Report generation must not translate customer answers or free-form notes
automatically.

---

## 31. Missing Translation Behaviour

Missing translations MUST NOT break public API execution.

Production behaviour:

1. resolve the fallback locale
2. return a safe fallback message
3. do not expose the raw translation key
4. log the missing key safely
5. preserve the API response structure

Development and test environments may surface stricter diagnostics.

A missing translation is a release-quality issue.

Fallback is a safety mechanism, not permission to ship incomplete translations.

### 31.1 Missing Public Content Translation

For public content:

- active content should already satisfy bilingual completeness rules
- when a translation is unexpectedly missing, use the approved field fallback
- do not return the raw database column name
- do not return a translation object when the endpoint promises a string
- log the content record and missing locale safely
- do not expose private draft content as fallback

---

## 32. Logging and Observability

Localization-related logs MAY include:

```text
translation_key
requested_locale
resolved_locale
fallback_locale
resource_type
resource_id
route
request_id
```

Logs MUST NOT include:

- passwords
- tokens
- authorization headers
- secrets
- private file contents
- full customer answers
- unnecessary customer data
- full Contact Us message text

Repeated missing translation events should be treated as a release defect.

---

## 33. Testing Requirements

Automated tests MUST verify localization behaviour.

### 33.1 Locale Resolution

- `ar` is supported.
- `en` is supported.
- Arabic regional locales normalize to `ar`.
- English regional locales normalize to `en`.
- Unsupported headers fall back safely.
- Missing header uses the configured default.
- Explicit invalid locale fields return validation errors.
- `Content-Language` matches the resolved locale where implemented.
- `Vary: Accept-Language` is returned where locale caching applies.

### 33.2 API Messages

- success messages are Arabic when `ar` is resolved
- success messages are English when `en` is resolved
- validation messages are translated
- business errors are translated
- authentication errors remain generic
- permission errors are translated
- error codes remain identical
- HTTP statuses remain identical
- response structure remains identical
- validation field keys remain unchanged

### 33.3 Public Content

- category content resolves in Arabic
- category content resolves in English
- service content resolves in Arabic
- service content resolves in English
- option labels resolve correctly
- question labels resolve correctly
- question choices resolve correctly
- inactive or draft fallback content is not exposed
- activation rejects missing required bilingual content where specified

### 33.4 Service Questions

- required-answer logic is identical in both locales
- question IDs and keys remain stable
- translated labels do not affect ownership validation
- answer snapshot strategy preserves required localized values
- customer answers are not translated

### 33.5 Enums and Permissions

- enum values remain stable
- localized labels are correct when included
- permission identifiers are unchanged
- role identifiers are unchanged
- API keys remain `camelCase`

### 33.6 Email and Queue

- Contact Us reply Job uses stored enquiry locale
- queued Job preserves intended locale
- Arabic email template is selected correctly
- English email template is selected correctly
- free-form reply body is not automatically translated
- missing translation falls back safely
- queued is not represented as delivered

### 33.7 Missing Translations

- missing Arabic key falls back safely
- missing English key does not expose raw key
- missing content translation follows field fallback
- localization failures do not change authorization or pricing behaviour

---

## 34. Feature Specification Requirements

Every feature that returns user-facing messages or localized content MUST
document:

- translation keys
- supported localized fields
- required Arabic fields
- required English fields
- activation completeness rules
- success messages
- business error messages
- stable error codes
- enum labels when returned
- locale resolution behaviour
- public Resource shape
- administration translation input shape
- fallback behaviour
- snapshot requirements
- email locale behaviour
- required localization tests

Feature contracts must show Arabic and English examples for critical responses.

Features MUST NOT redefine the global locale list independently.

---

## 35. Implementation Checklist

Before a Feature is complete:

- [ ] No user-facing backend message is hard-coded.
- [ ] Arabic translations exist.
- [ ] English translations exist.
- [ ] Translation keys match across locale files.
- [ ] `Accept-Language` is resolved before validation.
- [ ] `Content-Language` is correct where implemented.
- [ ] API messages follow the shared envelope.
- [ ] Error codes remain stable and untranslated.
- [ ] API keys remain `camelCase`.
- [ ] Enum values remain stable.
- [ ] Permission identifiers remain stable.
- [ ] Public localized fields follow the approved Resource shape.
- [ ] Administration APIs accept approved Arabic and English fields.
- [ ] Active public content satisfies translation completeness.
- [ ] Slugs and machine keys are not translated.
- [ ] Service questions and choices support both languages.
- [ ] Required-answer validation is locale-independent.
- [ ] User-generated content is not translated.
- [ ] Order or enquiry locale is persisted when required.
- [ ] Queued Jobs preserve the intended locale.
- [ ] Missing translations do not expose raw keys.
- [ ] Arabic and English localization tests pass.

---

## 36. Non-Negotiable Rules

- Arabic and English are both active supported locales.
- Default locale is configurable and initially Arabic.
- English is the configured fallback locale.
- Do not hard-code user-facing API messages.
- Do not translate API keys.
- Do not translate error codes.
- Do not translate HTTP status codes.
- Do not translate enum values.
- Do not translate permission identifiers.
- Do not translate role identifiers.
- Do not translate access tokens or authorization headers.
- Do not translate order numbers or IDs.
- Do not automatically translate customer-entered content.
- Do not let localization change pricing, authorization, validation ownership,
  or workflow behaviour.
- Do not store core bilingual catalogue content in one uncontrolled JSON blob.
- Do not change Resource shape according to locale.
- Do not publish required public content with missing mandatory Arabic or
  English translations.
- Do not use translated question labels as identifiers.
- Do not lose historical question meaning when translations change.
- Do not claim queued email is delivered.
- Every supported locale must provide validation and common API messages.
- Security-sensitive messages must remain safe and generic in both locales.
