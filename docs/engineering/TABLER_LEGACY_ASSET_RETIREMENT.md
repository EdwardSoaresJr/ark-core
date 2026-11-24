# Tabler legacy asset retirement

Tabler is the sole design system for staff screens. Fully migrated Tabler pages must eventually stop loading unnecessary legacy ARK CSS, JavaScript, and competing framework dependencies.

This document is the living inventory, precedence rules, and phased backlog. It does not restate the product boundary.

## Current load path (verified)

`ui="tabler"` → `components/operations/app.blade.php` Vite entries, in order:

1. `resources/css/app.css` (legacy Tailwind + ops islands)
2. `resources/css/tabler.css` (Tabler + ARK Tabler overlays; PostCSS excludes `.ark-legacy *`)
3. `resources/js/app.js` (Alpine + shared ops initializers)
4. `resources/js/tabler.js` (`@tabler/core` + page-context)

Legacy (non-`ui="tabler"`) shells load only `app.css` + `app.js`. Guest / customer portal use their own Vite lists (`components/customer/shell.blade.php`, `layouts/guest.blade.php`).

**Do not stop loading `app.css` on Tabler pages while any `.ark-legacy` island remains on those pages.** The guide modal alone embeds an island on every OperationsAccess Tabler page.

## Bundle baseline → first cleanup (main `9cc85289`)

Measured with `npm run build`, `public/hot` absent:

| Asset | Before (bytes) | After (bytes) | Notes |
|-------|----------------|---------------|-------|
| `tabler.css` | 864,738 | 864,738 | unchanged |
| `app.js` | 534,688 | 533,087 | Preline import removed |
| `app.css` | 427,914 | 422,587 | Preline dropped from Tailwind content scan |
| Preline `dist-*.js` | 373,037 | **0** | dynamic chunk eliminated |
| `tabler.js` | 147,973 | 147,973 | unchanged |
| **Total shipped entries** | **2,348,856** | **1,968,891** | **−379,965 (~16%)** |

Gzip (Vite report): Preline chunk was ~83.8 KB gzip; that network cost is gone on every staff page.

## Evidence: Preline

| Check | Result |
|-------|--------|
| Blade `hs-*` / `data-hs-*` widgets | None outside unused `ark-disclosure` |
| `x-ark-disclosure` consumers | **Zero** |
| `HSStaticMethods` / Preline APIs in JS | Only former `app.js` autoInit |
| Tests referencing Preline | None |
| Runtime on Job Board (browser) | No `dist-*.js` request after cleanup |

Preline remains in `package.json` until a dedicated uninstall PR (soak + SaaS confirmation). It is no longer imported or scanned.

## Tabler pages vs legacy islands

- **~66** staff views opt into `ui="tabler"`.
- **Direct `.ark-legacy` wrappers still required for:**
  - Guide modal article prose (`learn/guide-modal`) — present on every ops Tabler shell
  - Repair order worksheet islands (`worksheet-island` on RO show / estimate editor)
  - Print helpers when `:printing="true"`
  - Estimate document printable `<article>`
  - Customer hub operational journey card (when rendered)
- Communications telephony settings / event-bubble rows: still styled by legacy classes via includes; treat as communications-owned migration (see `TABLER.md` inventory).

Job Board, Customers index, Vehicles index, and Repair Orders index blades themselves have **no** page-local `.ark-legacy`, but they still inherit the guide-modal island from the Tabler shell → **`app.css` stays**.

## `app.js` initializers (all pages)

Many `init*` calls already no-op when their DOM hooks are absent (e.g. `initMobileNav` requires `[data-ops-mobile-nav]`, which Tabler does not render). Static imports still ship their code. Splitting a Tabler shell entry vs legacy/RO entry is a later PR (large surface).

## First safe cleanup (this PR)

1. Stop dynamic `import('preline')` in `app.js`.
2. Remove Preline from Tailwind `content` scan.
3. Delete unused `resources/views/components/ark-disclosure.blade.php`.
4. Drop obsolete `.hs-dropdown-open` guard in keyboard shortcuts (use ARIA instead).

**Not in this PR:** removing `app.css` from Tabler Vite lists, rewriting RO worksheet CSS, Job Board design, or Customers/Vehicles/RO List/Card feature branches.

## Phased backlog

| Phase | Work | Gate |
|-------|------|------|
| A (done here) | Drop unused Preline runtime + dead disclosure | Bundle −Preline; smoke Tabler pages |
| B | Migrate guide-modal prose off `.ark-legacy` | Enables evaluating `app.css`-free simple indexes |
| C | Split `app.js` into `ops-shell.js` + page islands (RO, intake, comms) | Code-split with route/manifest evidence |
| D | Migrate worksheet / print / journey / telephony islands | Per-island PRs; keep coexistence plugin until last island |
| E | Stop loading `app.css` on Tabler pages with zero islands | Delete `postcss-exclude-legacy-islands` when unused |
| F | `npm uninstall preline` after soak | Confirm SaaS/public surfaces |

## SaaS risks

- Hosted shops inherit Core Vite entries; dropping Preline is safe if they do not inject Preline markup (none found in Core).
- Do not change the SaaS Core pin in this workstream.
- Keep coexistence PostCSS plugin until islands are gone — shared class names still collide.

## Related open work (do not collide)

- #254 / #256 Job Board UX
- #257 collection List/Cards
- #258 layout preferences / appearance
