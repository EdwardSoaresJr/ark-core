# Screen spec — Conversation List (Communications)

**ID:** `companion.screen.conversation-list`  
**Role(s):** Advisor  
**Quo ref:** `references/external/quo/quo-threads.png` · `screensdesign-5.webp`  
**Status:** 📝 draft — Edward review

---

## Job

**Who needs a reply?** Scan · tap · reply — with vehicle and RO visible on every row.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | Excellent list UX · generic contact | **Target: Yes** |
| **Why** | Contact name + last message | Same speed **plus** vehicle · RO badge · estimate/DVI chips · turn indicator |

---

## Layout

### Shell

- **Title:** Communications
- **Filter chips (horizontal):** Needs reply · All · Calls · (Unread)
- **Search icon** → global search with comms scope


Each row (~72–80pt height):

- **Avatar** — customer initials or photo
- **Line 1:** Customer name · time right
- **Line 2:** Message preview · unread dot
- **Line 3:** `2019 Civic · RO #1599` · status chip `Waiting approval`
- **Optional chips:** Estimate sent · DVI pending

### FAB


### Tab bar

- Home · **Communications** · Search · Schedule · More

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Tap row | Open conversation thread |
| Swipe left | Mark read / archive from needs-reply (turn logic) |
| Swipe right | Call customer |
| Long press | Quick actions sheet — open RO · assign · mute |
| Pull to refresh | Reload threads |
| Infinite scroll | Paginate |
| FAB tap | Compose — pick customer or search |

---

## States

| State | UX |
|-------|-----|
| Needs reply filter | Only customer-turn threads |
| Empty needs reply | "You're caught up." |
| Loading | Skeleton rows |

---

## Flows

**Tab Communications →** this screen  
**Tap row →** [`conversation-thread.md`](conversation-thread.md)

---

## Data & API

**Existing:** `/api/mobile/comms/hub` — verify row shape includes vehicle · RO · estimate posture  
**Extend if missing:** thread list DTO fields for automotive badges

---

## Edward sign-off

- [ ] Ready for Flutter
