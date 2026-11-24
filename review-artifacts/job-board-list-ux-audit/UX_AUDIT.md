# Job Board List View UX Audit

**Baseline (production Core):** `9cc852899b2ab9754185675b7d83d656978b2b33`  
**Branch:** `feat/job-board-list-ux-audit`  
**Status:** STOPPED for owner/Carlos visual review — no merge, staging, production, or SaaS Core-pin.

## 1. Concern / Job blank cells — root cause

**Cause:** List/card projection read only `repair_orders.concern_summary`. On production LugsnPlugs open ROs, that denormalized column is empty for most rows while `repair_order_concerns.summary` holds the real text (observed ~24/25 open ROs with concern rows but empty RO-level summary).

**Not:** Wrong lane projection alone, fabricated notes, or missing RO jobs as the primary blank path.

**Fix (display only):** `RepairOrderConcernText` prefers `concern_summary`, then falls back to loaded `concerns` summaries in position order (compact two-line stack). Search also matches concern-row text. Historical DB values are unchanged.

**Legitimate blanks:** ROs with neither `concern_summary` nor any non-empty `repair_order_concerns.summary` still show `—`.

## 2. Sortable / non-sortable matrix

| Column | Sortable | Default first click | Key | Notes |
|--------|----------|---------------------|-----|-------|
| RO # | Yes | Asc | `ro` | Numeric RO number |
| Customer | Yes | Asc | `customer` | Independent of vehicle; combined cell preserved |
| Vehicle | Yes | Asc | `vehicle` | Independent of customer |
| Concern / Job | No | — | — | No well-defined global order beyond free text |
| Status | Yes | Asc | `status` | Existing lane/status label order |
| Tech | Yes | Asc | `tech` | Alpha; unassigned (`—`) last / predictable empty bucket |
| Total | Yes | Desc | `total` | Numeric cents via `EstimateTotals::totalCents()`, not string |
| Attention | Yes | Desc | `attention` | Semantic severity rank (critical → watch), not label alpha |
| Activity | No | — | — | Multi-signal icons; no single comparable key |
| Updated | Yes | Desc | `updated` | Existing timestamp sort |

Behavior preserved: toggle on re-click; `aria-sort` on active header; server-side sort of full filtered set before pagination; stable id tie-break; search / lane / technician / layout cookie persist.

## 3. Active lane tab styling (final)

Selected tab (`[data-job-board-lane-tabs] .nav-link.active`):

- Filled surface: `color-mix(in srgb, var(--tblr-primary) 10%, var(--tblr-bg-surface))` (dark: 18%)
- Bold body-color text
- Border + inset primary underline (`box-shadow: inset 0 -2px 0 var(--tblr-primary)`)
- Status dots and count badges unchanged
- Inactive tabs remain understated; horizontal overflow retained; `aria-current` preserved

## 4. Header / sort chrome

- Stronger header color + weight; subtle mixed secondary header background; consistent padding
- Sort links with hover/focus-visible; active column emphasized; asc/desc caret state
- Compact density retained; no new toolbar

## 5. Screenshots

Under `review-artifacts/job-board-list-ux-audit/screenshots/`:

| File | Viewport / note |
|------|-----------------|
| `before-prod-1440-list.png` | Production baseline ~1440 — blank Concern/Job `—`, light headers, thin lane underline |
| `after-local-1440-list.png` | Local after — concern text, header/sort chrome |
| `after-local-waiting-approval-total-sort.png` | Active Waiting Approval filled tab + Total descending |
| `after-local-390-mobile.png` | 390×844 — list remains usable; lane tabs scroll horizontally |

Also exercised locally at 1366×900 and 1920×1080 (no new horizontal overflow; List/Cards switcher intact).

## 6. Tests

- `tests/Unit/Operations/RepairOrderConcernTextTest.php`
- Extended `tests/Feature/Operations/JobBoardListViewTest.php` (tech/total/attention sorts, concern fallback, `aria-sort`)

Run: `php artisan test --filter='JobBoardListViewTest|RepairOrderConcernTextTest'`

## 7. Remaining usability notes (for visual review)

- Header contrast is improved but still uppercase muted Tabler; Carlos may want one more step darker.
- Mobile list uses the compact card-row layout (existing), not the desktop table — intentional parity with prior List view.
- RO #85-type rows without concern rows remain blank by design.
- Active Total caret readability at a glance is better when only one column is active; dual caret inactive state is still subtle by design to avoid toolbar clutter.
