# ARK design standard (proposed)

Tabler remains the only component system. This document defines ARK-specific chrome and density so staff screens feel like one product.

## Navigation hierarchy

1. **Global shell** — sidebar, top-bar global cluster (Inbox, search, station, account).
2. **Workspace strip** — open entity tabs only.
3. **Context header** — current record identity + contextual actions.
4. **Content** — the work.

## Typography

| Role | Size / weight |
|------|----------------|
| Context title | ~1.05–1.125rem / 650 |
| Identity name in content | 1.125rem / 700 |
| Section title | 0.95rem / 650 |
| Body / meta | 0.875rem / 400–600 |
| Crumbs / saving | 0.75rem |

## Spacing & density

- Page body: Tabler `--tblr-page-padding`.
- Card internal padding: ~0.9–1rem.
- Stack gap: 0.75–1rem (`g-3`).
- Prefer one primary question answered above the fold; avoid decorative empty cards.

## Chrome dimensions

| Element | Token / value |
|---------|----------------|
| Top bar | `--ark-topbar-height: 3.5rem` |
| Workspace tabs | ~2.5rem; sticky under top bar |
| Sidebar expanded | ~15rem |
| Sidebar collapsed | ~4.25rem icon rail |
| Content max | fluid; avoid ultra-wide unreadably wide text blocks |

## Buttons

- **Primary:** one per context header (Create RO, Authorize, Check In when contextual).
- **Secondary:** outline; overflow into “More page actions” below `md`.
- Do not duplicate the same primary in strip + header + card without a workflow reason.

## Status & color

- Use Tabler badge `*-lt` variants.
- Red: live call, overdue follow-up, credit due, failed save.
- Orange/warn: in-shop / needs auth.
- Green: paid / complete.

## States

| State | Treatment |
|-------|-----------|
| Loading | Existing Tabler/ARK busy patterns; no full-page flash for autosave |
| Saving | Quiet label near title |
| Saved | “Saved” / clear after idle |
| Failed save | Error tone + Retry; keep edit |
| Empty | One sentence + one action |
| Disabled | Respect existing capability checks |

## Responsive

- Sidebar: off-canvas under ~900–992px; hamburger in global header.
- Context actions: primary visible; secondaries overflow.
- Workspace tabs: keep desktop multitasking; mobile focuses single record (current behavior ≥1024).

## Themes

- Light default; dark via existing account theme control (`data-bs-theme`).
- Prototypes demonstrate both; production tokens stay on `--tblr-*` / existing `--ark-*`.

## Autosave trust

- No false workspace discard dialogs after hydrate, navigate, or successful autosave.
- Modal drafts confirm with the **modal** name/copy.
- Failed writes never silently discard.
