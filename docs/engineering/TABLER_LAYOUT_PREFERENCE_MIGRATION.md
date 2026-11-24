# Layout & appearance preference migration

**Status:** Recommendation only — do not implement the full migration in the Card View PR.  
**Related:** `JobBoardLayoutPreference`, cookie `ark_job_board_layout`, URL `?view=`.

## Desired end state

1. Clean URLs without automatically appending `?view=`.
2. Layout preference scoped by **authenticated user + tenant + module**.
3. Responsive defaults (`auto`) until the user chooses List or Cards.
4. Appearance: Light / Dark / System; System tracks OS changes.
5. Existing explicit selections preserved across devices only when a durable store is introduced — until then, browser-local is acceptable if clearly scoped.

## Current authorities (compete today)

| Authority | What | Problem |
|-----------|------|---------|
| Query `view=` | Explicit layout for a request | Pollutes URLs; shareable links bake layout |
| Cookie `ark_job_board_layout` | Cross-request persistence | Not user/tenant/module keyed; SaaS EncryptCookies needed except (#348) |
| `localStorage` theme keys | Appearance (`ark_theme` / legacy `hs_theme`) | Parallel to server user display theme |
| User model display theme | Server-resolved appearance | Must stay single source for theme |

## Cleanest next slice

**Slice A — Job Board only (recommended next PR after Cards):**

1. Keep reading `view=` when present (compat).
2. Stop *writing* `view=` into history for toggle clicks (`ark-job-board-layout.js` already uses `replaceState` — ensure clear-filters / lane links omit `view` unless explicit).
3. Persist layout in one cookie **or** `localStorage` key:  
   `ark.layout.v1:{tenantId}:{userId}:job_board` → `list|cards|auto`.
4. Document that DB sync is a later slice; do not add a second cookie and localStorage for the same fact.

**Slice B — Appearance:**

1. Treat user `displayTheme` as source of truth on authenticated pages.
2. System mode: listen to `prefers-color-scheme` and update `data-bs-theme` + legacy `html.dark` together (shell already dual-writes).
3. Retire duplicate `localStorage` writes once server theme always hydrates first.

**Slice C — Module generalization:**

1. Extract preference helper used by Job Board.
2. Customers / Vehicles / RO indexes adopt the same helper when they gain List/Cards.

## Compatibility

- Old cookie values continue to resolve for one release.
- Bookmarks with `?view=cards` still work.
- No SaaS pin required for Core-only preference JS; cookie encrypt except remains SaaS concern if cookies stay.

## Non-goals

- Competing DB + cookie + localStorage for the same layout key.
- Forcing `?view=` on every Job Board navigation.
