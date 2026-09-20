# Deliverable 5 — Design System Components

**Rule:** Inventory before Flutter. Every screen in [`01-screen-inventory.md`](01-screen-inventory.md) must compose from this list — no one-off widgets.

**Status:** 📝 spec'd · map in [`screen-component-map.md`](screen-component-map.md)

---

## Identity & context

| Component | Used on | Spec notes |
|-----------|---------|------------|
| **Identity strip** | All workspaces | Customer · vehicle · RO · status — persistent |
| **Status chip** | RO · concern · inspection · payment | Color = operational posture |
| **Avatar / initials** | Threads · staff | |
| **RO badge** | Comms rows · search | `#1599` + lifecycle hint |
| **Vehicle line** | Cards · strip | YMM · plate |

---

## Lists & rows

| Component | Used on | Spec notes |
|-----------|---------|------------|
| **Conversation row** | Comms list | Preview · time · unread · channel |
| **Call row** | Calls · VM | Direction · duration · handled |
| **Continuity row** | Home | What changed · age · one tap target |
| **Timeline row** | Customer · RO timeline | Event verb · evidence · time |
| **Activity row** | Shop feed | Lighter than timeline |
| **Search result row** | Search | Identity + quick actions inline |
| **Work row** | My work (tech) | Assigned RO · blocker |
| **Appointment row** | Schedule | Arrival · customer · vehicle |

---

## Cards

| Component | Used on | Spec notes |
|-----------|---------|------------|
| **Customer card** | Search · workspace header | |
| **Vehicle card** | Customer · vehicle WS | |
| **RO card** | Customer · lists | Total · status · advisor |
| **Inspection card** | RO · notifications | Progress · DVI badge |
| **Estimate summary** | RO · customer | Total · approval state |
| **Payment summary** | RO · invoice | Balance due authoritative |

---

## Comms & calls

| Component | Used on | Spec notes |
|-----------|---------|------------|
| **Message bubble** | Thread | In/out · MMS · timestamps |
| **Composer bar** | Thread | Attach · send · quick replies |
| **Quick reply chip** | Composer | Operational templates |
| **Incoming call panel** | Incoming | Context before answer |
| **Post-call action row** | Post-call | Note · text · RO · schedule |
| **Call control bar** | Active call | Mute · hang up · speaker |

---

## Media

| Component | Used on | Spec notes |
|-----------|---------|------------|
| **Photo grid** | Inspection · RO | Tap → viewer |
| **Photo viewer** | Full screen | Swipe · share |
| **Camera shutter UI** | Capture | Minimal · bay-safe |
| **Video thumbnail** | Grid · timeline | |

---

## Actions & chrome

| Component | Used on | Spec notes |
|-----------|---------|------------|
| **Primary button** | Forms · CTAs | One per screen max |
| **Secondary button** | | |
| **Destructive button** | | Rare |
| **Floating action (FAB)** | List screens | One primary add action |
| **Action sheet** | Long-press · more | iOS-style |
| **Context action bar** | Workspace bottom | Changes with workspace |
| **Tab bar** | Root | 4–5 tabs max |
| **Search field** | Global · inline | |
| **Filter chips** | Lists | Horizontal scroll |
| **Empty state** | All lists | One line + action |
| **Error banner** | Offline · failed send | |

---

## Notifications

| Component | Used on | Spec notes |
|-----------|---------|------------|
| **Push payload → route** | OS | Not UI — document in flows |
| **In-app notification row** | Notifications inbox | Same routing as push |
| **Badge (tab / app icon)** | | Unread · continuity count |

---

## Typography & spacing (set once)

| Token | Use |
|-------|-----|
| Display | Customer name on incoming call |
| Title | Screen title in shell |
| Headline | Card titles |
| Body | Messages · notes |
| Label | Metadata · timestamps |
| Spacing 4/8/12/16/24 | No per-screen inventing |

Reference: ~15–20% tighter than generic CRM — `ark product doctrine` — but **spec here in Companion terms**, not doctrine prose.

---

## Sign-off

- [ ] Every screen maps to components above (no orphan UI)
- [ ] Edward: density feels operational, not SaaS
