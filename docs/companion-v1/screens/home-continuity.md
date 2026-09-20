# Screen spec — Home (Continuity)

**ID:** `companion.screen.home`  
**Role(s):** Advisor (default launch)  
**Status:** 📝 draft — Edward review

---

## Job

**Unlock → know what changed → one tap → done → pocket.** Not KPIs. Not dashboards.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | Generic dashboard widgets | **Target: Yes** |
| **Why** | CRM metrics | **Operational moments** — replies · inspections · approvals · calls · arrivals |

---

## Layout

### Shell

- **Title:** Shop name or "Good morning, Edward" — subtle, not hero
- **Status line:** `🟢 Phone online` · tap → phone settings
- **No tab clutter on first paint** — tab bar visible bottom

### Body — continuity list (primary 100% of above-fold)

Ordered **oldest actionable first** or **newest first** (Edward picks in review — default newest):

Each **continuity row:**

- Headline — `Emma replied`
- Subline — vehicle · RO · snippet
- Time — `8 min` · posture chip if waiting
- Chevron — tappable entire row

**Example rows (Edward's Monday):**

- Josh called while closed
- 3 customers replied
- Ben uploaded inspection
- 1 estimate approved
- John arriving at 9:00

### Below fold (optional P1)

- Quick search field — "Find customer…"
- Not a widget grid

### Empty

- "You're caught up." — calm · pocket

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Tap row | Deep link → thread · inspection · RO · call log — **never Home again** |
| Swipe left | Dismiss / mark seen (continuity cursor — not delete truth) |
| Pull to refresh | Reload continuity projection |
| Long press | Pin to top (optional) |

---

## States

| State | UX |
|-------|-----|
| Since last unlock | Filter `occurred_at > last_seen` |
| First open ever | Last 24h or "since yesterday 6pm" |
| Phone offline | Banner on status line · rest works |

---

## Flows

**Entry:** Launch · unlock · default tab Home

**Tap "Emma replied" →** Conversation thread

**Tap "Ben uploaded inspection" →** Inspection item

**Done →** pocket

---

## Data & API

**Needs:** continuity projection — same family as `/api/mobile/continuity` · extend for shop moments (inspection uploaded · estimate approved · arrival)

**May need:** expand continuity payload with deep link routes per moment type

---

## Edward sign-off

- [ ] Ready for Flutter
