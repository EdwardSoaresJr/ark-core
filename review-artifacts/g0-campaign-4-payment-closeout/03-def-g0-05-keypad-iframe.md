# DEF-G0-05 — Payment keypad vs Square iframe

## Do not conflate

| # | Hypothesis | Verdict |
| --- | --- | --- |
| 1 | ARK amount keypad captures keystrokes intended for Square | **Confirmed ARK defect** when focus is on/near the Square iframe and `@keydown.window` still runs |
| 2 | Browser automation cannot interact with cross-origin iframe | **Confirmed automation limitation** (separate from #1) |
| 3 | Actual payment form usability problem for humans | **Not proven as a human UX blocker**; C3 showed Square form visible; keypad theft is the ARK-side interference |
| 4 | Combination | **Yes for automated C3 path:** #1 + #2 stacked. Human path needs re-proof after #1 fix |

## Required behavior vs fix

- Keypad shortcuts run only while the payment workspace is open and focus is not foreign (inputs/select/textarea other than the amount field, contenteditable, or any `IFRAME`).
- No card PAN/CVV through ARK application code (unchanged; Square.js tokenization only).
- No iframe security or validation disabled.
- Mouse/keyboard still work without the automation harness.

## Code change

- Export `shouldIgnorePaymentAmountShortcut(event, options?)` in `resources/js/ark-payment-capture.js`.
- `pressAmountKey` returns early when that helper says ignore.
- Focused node test covers iframe, foreign input, contenteditable, amount field, and body chrome.

## Staging

- Core commit `e9b4268` pushed on `settings/labor-matrix-billing-minimums`.
- SaaS pin branch `pin/core-e9b4268-g0-campaign-4` @ `c60f2c37` deployed via staging `deploy/deploy.sh` (backup-gated).
- Built assets on host include the IFRAME guard.

## Browser verification (post-deploy)

- Payment modal opens; Square sandbox iframe `cardReady=true`.
- With `activeElement === IFRAME`, digit keydown leaves amount at `259.50`.
- Amount-field and body chrome still drive the keypad; foreign `<input>` ignored.
- Cross-origin PAN entry remains an **automation limitation** (see `04-square-supervised-checklist.md`).
