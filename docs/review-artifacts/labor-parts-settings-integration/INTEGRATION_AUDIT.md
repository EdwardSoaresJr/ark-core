# Labor + Parts settings integration audit

**Status:** Owner review — do not merge PR #186; do not deploy production; no tenant migration.  
**Integration branch:** `integrate/labor-and-parts-settings`  
**Base:** Core `main` @ `f7cb79c` (includes authorization #185)  
**Labor redesign tip integrated:** `216739a` (`settings/labor-matrix-billing-minimums`)  
**Parts UI tip integrated:** `d96154c` / PR #186 (`ui/parts-matrix-labor-alignment`)

## Why staging showed the old Labor UI

| Pin | Labor UI | Parts UI |
|---|---|---|
| Core main `f7cb79c` | Old categories table + Labor Policies + Billing Classes tabs | Flat Parts Matrix editor (pre-#186) |
| PR #186 `d96154c` | Same old Labor (branched from main) | Approved Parts master-detail |
| Labor redesign `53e6d88`…`216739a` | Approved Work Types / Rates / Rules / Class Settings / Manage Billing Classes; obsolete tabs removed | Pre-#186 Parts (or absent redesign) |

PR #186’s Labor “reference” screenshots were taken against main’s obsolete Labor tabs, not the approved redesign. That is the branch integration gap—not a Parts visual defect.

## Why the Labor redesign never landed on main

1. Feature lived only on `settings/labor-matrix-billing-minimums` (staging Core pins for owner review).
2. **No GitHub PR** was opened from that branch onto `main` (confirmed: `gh pr list --head settings/labor-matrix-billing-minimums` → empty).
3. Subsequent work (#185 authorization, #186 Parts Matrix UI) branched from `main`, which still had the pre-redesign Financial Rules tabs.
4. Result: two “correct in isolation” branches, zero coherent Core pin with both approved settings surfaces.

## Approved feature retained (Labor redesign)

Complete surface on Labor tip `216739a` (and now on the integration branch):

- Financial tabs: **Labor · Tax · Shop Fees · Deposits · Parts Matrix** only.
- Labor: left-side **Work Types**, Rates grid, Rules, Class Settings, Manage Billing Classes (shared modals; no standalone Billing Classes nav).
- Deliberate retirement of Financial → Labor Policies and Financial → Billing Classes navigation (partials may remain as redirects/shared authority UI).
- Pricing safety dependencies already on that lineage: `RepairOrderPricingContext`, commercial / labor / tax / shop-fee profiles, billing minimums, forward-only stamps, standard hourly rate inheritance, billing-class identity APIs.

## Parts Matrix (PR #186) retained without overwriting Labor

Merge strategy used (not a blind branch merge onto the wrong base):

1. `c91550b` — merge Labor redesign onto `main`+#185 (preserve pricing/auth engine).
2. `293df7c` — merge Parts UI onto that pin; resolve conflicts in:
   - `LaborRateType` / `LaborPolicyResolver` (keep Standard + matrix resolution from Labor redesign + #185 compatibility)
   - `ShopFinancialSettingsController` (keep forward-only stamp paths; Parts JSON `{ok,committed}` autosave)
   - `shop.blade.php` (Labor consolidated tabs + Parts master-detail panel)
   - Financial Tabler tests (assert both interfaces)

## Dependency / version notes

| Component | Requirement | Notes |
|---|---|---|
| Core pin | Integration HEAD (post test-fix commit) | Single SHA for Labor + Parts screenshots |
| SaaS / Stancl | Staging `eb859c21` (`dev-pin` checkout) | Existing alpha staging host; Core detached checkout under `vendor/ark/core` |
| Migrations | Forward-only pricing profile / billing-class work already on Labor tip | **No new tenant migration** for this integration review pin |
| Auth #185 | Present on `main` base | Must remain; LaborRateType::Standard compatibility preserved in conflict resolution |
| Production | Not in scope | Vultr / DO / lugsnplugs untouched |

## Regression intent (must pass before owner merge)

- Labor settings / Work Type matrix / standard rate
- Billing classes via Class Settings + Manage (not standalone tab)
- Parts matrices (all-matrix FormData, JSON commit, serialized autosave)
- Estimates + historical pricing snapshots (forward-only)
- Authorization amount vs approved-scope (#185)
- Mobile Labor + Parts at 320 / 375 / 430

## Owner gates

- Freeze PR #186 (draft + freeze comment) until this coherent pin is accepted.
- Do not merge this integration until same-pin Labor + Parts screenshots are reviewed.
- Do not approve another financial settings merge until both approved interfaces share one Core version.
