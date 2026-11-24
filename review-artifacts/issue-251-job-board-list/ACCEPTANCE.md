# Issue #251 / PR #252 — Final RO index corrections

Stop for Carlos/owner visual acceptance. No merge. No deploy. No SaaS pin. No production data changes.

Prior layout SHA: `e65c211d92535ad57b1f5f36dd4a9f4d3e8271eb`.

## Commit

Recorded on push (see PR comment for exact SHA).

## Corrections in this pass

### 1. Customer / Vehicle header markup

Header uses a **single** `class="ark-job-board-col-customer-vehicle"` on the `<th>`, plus `data-job-board-col="customer-vehicle"`. Guarded by test `job board customer vehicle header has a single class attribute`.

### 2. Vehicle sorting preserved

Combined column exposes two compact server-side sort links (existing `JobBoardListPage` / builder):

- **Customer** → `sort=customer`
- **Vehicle** → `sort=vehicle`

`aria-sort` reflects whichever is active; `aria-current="true"` on the active link. No new sorting system.

### 3. Unchanged from prior refinement

- Promised / Parts absent from list UI; storage and RO workflows intact
- Combined Customer / Vehicle cell; Concern 2-line clamp + tooltip
- Watch muted; Attention / Critical prominent
- Activity + Updated retained
- Mobile cards unchanged

## Layout verification

| Viewport | Result |
| --- | --- |
| **1366×900** | Overflow **0**. `screenshots/list-desktop-1366.png` |
| **1440×900** | Clean spacing. `screenshots/list-desktop-1440.png` |
| **1920×1080** | Concern uses width naturally. `screenshots/list-desktop-1920.png` |
| **390×844** | Compact mobile cards. `screenshots/list-mobile-390.png` |

Local: `jobboard-review-251@example.test` / `Review251!`. Reseed: `php review-artifacts/issue-251-job-board-list/seed-syn.php`.

## Focused tests

`JobBoardListViewTest` + `AdvisorHomeAttentionBoardTest`:

- **passed:** 23
- **failed:** 0
- **skipped:** 0

## Remaining owner/Carlos acceptance items (not implemented)

1. Excessive default **Watch** on otherwise idle ROs (classifier unchanged).
2. **Promise overdue** Attention for shops that rarely use promised times.
3. **Parts-needed** Attention vs **Waiting Parts** status semantics.

These need a separate Attention-rules review — out of scope here.

## Still out of scope

No merge, deploy, SaaS pin, production/staging DB changes, Sites release change, or Attention rule rewrite.
