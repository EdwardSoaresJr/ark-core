# Tabler layout preference migration

## Goal

One preference contract for List/Cards across Job Board and (later) Customers, Vehicles, and Repair Orders indexes, plus Light / Dark / System appearance — without competing stores or dirty `?view=` navigation URLs.

## Authorities (do not invent a second)

| Setting | Authority | Notes |
|---------|-----------|--------|
| Job Board List/Cards | Browser `localStorage` scoped by **tenant + user + module** | Dual-writes legacy `ark_job_board_layout` cookie for SSR |
| Customers / Vehicles / RO List/Cards | Same browser API (`UiLayoutModule::*`) | **Not wired in this PR** — reserved keys only; integrate in a later slice |
| Appearance Light/Dark/System | `users.display_theme` (DB) | Synced to `ark_display_theme` cookie via `SyncEcosystemDisplayThemeCookie` |

## Layout precedence

Effective layout for a module:

1. Explicit browser store for `ark.ui.layout.v1:{tenant}:{user}:{module}`
2. Legacy unscoped cookie for that module (migrated into the store on first read)
3. Server `data-*-layout` when request had `?view=` or a legacy cookie (first paint)
4. **Auto**: List at `min-width: 768px`, Cards below

Manual List or Cards always wins over the responsive default. Explicit List on a phone keeps the compact mobile List presentation — Cards are never forced.

### Clean URLs

- Accept bookmarked `?view=list|cards|board` once, persist, then **strip** `view` from the address bar.
- Pagination, sort, lane, and filter links **must not** append `view`.
- Filter forms must not submit a hidden `view` field.

## Appearance precedence

1. Authenticated `User::displayTheme()` (default **Light** for fresh users)
2. Ecosystem cookie mirrors the user preference for cross-surface paint
3. **System** resolves with `prefers-color-scheme` and listens for OS changes
4. `arkToggleTheme` remains an explicit Light ↔ Dark shortcut that PATCHes the profile API

Blocking boot runs in `<head>` before CSS (`partials/operations/display-theme-boot`) to avoid light/dark flashes. `localStorage.ark_theme` is a last-resolved paint hint only, not the preference authority.

## Helper / API contract

| Layer | Symbol |
|-------|--------|
| PHP modules | `UiLayoutModule` |
| PHP scope | `UiPreferenceScope::forRequest()` |
| PHP resolve / legacy cookies | `UiLayoutPreference` |
| Job Board façade | `JobBoardLayoutPreference` (delegates) |
| JS store | `resources/js/ark-ui-layout-preference.js` |
| Job Board UI | `resources/js/ark-job-board-layout.js` |

Collection indexes (#257) should call the same JS helper with `UiLayoutModule.Customers|Vehicles|RepairOrders` instead of maintaining a parallel cookie-only path.

## Compatibility

- Legacy Job Board cookie remains readable and EncryptCookies-excepted.
- Reserved collection cookie names are excepted so a later merge does not brick first-party JS.
- `view=board` continues to map to Cards.

## SaaS integration risks

- Hosted shops must keep EncryptCookies exceptions for `ark_display_theme`, `ark_job_board_layout`, and (when collections ship) `ark_collection_layout_*`.
- Tenant key uses `ShopChannelScope` when bound; otherwise the request/app host. SaaS must bind a stable shop id so preferences do not collide across tenants on a shared browser profile.
- Do not change the SaaS Core pin in this workstream.
- Appearance remains user-row authority — do not move it solely into localStorage.

## Out of scope here

- Wiring Customers / Vehicles / Repair Orders blades to the new store (owned by the collection List/Cards PR).
- Job Board Card visual hierarchy / List UX PRs (#254–#256).
- DB-backed layout preferences / shop defaults.
