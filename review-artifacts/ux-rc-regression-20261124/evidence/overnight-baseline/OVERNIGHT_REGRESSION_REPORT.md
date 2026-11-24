# Overnight Pest Regression & Financial Integrity Audit

**Audit date:** 2026-11-24 (America/Denver)  
**Agent role:** Independent regression / financial-integrity testing only (no feature work, no deploy, no production mutations)  
**Worktrees:**

| Repo | Path | Branch | SHA |
|------|------|--------|-----|
| Core | `/Users/edwardsoares/Herd/ark-core-overnight-reg-20261124` | `overnight/regression-audit-20261124` | `9cc852899b2ab9754185675b7d83d656978b2b33` |
| SaaS | `/Users/edwardsoares/Herd/ark-stancl-overnight-reg-20261124` | `overnight/saas-regression-audit-20261124` | `df735e4096e94eb8f0440f5124794ea924a88d2a` |

**SaaS Core pin (composer.lock):** `9cc852899b2ab9754185675b7d83d656978b2b33` (matches tested Core SHA)

Primary checkouts and other agents' worktrees were left alone. Unrelated dirty trees were not reset.

---

## Verdict (owner review)

| Gate | Result |
|------|--------|
| **Financial integrity (production baseline `9cc85289`)** | **PASS** |
| **Overall regression readiness (production baseline)** | **FAIL** |
| **Final combined integration candidate** | **PENDING FINAL INTEGRATION TEST** |
| **Production release** | **BLOCKED BEFORE CUTOVER** (audit only; no deploy attempted) |

Financial authorization, pricing snapshots, invoicing/payment capture suites, and disposable-MySQL concurrency overlap all passed on Core `9cc85289`, including a second-pass financial re-run (247/247).

Overall readiness on the **production baseline** fails because the full Core suite has **31 failures not in** `.github/regression-known-failures.txt`, including seven **Advisor Home / Job Board card** DOM regressions on main, plus five SaaS Feature failures against the matching Core pin (one labor-rate settings isolation failure).

**A final PASS is not issued.** Individual PR focused CI and the baseline run are not a combined-candidate regression.

Companion matrix: [FINANCIAL_INTEGRITY_TEST_MATRIX.md](./FINANCIAL_INTEGRITY_TEST_MATRIX.md).  
Raw evidence: `review-artifacts/overnight-regression-20261124/`.  
Independent integration auditor (same candidate SHAs): `docs/engineering/ARK_INTEGRATION_READINESS_AUDIT.md` on Core branch `docs/integration-readiness-audit-2026-11-24`.

---

## Final integration gate — workstream completion (2026-11-24 re-check)

Re-fetched `origin` for Core and SaaS. **None** of the four Tabler UX workstreams are merged to `main`. Tips below match the integration auditor’s evaluated SHAs; later tip moves require **PENDING RECHECK**.

| Workstream | PR(s) | Tip SHA | State | On `main`? | Full Pest / Regression on tip | Treated as final? |
|------------|-------|---------|-------|------------|-------------------------------|-------------------|
| Job Board UX | **#254** List UX | `f10b70f5d42f988361c9dd9697d2efa5cbde3106` | OPEN, MERGEABLE | **No** | Focused/targeted SUCCESS; full `pest` not a green merge gate | **No** — unmerged |
| Job Board UX | **#256** Cards hierarchy (base `#254`) | `d35acf7502360d36eafc31338ea546303762e82e` | OPEN, MERGEABLE | **No** | Focused SUCCESS; Regression `pest` **SKIPPED** | **No** — unmerged |
| Customers / Vehicles / RO List+Cards | **#257** | `9b5bc832fa75e6f840073e19be1971be658014d4` | OPEN, MERGEABLE | **No** | Focused SUCCESS; Regression `pest` **SKIPPED** | **No** — unmerged |
| Layout preferences + System appearance | **#258** | `4d20aa7f471ea07bd20758b49623562f7720767d` | OPEN, MERGEABLE | **No** | Focused SUCCESS; Regression `pest` **SKIPPED** | **No** — unmerged |
| Legacy UI asset retirement | **#259** | `b54c4864dcdb40229980c96197348c68e3ed738e` | OPEN, MERGEABLE | **No** | Focused SUCCESS | **No** — unmerged |
| Eval only (not product) | #255 (draft) | `3b614614…` | OPEN draft | No | — | **Exclude** |

### Intended combined Core/SaaS candidate (prospective — not tested as final)

Aligned with the integration auditor:

| Layer | Intended SHA / pin | Status |
|-------|-------------------|--------|
| Core base | `9cc85289` (`origin/main`) | Baseline Pest **completed** (this report) |
| + Job Board pack | merge **#256** tip `d35acf75` (includes #254) | **NOT MERGED** |
| + Preline drop | **#259** tip `b54c4864` | **NOT MERGED** |
| + Preferences / appearance | **#258** tip `4d20aa7f` | **NOT MERGED** |
| + Collections List/Cards | **#257** tip `9b5bc832` (must rebase onto #258 per auditor) | **NOT MERGED** / dual-authority risk until rebased |
| SaaS | `df735e40` pin Core `9cc85289` | Pin bump **only after** Core merges; **NOT** done |

### What was NOT run (final candidate)

| Activity | Status |
|----------|--------|
| Disposable merge-simulation of #256+#259+#258+#257 | **NOT TESTED** (workstreams unmerged; no owner-approved final tip stack) |
| Full Pest on combined candidate | **NOT TESTED** |
| Financial-integrity suite on combined candidate | **NOT TESTED** (baseline PASS only) |
| SaaS suite against post-merge Core pin | **NOT TESTED** |
| Browser smoke on combined candidate | **NOT TESTED** |
| Comparison of combined vs baseline failures | **NOT TESTED** |

### Missing for lifting PENDING

1. Owner acceptance and merge (or an explicit owner-named integration branch with frozen tip SHAs) for #256, #259, #258, then rebased #257.  
2. Isolation worktree merge simulation recorded with exact SHAs.  
3. Full Core Pest + financial re-run + SaaS Feature (matching pin) + browser smoke on that candidate.  
4. Diff failures vs this baseline (`9cc85289` / `df735e40`).  
5. Cross-signoff with integration auditor on the **same** candidate SHAs.

Until then: **PENDING FINAL INTEGRATION TEST** — do not treat focused CI green or baseline financial PASS as final readiness.

---

## Phase 0 — Testing baseline

### Remotes and pins

- Fetched `origin` for Core and SaaS (`ark-stancl`).
- Core `origin/main`: `9cc85289` — Merge PR #253 Job Board list/cards switcher.
- SaaS `origin/main`: `df735e40` — Except Job Board layout cookie from EncryptCookies (#348); pins Core `9cc85289`.

### Production SHAs

- **Not live-verified** (production protection: no SSH/deploy/DNS).
- Documented historical LugsNPlugs pin in `RELEASE_PROTOCOL.md` is older (`Core d926813`) and is **not** treated as current acceptance evidence.
- Intended integration combination for this audit: **SaaS main `df735e40` + Core pin `9cc85289`**.

### Open PRs (Core) relevant to active agents

| PR | Tip SHA (at audit) | Notes |
|----|--------------------|-------|
| #256 Job Board Cards hierarchy | `d35acf7502360d36eafc31338ea546303762e82e` | Open; base `feat/job-board-list-ux-audit`; **not** merge-simulated |
| #254 Job Board list UX | `f10b70f5d42f988361c9dd9697d2efa5cbde3106` | Open on main |
| #255 Eval Tabler vs DataTables | `3b614614ba4212af258d6d216134b7e60871204f` | Eval only |

### Environment

| Component | Version / value |
|-----------|-----------------|
| PHP | 8.4.28 (Herd) |
| Laravel (Core) | 13.29.0 |
| Pest | 4.7.8 |
| Composer | 2.11.1 |
| Node / npm | v22.22.1 / 10.9.4 |
| Core migrations present | 357 |
| Core test DB | SQLite `:memory:` (`phpunit.xml` force) |
| Frontend build | `npm run build` OK (vite 8.3.4); assets under `public/build/` |
| SaaS MySQL | 8.4.8 (Herd local) |
| SaaS Redis | PONG |
| SaaS test DB | `ark_stancl_testing` + prefix `ark_stancl_test_shop_` |
| Disposable MySQL concurrency | user `ark_test_overnight@127.0.0.1`, prefix `ark_test_overnight` |

Known-failure baseline: `.github/regression-known-failures.txt` — **200** entries; `# tests: 4375` (stale relative to current suite size).

---

## Phase 1 — Full Pest regression (Core)

### Run identity

- Command: `php -d memory_limit=2048M vendor/bin/pest --no-coverage --log-junit …/junit.xml`
- Started: 2026-11-24T07:42:08Z  
- Finished: 2026-11-24T09:04:39Z  
- Wall time: **4950.97 s** (~82.5 min)  
- Peak RSS (`/usr/bin/time -l`): **527,499,264** bytes  
- Peak memory footprint: **817,906,816** bytes  
- Exit code: **2** (PHPUnit: failures and/or errors)

### Totals (JUnit)

| Metric | Count |
|--------|------:|
| Tests | 4849 |
| Passed | 4467 |
| Failed (assert) | 195 |
| Errors | 29 |
| Skipped | 158 |
| Assertions | 28990 |
| Suite time (junit) | 4841.75 s |

### Baseline comparison (`scripts/ci-regression-report.php`)

| Bucket | Count |
|--------|------:|
| Failing total | 224 |
| Known baseline entries | 200 |
| **Regressions (failing, not in baseline)** | **31** |
| Known failures still failing | 193 |
| Known failures now passing (remove from baseline) | 7 |

Report artifact: `review-artifacts/overnight-regression-20261124/evidence/ci-regression-report.md`.

### Confirmed regressions (not in known baseline) — summary

**P1 — Job Board / Advisor Home (7)** — `AdvisorHomeBoardTest`  
DOM expectations for home/job-board cards fail (`ops-card-ro-*` anchors, promise-time meta, waiting-approval column, labor progress hiding). Consistent with main after #253 Job Board list/cards switcher; card markup/hooks likely drifted while tests still expect prior structure.

**P1 / P2 — Communications Tabler / device workspace (18)** — shop workspace, discovered devices, stations/phones shell, missed-call rescue settings, quick-reply templates. Mostly 302 vs 200 or missing UI hooks. Communications are ARK Shop–adjacent; still suite regressions on Core main.

**P2 — Demo / Daily Ops / Owner settings / RO open profile (6)**  
- `DailyOperationsTablerTest`, `DemoCompanionSurfaceTest`, `OwnerCallIntelligenceTest`: 302 instead of 200  
- `RepairOrderOpenProfileTest`: query budget 66 > 65 (perf assertion)

### Known failures (selected) that remain — not new

Includes communications fabric/inbox suites, `SendEstimateLinkTest` (422), `RepairOrderLifecycleQueueTest`, `PartsMatrixTuneTest`, and `RepairOrderFinancialPresenterBalanceAxesTest` query-count assertion (`0 is less than 0`). These are **baseline**, not new regressions.

### Reproducibility

- Full suite: single complete run (JUnit preserved).  
- Financial subset second pass: **247 passed / 247** (see Phase 2).  
- Concurrency MySQL: **3 passed / 3**.

### Risky / incomplete

- Skipped: 158 (includes Integration MySQL when not selected; full suite did not include `tests/Integration`).  
- Exit code 2 reflects errored cases (29) plus failures.

---

## Phase 1b — SaaS integration suite (matching Core pin)

### Blocked first attempt (environment)

- First run (~22 s): **762 tests, 737 errors** — `InvalidArgumentException: Please provide a valid cache path` (missing `storage/framework/{cache,views,…}` in fresh worktree).  
- Evidence preserved as `saas-full-BLOCKED-cachepath.*`.

### Successful re-run (after mkdir storage dirs)

| Metric | Value |
|--------|------:|
| Core pin | `9cc85289` |
| SaaS SHA | `df735e40` |
| Tests | 762 |
| Passed | 754 |
| Failures | 5 |
| Errors | 0 |
| Skipped | 3 |
| Assertions | 11855 |
| Wall time | 4835.08 s |
| Exit | 1 |

### SaaS failures (severity)

| Test | Severity | Notes |
|------|----------|-------|
| `RepairOrderWorkflowTest::test_each_shop_keeps_its_own_labor_rate_setting` | **P1** | Validation: `The standard hourly rate field is required.` SaaS multi-shop labor-rate settings POST no longer matches Core form contract. |
| `MessageDeliveryTest::test_callbacks_are_recorded_once_…` | **P2** | Expected delivery state `reported`, got `observed`. |
| `BookAppointmentProductTest::test_concurrent_submissions_…` | **P2** | All submissions rejected `preferred_date` (0 bookings). Likely date/fixture sensitivity. |
| `ShopScheduleTest::test_core_due_commands_run_inside_each_opened_shop` | **P2** | Unexpected `appointments:send-reminders` in due-command set. |
| `QzPrintingIsolationTest::test_each_shop_signs_with_its_own_pair…` | **P3** | HTML/UI assertion miss. |

### Tenant isolation (SaaS)

| Suite | Result |
|-------|--------|
| `TenantIsolationTest` (6) | **PASS** |
| `PaymentVisibilityAndStaffTest` | **PASS** |
| `CommercialPaymentsResellersCreditsTest` | **PASS** |
| `BackupTenantReportTest` | **PASS** |
| `CoreQueryAndWorkerBaselineTest` | **PASS** |

Smoke: `test_two_shops_are_isolated_end_to_end` — OK (33 assertions) after storage fix.

---

## Phase 2–3 — Financial integrity (summary)

See [FINANCIAL_INTEGRITY_TEST_MATRIX.md](./FINANCIAL_INTEGRITY_TEST_MATRIX.md).

**Headline:** Core financial authorization / pricing / fee / tax stamp / payment capture / AR allocation suites **PASS** on `9cc85289`. Second-pass financial re-run **247/247**. Disposable MySQL concurrency overlap **3/3**.

Invariant checked by F-FIN-01 / ApprovedWork / AuthorizationIntegrity / PaymentCapture: system does not bill above authorized cents merely because an estimate changed.

---

## Phase 4 — Tenant security

- SaaS two-shop isolation suite: **PASS**.  
- Core settings separation Phase 1–3: **PASS** in full suite.  
- No production tenant data accessed. Local SaaS tests used `ark_stancl_testing` only.

---

## Phase 5 — Workflow regression

### Automated (Core Feature — from full suite)

| Area | Status |
|------|--------|
| Check In (`IntakeEntryTest`, `VisitCheckInActionTest`) | PASS |
| Job Board list (`JobBoardListViewTest`) | PASS |
| Workboard glance (`WorkboardCardGlanceTest`) | PASS |
| Advisor Home board cards (`AdvisorHomeBoardTest`) | **FAIL (7) — regression** |
| RO status catalog / closeout PDF | PASS |
| Authorization → invoice → capture paths | PASS (financial suites) |

### Browser (synthetic local only)

- Linked Herd site `ark-overnight-reg.test` → overnight Core worktree.  
- Disposable MySQL DB `ark_overnight_reg` (not shared `ark` DB); migrate:fresh + seed admin; `InstallationState::markInstalled()` for local smoke only.  
- Verified logged-in navigation: Dashboard, **Job Board** (`/app` workboard) with List/Cards switcher + lane tabs + search, **Check In** intake (customer fields, billing class), **Customers** search.  
- Did **not** drive a full RO money path in the browser (covered by Pest). No live SMS/email/payments.

---

## Phase 6 — Current PR compatibility

**PENDING FINAL INTEGRATION TEST** (see Final integration gate above).

- Workstreams #254/#256, #257, #258, #259 remain **OPEN and unmerged**; tips match integration auditor SHAs (`f10b70f5`, `d35acf75`, `9b5bc832`, `4d20aa7f`, `b54c4864`).  
- Baseline acceptance remains **main Core `9cc85289` + SaaS `df735e40`**.  
- No disposable combined merge was executed as a “final” candidate — that would invent completion.  
- Individual PR focused CI SUCCESS is **not** reported as integration PASS.

When owner freezes the stack: create a disposable worktree, merge in auditor order (#256 → #259 → #258 → rebase #257), record SHAs, run full Pest + financial + SaaS (pin only in that worktree) + browser smoke, compare to this baseline, then discard the worktree — **do not push pin changes or merge without owner approval**.

---

## Findings classification

### P0 — Financial integrity / tenant security / data corruption

**None confirmed** on Core `9cc85289` financial suites or SaaS tenant isolation.

### P1 — Broken core workflow / pricing settings isolation

1. **AdvisorHomeBoardTest (7)** — Job Board / home card presentation broken vs tests after #253.  
2. **SaaS `RepairOrderWorkflowTest::test_each_shop_keeps_its_own_labor_rate_setting`** — standard hourly rate field required; multi-shop labor settings path broken or contract drifted.

### P2 — Important noncritical

- 18+ Core communications/settings regressions vs baseline.  
- SaaS MessageDelivery callback state, booking concurrency date, shop schedule due-commands.  
- `RepairOrderOpenProfileTest` query budget (+1).

### P3 — Cosmetic / minor

- SaaS Qz printing HTML assertion.  
- Various 302 vs 200 settings page expectations.

---

## Coverage gaps

| Gap | Status |
|-----|--------|
| Live production SHA verification | NOT TESTED (protected) |
| Combined merge of open Job Board PRs | NOT TESTED |
| Full interactive RO money path in browser | NOT TESTED (Pest covered) |
| Real Square/Twilio charges | NOT TESTED (forbidden; mocked only) |
| Core Integration MySQL outside ConcurrencyOverlap | NOT TESTED (only ConcurrencyOverlap run) |

---

## Recommended fixes (no silent remediation performed)

1. Align Advisor Home / Job Board card DOM hooks with post-#253 markup **or** update `AdvisorHomeBoardTest` in the same PR that owns the card redesign — do not greenwash by skipping.  
2. Fix SaaS labor-rate settings POST to satisfy Core’s required standard hourly rate (or restore field name) so `test_each_shop_keeps_its_own_labor_rate_setting` passes.  
3. Refresh `.github/regression-known-failures.txt` `# tests:` count to ~4849; remove the 7 now-passing known entries after confirmation.  
4. Ensure SaaS worktrees create `storage/framework/{cache,views,sessions,testing}` before CI/local Feature runs.  
5. Triage communications 302 regressions separately from financial release gates.

---

## Production release blockers

1. **31 Core regressions vs known-failure baseline** on the SHA SaaS already pins (`9cc85289`), including Job Board home-card failures.  
2. **SaaS labor-rate settings isolation failure** against that pin.  
3. This audit did **not** authorize or perform any production cutover.

---

## Exact SHA combinations tested

| Run | Core | SaaS | Notes |
|-----|------|------|-------|
| Core full Pest | `9cc85289` | n/a | Complete |
| Core financial re-run | `9cc85289` | n/a | 247/247 |
| Core concurrency MySQL | `9cc85289` | n/a | 3/3 |
| SaaS Feature (blocked) | pin `9cc85289` | `df735e40` | cache path env failure |
| SaaS Feature (valid) | pin `9cc85289` | `df735e40` | 754/762; 5 fail |

**Stop for owner review.**
