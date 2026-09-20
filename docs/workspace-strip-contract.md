# ARK V2 Workspace Strip Contract

**Status:** Spec only — do not implement until this document is accepted.  
**Version:** 1.0 — 2026-06-14  
**Surfaces:** Repair Order estimate workspace (`operations.repair-orders.show`, `operations.repair-orders.show`).  
**Predecessor insight:** ARK-SMS sticky bar audit — orientation + primary action reachability, not bottom-dock clutter.

---

## 1. What this is

The **workspace strip** is **persistent workspace state**, not a footer, not a command dock, not a second identity band.

It answers one question while the advisor is deep in concerns:

> Who is this RO, what mode am I in, and what is the one thing I should do next?

```
RO #1457 · Brianna Watson · 2003 Subaru Outback          [VIEW ▼]  [Send Estimate]
```

**Height:** 40–48px total. One row. No second row. No full-width chrome slab.

---

## 2. What this is not

| Not this | Why |
|----------|-----|
| `#ark-ro-sticky-bar` port | Bottom fixed, clone-to-sticky JS, 15-button matrix |
| Floating command dock | Action-first; becomes another button farm |
| Mode banner row | Removed for height; mode lives on the strip |
| Replacement identity band | Three-column band stays authoritative at top |
| Financial / comms rail | Money and conversation stay in rails |
| Global app chrome | RO workspace only |

---

## 3. Placement in layout

```
ops-main
└── ops-estimate-workspace [data-worksheet-root]
    ├── worksheet banners / flash
    ├── ops-review-shell
    │   ├── #ro-identity-band (full identity band — scrolls away)
    │   └── #review-toolbar (PDFs, lifecycle, assignee, mode control today)
    ├── [SENTINEL — see §4]
    ├── ops-workspace-strip (hidden until sentinel triggers — sticky top)
    └── estimate layout (concerns scroll with document)
```

**Scroll authority:** Document scroll (`window`) — same as `ark-ro-workspace-memory.js`. The strip does not create an inner scroll container.

**Sticky stack:** Strip sticks below workspace tabs + topbar offset (`--ops-sticky-stack-offset`). Solid background. `z-index` above concern content, below modals/comms interrupt. Document z-index in interface constitution before shipping.

---

## 4. Scroll sentinel behavior

**Goal:** No double chrome. Full identity visible → strip hidden. Identity scrolled away → strip visible.

**Mechanism:** `IntersectionObserver` only — **no** `scrollTop` listeners.

| Element | Role |
|---------|------|
| `#ro-identity-band` | Observed target (or a dedicated sentinel immediately after identity band) |
| `ops-workspace-strip` | Toggles class `is-visible` on workspace root or strip element |

**Rules:**

1. When the identity band is **fully intersecting** the viewport (or sentinel above fold threshold), strip is **hidden** (`hidden` / `aria-hidden="true"` / no pointer events).
2. When the identity band **leaves** the viewport upward (user scrolled down into concerns), strip **appears** and becomes `position: sticky` at the contracted top offset.
3. Threshold: `rootMargin` tuned so strip appears as soon as customer/vehicle columns leave view — not after entire `ops-review-shell` exits.
4. Observer must **not** fire writes on GET; visibility is presentation-only.
5. Strip appearance must **not** reset scroll position or fight `ark-ro-workspace-memory` restore.
6. On mode switch (View ↔ Edit navigation), strip state re-derives from observer on load — no session persistence of strip visibility.

**Forbidden:** Strip always visible. Strip fixed at bottom. Strip inside concern cards.

---

## 5. Visible states

| State | Condition | UI |
|-------|-----------|-----|
| **Hidden** | Identity band in view | Strip not in layout flow or `display: none`; zero height reserved |
| **Sticky** | Identity band scrolled away | Single row visible, sticky top, solid `bg-white` + border-bottom |

No collapsed/half states in v1. No animation beyond optional 120ms opacity (no height animation).

---

## 6. Identity fields (exact)

Single line, left side, ellipsis on overflow. Order fixed:

| # | Field | Source | Format |
|---|--------|--------|--------|
| 1 | RO number | `$repairOrder->repair_order_id` | `RO #1457` |
| 2 | Customer | Identity projection / customer display name | `Brianna Watson` |
| 3 | Vehicle | Vehicle identity line | `2003 Subaru Outback` |

**Separator:** Middle dot ` · ` between segments (space-dot-space).

**Omit from strip:** VIN, phone, email, mileage, plate, visit posture, waiting-parts narrative, incomplete-customer warnings, technician name, totals, concern summary. Those remain on the full identity band or rails.

**Missing data:** Use existing operational dash/placeholder conventions — do not invent new empty states.

---

## 7. Mode control

Reuse existing mode control behavior — do not fork logic.

| Item | Contract |
|------|----------|
| Component | Same affordance as `repair-order-mode-control` (View ▼ / Edit ▼) |
| Placement | Strip right cluster, before primary action |
| Toggle | Click + `V` shortcut (`ark-ro-mode-control` / `ark-keyboard-shortcuts`) |
| Unsaved edit | Same confirmation modal (Save & Switch / Discard & Switch / Cancel) |
| Visual | Rose (Edit) / emerald (View) on control only — **no** full-strip tint |
| Tooltip | `Toggle Mode (V)` |

**Remove from toolbar when strip ships:** Duplicate mode control in `#review-toolbar` workflow section — strip owns mode while implemented on RO workspace. Toolbar keeps lifecycle/assignee/PDFs.

---

## 8. Primary action (one only)

**Rightmost control.** One button or one link. Label from server projection — never hardcoded in Blade per status string.

### Authority

New read-only projection: `RepairOrderWorkspaceStripProjection` (name fixed at implement time).

Inputs (existing truth only):

- `RepairOrder` status + lifecycle posture
- `RepairOrderLifecycleSelectProjection` (blocking reasons — if blocked, primary may be disabled with reason)
- Mode (`review` vs `edit`)
- Financial snapshot (invoice issued, balance due) — for **label/disabled** only, not amounts on strip
- Vehicle/customer identity pressure (e.g. VIN required before send) — disable + title, not strip clutter
- Permissions

**No** parallel `command_actions` JSON matrix. **No** client-side primary pick from a meta map. **No** AI-first CTA.

### v1 action catalog (exhaustive allowlist)

Implement only these keys in v1; expand by contract revision, not ad hoc Blade.

| Key | Label (example) | Mode | When |
|-----|-----------------|------|------|
| `send_estimate` | Send Estimate | review | Estimate posture, lines present, not terminal, manage permission |
| `open_comms` | Text Customer | review or edit | No send-estimate posture but customer comms appropriate; deep-link `#customer-communication` or compose |
| `add_concern` | Add Concern | edit | Builder, not terminal, manage permission, not locked |
| `view_estimate_pdf` | Estimate PDF | review or edit | Fallback when no higher-priority action; opens PDF route |
| `none` | — | any | Terminal, or no allowed action — hide primary slot |

**Precedence (first match wins):**

1. Lifecycle/financial **block** with user-visible reason → primary disabled, `title` = blocking reason (no click).
2. `send_estimate` when review + estimate send allowed.
3. `add_concern` when edit + builder open.
4. `open_comms` when conversation initiation appropriate and higher actions unavailable.
5. `view_estimate_pdf` as read-only fallback.
6. `none` — empty primary slot; strip still shows identity + mode.

**Not primary in v1:** Take payment, collect deposit, send payment link, assign tech, change status, print key tag, oil sticker, tech sheet, intake sheet, FOB, POS display, finalize invoice, reset approval, preview estimate (unless merged into send flow).

Secondary PDFs stay in `#review-toolbar`.

### Primary interaction

| Type | Behavior |
|------|----------|
| Link | Navigate or `target="_blank"` for PDF |
| Button | Existing pathways (e.g. Send Estimate → `#communication-rail` or conversation action — same as toolbar trailing today) |

Success/error toasts use existing notify patterns. No new toast system.

---

## 9. Status on strip

**Default: omit.**

Lifecycle status and technician assignment stay in `#review-toolbar` (`repair-order-lifecycle-select`, technician select).

Optional **read-only** status chip on strip only if user testing shows disorientation **and** it does not duplicate the lifecycle dropdown label. If added later: short label (`In Progress`), no dropdown on strip, max 1 chip.

---

## 10. Permission gates

| Actor | Identity line | Mode | Primary action |
|-------|---------------|------|----------------|
| Advisor with `repair_orders.manage` | Yes | Toggle View/Edit | Full v1 catalog |
| Advisor lifecycle-only | Yes | View only on review; edit if can open builder | Gated by existing capabilities |
| Technician (`repair_orders.view`, no manage) | Yes | Static **View** label | `view_estimate_pdf` or `none` |
| Terminal RO | Yes | View or static mode | `view_estimate_pdf` or `none` |

Reuse `ArkCapability` and existing `@can` gates — no new RBAC.

---

## 11. Terminal RO behavior

- Strip may appear when identity scrolls away (same sentinel).
- Mode: no Edit toggle if terminal; static **View** (or hidden mode control if review-only surface).
- Primary: `view_estimate_pdf` if PDF route allowed, else empty.
- No send, add concern, or payment actions on strip.

---

## 12. Mobile behavior

**v1 scope: desktop operations first** (`min-width: 992px`).

Below 992px:

- Strip **hidden** entirely, OR single-line identity without primary (choose at implement — default **hidden** to avoid fighting mobile layout).
- Do not ship mobile FAB or bottom dock in v1.

Staff mobile app (`arksms_shop`) is out of scope.

---

## 13. Must not appear on the strip

Hard prohibition list for v1:

- Payment, deposit, invoice, balance due amounts
- Assign technician / lifecycle `<select>`
- Print key tag, oil sticker, tech sheet, intake sheet (except PDF fallback key above)
- Parts ledger, PartsTech, labor guide launchers
- Customer display / POS / FOB / present-on-tablet
- Comms rail content, message threads, phone/SMS/email icon row
- Estimate totals, GP, parts pressure chips
- Concern counts, waiting-parts lists
- Multiple primary buttons or icon-only button groups
- More than one secondary action
- Workspace tabs, breadcrumbs, save draft
- AI suggestions, phase SLA, “next action” badges from heuristics
- Green/red wash across full strip background

---

## 14. Implementation constraints (when build starts)

**Smallest possible v1:**

1. One Blade partial + one CSS block + one JS module (`IntersectionObserver` + class toggle).
2. One projection class returning: identity triple, mode, primary action descriptor `{ key, label, href|action, disabled, title }`.
3. Reuse `arkRoModeControl` — register strip instance or single mode control per page (no duplicate `V` targets).
4. Controller passes projection to show + estimate-review blades only.
5. Remove duplicate mode control from workflow toolbar when strip ships.
6. Query composition: projection computed once per GET in controller — **zero** strip-driven queries in Blade loops.
7. Tests: feature tests for projection keys by status/mode/permission; DOM assert strip hidden when sentinel mocked in view tests optional.

**Files likely touched (implement phase only):**

- `resources/views/operations/repair-orders/show.blade.php`
- `resources/views/operations/repair-orders/estimate-review.blade.php`
- `app/Ark/Operations/RepairOrders/RepairOrderWorkspaceStripProjection.php` (new)
- `resources/views/operations/repair-orders/partials/workspace-strip.blade.php` (new)
- `resources/js/ark-workspace-strip.js` (new)
- `resources/css/app.css` (strip tokens)
- `docs/ark-v2-interface-constitution.md` (anatomy line — after ship)

---

## 15. Success criteria

1. Scrolled 30+ concerns: RO #, customer, vehicle still readable in strip.
2. Mode obvious without scrolling to toolbar.
3. One primary action reachable without scrolling to toolbar.
4. At top of page: full identity band visible, **no** strip (no double chrome).
5. No new authority stores, no GET mutations, no convenience-layer fan-out in Blade.
6. Strip height ≤ 48px measured in browser.

---

## 16. Changelog

| Version | Date | Change |
|---------|------|--------|
| 1.0 | 2026-06-14 | Initial contract from ARK-SMS footer audit synthesis |
