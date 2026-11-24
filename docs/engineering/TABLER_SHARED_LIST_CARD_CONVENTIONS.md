# Shared Tabler List / Card conventions

**Status:** Architecture proposal for Customers, Vehicles, and Repair Orders.  
**Not implemented in this assignment** (Job Board Cards only).  
**Depends on:** Tabler as sole design system; Job Board List (#254) + Card hierarchy PRs as reference implementations.

## Goal

Customers, Vehicles, and Repair Orders share interaction patterns (List/Cards, search, filters, pagination, empty states, accessibility) while keeping module-specific content.

## Current state (audit)

| Module | Shell | Search/filter | Layout | Pagination | Notes |
|--------|-------|---------------|--------|------------|-------|
| Customers | Tabler fluid | `x-tabler.search-filters` | `.ark-card-grid` + `x-tabler.record-card` | Laravel 24 + bootstrap-5 | Cards only today |
| Vehicles | Tabler fluid | same | same | same | Cards only today |
| Repair Orders index | Tabler fluid | same + many drill-down query keys | same | same | Cards only; fixed sort |
| Job Board | Tabler fluid | custom toolbar | List table + Cards grid | Server 50/page | List/Cards toggle + cookie |

## Proposed shared conventions

### 1. Layout selector

- Control: Tabler `nav-segmented` with List | Cards (Job Board pattern).
- Preference key: per authenticated user + tenant + module (`customers` | `vehicles` | `repair_orders` | `job_board`).
- Responsive default: Cards below `md`, List from `md` up, until the user chooses (Job Board `auto`).

### 2. Search and filtering

- Prefer `x-tabler.search-filters` for record indexes.
- Job Board may keep its denser toolbar; extract shared pieces (search input icon, employee/select, clear filters) only when duplication hurts.

### 3. Sortable headers (List)

- First-party Tabler `table table-vcenter card-table table-sm` (no DataTables).
- Reuse Job Board sort-link partial pattern: toggle dir, default dir per column, `aria-sort`, visible active state.
- Server-side sort of the filtered set before pagination.

### 4. Pagination and counts

- “Showing X–Y of Z” + optional queue total.
- Laravel paginator or Job Board page DTO; `pagination::bootstrap-5` for record indexes.

### 5. Empty states

- `x-tabler.empty-state` with clear-filters when filters active.

### 6. Responsive cards

- `.ark-card-grid` with `--ark-card-min-width`.
- Card composition bands (identity → secondary → body → meta → actions) as established on Job Board Cards.

### 7. Navigation and actions

- Primary record open = title/identity link.
- Secondary hubs (customer) stay separate destinations (Job Board click contract).
- Page actions via `x-tabler.page-action`.

### 8. Accessibility

- `aria-current` on layout toggle and active filters/lanes.
- `aria-sort` on active columns.
- Focus-visible on sort controls and card links.
- Do not rely on color alone for Attention/Critical (text weight + status strip).

## Shared-component plan (next slices)

| Component | Responsibility | First consumer |
|-----------|----------------|----------------|
| `x-tabler.layout-toggle` | List/Cards segmented control + a11y | Job Board (extract), then RO index |
| `x-tabler.collection-summary` | Showing X–Y of Z / clear filters | Job Board + record indexes |
| `x-tabler.sortable-th` | Sort link + aria-sort chrome | Job Board extract → RO list later |
| Module preference helper | Resolve layout without `?view=` pollution | See preferences doc |

## Out of scope here

- Redesigning Customers or Vehicles UI.
- Installing DataTables/Yajra.
- Implementing preference storage migration.
