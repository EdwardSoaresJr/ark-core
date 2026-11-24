# Issue #251 / PR #252 — table-first Job Board acceptance

Stop for Carlos/owner visual inspection of the live `/app` Job Board. No merge. No deploy. No SaaS pin.

Direction from review [#7120220723](https://github.com/EdwardSoaresJr/ark-core/pull/252#issuecomment-7120220723): code direction accepted; **visual acceptance not yet granted**.

## Commit

Recorded on push (see PR comment for exact SHA).

## Layout verification (local browser)

| Viewport | Result |
| --- | --- |
| **1366×900** | All 13 columns present. Idle activity marks collapse to `—` (no six-icon idle row). Table width ≈ wrap +13px; `.table-responsive` absorbs residual. Sticky chrome stays `position: sticky`. Screenshot: `screenshots/list-desktop-1366.png` |
| **1920×1080** | No horizontal overflow (`tableW === wrapW`). Phone numbers visible under customer. Screenshot: `screenshots/list-desktop-1920.png` |
| **390×844** | Desktop table hidden; compact mobile rows with RO / total / status / vehicle / customer / next action / updated. Screenshot: `screenshots/list-mobile-390.png` |

## Workflow preservation

Card activity marks were **never clickable** (informational tooltips only). After row drill-through to RO #85:

- Message link present
- Customer phone / email links present
- Communications tab present
- Inspection / Add Photo present

Matrix: `ACTION_PRESERVATION.md`. Drill-through screenshot: `screenshots/list-drillthrough.png`.

## Scale note (honest)

List still hydrates eligible ROs then projects page 50. `PERF_LIST.json` (120 RO / ~38 queries / ~121 ms) is synthetic evidence, **not** a high-volume DB pagination guarantee. Keep a separate scale gate before broad rollout.

## CI (head SHA — recorded on push)

| Workflow | Result |
| --- | --- |
| **Tests** | success (lint, security, focused, targeted, communications-boundary) |
| **Secret scan** | success |
| **Regression** | skipped (expected; not a green regression suite) |

## Focused local tests

`JobBoardListViewTest` + Job Board cases in `WorkboardTriageTest` — **27 passing**.

## Still out of scope

No merge, staging/production deploy, SaaS pin, ARK Sites, or production data changes. PR stays **draft**.
