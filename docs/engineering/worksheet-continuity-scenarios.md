# Worksheet continuity scenarios

Shipped to LugsNPlugs in `a8dce58e`. Closed unless production shows a concrete regression.

These are the first certification scenarios for a later Playwright suite. Local fixtures only. Do not mutate RO 1734 or RO 1730.

A passing run starts from a new repair order, with no observation hook, and does not reload until the final check.

## Assertion

After a successful mutation, capture the visible authoritative state. Keep intentional unsaved text separate. Reload. The persisted business truth must agree. If the open worksheet and the reload disagree, the run fails.

## Scope protocol

| Mutation | Declared scopes |
| --- | --- |
| Line add, edit, or delete | lines. Add workflow only when the repair order actually changes status. |
| Disposition | authorization, workflow, lines, and settlement |
| Concern create | lines |
| Authorization record or revoke | authorization, workflow, and settlement |
| Settlement (manual cash or check) | settlement. Add workflow only when status actually changes. |

A successful mutation paints from its redirect HTML. It does not follow that with a JSON re-GET, a broad worksheet replacement, `window.location`, or a native `requestSubmit()` fallback.

## RO 1734

Customer Cert Stabilization. 2019 Fixture Sedan, plate CERT2601. Preserve this repair order as the broken fixture from the diagnostic pass.

Observed before the escape-path fix: recommended money stayed at $0 until reload; approval navigated the whole document; the Deferred chip stayed Draft until reload even though the server had saved Deferred; the first labor line did not show the workflow move or the scope count until reload; adding a part cleared unrelated unsaved text.

## RO 1735

Customer Continuity Journey. 2019 Journey Sedan, plate JOUR2602. First complete in-place proof after the escape-path fix.

Held without reload: line add and delete updated cards and the scope count while an unsaved concern composer survived; Recommended updated the chip and estimate money; approval updated authorization, workflow, and owe today on the same page; Deferred painted through the applier; a $10 cash deposit updated settlement only and stayed at $10 on idempotent replay. Reload agreed.

Concern create once returned 409 because the long-lived composer still held an older estimate version. A new concern now submits the current estimate version, the same way a new line does.

## RO 1736

Customer Release Candidate. 2019 Candidate Sedan, plate CAND2603. Fresh run after the visible Edit control, with no observation hook.

Held without reload: concern create, first labor (Draft to Building Estimate), visible Edit to 2.00 hours, part add with unsaved composer text still present, sublet add and delete, Recommended, approval, second concern, and Deferred. Chips, totals ($435.38), owe today ($435.38), and status (Approved) matched the reload.

Failed the reload agreement on one surface during the first pass. Before reload, `#settlement-deposit` was empty, so the advisor could not record a cash deposit. After reload, the same region offered "Customer paid - record deposit" with $93.83 remaining on the suggested deposit. Approval had updated financial position and had not declared the settlement region that holds that form.

Settlement eligibility is derived from authorization. Approved and recommended work can offer a deposit; deferred, declined, and draft work can remove it. Disposition, recording authorization, and revoking authorization now declare settlement as well. Settlement still owns the deposit form and the financial position. The deposit form was not moved.

Continuation on the same repair order, after that declaration: deferring the approved concern removed the deposit form in place and the reload agreed. Setting that concern back to Approved restored the form in place, with $93.83 remaining, owe today $435.38, and the same Waiting Approval status the defer had left. Reload agreed on those. A $10 cash deposit, reference CANDIDATE DEPOSIT, key `e18ba1ff-160c-4366-9243-f8eb80636b6c`, painted owe today $425.38, deposits $10.00, and one history row. Replaying that key did not add a second row. Reload agreed.

The estimate context rail is outside these regions. After the in-place re-approval it still read Authorization $0.00, and the reload read Authorization $435.38.

## RO 1737

Customer Final Candidate. 2019 Final Sedan, plate FINL2604. Zero-to-finish run after the settlement declaration, with no observation hook. One concern, labor and a part. No sublet and no second concern.

Held without reload: suggested concern accepted, first labor moved the repair order to Building Estimate, visible Edit to 2.00 hours, unsaved composer text (`REAR BRAKE DRAFT`) survived a part add, Recommended, Approved, recording authorization, the cash deposit, and the idempotent replay. Setting the concern to Approved painted the deposit form on the same document: $93.83 remaining, owe today $435.38, while status was still Building Estimate. Recording authorization then moved status to Approved in place. The deposit form, suggested amount, and owe today stayed as they were.

A $10 cash deposit, reference FINAL DEPOSIT, key `28b775a9-20d2-4cb3-8302-43c90e1b570d`, painted owe today $425.38, deposits $10.00, suggested remaining $83.83, and one history row. Replaying that key did not add a second row. Reload agreed on the chip, status, totals ($435.38), owe today, deposits, suggested amount, and the single history row. The unsaved composer text was not on the reloaded document.

The estimate context rail stayed "Authorization" with no dollar through the in-place approval and the deposit. The reload read Authorization $435.38.

The rail is a context strip: authorization summary, customer view, parts, and recommendations. Only the authorization button is a continuity region. Authorization and line mutations repaint it. Settlement does not. Customer view, parts, and the authorization panel stay out of that region.

Focused check on this repair order, after that region: deferring the approved concern changed the button to Authorization $0.00 on the same document, and the reload agreed. Setting the concern back to Approved restored Authorization $435.38 on the same document, and the reload agreed. Recording authorization again moved status back to Approved and left the button at Authorization $435.38. The parts button on that strip is not in the region. It stayed on the previous text until reload.
