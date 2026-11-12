# Same-pin staging screenshots

**Host:** `alpha.staging.arksms.com`  
**SaaS:** `eb859c21` (`dev-pin`)  
**Core staged for review:** `f405bee` (`integrate/labor-and-parts-settings` tip)  
**UI capture:** Labor + Parts screenshots taken on this lineage at `7d0c9d8` (subsequent commits are review docs/screenshots only; Financial Rules UI unchanged).

Both images are from this single Core lineage (not separate branches).

| File | URL | What it proves |
|---|---|---|
| `screenshots/01-labor-work-types-same-pin.png` | `…/settings/shop?section=financial&financial-tab=labor` | Approved Labor: Work Types left rail, Default Labor Rate, Rates/Rules/Class Settings. Financial tabs = Labor · Tax Rules · Shop Fees · Deposits · Parts Matrix. **No** Labor Policies or Billing Classes tabs. |
| `screenshots/02-parts-matrix-same-pin.png` | `…/settings/shop?section=financial&financial-tab=parts` | Approved Parts: same Financial tab strip, vertical left matrix selection, single editor. |

## Explicit absences (Labor)

- No Financial → Labor Policies tab
- No Financial → Billing Classes tab
- No old Labor categories bulk table as the primary Labor UI
