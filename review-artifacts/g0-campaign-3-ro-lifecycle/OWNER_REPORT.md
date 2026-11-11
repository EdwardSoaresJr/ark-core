# G0 Campaign 3 — Owner stop report

Staging-only. Production unauthorized. No merge to main.

## A. RO lifecycle

Synthetic **RO #20** advanced through estimate construction, partial in-person auth, hostile post-auth edits, Ready for Pickup, and **final invoice issue**.

| Phase | Result |
| --- | --- |
| Labor #53 Troubleshooting verify | Pass |
| Parts + estimate doc #6 | Pass ($259.50) |
| Partial auth + decline package | Pass |
| Post-auth labor + approved-part price bump need re-auth | Pass (+$108) |
| Invoice #7 | Pass ($259.50 authorized only) |
| Square decline/success | **Blocked** (card iframe automation) |
| Paid closeout + immutability | **Partial** — unpaid close rejected; paid close not reached |

**Final RO state:** `ready_pickup` / `unpaid` · Invoice issued $259.50 · Auth $259.50 · Unauthorized on estimate +$108.00 · Ledger empty.

## B. Financial reconciliation

| Axis | Expected | Actual |
| --- | --- | --- |
| Approved | $259.50 | $259.50 |
| Invoiced | $259.50 | $259.50 (labor $165 + parts $94.50 approved; not live $120) |
| Unauthorized excluded | $108.00 | Excluded from invoice |
| Paid | $0.00 | $0.00 |
| Remaining | $259.50 | $259.50 settlement balance |

## C. Square sandbox

Form opens on sandbox Web Payments (**Pass**). Decline / retry / success / duplicate protection **Blocked** by cross-origin card entry + DEF-G0-05 keypad theft. No payment forged in DB.

## D. Forward-only settings

**Not Run** — deferred after payment blocker.

## E. Automated regressions

- Financial suite: **129/131** passed (2 failures detailed in `09-test-results.md`).
- ShopFeeFlatDollar: **8/8** passed.
- Failures look fee-recalc / presenter query-count related; not caused by the local keypad patch. Assertions not weakened.

## F. Defects

See `08-defect-register.md`: DEF-G0-03 residual, DEF-G0-04 auth amount stale default, DEF-G0-05 Square keypad steal (local fix prepared, not deployed).

## G. G0 gate

**BLOCKED**

Money path is proven through **authorized invoice**. Gate cannot PASS without a recorded sandbox payment and immutable paid closeout.

## H. Next action (smallest)

1. Deploy or hot-serve the DEF-G0-05 keypad iframe ignore (reviewable JS-only) onto staging **or** manually key Square sandbox cards on RO #20.
2. Complete decline → fresh retry → success $259.50 → verify ledger/balance → `closed:paid` → attempt post-close edit rejection.
3. Then run forward-only settings drill + triage the 2 Financial suite failures.
