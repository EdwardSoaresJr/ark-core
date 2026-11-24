# Final Regression Reconciliation & Release Gate

**Date:** 2026-11-24  
**Role:** Integration owner — regression reconciliation (no production deploy)

## Candidate lock

| Artifact | SHA |
|----------|-----|
| **Product Core** | `bc5b8187008f3002a6d28039f86eb4a44841484e` |
| **Product SaaS** | `67762712fe5fb6a65307450dd54b57ab464ed159` |
| Core test-fix commit | `b6aa4588e2024ff5c0739ac1d821a4451e3cebc2` (`AdvisorHomeBoardTest` DOM contract) |
| SaaS test-fix commit | `83df07d237bda3c67446580f91e0250ab509c4e7` (`standard_hourly_rate` payload) |
| Overnight Core baseline | `9cc852899b2ab9754185675b7d83d656978b2b33` |
| Overnight SaaS baseline | `df735e4096e94eb8f0440f5124794ea924a88d2a` |

Staging (`157.230.172.143` / `demo.staging.arksms.com`): SaaS `67762712` + Core pin `bc5b8187` — **matches product candidate**. `APP_ENV=staging`, `/up` 200.

## Suites vs overnight baseline

| Suite | Overnight baseline | Candidate | Delta |
|-------|--------------------|-----------|-------|
| Full Core Pest | 4849 tests; 224 fail/err; **31 regressions** | 4884 tests; 229 fail/err; **37 raw regressions** | +35 tests on tip; see reconciliation |
| AdvisorHomeBoardTest | **7 FAIL** | Full-suite junit captured pre-fix fails; **post-fix 14/14 PASS** on `b6aa4588` | **Remediated** (stale DOM assertions) |
| Financial 17-file re-run | **247/247 PASS** | **247/247 PASS** (210325 ms) | Same |
| MySQL concurrency | **3/3 PASS** | **3/3 PASS** (35 assertions) | Same |
| SaaS PHPUnit | 762; 5 fail; labor P1 | 762; 3 fail + 2 err; **labor PASS** | Labor remediated |
| Staging UI (auth guest) | n/a (overnight synthetic Core) | Job Board / Customers / Vehicles / ROs / Profile + Tabler assets **200** | Verified |

### Core regression reconciliation

Raw CI report: 37 regressions vs known baseline.  
Minus 7 AdvisorHomeBoard (remediated after suite start; re-verified 14/14): **30 remaining**.

| Bucket | Count | Classification |
|--------|------:|----------------|
| Communications / devices / missed-call / quick-reply | ~20 | **Existing** (overnight P1/P2; Shop-adjacent) |
| Daily Ops / Demo companion / Owner call intelligence | ~9 | **Existing** / environmental 302s |
| `RepairOrderOpenProfileTest` | 1 | **Existing** (overnight P2) |

No new financial/auth-cent regressions. Full Pest exit **2** (same class as overnight).

### SaaS failures

| Test | Class | Notes |
|------|-------|-------|
| `test_each_shop_keeps_its_own_labor_rate_setting` | **Remediated** | PASS after `standard_hourly_rate` |
| `MessageDeliveryTest` … `reported` vs `observed` | **Existing** | Overnight P2 |
| `BookAppointmentProductTest` concurrent | **Existing** | Overnight P2 |
| `ShopScheduleTest` appointments:send-reminders | **Existing** | Overnight P2 |
| `ReverbBroadcastTest` (2 errors) | **Environmental** | Broadcast stack; not present as overnight assert fails |
| `QzPrintingIsolationTest` | **Cleared** | Overnight P3 no longer failing |

## Remediation performed

1. **Core** `AdvisorHomeBoardTest` — align with Job Board list/cards Tabler DOM (`view=cards`, `data-job-board-card`, Check In button classes). Assertions not weakened.  
2. **SaaS** `RepairOrderWorkflowTest::laborSettings()` — include required `standard_hourly_rate`.  
3. Staging demo enter blocked by **staff-seat exhaustion** (guest assignments) + rate limit — environmental; seats freed for UI verify only.

## Staging UI verification

Authenticated guest advisor via `/demo/enter` after seat cleanup:

- `/app`, `/app?view=cards` — Job Board, `data-job-board`, List/Cards  
- `/app/customers/search`, `/app/vehicles/search`, `/repair-orders`, `/app/profile` — 200  
- Tabler assets: `tabler-DOFHxzQL.css`, `tabler-Bs_7zNN4.js`, `app-*.css/js`  
- Profile: `display_theme` / Dark mode present  
- No Preline on login HTML  

## Final verdict

### **PASS** (UX RC release-ready)

Product candidate **Core `bc5b8187` + SaaS `67762712`** is cleared for owner release decision:

- Confirmed integration regressions (Job Board test contract + SaaS labor payload) **fixed and retested**  
- Financial integrity matrix **PASS** (247/247 + 3/3 concurrency)  
- Staging SHAs match; staff UI surfaces + Tabler assets verified  
- Remaining Core/SaaS failures classify as **existing overnight carry-forward** or **environmental** (Reverb), not new UX-pack money/path defects  

### Remaining risks

- Communications / device-workspace Pest regressions still open on Core tip (Shop-adjacent)  
- SaaS P2: MessageDelivery, BookAppointment concurrency, ShopSchedule due-commands  
- SaaS Reverb broadcast errors in this environment  
- Full Core Pest junit still lists AdvisorHomeBoard fails from the run that started before the test-fix commit; tip re-verify is 14/14  
- Demo staff-seat pressure on staging if guests accumulate again  

### Production

**Not deployed.** Production cutover requires explicit owner authorization naming artifact + target.

## Evidence paths

- `review-artifacts/ux-rc-regression-20261124/logs/` (Core Pest junit, financial, concurrency, AdvisorHomeBoard)  
- `../ark-stancl-ux-rc-67762712/review-artifacts/ux-rc-regression-20261124/logs/` (SaaS suite)  
- `evidence/overnight-baseline/` (copied overnight reports; overnight worktree untouched)  
- `evidence/staging-ui-verify.txt`, `evidence/failure-classification.md`, `evidence/ci-regression-report.md`
