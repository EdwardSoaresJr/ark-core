# Repair Order Presentation Reset — commit carve-out

**Status:** Binding until Edward approves commit scope  
**Date:** 2026-08-06  
**Rule:** Presentation Reset + Canonical Rename + Contextual Footer ship **without** Financial F1.

## In scope (this product move)

- Repair Order canonical show route + legacy GET redirects
- Workspace Modal authoring chrome
- `RepairOrderFooterProjection` / footer Blade / CSS
- Builder product-language retirement
- Related tests/docs for the above

## Financial F1 — carve out (do not stage)

| Path | Why |
| --- | --- |
| `app/Ark/Operations/Financial/FinancialContractSource.php` | F1 authority source enum |
| `app/Ark/Operations/Financial/FinancialPositionCalculator.php` | F1 calculator |
| `app/Ark/Operations/Financial/FinancialPositionProjection.php` | F1 projection |
| `docs/ARK-FINANCIAL-AUTHORITY-V2-ADOPTION.md` | F1 adoption doc |
| `docs/ARK-FINANCIAL-AUTHORITY-V2.md` | F1 doctrine edits |
| `app/Ark/Operations/Financial/README.md` | F1 README edits |
| `app/Ark/Operations/Financial/RepairOrderFinancialPresenter.php` | Consumes `FinancialPositionProjection` |
| `app/Ark/Mobile/MobileEstimateProjection.php` | Consumes F1 owe-today |
| `app/Ark/Operations/Portal/RepairOrderPortalPaymentPreviewController.php` | F1 balance wiring |
| `resources/views/components/operations/estimate-totals-panel.blade.php` | F1 label wiring |
| `tests/Feature/Operations/Financial/FinancialPositionProjectionTest.php` | F1 tests |
| `tests/Feature/Operations/Financial/FinancialCoreAuthorityTest.php` | F1 assertions |
| `tests/Feature/Operations/Financial/FinancialWorkflowUiTest.php` | F1 UI assertions |

## Adjacent — classify before stage

| Path | Likely bucket |
| --- | --- |
| `app/Ark/Operations/Documents/EstimateDocument.php` | Invoice immutability / PDF — **not** Presentation Reset; keep with F1 or separate micro-fix |
| `app/Ark/Operations/Documents/EstimateDocumentService.php` | Same — issued invoice PDF metadata |

If unsure at commit time: leave unstaged with F1.

## Present footer — Tablet

Flutter **customer** presentation / signature deep link: **not found** in `arksmsv2` or `ark-mobile` (staff Companion only).

| Action | Status |
| --- | --- |
| Customer Display | Wired → `operations.repair-orders.portal-preview` |
| Tablet | **Hidden** until customer Flutter surface exists |
| Inspection `surface=tablet` | Must **never** appear under Present |

Earn gate to show Tablet again: ship customer-facing Flutter deep link, then add `key: tablet` to `RepairOrderFooterProjection::presentActions`.

## Broader-suite failures (classified 2026-08-06)

| Test | Verdict | Evidence |
| --- | --- | --- |
| ApprovalForecast portal “Approved Work Breakdown” | **Pre-existing copy/test drift** — not rename | Portal `_estimate-summary-panel` renders `Approved work breakdown` (sentence case); PDF footer uses Title Case. `assertSee(..., false)` is case-sensitive. |
| Customer hub deferred copy | **Pre-existing copy/test drift** | Hub shows `Immediate attention item should be revisited calmly.`; test still expects retired `Safety/drivability…` string. |
| RO deferred Vehicle History on show GET | **Rename-adjacent** — fixed | Canonical show had `workspaceMode="builder"` (Estimate-only tabs). Restored `workspaceMode="review"`. History remains lazy; test now hits `workspace-tabs/history`. |
| “Schedule maintenance due at next service” | **Pre-existing copy/test drift** | `RecommendationIntent::deferredFollowUpAction()` returns `Schedule maintenance at next service` (no “due”); test expectation stale. |

Do **not** “fix” the three pre-existing copy drifts inside the Presentation Reset commit unless Edward asks — they are unrelated product-copy debt.
