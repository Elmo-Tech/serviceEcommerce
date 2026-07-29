# Feature 001 Requirement Traceability

**Feature:** Identity and Authentication  
**Last updated:** 2026-07-29

This file records the backend-verifiable mapping used during implementation. It
does not replace `tasks.md`; task completion still requires code and named
verification evidence.

## Phase-level mapping

| Requirement group | Primary task coverage | Evidence target |
|---|---|---|
| `FR-001`-`FR-007` | T001, T008, T051, T053, T101, T114 | Route, architecture, Postman, and obsolete-concept scans. |
| `FR-008`-`FR-022` | T004, T025-T030 | Provisioning config, seeders, and persistence tests. |
| `FR-023`-`FR-035` | T049, T055-T060 | Login JSON token contract and session replacement tests. |
| `FR-036`-`FR-053` | T102-T112, T116 | External React evidence; blocked when the owning frontend repository is absent. |
| `FR-054`-`FR-077` | T042, T051-T054, T061-T072, T101, T114 | Refresh/logout routes, services, throttling, revocation, and contract tests. |
| `FR-078`-`FR-116` | T043, T048, T073-T082 | Profile, avatar, and password-change implementation/tests. |
| `FR-117`-`FR-167` | T036, T045-T047, T083-T094 | Password recovery workflow, reset tokens, and recovery tests. |
| `FR-168`-`FR-182` | T009-T014, T099 | API envelopes, localization, and response-header tests. |
| `FR-183`-`FR-193` | T004, T095-T097, T116, T125 | Direct CORS config, deployment checks, and no credentialed CORS. |
| `FR-194`-`FR-220` | T015, T018, T030, T078, T094, T100, T124, T130 | Secret-safety implementation, scans, logs, and no auth cleanup jobs/commands. |
| `FR-221` | T102, T112, T116, T131 | External React and CSP evidence; blocked if unavailable. |
| `FR-224`-`FR-225` | T062, T068, T070, T114, T128 | Successful refresh predecessor Access Token revocation and one-active-token-pair verification. |
| `API-001`-`API-007` | T009, T041, T042, T048-T050, T054, T056, T064, T113, T123 | OpenAPI, request/resource, and endpoint contract tests. |
| `ERR-001`-`ERR-017` | T010, T011, T098, T123, T124 | Stable error inventory and obsolete error absence. |
| `AUTH-001`-`AUTH-004` | T001, T005, T051, T052, T055, T072, T096 | Backend auth boundary and protected operation middleware checks. |
| `DATA-001`-`DATA-007` | T020-T024, T028, T030, T032-T040, T062, T070, T128 | Token/reset persistence, indexes, relationships, refresh atomicity, and concurrency tests. |
| `VER-001`-`VER-016` | T006, T016-T018, T028-T030, T039-T040, T054-T101, T120 | Backend automated verification. |
| `VER-017`-`VER-029` | T102-T112 | External React automated verification and CSP evidence. |
| `VER-030`-`VER-040` | T003, T007, T101, T113, T116, T123, T126, T131 | Cross-artifact and completion audit. |
| `VER-041`, `VER-042` | T038, T061, T067, T120 | Refresh throttle key and trusted requester-IP verification. |
| `VER-044`-`VER-047` | T031, T039, T092, T120 | Secure generator and recovery-token verification. |
| `VER-050` | T068, T128 | Post-refresh replacement Access Token succeeds, predecessor Access Tokens fail, and only the replacement token pair remains active. |

## Current external blocker

Tasks T102-T112 require the owning React Admin Frontend repository and its
deployment/CSP evidence. This backend repository cannot complete or verify
those tasks by itself, so full Feature 001 completion must not be claimed until
that external evidence is supplied.
