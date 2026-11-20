# AR #224 owner acceptance results — scenarios 1–4

**Date:** 2026-11-20  
**Product tip:** `ddc95027dbb698a18ad1557a2ded267391c7e6c4`  
**Branch tip (docs/seed):** `798d91ed` + seed reset fix  
**Host used for UI:** `https://ark-ar-224.test` (Herd link to `ark-core-ar-224` worktree)  
**Note:** `https://ark-core.test` still points at main `ark-core` and does **not** serve #226 routes.

Production untouched. #226 unmerged. #224 open. #227 separate.

## Checklist

| # | Item | Result | Evidence |
| --- | --- | --- | --- |
| 1 | AR list and outstanding balances match | **PASS** | UI: Total outstanding `$1,325.00`, 5 accounts; Multi `$350`, Credit `$180`, Refund `$100`, Statement `$395`, Card `$300` |
| 2 | Multi-invoice checks allocate correctly | **PASS** | Runner: `$200` → `$150` remaining; `$150` → `$0` |
| 3 | Store credit applies without a new charge | **PASS** | Runner: `$50` credit → due `$130`; `$150` check keep-credit → credit `$20` |
| 4 | Pending manual refund records proof and clears | **PASS** | Runner: `$130` check → pending `$30`; confirm `CHK-RETURNED-ACCEPT-1` → change `$30`, pending `$0` |
| 5 | Statements and PDFs display correct amounts | **PASS (preview)** | UI statement `$395.00` / `$220` + `$175`; Print/PDF/Email actions present. Live email not exercised (Shop #29 / Platform mail) |
| 6 | Keyed card / portal match Platform readiness | **DEFERRED / OK disabled** | Receive Payment shows keyed/terminal not enabled + saved-card readiness blocker. Appropriate when Platform disconnected |
| 7 | Processor refunds stay pending unless verified | **DEFERRED** | Not run live; prior automated coverage remains. Requires Platform `/payments/refunds` |

## Scenario runner

```bash
php scripts/seed-ar-224-acceptance.php
php scripts/run-ar-224-acceptance-scenarios.php
# RESULT: ALL SCENARIOS 1–4 PASSED
```

## Advisor usability observations

**Works without an accounting lesson**
- Financials → Accounts Receivable is obvious in nav.
- Outstanding totals, aging, and per-customer balances are immediately readable.
- Receive Payment: amount + method + per-invoice allocate columns; Record stays disabled until allocation > 0.
- Overpayment choices use plain language (“Keep as account credit” vs “Pending refund / return … Not treated as returned yet”).
- Store credit shows on the customer account header (`Store credit $50.00`).
- Payment receipts section exists so staff can see what happened after the fact (once recorded).

**Friction to fix before calling UX “done” (not blocking money-math)**
- Every synthetic invoice labels as **INV #1**. Advisors must use RO # to tell them apart. Real shops may already sequence document numbers; acceptance seed exposed the ambiguity.
- Auto-allocate happens when payment amount is entered (good), but empty checkboxes look idle until that amount is typed — a one-line hint would help first-time use.

## Platform-dependent items

Do not spend merge-decision time here until 1–4 are owner-signed:

1. Shop #29 for live `statement.send`.
2. Platform readiness for keyed/portal capture.
3. Platform `/payments/refunds` for processor settle.

## Owner decision (fill in)

- [ ] **ACCEPT for merge consideration** — scenarios 1–4 money path + UI are good enough
- [ ] **CHANGES REQUIRED** — especially INV labeling / allocate discoverability
- [ ] **HOLD**
