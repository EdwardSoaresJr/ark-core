# Certification records

Historical sign-off when a level reaches **Engineering**, **Operational**, or **Production**.

Copy [_template.md](./_template.md) to `{slug}.md`. Update index below.

## Video evidence

**Film operations, not certifications.**

- Certification: *"Did we pass?"*  
- Operation: *"How does Edward actually work?"*

Catalog: [operations/README.md](../operations/README.md) — reference implementations for Demo Auto Repair; other shops record their own.

**PR gate:** Re-record the operation. Smoother → better product.

**Engineer sentence:** If you can't point to a real operation this improves, you probably aren't improving ARK.

North star: [operation-follows-operator-v1.md](../operation-follows-operator-v1.md)

## Index

| Certification | Owner | Engineering | Operational | Production |
|---------------|-------|-------------|-------------|------------|
| [Front Counter](./front-counter.md) | Alex Rivera | ✓ 2026-06-27 | ✓ 2026-06-27 | ⬜ |
| [Voice Transport](./voice-transport.md) | Alex Rivera | ✓ 2026-06-27 | ✓ 2026-06-27 | ⬜ |
| [Portable Station Phase 1](./portable-station-phase-1.md) | Alex Rivera | ✓ 2026-06-27 | superseded → workflow certs | ⬜ |
| [Customer Arrival](./customer-arrival-workflow.md) · WF 1 | Alex Rivera | ⬜ | ⬜ **active** | ⬜ |
| [Technician Start](./technician-start-workflow.md) · WF 2 | Alex Rivera | ⬜ | ⬜ | ⬜ |
| [Advisor Communication](./advisor-communication-workflow.md) · WF 3 | Alex Rivera | ⬜ | ⬜ | ⬜ |
| [Vehicle Pickup](./vehicle-pickup-workflow.md) · WF 4 | Alex Rivera | ⬜ | ⬜ | ⬜ |
| [Shop Walk](./shop-walk-workflow.md) · WF 5 | Alex Rivera | ⬜ | ⬜ | ⬜ |
| [Phone-First Shop](./phone-first-shop.md) · meta | Alex Rivera | ⬜ | ⬜ | ⬜ |

## Fields

| Field | Purpose |
|-------|---------|
| **Owner** | Who verified on the floor |
| **Evidence** | What happened (checklist narrative) |
| **Proof** | Artifacts to verify later — video, screenshot, log, RO # |
| **Why this matters** | One sentence: after this cert, the shop can… |

## Dependency rule

If Engineering goes ❌, Operational and Production are **suspended** until Engineering is green again — not independent badges.

Do not delete records. Append corrections.
