# Legacy ARK UI retirement on Tabler pages

**Status:** Plan only — do not delete shared assets in this assignment.  
**Rule:** Tabler is the sole design system. Do not introduce DataTables or another UI framework.

## What still loads on every Tabler staff page

From `components/operations/app.blade.php` when `ui="tabler"`:

1. `resources/css/app.css` (Tailwind + large `ops-*` legacy bundle)
2. `resources/css/tabler.css` (Tabler + ARK tokens; PostCSS excludes `.ark-legacy *`)
3. `resources/js/app.js` (full Alpine registration + many inits + dynamic Preline)
4. `resources/js/tabler.js` (`@tabler/core` + page-context)

## Inventory

| Asset / island | Classification | Notes |
|----------------|----------------|-------|
| `app.css` on Tabler | Requires migration of remaining consumers | Required while any `.ark-legacy` island or `ops-*` script hook remains |
| `app.js` full bundle on Tabler | Requires split | Most page inits no-op; still parses/registers everything |
| Preline dynamic import | Still required (today) | Used for some HS components; pulls unused transitive DataTables in lockfile only |
| Guide modal ARKademy prose | Requires migration | `.ark-legacy` island |
| Estimate printable body | Requires migration | documents/estimates |
| RO print helpers (`$printing`) | Requires migration | |
| Conditional operational journey card | Requires migration / remove if dead | `worksheet-island` |
| Telephony settings / event bubbles | Likely migrated — **re-verify** | TABLER.md “Remaining legacy” is partially stale |
| jQuery / DataTables app imports | Safe to keep **out** | Do not enable |
| Eval DataTables prototype (#255) | Do not merge into product | Review-only |

## Staged plan

1. **Inventory freeze** — regenerate live Blade `.ark-legacy` list; update `TABLER.md`.
2. **JS split** — Tabler entry loads only modules needed for shell + current page hooks.
3. **Island burn-down** — migrate or delete each island; remove wrapper when gone.
4. **Stop `app.css` on Tabler** — when islands = 0; remove PostCSS exclude plugin.
5. **Never** reintroduce a second CSS framework.

## Safe immediate candidates

None for deletion without a dedicated PR proving zero consumers. Safe *non-actions*: do not merge #255; do not add Yajra; do not load default DataTables CSS on staff pages.
