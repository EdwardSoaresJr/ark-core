# Interaction patterns — ARK Companion v1

**Rule:** These patterns repeat **everywhere** they apply. Habit-forming apps feel consistent — not one-off gestures per screen.

Each screen spec must declare which patterns it uses ([`_SPEC_TEMPLATE.md`](../screens/_SPEC_TEMPLATE.md)).

---

## Core patterns

| Pattern | Default behavior | ARK automotive twist |
|---------|------------------|----------------------|
| **Tap** | Primary navigation · select row | Row tap → workspace, not detail-only dead-end |
| **Swipe left** | Archive · mark handled · quick action | Mark call handled · dismiss continuity moment |
| **Swipe right** | Pin · call back · secondary | Call customer from thread row |
| **Long press** | Context menu · multi-select | Assign advisor · link RO |
| **Pull to refresh** | Reload list authority | Comms · home continuity · schedule |
| **Infinite scroll** | Paginate timeline · messages | Customer timeline · thread history |
| **Action sheet** | Destructive / overflow | Delete draft · transfer call (P1) |
| **FAB** | One primary create action | New message · new intake (context-gated) |
| **Search affordance** | Persistent or pull-down | Global command palette — always reachable |
| **Back** | Single stack — shell owns it | Never nested AppBars |
| **Haptic** | Send · payment recorded · call connect | Subtle — confirm money and comms |

---

## Media patterns

| Pattern | Use |
|---------|-----|
| **Camera overlay** | Full-screen capture · bay-safe · flash toggle |
| **Attachment picker** | Photo · video · camera · library |
| **Photo viewer** | Pinch · swipe between · share |
| **Video** | Inline thumb · tap full-screen |

---

## Comms patterns

| Pattern | Use |
|---------|-----|
| **Inline reply** | Composer fixed bottom · keyboard pushes content |
| **Quick reply chips** | Operational templates — not AI |
| **Unread badge** | Tab + row — same count authority |
| **Mark read** | On thread open — server authoritative |

---

## Call patterns

| Pattern | Use |
|---------|-----|
| **Incoming full-screen** | Context before answer — not minimal OS default only |
| **In-call minimal chrome** | Customer strip stays visible |
| **Post-call sheet** | Note · text · RO · schedule — no hunt |

---

## Anti-patterns (reject in spec review)

- Swipe behavior **different** on comms vs customers vs RO list without reason
- Bottom sheet for **navigation** (use for actions only)
- Pull-to-refresh on **detail** screens that should be push-updated
- FAB + duplicate primary button on same screen
- Long-press as **only** way to reach common action

---


For list-heavy screens, spec must address:

- [ ] Swipe actions on rows
- [ ] Filter chips or sheet
- [ ] Search on list
- [ ] FAB or fixed compose affordance
- [ ] Pull to refresh
- [ ] Avatar + title + subtitle + time rhythm

