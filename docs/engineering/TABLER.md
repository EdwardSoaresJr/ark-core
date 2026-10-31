# Tabler UI migration

Tabler is Core's design system for staff screens. Views move to it one at a time. ARK Shop renders Core's views, so it inherits each migrated view with no Shop change.

## How a view opts in

```blade
<x-operations.app title="Customers" ui="tabler">
```

`ui="tabler"` renders the Tabler shell (`components/operations/shell/tabler.blade.php`) and loads, in order, `app.css`, `tabler.css`, `app.js`, `tabler.js`. Every other view keeps the legacy shell and never loads Tabler.

Tabler cannot load globally. 159 class names exist in both `app.css` and Tabler with different meanings, and Tabler's utilities are `!important`. Examples: `.space-y-4` becomes a flex column, `.w-4` is 1.5rem instead of 1rem, and Tailwind's `.collapse` hides content.

## The shell

- Same component, `components.operations.app`. ARK Shop's view composer and the `commercial-scripts` stack keep working.
- The account menu mirrors `components/operations/user-menu.blade.php`, including the `data-ark-workstation-*` hooks.
- The Communications link keeps `data-ops-comms-nav-link`. `ark-comms-nav-pressure.js` updates its Tabler badge live.
- The header is `navbar-expand`, so its dropdowns float at every width. A header that collapses (`navbar-expand-md`) renders `.navbar-nav` dropdowns in the page flow on phones.
- Below `lg`, the channel strip becomes one icon with the "All" count and a dropdown of the same channels and links. From `lg` up the legacy strip renders unchanged. `TablerShellTest` fails if the two link to different places. This is presentation only: the same `$commsChannelTabs`, counts, and URLs.
- Theme: the shell sets `data-bs-theme` and the legacy `html.dark` class together. The user's accent drives `--tblr-primary`.

## Navigation

Required end state: one navigation definition. Sections, items, permission checks, active routes, and live counts are declared once and rendered by whichever shell a view uses. The account menu follows the same rule.

Today there are two: the legacy rail markup in `components/operations/app.blade.php` and the `$navSections` array in `components/operations/shell/tabler.blade.php`. They read the same permission flags, and `TablerShellTest` compares their destinations for three roles. Until they are merged, any change to a destination changes both files in the same commit.

Merging them means rendering the legacy rail from the shared definition too, which changes legacy markup that other tests assert on. That is its own reviewed change. The trigger is in [TECHNICAL_DEBT.md](TECHNICAL_DEBT.md#two-staff-navigation-definitions).

## Legacy components on a Tabler page

These are not migrated yet and render inside `.ark-legacy`: channel strip (`lg` and up), workstation presence and station lock, workspace tabs, comms pressure bar, comms interrupt panel, global search, image lightbox, guide modal, call queue, print helpers, and the repair-order orientation dock.

`.ark-legacy` restores the Tailwind preflight element defaults those components were built on. When a component migrates, remove its wrapper. When none remain, stop loading `app.css` on Tabler pages and delete the coexistence rules in `tabler.css`.

Known differences inside `.ark-legacy`:

- Tabler's `a:hover` outranks a single-class legacy link color on hover.
- Toasts are appended to `<body>` by `ark-ops-toast.js`, outside the wrapper.
- The demo-install bar is not wrapped.

## Workspace layouts

Every migrated view picks one layout. `layout` is required, and an unknown value throws:

```blade
<x-tabler.page layout="fluid" title="Customers" pretitle="Records" subtitle="…">
    <x-slot:actions>…</x-slot:actions>
    …
</x-tabler.page>
```

`<x-tabler.page>` renders the page header and body with the same container, so they always align. The shell header and banners are `container-fluid` on every Tabler page.

| Layout | Container | Use for |
| --- | --- | --- |
| `fluid` | `container-fluid` | Lists and indexes: customers, vehicles, repair orders, reports. |
| `focused` | `container-narrow` (61.875rem) | Account forms, settings, onboarding: one column of reading or input. |
| `split` | `container-fluid`; the body fills the remaining height (`d-flex flex-column flex-fill`) | Multi-panel workspaces: communications, customer hub. The slot is one `.row` of panels; each panel is a `.card` that scrolls on its own. |
| `canvas` | `container-fluid px-0`; the body fills the remaining height with no bottom margin | Boards and grids that manage their own spacing: schedule, job board, inspections. |

All four use Tabler's page padding (`--tblr-page-padding`: 1rem from `lg`, 0.5rem below).

`split` and `canvas` are defined but have no migrated view yet. The first view of each kind confirms the height behavior and updates this table.

Sign-in, password reset, and first-run setup are not in the staff shell. When they migrate they use Tabler's `page-center` with `container-tight` (32rem) in the guest layout.

### Record card grid

Fluid lists of record cards use `.ark-card-grid` instead of `.row.row-cards` with `col-*` classes. It adds columns as the workspace widens. With two or more columns each card is between 17rem and about 26rem wide; with one column, on phones, the card fills the width:

| Viewport | Columns | Card width |
| --- | --- | --- |
| 390 | 1 | 374px |
| 820 | 2 | 398px |
| 1440 | 4 | 276px |
| 1920 | 5 | 314px |
| 2560 | 7 | 311px |

Bootstrap's breakpoints end at 1400px, so `col-*` classes give the same column count at 1440 and 2560. `.ark-card-grid` uses `auto-fill`, not the `auto-fit` of Tabler's `.datagrid`, so a single result stays one column wide. Set `--ark-card-min-width` on the grid if a card needs a different minimum. It is the only layout rule in `tabler.css`.

Filter rows on fluid pages switch fields to their natural width from `xxl` (`col-xxl-auto`) so they do not stretch across an ultrawide screen. The search input takes the remaining width.

## Conventions for a migrated view

- Markup is Tabler's. No `ops-*` classes, no Tailwind utilities, and no new CSS. `tabler.css` holds theme tokens, coexistence rules, and `.ark-card-grid`.
- Page: `<x-tabler.page layout="…">` with an `actions` slot. See "Workspace layouts".
- Forms: `.form-label`, `.form-control`, `.form-select`, `.input-icon`. Native `type="date"`. Keep field `name`s and `id`s.
- Buttons: `.btn`, `.btn-primary` for the one main action, `.btn-ghost-secondary` for Clear, grouped in `.btn-list`.
- Icons: `<x-tabler.icon name="…">`. Add an icon by copying its paths from the `@tabler/icons` outline set into that component.
- Repair-order status: `<x-tabler.status-badge :tone="$status->indexTone()">` and `RepairOrderStatusColor::tablerColorForChipTone()` for `card-status-start`. Colors follow the shop's configured status colors.
- Lists: `.ark-card-grid` of `<x-tabler.record-card>`, or a `.card` with `.table-responsive`.

### Shared components

Build record pages from these. Do not copy their markup into a view.

| Component | Renders |
| --- | --- |
| `<x-tabler.page layout="…">` | Page header and body in the layout's container. |
| `<x-tabler.page-action :href icon label [primary]>` | A header button. Secondary actions add an icon-only twin with a tooltip below `sm`. |
| `<x-tabler.search-filters id action query placeholder label clear-url has-filters active-filter-count>` | The search card: search box and button, the phone Filters toggle and count, and the collapsible filter row. The slot holds the filter columns; an optional `hidden` slot holds hidden inputs. Field ids are `{id}-search` and `{id}-filters`. |
| `<x-tabler.record-card :tone :actions>` | A `.card-sm` with the status color bar, the slot as its body, and the open-in icon row from `operations.partials.tabler-index-card-actions`. |
| `<x-tabler.empty-state icon clear-url>` | The empty result, with "Clear search" when `clear-url` is set. |
| `<x-tabler.status-badge :tone>` | A repair-order status badge. |
| `<x-tabler.vehicle-identity-chip :vehicle>` | "VIN missing" with its hint as a tooltip. The legacy chip partial stays for unmigrated repair-order screens. |
| `<x-tabler.icon name>` | A Tabler Icons outline icon. |
- Pagination: `$paginator->links('pagination::bootstrap-5')`.
- Tooltips, dropdowns, collapse, and modals use Tabler's `data-bs-*` attributes. Do not add Alpine or Preline for those.
- Business logic, routes, permissions, and query parameters do not change. The controller is not touched.

### Phones

Design the phone layout first, not as a squeezed desktop.

- The primary task is on screen without scrolling. On a search page that is the search box, its button, and the first result.
- Secondary filters sit in a `.collapse.d-md-block` panel behind a "Filters" toggle that is `d-md-none`. The panel opens on load when a filter is active, and the toggle shows the active count.
- Labels that repeat a placeholder are `d-none d-md-block`. The input then carries an `aria-label`.
- Page subtitles are hidden below `md` by `<x-tabler.page>`.
- Secondary header actions are icon-only below `sm`: a labelled `.btn.d-none.d-sm-inline-flex` plus a `.btn.btn-icon.d-sm-none` with `aria-label`. The primary action keeps its label.
- Card bodies and search cards use `p-2 p-md-3`. Touch targets stay at Tabler's default button size.

## Checklist per view

1. Record its current behavior and the legacy classes it uses (below).
2. Migrate the Blade view only.
3. Run its feature tests and `TablerShellTest`. Update assertions that named legacy classes to assert the same behavior. `TablerShellTest` compares the Tabler sidebar with the legacy rail on the Job Board (`operations.index`); when that view migrates, point those tests at another legacy page.
4. Check 390, 820, 1440, 1920, and 2560 wide, light and dark, for horizontal overflow and stretched content: forms, links, pagination, dropdowns, tooltips, the station overlay, and Ctrl/⌘K search.
5. List what still depends on `app.css`.
6. Stop for review before the next view.

## Migration log

### Customers (`operations.customers.search`)

Chosen first: read-only, no money, used daily, and it covers a filter form, a date range, status chips, a permission-gated action, intake mode, an empty state, and pagination.

Before: `ops-index`, `ops-board-shell`, `ops-page-toolbar`, `ops-page-link`, `ops-index-filters`, `ops-index-field`, `ops-index-btn`, `ops-date-field`, `ops-ro-retrieval-grid`, `ops-job-card*`, `ops-ro-card--{tone}`, `ops-status-chip--{tone}`, `ops-index-empty`, and the Tailwind pagination view.

After: Tabler only, `fluid` layout with `.ark-card-grid`. On a 390px phone the first result starts about 350px down instead of about 790px: Type, From, and To collapse behind "Filters", the subtitle is hidden, and Vehicles and Workboard are icon buttons. Unchanged: query parameters (`q`, `type`, `created_from`, `created_to`, `intake`), intake links, the Check In permission, 24 per page, and every card link.

Still on `app.css`: the shell components listed under "Legacy components on a Tabler page".

### Vehicles (`operations.vehicles.search`)

Chosen second to prove reuse: the same record-search shape as Customers with a different filter (Work: open RO or not) and the missing-VIN chip. Built entirely from the shared components; Customers moved onto the same components in the same change, with unchanged output.

Before: the same `ops-*` index classes as Customers, plus `ops-index-card-chips`, `ops-ro-concern`, `ops-job-card__customer-link`, and the `ops-vehicle-identity-pressure-chip` partial.

After: Tabler only, `fluid` layout with `.ark-card-grid`. Unchanged: query parameters (`q`, `work`, `created_from`, `created_to`), the controller, 24 per page, the Check In permission, the vehicle/documents/customer/RO links, and the "VIN missing" chip and its hint.

Still on `app.css`: the shell components. `x-operations.date-field`, `operations.partials.index-card-actions`, and the vehicle identity chip partial are still used by unmigrated views.
