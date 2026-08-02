# Dashboard Analytics Index Decision

Representative MySQL `EXPLAIN` coverage is executable in `DashboardQueryPlanTest` and is rerun by the Feature 007 quality gate.

| Query family | Existing candidate index | Representative plan decision | Final decision |
|---|---|---|---|
| Non-cancelled financial aggregates by `created_at` | `orders_created_id_idx`, `orders_status_created_id_idx` | The one conditional aggregate intentionally reads the complete financial population once; a new index cannot remove that all-time scan. | No new index |
| Completed collected-sales aggregates by `completed_at` | `orders_status_created_id_idx` only partially matches | Collected windows are conditions inside the same bounded financial SELECT, so a separate index would not be selected for that combined aggregate. | No new index |
| Order count by `created_at` and optional status | `orders_created_id_idx`, `orders_status_created_id_idx` | Existing left prefixes cover both unfiltered range count and exact-status range count. | No new index |
| Six-month performance by `created_at` | `orders_created_id_idx`, `orders_status_created_id_idx` | Existing created-at index bounds the six-month range; grouping still requires calendar-month projection. | No new index |

No duplicate or speculative Feature 007 index is introduced.
