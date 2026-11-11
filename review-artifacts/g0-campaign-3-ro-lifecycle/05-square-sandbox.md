# Square sandbox (RO #20)

## Open keyed capture — Pass

- Take payment workspace opened from Estimate Total rail (Closeout tab hides the payment panel — use Estimate Total).
- Method: **Keyed card**
- Amount: **$259.50** (invoice balance)
- Square sandbox iframes present (`sandbox.web.squarecdn.com` single-card element)
- Screenshot: `screenshots/05-square-keyed-visible.png`

## Decline / success / retry / duplicate — Blocked (automation)

Cross-origin Square card iframes could not be reliably filled from the IDE browser automation layer:

1. Window `pressAmountKey` stole digits into the amount field when focus was in the Square iframe (**DEF-G0-05**).
2. After an in-session patch of that listener, System Events / bulk key injection still landed in the wrong Square sub-fields (partial PAN / wrong expiry). Observed validation: “Credit card number is not valid” / “Enter a valid card number.”
3. CDP `Input.*` and Computer Use against Cursor are disallowed for this surface.

| Step | Status |
| --- | --- |
| 22 Open Square sandbox card-capture | **Pass** |
| 23 Declined sandbox transaction | **Blocked** |
| 24 Retry fresh payment page | **Blocked** (depends on 23) |
| 25 Successful sandbox payment | **Blocked** |
| 26 Recorded payment + balance | **Not Run** |
| 27 Duplicate webhook/callback | **Not Run** |
| 28 No duplicate payment | **Not Run** |

No live Square credentials or real cards used. No DB forge of payment success. Ledger remains empty (`0` entries). RO `payment_status=unpaid`.

## Follow-up for owner

Manual operator completion on staging with keypad fix deployed: decline card `4000 0000 0000 0002`, then success `4111 1111 1111 1111`, then replay webhook if a supported test hook exists.
