# ARK Companion: Communications

**Product:** ARK Companion: Communications - advisor communications command center. Not ARK Mobile, not ARKv2 on a smaller screen.

**Phase:** Companion v1 is frozen. Fix production breaks only. Floor notes: [`companion-pocket-notebook.md`](companion-pocket-notebook.md).

## Build milestones (v1 complete)

Foundation shipped M1–M7. See [`MISSION.md`](MISSION.md) and [`09-production-feel.md`](09-production-feel.md).

| # | Milestone | Status |
| --- | --- | --- |
| 1–6 | Inbox → Operational Context | ✅ |
| 7 | Production feel | ✅ built |
| - | **Floor observation** | 🔄 [`companion-pocket-notebook.md`](companion-pocket-notebook.md) |

---

## Reference library (not build order)

These **inform** design; they do not dictate sequence:

| Resource | Role |
| --- | --- |
| [`screens/`](screens/) | Screen specs - reference when implementing a milestone |
| [`references/external/quo.md`](references/external/quo.md) | Primary UX benchmark |
| [`references/external/`](references/external/) | Pattern library (Quo + call-flow refs) |
| [`design-system/interaction-patterns.md`](design-system/interaction-patterns.md) | Repeating interaction grammar |
| [`07-api-projection-backlog.md`](07-api-projection-backlog.md) | API gaps when a milestone needs data |
| [`frozen-flutter-ui.md`](frozen-flutter-ui.md) | Legacy Flutter frozen - build in `lib/companion/` |

**Legacy Flutter build order** ([`08-flutter-build-order.md`](08-flutter-build-order.md)) - superseded by milestone model; kept for historical slice tracking.

---

## Product family (future)

Same backend, different missions:

- **Companion: Communications** (advisor) - this product  
- **Companion: Technician** (future)  
- **Companion: Customer** (future)

---

## Ruthless rule

> **Would I rather use this than Quo for shop communications?**

If **not yet** - improve interaction quality or operational context before shipping the milestone.

---

## Success

Advisor opens Companion → knows who needs attention → acts (reply, call, listen, quick send) **with full customer/RO context inline** - without reproducing ARKv2 on a phone.
