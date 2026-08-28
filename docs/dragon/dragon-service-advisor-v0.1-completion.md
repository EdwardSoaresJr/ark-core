# Dragon Service Advisor v0.1 — Completion Report

**Date:** 2026-08-21  
**Model:** qwen3:14b (unchanged)

## HEADs

| Repo | Note |
| --- | --- |
| ARK (`arksmsv2`) | `11499f77` — *Add reversible Dragon Service Advisor note editing* |
| Dragon (`arkai:/home/edward/dragon`) | `913f728` — *Add Dragon Service Advisor rewrite skill* (prior `4393816`; dirty Agent Core WIP **not** absorbed) |
| Bridge (`~/dragon-bridge` on arkai) | Multi-capability client deployed + `dragon-assist-bridge` restarted |

## UI placement

Narrative card on RO worksheet → **Dragon Service Advisor** (beside Edit) → workspace modal: field + mode → Generate → ORIGINAL / DRAGON PROPOSAL → Apply · Edit Proposal · Cancel. After apply: **Revert Dragon Rewrite**.

## Context shape

`ServiceAdvisorContextBuilder` payload: RO id/number, estimate_version, vehicle YMM + mileage, concern id/summary, selected field/text, `original_hash`, sibling narrative snippets, mode + instruction, constraints. No phone/email/address/payment.

## Fact-preservation examples

| Case | Result |
| --- | --- |
| Drop `2 mm` / `P0456` | Reject |
| Flip Left front → Right front | Reject |
| Uncertainty → “has failed” + invented “unsafe to drive” | Reject |
| Preserve measurements + DTC + hedges | Pass |

## Apply / Revert proof

Pest `tests/Feature/Dragon/DragonServiceAdvisorTest.php` (9 tests): preview does not mutate; apply writes + audit; revert restores exact original; stale hash → conflict; fact-fail → no Apply; schema blocks prices/parts/mutations.

**Floor live cert** (2026-08-21, draft RO **1711**, concern 1287, Clean Up mode):

| Step | Result |
| --- | --- |
| Generate | `completed` · qwen3:14b · ~31.6s · field unchanged before Apply |
| Apply | exact proposal written · audit `f243896a-…` |
| Revert | exact original restored |
| Cleanup | seeded findings cleared (RO returned to prior empty findings) |

Assist request `f8bd0925-…`. Context build ~29ms. No customer PII in payload path.

## Security checklist

- [x] PII keys excluded from assist payload
- [x] Selected text treated as DATA (prompt + constraints)
- [x] Result schema prohibits prices/parts/labor/mutations/status
- [x] Apply requires `RepairOrdersManage` + open RO + hash/version concurrency
- [x] Dragon complete path never mutates concern fields
- [x] Injection payloads cannot smuggle write fields through schema

## 14B quality judgment

Bridge fake mode is deterministic for CI. Live 14B quality is “good enough for draft” when fact check passes; advisors must still read before Apply. Stronger Explanation mode needs the most human scrutiny.

## Test counts

| Suite | Result |
| --- | --- |
| Pest `DragonServiceAdvisor` | 9 passed |
| Pest `DragonAssistBridge` | green (regression) |
| Bridge `unittest` | 12 passed |

## One next step

~~**Review Estimate Notes** — whole-estimate critique (deferred from v0.1).~~ **Shipped** — see `docs/dragon/review-estimate-notes-v0.1.md`.
