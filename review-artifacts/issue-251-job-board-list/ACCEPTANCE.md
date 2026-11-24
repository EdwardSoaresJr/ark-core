# Issue #251 / PR #252 — RO index owner layout refinement

Stop for Carlos/owner visual inspection. No merge. No deploy. No SaaS pin. No production data changes.

Implements the presentation direction in `docs/product/RO_INDEX_OWNER_PROPOSAL_2026-11-23.md` (`f607227be9b4771247e17332f1853024431df786`) against prior SHA `c7310431`.

## Commit

Recorded on push (see PR comment for exact SHA).

## Desktop column layout

| Column | Present |
| --- | --- |
| RO # | Yes → RO |
| Customer / Vehicle | Combined two-line cell; customer → customer hub; vehicle → RO |
| Concern / Job | Full `concern_summary`, 2-line clamp, tooltip |
| Status | Yes (incl. Waiting Parts) |
| Tech | Yes |
| Total | Yes |
| Attention | Yes (Watch muted; attention/critical prominent) |
| Activity | Yes (compact active marks) |
| Updated | Yes |
| **Promised** | **Absent from list UI** (DB/commitments unchanged) |
| **Parts** | **Absent from list UI** (procurement/RO detail unchanged) |

Phone numbers are not shown in the combined cell (avoids overcrowding); available via customer/RO navigation.

## Layout verification (Playwright screenshots)

| Viewport | Result |
| --- | --- |
| **1366×900** | Overflow **0**. Promised/Parts headers absent. Combined customer/vehicle + wider concern. `screenshots/list-desktop-1366.png` |
| **1440×900** | Comfortable spacing; overflow 0. `screenshots/list-desktop-1440.png` |
| **1920×1080** | Concern uses reclaimed width naturally; overflow 0. `screenshots/list-desktop-1920.png` |
| **390×844** | Existing compact mobile cards retained (not desktop table). `screenshots/list-mobile-390.png` |

Local login: `jobboard-review-251@example.test` / `Review251!`. Reseed SYN rows: `php review-artifacts/issue-251-job-board-list/seed-syn.php`.

## Focused tests

`JobBoardListViewTest` + `AdvisorHomeAttentionBoardTest` — **22 passed**.

Covered: combined customer/vehicle markup, absent promised/parts cells, click contract, concern clamp, Watch tone, SYN acceptance totals/status.

## Underlying fields preserved

- Operational commitments / promised dates still stored and usable in RO detail / attention signals.
- Parts procurement states and Waiting Parts status unchanged.
- No migrations; no billing/authorization changes.

## Owner review notes (not fixed in this slice)

1. Default **Watch** still appears on many idle rows — proposal asked to document ambiguous Watch rather than rewrite the classifier here.
2. Existing attention can show **Promise overdue** or **Needs parts** even without Promised/Parts columns; that is residual classifier output, not new business rules.
3. Mobile cards still emphasize vehicle + next action; full concern text remains desktop-first.

## Still out of scope

No merge, deploy, SaaS pin, production data, Sites freeze change, or Attention rule rewrite.
