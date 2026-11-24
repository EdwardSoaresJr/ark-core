# Issue #251 / PR #252 — Job Board refinement acceptance

Stop for Carlos/owner visual inspection. No merge. No deploy. No SaaS pin. No production data changes.

Addresses review [#7120427094](https://github.com/EdwardSoaresJr/ark-core/pull/252#issuecomment-7120427094) on SHA `1f86111d`.

## Commit

Recorded on push (see PR comment for exact SHA).

## Refinement checklist

| Item | Result |
| --- | --- |
| **Concern / Job readability** | List uses full `concern_summary` with CSS 2-line clamp (`ark-job-board-concern-text`); tooltip has full text. Not truncated by card headline limit. |
| **Routine Watch muted** | `data-attention-tone="watch"` → `text-secondary` + normal weight + reduced opacity. `attention` / `critical` stay warning/danger + full opacity. |
| **Synthetic ROs** | `Tests\Support\JobBoardAcceptanceScenario` seeds SYN-* customers only (purge/reseed safe). Techs Alex Rivera / Jordan Lee, promises (incl. overdue), parts badges, totals `$120` / `$425` / `$505` / `$217` / `$1,066`. |
| **Click contract** | RO # → RO builder; Customer → customer hub; Vehicle → RO builder; Concern is plain text (no link). |

## Layout verification (local browser)

| Viewport | Result |
| --- | --- |
| **1366×900** | Table-first board; concern 2-line clamp; SYN rows visible. Screenshot: `screenshots/list-desktop-1366.png` |
| **1440×900** | Same; mid desktop. Screenshot: `screenshots/list-desktop-1440.png` |
| **1920×1080** | Full concern lines often fit; phones under customer; no wrap overflow. Screenshot: `screenshots/list-desktop-1920.png` |
| **390×844** | Compact mobile cards (prior work unchanged). Screenshot: `screenshots/list-mobile-390.png` |

Local login for inspection: `jobboard-review-251@example.test` / `Review251!` (synthetic only). Reseed: `php review-artifacts/issue-251-job-board-list/seed-syn.php`.

## Focused tests

`JobBoardListViewTest` — concern clamp, Watch tone, click contract, acceptance scenario (**4 new / 14 total passing**).

## Still out of scope

No merge, staging/production deploy, SaaS pin, or production data mutation. PR stays **draft**.
