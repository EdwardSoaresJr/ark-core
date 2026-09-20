# ARK Platform — Architecture Phase 1 Complete

**Status:** Closed 2026-07-19  
**Next:** [Engineering Phase 1 — Adapters](engineering-phase-1-adapters.md)  
**Culture:** [ARK Platform Manifesto v1](ark-platform-manifesto-v1.md)

## What Phase 1 froze

```text
Identity
  ↓
Shop
  ↓
Deployment
  ↓
Cluster
  ↓
Cluster Assignment
  ↓
Provisioning Request
──────────────────────────────
Adapters (replaceable)
```

| Document | Role |
| --- | --- |
| [domain-contract-v1.md](domain-contract-v1.md) | Host audiences |
| [shop-authority-v1.md](shop-authority-v1.md) | Shop as unit |
| [shop-status-authority-v1.md](shop-status-authority-v1.md) | Lifecycle |
| [deployment-authority-v1.md](deployment-authority-v1.md) | Edge, Docker as projection |
| [cluster-authority-v1.md](cluster-authority-v1.md) | Shared / Dedicated |
| [cluster-assignment-authority-v1.md](cluster-assignment-authority-v1.md) | Placement history |
| [provisioning-request-authority-v1.md](provisioning-request-authority-v1.md) | Workflow attempt |
| [adapter-rule-v1.md](adapter-rule-v1.md) | Orchestrator owns Completed; retry the request |
| [orchestrator-rule-v1.md](orchestrator-rule-v1.md) | Orchestrator coordinates only — no infra work |
| [deployment-flow-v1.md](deployment-flow-v1.md) | Provisioning v1 = truth → STOP |

**Language is finished.** This is ARK Platform’s operating system (not its infrastructure). Implementation should feel mechanical: domain tells adapters what to do; adapters never redefine the domain.

**Stop freezing doctrine** until operational pressure earns the next authority.  
Implementation practice: [engineering-principles.md](engineering-principles.md) (guardrails, not new architecture).  
Culture: [ark-platform-manifesto-v1.md](ark-platform-manifesto-v1.md).

---

## What Phase 1 actually delivered

Not Coolify. Not Stancl. Not a hosted shop.

**A design language** that the next fifty engineering decisions can use:

| Domain | Request / work | Coordinator | Hands |
| --- | --- | --- | --- |
| Operations | Repair Order | Workflow | Technicians |
| ARK Platform | ProvisioningRequest | Orchestrator | Adapters |
| Stinson | Trip | Dispatch | Driver |
| Interpretation | Evidence | Interpretation | Human confirmation |

Underneath: **Authority → Workflow → Projection → Infrastructure** — found by asking *who owns this truth?*, not by forcing a template.

**Measure going forward:** how often a frozen authority must change. Near zero over a year means the model absorbs growth.

**Daily antidote to erosion:** every sprint replaces a stub; none reshape the spine. “Just add it to Deployment / call Coolify from the orchestrator / one conditional” is how architecture dies.

Three layers appeared: shop product → software platform → philosophy of how software evolves. Phase 1 is complete because the language exists — adapters and customers prove it.

## Intentionally not designed

Kubernetes · multi-region · auto-scaling · cluster balancing · live migrations · HA databases · multi-edge routing.

Those earn themselves under operational pressure.

## Phase 2 engineering sprints

| Sprint | Ship |
| --- | --- |
| **1** | Provisioning Orchestrator (stub steps return success) |
| **2** | Coolify Adapter |
| **3** | Stancl Adapter |
| **4** | Bootstrap Adapter |
| Later | DNS · Certificate · Email |

Each sprint replaces a stub `return Success` — orchestration does not change.

## Prediction (not a build ticket)

A future `PlatformOperation` (Provision / Upgrade / Backup / Restore / Migrate / Destroy) may sit beside `ProvisioningRequest`. **Do not invent it now.** Wait for repeated pressure.
