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

`App\Ark\Operations\Staff\StaffNavigation` is the only navigation definition. `sections()` declares the sections, destinations, labels, URLs, permission checks, active routes, and the live communications count, and returns only what the user may see. `accountMenu()` does the same for the account menu. Add, remove, re-permission, or rename a destination there.

Each shell renders those arrays in its own markup: the legacy rail in `components/operations/app.blade.php` with its icons in `components/operations/rail-icon.blade.php`, and the Tabler sidebar in `components/operations/shell/tabler.blade.php` with the item's Tabler `icon`. A new destination needs a rail icon under its `key`; the rail throws for an unknown key.

`StaffNavigationTest` covers what each role sees, active routes, and that both shells render exactly the shared sections and account entries.

## Legacy components on a Tabler page

These are not migrated yet and render inside `.ark-legacy`: channel strip (`lg` and up), workstation presence and station lock, workspace tabs, comms pressure bar, comms interrupt panel, global search, image lightbox, guide modal, call queue, print helpers, and the repair-order orientation dock.

Tabler's CSS never applies inside `.ark-legacy`. The build (`resources/css/postcss-exclude-legacy-islands.js`) appends `:not(.ark-legacy *)` to the subject of every rule in `tabler.css`, before any pseudo-element, so `app.css` styles an island alone. This matters because the two stylesheets share class names with different meanings: Tabler's `.grid` is a 12-column grid that overrode Tailwind's `md:grid-cols-2` in the hub's edit forms. When a component migrates, remove its wrapper. When none remain, stop loading `app.css` on Tabler pages, delete the plugin and the coexistence rules in `tabler.css`.

Rules for islands:

- Inherited values still flow in from Tabler's `html` and `body`. `.ark-legacy` resets the ones that differ from a legacy page: the 16px Tailwind font and line height, font features, text rendering, and `color-scheme`. Without the last one, native controls in an island (select menus, date pickers, scrollbars) turned dark in the dark theme while legacy pages keep them light. Text color is not reset; an island sets its own or follows its container.
- An inline island (`<span class="ark-legacy">`) inherits its line's font size, so a legacy chip in a line of small Tabler text stays small.
- Tabler markup inside an island is unstyled. Wrap the legacy component only, never a slot that holds migrated markup.
- The two stylesheets share no custom property names, so Tabler's `--tblr-*` variables are inert inside an island.
- Legacy components assume a white workspace surface. An island inside a Tabler card adds `bg-white text-dark` (`app.css` utilities), so it stays readable when the card is dark.

Every Tabler selector gains one class of specificity, equally, so Tabler's own cascade is unchanged. The ancestor check costs under 1ms per full restyle of the customer hub (about 1,000 elements).

Known differences inside `.ark-legacy`:

- Toasts are appended to `<body>` by `ark-ops-toast.js`, outside the wrapper.
- The demo-install bar is not wrapped.

## Building and checking assets

Check migrated views against `npm run build` output, not only the Vite dev server. Herd starts the dev server and writes `public/hot`, which serves unminified CSS; delete `public/hot` to load `public/build`.

`vite.config.js` excludes the `light-dark()` feature from the CSS minifier. Its polyfill resolves `light-dark()` once at `:root`, so Tabler's dark sidebar (`data-bs-theme="dark"` inside a light page) rendered white text on a white background in built assets. Do not remove the exclusion. Tabler pages therefore need native `light-dark()`: Chrome and Edge 123, Safari 17.5, Firefox 120, or later. Tabler's published CSS has the same requirement. Legacy pages do not use `light-dark()`.

`resources/css/postcss-exclude-legacy-islands.test.js` covers the selector rewrite. Run it with `node --test resources/css/postcss-exclude-legacy-islands.test.js` after changing the plugin or upgrading Tabler.

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
| `fluid` | `container-fluid` | Lists, indexes, and record pages that scroll as one document: customers, vehicles, the customer hub, repair orders, reports. |
| `focused` | `container-narrow` (61.875rem) | Account forms, settings, onboarding: one column of reading or input. |
| `split` | `container-fluid`; the body fills the remaining height (`d-flex flex-column flex-fill`) | Multi-panel workspaces where panels scroll independently: communications. The slot is one `.row` of panels; each panel is a `.card` that scrolls on its own. |
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
| `<x-tabler.page layout="…" [actions-label] [stack-actions]>` | Page header and body in the layout's container. `actions-label` names the action group for assistive technology. `stack-actions` moves the actions below the title on phones, for record pages whose title is a long name. |
| `<x-tabler.page-action [href] icon label [primary]>` | A header button. Without `href` it is a `<button>`; pass its behavior as attributes, for example `x-on:click`. Secondary actions add an icon-only twin with a tooltip below `sm`. |
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

### Customer hub (`operations.customers.show`)

Chosen third: the first record page, with tabs, deep links, inline forms, a modal, and permission-gated commands.

Layout: `fluid`, not `split`. The hub is one record read top to bottom, and its tabs hold long lists and forms; panels that scroll on their own would hide the page's length and trap scrolling on phones. From `xl` the profile is a column beside the tab card (`col-xl-4 col-xxl-3`); below `xl` it sits above, with the contact and open-RO cards side by side from `md`. The header uses `stack-actions`, so on a 390px phone the name keeps one line and Call, Text, Schedule, and Edit are 40px icon buttons beside the labelled Create RO.

Before: `ops-hub-layout`, `ops-hub-profile`, `ops-report-tab`, `ops-hub-tab-*`, `ops-workspace`, `ops-section-label`, `ops-hub-vehicle-*`, `ops-ro-card`, `ops-status-chip--{tone}`, `ops-index-btn`, and Tailwind layout utilities.

Migrated to Tabler:

- Page header and commands: Call, Text, Schedule, Edit Customer, Create RO (`x-tabler.page-action`, `CustomersManage`).
- Status message: `alert alert-success`.
- Profile card: type, referral, vehicle and visit counts, phone, email, Messenger, contact preference and active-RO badges, notes with Show more.
- Open repair orders card: up to three open ROs and "View all".
- Tab navigation: Work, Vehicles, Comms, History, Documents with counts (`nav-tabs card-header-tabs`).
- Vehicles tab: the fleet `.ark-card-grid`, each card's posture, recommendation, identity, active RO or last visit, notes, future work, RO links, the Start RO form, and Schedule, Start RO, Edit Vehicle, and Remove Vehicle with its confirmation.
- History tab: the operational timeline per vehicle (collapsible) and the service history list with `x-tabler.status-badge`.
- Documents tab: Add Document, the search and type filter, and an empty state.
- Comms tab: header and the All, Text, Email, Messenger, Portal, Logged filters (`aria-pressed`).

Unchanged: the controller, routes, permissions, tab and deep-link parameters (`tab`, `vehicle`, `compose`, `doc_q`, `doc_type`, the `#customer-*` and `#vehicle-*` anchors), form field names, and the Alpine state in `ark-customer-hub-tabs.js` and `ark-customer-hub-comms.js`.

Still on `app.css`, in `.ark-legacy`: the workspace modal and its customer, vehicle, and document forms (`hub-workspace-modal`), the conversation context and advisor work panels on the Work tab, the reply composer and message rows on the Comms tab, the document list, the operational journey card, and the VIN display. All are shared with unmigrated views.

Known visual limitations, both from the legacy components:

- In the dark theme the Work and Comms tab panels and the workspace modal stay white inside dark cards. They have no dark style of their own, and this keeps them readable until they migrate.
- The VIN display's muted prefix (all but the highlighted last eight characters) uses slate-700, which has low contrast on a dark card. It is the same on legacy pages in dark mode (slate-700 on slate-800).

### Repair orders index (`operations.repair-orders.index`)

The same record-search shape as Customers and Vehicles, built from the shared components with no new markup.

Before: `ops-index`, `ops-board-shell`, `ops-page-toolbar`, `ops-board-filters`, `ops-index-filters--ro`, `ops-ro-retrieval-grid`, `ops-job-card*`, `ops-ro-index-card`, `ops-ro-card--{tone}`, `ops-status-chip--{tone}`, `ops-ro-concern`, `ops-index-empty`, `x-operations.date-field`, and the Tailwind pagination view.

After: Tabler only, `fluid` layout with `.ark-card-grid`. The page subtitle carries the queue note (search, workboard overflow, or Shop Dashboard drill-down), and the results heading shows the queue label. Status, From, and To collapse behind "Filters" on phones. Unchanged: the controller, query parameters (`q`, `status`, `created_from`, `created_to`), the queue drill-downs and their hidden inputs (`pickup`, `unassigned`, `lane`, `attention`, `disposition`, `open`), the "Workboard triage" return link, 24 per page with the query string kept, the `RepairOrdersView` permission, and every card link.

Still on `app.css`: the shell components listed under "Legacy components on a Tabler page".
