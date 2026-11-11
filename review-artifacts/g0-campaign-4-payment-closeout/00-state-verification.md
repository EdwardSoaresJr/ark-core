# Campaign 4 — state verification (pre-change)

Captured 2026-11-10 (staging only). Production untouched.

## Pins

| Field | Value |
| --- | --- |
| Host | `157.230.172.143` |
| Tenant | `alpha` (`alpha.staging.arksms.com`) |
| SaaS tip | `664d8485a492fd6d191fb87d31469ec1fdf5d8b1` |
| Core tip (vendor/ark/core) | `e2aa732dc4ab5d4e274f549581481007d866889c` |
| Local feature branch | `settings/labor-matrix-billing-minimums` |
| C3 evidence commit | `1b34fe2` |

## Worktrees / uncommitted (at verification)

- Feature worktree: DEF-G0-05 prepared in `resources/js/ark-payment-capture.js` (preserved, then refined + tested).
- Unrelated untracked review-artifacts left untouched / not deployed.

## RO #20

| Field | Value |
| --- | --- |
| Status | `ready_pickup` |
| Payment status | `unpaid` |
| `closed_at` | `2026-11-10 23:40:30` (set while still unpaid/ready_pickup — residual anomaly; not force-cleared) |
| Invoice document id | `7` (type `invoice`, status `issued`, document_number `1`) |
| Invoice total | **25950¢ ($259.50)** |
| Estimate draft id | `6` total 36750¢ (includes unauthorized +$108 scope) |
| Ledger active payments | **0** |
| Balance due | **25950¢** unpaid |

Unauthorized +$108 remains excluded from the issued invoice (36750 − 25950 = 10800¢).

## Campaign 3 evidence publication

Committed and pushed on feature branch tip `1b34fe2`:

- https://github.com/EdwardSoaresJr/ark-core/tree/settings/labor-matrix-billing-minimums/review-artifacts/g0-campaign-3-ro-lifecycle
- https://github.com/EdwardSoaresJr/ark-core/blob/1b34fe2a32fd1bde435158989ec1221b7de5a90e/review-artifacts/g0-campaign-3-ro-lifecycle/README.md
- https://github.com/EdwardSoaresJr/ark-core/blob/1b34fe2a32fd1bde435158989ec1221b7de5a90e/review-artifacts/g0-campaign-3-ro-lifecycle/SCENARIO_LEDGER.md
- https://github.com/EdwardSoaresJr/ark-core/blob/1b34fe2a32fd1bde435158989ec1221b7de5a90e/review-artifacts/g0-campaign-3-ro-lifecycle/OWNER_REPORT.md
