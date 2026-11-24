# Job Board UX completion — acceptance package

**Date:** 2026-11-24  
**Stopped for owner/Carlos visual acceptance.** No merge, staging, production, or SaaS Core-pin.

## 1. Exact SHAs

| Role | SHA |
|------|-----|
| Production Core baseline / `origin/main` | `9cc852899b2ab9754185675b7d83d656978b2b33` |
| PR #254 List UX (OPEN, not merged) | `f10b70f5d42f988361c9dd9697d2efa5cbde3106` |
| PR #255 DataTables eval (DRAFT, do not merge) | `3b614614ba4212af258d6d216134b7e60871204f` |
| Card hierarchy branch tip | *(this PR tip — see GitHub)* |
| SaaS `origin/main` (production pin) | `df735e4096e94eb8f0440f5124794ea924a88d2a` pins Core `9cc85289` |

## 2. Environment parity

| Check | Result |
|-------|--------|
| Local Core branch for Card work | `feat/job-board-card-hierarchy` based on #254 tip |
| Uncommitted unrelated artifacts | Left untracked; not committed |
| `public/hot` | Removed for acceptance; served `public/build` |
| Compiled Tabler CSS (Card tip) | `assets/tabler-BYHBbn9Y.css` |
| Synthetic dataset | Local demo shop (~21 open ROs with concerns/status/comms) — not production customers |
| SaaS Core pin | Still production `9cc85289` — Cards/List not on SaaS |

**Not BLOCKED:** local Herd serves expected #254+#Cards combination with rebuilt assets.

## 3. PR #254 acceptance findings

Browser-validated on #254 tip before Card edits:

- Eight sortable headers present; `sort=total` yields numeric order (RO #93 $1,066 first).
- Concern cells show real text (concern-row fallback), not mass `—`.
- Active lane styling from #254 retained.
- Activity deep links present on list rows.
- Kept independent; not combined with #255.

Screenshot: `screenshots/phase1-list-254-total-sort-1440.png`

## 4. Card View PR

Focused PR stacks on #254. Changes:

- Tabler band hierarchy: identity+money → party → concern → status/tech → Next (from `nextMoveLabel` / actionable attention only) → activity+updated.
- `.ark-card-grid` for responsive equal-height cards.
- Watch understated; Attention/Critical keep status strip + weight.
- No Yajra/DataTables; no business-logic duplication.

## 5. Screenshots

| File | Note |
|------|------|
| `before-cards-1440.png` | #254 cards composition |
| `after-cards-1440.png` | Hierarchy redesign |
| `after-cards-390.png` | Mobile cards |
| `phase1-list-254-total-sort-1440.png` | List sort + concerns |

Also exercised 1366/1920 desktop; light theme primary. Dark theme uses same Tabler variables (visual owner check remaining).

## 6–9. Architecture docs

- `docs/engineering/TABLER_SHARED_LIST_CARD_CONVENTIONS.md`
- `docs/engineering/TABLER_LAYOUT_PREFERENCE_MIGRATION.md`
- `docs/engineering/TABLER_LEGACY_ASSET_RETIREMENT.md`

## 10. Tests

- `JobBoardListViewTest`: 24 passed
- CustomerSearch|VehicleSearch|RepairOrderIndex filter: 16 passed
- CI: see Card PR checks after push

## 11. Remaining / owner decisions

- Visual accept Cards hierarchy (Next line density, min-height).
- Merge order: #254 first, then Cards PR; never #255 as product.
- Preference migration and Customers/Vehicles List not implemented (documented only).
- Dark-theme Cards pass for Carlos.
- No production deploy authorized.
