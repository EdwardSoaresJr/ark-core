# ARK V2 Interface Constitution

**Status:** Active — Phases 0–5 complete (2026-06-14). New operations UI work follows this substrate.  
**Companion:** `product doctrine` (product posture). This document is **structure and visual language**.  
**Earned freeze (2026-08-05 / rename 2026-08-06):** Interaction primitives below — Repair Order Presentation Reset (Builder identity retired).

---

## 0. Interaction primitives (earned)

### Presentation

**Presentation is permanent.**

Default. Read. Understand. Observe. No permanent editors. Authority appears when it has something to say — not because a capability exists.

### Authoring

**Authoring is temporary.**

**Users express intent. ARK resolves structure.**

Open → change → save → return to presentation. Prefer the existing Workspace Modal. Do not invent a new modal family per feature.

### Workspaces

**Workspaces are immersive.**

Dedicated operational surface for sustained work — not a panel bolted onto presentation. Examples: Inspection · (later) Closeout · Reporting · Administration.

### Placement

**Placement is an implementation detail, not a user decision.**

**Compose follows the deepest existing work context.**

When a clear deepest context exists, ARK determines placement automatically.

When no unambiguous deepest context exists, ARK may ask the user to choose.

Users never navigate the object graph to answer “Where does this belong?” They identify what they are authoring; ARK determines the correct attachment point.

| User intent | ARK placement |
| --- | --- |
| Add Note | Deepest existing work context |
| Add Labor | Current Repair Action |
| Add Part | Current Repair Action |
| Add Evidence | Deepest existing work context |
| Record Outcome | Current Testing Package |

### Workspace Modal

```text
Header
Helper
Body
Validation
Footer
```

**Nothing inside the body should ever submit the form.**

Validation appears immediately above the footer — never at the top of the modal, never as a toast, never replacing the helper.

Footer owns Cancel and the primary action.

---

**Product language:** ARK does not have “Edit Mode.” View the repair order → open authoring → return to the repair order.

**Discoverability (earned 2026-08-07):** The user should never have to wonder whether something is editable.

Not everything should be editable. But if it is editable, the interaction should be obvious and consistent — same presentation → Workspace Modal → return grammar everywhere. This is not the same sentence as “Presentation is permanent”; it is how operators *find* authoring without memorizing exceptions (“this field is inline, that one is a popup”).

**Relation to §1 page types:** Page types (Workspace · Queue · Index · Settings) classify *routes*. Interaction primitives classify *how humans engage* on those routes. A page-type Workspace may be mostly **Presentation** (Repair Order) with **Authoring** (modals) and may open an immersive **Workspace** (Inspection).

**Protect the grammar:**
- Every authority gets a home; it only appears when it has something to say.
- Before adding authoring: can it fit the existing Workspace Modal? Usually yes.
- Before adding chrome to the Repair Order: does this belong in presentation, or is it authoring leaking?
- If it is editable, make that obvious and consistent — do not invent a second way to enter authoring on the same surface.
- Do not ask “Where does this belong?” when a clear deepest work context exists.
- Observation before polish: write friction down; group patterns; then one polish pass — do not redesign from one-off reactions.

**Near-term freeze:** No new authorities and no new UI paradigms until floor observation earns the next change.

**Earned freeze (2026-08-08):** Placement / deepest work context / Workspace Modal footer-only actions — authoring philosophy above.

---

## Why this exists

ARK V2 does not have a bad-design problem. It has a **consistency problem**.

Communications, Intake, Operations, Repair Orders, Estimate Review, Customers, and Settings each evolved with local patterns. Nobody is wrong — they are **drifting**.

This constitution defines one operating-system substrate. Features ship on the substrate; they do not invent a new dialect per surface.

**Not a redesign.** Preserve behavior. Standardize anatomy, tokens, and components.

---

## 1. Page types

Every operations page belongs to exactly one type. Mixed types on one route are a smell.

### Workspace

**Purpose:** One entity session — review and complete work on a single authoritative object (presentation first; authoring via §0).

| Examples | Route pattern |
|----------|----------------|
| Repair Order (canonical show) | `/app/repair-orders/{id}` |
| Customer Hub | `/app/customers/{id}` |
| Intake session | `/app/intake/new?ws=` |
| Conversation reply | `/app/conversations/reply` |

**Anatomy:**

```
Layout topbar (entity title from $title)
└── Workspace tabs (when entity is tabbed)
└── Context band (identity, posture, totals)
└── Primary content (presentation report; immersive work opens elsewhere when needed)
└── Context rail (customer, vehicle, status — read-first)
```

**Rules:**
- Presentation is default on the Repair Order; authoring is modal (§0). Do not restore permanent page-wide edit chrome.
- No queue bands, no retrieval filters.
- Financial totals and workflow state are server-authoritative (never duplicated in JS).

**Current drift:**
- Legacy `ops-mode-header` / Edit·View naming may linger in debt docs — product language is presentation + authoring, not Edit Mode.
- ~~Intake create uses `ops-page-toolbar` inside workspace~~ — uses `workspace-context-band`.

---

### Queue

**Purpose:** Pressure requiring human attention before lifecycle work continues.

| Surface | Route | Owned pressure |
|---------|-------|----------------|
| **Work** (Attention home) | `/app` | Customer + shop pressure summary |
| **Communications** | `/app/communications` | Communication recovery |
| **Intake** | `/app/intake` | Qualification before lifecycle |
| **Operations** | `/app/workboard` | Repair order lifecycle lanes |
| **Work queues** (full) | `/app/work/queues/{queue}` | Tasks, follow-ups, scheduled, decisions |

**Anatomy:**

```
Layout topbar (surface name: Work, Communications, Intake, Operations)
└── Queue page header (ONE per full-page queue — see §2)
└── Optional filter strip (channel tabs, buckets — subordinate to header)
└── Pressure surface (bands, lanes, or sections)
    └── Queue cards or list rows
```

**Rules:**
- One visual language for all queue headers (§2).
- Counts are visible at band level and in sidebar — same badge component (§5).
- No retrieval search bars on queue pages (historical search lives on Index surfaces).

**Current drift:**
- ~~Three header systems~~ — unified on `queue-page-header` + `queue-band-header` (`work-queue-band-header` delegates to `home` variant).
- Communications still stacks channel tabs under page header — intentional filter strip, not a second page title.
- Intake lane tone modifiers — [x] intake bands use `ops-pressure-band--*` + `ops-queue-band--*` like Operations lanes; lane headers inherit tone via `ops-queue-band-header`.

---

### Index

**Purpose:** Find and open entities without changing live queues.

| Examples | Route |
|----------|-------|
| Customer recognition | `/app/customers/search` |
| Vehicle recognition | `/app/vehicles/search` |
| Repair order historical search | `/app/repair-orders` |
| Caller lookup | `/app/caller-lookup` |
| Appointments schedule | `/app/appointments` |

**Anatomy:**

```
Layout topbar (surface name)
└── ops-board-shell
    └── ops-page-toolbar (note + secondary actions)
    └── ops-board-filters (GET search / filters)
└── ops-board-shell
    └── ops-index-results-head (count strip)
    └── ops-ro-retrieval-grid → ops-ro-card (appointments: `ops-schedule-*` list — see rules)
```

**Rules:**
- Canonical toolbar is `ops-page-toolbar` + `ops-page-toolbar-note` (not `ops-page-header` — that class does not exist).
- Results use **card grid** (`ops-ro-retrieval-grid`) for entity retrieval. **Schedule lists** use `ops-schedule-row` + bucket/day labels — not card grids.
- Cross-links in toolbar: Workboard, Customers, + New Intake — not duplicate primary CTAs.

**Current drift:**
- ~~Appointments toolbar h2~~ — note-only `ops-page-toolbar`; week body uses shared `schedule-row` partial.
- ~~Vehicles global index~~ — `/app/vehicles/search` with `ops-ro-card` retrieval grid.
- Intake AJAX customer search reuses card grid inside workspace shell — acceptable hybrid, not a fourth page type.

---

### Settings

**Purpose:** Configure shop behavior — infrequent, form-heavy, category-navigated.

| Examples | Route |
|----------|-------|
| Shop settings | `/app/settings/shop` |
| Profile | `/app/profile` |

**Anatomy:**

```
Banner (outcomes summary + save guidance)
└── Sticky category nav (sidebar)
└── Section stack (eyebrow + h2 + description + form)
    └── Reuses ops-index-field-* controls
```

**Rules:**
- No queue bands, no pressure cards.
- Forms use shared field primitives; sections own save boundaries.
- Settings does not surface operational pressure (no RO counts in settings nav).
- **Tabular admin lists** (staff team roster) reuse `ops-index-results-head` + `ops-index-results-columns--staff` + `ops-index-results-row--staff` — not `ops-ro-card` retrieval grids.

**Current drift:**
- ~~Staff list uses `ops-staff-columns` grid~~ — team list uses `ops-index-results-columns--staff` + `ops-index-results-row--staff` (tabular settings exception; not card retrieval).
- Settings borrows `ops-index-field-*` without `ops-index` wrapper — fine, but documents the split.

---

## 2. Headers

### Global chrome (all types)

**File:** `resources/views/components/operations/app.blade.php`

| Element | Class | Role |
|---------|-------|------|
| Topbar | `ops-topbar` | `h1` from `<x-operations.app title="…">` |
| Global search | `ops-global-search` | Always links to customer recognition |
| Primary CTA | `ops-topbar-primary` | + New Intake (narrow / docked when workspace tabs enabled) |
| Workspace tabs | `ops-workspace-tabs` | Multitasking chrome |
| Sidebar counts | `ops-rail-link__count` | Pressure badges on nav items |

The topbar title is the **surface name**, not the queue description. Queue context lives in the page header below.

---

### Queue page header (normative — target state)

Every **full-page queue** gets one header with the same anatomy:

| Slot | Content | Typography (target) |
|------|---------|---------------------|
| **Title** | Surface or queue name | `text-sm font-black text-slate-950` |
| **Description** | One line — what pressure means here | `text-[11px] leading-4 text-slate-500` |
| **Count** | `(N)` when N > 0 | `tabular-nums text-slate-500` inline with title |
| **Primary action** | Surface-specific CTA | `ops-page-link--primary` or topbar-docked equivalent |
| **Secondary** | Back to Work, overflow | `ops-page-link` ghost |

**Target examples:**

| Queue | Title | Description | Primary action |
|-------|-------|-------------|----------------|
| Communications | Communications | Customer communication pressure requiring action. | New message (when product supports) |
| Intake | Intake | Recognition and qualification before lifecycle work. | + New Intake |
| Operations | Operations | Repair order lifecycle pressure in the building. | + New Repair Order (if/when exposed) |

**Component target:** `x-operations.queue-page-header` — one Blade component, used by `work/queue.blade.php`, `intake/index.blade.php`, and `operations/index.blade.php` (workboard).

---

### Band / lane headers (within a queue)

Subordinate to the page header. Same anatomy everywhere:

| Slot | Target class |
|------|----------------|
| Label | `ops-queue-band-label` — 11px uppercase, semibold, tracking |
| Description | `ops-queue-band-description` — 11px, slate-400 |
| Count | Separate `span`, tabular-nums, right-aligned — **not baked into title string** |

**Tone:** Lane color is a **top border** on the band container (`ops-queue-band--{tone}`), not per-card decoration.

**Current implementations to merge:**

| Class today | Used on |
|-------------|---------|
| `ops-pressure-band-header` | Workboard, Intake |
| `ops-home-band-header` via `work-queue-band-header` | Work home, full queues |
| Inline `h3` in `queue-section` | Comms Since Last Shift, etc. | [x] `x-operations.queue-band-header` |
| `work-queue-band-header` partial | Work home decision lanes | [x] Delegates to `queue-band-header` variant `home` |

**Target:** `x-operations.queue-band-header` replacing all three.

---

### Index toolbar (normative)

Already consistent on customers, RO search, caller lookup:

```html
<div class="ops-page-toolbar">
  <p class="ops-page-toolbar-note">…one sentence…</p>
  <div class="ops-page-toolbar-actions">…</div>
</div>
```

Do not add a second title row inside the toolbar (appointments drift).

---

## 3. Cards

Queue cards and operations cards are **siblings** — same shell, different contents.

### Queue card (normative anatomy)

**Target component:** `ops-queue-card` (evolve from `ops-ro-card` + `ops-intake-qual-card` convergence)

| Row | Slot | Typography |
|-----|------|------------|
| 1 | **Identity** — vehicle YMM or customer name | 0.875rem, weight 800, slate-950 |
| 2 | **Context** — customer · RO# · status OR concern snippet | 0.6875rem, weight 600, slate-500 |
| 3 | **Status** — chips, age, dollars, completeness | chips + `tabular-nums` |
| 4 | **Action** — next action label OR whole-card link | 0.6875rem, weight 800, accent |

**Shared substrate (target tokens):**

| Property | Value |
|----------|-------|
| Border | `1px solid slate-200` |
| Radius | `0.25rem` |
| Shadow | none at rest; optional `0 1px 2px` on hover |
| Padding | `0.5625rem 0.625rem 0.5625rem 0.8125rem` |
| Accent | **3px left bar** via `::before` — single system (not left-border on one surface and `::before` on another) |
| Density | gap `0.375rem` internal, grid gap `0.5rem` |

**Lane tone** colors the **band**, not each card. Cards may carry semantic accent on the left bar only when row-specific (e.g. qual blocked vs ready).

---

### Current card families (audit inventory)

| Family | Class | Surfaces | Accent system |
|--------|-------|----------|---------------|
| RO / retrieval | `ops-ro-card` | Workboard, RO index, customer search, intake vehicle pick | `::before` 3px accent bar |
| Intake qual | `ops-intake-qual-card` | Intake queue | `border-left` 3px amber/emerald |
| Comms row | list row + Tailwind | Comms queue, call dropdown | none — row actions |
| Decision pressure | list row | Work home decisions | lane tint on parent band |
| Status chips | `ops-status-chip`, `ops-state-pill`, `ops-ro-flag` | RO index, estimate, customer | inconsistent tone coverage |

**Convergence plan:** Intake qual cards adopt `ops-queue-card` modifiers (`--blocked`, `--ready`) with shared `::before` accent. Comms rows become `ops-queue-row` (list variant, same typography scale).

---

### Chip taxonomy (reference)

Use the right chip family — do not invent new badge classes for the same semantic layer.

| Family | Class prefix | Owns | Modifier source |
|--------|--------------|------|-----------------|
| **Disposition** | `ops-state-pill` | Estimate line disposition (draft, recommended, approved, deferred, declined) | Static modifiers: `--draft`, `--recommended`, `--approved`, `--deferred`, `--declined` |
| **RO status** | `ops-status-chip` | Repair order workflow status on index cards | `RepairOrderStatus::indexTone()` → `--{tone}`; card sets `--ops-accent-*` via `ops-ro-card--{tone}`; `--closed` is explicit gray |
| **Parts pressure** | `ops-parts-pressure-chip` | Parts waiting / partial / backordered | `PartsPressure::chipTone()` → `--waiting`, `--partial`, `--backordered` |
| **Vehicle identity** | `ops-vehicle-identity-pressure-chip` | VIN / vehicle identity gaps | `VehicleIdentityPressure::chipTone()` → `--no-vin`, `--missing`, … |
| **Customer identity** | `ops-customer-identity-pressure-chip` | Customer contact / billing gaps | `CustomerIdentityPressure::chipTone()` → `--incomplete`, `--critical`, … |
| **RO flag** | `ops-ro-flag` | Lightweight RO markers (e.g. intake) | `--intake` |
| **Count (pressure)** | `ops-pressure-count` | Section / band / sidebar counts — tabular-nums, not title text | `--inline` for parenthetical rail counts |
| **Topbar bubble** | `ops-call-queue__count` | Live comms interrupt count | Rose bubble; typography aligned with pressure-count (600, tabular-nums) — **do not** add `ops-pressure-count` (color conflict) |

**Count rule:** Counts live in a separate `span` with `ops-pressure-count` or `ops-rail-link__count`, never concatenated into band labels.

---

## 4. Color semantics

Colors mean **operational state**, not decoration. Five meanings — no sixth without constitution amendment.

| Meaning | Color family | ARK tone key | Use |
|---------|--------------|------------|-----|
| **Information** | Blue (`blue-500` lane, sky chips) | `motion` | In progress, active work, production |
| **Completed / Ready** | Green (`emerald-500`) | `ready` | Pickup ready, qual complete, inbound answered |
| **Waiting** | Orange (`orange-500`) | `approval` | Awaiting approval, customer decision |
| **Attention required** | Amber / Rose | `blocked`, live pressure | Blocked work, missed calls, overdue, sidebar `--pressure-live` |
| **Neutral** | Slate | `move`, `closed` | Draft/intake queue, closed, metadata |

**Rules:**
- Rose is for **time-sensitive human attention** (hot age, live calls, shift pressure) — not generic errors.
- Red (`rose-800` counts) is reserved for **interrupt** surfaces (topbar bubble, sidebar live pressure) — not lane borders.
- Do not invent per-surface palettes (e.g. violet recording chip is OK as **channel hint**, not a sixth workflow meaning).

**Drift today:**
- `ops-ro-card--{tone}` modifiers applied in Blade but **mostly unstyled** — lane color lives on band only.
- `ops-status-chip--{tone}` only styles `--closed`; other tones share default.
- Recording/voicemail use violet/amber ad hoc in comms rows — keep as channel hints, document in chip taxonomy.

---

## 5. Pressure badges

One component family for all counts.

### Sidebar nav count

**Today:** `ops-rail-link__count` with modifiers `--pressure`, `--pressure-shift`, `--pressure-live`

| Modifier | Meaning |
|----------|---------|
| default | Neutral count |
| `--pressure` | Rose count |
| `--pressure-shift` | Amber band background + amber count (since last shift) |
| `--pressure-live` | Rose band background + pulse (live calls) |

### Topbar interrupt

**Today:** `ops-call-queue__count` — rose pill, pulse variants

### Band / header count

**Today:** inconsistent — `(N)` in title string, separate span, or `text-slate-500` parentheses

**Target:** `ops-pressure-count` — tabular-nums, 11px, semibold, always **outside** title text:

```html
<span class="ops-pressure-count">{{ $count }}</span>
```

Same in sidebar, band headers, and queue page headers.

---

## 6. Surface ownership

Each major surface owns **one pressure type**. UI that belongs to another surface should move.

| Surface | Owns | Does not own |
|---------|------|----------------|
| **Communications** | Comms interrupt + recovery queue | RO lifecycle lanes, qual checklist |
| **Intake** | Recognition + qualification pressure | Approved work blocking, parts pressure |
| **Operations (workboard)** | Lifecycle lanes in the building | Customer decision cohorts (→ Work/Attention) |
| **Work (Attention)** | Morning triage — customer decision, tasks, follow-ups, comms snapshot | Full comms history |
| **Customers** | Identity + relationship context | Estimate editing |
| **Vehicles** | Vehicle truth on hub / intake | Standalone vehicle CRM |
| **Repair Order workspace** | Scope, estimate, workflow on one RO | Shop-wide queues |
| **Settings** | Shop configuration | Operational metrics |

**Test:** If a widget answers a question another surface already owns, it is drift.

---

## 7. Drift register (2026-06-13 audit)

Prioritized fixes — behavior preserved, substrate aligned.

### P0 — Header unification

| Issue | Location | Fix |
|-------|----------|-----|
| Three queue header systems | workboard, intake, work, comms | [x] `queue-page-header` + `queue-band-header` |
| Comms triple headers | `queue.blade.php`, `channel-tabs`, `queue-section` | [x] Page header once; sections use band header |
| Decision lane notes dropped | `customer-decision-pressure-section.blade.php` | [x] `note` as band description |
| Count in title string | `queue-comms-attention.blade.php` | [x] `ops-pressure-count` span |
| Intake missing page header | `intake/index.blade.php` | [x] Shared `queue-page-header` |

### P1 — Card convergence

| Issue | Location | Fix |
|-------|----------|-----|
| Two accent systems | `ops-ro-card` vs `ops-intake-qual-card` | [x] `ops-queue-card` shared substrate |
| Card tone modifiers unused | workboard Blade | [x] Lane accent + status chip tones per `indexTone()` |
| Comms row dual markup | `queue-row.blade.php` vs `call-queue.blade.php` | [x] Shared `queue-row` interrupt + API `html` fragment |
| Dead Blade/CSS | `ops-index-row`, unused comms strip partial | [x] Removed; `ops-work-comms-strip` partial deleted (never included) |

### P2 — Index hygiene

| Issue | Location | Fix |
|-------|----------|-----|
| Appointments toolbar h2 | `appointments/index.blade.php` | [x] Note-only toolbar + shared `schedule-row` |
| `ops-index` on Work home | `home.blade.php` | [x] `ops-work-surface` only |
| Intake workspace toolbar bleed | `intake/create.blade.php` | [x] `workspace-context-band` |

### P3 — Token cleanup

| Issue | Fix |
|-------|-----|
| `ops-status-chip` tone gaps | [x] Explicit chip + card accent modifiers for all `indexTone()` values |
| Chip taxonomy sprawl | [x] §3 Chip taxonomy — `ops-state-pill`, `ops-status-chip`, `ops-pressure-chip` |
| Settings staff grid | [x] `ops-index-results-columns--staff` + row grid aligned with index results head |

---

## 8. Implementation plan

**Principle:** Substrate first, surface passes second. No feature freeze — new work uses target components even before old surfaces migrate.

### Phase 0 — Document + lint (this PR)

- [x] This constitution in `docs/ark-v2-interface-constitution.md`
- [x] Doctrine pointer: `ark-interface-constitution.mdc` → link here + PR checklist

### Phase 1 — Shared components (1–2 passes)

1. [x] `resources/views/components/operations/queue-page-header.blade.php`
2. [x] `resources/views/components/operations/queue-band-header.blade.php`
3. [x] CSS: `ops-queue-band--{tone}` aliases lane top borders
4. [x] CSS: `ops-pressure-count`
5. [x] Wire **Communications** full page first (most header drift) — validate visually
6. [x] Wire **Intake** index page header + band header
7. [x] Wire **Operations** workboard bands via shared band header partial

### Phase 2 — Card substrate

1. [x] Define `ops-queue-card` in `app.css` — copy `ops-ro-card` tokens, add qual modifiers
2. [x] Migrate `qualification-card.blade.php` to extend queue card
3. [x] Align workboard `ops-ro-card` — drop unused tone classes on cards
4. [x] Introduce `ops-queue-row` for comms + decision list rows (shared headline/meta/action classes)

### Phase 3 — Badge + sidebar pass

1. [x] Unify sidebar count typography with `ops-pressure-count` weights
2. [x] Document chip modifiers in a single partial reference table (§3 Chip taxonomy)
3. [x] Align topbar bubble typography with count semantics (`ops-call-queue__count` tabular-nums + weight 600)

### Phase 4 — Index + settings cleanup

1. [x] Appointments toolbar normalization
2. [x] Remove dead CSS (`ops-index-row`, orphan comms strip)
3. [x] Work home shell class cleanup (`ops-work-surface` only)

### Phase 5 — Workspace boundaries

1. [x] Intake create: `x-operations.workspace-context-band` (not retrieval toolbar)
2. [x] RO mode headers: `x-operations.mode-header` on edit + estimate review
3. [x] Mode badge tokens aligned (`ops-mode-header-badge` → band-label scale)

### Verification

- [x] Feature tests on migrated surfaces (appointments, intake bands, comms rows, staff grid, work queues)
- Visual regression: screenshot Work, Communications, Intake, Operations at 1280px and 1024px — **manual shop-floor check** when convenient
- Shop floor: advisors should not notice a "redesign" — only that surfaces **rhyme**

### Substrate complete — intentional exceptions

| Area | Exception | Why |
|------|-----------|-----|
| **Vehicles** | ~~No global index~~ | `/app/vehicles/search` — plate/VIN/YMM/customer retrieval |
| **Index tabular** | Staff roster, schedule week list | Settings admin + schedule time-grid — not `ops-ro-card` retrieval |
| **Workspace hybrid** | Intake AJAX customer search | Card grid inside workspace shell — acceptable, not a fifth page type |
| **Comms full page** | Channel tabs under page header | Filter strip for channel scope — not a duplicate page title |
| **Recent Activity** | Inline `h3` + JS count on comms queue | Fragment refresh; migrate to `queue-band-header` when comms page is next touched |

---

## Shared components (use these)

| Component | Path | When |
|-----------|------|------|
| Queue page header | `components/operations/queue-page-header.blade.php` | Full-page queue (Work queues, Intake, Comms tone) |
| Queue band header | `components/operations/queue-band-header.blade.php` | Lane (`variant=lane`), section, home compact bands |
| Work home band | `work/partials/work-queue-band-header.blade.php` | Work home + decision lanes — delegates to `home` variant |
| Workspace context | `components/operations/workspace-context-band.blade.php` | RO workspace, intake create — not retrieval toolbar |
| Mode header | `components/operations/mode-header.blade.php` | Review / Edit badge on RO surfaces |
| Pressure count | `components/operations/pressure-count.blade.php` | Sidebar, bands, headers — never concatenate into titles |
| Queue row | `components/operations/queue-row.blade.php` | Comms list + call-queue interrupt (`variant=interrupt`) |
| Schedule row | `appointments/partials/schedule-row.blade.php` | Work schedule band + appointments index |

**Card family:** `ops-queue-card` / `ops-ro-card` (retrieval + workboard). **Qual intake:** `qualification-card` extends queue card.

---

## 9. File reference (audit inventory)

### Layout & chrome

| File | Role |
|------|------|
| `resources/views/components/operations/app.blade.php` | Shell, rail, topbar |
| `resources/views/components/operations/workspace-tabs.blade.php` | Tab bar |
| `resources/css/app.css` | All ops tokens (~7000–9500 components layer) |

### Queue surfaces

| File | Type |
|------|------|
| `resources/views/operations/home.blade.php` | Queue (Work) |
| `resources/views/operations/work/queue.blade.php` | Queue (full pages) |
| `resources/views/operations/index.blade.php` | Queue (Operations) |
| `resources/views/operations/intake/index.blade.php` | Queue (Intake) |
| `resources/views/operations/work/partials/work-queue-band-header.blade.php` | Band header partial |
| `resources/views/operations/communications/partials/queue-section.blade.php` | Comms section (`queue-band-header` section variant) |
| `resources/views/operations/communications/partials/queue-row.blade.php` | Comms row |

### Cards

| File | Card family |
|------|-------------|
| `resources/views/operations/index.blade.php` | `ops-ro-card` workboard |
| `resources/views/operations/intake/partials/qualification-card.blade.php` | `ops-intake-qual-card` |
| `resources/views/operations/repair-orders/index.blade.php` | `ops-ro-card` retrieval |
| `resources/views/operations/customers/search.blade.php` | `ops-ro-card` customer |
| `resources/views/operations/attention/partials/customer-decision-pressure-rows.blade.php` | list rows |

### Index

| File | Notes |
|------|-------|
| `resources/views/operations/customers/search.blade.php` | Canonical customer index |
| `resources/views/operations/vehicles/search.blade.php` | Canonical vehicle index |
| `resources/views/operations/repair-orders/index.blade.php` | Canonical + pagination |
| `resources/views/operations/caller-lookup/index.blade.php` | Canonical |
| `resources/views/operations/appointments/index.blade.php` | Canonical schedule index (`week-schedule-list`) |
| `resources/views/operations/appointments/partials/schedule-row.blade.php` | Shared schedule row |

### Settings

| File | Notes |
|------|-------|
| `resources/views/operations/settings/shop.blade.php` | Primary settings shell |
| `resources/views/profile/edit.blade.php` | Profile mirror |

### Authority for tones

| File | Role |
|------|------|
| `app/Ark/Operations/RepairOrders/RepairOrderStatus.php` | `indexTone()` |
| `app/Ark/Operations/Workboard/WorkboardLens.php` | Lane labels, intake bands |

---

## 10. Amendment process

1. Propose change in PR description — which section and why.
2. If adding a **sixth color meaning** or a **new page type**, update this doc in the same PR.
3. Prefer extending `ops-queue-card` modifiers over new card families.
4. UI substrate changes do not require product doctrine changes unless surface ownership shifts.

---

*Generated from codebase audit 2026-06-13. Update the drift register when phases complete.*
