# Unified Layout Preferences and System Appearance

**Stopped for owner review.** No merge, deploy, or SaaS Core-pin change.

## Exact revisions

| Role | SHA |
|------|-----|
| Core `origin/main` baseline | `9cc852899b2ab9754185675b7d83d656978b2b33` |
| This branch tip | *(filled after push)* |
| SaaS / Stancl `origin/main` (unchanged pin) | `df735e4096e94eb8f0440f5124794ea924a88d2a` → Core `9cc85289` |

Related open PRs left alone: #254 List UX, #256 Card hierarchy, #255 DataTables eval, #257 collection List/Cards.

## What shipped

- Shared contract: `UiLayoutModule`, `UiPreferenceScope`, `UiLayoutPreference`, `ark-ui-layout-preference.js`
- Job Board uses browser-local prefs scoped by tenant + user + `job_board`
- Clean URLs: no `?view=` on navigation/forms; bookmarks accepted then stripped
- Appearance: Light / Dark / System with head-blocking boot (no FOUC); System follows OS
- Docs: `docs/engineering/TABLER_LAYOUT_PREFERENCE_MIGRATION.md`

Customers / Vehicles / RO indexes **not** rewired here (contract reserved for #257 integration).

## Screenshots

`review-artifacts/unified-layout-preferences/screenshots/`

- `job-board-list-1440.png`
- `job-board-cards-1440.png`
- `job-board-list-390.png` (explicit List on phone — compact mobile List)
- `appearance-light-dark-system-1440.png`

## Tests

- Unit + Job Board ListView + DisplayTheme: **34 passed**
- Vite production build refreshed (`public/hot` absent)

## SaaS risks

- Bind stable `ShopChannelScope` so tenant keys do not collide.
- Keep EncryptCookies exceptions for layout + display-theme cookies.
- Do not change SaaS Core pin in this workstream.

## Remaining

- Owner visual acceptance.
- Wire collection indexes (#257) to the shared JS store in a later merge slice.
- Optional: retire legacy layout cookies after a soak period.
