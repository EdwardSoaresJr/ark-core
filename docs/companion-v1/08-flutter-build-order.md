# Flutter build order — after Edward sign-off

> **Superseded for product guidance** by [`MISSION.md`](MISSION.md) milestone model (Inbox → Thread → Calling → …).  
> Kept for historical slice tracking and API reference.

**Do not start until:** [`product-review/edward-sign-off-checklist.md`](product-review/edward-sign-off-checklist.md) P0 ✅

**Build from:** [`screens/`](screens/) + [`07-api-projection-backlog.md`](07-api-projection-backlog.md)  
**Legacy UI:** frozen — [`frozen-flutter-ui.md`](frozen-flutter-ui.md)  
**Floor test:** [`../mobile/companion-sprint-1-run-the-shop.md`](../mobile/companion-sprint-1-run-the-shop.md)

---

## Sprint 1 — Run the shop from pocket (vertical slice)

Build in order — each slice is shippable to Edward's Razr:

| Order | Slice | Specs | API |
|-------|-------|-------|-----|
| **1** | Shell + auth | `launch-login.md` · tab bar · role route | login · capabilities | ✅ |
| **2** | Home continuity | `home-continuity.md` · `notifications-inbox.md` | continuity · notifications | ✅ |
| **3** | Push deep links | routing only | deep_link payload | ✅ |
| **4** | Comms list + thread | `conversation-list.md` · `conversation-thread.md` · `compose-reply-sheet.md` | conversations | ✅ |
| **5** | Search → act | `global-search.md` | search | ✅ |
| **6** | Incoming + active + post | `incoming-call.md` · `active-call.md` · `post-call.md` | incoming context | ✅ Flutter · deploy API |
| **7** | RO workspace | `repair-order-workspace.md` · `concern-detail.md` | RO projection | ✅ |
| **8** | Payment | `payment-sheet.md` | payments | ✅ |
| **9** | Inspection push | `inspection-item.md` · `photo-viewer.md` | inspection + push | ✅ |
| **10** | Tech my work | `my-work.md` · `inspection-overview.md` | my-work | ✅ |

**Defer:** owner pulse · quick intake · warm transfer · bay orientation

---

## New Flutter app vs legacy

**Recommendation:** New `ark_companion` module or app target — do not patch legacy module tabs.

Shared package: API client · models from projection DTOs · auth token storage.

---

## Definition of done (Sprint 1)

Edward completes [`companion-sprint-1-run-the-shop.md`](../mobile/companion-sprint-1-run-the-shop.md) on production shop data without opening desktop for:

- [ ] Reply to customer text from push
- [ ] Answer call with context · post-call note
- [ ] Search customer · open RO · take payment
- [ ] Tap Ben inspection push · view photo · reply

---

## Component implementation order

Match [`design-system/screen-component-map.md`](design-system/screen-component-map.md):

1. Identity strip · status chip · vehicle line  
2. List rows (continuity · conversation · search)  
3. Composer · message bubble  
4. Bottom sheets (payment · post-call · manage)  
5. Call overlays  
6. Photo viewer · camera  

---

## Anti-patterns (Flutter)

- Rebuilding legacy `HomeScreen` tiles  
- Client-side estimate math  
- SMS inbox parallel to Conversation  
- Push → Home → hunt  
- SIP jargon in phone settings UI  
