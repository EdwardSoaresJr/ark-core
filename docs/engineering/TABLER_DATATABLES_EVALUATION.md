# Tabler + Yajra / DataTables evaluation

**Date:** 2026-11-24  
**Production Core baseline:** `9cc852899b2ab9754185675b7d83d656978b2b33`  
**UX PR #254 tip (preserved, not merged here):** `f10b70f5d42f988361c9dd9697d2efa5cbde3106`  
**Evaluation branch:** `eval/tabler-datatables-job-board`  
**Prototype URL (local only):** `/app/eval/job-board-datatables`  
**Status:** STOPPED for owner review — no merge, staging, production, or SaaS Core-pin.

## Verdict: **ADOPT SELECTIVELY**

Adopt **Tabler as the only design system** for all staff tables and boards.  
**Do not** standardize the Job Board (or ARK generally) on Yajra + DataTables.js as the default list architecture.

Use DataTables/Yajra **only** for a narrow class of admin/export-heavy tabular screens *if* a later ticket proves they reduce code versus today’s `card-table` + Laravel paginator pattern. Job Board List should stay a first-party Tabler table (continue PR #254), with a separate Tabler-native Card composition pass.

Evidence is below.

---

## 1. Current architecture inventory

### Cross-cutting

| Pattern | Where | Search / sort / page |
|---------|--------|----------------------|
| Tabler shell | `ui="tabler"` → `components/operations/shell/tabler.blade.php` | Loads `app.css` + `tabler.css` + `app.js` + `tabler.js` |
| Record search | Customers, Vehicles, Repair Orders index | `x-tabler.search-filters` + `.ark-card-grid` + Laravel `paginate(24)` + `pagination::bootstrap-5` |
| Dense `card-table` | AR, reports, owner tools, observations, stations | GET filters; often **no** pagination; no JS table lib |
| Advisor Job Board | `OperationsHomeController` + `JobBoardListPageBuilder` | Server filter/sort/page (50); List + Cards over **shared** rows; cookie layout |

**No first-party DataTables or Yajra usage today.** `datatables.net` appears only as a **transitive** npm dependency of Preline; `resources/js` never imports it. Staff JS is Alpine + Tabler/Bootstrap + dynamic Preline.

### Surfaces (summary)

| Surface | Tabler | Notes |
|---------|--------|-------|
| Job Board (advisor) | Yes | Custom list/cards; high complexity; #254 concern/sort/header work |
| Job Board (technician) | Yes | `.ark-board` lanes; no list infra |
| Customers / Vehicles / RO index | Yes | Shared record-card pattern |
| Accounts Receivable | Yes | Dashboard + customer invoice/receipt tables |
| Parts matrices / tune | Yes | Alpine editors + simulation tables |
| Work queues / schedule / reports / calls | Yes | Mix of list-group, boards, paginated call library |

Full audit notes: `review-artifacts/tabler-datatables-eval/` (this evaluation) and explore transcript.

---

## 2. Yajra / DataTables compatibility assessment

| Topic | Finding |
|-------|---------|
| Laravel | Core is `laravel/framework ^13.8`. Yajra publishes `yajra/laravel-datatables` **^13** for Laravel 13 (MIT). |
| Frontend | Official path: DataTables 1.x/2.x + Bootstrap 5 assets (`laravel-datatables-vite` / `datatables.net-bs5`). |
| Licensing | Yajra MIT; DataTables core MIT. Editor/commercial plugins not required for server-side tables. |
| Server-side | Search, order, pagination, column defs — yes. Semantic sorts (attention rank, total cents) still need custom columns — **same work as #254 builder**. |
| Column visibility / export | Available via Buttons/ColVis; adds JS weight unused by Job Board. |
| Accessibility | DataTables improves some keyboard sort chrome; still needs Tabler-skinned overrides and careful `aria-sort` for custom cells. |
| Alpine / Tabler | Coexists if DT owns one table subtree; **jQuery** is an otherwise-unused dependency on the staff shell. |
| Maintenance | Extra major-version coupling (Laravel ↔ Yajra ↔ DataTables ↔ BS5). Tabler upgrades already require care with `app.css` coexistence. |

**This evaluation did not install Yajra** (assignment boundary: no new production dependencies merely to evaluate). The prototype implements the **DataTables server-side JSON protocol** over `JobBoardListPageBuilder` — the same seam Yajra would wrap.

---

## 3. Prototype screenshots

Artifacts under `review-artifacts/tabler-datatables-eval/screenshots/` (demo tenant data, not production customers):

| File | What |
|------|------|
| `A-list-254-1440.png` | PR #254 Tabler List @ ~1440 |
| `B-datatables-eval-1440.png` | DataTables + Tabler prototype @ ~1440 |
| `A-list-254-390.png` | #254 mobile compact rows @ 390 |
| `B-datatables-eval-390.png` | DT prototype @ 390 (horizontal clip) |

Also exercised 1366 and 1920 locally: desktop List remains usable; DT adds length menu + paging chrome without improving hierarchy.

**Prototype route:** `operations.eval.job-board-datatables` (404 unless local/testing or `ARK_EVAL_JOB_BOARD_DATATABLES=true`).

**Preserved from #254:** lane counts, search/tech filters, customer/vehicle stack, `RepairOrderConcernText` fallback, status/tech/totals, attention severity, activity marks, RO links, tenant auth via same advisor query.

**Cards coexistence:** List body = DataTables AJAX; Cards = same server `JobBoardListRow` projection. One builder — not a second data system.

---

## 4. Functional comparison

| Concern | #254 Tabler List | DT + Tabler prototype |
|---------|------------------|------------------------|
| Header readability | Improved in #254 (filled header, sort links) | Similar with DT ordering classes + Tabler CSS |
| Sort indicators | First-party carets + `aria-sort` | DT built-in; needs CSS to match Tabler |
| Row density | Compact `table-sm` | Same cells (reuses list-row Blade) |
| Pagination | Prev/next + page size 50 | Length menu 10–100 + DT pager |
| Lane tabs | First-party filled active tab (#254) | Same Tabler tabs |
| Mobile | Dedicated compact row layout | **Wide table + horizontal overflow** (regression vs A) |
| Cards | Shared rows, dual DOM | Same; DT irrelevant to Cards |
| Dependencies | None new | jQuery + DataTables CDN (eval); Yajra would add Composer |

---

## 5. Performance measurements

Local demo dataset (~21 open ROs), builder micro-benchmark ~**67ms** for filter/sort/page (shared path).

| Surface | Queries | Notes |
|---------|---------|-------|
| A HTML List | ~35–45 | Dual List+Cards Blade |
| B DT JSON | ~35–45 | Same projection; HTML cells still rendered server-side |
| B page shell | ~35–45 | Plus ~90KB gz jQuery+DT client cost |

Larger sets: both A and B still load the advisor open-RO collection then sort in memory — **DataTables does not fix query strategy**. True SQL-side paging would be a separate project whether or not Yajra is used.

Details: `review-artifacts/tabler-datatables-eval/PERF_NOTES.md`.

---

## 6. Dependency and maintenance implications

| If we ADOPT Yajra globally | Cost |
|----------------------------|------|
| Composer `yajra/laravel-datatables` ^13 | Ongoing Laravel major sync |
| npm jQuery + datatables.net-bs5 (+ Buttons?) | Conflicts with Alpine-first culture; asset weight on every migrated table page if bundled globally |
| Skinning | Must suppress default DT CSS; maintain Tabler overrides (prototype proves this is doable but permanent work) |
| Job Board | **Little reduction** — semantic domain sorts and dual List/Cards remain custom |
| Record searches | Card grids are not tables; DT is the wrong widget |

| If we REJECT as default | Benefit |
|-------------------------|---------|
| Continue #254 Tabler List | Matches design foundation; better mobile story |
| Invest in Tabler table primitives | Shared header/sort/pagination partials for AR/reports |

---

## 7. Legacy ARK asset retirement inventory

Tabler pages **still load** full `app.css` and `app.js` (documented in `docs/engineering/TABLER.md`).

| Dependency | Classification | Notes |
|------------|----------------|-------|
| `app.css` on Tabler pages | Requires migration of remaining consumers | Needed for `.ark-legacy` islands + leftover `ops-*` hooks |
| `app.js` full Alpine surface on every Tabler page | Requires migration / code-splitting | Most inits no-op per page; Preline dynamic import always runs |
| Preline (`import('preline')`) | Still required (today) | Transitive DataTables unused |
| `.ark-legacy` guide modal prose | Requires migration | ARKademy article body |
| Estimate printable document body | Requires migration | `documents/estimates/show` |
| RO print helpers when `$printing` | Requires migration | |
| Conditional operational journey card island | Requires migration / delete if unused | |
| Telephony settings / event bubbles | **Docs drift — largely Tabler now** | Re-verify before removal; TABLER.md “Remaining legacy” is stale in places |
| jQuery / DataTables in app code | Safe to keep **out** | Do not enable via Preline |

### Phased removal (proposal only — not executed)

1. Finish island inventory vs live Blade (update TABLER.md).  
2. Split `app.js` so Tabler pages load only needed Alpine modules.  
3. Remove each `.ark-legacy` island as its content migrates.  
4. Stop loading `app.css` on Tabler when islands = 0; delete PostCSS exclude plugin.  
5. Never reintroduce a second UI framework; Tabler only.

---

## 8. Card View design recommendations

Cards feel weak because hierarchy is flat and “next move” is under-signaled — not because List/Cards share a projection (that parity is correct).

**Recommend Tabler-native bands:** identity+money → party → concern (#254) → status/tech/clock → **Next** action line → activity marks. Keep `.ark-card-grid`.

Concepts (static HTML for Carlos):  
`review-artifacts/tabler-datatables-eval/card-concepts/`  
(`CARD_VIEW_RECOMMENDATIONS.md`, `concept-a-dense-workflow.html`, `concept-b-status-led.html`)

**Do not** redesign Cards in this evaluation.

---

## 9. Migration risks and scope

| Path | Scope estimate | Risks |
|------|----------------|-------|
| Merge/finish #254 List UX | Small (already open) | Visual polish only |
| Tabler Card composition pass | Medium (Blade + CSS; shared rows) | Low data risk |
| Shared Tabler table header/sort partial for AR/reports | Medium | Touch many views carefully |
| Job Board → Yajra/DT | Large | jQuery, mobile regression, dual Cards model, little domain leverage |
| Org-wide Yajra | Very large | Framework coupling; fights Tabler-first roadmap |

---

## 10. Final recommendation

### **ADOPT SELECTIVELY**

1. **Adopt / continue:** Tabler-first List (#254), Tabler Card redesign concepts, shared Tabler table primitives over time.  
2. **Reject as Job Board standard:** Yajra + DataTables.js. Prototype shows chrome parity at best and **worse mobile** without Responsive extensions — while retaining all custom projection work.  
3. **Optional later:** Yajra only for a specific export-heavy admin grid after a written ADR, without loading DT on Job Board or record-card pages.  
4. **Do not** install Yajra or enable DT CSS globally without a scoped adoption ticket.

### Supporting evidence

- Inventory: no existing DT usage; lists already Tabler.  
- Prototype: protocol works; visual gain vs #254 is marginal.  
- Mobile: A compact rows ≫ B horizontal table.  
- Perf: same builder bottleneck; DT adds client weight.  
- Boundary honored: #254 untouched/not merged; no staging/prod; no SaaS pin; no Yajra Composer install; Concern/Job fallback preserved.

---

## How to review the prototype

```text
Local (Herd): https://ark-core.test/app/eval/job-board-datatables
Compare:      https://ark-core.test/app?view=list
Tests:        php artisan test --filter=JobBoardDataTablesPrototypeTest
```

**Stop.** Owner/Carlos visual + architecture decision required before any conversion or dependency install.
