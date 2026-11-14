# Labor Matrix & Billing Classes — Owner UI Audit

**Date:** 2026-11-14  
**Directive:** AUDIT ONLY — no implementation, merge, cherry-pick, rebase, or deploy.  
**Surface:** Alpha shop `https://alpha.staging.arksms.com` · authenticated as synthetic `owner@alpha-staging.example.com` (Avery Alpha).  
**Artifacts:** [screenshots/](./screenshots/)

---

## 1. Exact SHAs and deployment status

| Layer | Full SHA | Where |
| --- | --- | --- |
| **Core `main`** | `09aa7ba3eb1fa93abac51ee50b838a8d03a41b6f` | Includes Print hotfix #205; Labor Matrix lineage is an ancestor (`2cfac68a` / #196, `b29bf318` / #195, `c91550b2` / #187) |
| **SaaS `main`** | `b972410d47dcda7ebb1981c80faff0234169af12` | Pins Core `09aa7ba3…` |
| **Alpha** (`157.230.172.143` / `alpha.staging.arksms.com`) | SaaS `b972410d…` · Core `09aa7ba3…` | `/up` **200** |
| **Production** (`ark-saas-prod-1` / `lugsnplugs.arksms.com`) | SaaS `b972410d…` · Core `09aa7ba3…` | `/up` **200** — **same tip as Alpha** |

### Labor Matrix feature merges (already on `main`)

| PR | Title | Merged |
| --- | --- | --- |
| Core [#187](https://github.com/EdwardSoaresJr/ark-core/pull/187) | Integrate Labor redesign + Parts Matrix settings | 2026-11-12 |
| Core [#195](https://github.com/EdwardSoaresJr/ark-core/pull/195) | Labor Matrix hybrid inline UX (R1+D2) | 2026-11-13 |
| Core [#196](https://github.com/EdwardSoaresJr/ark-core/pull/196) | Document presentation + Billing Class / Work Type lifecycle | 2026-11-13 |
| SaaS [#226](https://github.com/EdwardSoaresJr/ark-stancl/pull/226) | Alpha pin R1+D2 | 2026-11-13 |
| SaaS [#228](https://github.com/EdwardSoaresJr/ark-stancl/pull/228) | Pin presentation + lifecycle | 2026-11-13 |

Prior GO/NO-GO (`docs/review-artifacts/labor-matrix-final-2026-11-13`) froze Core `2cfac68a…` / SaaS `239d12ff…` as CONDITIONAL GO. That lineage is now an ancestor of the live production pin above. Read-only prod tenant check: `labor_rounding` present (`rounding_count=12`).

### What remains only on development / docs branches

| Branch | Ahead of `main` | Content |
| --- | --- | --- |
| `docs/labor-matrix-hybrid-ux-reference` | 1 | Conceptual layout reference docs only |
| `review-artifacts-labor-clarity` | 3 | Older review captures / pin plan docs |
| `settings/consolidated-labor-settings` | 1 | Staging pin note (`STAGING_SETTINGS_LABOR_PIN.md`) |

**No incomplete Labor Matrix feature code was found left behind** relative to the merged hybrid + lifecycle work. Remaining branch tips are documentation / historical review artifacts, not unmerged product behavior.

### Mapping: “Rates / Rules / Class Settings / Manage Billing Classes”

| Owner label | Current shipped UI | Status |
| --- | --- | --- |
| **Rates** | Financial Rules → Labor → work-type rail + Hourly Rate column | Present |
| **Rules** | Intentionally removed (`a17e9843` — “Remove Labor Matrix Rules link and popup”). Replaced by inline **Min Hrs** + **Round To** on each matrix row | Absorbed into hybrid matrix (not a missing tab) |
| **Class Settings** | Gear on each labor-linked row → modal (identity, pricing, defaults, customer documents) | Present |
| **Manage Billing Classes** | “Customer-only & archived” → manage modal (+ Add Billing Class) | Present |

Shop Settings connection: Labor lives under **Financial Rules** with Tax Rules · Shop Fees · Deposits · Parts Matrix. Deep links from Workflow / Excellence targets open Labor or Manage Billing Classes. There is **no** separate Financial → Labor Policies or Billing Classes tab.

---

## 2. Completed functionality

| Area | Classification | Evidence |
| --- | --- | --- |
| Labor rate matrix load (work types × billing classes) | **COMPLETE** | [01](./screenshots/01-desktop-1440-labor-matrix-rates.png), Alpha `GET /app/settings/shop/labor-matrix` 200 |
| Edit + autosave hourly rates | **COMPLETE** | [02](./screenshots/02-desktop-1440-rates-editing-focus.png), [04](./screenshots/04-desktop-1440-rates-save-confirmation.png); Alpha PATCH Internal `$1.00` → `$0.00` restored, reload survived |
| Round To (WT × BC) | **COMPLETE** | [05](./screenshots/05-desktop-1440-rounding-rule-edit.png); prod/Alpha `labor_rounding` present |
| Min Hrs shared per billing class | **COMPLETE** | Visible on matrix rows (Retail Per RO 1.00) |
| Class Settings open / commercial fields | **COMPLETE** | [06](./screenshots/06-desktop-1440-class-settings-open.png), [07](./screenshots/07-desktop-1440-class-settings-lower.png) |
| Manage Billing Classes list | **COMPLETE** | [09](./screenshots/09-desktop-1440-manage-billing-classes.png) — customer-only + labor-only columns |
| Create Billing Class dialog | **COMPLETE** | [10](./screenshots/10-desktop-1440-create-billing-class.png) |
| Empty-name validation on create | **COMPLETE** | [11b](./screenshots/11b-desktop-1440-create-billing-class-empty-name.png) — “The name field is required.” |
| Add Labor Column dialog | **COMPLETE** | [12](./screenshots/12-desktop-1440-add-labor-column-dialog.png) |
| Work-type switch | **COMPLETE** | [14](./screenshots/14-desktop-1440-rates-other-work-type.png) |
| Authorization (settings mutations) | **COMPLETE** | Owner/admin OK; Advisor PATCH **403**; Technician GET/PATCH **403**; unauthenticated **419** |
| Server rejects negative rates | **COMPLETE** | PATCH `rate: -5` → **422** `The rate must be at least 0.` |
| New-RO-only / snapshot posture (architecture) | **COMPLETE** | UI copy + engine tests; open RO money not rewritten by settings edits (PricingEngineSafety / LaborAuthority coverage) |
| Automated tests (local Core `main`) | **COMPLETE** | Pest filter `LaborMatrix\|LaborWorkTypeMatrix\|BillingClassSettings\|StandardLaborRate\|LaborAuthority\|PricingEngineSafety\|BillingClassBlank` → **72 passed** (483 assertions) |

---

## 3. Missing or incomplete functionality

| Item | Classification | Notes |
| --- | --- | --- |
| Separate Labor “Rules” tab / popup | **Not incomplete** | Removed by design in hybrid UX; Min Hrs + Round To are the replacement controls |
| Unmerged Labor Matrix product code | **None found** | Leftover branches are docs/review only |

---

## 4. Verified defects

| ID | Classification | Reproduction | Result |
| --- | --- | --- | --- |
| — | *(none classified as DEFECT)* | — | Functional paths checked on Alpha worked as designed |

**Near-miss (not elevated to DEFECT):** entering a negative hourly rate in the UI is clamped with `Math.max(0, …)` and saved as `$0.00` / zero mode with “All changes saved,” rather than showing a field error. The API still rejects negatives if they are sent. See UX CONCERN below.

---

## 5. UI/UX concerns (screenshot-backed)

| ID | Classification | Concern | Screenshots |
| --- | --- | --- | --- |
| UX-1 | **UX CONCERN** | At **390px / 320px**, Round To and Class gear columns are truncated; only Billing Class + Hourly Rate (+ partial Min Hrs) remain obvious without horizontal scroll | [19](./screenshots/19-mobile-390-labor-matrix-rates.png), [23](./screenshots/23-mobile-320-rates-table.png) |
| UX-2 | **UX CONCERN** | At **320px**, banners + section chrome consume most of the first viewport before the matrix | [22](./screenshots/22-mobile-320-labor-matrix.png) |
| UX-3 | **UX CONCERN** | At **768px**, layout stacks (section dropdown); rates table starts below the fold | [15](./screenshots/15-tablet-768-labor-matrix.png) |
| UX-4 | **UX CONCERN** | Negative rate input silently becomes `$0.00` and autosaves — no inline “must be ≥ 0” message in the matrix | [03](./screenshots/03-desktop-1440-rates-validation-negative.png) |

These are presentation / feedback improvements. They do not block the approved Labor Matrix architecture.

---

## 6. Known tests and results

| Suite | Result |
| --- | --- |
| Local Pest (filter above) on Core `09aa7ba3` worktree | **72 passed** |
| Alpha live: rate PATCH + GET reload (Internal synthetic `$1` → `$0`) | **PASS** |
| Alpha live: negative rate API validation | **422 PASS** |
| Alpha live: Advisor / Technician authorization | **403 PASS** |
| Production LugsNPlugs settings | **Not mutated** (read-only: `labor_rounding` present) |

---

## 7. Screenshot index (direct links)

### Desktop 1440

- [01-desktop-1440-labor-matrix-rates.png](./screenshots/01-desktop-1440-labor-matrix-rates.png)
- [02-desktop-1440-rates-editing-focus.png](./screenshots/02-desktop-1440-rates-editing-focus.png)
- [03-desktop-1440-rates-validation-negative.png](./screenshots/03-desktop-1440-rates-validation-negative.png)
- [04-desktop-1440-rates-save-confirmation.png](./screenshots/04-desktop-1440-rates-save-confirmation.png)
- [05-desktop-1440-rounding-rule-edit.png](./screenshots/05-desktop-1440-rounding-rule-edit.png)
- [06-desktop-1440-class-settings-open.png](./screenshots/06-desktop-1440-class-settings-open.png)
- [07-desktop-1440-class-settings-lower.png](./screenshots/07-desktop-1440-class-settings-lower.png)
- [08-desktop-1440-class-settings-validation.png](./screenshots/08-desktop-1440-class-settings-validation.png)
- [09-desktop-1440-manage-billing-classes.png](./screenshots/09-desktop-1440-manage-billing-classes.png)
- [10-desktop-1440-create-billing-class.png](./screenshots/10-desktop-1440-create-billing-class.png)
- [11-desktop-1440-create-billing-class-validation.png](./screenshots/11-desktop-1440-create-billing-class-validation.png)
- [11b-desktop-1440-create-billing-class-empty-name.png](./screenshots/11b-desktop-1440-create-billing-class-empty-name.png)
- [12-desktop-1440-add-labor-column-dialog.png](./screenshots/12-desktop-1440-add-labor-column-dialog.png)
- [13-desktop-1440-shop-settings-financial-context.png](./screenshots/13-desktop-1440-shop-settings-financial-context.png)
- [14-desktop-1440-rates-other-work-type.png](./screenshots/14-desktop-1440-rates-other-work-type.png)

### Tablet 768

- [15-tablet-768-labor-matrix.png](./screenshots/15-tablet-768-labor-matrix.png)
- [16-tablet-768-class-settings.png](./screenshots/16-tablet-768-class-settings.png)
- [17-tablet-768-manage-billing-classes.png](./screenshots/17-tablet-768-manage-billing-classes.png)

### Mobile 390

- [18-mobile-390-labor-matrix-top.png](./screenshots/18-mobile-390-labor-matrix-top.png)
- [19-mobile-390-labor-matrix-rates.png](./screenshots/19-mobile-390-labor-matrix-rates.png)
- [20-mobile-390-manage-billing-classes.png](./screenshots/20-mobile-390-manage-billing-classes.png)
- [21-mobile-390-class-settings.png](./screenshots/21-mobile-390-class-settings.png)

### Mobile 320

- [22-mobile-320-labor-matrix.png](./screenshots/22-mobile-320-labor-matrix.png)
- [23-mobile-320-rates-table.png](./screenshots/23-mobile-320-rates-table.png)
- [24-mobile-320-manage-billing-classes.png](./screenshots/24-mobile-320-manage-billing-classes.png)

---

## 8. Owner decisions genuinely needed

| ID | Classification | Decision |
| --- | --- | --- |
| OD-1 | **OWNER DECISION** | Prioritize any mobile/tablet Labor Matrix layout polish (UX-1…UX-3), or accept current responsive table as good enough for settings |
| OD-2 | **OWNER DECISION** | Whether negative rate inputs should show an inline error instead of silently clamping to `$0.00` (UX-4) |
| OD-3 | **OWNER DECISION** | Authorize further Labor Matrix / Billing Classes **development** at all — audit finds the approved hybrid + lifecycle scope functionally complete on current main / Alpha / production tip |

No pricing-engine redesign, RO workspace, print, or onboarding reopen is required by this audit.

---

## Verdict

**Labor Matrix and Billing Classes are functionally complete** on Core/SaaS `main` and on the live Alpha **and** production pins (`09aa7ba3` / `b972410d`).

Outstanding items are **optional UI improvements** (mobile truncation, tablet fold, negative-rate feedback) — not missing product scope and not verified functional defects.

**Release:** documentation-only review PR (no product cutover).
