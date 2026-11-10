# G0 Campaign 2 — Remove blockers and prove the RO money path

Supervised **staging-only** campaign. **Not a production release gate pass.** Production remains prohibited.

## Environment

| Field | Value |
| --- | --- |
| Campaign start Core pin | `53e6d88c2706b0f9cc554b9541887c93743dc1c8` |
| Campaign start SaaS pin | `8dc38a139999d4ad116c3669d2c2bf76b8a61b3e` |
| Deployed Core tip | `e2aa732dc4ab5d4e274f549581481007d866889c` |
| Deployed SaaS tip | `664d8485a492fd6d191fb87d31469ec1fdf5d8b1` |
| Host | `157.230.172.143` (`APP_ENV=staging`) |
| Tenant | `alpha.staging.arksms.com` |
| Capture date | 2026-11-10 |
| Production | Untouched |

## Evidence index

| File | Demonstrates |
| --- | --- |
| [screenshots/g0c2-add-labor-modal.png](./screenshots/g0c2-add-labor-modal.png) | Add Labor modal opened after footer-clearance fix |
| [SCENARIO_LEDGER.md](./SCENARIO_LEDGER.md) | Step ledger + defect dispositions |

## Privacy

Synthetic records only (`GZero CampaignOne`, `5550199`, `2019 Toyota Corolla`). No payment tokens or secrets committed.

## Campaign outcome (summary)

**G0 status: BLOCKED** for production. Blockers G0-01 and G0-03 are fixed and staged. Square sandbox readiness is restored on alpha (keyed). The full hostile RO money path (partial auth → invoice → sandbox payment → immutable close) is **not complete** in this campaign stop.
