# Forward-only settings drill

**Status: Not Run**

Deferred because the primary money-path gate (sandbox payment → paid closeout) is blocked on Square card-entry automation. Independent of payment, this drill remains testable on open/new synthetic ROs and should be the first follow-up after manual sandbox payment or keypad fix deploy.

Intended sequence (unchanged from campaign brief):

1. Snapshot effective profiles on open RO + new RO.
2. Change via staging UI: labor rate, Billing Class commercial rule, Shop Fee, Parts Matrix, Tax rule.
3. Compare existing RO (original stamps / documents) vs new RO (updated config) vs explicit advisor reprice.
4. Restore synthetic config via UI with evidence.
