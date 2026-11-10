# Labor Rate Matrix — Final Implementation Plan

**Repo state:**
- Core branch `settings/labor-matrix-billing-minimums`
- Prior staging SaaS pin `436770ee` → Core `ee2753d` (interim grid)
- This iteration replaces the interim grid with the owner-approved vertical matrix

## Decisions

| Topic | Decision |
|---|---|
| UI | Vertical work-type list + inline rates + Rules modal; autosave; no Standard rate box; Mechanical×Retail labeled Default |
| Rate authority | Still `UpsertLaborPolicyAction` / `LaborPolicyResolver`; Mechanical×Retail writes `default_labor_rate_cents` + Standard sync |
| Profile | **v2** stamps rates matrix + minimums + default cents + work-type map + category rules; never backfill |
| Legacy null RO | New lines resolve policies **as-of `opened_at`**; Standard uses policy row `hourly_rate_cents` when present |
| Programming vs Troubleshooting | Split: new `programming` Operation Class; migrate `programming` Operation; copy policies from `diagnostics` |
| Add Work Type | Creates labor category + Operation Class + Operation + seeded policies |
| Add Billing Class | Shop-scoped `custom_*` key in `custom_labor_billing_classes` + `labor_policies`; concern picker selects the key; fee/parts follow `base_posture` |
| Minimum fees | Durable `system_line_kind = billing_minimum` (+ scope); description is display-only |
| Advisor copy | Persistent concern explanation partial; flash notice only for delta |

## Migrations (additive, forward-only)

1. `2026_11_10_180000_labor_matrix_profile_v2_and_programming_split.php`
2. Profile version bump is JSON-only on ROs (no historical rewrite)

## Explicit limitations deferred

- Labor brackets / vehicle age / rust / manufacturer formulas: architecture leaves room in profile JSON; not implemented.
- Custom billing class fee/parts matrices: follow configured `base_posture` until a later settings surface maps them.
- Historical Standard-rate reconstruction for null-profile ROs: as-of policy row cents when stored; residual risk documented for pre-v2 null ROs.

## Staging

Pin after commit; screenshots in `review-artifacts/labor-rate-matrix-final-2026-11-10/`; no production.
