# Consolidated Labor Settings — staging review

**Core SHA:** `8f73efefae46b36ad563447c0c68a37351518192` (`settings/consolidated-labor-settings`)  
**SaaS / ark-stancl SHA:** `4b74b6e3e949039ca9ee0d896d19f5606cd700b6` (`pin/core-8f73efe-labor-settings`)  
**Staging shop:** https://demo.staging.arksms.com/app/settings/shop?section=financial&financial-tab=labor  
**Captured:** 2026-11-10 on synthetic demo tenant only.

## Before / after

| File | What it shows |
|------|----------------|
| `before-labor-tab-desktop.png` | Pre-consolidation Labor tab (audit 2026-11-09) |
| `before-labor-policies-tab-desktop.png` | Separate Labor Policies tab (audit 2026-11-09) |
| `after-labor-settings-desktop-1440-full.png` | Consolidated Labor Settings — Standard + Categories (desktop) |
| `after-labor-advanced-desktop-1440.png` | Advanced billing rates with Use Standard / Custom / Zero (desktop) |
| `after-labor-specialty-desktop-1440.png` | Operation-class specialty matrix + rate preview (desktop) |
| `after-labor-standard-mobile-390.png` | Standard rate + categories intro (mobile 390) |
| `after-labor-settings-mobile-390.png` | Categories with Archive (mobile 390) |
| `after-labor-advanced-mobile-390.png` | Advanced billing rates (mobile 390) |
| `after-labor-settings-mobile-390-full.png` | Full-page mobile capture |

## Real pricing behavior (staging)

1. **New estimate lines** resolve through `LaborPolicyResolver`.  
   - `rate_type=standard` → `ShopSettings::defaultLaborRateCents()` (shop Standard).  
   - `custom`/`hourly`/`contract` → stored policy cents.  
   - `zero` → $0 intentional no charge.
2. Matching dollar amounts alone do **not** mean inheritance; mode/`rate_type` is explicit.
3. **Labor Categories** are not Operation Classes. Categories own min hours, rounding, modifiers, and the default category rate mirror of Standard. Operation-class × billing-type cells remain the policy matrix.
4. Migration `2026_11_10_010000_mark_standard_labor_policies` marks Customer Pay policies that already equaled the shop default as `standard`. Specialty / custom cells are unchanged.
5. Existing RO/estimate line snapshots are **not** rewritten on save.
6. **Add category** stays disabled until an Operations catalog mapping exists. Archive/Restore soft-hides categories from new lines.

## Unresolved / follow-on

- Category **Add** still requires Operations catalog work (not faked).
- Specialty matrix saves separately from the main Labor Settings form (same page).
- Warranty/RepairPal remain contract-style custom rates unless the owner sets Use Standard or Zero.
- Not a production release; Core `main` and stancl `main` were not merged for this preview.
