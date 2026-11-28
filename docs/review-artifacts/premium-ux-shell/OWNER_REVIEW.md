# ARK Premium UX / Workspace Shell — Owner visual review

**Status:** Design package ready for owner visual approval. Narrow false-dirty fix implemented in this branch; **broad shell redesign not started**.

**Do not merge production. Do not deploy without separate authorization.**

---

## Current project state (verified)

| Item | Value |
|------|--------|
| Core `origin/main` | `03541f034742a6bc612c0eb39a2c4a5081497163` (#292 / RC.1 merge) |
| Public tag | `v1.0.0-rc.1` — **not modified** |
| This branch | `fix/workspace-false-dirty-warn` (from `origin/main`) |
| SaaS / Shop pin in local `ark-saas` | `1b63d6d0…` (not advanced to RC.1) |
| Stancl routing PR | [ark-stancl#379](https://github.com/EdwardSoaresJr/ark-stancl/pull/379) open (field routing / Maps) — **staging must not be overwritten** without coordinating that workstream |
| Staging hosts historically used | `alpha.staging.arksms.com` / `demo.staging.arksms.com` on `157.230.172.143` |
| Staging for this package | **Not deployed** — isolated branch + local acceptance only |

---

## 1. False unsaved-changes — root causes

Exact dialog: `This workspace has unsaved changes. Close without saving?` from `confirmClose` in `resources/js/ark-workspace-tabs.js` (native `window.confirm`, **not** `beforeunload`).

### Ranked causes

1. **Sticky `sessionStorage` dirty flags on inactive tabs** — `keyHasUnsavedChanges` only runs live `syncDirty()` for the active tab; background tab close trusts a stale flag.
2. **No departing flush before `location.assign`** — tab switches (often with `skipDirtyConfirm`) left the previous page’s dirty bit set even after autosave / revert.
3. **Hydration baseline drift** — `adoptRenderedBaselines` ran after continuity morphs, not on first paint; Alpine/server HTML could leave `value !== defaultValue` with no user edit.
4. **Successful save then `syncDirty` without adopting the saved form** — pre-morph defaultValue mismatch could re-scare.
5. **Legitimate remaining dirty** (kept) — other dirty composers, failed saves, open modal drafts.

Modal discard already uses: `Discard unsaved changes in this form?` — preserved.

### Narrow fix in this branch

| Change | File |
|--------|------|
| Policy helpers | `resources/js/ark-workspace-dirty-policy.js` |
| Adopt baselines + sync on bind | `resources/js/ark-workspace-dirty-bindings.js` |
| Flush dirty before cross-workspace navigate | `resources/js/ark-workspace-tabs.js` |
| Adopt saved form on successful `settleSaveDirty` | `resources/js/ark-worksheet-continuity.js` |
| Regression tests | `resources/js/ark-workspace-dirty.test.js` |

**Kept:** failed-save dirty, modal discard confirm, closed-modal ignore, live sync for active tab, continuity preserved drafts.

**Tests:** `node --test resources/js/ark-workspace-dirty.test.js` (+ related focus-save / draft / modal dismissal) — **27 passed**.

---

## 2. Shared header architecture (proposed contract)

Four layers — responsibilities must not blur:

| Layer | Owns | Must not contain |
|-------|------|------------------|
| **A. Global application header** | Sidebar toggle, Inbox/comms, global search entry, workstation, account/theme | Record actions (Call, Create RO, Check In) |
| **B. Workspace tab strip** | Open records, active state, close, overflow, optional docked Intake launcher | Persistent **Check In** as a business action chrome (today Check In is a right-docked workspace tab — relocate Intake launch to global/list primary actions) |
| **C. Context header** (`ark-page-context`) | Record identity, parent links, status meta, contextual actions + overflow | Global account chrome |
| **D. Record content** | Workflows, lists, worksheets | Duplicate primary action rows without reason |

Existing building blocks to reuse (already on main):

- `components/operations/shell/tabler.blade.php`
- `components/tabler/page.blade.php` + `page-context.blade.php` + `page-action.blade.php`
- `resources/js/ark-page-context.js` (secondary → overflow)
- `resources/js/ark-workspace-tabs.js` + `workspace-tabs.blade.php`

---

## 3. Context / action placement matrix

| Action | Customer | Vehicle | RO / visit | Appointment | Notes |
|--------|----------|---------|------------|-------------|-------|
| Call | Context header | Context (owner) | via customer | Context | tel: when phone present |
| Text | Context header | via owner | via customer | — | Compose deep-link |
| Schedule | Context header | Context | — | Primary | Appointments feature flag |
| Check In | **Not** strip; list/Shop Queue / appointment eligible | Same | Eligible RO state only | Primary when checking in | Remove as permanent strip business chrome |
| Edit Customer | Context | — | — | — | Modal |
| Edit Vehicle | — | Context | Identity panel | — | Modal |
| Create RO (customer) | Context primary | — | — | — | Intake with customer_id |
| Create RO (vehicle) | Vehicles section row | Context primary | — | — | Vehicle-scoped intake |
| RO authorize / print / notes | — | — | Context | — | Existing visit actions |

Permissions/authorization rules unchanged — presentation only.

---

## 4. UX inconsistency audit (summary)

| Surface | Issue |
|---------|--------|
| Customer hub identity card | Phone/email prominent; **name omitted** from card (only top bar) |
| Customer Vehicles section | Labeled **Fleet** for ordinary retail |
| Vehicles | Search only — **no ops vehicle show** workspace/header |
| Shop Queue | Check In in page toolbar; no top-bar actions |
| Calendar | Mixed raw buttons vs `page-action` (overflow inconsistent) |
| Settings | No context actions |
| Sidebar | Mobile collapse only; Tabler **folded** preference not wired / not persisted for ops |
| Workspace strip | Check In docked right; desktop-only (≥1024) |
| Global search | ⌘K + Inbox menu — no dedicated top-bar search control |
| Docs vs CSS | TABLER.md says top bar not sticky; CSS makes `.ark-topbar` sticky |
| Density | Some hubs sparse; RO worksheet dense — hierarchy uneven |
| Themes | Supported via Tabler `data-bs-theme`; chrome tokens exist but not a full ARK scale doc |

Shared-component vs page-specific: most header inconsistency is **page action packing**, not missing shell primitives. Vehicle workspace gap is **routing/product**, not only CSS.

---

## 5. Proposed ARK design standard (Tabler-based)

| Token / rule | Proposal |
|--------------|----------|
| Font | Tabler sans (`--tblr-font-sans-serif`); title ~1.05–1.125rem semibold in context bar |
| Top bar height | `--ark-topbar-height: 3.5rem` (existing) |
| Workspace tabs | `--ark-workspace-tabs-height` ~2.5rem; sticky under top bar |
| Sidebar | Expanded ~15rem; collapsed icon rail ~4.25rem; persist fold in `localStorage` (new) |
| Content gap | Prefer `g-3` / 0.75–1rem; avoid empty hero cards |
| Buttons | One primary per context; secondary outline; overflow below `md` |
| Status | Tabler badge light variants; red reserved for live call / overdue / credit-due |
| Saving | Quiet “Saved / Saving…” near title; failed save unmistakable + retry |
| Breakpoints | Align Tabler `md`/`lg` + workspace desktop 1024 |
| Themes | Light default; dark via existing theme control |

Full detail: [DESIGN_STANDARD.md](./DESIGN_STANDARD.md).

---

## 6. Prototypes & screenshots

Interactive HTML (Tabler CDN + shared shell):

- [prototypes/customer-workspace.html](./prototypes/customer-workspace.html)
- [prototypes/vehicle-workspace.html](./prototypes/vehicle-workspace.html)
- [prototypes/repair-order-workspace.html](./prototypes/repair-order-workspace.html)

Rendered screenshots (Puppeteer, not AI concept art):

| File | What |
|------|------|
| `screenshots/customer-desktop-light.png` | Customer identity + Vehicles (not Fleet) |
| `screenshots/customer-desktop-dark.png` | Dark theme |
| `screenshots/customer-desktop-sidebar-collapsed.png` | Collapsed sidebar |
| `screenshots/customer-mobile-light.png` | Mobile width |
| `screenshots/customer-modal-dirty.png` | Modal draft (discard path) |
| `screenshots/vehicle-desktop-light.png` / `vehicle-mobile-light.png` | Vehicle context |
| `screenshots/ro-desktop-light.png` / `ro-desktop-dark.png` / `ro-mobile-light.png` | RO context |
| `screenshots/ro-save-failed.png` | Failed autosave feedback |

---

## 7. Customer / vehicle corrections (proposed, pending approval)

**Customer:** Identity block shows name, type, phone, email or “No email on file”, visit/RO summary; rename Fleet → Vehicles; keep hub tabs.

**Vehicle:** Dedicated ops vehicle workspace using same context header; Y/M/M/trim, owner link, VIN/plate, active RO; strip repeated chrome from cards.

**Not implemented in app code yet** (awaiting visual approval).

---

## 8. Implementation roadmap (PR boundaries)

| PR | Scope | Gate |
|----|-------|------|
| **This PR** | False dirty / autosave honesty + this design package | Review / merge when green |
| **Shell contract PR** | Document action matrix in TABLER.md; relocate Check In dock; sidebar fold persist; hamburger consistency | After visual approval |
| **Customer/Vehicle identity PR** | Name in identity; Fleet→Vehicles; vehicle show workspace | After visual approval |
| **Density polish PR** | Calendar/Settings action packing; search affordance; sticky doc fix | After visual approval |
| **SaaS pin / staging** | Separate; coordinate with stancl#379 | Owner deploy command |

---

## 9. Risks & compatibility

- Adopting baselines on bind could mask a true dirty form if scripts mutate fields *after* the double-rAF without firing `input`/`change` — mitigated by continuing to listen for trusted events.
- Removing Check In from the strip changes advisor muscle memory — keep Intake reachable from Shop Queue + Customers/Vehicles primary actions.
- Vehicle show route is net-new surface area (tests, permissions, workspace boot entity).
- Staging shared with Maps/routing (#379) — do not claim `alpha.staging` without ownership check.
- Do not advance SaaS pin to RC.1 as part of this UX work unless separately authorized.

---

## 10. Staging requirements

To stage this after approval:

1. Confirm stancl#379 / Maps acceptance is not using the target staging tenant.
2. Pin Core SHA of the approved UI PR into SaaS via normal pin PR (not this branch alone).
3. Deploy to agreed staging hostname only.
4. Browser accept: desktop + mobile; dirty close matrix; customer/vehicle/RO screenshots against live CSS.

**This session:** local JS tests + prototype screenshots only. Staging **blocked on ownership coordination**, not on missing code for the narrow dirty fix.

---

## 11. Production release considerations

- Narrow dirty fix is safe to merge independently; ship with next Core cut after CI.
- Premium shell redesign requires owner visual sign-off, then sequenced PRs above.
- Traditional hosting RC.1 tag remains untouched.
- Hosted production (`lugsnplugs.arksms.com`) needs explicit **Deploy the approved release** with artifact + target.

---

## Owner decision needed

1. Approve / annotate the three prototypes (light/dark, desktop/mobile, collapsed sidebar).
2. Confirm Check In leaves the workspace strip.
3. Confirm vehicle ops show workspace is in scope for v1 polish.
4. Authorize staging host when Maps/#379 is clear.

**ARK PREMIUM UX DESIGN SYSTEM — READY FOR OWNER VISUAL REVIEW**
