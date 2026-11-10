# ARK Labor Matrix + Billing Minimums — Owner Design Contract (staging only)

**Status:** Proposed design; not approved implementation or production release.
**Starting point:** staging Core `8f73efefae46b36ad563447c0c68a37351518192`. Separate documentation branch; do not merge or deploy automatically.
**Scope:** Settings / pricing behavior only. No unrelated RO redesign. This spec is a design target, not a claim that current Core does this.

## 1. First screen: labor-rate matrix

Use a compact, readable **work type × billing class** grid as the primary Labor Settings screen. Example labels supplied by owner: Mechanical, Advanced Mechanical, Troubleshooting, Programming × Retail, Warranty, Fleet, RepairPal. Other active billing classes must remain supportable without forcing everything into the default viewport. Do not conflate Labor Categories with Operation Classes until code/data mapping has been proven. Avoid a second competing hourly-rate editor. No labor-hour bracket multipliers or vehicle age/rust/make features in this iteration.

| Work type \ Billing class | Retail | Warranty | Fleet | RepairPal |
|---|---:|---:|---:|---:|
| Mechanical | $165 | $150 | $155 | $150 |
| Advanced Mechanical | $185 | $165 | $175 | $165 |
| Troubleshooting | $165 | $150 | $155 | $150 |
| Programming | $185 | $165 | $175 | $165 |

**All amounts are illustrative, not a shop's production pricing.** Keep explicit Standard / Custom / Intentional Zero semantics in underlying policy model, surfaced as understandable editing controls. Billing-class names are user-facing; internal posture aliases and operations mappings require verification.

## 2. Billing-class minimums, independent of rate matrix

For each billing class, configure **None**, **Per labor line**, or **Per repair order**, plus a minimum hours value. Example: Retail = 1.00 hour PER RO. Warranty = no minimum. Other defaults are NOT decided.

Per RO is **per billing class within an RO**, NOT across all billing classes. Retail labor must not use Warranty/Fleet/RepairPal hours to satisfy the Retail minimum, even if concerns are on one RO. Apply minimum to eligible billed labor only, with explicitly documented treatment of excluded/zero-rated/discounted lines, mixed billing, category minimums, rounding, and manual overrides.

For per-line mode, each eligible labor line receives its own minimum but the original estimated/technician hours must remain distinct from charged hours. Define precedence relative to category minimums; don't stack duplicate minima.

## 3. Visual acceptance examples (all examples $165/hr mechanical retail)

**A. One Retail concern**

| Concern | Billing class | Original labor | Minimum contribution | Priced labor |
|---|---|---:|---:|---:|
| #1 Bulb repair | Retail | 0.40 hr | +0.60 hr at Retail minimum rate | $66 + $99 = $165 |
| RO total | | 0.40 hr | 0.60 hr | $165 |

Show on Concern #1: “Retail RO minimum · 1.00 hr required · 0.40 hr eligible · +0.60 hr / $99 currently applied.” Show original 0.40 hr unmodified.

**B. Second Retail concern added**

| Concern | Billing class | Original labor | Minimum contribution | Priced labor |
|---|---|---:|---:|---:|
| #1 Bulb repair | Retail | 0.40 hr | — | $66 |
| #2 Air filter | Retail | 0.60 hr | — | $99 |
| RO total | | 1.00 hr | 0.00 hr | $165 |

Minimum adjustment disappears; existing original labor remains 0.40 and 0.60. Advisor gets a non-blocking notice: “Retail minimum recalculated: +0.60 hr → +0.00 hr after Concern #2.” No alert on every keystroke.

**C. Mixed Retail + Warranty (non-negotiable)**

| Concern | Billing class | Original labor | Minimum contribution | Priced labor |
|---|---|---:|---:|---:|
| #1 Bulb repair | Retail | 0.40 hr | +0.60 hr Retail | $165 retail total |
| #2 Warranty repair | Warranty | 2.00 hr | 0.00 hr | Warranty policy rate × 2.00 hr |
| Retail eligible labor | | **0.40 hr** | **+0.60 hr** | Retail minimum remains active |

Warranty's 2.00 hours DO NOT count toward Retail's 1.00-hour minimum. Likewise don't mix Fleet, RepairPal, Wholesale, Internal, and Comeback minima.

**D. Per-line mode**
Retail minimum 1.00 hr per eligible line; two retail lines of 0.40 and 0.60 charge their respective 1.00-hr minima. Explicitly show both adjustments; original labor remains 0.40 and 0.60. Do NOT imply this is the recommended shop default.

## 4. Advisor-facing presentation

```text
Concern #1 — Bulb repair                    Retail
Mechanical labor                         0.40 hr  $66.00
  Retail RO minimum — currently applied
  Eligible Retail hours on this RO: 0.40 of 1.00
  Additional minimum charge:             0.60 hr  $99.00
[Why this was applied] (optional detail)

Concern #2 — Warranty repair                Warranty
Warranty labor                           2.00 hr  (warranty rate)
  Does not count toward Retail minimum

RO labor summary
Retail actual labor:                       0.40 hr
Retail minimum adjustment:                +0.60 hr
Warranty labor (separate):                 2.00 hr
```

Minimum charge may be presented on the concern but is logically **RO-scoped per billing class**; do not bake it permanently into the underlying labor line or technician productivity hours. Define one deterministic, stable attribution/presentation rule so the charge doesn't jump unpredictably between concerns and all financial totals match. UI can use an info label/chip for currently applied minimum, non-blocking change notification on addition/removal/reclassification, and authorization warning when an already-approved price would increase.

## 5. Forward-only activation and immutable history

Owner expressly requires **no effect on closed/archived ROs or currently open ROs**. Policy versions/activation must be based on the **RO creation / pricing-profile assignment**, not merely today's date or whether a line is newly added. Existing open ROs must not pick up new minimum behavior when reopened, modified, or repriced; even new lines on a pre-activation RO must stay on the prior pricing profile unless an explicit owner-approved migration/opt-in path is later designed.

The current production/staging pricing model may require a versioned per-RO price-profile identifier/snapshot and migration safeguards. Cursor MUST investigate how existing policy versioning and labor rate snapshots behave before changing the engine. Avoid backfilling a new mode as if old ROs opted in. Safe default for all pre-activation ROs is legacy pricing. Don't mutate approved estimates/invoices; increases need existing authorization workflow. Past financial docs, reporting, technician hours and financial snapshots stay stable.

## 6. Expected pricing sequence and questions to prove

1. Select billing class/posture and work type, resolve the rate.
2. Preserve raw estimated/book hours vs chargeable hours.
3. Apply existing category minimum and rounding according to a documented precedence.
4. Evaluate billing-class minimum per labor line OR per RO (within that billing class only) against eligible hours.
5. Price the minimum shortfall at a clearly specified, configured rate, especially when one class spans mixed work-type rates. Do not silently select the highest rate.
6. Surface each minimum's provenance and effect on concern, RO total, financial rail and customer authorization.
7. Recompute draft previews as concerns change; preserve locked/approved amount and provide authorization if increased.

**Decision still needed:** when mixed retail rates occur (Mechanical and Advanced Mechanical) and a retail RO-minimum shortfall remains, whether the shortfall should use standard Retail Mechanical rate, another configured base rate, or an explicit selected rate. Do not pretend this is settled.

## 7. Implementation and visual-review gates

- First deliver traceable mapping of current Labor Category / Operation Class / Billing Posture / policy resolver and a minimal implementation plan. Don't remove existing models or tables until dependency proof.
- Implement compact matrix and billing-class minimum controls in Settings with direct, obvious edit/save behavior and no giant nested policy panels.
- Add UI/feature tests for examples A–D; mixed classes; add/remove/switch concern; zero/excluded lines; category min + rounding; mixed work-type rates; manual override; concurrent edits; approved estimate; closed RO; pre-activation open RO with a *new* line added after activation; warranty/RepairPal; tenant isolation.
- Test old/new RO boundary, including reopen/clone behavior; new clones must get the new profile only if created after activation.
- Staging only, synthetic demo tenant; no production pin, merge, migrations, data changes or deployment. Show actual browser screenshots and owner-interactive staging demonstration before approval.
- Keep documentation and visual examples committed for review. Don't broaden into future age/make/rust/matrix brackets yet.
