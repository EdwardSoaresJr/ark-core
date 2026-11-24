# Tabler Asset Isolation — First Safe Cleanup

**Stopped for owner review.** No merge, deploy, or SaaS Core-pin change.

## Exact revisions

| Role | SHA |
|------|-----|
| Core `origin/main` baseline | `9cc852899b2ab9754185675b7d83d656978b2b33` |
| This branch tip | `9b68f541621d4d2cc0bc54267354d727156c1bb1` |
| SaaS / Stancl pin (unchanged) | `df735e4096e94eb8f0440f5124794ea924a88d2a` → Core `9cc85289` |

Left alone: #254–#258 (Job Board / List-Cards / preferences).

## Audit summary

See `docs/engineering/TABLER_LEGACY_ASSET_RETIREMENT.md`.

- Every `ui="tabler"` page still receives `app.css` + `app.js` + `tabler.css` + `tabler.js`.
- `app.css` cannot drop yet: guide-modal `.ark-legacy` island is on every ops Tabler shell.
- Preline had **no** runtime Blade consumers; its ~373 KB dynamic chunk is removed.

## Bundle measurements (`npm run build`, no `public/hot`)

| | Before | After | Delta |
|--|--------|-------|-------|
| Total entry assets | 2,348,856 B | 1,968,891 B | **−379,965 B (~16%)** |
| Preline chunk | 373,037 B | 0 | eliminated |
| `app.css` | 427,914 B | 422,587 B | −5,327 B |

## Changes in this PR

- Remove Preline dynamic import from `app.js`.
- Remove Preline from Tailwind content scan.
- Delete unused `ark-disclosure` component.
- Keyboard shortcut guard no longer references Preline class.

## Screenshots

`review-artifacts/tabler-legacy-asset-retirement/screenshots/`

## Remaining islands / backlog

Documented in the retirement doc phases B–F (guide modal, worksheet split, `app.css` drop, `npm uninstall preline`).

## Remaining blockers

- Owner review of audit + first cleanup.
- Do not remove `app.css` from Tabler Vite lists until islands migrate.
