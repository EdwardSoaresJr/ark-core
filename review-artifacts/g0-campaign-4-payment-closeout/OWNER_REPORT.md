# G0 Campaign 4 — Owner report (A–H)

**G0 is not passed.** Payment success, authorization reconciliation after pay, and paid closeout on RO #20 remain unproven. Production remains unauthorized.

Pins: Core `e9b4268` · SaaS `c60f2c37` · `alpha.staging.arksms.com`.

---

## A. Payment status

| Step | Result | Kind |
| --- | --- | --- |
| Payment workspace opens; amount $259.50 | Pass | Browser-proven |
| Square sandbox iframe loads (`cardReady`) | Pass | Browser-proven |
| Sandbox decline | **Blocked** | Automation limitation (cross-origin iframe) |
| Fresh-page retry | **Blocked** | Depends on decline |
| Successful sandbox charge | **Blocked** | Needs supervised human entry |
| Provider transaction recording | **Not Run** | Needs charge |
| No duplicate payments / webhook idempotency | **Not Run** | Needs charge |
| Invoice balance reconciliation | **Not Run** | Needs charge |

Supervised checklist: `04-square-supervised-checklist.md`.

**Distinction:** ARK form path works in an ordinary browser open. Filling Square’s cross-origin fields is an **automation limitation**, not classified as an ARK payment-form defect after DEF-G0-05.

---

## B. RO #20 final state

| Field | Value |
| --- | --- |
| Status | `ready_pickup` |
| Payment | `unpaid` |
| Invoice document id | `7` · issued · **25950¢ ($259.50)** |
| Unauthorized increase | Still excluded (+10800¢ on draft estimate only) |
| Ledger payments | **0** |
| Balance due | **25950¢** |
| Closeout | **Not completed** (requires valid payment) |
| Post-close immutability | **Not Run** |
| Anomaly | `closed_at` set while still unpaid/ready_pickup — residual; not force-cleared |

---

## C. DEF-G0-05 result

| Item | Detail |
| --- | --- |
| Root cause | Window `@keydown` amount keypad stole digits while Square iframe focused (**ARK defect #1**). Separately, automation cannot type into cross-origin Square fields (**limitation #2**). |
| Fix | `shouldIgnorePaymentAmountShortcut` — ignore IFRAME, foreign inputs, contenteditable; allow amount field + non-input chrome. |
| Commit | Core `e9b42680adfb70e96833f20a814e879f012a4e9d` |
| Staging | SaaS pin `c60f2c37` via `deploy/deploy.sh` (backup-gated) |
| Browser verification | Iframe-focused digit keydown leaves amount at `259.50`; amount/body still accept keys; foreign input ignored |
| Automation limitation | Still cannot complete PAN entry inside Square iframe |

---

## D. Financial failures

### Fail A — fees expected 1500, actual 0

**Classification:** Test debt after forward-only `shop_fee_profile` stamp — **not** a live underbilling defect.

`financialCloseoutRepairOrder()` disables fees before create → stamp disabled → mid-test live enable correctly ignored → fees stay 0. Expected $15 is **not** billable under the stamped rules for that fixture.

**Proposed correction (not applied):** enable fees before create / split forward-only vs freeze tests. Do not assert 0 alone.

### Fail B — presenter `0 < 0`

**Classification:** Test harness invalidation by `RepairOrderReadCache` warmed in setup.

**Proposed correction (not applied):** flush cache before fresh measurement, or spy that preload skips `projectForRepairOrder`.

No financial calculation change in this campaign.

---

## E. Forward-only results

| Subject | After live fees 10% + tax + $200/hr labor |
| --- | --- |
| RO #20 | fees 0 · approved 25950 · invoice 25950 · stamp still disabled |
| New RO #21 | stamp enabled 10% · fees **1500** · total **16500** |
| Live settings | Restored (fees off, tax off, labor 16500) |

---

## F. Regression results

| Suite | Result |
| --- | --- |
| `ark-payment-capture` JS | **19/19 pass** |
| Fail A + Fail B focused | **0/2** (expected until test fix approved) |
| Broader financial 131 | Prior **129/131**; not re-run after JS-only pin |

---

## G. Evidence publication

Campaign 4 (this package):

- https://github.com/EdwardSoaresJr/ark-core/tree/settings/labor-matrix-billing-minimums/review-artifacts/g0-campaign-4-payment-closeout  
  (links resolve after evidence commit push)

Campaign 3 (already pushed at `1b34fe2`):

- https://github.com/EdwardSoaresJr/ark-core/tree/1b34fe2a32fd1bde435158989ec1221b7de5a90e/review-artifacts/g0-campaign-3-ro-lifecycle
- https://github.com/EdwardSoaresJr/ark-core/blob/1b34fe2a32fd1bde435158989ec1221b7de5a90e/review-artifacts/g0-campaign-3-ro-lifecycle/README.md
- https://github.com/EdwardSoaresJr/ark-core/blob/1b34fe2a32fd1bde435158989ec1221b7de5a90e/review-artifacts/g0-campaign-3-ro-lifecycle/SCENARIO_LEDGER.md
- https://github.com/EdwardSoaresJr/ark-core/blob/1b34fe2a32fd1bde435158989ec1221b7de5a90e/review-artifacts/g0-campaign-3-ro-lifecycle/OWNER_REPORT.md

DEF-G0-05 Core: https://github.com/EdwardSoaresJr/ark-core/commit/e9b42680adfb70e96833f20a814e879f012a4e9d  
SaaS pin: https://github.com/EdwardSoaresJr/ark-stancl/commit/c60f2c37f376418094be202fa079cdf29aa0068e

---

## H. Remaining G0 blockers (severity-ranked)

| Sev | Blocker | Kind | Smallest next action |
| --- | ---: | --- | --- |
| 1 | Square sandbox decline → retry → success on RO #20 | Automation limit for card entry; product path open | Owner runs `04-square-supervised-checklist.md`; agent resumes closeout |
| 1 | Paid closeout + post-close immutability | Blocked on payment | After sandbox success, supported closeout only (no DB force-close) |
| 2 | Fail A test debt (freeze vs forward-only) | Test | Approve setup/split fix; **no** calc change |
| 2 | Fail B query-count harness | Test | Flush ReadCache or spy preload |
| 3 | DEF-G0-04 stale auth amount default | Open from C3 | Separate small fix |
| 3 | RO #20 `closed_at` while unpaid | Data anomaly | Inspect closeout path that set timestamp; do not silent DB clear mid-G0 |
| — | DEF-G0-03 sticky footer | Cleared on current pin | None unless short-desktop human repro returns |
| — | DEF-G0-05 keypad theft | Fixed + staged | Watch during supervised Square entry |
