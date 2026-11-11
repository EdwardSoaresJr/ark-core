# Defect register — Campaign 3

| ID | Severity | Status | Summary | Financial impact |
| --- | --- | --- | --- | --- |
| DEF-G0-03 | Low/UI | Open (residual) | Sticky RO footer intercepts Add/Edit Part clicks | None; workflow friction |
| DEF-G0-04 | Medium | Open | Auth amount defaults to stale worksheet total after part add | Mis-recorded auth ceiling if advisor ignores |
| DEF-G0-05 | Medium/UI | Fix prepared (local, unreviewed) | `pressAmountKey` window listener steals digits while Square keyed iframe focused | Blocks advisor keyboard entry into Square card fields; amount corrupted |

## DEF-G0-05 detail

- File: `resources/js/ark-payment-capture.js` `pressAmountKey`
- Repro: Open Take payment → Keyed card → focus Square card number → type digits → amount field grows; card incomplete.
- Fix (local worktree only, not deployed): ignore key events when `method === 'keyed'` and `document.activeElement` is `IFRAME`; also treat `IFRAME` like other non-amount targets.
- Does **not** change financial calculation, authorization, or payment recording logic.
- Requires staging asset deploy for retest — **not deployed** (production/staging cutover unauthorized in this campaign beyond existing pins).
