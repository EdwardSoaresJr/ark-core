# Visual acceptance package (prototypes)

Rendered with Puppeteer from the HTML prototypes (not AI concept images).

## Inspect

Open any file under [`screenshots/acceptance/`](./screenshots/acceptance/) directly in the IDE or Finder.

Interactive prototypes:

- [customer-workspace.html](./prototypes/customer-workspace.html)
- [vehicle-workspace.html](./prototypes/vehicle-workspace.html)
- [repair-order-workspace.html](./prototypes/repair-order-workspace.html)

## Viewports

| Size | Files |
|------|--------|
| 1920×1080 | `*-1920-light.png`, `*-1920-dark.png`, `*-1920-collapsed.png` |
| 1366×900 | `*-1366-light.png`, `*-1366-dark.png` |
| 390×844 | `*-390-light.png` (+ `customer-390-dark.png`) |

Extra: `ro-1920-save-failed.png`, `ro-1920-inspection.png`, `customer-1366-modal-dirty.png`.

## Before / after

| Concern | Before (v1 prototype) | After (this revision) |
|---------|------------------------|------------------------|
| Header mix | Context actions lived in the same bar as Inbox/account ([before/customer-desktop-light.png](./screenshots/before/customer-desktop-light.png)) | Global bar = hamburger/search/Inbox/theme only; context bar = identity + record actions ([acceptance/customer-1920-light.png](./screenshots/acceptance/customer-1920-light.png)) |
| RO depth | Simplified single card ([before/ro-desktop-light.png](./screenshots/before/ro-desktop-light.png)) | Customer/vehicle/visit band + Estimate/Inspection/Recommendations/Comms/History + auth/totals ([acceptance/ro-1920-light.png](./screenshots/acceptance/ro-1920-light.png)) |
| Density | Sparse customer body | Alert + vehicles + active work + history without duplicate Create RO on every card |
| Check In | Called out as strip problem in copy only | Strip shows open records / overflow only — no Check In chrome |

## Shell contract demonstrated

1. **Global header** — sidebar toggle, search, Inbox, theme/account.
2. **Workspace strip** — open tabs, active state, close, overflow.
3. **Context header** — identity, relationships, status, contextual actions (+ overflow affordance).
