# ARK Accounts Receivable (#224) — Owner acceptance

**Stop here for owner acceptance testing. Do not merge PR #226. Do not deploy. Production stays untouched.**

| Item | Value |
| --- | --- |
| Core tip | `ddc95027dbb698a18ad1557a2ded267391c7e6c4` |
| Core PR | https://github.com/EdwardSoaresJr/ark-core/pull/226 |
| Issue | https://github.com/EdwardSoaresJr/ark-core/issues/224 (keep open) |
| Shop statement catalog | https://github.com/EdwardSoaresJr/ark-saas/pull/29 (open; required for live email) |
| Local app (this worktree) | `https://ark-ar-224.test` |
| Molly / #227 | Independent — do not mix into this acceptance |

Herd: link this worktree as `ark-ar-224` (`herd link ark-ar-224` from `ark-core-ar-224`). Do **not** use `https://ark-core.test` for #226 — that host still serves main and returns 404 for AR routes.

## Environment prep (local Herd only)

From the AR worktree (`ark-core-ar-224`):

```bash
git checkout feature/accounts-receivable-224
git rev-parse HEAD   # expect ddc95027… or later acceptance-only commits
php artisan migrate --force
php scripts/seed-ar-224-acceptance.php
# optional money-path smoke (no browser):
php scripts/run-ar-224-acceptance-scenarios.php
```

The seed refuses non-local hosts and production-like database names. Re-running resets prior `AR224-ACCEPT` ROs/receipts for the five synthetic customers, then recreates them.

Staff: sign in as an advisor/admin that can open Financials. Seed actor email is printed by the script (`ar-accept-advisor@example.test` when created).

## Synthetic customers (after seed)

| Key | Customer | Outstanding | Purpose |
| --- | --- | --- | --- |
| MULTI | AR Multi Invoice | $350.00 | Multi-invoice allocation |
| CREDIT | AR Credit Retain | $180.00 + $50 credit | Store credit apply + keep-credit overpay |
| REFUND | AR Refund Pending | $100.00 | Pending refund / manual confirm |
| STATEMENT | AR Statement Fleet | $395.00 | Statement preview / PDF / email |
| CARD | AR Card Keyed | $300.00 | Keyed / portal card (needs Platform) |

Index: `/app/financials/accounts-receivable`  
RO concern summaries are marked `AR224-ACCEPT`.

## Practical test script

After every money step: record the expected amount, then verify the customer AR page, invoice balances, receipt totals (`amount = allocated + unapplied + change + refund_pending`), and ledger lines.

### 1. Lifecycle baseline (create → on account → AR list)

1. Open Financials → Accounts Receivable.
2. Confirm the five synthetic customers appear with the outstanding totals above.
3. Open **AR Multi Invoice**. Confirm three open invoices ($150 / $120 / $80).
4. Optional UI create path (if you prefer not using seed for one ticket): new RO → approve labor → final invoice → close **on account** → customer appears on AR.

### 2. Multi-invoice payment (partial, then remainder)

On **AR Multi Invoice** → Receive Payment:

1. Method **Check**, amount `$200.00`, allocate `$150` + `$50` across the first two invoices (leave oil unpaid).
2. Record payment. Expect:
   - Front brakes paid in full
   - Alignment remaining `$70`
   - Oil still `$80`
   - Account outstanding `$150`
3. Receive a second check for `$150.00` that clears the remainder. Expect outstanding `$0` and no leftover credit unless you overpay.

### 3. Retained account credit

On **AR Credit Retain** → Receive Payment:

1. Confirm method **Store credit** shows ~`$50.00` available.
2. Apply `$50.00` to the `$180` invoice. Expect balance `$130`, credit `$0`, **no new charge**.
3. Receive a **Check** for `$150.00` with disposition **Keep credit**. Expect invoice paid, account credit `$20.00`.
4. Confirm the credit appears on the customer AR page and can be applied later.

### 4. Pending refund / manual return

On **AR Refund Pending** → Receive Payment:

1. Method **Check**, amount `$130.00`, allocate `$100` to the invoice, disposition **Refund**.
2. Expect receipt: allocated `$100`, `refund_pending` `$30`, change `$0`, credit `$0`.
3. On the account page, confirm **Confirm manual return** (not Settle processor refund).
4. Enter proof `CHK-RETURNED-ACCEPT-1` and confirm. Expect pending `$0`, change `$30`, evidence + actor + timestamp recorded.
5. Negative check: a **Card** pending refund must **not** clear from a typed reference (use CARD customer only if you create a card pending path).

### 5. Statement preview / PDF / email

On **AR Statement Fleet**:

1. Open Statement — both invoices and `$395.00` due.
2. Download PDF / print preview — amounts match.
3. Address label — synthetic Denver address only.
4. Email statement:
   - Without Shop #29 + Platform mail: expect a clear configuration failure, not a silent send.
   - With Shop #29 merged and Platform mail connected: email goes only to `ar224.statement@example.test` with operation `statement.send` and variables `shop_name`, `customer_name`, `balance_due`, `as_of`.

### 6. Keyed card / portal (Platform-dependent)

On **AR Card Keyed**, only when Platform readiness shows keyed capture:

1. Receive Payment → **Card (keyed)** — Square fields mount; no stub token in production HTML.
2. Charge both `$150` invoices in one capture. Expect one receipt, both balances `$0`.
3. Portal account pay (if portal pay enabled): same customer, tokenize, pay selected invoices.
4. If Platform is disconnected: card options stay hidden/disabled; do not force stubs.

### 7. Processor refund settle (Platform-dependent)

Only when Platform `/payments/refunds` is live:

1. Create a card receipt with pending refund liability.
2. **Settle processor refund** clears pending only after final success + processor refund id.
3. Pending / accepted / initiated responses leave liability open.
4. Second settle is a no-op (no duplicate refund).

If Platform refunds are not live: document “blocked on Platform” — do not invent a Core bypass.

## Pass / fail recording

| Step | Pass? | Observed amounts / notes |
| --- | --- | --- |
| 1 AR list + on-account | | |
| 2 Multi-invoice | | |
| 3 Store credit | | |
| 4 Pending refund | | |
| 5 Statement | | |
| 6 Keyed / portal | | |
| 7 Processor refund | | |

## Owner decision

- [ ] **ACCEPT for merge consideration** — financial lifecycle evidence is sufficient; merge still requires explicit authorization and the integration dependencies below
- [ ] **CHANGES REQUIRED** — list defects with customer/invoice/receipt ids
- [ ] **HOLD** — leave #226 open; no merge

## Integration dependencies (not Core merge blockers for code review, but live gates)

1. Merge/validate Shop #29 for `statement.send`.
2. Confirm Platform live `/payments/refunds` before treating processor refund UI as production-ready.
3. Track the unrelated overlapping estimate-writer MySQL failure separately — see [FOLLOWUPS.md](./FOLLOWUPS.md).
