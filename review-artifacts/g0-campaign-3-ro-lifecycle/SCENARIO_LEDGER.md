# Scenario ledger — G0 Campaign 3

Synthetic RO **#20** · alpha.staging · Core `e2aa732` · SaaS `664d8485`

| # | Step | Result | Notes |
| --- | --- | --- | --- |
| 0 | Verify pins + labor #53 Troubleshooting | **Pass** | diagnostic category, $165, concern CEL |
| 1–6 | Estimate construction + customer estimate | **Pass** | Part #54 AFT $94.50; doc #6 $259.50 |
| 7–9 | Present + partial auth | **Pass** | In-person $259.50; Testing Package declined |
| 10–12 | Post-auth add + price increase needs auth | **Pass** | +$82.50 labor; +$25.50 part delta |
| 13 | Unauthorized excluded from invoice | **Pass** | Invoice $259.50 not $367.50 |
| 14 | Decline another line | **Pass** | Testing Package declined |
| 15 | Remove approved line | **Not Run** | Preserved for invoice test |
| 16 | Approval history coherent | **Pass** | scopes + events + estimate doc |
| 17–21 | Invoice generate + reconcile | **Pass** | Invoice #7 issued $259.50 |
| 22 | Open Square keyed sandbox | **Pass** | sandbox.web.squarecdn.com |
| 23–28 | Decline/retry/success/dup | **Blocked** | Cross-origin card entry automation; DEF-G0-05 |
| 29–33 | Closeout + immutability | **Partial** | Unpaid close rejected; paid closeout blocked on payment |
| F | Forward-only settings | **Not Run** | Deferred after payment blocker |
| R | Broader financial/RO regressions | **Partial** | Financial 129/131; ShopFeeFlatDollar 8/8; 2 failures documented |

## Money path verdict

Estimate → partial auth → hostile edits → authorized invoice: **proven**.  
Sandbox payment → paid immutable close: **not proven** (automation blocked; product path open for manual operator).
