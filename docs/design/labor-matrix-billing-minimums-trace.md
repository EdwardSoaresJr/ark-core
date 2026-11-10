# Labor Matrix + Billing Minimums — Dependency Trace & Versioning Strategy

**Source of truth:** `docs/design/labor-matrix-and-billing-minimums-staging.md` (owner contract @ `d563573`).  
**Starting Core tip:** `8f73efe` (consolidated Labor Settings staging).  
**Status:** Trace complete; vertical slice implements this plan. Staging only.

## 1. Current pricing pipeline (authoritative today)

```
Concern billing_posture
  → LaborAuthority::resolveForLine
      → Operation::forLine(category code) → OperationClass
      → LaborPolicyResolver(posture, operation_class)
           LaborRateType::Standard → ShopSettings::defaultLaborRateCents()
           Hourly/Contract/Custom → policy.hourly_rate_cents
           Zero → 0
      → category minimum_hours + rounding (billable hours)
      → LaborAdjustment factor (hours, not $/hr)
  → RepairOrderLinePricing snapshots labor_rate_cents + labor_policy_id/version
```

**Important separations already present:**

| Concern | Owner today | Not interchangeable with |
|---------|-------------|--------------------------|
| Labor Categories (`ShopSettings.labor_categories`) | Display name, min hrs, rounding, modifiers, default | Operation Classes |
| Operations (`operations.code`) | Maps category → Operation Class | Billing postures |
| Labor Policies | Rate by posture × operation_class | Category JSON rates |
| Line snapshots | Frozen $/hr + policy version | Live Settings |

Category `rate_cents` are **not** the estimate authority for billed labor (policy resolver is). Courtesy without explicit posture still uses legacy category rate.

## 2. Work-type × billing-class matrix vs today's models

Owner matrix rows (Mechanical, Advanced Mechanical, Troubleshooting, Programming) are **work types**. Today rates are keyed by **Operation Class**, not Labor Category.

| Desired row | Labor category code | Operation class today | Independent rate? |
|-------------|---------------------|----------------------|-------------------|
| Mechanical | `mechanical` | `general_repair` | Yes |
| Advanced Mechanical | `advanced_mechanical` | `advanced_mechanical` | Yes |
| Troubleshooting | `diagnostic` | `diagnostics` | Shared with Programming |
| Programming | `programming` | `diagnostics` | **Same class as Troubleshooting** |

**Proven constraint:** Editing Programming × Retail and Troubleshooting × Retail currently mutate the **same** `labor_policies` cell (`diagnostics` × `customer_pay`). Vertical slice surfaces both rows but marks them as linked until an Operation Class split is approved. Do not invent a second rate store.

User-facing column “Retail” maps to `ConcernBillingPosture::CustomerPay` (`customer_pay`). Warranty → `warranty_other`, Fleet → `fleet`, RepairPal → `repairpal`.

## 3. Why open ROs would silently change without a profile stamp

Today:

- Policy rates use `LaborPolicyResolver` as-of “now” for **new** lines; existing lines keep `labor_rate_cents` snapshots unless category/reprice paths run.
- **There is no RO-level pricing profile.** Any new billing-class minimum engine that reads live `ShopSettings` would apply to:
  - newly added lines on old open ROs
  - recalculation paths that rebuild totals

Line-level policy snapshots alone **do not** protect against new minimum *hours* behavior.

## 4. Safest migration / versioning strategy (chosen)

**Stamp an immutable labor pricing profile on the RO at creation. Never backfill.**

1. `shop_settings.labor_billing_minimums` (JSON) — current shop config (modes/hours/shortfall rate).
2. `shop_settings.labor_pricing_profile_version` (int, default `0`) — bumps when minimums are first activated / materially saved.
3. `repair_orders.labor_pricing_profile` (JSON, **nullable**):
   - `null` → **legacy profile** (all existing ROs after migration). No billing-class minimums. Forever.
   - non-null → snapshot copied at create time from current shop config + version.

```json
{
  "version": 1,
  "billing_minimums": {
    "customer_pay": {
      "mode": "per_ro",
      "hours": "1.00",
      "shortfall_rate_cents": 16500,
      "shortfall_rate_source": "standard_mechanical"
    },
    "warranty_other": { "mode": "none", "hours": "0.00", "shortfall_rate_cents": null, "shortfall_rate_source": "standard_mechanical" }
  }
}
```

**Rules:**

- New RO create paths stamp the profile once.
- Pre-activation / null-profile ROs: calculator no-ops even if shop later enables minimums.
- New labor lines on a null-profile RO stay on legacy rules.
- Clones / “move concern to new RO”: new RO created after activation gets the **current** profile; the source RO stays on its stamp.
- Approved amount increases still go through existing authorization — minimum applicator must not silently inflate authorized totals (draft/open estimable paths only in this slice; approved/locked paths skip auto-apply or require auth hook).

**Shortfall rate (owner lean, adopted for slice):**  
Configurable per billing class; default = shop standard Retail Mechanical rate (`CustomerPay` × `general_repair` / Mechanical category). Mixed Mechanical + Advanced Mechanical RO shortfall does **not** silently pick the higher rate.

## 5. Billing-class minimum calculation (slice)

Eligible hours for a posture = sum of labor lines on concerns with that posture, using **billed hours already on the line** (`labor_billed_hours` / quantity), excluding:

- Intentional zero / $0 policy rates (Internal/Comeback style)
- Non-labor lines
- System “billing minimum” fee lines themselves

**Per RO mode:** `shortfall = max(0, configured_hours − eligible_hours)` within that posture only. Warranty hours never satisfy Retail.

**Per line mode:** each eligible labor line shortfall = `max(0, configured − line_billed_hours)`; priced as fee lines or per-line fees attributed to that concern.

**Category minimum vs billing-class minimum:** category min/rounding already applied when the labor line was priced. Billing-class minimum compares against those billed hours; do not re-apply category min as a second floor on the same hours. Shortfall is a **Fee** line (not labor hours), so technician production hours stay on original labor lines.

**Attribution (stable):** attach the RO-scoped Retail shortfall Fee to the **first** Retail concern (lowest id) that currently has eligible labor; if none, omit. When shortfall changes, update/remove that Fee and flash a non-blocking notice.

## 6. Settings UI direction (replace clutter)

One Labor Settings panel:

1. Compact **work type × billing class** rate matrix (edit cells → `UpsertLaborPolicyAction` with explicit Standard/Custom/Zero).
2. **Billing-class minimums** table under it (mode + hours + shortfall rate source).
3. No second Standard-rate mega-form, no nested specialty editor competing with the matrix.

Standard rate remains: Mechanical × Retail cell set to Use Standard, driven by `ShopSettings.default_labor_rate_cents`.

## 7. Vertical slice contents / non-goals

**In slice:** matrix UI + minimums config + RO profile stamp + per-RO minimum Fee applicator + tests A–D + mixed Retail/Warranty + forward-only null profile + staging screenshots.

**Out of slice:** labor brackets, vehicle age/make/rust, Operation Class split for Programming, production deploy/merge, approved-estimate auto-inflate, giant competing Settings panels.

## 8. Open residual risk

Programming and Troubleshooting share `diagnostics` until catalog change — matrix must not pretend they have independent rates. Shortfall Fee presentation on the worksheet is intentional (not labor); advisor chip copy can deepen in a follow-on without changing the calculator.
