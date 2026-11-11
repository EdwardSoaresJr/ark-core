# PR #187 financial integration gate

**PR:** https://github.com/EdwardSoaresJr/ark-core/pull/187  
**Branch:** `integrate/labor-and-parts-settings`  
**Gate date:** 2026-11-11  
**Final SHA:** _(filled at evidence commit)_  

## 1. GitHub CI

| Check | Workflow | Result | Notes |
|---|---|---|---|
| lint | Tests | SUCCESS | |
| security | Tests | SUCCESS | |
| targeted | Tests | SUCCESS | |
| communications-boundary | Tests | SUCCESS | |
| gitleaks | Secret scan | SUCCESS | |
| focused | Tests | _(await / record)_ | Changed-file focused suite + known-failure baseline |
| pest (full Regression) | Regression | _(await / record)_ | Was **SKIPPED** until `full-regression` label added (by design). Not classified as passed while skipped. Label applied; run `43110450611`. |

Main still `f7cb79c` (#185). Integration **55 ahead / 0 behind** — no lost-feature gap vs current main at gate time.

## 2. Migrations (6) — disposable rehearsal

Script: `scripts/rehearse-pr187-financial-migrations.php`  
DB: MySQL `ark_pr187_mig_rehearsal` (created and dropped; never production).

Migrations:

1. `2026_11_10_010000_mark_standard_labor_policies.php`
2. `2026_11_10_120000_add_labor_pricing_profile_and_billing_minimums.php`
3. `2026_11_10_180000_labor_matrix_profile_v2_and_programming_split.php`
4. `2026_11_10_210000_shop_fee_flat_mode_and_profile.php`
5. `2026_11_10_220000_commercial_pricing_profile_on_repair_orders.php`
6. `2026_11_10_230000_tax_profile_on_repair_orders.php`

**Result: all PASS** (log `/tmp/pr187-mig-rehearsal.txt`):

- Open Customer Pay `hourly` rows matching shop default → `standard`
- Open specialty `19000` stays `hourly`
- Closed matching-default historical row stays `hourly` (`effective_until` set)
- Additive profile columns present; closed RO profiles remain **null** (forward-only)
- Closed RO `status` / `public_id` preserved
- Second `artisan migrate` exit 0 (repeatability)
- `mark_standard` re-invoke stable; guarded additive ups replay OK
- Unguarded `120000` direct replay raises (expected); covered by migrator bookkeeping

## 3. Broader financial regressions (local, integration HEAD)

| Batch | Tests | Passed | Failed | Notes |
|---|---|---|---|---|
| Pricing / auth / billing / estimates / concerns | 188 | 186 | 2 → fixed | Auth integrity retargeted for forward-only tax/fee stamps |
| Settings / labor / invoices / deposits / tune | 126 | 125 | 1 | PartsMatrixTune known-failure (see §4) |
| Parts JSON `{ok,committed}` + sibling saves | 4 | 4 | 0 | ShopSettingsTest filter |
| Financial Tabler Parts contract | 1 | 1 | 0 | `payload.committed === true` |
| Auth integrity tax/fee settings rise (retargeted) | 2 | 2 | 0 | After forward-only assertion update |

Auth fix: `#66` now asserts stamped ROs do **not** reprice or invent authorization debt when only shop tax/fee settings change (aligned with `tax_profile` stamp-at-create).

## 4. PartsMatrixTuneTest

- **Reproducible:** yes — `insufficient_data === true` (expects false)
- **Classification:** **Pre-existing known failure** listed on `main` in `.github/regression-known-failures.txt` (`PartsMatrixTuneTest::parts matrix tune assistant simulates markup changes on closed sample`)
- **Not introduced by Labor+Parts settings integration**; owner matrix-tune assistant sampling/date-scope — out of Financial Rules nav merge gate
- CI Regression/focused reporters treat it as baseline, not a new regression

## 5. Parts Matrix save contract survival

Present on integration tip in `parts-matrices-panel.blade.php`:

- `saveTail` serialized autosave chain
- `Accept: application/json`
- Success requires `payload.committed === true`

Feature tests covering committed payload + sibling-preserving saves: **passed**.

## 6. Staging after fresh application boot

| | |
|---|---|
| Host | `alpha.staging.arksms.com` |
| SaaS | `eb859c21` |
| Core | integration tip (pinned) |
| Boot | `optimize:clear` / `view:clear` / `config:clear` / `route:clear` (no nginx/php-fpm restart; no prod infra) |

Post-boot UI:

- Financial tabs: Labor · Tax Rules · Shop Fees · Deposits · Parts Matrix only
- No Labor Policies / Billing Classes **tabs** (Manage Billing Classes remains under Labor)
- Mobile 375: Labor + Parts usable; screenshots `screenshots/03-labor-mobile-375-after-boot.png`, `04-parts-mobile-375-after-boot.png`

## 7. Vs current main

`origin/main` = `f7cb79c` (unchanged since #185). Merge-base clean; integrating #187 lands Labor redesign + Parts without another orphaned branch.

## 8. Owner actions after green CI

- Approve/merge **#187** only
- Close **#186** as superseded (do not merge independently)
- After merge, retire `settings/labor-matrix-billing-minimums` only once tip ancestry is confirmed on `main`
- No production deploy / tenant migration from this gate

## Ready-for-merge criterion

All of: Tests focused SUCCESS (or known-failure-only), full Regression pest SUCCESS (or known-failure-only), migrations PASS, local financial suites PASS aside from known PartsMatrixTune, staging post-boot verified, main still ancestor-clean.
