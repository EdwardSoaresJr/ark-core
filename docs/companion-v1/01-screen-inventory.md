# Deliverable 1 — Screen Inventory

**Rule:** Every single screen. No wireframes-only. No "TBD." No surprises before Flutter.


**Columns:** Screen · Job · Role(s) · **Spec** (`screens/*.md`) · Ref · Status

**Status:** ⬜ not defined · 📝 drafted · ✅ Edward signed

---

## Launch & account

| Screen | Job | Roles | Spec | Ref | Status |
|--------|-----|-------|------|-----|--------|
| Launch / splash | Brand + load session | All | [`launch-login.md`](screens/launch-login.md) | — | 📝 |
| Login | Staff sign-in | All | [`launch-login.md`](screens/launch-login.md) | — | 📝 |
| Shop select | Pick tenant (multi-shop future) | All | [`launch-login.md`](screens/launch-login.md) | 2689 switcher ref | 📝 |
| PIN / station unlock | Operator at shared device | All | [`launch-login.md`](screens/launch-login.md) | P1 section | 📝 |
| Permissions prompt | Notifications · mic · phone | All | [`launch-login.md`](screens/launch-login.md) | — | 📝 |

---

## Home & continuity

| Screen | Job | Roles | Spec | Ref | Status |
|--------|-----|-------|------|-----|--------|
| **Home** | What changed since last unlock — not KPI dashboard | Advisor · Owner | [`home-continuity.md`](screens/home-continuity.md) | | 📝 |
| Morning continuity detail | Expand one continuity moment | Advisor | [`morning-continuity-detail.md`](screens/morning-continuity-detail.md) | — | 📝 |
| Notification detail (routing) | Deep link resolver — usually skip as standalone | — | Merged into push routes + inbox | — | — |

---

## Communications

| Screen | Job | Roles | Spec | Ref | Status |
|--------|-----|-------|------|-----|--------|
| Communications hub | Who needs a reply · calls · threads | Advisor | | Merged into list + calls library | | — |
| Conversation list | All threads — filters · search | Advisor | [`conversation-list.md`](screens/conversation-list.md) | 2748, 2749 · Quo | 📝 |
| Conversation thread | Messages · quick actions · context rail | Advisor | [`conversation-thread.md`](screens/conversation-thread.md) | 2756–2759 · Quo | 📝 |
| Compose / reply sheet | Send SMS · attach · quick replies | Advisor | [`compose-reply-sheet.md`](screens/compose-reply-sheet.md) | 2756 | 📝 |
| Internal note | Staff-only note on thread | Advisor | [`internal-note.md`](screens/internal-note.md) | Manage sheet | 📝 |
| **Active call** | In-call · minimal · context visible | Advisor | [`active-call.md`](screens/active-call.md) | 2760 | 📝 |
| Outgoing call | Dial · confirm customer | Advisor | [`outgoing-call.md`](screens/outgoing-call.md) | 2761–2763 | 📝 |
| Voicemail list | Missed · VM playback | Advisor | [`calls-library.md`](screens/calls-library.md) | Quo missed | 📝 |
| Voicemail detail | Play · mark handled · callback | Advisor | [`calls-library.md`](screens/calls-library.md) | Same spec | 📝 |
| Calls history | Recent calls library | Advisor | [`calls-library.md`](screens/calls-library.md) | Same spec | 📝 |

---

## Search

| Screen | Job | Roles | Spec | Ref | Status |
|--------|-----|-------|------|-----|--------|
| **Global search** | Command palette — customer · vehicle · RO · phone | Advisor · Owner | [`global-search.md`](screens/global-search.md) | 2752, 2762 | 📝 |
| Search results | Emma → call · text · RO · pay · schedule · history | Advisor | [`global-search.md`](screens/global-search.md) | Modes B/C in same spec | 📝 |
| Recent searches | Speed repeat | Advisor | [`global-search.md`](screens/global-search.md) | Mode A | 📝 |

---

## Customer · vehicle · RO

| Screen | Job | Roles | Spec | Ref | Status |
|--------|-----|-------|------|-----|--------|
| Customer edit | Identity correction (rare) | Advisor | [`customer-edit.md`](screens/customer-edit.md) | — | 📝 |
| Vehicle workspace | Vehicle history · ROs · inspections | Advisor · Tech | [`vehicle-workspace.md`](screens/vehicle-workspace.md) | — | 📝 |
| **Repair order workspace** | RO command center — status · estimate · comms | Advisor · Tech | [`repair-order-workspace.md`](screens/repair-order-workspace.md) | ARK-native | 📝 |
| RO concerns list | Work grouped by concern | Advisor · Tech | [`repair-order-workspace.md`](screens/repair-order-workspace.md) | Section in RO spec | 📝 |
| Concern detail | Findings · lines · production | Tech · Advisor | [`concern-detail.md`](screens/concern-detail.md) | — | 📝 |
| Estimate review | Customer-facing estimate read mode | Advisor | [`repair-order-workspace.md`](screens/repair-order-workspace.md) | Read mode P1 | 📝 |
| Estimate send | Send link · SMS confirm | Advisor | [`estimate-send-approval.md`](screens/estimate-send-approval.md) | — | 📝 |
| Approval capture | Record customer decision | Advisor | [`estimate-send-approval.md`](screens/estimate-send-approval.md) | Mode B | 📝 |
| Invoice / payment | Balance · take payment · receipt | Advisor | [`payment-sheet.md`](screens/payment-sheet.md) | — | 📝 |
| Payment methods sheet | Cash · card · terminal · link | Advisor | [`payment-sheet.md`](screens/payment-sheet.md) | 2755 keypad ref | 📝 |
| RO timeline | Scoped activity stream | Advisor | [`ro-timeline-notes.md`](screens/ro-timeline-notes.md) | — | 📝 |
| RO notes | Internal production notes | Advisor · Tech | [`ro-timeline-notes.md`](screens/ro-timeline-notes.md) | — | 📝 |

---

## Inspection & media

| Screen | Job | Roles | Spec | Ref | Status |
|--------|-----|-------|------|-----|--------|
| Inspection overview | Item list · progress | Tech · Advisor | [`inspection-overview.md`](screens/inspection-overview.md) | — | 📝 |
| **Inspection item** | Finding · photos · video · measurements | Tech | [`inspection-item.md`](screens/inspection-item.md) | — | 📝 |
| Camera capture | Photo for finding | Tech | [`inspection-item.md`](screens/inspection-item.md) | Inline capture | 📝 |
| Video capture | Video for finding | Tech | [`inspection-item.md`](screens/inspection-item.md) | Inline capture | 📝 |
| Photo viewer | Full-screen · share | All | [`photo-viewer.md`](screens/photo-viewer.md) | — | 📝 |
| Photo grid (RO) | All media on RO | Advisor · Tech | [`concern-detail.md`](screens/concern-detail.md) | Media section | 📝 |
| Advisor: inspection review | Approve findings · build estimate | Advisor | [`inspection-item.md`](screens/inspection-item.md) | Advisor mode | 📝 |

---

## Schedule & intake

| Screen | Job | Roles | Spec | Ref | Status |
|--------|-----|-------|------|-----|--------|
| Appointment detail | Customer · vehicle · RO link | Advisor | [`appointment-detail.md`](screens/appointment-detail.md) | 2747 | 📝 |
| Check-in / arrival | Customer arrived → RO | Advisor | [`check-in-arrival.md`](screens/check-in-arrival.md) | — | 📝 |
| Quick intake | Walk-in · new RO | Advisor | [`quick-intake.md`](screens/quick-intake.md) | — | 📝 |

---

## Technician product (separate map — see role doc)

| Screen | Job | Roles | Spec | Ref | Status |
|--------|-----|-------|------|-----|--------|
| My work | Assigned ROs only | Tech | [`my-work.md`](screens/my-work.md) | — | 📝 |
| Bay orientation | Where am I · what's next | Tech | [`bay-orientation.md`](screens/bay-orientation.md) | — | 📝 |

---

## Owner / manager

| Screen | Job | Roles | Spec | Ref | Status |
|--------|-----|-------|------|-----|--------|
| Owner pulse | Daily numbers — optional · not home for advisor | Owner | [`owner-pulse.md`](screens/owner-pulse.md) | — | 📝 |
| Shop feed (owner) | Shop-wide continuity | Owner · Manager | [`shop-feed-owner.md`](screens/shop-feed-owner.md) | — | 📝 |

---

## Profile & settings

| Screen | Job | Roles | Spec | Ref | Status |
|--------|-----|-------|------|-----|--------|
| Profile | Operator identity · presence | All | [`settings-profile.md`](screens/settings-profile.md) | — | 📝 |
| Settings | Notifications · theme · phone | All | [`settings-profile.md`](screens/settings-profile.md) | — | 📝 |
| Phone / SIP status | Registration · troubleshooting | Advisor | [`settings-profile.md`](screens/settings-profile.md) | Operator language | 📝 |
| About / support | Version · help | All | [`settings-profile.md`](screens/settings-profile.md) | — | 📝 |

---

## System sheets (not full screens — still inventory)

| Surface | Job | Status |
|---------|-----|--------|
| Action sheet | Context actions on any card | [`system-surfaces.md`](screens/system-surfaces.md) | 📝 |
| Bottom sheet | Reply · pay · schedule shortcuts | [`system-surfaces.md`](screens/system-surfaces.md) | 📝 |
| Filter sheet | Comms · calls · schedule filters | [`system-surfaces.md`](screens/system-surfaces.md) | 📝 |
| Share sheet | OS share for photos / links | [`photo-viewer.md`](screens/photo-viewer.md) | 📝 |
| Error / offline | Retry · calm copy | [`system-surfaces.md`](screens/system-surfaces.md) | 📝 |
| Empty states | No messages · no ROs · etc. | [`system-surfaces.md`](screens/system-surfaces.md) | 📝 |

---

**Rule:** Every row ends with a **production screen spec** in [`screens/`](screens/) — not ⬜ inventory-only.

