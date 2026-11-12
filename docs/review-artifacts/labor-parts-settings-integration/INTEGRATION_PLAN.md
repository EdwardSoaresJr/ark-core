# Safe integration plan — Labor redesign + Parts Matrix

## Goal

One Core SHA that presents **both** owner-approved Financial settings interfaces:

1. **Labor** — Work Types (left), Rates, Rules, Class Settings, Manage Billing Classes.
2. **Parts Matrix** — vertical left matrix selection + single editor; JSON `{ok,committed}` autosave.

Obsolete Financial → Labor Policies and standalone Billing Classes **navigation** stay removed. Underlying billing-class / labor-policy authorities remain.

## Non-goals

- Blind merge of `settings/labor-matrix-billing-minimums` over current pricing/auth.
- Merging PR #186 onto main while Labor is still old.
- Production deploy, Landlord shop-release, tenant migration.
- Another UI redesign of either surface.

## Steps executed

| Step | Action | Result |
|---|---|---|
| 0 | Freeze PR #186 (draft + owner freeze comment) | OPEN draft, unmerged |
| 1 | Branch `integrate/labor-and-parts-settings` from `main`+#185 | Base `f7cb79c` |
| 2 | Merge Labor tip `216739a` | Commit `c91550b` — redesign + pricing context |
| 3 | Merge Parts tip (PR #186 lineage) | Commit `293df7c` — Parts UI on redesign pin |
| 4 | Conflict policy | Prefer Labor pricing/auth engine; keep Parts JSON save contract; Financial tabs = Labor redesign set + Parts panel |
| 5 | Retarget obsolete `labor_categories` ShopSettingsTest expectations | Match `updateLabor` (standard rate only; ignore retired category form fields) |
| 6 | Regression suite (financial settings, forward-only, auth, snapshots) | Recorded in `TEST_RESULTS.md` |
| 7 | Push branch + open **integration** PR for review (not merge) | Owner decides |
| 8 | Staging-only Core pin of that SHA on alpha; screenshots Labor + Parts **same pin** | `SCREENSHOTS.md` |

## Conflict hotspots (already resolved)

- `LaborRateType` — Standard case + matrix resolution
- `LaborPolicyResolver` — redesign behavior + #185 compatibility
- `ShopFinancialSettingsController` — stamp/mergeArchived + Parts JSON response
- `shop.blade.php` — tab list without labor-policies / billing-classes; include Parts master-detail
- Tabler financial tests — assert absence of retired tabs and presence of both UIs

## Rollback

Staging: detach `vendor/ark/core` back to prior SHA (`d96154c` or last known good).  
Git: leave `main` untouched until owner merge of the integration PR.

## Acceptance for merge (owner)

- [ ] Same-pin Labor screenshot shows Work Types + no Labor Policies / Billing Classes tabs
- [ ] Same-pin Parts screenshot shows left matrix nav + single editor
- [ ] Documented Core SHA equals both screenshots
- [ ] Regression suite green
- [ ] Explicit owner merge of integration PR (not #186 alone)
