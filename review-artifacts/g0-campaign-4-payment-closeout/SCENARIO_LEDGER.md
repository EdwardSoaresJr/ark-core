# G0 Campaign 4 — Scenario ledger

Synthetic RO **#20** · alpha.staging · Core `e9b4268` · SaaS `c60f2c37`  
Companion RO **#21** (forward-only probe only)

Statuses: **Pass** · **Fail** · **Blocked** · **Partial** · **Not Run**

| ID | Scenario | Status | Evidence | Notes |
| --- | --- | --- | --- | --- |
| C4-00 | Verify pins + RO #20 unpaid invoice $259.50 | Pass | `00-state-verification.md` | Invoice id 7 / 25950¢; ledger 0; unauthorized +108 excluded |
| C4-01 | Publish Campaign 3 evidence on feature branch | Pass | GitHub links in `00-` / README | Commit `1b34fe2` |
| C4-02 | Fail A frozen-invoice fee 1500→0 root cause | Pass (classified) | `01-financial-fail-a-*.md` | Test debt / forward-only stamp; **not** underbilling |
| C4-03 | Fail B presenter query-count 0<0 root cause | Pass (classified) | `02-financial-fail-b-*.md` | ReadCache invalidates assertion |
| C4-04 | DEF-G0-05 review + unit tests + isolated commit | Pass | `03-def-g0-05-*.md`, Core `e9b4268` | Helper `shouldIgnorePaymentAmountShortcut` |
| C4-05 | DEF-G0-05 staging deploy (backup+deploy.sh) | Pass | SaaS `c60f2c37` | Staging only |
| C4-06 | DEF-G0-05 browser proof (iframe focus) | Pass | `03-` | Amount stays 259.50 under iframe keydown |
| C4-07 | Square form visible / cardReady | Pass | Screenshot in session; `04-` | Ordinary open path works |
| C4-08 | Square sandbox decline | Blocked | `04-square-supervised-checklist.md` | **Automation limitation:** cross-origin iframe fill |
| C4-09 | Fresh-page retry after decline | Blocked | `04-` | Depends on C4-08 |
| C4-10 | Square sandbox success + provider record | Blocked | `04-` | Needs owner supervised card entry |
| C4-11 | Duplicate payment / webhook idempotency | Not Run | — | Needs successful charge first |
| C4-12 | Invoice balance reconciliation after pay | Not Run | — | Needs C4-10 |
| C4-13 | RO #20 paid closeout + terminal status | Blocked | — | Needs valid sandbox payment |
| C4-14 | Post-close financial immutability | Blocked | — | Needs C4-13 |
| C4-15 | DEF-G0-03 sticky footer residual | Pass | `06-sticky-footer.md` | 600/700px; no overlap after scroll |
| C4-16 | Forward-only settings vs historical RO | Pass | `05-forward-only.md` | RO20 unchanged; RO21 fees 1500 |
| C4-17 | Focused JS regressions | Pass | `07-test-results.md` | 19/19 |
| C4-18 | Focused Fail A/B still failing as classified | Pass | `07-` | 0/2 expected until test fix approved |

## Separate reporting lanes

| Lane | Outcome |
| --- | --- |
| Browser-proven | Pins, RO20 unpaid, payment UI open, Square iframe ready, DEF-G0-05 keypad ignore, footer clearance |
| Automated-test | Payment-capture JS 19/19; Fail A/B still fail as test debt |
| Provider-confirmed Square sandbox | **Not yet** — awaiting supervised human card entry |
| Code-path without execution proof | Webhook idempotency / closeout immutability (covered by existing Pest suite historically; not re-proven on RO20) |
