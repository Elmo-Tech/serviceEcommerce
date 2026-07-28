# Implementation Plan: [FEATURE]

**Branch**: `[###-feature-name]` | **Date**: [DATE] | **Spec**: [link]

**Input**: Feature specification from `/specs/[###-feature-name]/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

[Extract from feature spec: primary requirement + technical approach from research]

## Technical Context

<!--
  ACTION REQUIRED: Replace the content in this section with the technical details
  for the project. The structure here is presented in advisory capacity to guide
  the iteration process.
-->

**Language/Version**: PHP version pinned by the repository for Laravel 13

**Primary Dependencies**: Laravel 13, Laravel Sanctum, `spatie/laravel-permission`,
`spatie/laravel-query-builder`

**Storage**: MySQL and Laravel Filesystem on the approved configurable disk

**Testing**: Pest with a dedicated MySQL test database; Laravel Pint and
Larastan/PHPStan quality gates

**Target Platform**: Hostinger-compatible production PHP/MySQL environment;
confirm queue, scheduler, Cron, and shell availability before depending on them

**Project Type**: Backend-only, API-first conventional Laravel monolith

**Performance Goals**: [Measurable feature-specific API and workload goals or
NEEDS CLARIFICATION]

**Constraints**: Stable `/api/v1` JSON contracts; Arabic and English
localization; backend-controlled authorization, pricing, state, and ownership;
no unapproved frontend or infrastructure scope

**Scale/Scope**: [Expected records, requests, file volume, and bounded list
sizes or NEEDS CLARIFICATION]

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

The plan MUST record PASS or an explicit unresolved violation for every gate:

- **Authority, precedence, and traceability**: Every planned behaviour maps to
  an approved requirement or defect fix; the active Feature complies with
  shared standards, and feature-specific decisions only specialize details
  that governing standards intentionally leave configurable.
- **Conflict and exception gate**: Governing documents have no unresolved
  conflict. Planning MUST stop when a feature would weaken or contradict a
  higher-level rule. Any exception has explicit user approval and is documented
  first in the affected governing files.
- **Repository boundary**: Backend-only Laravel work; no React, Next.js, Blade
  product UI, customer portal, or unapproved infrastructure.
- **Architecture**: Thin Controllers, Form Requests, Resources, and only
  currently justified Actions, Services, Queries, Jobs, or abstractions.
- **API contract**: Versioned routes, stable envelopes and machine codes,
  `camelCase` API keys, bounded pagination, and allow-listed query parameters.
- **Authentication and authorization**: Approved Sanctum/session rules,
  explicit Spatie permissions, resource scoping, and correct `401`/`403`/`404`
  behaviour.
- **Trust and content safety**: Backend authority for sensitive values,
  allow-listed input, plain-text content, safe URLs, and no sensitive output.
- **Database integrity**: MySQL constraints, precise money, enums, transactions,
  locks, immutable snapshots, deletion behaviour, and concurrency strategy.
- **Files**: Filesystem abstraction, MIME/extension/size/count validation,
  random names, safe exposure, and rollback compensation.
- **Localization**: Arabic/English messages and content with stable English
  machine identifiers and locale metadata.
- **Testing and quality**: Mandatory Pest coverage on MySQL, including relevant
  failure and race cases; Pint and Larastan/PHPStan gates.
- **Scope and operations**: No speculative features or dependencies; Hostinger
  capabilities are verified before relying on workers, Cron, scheduler, Redis,
  Docker, or shell access.

A failed gate blocks planning and implementation. Any proposed exception MUST
be documented in Complexity Tracking with risk and mitigation, MUST receive
explicit user approval, and MUST be recorded in the affected governing files
before planning or implementation resumes. Re-run this check after Phase 1
design.

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)
<!--
  ACTION REQUIRED: Replace the placeholder tree below with the concrete layout
  for this feature. Delete unused options and expand the chosen structure with
  real Laravel paths. Do not add directories that the feature does not require.
-->

```text
app/
├── Actions/             # only complex, named use-case orchestration
├── Enums/
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Middleware/
│   ├── Requests/Api/V1/
│   └── Resources/Api/V1/
├── Jobs/                # only approved asynchronous work
├── Models/
├── Policies/
├── Queries/             # only complex read operations
├── Rules/
├── Services/            # focused reusable capabilities
└── Support/

database/
├── factories/
├── migrations/
└── seeders/

routes/
├── api.php
└── api/v1/

tests/
├── Feature/Api/V1/
└── Unit/
```

**Structure Decision**: [Document the selected structure and reference the real
directories captured above]

## Complexity Tracking

> **Fill ONLY for a proposed Constitution Check exception. Every row requires
> explicit user approval before implementation.**

| Violation | Why Needed | Risk and Mitigation | User Approval |
|-----------|------------|---------------------|---------------|
| [Principle or gate] | [Current requirement] | [Risk and mitigation] | [Pending/Approved with reference] |
