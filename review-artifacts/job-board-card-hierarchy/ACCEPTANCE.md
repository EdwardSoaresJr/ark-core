# Job Board UX finalization — visual acceptance

**Date:** 2026-11-24  
**Stopped for owner/Carlos visual acceptance.** No merge, staging, production, or SaaS Core-pin.

## 1. Exact revisions

| Role | Ref | SHA |
|------|-----|-----|
| Production Core baseline / `origin/main` | main | `9cc852899b2ab9754185675b7d83d656978b2b33` |
| PR #254 List UX (OPEN, independent) | `feat/job-board-list-ux-audit` | `f10b70f5d42f988361c9dd9697d2efa5cbde3106` |
| PR #255 DataTables eval (DRAFT — do not merge) | `eval/tabler-datatables-job-board` | `3b614614ba4212af258d6d216134b7e60871204f` |
| PR #256 Card hierarchy (OPEN, stacked on #254) | `feat/job-board-card-hierarchy` | `ff566ccc815f3c7f16c38ef0912668cae538750d` |
| SaaS `origin/main` (production pin) | pins Core `9cc85289` | `df735e4096e94eb8f0440f5124794ea924a88d2a` |

Merge order if accepted: **#254 → #256**. Never productize #255.

## 2. Local environment & assets

| Check | Result |
|-------|--------|
| Local Core branch | `feat/job-board-card-hierarchy` (includes #254 tip) |
| Unrelated untracked review-artifacts | Left untracked; not committed |
| `public/hot` | Absent for acceptance; served `public/build` |
| Compiled Tabler CSS | `assets/tabler-pQ95JiqO.css` |
| Synthetic dataset | Local demo shop (~21 open ROs: Building Estimate, Waiting Approval, Waiting Parts, In Progress, Ready for Pickup, Draft) |
| SaaS Core pin | Still production `9cc85289` — Cards/List UX not on SaaS |

## 3. Next-action semantics (root cause + fix)

**Root cause:** `WorkboardTriageCard::nextMoveLabel()` / glance projection treated any `Vehicle ID` signal as **Confirm vehicle**, including Ready for Pickup. Generic column defaults (`Review`, `Work active`, `Close or collect`) and attention copy (`Needs attention`) were also presented as Next.

**Correction (presentation only — no RO workflow/billing rule changes):**

- `vehicleIdentityNextMove()` only for Draft / Estimate / Waiting Approval.
- Glance `next()` omits generic column invents; Ready for Pickup with Vehicle ID → `null`.
- `JobBoardCardNextPresentation` classifies `action` | `warning` | `watch` | `none`; prefers omission over a false instruction.
- Cards/mobile compact rows use that presentation; List Attention column still shows severity labels (Watch / exception), not invented Next verbs.

**Verified on local board (RO #95 Ready for Pickup):** `Promise overdue` warning — not “Confirm vehicle”. RO #88 Ready for Pickup: understated `Watch`. Building Estimate + Vehicle ID: `Next Confirm vehicle` remains valid.

## 4. Desktop density

| Width | Columns (`--ark-card-min-width: 21rem`) |
|-------|----------------------------------------|
| 1024 | 2 |
| 1366–1440 | 3 |
| 1920 | 4 |

Chose 21rem after 19rem produced five skinny columns at 1920. Hierarchy: RO+money → vehicle→customer → concern → status/tech → Next/warning → activity+clock.

## 5. Mobile toolbar

- Visible **Employee** label below `lg`; stacked search / tech / Search+List·Cards / Check In.
- No horizontal page overflow at 320 / 390 / 768.
- Lane tabs remain horizontally scrollable; Cards default on small screens with saved `view=` honored (`list` explicit works).

## 6. Activity

Six categories unchanged (`estimate`, `sms`, `email`, `phone`, `dvi`, `scheduled`). Larger hit targets + Tabler theme colors for engaged/attention; deep links intact. No new legend.

## 7. List/Card consistency

Shared `JobBoardListPageBuilder` / `JobBoardListRow` / glance surface. Same filtered 21 ROs, totals, tech, concerns, activity marks, pagination. Layout toggle only changes projection chrome.

## 8. Screenshots

Under `review-artifacts/job-board-card-hierarchy/screenshots/`:

| File | Note |
|------|------|
| `before-cards-1440.png` | Pre-hierarchy (#254 cards) |
| `mid-cards-*-pre-finalization.png` | Mid #256 before Next/toolbar polish |
| `after-cards-{1366,1440,1920,390}.png` | Canonical after |
| `final/cards-*-{light,dark}.png` | Full matrix incl. 320/768/1024 + dark |
| `final/list-390-light.png` | Explicit List on phone |
| `phase1-list-254-total-sort-1440.png` | #254 list sort evidence |

## 9. Tests

Focused: `JobBoardCardNextPresentationTest|WorkboardCardGlanceTest|JobBoardListViewTest|AdvisorHomeCardSurfaceTest` → **42 passed**.

## 10. Remaining / owner decisions

- Visual accept Cards hierarchy, Next omission, mobile Employee stack, density at 3/4 columns.
- Dark-theme Cards pass for Carlos.
- Preference migration / Customers·Vehicles List conventions documented only.
- **Do not merge or deploy** until owner acceptance.
