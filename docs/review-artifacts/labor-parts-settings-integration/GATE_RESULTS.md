# PR #187 financial integration gate — RESULTS

**Final SHA:** `bf3368adb74dca4b03a4d9b33d9f6f3fa7c01e81` (`bf3368a`)  
**Branch:** `integrate/labor-and-parts-settings`  
**PR:** https://github.com/EdwardSoaresJr/ark-core/pull/187  
**Date:** 2026-11-11

## CI (exact tip `bf3368a`)

| Check | Result | Run |
|---|---|---|
| lint | SUCCESS | Tests 43119204525 |
| security | SUCCESS | |
| targeted | SUCCESS | |
| communications-boundary | SUCCESS | |
| **focused** | **SUCCESS** (18m9s) | |
| gitleaks | SUCCESS | |
| **pest (full Regression)** | **SUCCESS** (26m7s) | 43119204483 |

`pest` was previously SKIPPED without `full-regression`; label was applied and the suite completed successfully. Incomplete/skipped checks were not treated as passed.

## Migrations (6) disposable rehearsal

`php scripts/rehearse-pr187-financial-migrations.php` → **all PASS** (legacy `standard` marking, specialty untouched, closed RO profiles null, second migrate repeatable). DB `ark_pr187_mig_rehearsal` dropped.

## Local financial regressions

Broader pricing/auth/billing/invoice/deposit/settings suites run on integration HEAD; forward-only fixture retargets applied; Parts JSON `{ok,committed}` + saveTail verified.

## PartsMatrixTuneTest

Reproducible `insufficient_data`; **known failure on main** baseline — not a new #187 regression.

## Staging

`alpha.staging.arksms.com` · SaaS `eb859c21` · Core `bf3368a` after optimize:clear. Labor + Parts same pin; mobile 375 captured; no Labor Policies / Billing Classes tabs.

## Main sync

`origin/main` still `f7cb79c` (#185). Integration **0 behind**.

## Owner next steps

- **#187 ready for merge**
- Close **#186** as superseded (do not merge independently)
- After merge: retire `settings/labor-matrix-billing-minimums` only once ancestry confirmed on main
- No production deploy / tenant migration from this gate
