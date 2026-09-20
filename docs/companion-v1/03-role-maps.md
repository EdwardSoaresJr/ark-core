# Deliverable 3 — Role Product Maps

**Rule:** Not permissions. **Different products** for different jobs on the floor.

Same backend. Different default home, tabs, and flows.

---

## Advisor (Edward) — primary Companion v1

**Job:** Run the front of the shop from pocket — calls, texts, customers, money, schedule.

**Default home:** Continuity feed (what changed)

**Primary tabs:**

| Tab | Product question |
|-----|------------------|
| Home | What changed since I last looked? |
| Communications | Who needs a reply? |
| Search | Find anyone — start work |
| Schedule | Who's arriving? |
| More | RO list · settings · profile |

**Never primary for advisor:** Technician production boards · owner P&L · platform settings

**P0 experiences:** All four in [`02-flows.md`](02-flows.md)

---

## Technician (Ben, Landon)

**Job:** Perform work on assigned vehicles — inspect, document, complete.

**Default home:** My Work (assigned ROs)

**Primary tabs:**

| Tab | Product question |
|-----|------------------|
| My Work | What am I assigned? |
| Active RO | What am I doing right now? |
| Camera | Capture evidence fast |
| More | Profile · bay · settings |

**Does not own:** Customer search globally · comms queue · schedule · payments · estimate send

**Comms consumption:** On assigned RO only — internal notes · advisor messages · photo notifications

---

## Owner (Edward-as-owner — optional surface)

**Job:** Daily pulse — did we win today?

**Default home:** Owner pulse (numbers + shop feed)

**Not the advisor home.** Separate entry or mode — do not merge dashboards.

---

## Manager / service manager

**Job:** Floor visibility — who's stuck · who's waiting · comms recovery

**Default home:** Shop continuity + team presence (P1)

**Overlap with advisor:** Communications recovery · RO lookup

**Does not replace:** Advisor pocket product for Edward during counter hours

---

## Map comparison

| Capability | Advisor | Tech | Owner |
|------------|---------|------|-------|
| Incoming customer call | ✅ P0 | ❌ | ⚠️ optional |
| SMS reply | ✅ P0 | RO-only | ❌ |
| Global search | ✅ P0 | ❌ | ⚠️ |
| Take payment | ✅ P0 | ❌ | ❌ |
| Inspection capture | ⚠️ review | ✅ P0 | ❌ |
| Schedule | ✅ P0 | ❌ | ⚠️ |
| Morning continuity | ✅ P0 | My work queue | Pulse |

---

## Sign-off

- [ ] Edward: advisor map matches ten-hour counter day
- [ ] Tech lead: technician map matches bay day (no advisor clutter)
- [ ] Owner map deferred or scoped — not blocking advisor P0
