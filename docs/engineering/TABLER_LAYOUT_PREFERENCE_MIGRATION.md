# Tabler layout preference migration

## Goal

One preference contract for List/Cards across Job Board and (later) Customers, Vehicles, and Repair Orders indexes, plus Light / Dark / System appearance — without competing stores or dirty `?view=` navigation URLs.

## Authorities (do not invent a second)

| Setting | Authority | Notes |
|---------|-----------|--------|
| Job Board List/Cards | `users.job_board_view` (DB, per authenticated user in the shop tenant) | Dual-writes legacy `ark_job_board_layout` cookie + scoped localStorage as mirrors only |
| Customers / Vehicles / RO List/Cards | Browser `localStorage` scoped by **tenant + user + module** | **Not DB-backed in this slice** — reserved keys only |
| Appearance Light/Dark/System | `users.display_theme` (DB) | Synced to `ark_display_theme` cookie via `SyncEcosystemDisplayThemeCookie` |

## Job Board precedence

Effective layout:

1. Bookmarked `?view=list|cards|board` (one-shot → persist to `job_board_view`, then strip from URL)
2. Authenticated `users.job_board_view`
3. Legacy unscoped cookie `ark_job_board_layout` (one-time migrate into the user row)
4. Browser scoped localStorage (client one-time migrate into the user row when the account has no value)
5. **Default Cards** (desktop and mobile)

An explicit server-saved preference always wins over browser storage and viewport size. Responsive auto layout no longer overrides Job Board.

### Clean URLs

- Accept bookmarked `?view=list|cards|board` once, persist, then **strip** `view` from the address bar.
- Pagination, sort, lane, and filter links **must not** append `view`.
- Filter forms must not submit a hidden `view` field.

### Persistence API

- Job Board switch: `PATCH /app/profile/job-board-view` with `{ "job_board_view": "cards"|"list" }`
- Profile Appearance → Display Preferences uses the same column via `profile.appearance.update`

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
| PHP Job Board authority | `JobBoardLayoutPreference` (`users.job_board_view`) |
| PHP shared resolve / legacy cookies | `UiLayoutPreference` |
| JS mirror store | `resources/js/ark-ui-layout-preference.js` |
| Job Board UI | `resources/js/ark-job-board-layout.js` |

Collection indexes should call the same JS helper with `UiLayoutModule.Customers|Vehicles|RepairOrders` instead of maintaining a parallel cookie-only path.

## Compatibility

- Legacy Job Board cookie remains readable and EncryptCookies-excepted (SSR mirror).
- Reserved collection cookie names are excepted so a later merge does not brick first-party JS.
- `view=board` continues to map to Cards.
- Invalid `job_board_view` values are rejected on write and ignored on read (default Cards).

## SaaS integration risks

- Hosted shops must keep EncryptCookies exceptions for `ark_display_theme`, `ark_job_board_layout`, and (when collections ship) `ark_collection_layout_*`.
- Tenant users tables must run the `job_board_view` migration from Core.
- Appearance remains user-row authority — do not move it solely into localStorage.

## Out of scope here

- Wiring Customers / Vehicles / Repair Orders blades to DB-backed preferences.
- Drag-and-drop Kanban status changes.
