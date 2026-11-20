# AR #224 follow-ups (outside PR #226 merge scope)

Do not fold these into Accounts Receivable feature work on #226.

## 1. Overlapping estimate-writer MySQL concurrency failure

| Item | Detail |
| --- | --- |
| Test | `tests/Integration/MySql/ConcurrencyOverlapTest.php` → `two overlapping writers cannot both mutate from the same estimate version` |
| Helper | `tests/Support/repair_order_concurrency_overlap.php` |
| Observed | `loser.status` is `null` (expected `409`); warning: array offset on null |
| Related AR overlaps | Store-credit and same-key deposit/payment overlaps **pass** under disposable MySQL |
| Disposition | Track as a separate RO concurrency defect. Do not repair it inside #226. |

Suggested next step when prioritized: reproduce the helper’s child-process JSON under disposable MySQL, confirm whether the loser path throws before emitting status, and open a dedicated Core issue/PR.

## 2. Shop #29 — live statement email catalog

| Item | Detail |
| --- | --- |
| PR | https://github.com/EdwardSoaresJr/ark-saas/pull/29 |
| Status | Open / mergeable at handoff |
| Core dependency | `CustomerAccountStatementEmailDelivery` already sends `statement.send` with Shop #29 variables |
| Gate | Merge Shop #29 and validate Platform mail before calling statement email “live” |

## 3. Platform `/payments/refunds` availability

| Item | Detail |
| --- | --- |
| Core support | `ArkPaymentsClient::createRefund` / `refundStatus` + settle action on #226 |
| Gate | Confirm the live Platform endpoint and provider refund settlement before treating processor refund UI as production-ready |
| Rule | Pending/accepted/initiated must leave AR liability open |

## 4. Molly / #227

Stancl production release #227 remains independent. Do not couple AR acceptance, merge, or deploy decisions to that release.
