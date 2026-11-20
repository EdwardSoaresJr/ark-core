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

## 2. Statement email catalog (Shop #29 + Stancl #310)

| Item | Detail |
| --- | --- |
| ark-shop PR | https://github.com/EdwardSoaresJr/ark-shop/pull/29 (`bf1074e9`) — hosted overlay lineage |
| Stancl PR | https://github.com/EdwardSoaresJr/ark-stancl/pull/310 — **required for LugsNPlugs** |
| Core dependency | `CustomerAccountStatementEmailDelivery` already sends `statement.send` with matching variables |
| Gate | Merge Stancl #310 (and #29 if needed), then prove live mail on staging before production |

## 3. Platform `/payments/refunds` availability

| Item | Detail |
| --- | --- |
| Core support | `ArkPaymentsClient::createRefund` / `refundStatus` + settle action on #226 |
| Platform truth (2026-11-20) | **No refund routes** in `ark-cloud`; saved cards never advertised |
| Gate | Keep processor refunds unsupported until Platform implements verified settlement |
| Rule | Pending/accepted/initiated must leave AR liability open |

See `INTEGRATION_RELEASE_CANDIDATE.md` for ship-now vs disabled scope and Stancl blast radius.

## 4. Molly / #227

Stancl production release #227 remains independent. Do not couple AR acceptance, merge, or deploy decisions to that release.
