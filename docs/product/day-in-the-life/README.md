# Day in the Life

Operational acceptance scenarios for ARK — not personas, not user stories.

> **Evolution note:** These scenarios are **role-based today** (advisor, technician, owner, parts) because they match how Demo Auto Repair validates behavior on the floor right now. They will **gradually migrate to station-based** files as Station becomes the primary operational anchor — e.g. `front-counter.md`, `bay.md`, `parts-desk.md`, `office.md`, `portable-station.md`. The station question is permanent; the operator changes. Role docs stay useful until each station regression suite is written; do not rewrite everything at once.

ARK optimizes **where work happens**, not org charts. Another shop may call the role "Service Writer" or "Foreman" — they still have Front Counter, Bays, Parts Desk, and Office.

| Station | Typical operator | Primary question |
|---------|------------------|------------------|
| Front Counter | Advisor | What customer needs me next? |
| Bay | Technician | What do I inspect or repair next? |
| Parts Desk | Parts | What is blocking production? |
| Office | Owner | What needs attention across the shop? |
| Portable Station | Any staff | What needs me right now? |

**Regression question:** Can this **station** still do its job? — not "did we ship the advisor screen?"

## Format

Each file describes one role's **workday** as a sequence of timed moments. Every moment is a complete operational scenario:

```text
At [time]
[What happens on the floor]
[What ARK must brief before action]
[What the human does]
[What authority changes]
[Surfaces involved: Front Counter · Portable Station · RO · …]
```

## Question that gates every feature

> **Show me where it changes this day.**

If nobody can point to a time on the clock, the feature probably is not important yet.

## vs user stories

| Typical software | ARK |
|------------------|-----|
| As a user, I want… | At 8:10 AM, customer texts… |
| Feature checklist | Complete operational loop |
| Screen-centric | Work-centric |

Scenarios are **product regression tests** per station (eventually) across Track A (Operations Platform), Track B (Portable Station), and Orientation Platform — same truth, different surfaces.

Example station pass criteria:

```text
Front Counter — morning: open shop · customer calls · customer texts · estimate approval · checkout → all green?
Bay — morning: open assigned RO · inspection · photos · request parts · finish work → all green?
Portable Station — morning: push · orientation · reply · internal message · walk to Front Counter · switch station → all green?
```

## Files

| File | Role | Primary question |
|------|------|------------------|
| [advisor.md](./advisor.md) | Service advisor (Edward) | Who needs a response or decision? |
| [technician.md](./technician.md) | Technician (Landon) | What do I inspect or repair next? |
| [owner.md](./owner.md) | Owner / manager | What needs attention across the shop? |
| [parts.md](./parts.md) | Parts desk | What is blocking production? |

## Scenario status legend

| Mark | Meaning |
|------|---------|
| ✅ | Shipped and floor-trusted |
| ⚠️ | Partial — authority exists, orientation or portable path incomplete |
| 🔲 | Not yet — scenario defines target |

Progress is measured by **[operational certifications](../operational-certifications.md) completed** at the claimed PASS level (Engineering → Operational → Production) — not sprints.

Each certification splits **Capability** (system can do it) vs **Operational** (shop can work with it). Record passes with owner, date, and evidence in [certifications/](../certifications/).
