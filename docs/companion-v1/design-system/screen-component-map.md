# Screen → component map

Every screen composes from [`components.md`](components.md). No orphan widgets in Flutter.

| Screen spec | Primary components | Interaction patterns |
|-------------|-------------------|----------------------|
| `launch-login.md` | Primary button · error banner | Tap |
| `home-continuity.md` | Continuity row · badge · tab bar | Tap · swipe left · pull refresh |
| `notifications-inbox.md` | Activity row · badge | Tap · swipe |
| `conversation-list.md` | Conversation row · filter chips · FAB · avatar | Swipe · FAB · infinite scroll |
| `conversation-thread.md` | Identity strip · message bubble · composer · bottom sheet | Inline reply · bottom sheet |
| `compose-reply-sheet.md` | Composer bar · quick reply chip | Bottom sheet |
| `incoming-call.md` | Incoming call panel · status chip · primary button | Full-screen |
| `active-call.md` | Identity strip · call control bar · context action grid | Bottom sheet over call |
| `post-call.md` | Post-call action row · identity strip | Bottom sheet |
| `outgoing-call.md` | Search field · call row · keypad | Bottom sheet confirm |
| `calls-library.md` | Call row · filter chips | Swipe · tap |
| `global-search.md` | Search field · search result row | Tap · action sheet |
| `customer-workspace.md` | Identity strip · vehicle card · RO card · timeline row | Tap |
| `vehicle-workspace.md` | Vehicle card · RO card | Tap |
| `repair-order-workspace.md` | Identity strip · status chip · RO card · context action bar | Tap |
| `concern-detail.md` | Photo grid · estimate line row · status chip | Tap → viewer |
| `payment-sheet.md` | Payment summary · bottom sheet · primary button | Keypad · bottom sheet |
| `estimate-send-approval.md` | Estimate summary · bottom sheet | Bottom sheet |
| `schedule-day.md` | Appointment row · filter chips | Tap → sheet |
| `appointment-detail.md` | Customer card · bottom sheet | Bottom sheet |
| `inspection-overview.md` | Inspection card · status chip | Tap |
| `inspection-item.md` | Photo grid · camera shutter · composer (note) | Camera · tap |
| `photo-viewer.md` | Photo viewer | Pinch · swipe |
| `my-work.md` | Work row | Tap |
| `settings-profile.md` | List sections · error banner (offline phone) | Tap |
| `system-surfaces.md` | Empty state · error banner · action sheet | All chrome |

---

## Density tokens (Companion)

| Token | px (logical) | Use |
|-------|--------------|-----|
| Row height list | 72–80 | Comms · continuity |
| Row height compact | 56–64 | Search · inspection list |
| Strip height | 56–72 | Identity strip |
| Chip height | 32 | Filters · status |
| FAB margin | 16 from edge | Lists |
| Screen horizontal padding | 16 | Body |
| Cerulean primary | `#0099cc` | ARK ecosystem · CTAs |

Reference: `ark-interface-constitution.mdc` · `ark-ecosystem-identity.mdc`
