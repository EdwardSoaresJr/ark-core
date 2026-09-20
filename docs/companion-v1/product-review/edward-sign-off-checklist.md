# Edward sign-off checklist — ARK Companion v1

**When:** After reading specs · before Flutter  

**Rule per screen:** *Would I carry this on the floor for ten hours?*

**References:** [`../references/external/CATALOG.md`](../references/external/CATALOG.md)

---

## How to review (15–30 min per batch)

1. Open Quo/external screenshot listed in spec header when present  
2. Read **Layout** + **Product quality gate**  
3. Check **≤3 taps** in linked flow ([`../02-flows.md`](../02-flows.md))  
4. Mark ✅ or leave 📝 with one-line note in **Notes** column  

---

## P0 — Advisor pocket (ship first)

| # | Spec | Ref | Ready? | Notes |
|---|------|-----|--------|-------|
| 1 | [`launch-login.md`](../screens/launch-login.md) | — | ⬜ | |
| 2 | [`home-continuity.md`](../screens/home-continuity.md) | 2689, 2690 | ⬜ | Continuity vs dashboard |
| 3 | [`notifications-inbox.md`](../screens/notifications-inbox.md) | doc-8 | ⬜ | Deep link every row |
| 4 | [`conversation-list.md`](../screens/conversation-list.md) | 2748, Quo threads | ⬜ | Vehicle on row |
| 5 | [`conversation-thread.md`](../screens/conversation-thread.md) | 2756–2759 | ⬜ | Manage sheet actions |
| 6 | [`compose-reply-sheet.md`](../screens/compose-reply-sheet.md) | 2756 | ⬜ | Quick links |
| 8 | [`active-call.md`](../screens/active-call.md) | 2760 | ⬜ | Shop grid |
| 9 | [`post-call.md`](../screens/post-call.md) | external | ⬜ | No disposition hunt |
| 10 | [`outgoing-call.md`](../screens/outgoing-call.md) | 2761–2763 | ⬜ | Named recents |
| 11 | [`calls-library.md`](../screens/calls-library.md) | Quo missed | ⬜ | VM + handled |
| 12 | [`global-search.md`](../screens/global-search.md) | 2752, 2762 | ⬜ | Emma → act |
| 13 | [`customer-workspace.md`](../screens/customer-workspace.md) | doc-5 | ⬜ | |
| 14 | [`vehicle-workspace.md`](../screens/vehicle-workspace.md) | — | ⬜ | |
| 15 | [`repair-order-workspace.md`](../screens/repair-order-workspace.md) | reject Opportunity | ⬜ | RO command center |
| 16 | [`concern-detail.md`](../screens/concern-detail.md) | — | ⬜ | |
| 17 | [`payment-sheet.md`](../screens/payment-sheet.md) | 2755 rhythm | ⬜ | Balance authority |
| 18 | [`estimate-send-approval.md`](../screens/estimate-send-approval.md) | — | ⬜ | |
| 19 | [`schedule-day.md`](../screens/schedule-day.md) | 2747 | ⬜ | |
| 20 | [`appointment-detail.md`](../screens/appointment-detail.md) | 2747 | ⬜ | Check-in |
| 21 | [`settings-profile.md`](../screens/settings-profile.md) | 2692 | ⬜ | Phone online copy |
| 22 | [`system-surfaces.md`](../screens/system-surfaces.md) | — | ⬜ | Errors calm |

---

## P0 — Tech + inspection (Ben flow)

| # | Spec | Ready? | Notes |
|---|------|--------|-------|
| 23 | [`my-work.md`](../screens/my-work.md) | ⬜ | No shop-wide queue |
| 24 | [`inspection-overview.md`](../screens/inspection-overview.md) | ⬜ | |
| 25 | [`inspection-item.md`](../screens/inspection-item.md) | ⬜ | **Push lands here** |
| 26 | [`photo-viewer.md`](../screens/photo-viewer.md) | ⬜ | |

---

## P1 — Drafted · sign when scheduling build

| Spec | Defer until |
|------|-------------|
| [`internal-note.md`](../screens/internal-note.md) | Thread P0 ships |
| [`check-in-arrival.md`](../screens/check-in-arrival.md) | Schedule P0 ships |
| [`quick-intake.md`](../screens/quick-intake.md) | Search P0 ships |
| [`owner-pulse.md`](../screens/owner-pulse.md) | Advisor P0 certified |
| [`bay-orientation.md`](../screens/bay-orientation.md) | Tech P0 certified |
| Remaining P1 in inventory | Floor observation |

---

## Flow sign-off (must pass together)

| Flow | ≤3 taps? | OK? |
|------|----------|-----|
| Incoming call → pocket | | ⬜ |
| Push Emma replied → reply | | ⬜ |
| Push Ben uploaded → inspection item | | ⬜ |
| Morning unlock → handle reply | | ⬜ |
| Search Emma → pay | | ⬜ |
| Thread → send estimate | | ⬜ |

Full list: [`../02-flows.md`](../02-flows.md)

---

## Global gates

- [ ] **I would carry this for ten hours** — advisor P0 set  
- [ ] No screen dumps to Home before job done  
- [ ] Every customer row shows vehicle when known  
- [ ] Phone status uses shop language (not SIP)  
- [ ] Product review table updated with ✅ rows  
- [ ] **Ready for Flutter** — date: ___________

---

## If "not yet"

Do not build. Edit the spec until **Yes**. Note what still beats us on speed or context — that becomes the redesign ticket.
