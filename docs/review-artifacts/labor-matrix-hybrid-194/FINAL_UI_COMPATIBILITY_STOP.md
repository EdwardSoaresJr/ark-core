# STOP — Labor Matrix Final UI vs pricing authorities (#194)

**Status:** Implementation of the approved Tabler mockup is **blocked** until the owner chooses how to handle two authority mismatches. Shipping the mockup unchanged would make the UI promise pricing behavior Core does not yet enforce.

Alpha currently runs the prior hybrid candidate (Core `0423ab03` / Stancl `0079ab0a`). That interface still shows Rate Type + Default Labor Rate, and Round To is work-type–scoped (duplicated across class rows).

---

## Blocker 1 — Round To per Billing Class (hard)

### Today

| Control | Grain | Storage | Applied by |
| --- | --- | --- | --- |
| Hourly rate | Work Type × Billing Class | `labor_policies` + RO profile `rates[]` | `LaborPolicyResolver` / `LaborAuthority` |
| Min Hrs (matrix) | **Billing Class only** (all work types) | `labor_billing_minimums` | Billing-class minimum actions |
| Round To | **Work Type only** | `labor_categories[].rounding_rule` | `LaborAuthority::resolveForLine` via live category |

There is **no** Work Type × Billing Class rounding field. `labor_policies` stores rate only. RO profile schema 2 freezes **rates** per cell; category `rounding_rule` is copied onto the profile but **not** used when billing hours — authority reads **live** shop categories.

Showing an independent Round To on each Billing Class row today would either:

- rewrite one shared work-type value from every row (lies), or
- invent per-class behavior the engine ignores (lies).

Owner instruction forbids both.

### Narrow engine change required (for approval)

To make **Work Type × Billing Class → Rate, Minimum Policy, Rounding Policy** real:

1. **Settings store** — Add cell-grain rounding, e.g. `labor_rounding[work_type][billing_class]` on `shop_settings`, **or** extend each profile/`rates` cell and a parallel shop matrix. Keep `labor_categories[].rounding_rule` as fallback default for new cells / legacy.
2. **Write API** — Replace work-type-only `PATCH …/work-type-rounding` with cell PATCH (`work_type`, `billing_class`, `rounding_rule`).
3. **`LaborAuthority`** — Resolve rounding by work type × concern billing class (not category alone). Prefer stamped RO profile cell when present (parity with frozen rates).
4. **`LaborPricingProfile` schema 3** — Stamp `rounding_rule` on each `rates[work_type][billing_class]` cell. Existing schema ≤2 profiles: treat missing cell rounding as the stamped (or live) work-type category rule — **forward-only**, no reprice of stored `labor_billed_hours`.
5. **Backfill shop settings** — For each work type, copy current category `rounding_rule` onto every billing class cell so Retail/Warranty/… start identical; shops then diverge intentionally.
6. **Billing-class change on a line** — Explicit product rule: keep hours (today) vs re-round under the new class’s rule. Default recommendation: **keep hours** on posture change; apply new rounding only on hours edits / new lines (matches current “same category keeps hours” spirit).
7. **Documents** — Issued estimates already store `labor_billed_hours`; no recalc. Settings snapshot may gain cell rounding for audit.

### Migration impact

| Surface | Impact |
| --- | --- |
| DB migrations | Likely **JSON column / settings only** (no new table required if stored on `shop_settings`) |
| Open RO money | **Unchanged** if hours stay stamped on lines; new/edited lines after schema 3 use cell rounding from profile |
| Historical invoices/estimates | **Unchanged** |
| Shop settings | Additive backfill; visible Round To values start equal per work type until edited |

### Owner choices (Round To)

| Choice | Meaning |
| --- | --- |
| **R1 — Approve engine change** | Implement schema/settings/resolver/profile bump above, then ship mockup with real per-class Round To |
| **R2 — Defer per-class rounding** | Keep work-type Round To in a single Work Type Settings control (not per row); mockup Round To column stays out until R1 |
| **R3 — Reject** | Hold Final UI |

**Do not implement R1 without explicit owner approval of this section.**

---

## Blocker 2 — Remove Rate Type + Default Labor Rate

### Today

- Shop-wide `default_labor_rate_cents` + Mechanical × Retail as authority cell.
- Cells use `standard` / `custom` / `zero`; Standard cells follow shop default via `LaborPolicyResolver` + `SyncStandardCustomerPayLaborRatesAction`.
- Schema ≥2 RO profiles freeze effective cents per cell — **safe** from later shop edits.

### Compatibility options

| Option | UI | Engine | Existing ROs |
| --- | --- | --- | --- |
| **A — Presentation** | One Hourly Rate input; hide Rate Type + Default Labor Rate section; `$0.00` → zero; Mechanical×Retail still updates shop default + Standard sync underneath | Semantics unchanged if save mapping never auto-promotes custom→standard just because amounts match | Schema ≥2 **safe** |
| **B — Explicit cells** | Same UI | Migrate open Standard policies → explicit hourly/zero; retire cascade / default authority for matrix | Stamped ROs **safe**; **future** new ROs change (no inheritance) |
| **C — Blocked** | Demand “no inheritance” and “edit Retail cascades everyone” with no Standard type and no migration | Contradiction | — |

Owner also said: *do not infer hidden inheritance solely because a rate numerically matches another rate* — that requires tightening SyncStandard’s legacy “hourly matching previous standard” path under **A** or completing **B**.

### Owner choices (rates)

| Choice | Meaning |
| --- | --- |
| **D1 — Approve A** | UI simplification only; inheritance remains for `rate_type=standard` cells; document that matching amounts are not how inheritance is detected |
| **D2 — Approve B** | True independence; policy migration; shop default becomes mirror/legacy only |
| **D3 — Hold rate UI** until Round To decision lands |

---

## Related honesty issue — Minimum Hours

Mockup shows Minimum Hours **per Billing Class row** under the selected Work Type.

Core’s matrix Min Hrs today is **Billing Class across all work types** (`labor_billing_minimums`). Category `minimum_hours` is a separate work-type line floor inside `LaborAuthority`.

So a per-row Min Hrs control under Mechanical that silently writes class-wide policy is honest **only if** the UI labels it as shared across work types (current hybrid hint). Making Min Hrs truly Work Type × Billing Class is a **third** engine change (not requested as hard as Round To).

**Recommendation:** Keep class-wide Min Hrs with a single clear shared indicator; do not invent WT×BC minimums in this pass.

---

## Adjust Labor

No change proposed. Keep retiring the matrix control; preserve `allows_modifiers` enforcement and authorized line corrections. Age/rust/brand modifiers remain out of scope.

---

## What will not ship until you reply

- Tabler mockup with per-row Round To that only updates work-type rounding
- Removal of Default Labor Rate / Rate Type without an approved **D1** or **D2**
- Production deploy
- New modifier engine, tax, payments, or infra

## What is ready once you choose

| If you approve | Next step |
| --- | --- |
| **R1 + D1** | Engine cell rounding (schema 3) + UI-only rate presentation + Tabler mockup → Alpha |
| **R1 + D2** | Cell rounding + explicit-rate migration + Tabler mockup → Alpha |
| **R2 + D1** | Tabler mockup **without** per-class Round To column (Work Type Settings for rounding) → Alpha sooner |
| **R2 + D2** | Same UI path with explicit-rate migration |

---

## Exact ask for the owner

Reply with one Round To choice (**R1 / R2 / R3**) and one rate choice (**D1 / D2 / D3**).

Recommended production-faithful path matching the mockup’s promise: **R1 + D1** (or **R1 + D2** if you want inheritance gone entirely).
