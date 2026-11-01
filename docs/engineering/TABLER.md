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

The repair order board is "Job Board" for every role. Staff with operations access open the advisor board (`operations.index`); technicians and production-only staff open their lanes (`operations.workboard`, key `workboard`). The routes, keys, views, and permissions stay separate; only the label, page titles, and links read "Job Board".

"Work Queues" (`work-queues`, staff with operations access) opens Tasks (`operations.work.queue`). Each queue page links to the other three (Tasks, Follow-Ups, Scheduled, Customer Decisions) with their counts. The `/app/work/queues/{queue}` URLs are unchanged, and `/app/work/queues/comms` still redirects to the inbox.

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

The repair order worksheet needs a secure context: `ark-worksheet-collaboration.js` calls `crypto.randomUUID()`, which browsers only provide over HTTPS or on localhost. On a plain-HTTP Herd site the worksheet component fails to start, on legacy and Tabler pages alike, and its toolbar and line controls do nothing. Run `herd secure` for the site before checking worksheet behavior.

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

`split` and `canvas` are defined but have no migrated view yet. The first view of each kind confirms the height behavior and updates this table. `canvas` was tried on the Schedule with its legacy board: the board does not manage its own outer spacing, so it sat flush against the sidebar while the header stayed inset, and its height is set by its content. The Schedule uses `fluid` until its board is replaced.

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

Bootstrap's breakpoints end at 1400px, so `col-*` classes give the same column count at 1440 and 2560. `.ark-card-grid` uses `auto-fill`, not the `auto-fit` of Tabler's `.datagrid`, so a single result stays one column wide. Set `--ark-card-min-width` on the grid if a card needs a different minimum.

Filter rows on fluid pages switch fields to their natural width from `xxl` (`col-xxl-auto`) so they do not stretch across an ultrawide screen. The search input takes the remaining width.

## Conventions for a migrated view

- Markup is Tabler's. No `ops-*` classes, no Tailwind utilities, and no new CSS. `tabler.css` holds theme tokens, coexistence rules, and the layout rules Tabler has no utility for: `.ark-card-grid`, the repair order line grid, and the Job Board's `.ark-board`.
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
| `<x-tabler.page layout="…" [actions-label] [stack-actions] [actions-below]>` | Page header and body in the layout's container. `actions-label` names the action group for assistive technology. `stack-actions` moves the actions below the title on phones, for record pages whose title is a long name. `actions-below` puts the actions on a full-width row under the title at every width, for a command bar too wide for the title row (the repair order). |
| `<x-tabler.page-action [href] icon label [primary]>` | A header button. Without `href` it is a `<button>`; pass its behavior as attributes, for example `x-on:click`. Secondary actions add an icon-only twin with a tooltip below `sm`. |
| `<x-tabler.search-filters id action query placeholder label clear-url has-filters active-filter-count>` | The search card: search box and button, the phone Filters toggle and count, and the collapsible filter row. The slot holds the filter columns; an optional `hidden` slot holds hidden inputs. Field ids are `{id}-search` and `{id}-filters`. |
| `<x-tabler.record-card :tone :actions>` | A `.card-sm` with the status color bar, the slot as its body, and the open-in icon row from `operations.partials.tabler-index-card-actions`. |
| `<x-tabler.empty-state icon clear-url>` | The empty result, with "Clear search" when `clear-url` is set. |
| `<x-tabler.status-badge :tone>` | A repair-order status badge. |
| `<x-tabler.vehicle-identity-chip :vehicle>` | "VIN missing" with its hint as a tooltip. The legacy chip partial stays for unmigrated repair-order screens. |
| `<x-tabler.vin-display :vin>` | The VIN with the last eight characters bold, copy on click with a Copied badge, and the phonetic readback on hover or focus (`arkVinDisplay`). The readback panel is still the legacy partial. `x-operations.vin-display` stays for the repair order workspace. |
| `<x-tabler.help-tip :text [label]>` | A Tabler `form-help` mark with the text in a popover on hover or focus. `x-operations.help-tip` stays for settings and the repair order workspace. |
| `<x-tabler.workspace-modal [initial-task] [initial-context]>` | `arkWorkspaceModal` in a Tabler modal: same host id, refs, keyboard handling, validation message, and footer buttons as `x-operations.workspace-modal`, full screen on phones. Panels are `<div data-workspace-modal-panel x-show="…">`. The repair order workspace keeps the legacy component. |
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
3. Run its feature tests and `TablerShellTest`. Update assertions that named legacy classes to assert the same behavior. `TablerShellTest`, `MobileApplicationNavTest`, and `CoreBoundaryCleanupPhase1Test` check the legacy rail on the profile page (`profile.edit`), which every role can open; when it migrates, point them at another legacy page.
4. Check 390, 820, 1440, 1920, and 2560 wide, light and dark, for horizontal overflow and stretched content: forms, links, pagination, dropdowns, tooltips, the station overlay, and Ctrl/⌘K search. Capture the pull request's Visual Review screenshots during this check ([RELEASE_PROTOCOL.md](RELEASE_PROTOCOL.md#1-implement-and-pr-this)).
5. List what still depends on `app.css`.
6. Stop for review before the next view.

## Migration inventory

The target is the 58 staff pages, the views that render `<x-operations.app>`. Update this list in the pull request that migrates a view. Names in code are route names. Reachability was checked by requesting every staff GET route as an admin, an advisor, and a technician, and following the views, includes, links, and script consumers each one renders.

A page counts as complete only when nothing reachable on it is still legacy. Code that never renders does not hold a page back, but it is listed under Unreachable.

**Complete (11):** on Tabler with no reachable legacy markup and no open corrections.

- Customers (`operations.customers.search`)
- Vehicles (`operations.vehicles.search`)
- Repair orders (`operations.repair-orders.index`)
- Job Board (`operations.index`)
- Appointment detail (`operations.appointments.show`)
- Schedule appointment (`operations.schedule`). The sidebar does not highlight Schedule on this page, because `StaffNavigation` matches only `operations.appointments.*`. That belongs to a shared navigation change, not a view slice.
- Time clock (`operations.time-clock.index`)
- Check In (`operations.intake.create`)
- Schedule (`operations.appointments.index`)
- Dashboard (`operations.today`), including the technician lanes
- Work queues (`operations.work.queue`: tasks, follow-ups, scheduled, decisions)

**Partial, actionable (1):** on Tabler, with legacy sections that can migrate now.

- **Shop settings (`operations.settings.shop.edit`):** every section except Shop Identity, Documents / Disclaimers, Workflow Defaults, Label Printing, and Financial Rules. See the log entry for which remaining sections wait on a hosted-service decision.

**Partial, blocked (2):** on Tabler, with legacy sections that wait for other work.

- **Repair order workspace (`operations.repair-orders.show`):**
  - The header and command bar are Tabler (see "Repair order workspace: header and command bar"). Keep the three-section Customer | Vehicle | Visit panel and the sticky footer as they are; other slices do not redesign them.
  - **Legacy islands:**
    - the worksheet body and busy overlay;
    - the engine oil panel;
    - the workspace modal host;
    - the authorization exception form;
    - the RTE labor panel;
    - the rail's Authorization, Portal, and Parts tabs;
    - the labor guide and parts catalog toolbar buttons.
  - Blocked on the financial and authorization workstream.
- **Customer hub (`operations.customers.show`):** the conversation context panel (Work tab), the reply composer and message rows (Comms tab), and the document list (Documents tab), all in `.ark-legacy`. They are shared with the communications and document views and move with them. Everything else on the hub, including the customer and vehicle edit modal and the VIN display, is Tabler. The add-document panel inside the modal stays legacy for the same reason.

**Not started (30):** live pages to migrate. "Navigation" pages are linked from the sidebar, a tab, or a page header; "internal" pages open only from another page or a direct link.

- **Communications (8):** inbox, history, internal, and calls (navigation); shop communications index, device, and person (navigation); shop communications setup required (internal).
- **Owner and reports (10):** business (Cockpit), reports index, operational report, day review, technician production index and detail, and the Scoreboard tab (navigation); call intelligence index and detail, and staff coaching (internal).
- **Learning (3):** ARKademy, print selection, and team progress (navigation).
- **Daily operations (4):** the technicians' Job Board (`operations.workboard`), their home because technicians get 403 on `/app` (navigation); new contact (`operations.leads.create-contact`), caller lookup, and observations (internal).
- **Account and system (5):** profile and the staff time clock history (`operations.time-clock.staff`) (navigation); ARK Cloud connecting, Platform clusters, and the 409 error page (internal). The staff history page corrects and deletes compensable hours, so it migrates on its own.

**Deferred (9):** live pages waiting for a decision or another workstream.

- **Technician (3):** technician landing, inspection (`operations.repair-orders.inspection.show`), and engine oil confirmation, all internal. They wait for the decision on the technician phone workflow.
- **Financial and authorization (6):** the end-of-day report (navigation); the estimate document, the document viewer, record testing outcome, the dealer parts quote, and parts matrix tuning (internal). They wait for the release-blocker workstream.

**Unreachable (5 views, plus partials):** no request renders them. Remove them rather than migrate; nothing here is deleted yet.

- `operations/intake/index`: `operations.intake.index` redirects to the Job Board.
- `operations/communications/workboard`: `operations.communications.workboard` redirects to the inbox.
- The briefing view: its route redirects, and its controller is not routed.
- The conversation reply view: its route goes to a redirect controller, and the reply controller is not routed.
- The communications attention workspace view: nothing renders it.
- Partials: `appointments/partials/capacity-bar` (no view includes it), the Comms work queue, `operations/partials/work-tab-queues` with the `work-queue-band-header` and the compact dispatch rows of `customer-decision-pressure-rows` that only it uses, `week-schedule-list`, the advisor triage branch of the workboard, and `operations.repair-orders.partials.operational-journey-card` on the customer hub and the repair order workspace. Both controllers always pass `operationalJourney` as `null`, so the card never renders. It stays legacy and in place.

**Known issues across migrated pages:**

- **Sidebar highlight:** the Schedule item is not highlighted on `operations.schedule`; see Complete above.
- **Slate status bars:** a `card-status-start bg-dark` bar, for the slate status tone, is nearly invisible against a dark-theme card. This appears on record cards and the Check In customer results.

**Excluded from the staff count, kept for a later design review:** these surfaces are not part of this migration and are not canceled.

- sign-in and account pages (`<x-guest-layout>`, 8);
- the installer (8);
- the ARKademy print page;
- wall displays (shop display, customer display, and scoreboard wall);
- the customer portal (9);
- the public website (10);
- ARK Cloud pages (13).

"Workspace layouts" describes the guest layout's target.

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

The edit modal (`hub-workspace-modal`) is `<x-tabler.workspace-modal>`. Edit Customer, Edit Vehicle, and Add Vehicle keep their routes (`operations.customers.update`, `operations.customers.vehicles.update`, `operations.customers.vehicles.store`), methods, field names and order, `CustomersManage` gating, and `arkVehicleDecode` VIN and plate decoding. Server errors mark the field `is-invalid` with its message and reopen the same panel; the required-field check shows the modal's alert. The vehicle fields are `workspace-vehicle-fields-tabler`; the repair order workspace keeps `workspace-vehicle-fields`. `ark-workspace-modal.js` finds panels by `[data-workspace-modal-panel]` as well as the legacy class. The Vehicles tab shows the VIN with `<x-tabler.vin-display>`, or the identity chip when it is missing.

Still on `app.css`, in `.ark-legacy`: the conversation context panel on the Work tab, the reply composer and message rows on the Comms tab, the document list, and the add-document panel inside the modal. All are shared with the communications and document views. The operational journey card is also legacy, but never renders (see Unreachable).

Known visual limitation: in the dark theme the Work tab's conversation context panel, the Comms tab panels, and the add-document panel stay white inside dark cards. They have no dark style of their own, and this keeps them readable until they migrate.

### Repair orders index (`operations.repair-orders.index`)

The same record-search shape as Customers and Vehicles, built from the shared components with no new markup.

Before: `ops-index`, `ops-board-shell`, `ops-page-toolbar`, `ops-board-filters`, `ops-index-filters--ro`, `ops-ro-retrieval-grid`, `ops-job-card*`, `ops-ro-index-card`, `ops-ro-card--{tone}`, `ops-status-chip--{tone}`, `ops-ro-concern`, `ops-index-empty`, `x-operations.date-field`, and the Tailwind pagination view.

After: Tabler only, `fluid` layout with `.ark-card-grid`. The page subtitle carries the queue note (search, workboard overflow, or Shop Dashboard drill-down), and the results heading shows the queue label. Status, From, and To collapse behind "Filters" on phones. Unchanged: the controller, query parameters (`q`, `status`, `created_from`, `created_to`), the queue drill-downs and their hidden inputs (`pickup`, `unassigned`, `lane`, `attention`, `disposition`, `open`), the "Workboard triage" return link, 24 per page with the query string kept, the `RepairOrdersView` permission, and every card link.

Still on `app.css`: the shell components listed under "Legacy components on a Tabler page".

### Shop dashboard (`operations.dashboard`, `operations.today`)

Before: `ops-today`, `ops-shop-dash-*` (toolbar, command cards, workflow bar, KPIs, sales table), and the `ops-ro-workspace-tab*` dashboard tabs.

After: Tabler `fluid` page. The date is the pretitle; the open-queue count, Job Board, Comms, and Business are page actions with their existing permission checks. Today and Scoreboard are `nav-pills` from the shared `operations.dashboard.tabs` partial (`'ui' => 'tabler'`); the Scoreboard page keeps the legacy tabs. The four command cards, the status bar and legend, and status dots use the shop's status colors through `RepairOrderStatusColor::tablerColorForChipTone()`. Tabler's `dark` is the dark theme's card color, so a status in the closed tone (Draft, by default) uses `secondary` on the bar and legend. The sales table is a `card-table` that scrolls inside its card on phones. Unchanged: `TodayController`, the projection, every count, amount, link, and hint.

Staff without a shop dashboard (technicians) get a Tabler `fluid` page titled "Today", with the attention count as the title badge, the greeting as the subtitle (hidden below `md`, like every page subtitle), and Job Board as a page action. Each lane (`operations.today.partials.technician-lanes`) is a `card`: the title and its total count, View all as a card action, and the rows as a flush `list-group` of links to the repair order. The owner label shows from `md` and "Open RO" from `sm`. With no lanes, a card says "You're caught up." with the day's focus. Unchanged: `TodayOperationalComposer`, the lane order and caps, each row's link, and the View all target. Hooks: `data-today-lanes`, `data-today-lane="{key}"`, `data-today-lane-count`, `data-today-action`, and `data-today-caught-up`.

### Shop settings (`operations.settings.shop.edit`)

Settings is one page with an Alpine section switcher and about 30 section partials. The page and five sections are Tabler; the rest are unchanged legacy markup in one island.

Migrated: the page header, the status and error alerts (`alert-success`, `alert-danger`), and the section navigation (a `list-group` card from `lg` up, a `form-select` below `lg`, both calling the existing `setActive()` and keeping the `?section=` URL). Shop Identity and Documents / Disclaimers are Tabler cards with `form-control`, `form-select`, `form-check`, `invalid-feedback`, and a card-footer Save button. Field names, `old()` values, routes, methods, and button labels are unchanged.

Workflow Defaults and Label Printing are also Tabler cards. Their tabs are `nav-tabs card-header-tabs` buttons that call the existing `setWorkflowTab()` and `setPrintingTab()`.

- **Check In defaults:** the visit type, priority, and work type selects pass `form-select` to the shared selects. The worksheet-notes chooser is the shared `repair-order-note-privacy-field` with `'ui' => 'tabler'`, a `form-selectgroup` with the same hidden inputs and the same rule that one view stays on. Without `ui`, the repair order workspace keeps the legacy chooser.
- **Job Board lanes, RO statuses, inspection checklists, Common Jobs:** `card-sm` blocks and `list-group` rows. The color picker (`ro-status-color-picker`, used only here) is a Tabler `dropdown-menu` listbox with the same hidden input. The "Who can move this" table is a `card-table` inside the status card's `details` footer.
- **Label Printing:** the printers form and the QZ certificate runbook, with `invalid-feedback` on the text and number fields.

Every field name, value, default, `old()` key, route, method, and Save label is unchanged. Each form submits the same fields in the same order per field as before. Hooks: `data-settings-section`, `data-settings-job-board-lanes`, `data-settings-status-catalog`, `data-settings-inspection-templates`, and `data-settings-work-templates`.

Financial Rules is a Tabler card with seven `nav-tabs card-header-tabs` buttons that call the existing `setFinancialTab()`, so `?financial-tab=` and the stored tab work as before (the old `customer-tags` and `customer-types` keys still open Billing Classes).

- **Labor:** a `list-group` row per category. Rounding is a `btn-check` radio group with the same `rounding_rule` values.
- **Labor Policies** (`partials.labor-policies`): the rate grid is a `card-table` of cell buttons; the editor and the resolver preview are `card-sm` blocks with the same forms.
- **Tax Rules, Shop Fees, Deposits:** `form-switch` and `form-check` with the same hidden `0` inputs, `input-group` rate fields, and `invalid-feedback`.
- **Parts Matrix:** a `card-sm` per matrix with a `card-table` of rows that scrolls sideways on phones. `parts-matrix-form`, the delete forms, `matrixSection()`, and `autosaveMatrix()` (the same request, 700 ms debounce, and status text) are unchanged.
- **Billing Classes:** a `card-sm` per class. The profile shows as a header badge and the pill color previews as a Tabler badge. The Fleet, Warranty, and standard branches keep their hidden and disabled fields.

No calculation, default, validation rule, permission, or stored value changed, and each form submits the same fields in the same order as before. Hooks: `data-settings-labor`, `data-settings-labor-policies`, `data-settings-tax`, `data-settings-fees`, `data-settings-deposits`, `data-parts-matrix`, and `data-settings-billing-classes`.

Still legacy, in one `.ark-legacy bg-white text-dark` island shown when another section is active:

- **Core sections not yet migrated:** Appointments & Setup, Staff, and Shop Overhead.
- **Sections that also hold hosted-service settings:**
  - Owner Targets & Reporting, because its owner digest is sent by mail;
  - Square Payments, which also holds the payment ledger's Check, External Card, and EFT buttons;
  - Parts catalogs, Communications, and ARK Platform;
  - Runtime health, which shows telephony health.

Appointments & Setup reads business hours from the telephony call-flow settings, so it waits with the business-hours decision. Migrate the rest section by section; each moves out of the island when it does.

### Schedule (`operations.appointments.index`)

Migrated: the page header (the focus date or week as title, the appointment count as subtitle), the Day / Week / Month switch (the same `board-view` POST form, now a `btn-group`), Previous / Today / Next, the primary Schedule action, the status, warning, and error alerts, and the week allocation and lens chips as `nav-pills` with counts. Query parameters, the stored board preference, and every link are unchanged.

The rest of the page followed in a second change, so no legacy markup remains:

- **Appointment requests:** a `card-sm` with the day's status and the same three forms (disable with a reason, accept, clear override).
- **Capacity:** a `card-sm` with scheduled, base, and target hours, the status line, warnings as an `alert`, and a Tabler `progress` bar per day, seven across from `lg`.
- **Day board:** a time column and a lane per resource in a card. Each lane is at least 8rem per overlapping appointment column, so on phones the lanes scroll sideways with the times pinned instead of squeezing the cards. Empty slots still link to Schedule at that time; cards show Call and Text on hover or focus.
- **Week board:** seven day columns from `lg`, a list of days below it, each with its count, appointments, and Schedule link. The technician and bay allocation is a resource grid that scrolls sideways, with the resource column pinned.
- **Month board:** a seven-day grid; on phones a list of the month's days with their weekday. Load pressure tints the day, and "+N more" opens the day view.
- **Popover:** a Tabler `card` with the same content, actions, and assignment form (`operations.appointments.assign` with its hidden context). `ark-schedule-appointment-popover.js` is unchanged apart from finding the panel by `[data-schedule-popover]`; it still opens one at a time, closes on outside click or Escape, and flips above near the bottom of the screen.

The grids are layout primitives in `tabler.css` (`.ark-cal-*`) using Tabler variables, so they follow the theme. There is no drag and drop on the board, before or after. Unchanged: `AppointmentIndexController` and its workspace data, the routes, query parameters, stored board view, lenses, and every link and form.

FullCalendar: Tabler 1.7.3 ships the FullCalendar 6.1.22 Standard bundle as a global script (`@tabler/core/dist/libs/fullcalendar/index.global.min.js`) and no `.fc` styles. ARK's boards are server-rendered, not a JavaScript calendar. The day board has a lane per bay and the week board allocates by technician or bay; those are resource views, which FullCalendar provides only in its separately licensed Scheduler, not the Standard bundle. Moving the boards to FullCalendar would also need a JSON event feed and a rewrite of the lenses, capacity rail, request controls, and popovers. That is a separate decision, not a presentation change.

### Repair order workspace: shell and toolbar (`operations.repair-orders.show`)

The first slice of the repair order workspace. The page uses the Tabler shell; the worksheet, rail, and footer dock are unchanged inside one legacy island.

Migrated: the page header (RO number, customer and vehicle) and the toolbar as its actions: Review Estimate Notes, Paperwork / Add Document, the Print menu (a Tabler dropdown with the same items, links, and `arkPrintDocument` calls), the technician select, the lifecycle select with its lost-reason panel, the review-request follow-up, and the PartsTech busy notice. The toolbar keeps `id="review-toolbar"`, so worksheet refreshes still replace it from the server, and every form, hidden field, data attribute, Alpine handler, and permission check is unchanged.

Still legacy, in `.ark-legacy.ops-main > .ops-estimate-workspace` (the wrapper legacy dark-mode and layout rules expect): the flash messages, collaboration banners, intake band, identity band, workspace tabs, the estimate worksheet, the totals and financial rail, the labor guide panel, and the footer dock. `tabler.css` sets `--ops-left-rail-width` inside islands to the Tabler sidebar width, so the fixed footer dock starts at the sidebar edge.

### Repair order workspace: header and command bar (`operations.repair-orders.show`)

The refinement pass on the first slice's header and toolbar.

- **Header:** "Repair order" and "RO #…". The customer and vehicle subtitle is gone: the Customer | Vehicle | Visit panel directly below shows both, and the footer dock repeats them.
- **Command bar:** `#review-toolbar` moved from the header's actions column to a full-width row under the title (`<x-tabler.page actions-below>`). It holds, in order:
  - the appointment and inspection signals, now Tabler (`.ark-ro-signal`), with the same text, links, `data-arrival-posture` / `data-inspection-posture`, and full detail in `title`;
  - the technician and status selects (`.ark-ro-command-bar__tech`, `__status`) and the review-request follow-up;
  - Review Estimate Notes, Paperwork / Add Document, and Print, each with a Tabler icon.
- **Layout:** at 1920 the bar is one line. Narrower, the controls wrap under the signals and span the row, selects first and actions last. Below `sm` the two selects share a row, the actions show only their icons (named by `aria-label` and `title`), and an open lost-reason panel takes the full row.
- **Unchanged:** `id="review-toolbar"` and its server replacement after worksheet saves; every form, action URL, hidden field, `data-*` hook, Alpine handler, permission check, and lifecycle rule; the Print menu's items and `arkPrintDocument` calls; the Customer | Vehicle | Visit panel; and the sticky footer. The band variants of the posture partials, used by the customer documents, are untouched.

### Repair order workspace: tabs and rail container (`operations.repair-orders.show`)

The second slice. The workspace tabs (Estimate, Inspection, Recommendations, Communications, History) and the rail's Estimate Total / Closeout switch are Tabler cards with `nav-tabs`. The tab state, `localStorage` key, URL hashes, lazy loading from `workspace-tabs/{tab}`, `#financial-rail`, and `ops-show-closeout` are unchanged. Tab counts are Tabler badges with `data-workspace-tab-count`.

The single island is now several. `x-operations.worksheet-island` wraps each piece of legacy content in `.ark-legacy.ops-main > .ops-estimate-workspace`, because the legacy rules select on both ancestors (dark-mode fields need the pair), and drops their page padding. Islands: the flash messages through the identity band, each tab panel's content, the totals rows, the closeout panel, and the rail's journey and follow-up panels. The labor guide panel, footer dock, and rail script keep the full legacy wrapper, whose bottom padding clears the fixed dock. These islands sit in Tabler cards without `bg-white`: the worksheet has its own dark-mode rules.

The `ops-estimate-layout` grid and the rail's `ops-review-rail ops-review-rail--pinned` classes stay. They are layout only. Payment capture selects on `.ops-review-rail`, and the grid's 1024px breakpoint matches the rail script's.

The rail had stopped sticking on the Tabler shell after the first slice: its `top` read `--ops-sticky-stack-offset`, which only the legacy shell defines. `tabler.css` now sets it on `.page`.

Dark mode, unchanged from the legacy page: the approval forecast, the reason-for-visit band, and the customer notes band keep light backgrounds with light text.

### Repair order worksheet: header (`operations.repair-orders.show`)

The first worksheet slice: everything above the concern cards on the Estimate tab. Each control keeps its Alpine handler, form, hidden fields, ids, and continuity regions.

- Estimate tools: Add Work, Import, and Customer View are Tabler buttons (`data-estimate-tools`). The labor guide and parts catalog groups stay inline islands, because their colors come from legacy classes and a shop picks each parts catalog's color in settings (`PartsCatalogButtonColor`). Moving them needs Tabler equivalents for those colors.
- Reason for Visit and its suggested concerns are a Tabler card. This fixes its dark mode.
- Recommended Work: the instruments are Tabler cards with badges and progress bars. Tones map to Tabler colors (excellent and good green, healthy teal, thin yellow, warn orange, low red). Each card is still the trigger of `x-operations.inspect-popover`, whose panel is now an island so its legacy styling holds on a Tabler page.
- The Authorization, Customer View, and Parts switches are `nav-pills`. Their panels are Tabler cards with the legacy panel content inside islands.
- Photos is a Tabler button.

The Estimate tab no longer wraps its whole slot in one island; `show.blade.php` wraps the remaining legacy pieces: the engine oil and testing panels, and the workspace modal host with the concern cards.

### Repair order worksheet: concern cards (`operations.repair-orders.show`)

Each concern is a Tabler card with a status strip in its priority color. The header, status controls, scope toolbar, and narrative are Tabler; the line rows and the authorization exception dialog stay in an island inside the card until the line-row slice.

Kept for the worksheet scripts:

- Each concern stays `<section id="concern-{id}">`, a direct child of `#worksheet-concerns`. Continuity mounts a new concern by finding its parent's id, and workspace memory reads the id.
- The `concern-money-*`, `concern-authorization-*`, and `concern-disposition-*` regions keep their ids, and every form keeps its `data-continuity-focus` selector, hidden fields, and `submitWorksheetForm` or `requestSubmit` call.
- New ids avoid the `concern-` prefix: workspace memory treats any focused element inside `[id^="concern-"]` as a concern.

Colors come from the presentation types, so each palette stays in one place: `RecommendationPresentation::tablerColor()` (priority: must do red, should do yellow, could do green, information only azure), `RepairOrderConcernDisposition::tablerColor()`, and `ScopeProductionStatus::tablerColor()`, beside the legacy `worksheetToneStyle()` they match.

- Priority, customer decision, and work status are three compact Tabler dropdowns, in that order: `data-concern-priority-choices`, `data-concern-decision-choices`, and `data-concern-work-status-choices`. Each option sets its form's hidden field (`recommendation_posture`, `disposition`, `production_status`) and calls `requestSubmit()`, posting to the same route the earlier select or modal did. The current option only closes the menu and shows a checkmark: re-saving the current decision would re-record the approved scope.
- Priority shows a colored status dot beside a plain label (yellow text is unreadable on white); customer decision and work status show icons, so a yellow Should Do and a yellow Deferred, or a green Could Do and a green Approved, still read as different fields.
- The customer decision dropdown shows the Recommended disposition as "Pending Approval" (`RepairOrderConcernDisposition::decisionLabel()`), and the work status dropdown shows Pending as "Not started" (`ScopeProductionStatus::workStatusLabel()`). Stored values and `label()` are unchanged. The card header shows the decision badge only on a closed repair order, where the dropdowns are hidden.
- Billing is a `form-select-sm` or `bg-*-lt` button. Work type is not in the header: it describes the work, not the concern, and stays editable in the concern's Edit modal until labor lines carry their own classification. The Recommendation Status modal is gone; priority has its dropdown.
- The More menu is a Tabler dropdown. Record exception still sends `ark-authorization-exception-open`, now with the dropdown toggle as `invokeEl`, so focus returns to More.
- `x-operations.scope-header` takes `ui="tabler"`; the estimate document page keeps the legacy header. `repair-order-concern-disposition-decision` and `finding-from-verified-findings-nudge` take a `ui` variable for the same reason.
- `x-operations.help-tip` is now an inline island itself, so its legacy tooltip renders inside Tabler markup.
- Tests that checked `ops-worksheet-concern--{disposition}` and similar classes check `data-concern-disposition`, `data-concern-narrative`, `data-scope-parts-catalog`, and `data-disposition-tone`.

### Repair order worksheet: line rows (`operations.repair-orders.show`)

The work section inside each concern card: repair action groups, line rows, the ledger head, suggested repairs, and the compose buttons. The concern card's only remaining island is the authorization exception dialog.

Line rows are presentation only. Editing quantity, price, and the rest happens in the workspace modal's edit-line form (`editLine()` through the row's `data-line-edit-trigger` link), which is unchanged.

Kept for the worksheet scripts:

- Each row stays `id="line-{id}"` with `data-continuity-region="line"`, a direct child of `repair-action-lines-{group}` or `concern-lines-{concern}`. Continuity replaces a saved row by id and mounts a new one by its parent's id.
- The row's click and Enter/Space handlers, `data-line-card-ignore` on its controls, and the edit link's `data-continuity-focus` are unchanged.
- The part status select keeps `data-procurement-select`, `data-current-state`, and its `data-procurement-form`, which `initPartProcurementSelect()` submits.

Layout: `.ark-line-grid` in `tabler.css` keeps the legacy column widths (a flexible description and six money columns from md). On phones `.ark-line-ledger` is a labelled quantity, price, and total strip under the description; from md the ledger head labels the columns.

Colors: `RepairOrderLineType::tablerColor()` for the type label and compose icons, and `RepairOrderLineItemPresentation` for procurement status, the row accent (backordered, partial, installed), part flags, and margin, each beside the legacy tone it matches. `repair-order-part-procurement-actions` and `repair-order-note-visibility-badge` take a `ui` variable, so the rail's parts panel and the estimate document keep their legacy output.

The estimate instruments are an `authorized-money` region (`#estimate-instruments`), so a line or authorization save refreshes the Customer Total with the rail.

### Repair order worksheet: financial rail (`operations.repair-orders.show`)

The rail's Estimate Total tab: the approval forecast, the totals, the lines needing authorization, the suggested deposit and balance, and the payment strip (Owe today, settlement balance, final invoice, deposit on file, waive balance, recent payments). The Closeout tab, the payment capture workspace, and the deposit breakdown are their own slices, below.

Kept for the rail scripts:

- Every region keeps its id and kind: `approval-forecast` and `authorized-lines` (`authorized-money`), `estimate-totals-region` and `estimate-balance-region` (`totals`), `financial-position`, `invoice-eligibility`, `settlement-deposit`, and `settlement-history`. None nest.
- The Breakdown link keeps `data-ops-deposit-breakdown-open`, and Full ledger & closeout still dispatches `ops-show-closeout`.
- The final total row is `data-total-row="final"`.

`repair-order-approval-forecast` renders Tabler for the staff variant only; the customer portal and document footer keep the legacy variants. Money columns use `.ark-money` (tabular figures). A balance due or paid state marks its block with a `card-status-start` strip rather than a tinted background, which kept the amount readable in dark mode.

### Repair order worksheet: closeout tab (`operations.repair-orders.show`)

The rail's Closeout tab (`partials.financial-rail`): payment status, the stale invoice notice, Post to sales, the ledger and documents list, the invoice email form, waive balance, record refund, the final invoice PDF, and payment history with Void entry. The slot in `x-operations.estimate-totals-panel` is no longer an island.

Kept for the rail scripts:

- Every region keeps its id and kind: `payment-posture`, `closeout-eligibility`, `settlement-invoice`, `settlement-balance`, and `settlement-history-closeout` (`settlement`), `invoice-posture`, and `financial-position-closeout` (`financial-position`). None nest.
- Forms keep their routes, permissions, concurrency fields, `data-refresh-scope="financial"`, and confirm prompts. The invoice email form still submits through `arkWorksheetFormSubmit`.
- `#waive-balance` stays the anchor for the payment strip's Waive balance link, which now dispatches `ops-show-closeout` before scrolling, because the form is in the hidden tab.

The partial no longer takes an `embedded` variable; it only renders inside the tab.

### Repair order worksheet: payment workspace and deposit breakdown (`operations.repair-orders.show`)

The Take payment workspace (`partials.financial-payment-workspace`) and the deposit line breakdown (`partials.financial-suggested-deposit-template`) are Tabler cards. Neither is wrapped in an island any more.

Payment behavior is unchanged. `ark-payment-capture.js` is untouched, and the markup keeps everything it reads:
- the `data-payment-workspace*`, `data-terminal-payment*`, and `data-payment-reader-*` hooks;
- the Alpine bindings;
- the form fields, routes, and concurrency and intent fields.

Constraints:

- Both dialogs sit in their existing overlays. Those are `.ops-workspace-modal` with its backdrop, which the script shows and layers over the page by raising the rail, and the overlay that `ark-deposit-breakdown.js` appends to `body`. The dialog inside is a `card`, not `.modal-content`, because Bootstrap defines the modal colors on `.modal`, which these overlays do not use.
- Bootstrap's `d-*` display utilities are `!important` and override Alpine's `x-show`. An element with `x-show` must not carry one. Put the flex layout on a wrapper inside it.
- The reader picker keeps a transparent native `select` over a display that adds the device id line.
- `ark-deposit-breakdown.js` finds the dialog by `data-deposit-breakdown-dialog` and focuses `data-ops-deposit-breakdown-close`. It marks an excluded line with `opacity-50`.

### Advisor follow-ups and tasks (`operations.repair-orders.show`, `operations.customers.show`)

`operations.work.partials.advisor-work-context-panel` lists the open follow-ups and shop tasks for a repair order (bottom of the rail) and for a customer (hub Work tab). It is Tabler on both pages and no longer sits in an island.

- The partial renders flush sections, a `subheader` and a `list-group-flush` for each list, with no frame of its own. The repair order wraps it in a `card` in the rail. The hub puts it under a `border-top` inside the tab card.
- Each row keeps its Done form: `PATCH` to `operations.work.follow-ups.complete` or `operations.work.tasks.complete` with the `redirect` back to the page.
- Follow-ups use `text-purple` and tasks use `text-green`, matching the violet and emerald of the work surface. The legacy left accent is not reproduced, because the legacy preflight turns a single-side Tabler border into a full box.

The operational journey card above it stays legacy. Both controllers pass `operationalJourney => null` and no class supplies a journey, so it never renders. The rail includes its island only when a journey is present.

### Time clock (`operations.time-clock.index`)

The punch screen staff open from the sidebar. It's a Tabler `focused` page, with the staff member's name as the title and "Time clock" as the pretitle.

- **Own punches:** a card with today's elapsed time and compensable hours, the current status, and Clock In, Out for Lunch, and Clock Out. Clock In is `btn-success`, Clock Out `btn-primary`, and Out for Lunch `btn-warning`. Today's punches are a `list-group-flush` card.
- **Staff punches:** for staff who manage or punch for others, a card listing each person's status with the same punch buttons (`btn-sm`) and a History link.
- **Alerts:** status and errors use `alert-success` and `alert-danger`. Punches open past shop midnight use `alert-warning` with links to each person's history.

Unchanged: `TechnicianTimeClockController`, the routes, `TechnicianTimeClockProjection`, the `canManage` and `canPunchForStaff` checks, and the form fields, including the `lunch` input.

Still legacy: the staff history page (`operations.time-clock.staff`), with auto clock, punch corrections, and deletions.

### Appointment detail (`operations.appointments.show`)

The page a Schedule card opens. It's a Tabler `focused` page with `stack-actions`: the appointment date and time as the pretitle, the contact name as the title, and Schedule as the back link.

- **Summary card:** the contact line, "Not linked to a customer yet", and the status, length, and reserved labor line sit in the first card body, because the page subtitle is hidden on phones. Concern and notes follow, then Vehicle and Advisor in a `datagrid`, then the actions in the footer. Check In / Create RO is the one `btn-primary`; Open RO replaces it once a repair order exists.
- **Customer text and Reschedule or edit:** still `<details>` cards, so `?comms=1` and `?edit=1` open them as before. The summary is a `card-body`, which keeps the browser's disclosure marker.
- **Status:** a `btn-list` of status forms at full button size.

Unchanged: `AppointmentShowController`, the routes, and every form's action and fields.

The date, time, length, and reserved labor fields are the shared components described under "Schedule appointment" below.

### Schedule appointment (`operations.schedule`, `operations.appointments.create`)

The booking page opened from Schedule, a customer, a vehicle, a repair order, or a conversation. It's a Tabler `focused` page titled "Schedule appointment", with the booking hint as the subtitle and "Back to schedule" as a `<x-tabler.page-action>`, which is icon-only on phones.

- **Customer search:** one card with the search box and Search in an `input-group`, and New caller as the primary button. Matches are a `list-group-flush` in the same card. With no match, the card adds "Schedule with name & phone" and the add-customer form, which posts to `operations.customers.store` with the same `schedule_*` fields.
- **Booking form:** one card. The first body holds the linked customer line (name, phone, Customer hub, and the repair order link), or the Caller `form-fieldset` for a booking without a customer. Then the date and time fields, Vehicle (with a customer), Concern, Notes, and Advisor. Schedule is the footer's primary button.
- **Shared components:** `x-operations.appointment-datetime-fields` and `x-operations.appointment-reserved-labor` render Tabler markup. Both appointment pages use them, so the detail page's Reschedule form no longer needs an island. The requested-time preference from a lead is an `alert-info`. Reserved labor is a `form-fieldset`. Its Auto and Manual panels toggle with Alpine `x-show` and carry no `d-*` classes, and only one `estimated_labor_hours` input is enabled at a time, as before.

Unchanged: `PresentAppointmentCreateForm`, `AppointmentStoreController`, the routes, the query parameters, and every field name.

The sidebar's Schedule item matches `operations.appointments.*`, so it is not highlighted on `operations.schedule`. That comes from `StaffNavigation`, on legacy and Tabler pages alike.

### Job Board (`operations.index`)

The advisor landing page at `/app` (view `operations.home`). It's a Tabler `fluid` page with no page header. Like the legacy board, it opens on the filter row.

- **Filters:** search, the employee select, and Check In (primary, for staff who manage repair orders). The row sticks to the top while the lanes scroll, as the legacy toolbar did. Alpine still filters the cards and the lane counts on the client.
- **Lanes:** `.ark-board` in `tabler.css`. From `md` the five lanes share the width, which fits beside the sidebar from 1280. On phones a lane is 85% of the screen and the board swipes sideways, one lane at a time, with the next lane's edge in view; the page no longer stacks every lane into one tall column. Each lane header is a `card-sm` with `card-status-top` in the lane's color, from `RepairOrderStatusColor`.
- **Cards:** `card card-sm`. They show the RO number and total (`ark-money`; balance due in `text-danger`), the exception line with its "+N" list as a Bootstrap dropdown (fixed strategy, so the lane's overflow does not clip it), the vehicle, the customer, the status badge in the shop's configured status color, and the age. Attention uses `card-status-start`: yellow for watch and attention, red for critical, with the activity footer tinted for attention and critical. The recommended card has `border-primary`.
- **Activity marks:** ARK's own icons, sized to 16px, in `avatar avatar-xs` squares colored by state, with a Bootstrap tooltip for each. Viewed, replied, check, and alert badges are Tabler avatar status dots. Each mark is `role="img"` with its tooltip text as the accessible name, and `tabindex="-1"` with the `hover focus` trigger. On a phone, a tap focuses the mark and shows the tooltip, and tapping elsewhere hides it; the marks add no tab stops.

Unchanged: `OperationsHomeController`, the projections, the routes, the `#ops-card-ro-{number}` anchors that triage links target, and the `data-home-search` and `data-home-tech-id` filter attributes.

`x-tabler.status-badge` now renders the slate tone as an outline badge. `bg-dark-lt` forces near-black text, which made slate statuses such as Draft unreadable in the dark theme on every page that uses the badge.

### Work queues: Customer Decisions (`operations.work.queue`)

The Tasks, Follow-Ups, and Scheduled queues were already Tabler. The Customer Decisions queue's lanes no longer sit in an `.ark-legacy` island.

- **Lanes:** `customer-decision-pressure-section` is a `card` with `card-status-top` in the lane tone (orange for approval, yellow for blocked, secondary for move), the title with its count badge, the note as the card subtitle, and Back to Work as a card action. They stay in `.ark-board`, so on phones each lane is 85% of the screen and the board swipes sideways.
- **Rows:** `customer-decision-pressure-rows` renders each decision as a `list-group-item`: dollars, customer, and vehicle link to the repair order; age, detail, the scheduled note, and the last customer activity sit at the right and wrap under the name on phones. A reminder row is `bg-warning-subtle` with a Reminder badge. Text, Call, Customer, and RO are small buttons.
- **Quick-add and schedule:** follow-up, task, and schedule are `<details>` whose summary is a ghost button and whose panel is a `card card-sm`. `ark-work-quick-add.js` still opens one panel per row, moves it into the row's panel slot, posts follow-ups and tasks as JSON, and shows the server's validation message in the panel. The schedule form still submits normally and redirects with a flash.

The rows and panels use `data-*` hooks instead of the legacy class names, because `app.css` still styles `ops-*` classes on a Tabler page: `data-decision-row`, `data-decision-row-foot`, `data-decision-row-actions`, `data-decision-row-panel-slot`, `data-decision-quick-add="{follow-up|task|schedule}"`, `data-decision-quick-add-owner`, `data-work-item-quick-add-form`, `data-quick-add-feedback`, `data-quick-add-error`, and `data-decision-schedule-form`. The script's selectors still match the old class names too. The compact dispatch rows, used only by the unreachable `work-tab-queues` partial, stay legacy and have no quick-add. Unchanged: the controllers, routes, the decision lanes and their order, counts, form fields, the `ops-decision-quick-add-{RO}-{kind}` ids, and the shared `advisor-work-item-quick-add` partial's fields.

### Check In (`operations.intake.create`)

The check-in workspace: recognize the customer, choose or add the vehicle, and open the repair order. It's a Tabler `fluid` page titled "Check In", with the workspace note as the subtitle. Operations and Customer Search are `<x-tabler.page-action>`s, icon-only on phones.

- **Customer step:** two columns from `lg`.
  - **Left card, "Recognize customer":** Quick check-in (VIN or plate in an `input-group`), Search existing, and the live results. Results are `card-sm` cards with the status color bar; the selected one gains `border-primary`.
  - **Right card:** the new or update customer form. Similar customers appear in an `alert-warning` list.
- **Context band:** a card with the customer, the chosen vehicle, Change vehicle and Change customer, and Tabler `steps` for Customer, Vehicle, and Open RO. "Customer information needed" is an `alert-warning`. "VIN missing" uses `<x-tabler.vehicle-identity-chip>`.
- **Vehicle step:** a header card with the last-vehicle shortcut and the filter for four or more vehicles, then an `.ark-card-grid` of vehicle cards with "Use this vehicle", then the add-vehicle form.
- **Open step:** Reason for visit and Visit & defaults cards. Visit and billing class are `form-selectgroup` pills, so each choice is a full-size touch target. Open Repair Order is full width on phones.

The page still shows only check-in markup. `customer-contact-lines` renders the result's contact lines in Tabler markup; `customer-search-card-badges` and `customer-search-card-footnote` had no other user and are removed.

The billing class help is `<x-tabler.help-tip>`, a popover on hover or focus. The previous-visit suggestions under Reason for visit are a Tabler `dropdown-menu` listbox (`repair-order-mention-suggest-tabler`), driven by the same `arkRoMention` component; the repair order workspace keeps `repair-order-mention-suggest`. Check In has no legacy markup left.

Unchanged: the intake controllers, routes, query parameters, form fields, and the `arkIntake*`, `arkVehicleDecode`, and `arkRoMention` components. Three script lines moved off legacy classes: `ark-intake-workspace-memory.js` finds the workspace by `[data-intake-find-workspace]` and `[data-intake-open-stack]`, and `ark-intake-find-customer.js` marks the selected result with `border-primary`.
