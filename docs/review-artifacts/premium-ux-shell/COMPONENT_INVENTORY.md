# Shared component inventory

| Component | Path | Role |
|-----------|------|------|
| Ops app wrapper | `resources/views/components/operations/app.blade.php` | Chooses Tabler vs legacy shell |
| Tabler shell | `resources/views/components/operations/shell/tabler.blade.php` | Sidebar + top bar + tabs + main |
| User menu | `.../shell/tabler-user-menu.blade.php` | Account / theme |
| Page | `resources/views/components/tabler/page.blade.php` | Layout + pushes context |
| Page context | `resources/views/components/tabler/page-context.blade.php` | Title / crumbs / actions |
| Page action | `resources/views/components/tabler/page-action.blade.php` | Primary/secondary actions |
| Workspace tabs mount | `resources/views/components/operations/workspace-tabs.blade.php` | Strip host |
| Tabs engine | `resources/js/ark-workspace-tabs.js` | Multitasking + dirty |
| Dirty bindings | `resources/js/ark-workspace-dirty-bindings.js` | Form → dirty |
| Dirty policy | `resources/js/ark-workspace-dirty-policy.js` | Flush / confirm rules |
| Form unsaved | `resources/js/ark-form-unsaved.js` | Baseline compare |
| Modal | `resources/js/ark-workspace-modal.js` | Modal drafts / discard |
| Continuity | `resources/js/ark-worksheet-continuity.js` | Autosave + morph |
| Page overflow | `resources/js/ark-page-context.js` | Narrow action overflow |
| Chrome CSS | `resources/css/tabler.css` | Top bar / tabs sticky tokens |
| Spec | `docs/engineering/TABLER.md` | Shell + layouts |

## Needs correction (post-approval)

| Gap | Approach |
|-----|----------|
| Check In docked in strip | Config/`docked_contextual` + list/queue primaries |
| Customer name only in bar | Identity block in customer show |
| Fleet label | Rename to Vehicles |
| No vehicle show | New ops vehicle workspace using page-context |
| Sidebar fold persist | Wire Tabler fold + localStorage |
| Hamburger placement | Always in global header on mobile |
