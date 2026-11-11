# PR #186 acceptance pass

**Staging:** `alpha.staging.arksms.com`  
**Core pin:** `a3239c9515c11657b147eaf51eb9946bc4c97467`  
**SaaS:** `eb859c21` (`dev-pin`)

## 1. Left-tab parity

Reference: Shop Settings left tabs (the vertical `list-group` beside Labor / Labor Policies), not the Labor categories table.

Corrections:
- `col-lg-3 col-xxl-2` / `col-lg-9 col-xxl-10`
- `card sticky-lg-top` + `list-group-item list-group-item-action` (same classes as settings nav)
- Default badge `bg-secondary-lt` (Labor Policies style), not competing blue

At 1280px: active padding `16px 20px`, font 14px/400, height ~53–54px.

Screenshots: `acceptance-pass/08`, `09`, `10`.

## 2. Autosave stress (browser + server)

| Scenario | UI status | Server |
| --- | --- | --- |
| Rapid tier edits then switch during 700ms debounce | Saved after flush | Filters edit persisted, then restored |
| Overlapping immediate saves | Saved | Last OEM value won; siblings intact |
| Invalid empty cost row | **Save needed** | Unchanged |
| Restore + reload | Saved | aft 180, filters 130, oem 90, fluids 150, warranty 0 |

Sibling matrices (AFT / Fluids / Warranty) never overwritten during the Filters/OEM stress path. Billing Class matrix associations unchanged.

## 3. False “Saved” fix

Cause: followed HTML redirect after validation looked like `response.ok`.

Fix: `Accept: application/json` + `redirect: 'manual'`; treat Chromium `opaqueredirect` as success; `422` → Save needed.

Confirmed: invalid row → Save needed (not Saved).

## 4. Mobile table

| Width | Page overflow | Table horizontal scroll | Margin / Remove / Save |
| --- | --- | --- | --- |
| 320 | no | yes (616 > ~260) | reachable after scroll |
| 375 | no | yes | reachable |
| 430 | no | yes | reachable |

Screenshots: `acceptance-pass/11`–`14`.

## 5. Financial boundary

No pricing engine, snapshot, Billing Class, or matrix-data rule changes. Staging matrix first-tier markups restored to prior shop values after the stress proof.
