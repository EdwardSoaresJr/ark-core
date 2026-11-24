# ARK UI Migration Status

**Point-in-time:** 2026-11-24  
**Baseline:** Core `origin/main` `9cc85289`  
**Companion:** `ARK_INTEGRATION_READINESS_AUDIT.md`, `TABLER.md`, `TABLER_LEGACY_ASSET_RETIREMENT.md` (#259 tip `b54c4864`)

A page with `ui="tabler"` is **shell-migrated**, not necessarily **asset-isolated** or **island-free**.

---

## Measurable inventory (main)

| Metric | Count / evidence |
|--------|------------------|
| Staff views with `ui="tabler"` | **66** Blade files |
| Files with `.ark-legacy` wrappers | **5** (guide modal, worksheet-island, print helpers, estimate document body, customer journey when shown) |
| Staff pages still on legacy shell | ARKademy (deferred; nav hidden by default) |
| Tabler Vite load order | `app.css` → `tabler.css` → `app.js` → `tabler.js` |
| Preline on `main` | Still dynamically imported in `app.js` (~373 KB chunk) |
| Preline after #259 | Removed from runtime + Tailwind scan (tip `b54c4864`) |

### Fully shell-migrated (per `TABLER.md`)

Records/daily work, communications workspaces, owner/reports, settings/profile, documents/financial surfaces listed under Migration inventory in `TABLER.md` — **Complete: every reachable staff page renders the Tabler shell.**

### Partially migrated (islands remain)

| Island | Why it blocks `app.css` drop |
|--------|------------------------------|
| Guide modal article prose | Present on **every** OperationsAccess Tabler page |
| RO worksheet (`worksheet-island`) | Estimate editor / RO show |
| Print helpers (`:printing="true"`) | RO |
| Estimate printable `<article>` | Document show |
| PartsTech toolbar group | Legacy button color classes |
| Event-bubble / telephony settings | Communications-owned (docs still claim islands; verify on tip) |
| Customer operational journey card | When non-null (often null) |

### Not staff-migrated / deferred

- ARKademy (legacy shell).
- Guest/auth, installer, wall displays, customer portal, public website, ARK Cloud pages — excluded from staff count in `TABLER.md`.

### Stale documentation risks

- `TABLER.md` “Still on app.css: shell components” historical log entries may overstate; current rule is **islands**, not unre-migrated shell chrome.
- Telephony settings: open Core branch `feat/comms-tabler-views` claims further Shop Settings migration — **PENDING RECHECK** vs `main` (not in #254–#259).

---

## List / Card surfaces

### Job Board (`operations.index`) — on `main` + open PRs

| Capability | `main` (#253) | #254 | #256 |
|------------|---------------|------|------|
| List + Cards | Yes | Yes | Yes |
| Layout toggle | Yes | Yes | Yes |
| Server dataset shared | Yes | Yes | Yes |
| List UX (headers/sorts/lanes/concern) | Baseline | **Improved** | Includes #254 |
| Card hierarchy / Next semantics | Baseline | — | **Improved** |
| Preference model | Cookie + `?view=` URL rewrite | same era | same era until #258 |
| Owner accepted | — | **No** | **No** |

#255 DataTables prototype: **DRAFT / DO NOT MERGE** into product Job Board.

### Customers / Vehicles / Repair Orders index — #257 tip `9b5bc832`

| Capability | Evidence on #257 |
|------------|------------------|
| List + Cards | Implemented |
| Shared toggle / summary / sortable-th | Yes (`CollectionLayout*`, tabler components) |
| Server-side sort/pagination | Yes |
| Same authorized dataset | Claimed + tests |
| Module-independent cookies | `ark_collection_layout_*` |
| Clean URLs | Still queues `?view=` into cookie on request; **not** the #258 localStorage contract |
| Desktop List / mobile Cards defaults | CSS/JS auto |
| Explicit List on phone | Compact mobile rows |
| Job Board untouched | Asserted in tests/acceptance |
| Owner accepted | **No** |
| Wired to #258 `UiLayoutPreference` | **No** — integration gap |

### Preferences / appearance — #258 tip `4d20aa7f`

| Capability | Status |
|------------|--------|
| Browser-local layout keyed tenant+user+module | Job Board wired |
| Collection modules reserved in enum/JS | Yes; indexes not switched |
| Stop emitting `?view=` on Job Board nav | Yes on #258 |
| Light / Dark / System | DB authority + head boot; System listens to OS |
| FOUC mitigation | Head boot before CSS |
| SaaS tenant key | Needs `ShopChannelScope` on hosted |
| Owner accepted | **No** |

### Legacy asset retirement — #259 tip `b54c4864`

| Capability | Status |
|------------|--------|
| Drop Preline chunk | Done (−~380 KB total entry assets in measured build) |
| Drop `app.css` on Tabler | **Not started** (blocked by islands) |
| Split `app.js` by page | Backlog |
| Owner accepted | **No** |

---

## Shared component duplication (post-merge debt)

| Concern | Today | Target |
|---------|-------|--------|
| Job Board layout JS | `ark-job-board-layout.js` (+ #258 shared helper) | Prefer shared helper only |
| Collection layout JS | `ark-collection-layout.js` (#257) | Call `ark-ui-layout-preference.js` |
| Cookie except lists | Job Board + collection + #258 legacy names | Single `UiLayoutPreference::legacyCookieNames()` |
| `tabler.css` layout rules | Parallel `[data-job-board]` and `[data-collection]` | Keep parallel selectors; do not merge product rules carelessly |

---

## Visual / responsive evidence (agent packages — not re-run 320–1920 matrix in this audit)

| PR | Screenshots present | Viewports claimed |
|----|---------------------|-------------------|
| #256 | Card acceptance package | Desktop + mobile (agent) |
| #257 | customers/vehicles/RO | 1440 + 390 customers |
| #258 | Job Board list/cards + appearance | 1440 + 390 list |
| #259 | Job Board / Customers / Settings | 1440 |

**This audit did not re-capture 320/375/768/1024/1366/1920 or force Dark/System OS toggles against each tip after the auditor’s branch switch.** Treat those matrices as **PENDING RECHECK** before DEPLOYED AND HEALTHY.

---

## Classification snapshot

| Workstream | Class |
|------------|-------|
| Tabler shell migration (staff) | **READY FOR OWNER REVIEW** as complete-for-shell; islands remain |
| Job Board #254/#256 | **READY FOR OWNER REVIEW** |
| Collection List/Cards #257 | **NEEDS CORRECTION** before merge if #258 lands first (preference API); else **READY FOR OWNER REVIEW** with follow-up integration |
| Preferences #258 | **READY FOR OWNER REVIEW** |
| Preline #259 | **READY FOR OWNER REVIEW** |
| DataTables #255 | **DEFERRED** / do not productize |
| `app.css` removal | **BLOCKED** on island migration |
| Production UI pack release | **BLOCKED** |
