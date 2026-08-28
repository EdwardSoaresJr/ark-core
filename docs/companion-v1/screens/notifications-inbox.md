# Screen spec — Notifications Inbox

**ID:** `companion.screen.notifications-inbox`  
**Role(s):** All  
**Status:** 📝 draft — Edward review

---

## Job

**Every interrupt in one list** — tap goes to **workspace**, not Home · recover what push missed.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | Generic CRM notifications | **Target: Yes** |
| **Why** | Activity log feel | **Deep links** · automotive context on row · same moments as continuity |

---

## Layout

### Entry

- Bell on Home header · badge count
- Not a primary tab — secondary to continuity list

### List

**Row:**

- Icon by type — message · call · inspection · approval · payment
- Headline — `Emma replied`
- Subline — vehicle · RO · snippet
- Time · unread dot

### Grouping

- Today · Earlier (optional)

### Empty

- "No notifications"

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Tap | Deep link — thread · inspection item · RO · call |
| Swipe | Mark read |
| Clear all | Mark all read — does not delete authority |

**Anti-pattern:** tap → Home → hunt (reject)

---

## Flows

Bell → list → tap inspection push equivalent → [`inspection-item.md`](inspection-item.md)

---

## Data & API

**Needs:** notification feed with `deep_link` + entity ids · read state

**May align with:** continuity projection — same moments · different sort (all vs since unlock)

---

## Edward sign-off

- [ ] Every row goes somewhere useful
- [ ] Ready for Flutter
