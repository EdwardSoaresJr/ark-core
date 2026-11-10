# G0 Campaign 1 — Scenario ledger

**Core start pin:** `53e6d88` · **SaaS:** `8dc38a13` · **Env:** staging alpha · **Date:** 2026-11-10

Statuses: Pass | Fail | Blocked | Not Run | Partial

---

## Phase A — Automated financial tests

| Test ID | Preconditions | Expected | Actual | Status | Evidence |
| --- | --- | --- | --- | --- | --- |
| A1 | Tip with retargeted assertions | `BillingClassBlankDiscountTest` blank Military inherits 10%; Class Settings/API path | Pass (financial cents + shared editor + blank JSON item update) | **Pass** | Commit on feature branch (test repair) |
| A2 | Tip with enriched event payload | Class-change event keeps totals `33000→29700` and includes auth diagnostic keys | Pass | **Pass** | Same |
| A3 | Financial suite filter | Broader BillingClass/Labor/ShopFee/Parts/Pricing suite green | **100 passed / 100** | **Pass** | Pest JSON result |

Financial assertions were not weakened.

---

## Phase B — Hostile repair order lifecycle (browser)

Synthetic: customer `GZero CampaignOne` / phone `5550199` / vehicle `2019 Toyota Corolla` / **RO #20**.

| Test ID | Step | Expected | Actual | Status | Evidence |
| --- | --- | --- | --- | --- | --- |
| B01 | Create customer | Synthetic Retail customer | Created via Check In | **Pass** | UI |
| B02 | Create vehicle | Year/Make/Model | 2019 Toyota Corolla | **Pass** | UI |
| B03 | Open RO + diagnostic concern | RO with visit reason | RO #20 Draft; concern accepted from visit reason | **Pass** | [g0-b01](./screenshots/g0-b01-ro20-opened.png) |
| B04 | Add Troubleshooting labor | Labor line @ Troubleshooting rate/minimums | Add Labor control present but browser automation blocked by sticky footer/intercept; Testing Package authorize path disabled until hours filled; incomplete | **Blocked** | UI obstruction |
| B05 | Rate inheritance / minimums | Inherited Retail × Troubleshooting pricing | Not verified on RO (depends on B04) | **Not Run** | — |
| B06 | Add parts via matrix | Parts line priced | Not reached | **Not Run** | — |
| B07 | Generate estimate | Document / totals | Not reached | **Not Run** | — |
| B08 | Partial approval | Only approved amounts billable | Not reached | **Not Run** | — |
| B09 | Add work after approval | New lines require auth as designed | Not reached | **Not Run** | — |
| B10 | Increase approved work without reauth | System blocks / requires reauth | Not reached | **Not Run** | — |
| B11 | Invoice bills approved only | Invoice totals match auth | Not reached | **Not Run** | — |
| B12 | Change billing class | Events + recalc + auth protection | Not reached in browser (covered by A2 automated) | **Partial** | A2 |
| B13 | Decline additional work | Disposition persists | Not reached | **Not Run** | — |
| B14 | Remove approved line | Auth/history consistent | Not reached | **Not Run** | — |
| B15 | Regenerate customer documents | Snapshots refresh intentionally | Not reached | **Not Run** | — |
| B16 | Square sandbox payment | Payment posts | Take payment UI shows **“Card processing is temporarily unavailable.”** | **Blocked** | RO #20 payment rail |
| B17 | Close RO | Closed immutability | Not reached | **Not Run** | — |
| B18 | Post-close financial edit | Rejected | Not reached | **Not Run** | — |
| B19 | Historical totals/docs | Unchanged after close | Not reached | **Not Run** | — |

---

## Phase C — Settings forward-only

| Test ID | Change | Expected | Actual | Status | Evidence |
| --- | --- | --- | --- | --- | --- |
| C1 | Labor Matrix rate change with open RO | Old RO keeps stamped labor profile; new RO uses new rates | Not executed in this campaign (depends on completed RO lines) | **Not Run** | — |
| C2 | Billing Class discount change | Forward-only commercial stamp | Covered for blank Military in A1; browser RO not stamped with lines yet | **Partial** | A1 |
| C3 | Parts matrix change | Forward-only | Not Run | **Not Run** | — |
| C4 | Shop Fee settings change | Living fee profile stamp; documents should not silently rewrite without intent | Documented residual: `syncOpenEstimateDocuments()` still called from Shop Fees save | **Fail (known open finding)** | `docs/engineering/SHOP_FEES_OPEN_ESTIMATE_SYNC_180_NOTE.md` |
| C5 | Tax settings change | Forward-only | Not Run | **Not Run** | — |
| C6 | Add new work to old RO / create new RO | Old vs new profile divergence | Not Run | **Not Run** | — |

No financial code fix applied for C4 in this campaign (requires owner approval).

---

## Phase D — Mobile Labor

| Test ID | Expected | Actual | Status | Evidence |
| --- | --- | --- | --- | --- |
| D1 | Work Type selection usable | Vertical list; Troubleshooting selectable | **Pass** | d01/d02 |
| D2 | Billing-policy rows + rate selection | Visible; dropdowns usable (labels truncate on narrow width) | **Pass** | d02 |
| D3 | Rules modal | Opens; Cancel/Save present; scope note present | **Pass** | d03 |
| D4 | Class Settings | Identity/Pricing/Defaults/Documents groups | **Pass** | d04 |
| D5 | Manage Billing Classes | Reachable; opens commercial editor | **Pass** | d05 |
| D6 | Validation error visible | Blank name → required error; Cancel restores | **Pass** | d06 |
| D7 | Save success feedback | Request-backed Saving…/saved not captured as still | **Partial** | — |
| D8 | No page-level horizontal overflow | `scrollWidth === clientWidth` (390) | **Pass** | CDP check |

---

## Defects (ranked)

| Sev | ID | Summary | Impact | Notes |
| --- | --- | --- | --- | --- |
| High | DEF-G0-01 | Shop Fees save may refresh open estimate **documents** without explicit advisor document action | Document content can change without authorization ceremony | Pre-existing #180 note; do not silently “fix” without approval |
| High | DEF-G0-02 | Alpha Take payment: “Card processing is temporarily unavailable.” | Blocks G0 payment/closeout proof on staging | Environment/config dependency |
| Medium | DEF-G0-03 | RO sticky footer intercepts concern-level Add Labor clicks in automation / dense layouts | Usability risk for advisors on short viewports; blocked automated hostile path | Observe manually next campaign |
| Low | DEF-G0-04 | Mobile rate-mode labels truncate (“Use Defa…”, “Custom I…”) | Readability only; control still works | Acceptable for G0 polish unless owner objects |

No GitHub issues filed from this agent session (report-only).

---

## G0 verdict

**BLOCKED** for limited production adoption.

Reasons: incomplete hostile RO money path; Square sandbox unavailable; Shop Fees document-sync finding still open.

What did pass: Phase A financial automation after honest test retargeting; Check In → RO creation; mobile Labor presentation + validation.

---

## Recommended next campaign

**G0 Campaign 2 — Complete RO money path (manual-assisted if needed)**

1. Advisor-operated completion of RO #20 (or fresh synthetic RO): Troubleshooting labor, parts, estimate, partial auth, hostile edits.
2. Restore/verify Square sandbox on alpha; complete payment + close + post-close rejection.
3. Explicit forward-only settings drill with stamped profiles visible in History/totals.
4. Owner decision on DEF-G0-01 (Shop Fees document sync) before any production cutover rehearsal.
