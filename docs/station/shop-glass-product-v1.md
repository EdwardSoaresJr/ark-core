# Shop Glass product v1

**Status:** Product lock after plumbing certification (2026-08-22) · **Active milestone 2026-08-23:** command center (scheduling/`coming_in` certified; do not grow scheduling on this screen)  
**Hardware:** Shared 1920×1080 advisor touchscreen between two people  
**Not:** A second ARK. Not ARK Lite. Not a Dragon chatbot destination.

## What the Mac/Windows glass proved

Connectivity and architecture work:

- ARK is the shop
- Flutter is the glass
- Station device token (`stn_…`) authenticates GET `/api/station/*`
- Dragon is optional (Not configured is a valid shop)

That is **plumbing**. It is not enough reason for the screen to exist.

## Distinction

| ARK (advisor computer) | Glass (shared command center) |
| --- | --- |
| Transactional work | Ambient orientation between two advisors |
| Open RO, estimate, status, customer, parts/labor, DVI, invoice | Shop right now · needs action · money at risk · who is on a call |
| Edit truth | Point at truth on the shared board; change it in ARK **at a desk**, never by taking over this screen |
| One person, one keyboard | Shared glance, tap, Dragon in the work |

Do not duplicate ARK workflows on the glass.

## Home screen (target)

One canvas. Not SaaS tabs.

```
Demo Auto Repair · clock · ARK ● · Dragon ● · Ask Dragon
────────────────────────────────────────────────
SHOP RIGHT NOW          │  NEEDS ACTION
Active / approvals /    │  Ranked cards: RO · vehicle · why · age
production / parts      │  tap → glance on this board (not a browser)
Tech load               │
Today $ sold / collected│
pending approval / hours│
────────────────────────────────────────────────
DRAGON  (one paragraph on this shop, not a chat tab)
[What should we work on?] [Money on the table] [Ask]
────────────────────────────────────────────────
CALLS (ambient)         RECENT ACTIVITY
```

**Needs action** is the job of the screen. Counts without cards were a layout defect; counts-as-the-product would still be ARK Lite.

## Dragon

Dragon is **in the work**, not a destination.

- No permanent Dragon tab whose job is “type an RO number, chat”
- Card tap → overlay: posture, money if ARK exposes it, last contact, one Dragon sentence, actions
- Actions: glance on this board, then rewrite / review / ask — assists only, not editors. Never launch a browser on the shared screen.
- Persistent **Ask Dragon** in the header is enough surface

Dragon may stay **Not configured**. The glass still has a job: shop right now + needs action. Work happens in ARK on each person's computer.

## Calls

Ambient awareness, not a second phone app.

Example: *Molly is on a call — RO 1706 · 4:32 · estimate · Dragon: brake / waiting approval.*

Missed / voicemail counts may appear. Playback, claiming, and dialing stay in ARK (Calls & VM).

## Navigation

Prefer **Today / Shop / Calls**. Persistent Ask Dragon. Contextual overlays.

- **Admin** tucked (pair / unpair / version)
- **Knowledge** has no permanent tab until the floor asks for it
- **ROs / Approvals / Dragon** as peer tabs = SaaS clone — do not grow that IA

## Shared board (not a browser)

This screen sits between two people. A tap must not steal it into Chrome/Safari for one person’s ARK session.

Tap → glance on the glass (RO, vehicle, why it matters). Work happens in ARK **at each desk**. Glass never becomes a second estimate editor, and it never launches a private browser on the shared PC.

## What may appear later (only when ARK already owns it)

Financials, activity/events, estimate posture, live calls, technician capacity, parts state — as **projections** on this canvas. Dragon correlates; it does not invent.

Financial sentences on the glass wait on **Financial Authority F1**. Do not fake $ sold / collected before then.

## Display contract (locked)

Canonical appliance: **1920 × 1080 logical pixels**, 16:9, 100% scaling, touchscreen.

- Design and certify Today / Shop / Calls / overlays against that size. Do not treat a resizable Mac window as the source of truth.
- Native window is locked to 1920×1080 (not fullscreen-on-a-larger-display).
- Today must not scroll at 1920×1080. Unused lower-right space stays empty until real capability earns it.
- Queue columns (Age, Tech) are fixed widths. Age is compact (`31d`, `4d`, `9h`).
- Admin shows `Display`, device pixel ratio, and `CERTIFIED` / `NON-CANONICAL` (logical size).

## Explicit non-goals

- Opening ROs, editing estimates, changing statuses, customer CRUD, parts/labor lines, DVI capture, invoicing
- A full RO index as the home metaphor
- Channel inboxes
- Dragon as the primary navigation
- Baking Dragon `drg_…` into the installer

## Sequence

```
Plumbing certified (done)
    ↓
coming_in from ARK certified (2026-08-23) — scheduling freeze
    ↓
Command center: attention · coming in · approvals · follow-up · shop pressure
    + glance on this board (no browser steal)
    + Dragon Not configured is fine
    ↓
Dragon sentences on cards / few things that deserve attention (hosted, optional)
    ↓
Richer money / activity / calls as those ARK projections already exist
```

Do not add glass tabs to “use the screen.”
