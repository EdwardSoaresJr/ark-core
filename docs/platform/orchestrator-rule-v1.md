# Orchestrator Rule v1

**Status:** Locked — engineering discipline for ARK Platform  
**Date:** 2026-07-19  
**Companions:** [adapter-rule-v1.md](adapter-rule-v1.md) · [architecture-phase-1-complete.md](architecture-phase-1-complete.md)

## Principle

**The orchestrator never performs infrastructure work. It only coordinates.**

```text
ProvisioningRequest
  ↓
Orchestrator          ← sequencing + lifecycle only
  ↓
Coolify / Stancl / DNS / Bootstrap / Email   ← adapters do the work
```

No API calls. No Docker. No SQL (beyond updating `ProvisioningRequest` status / completed steps). No Shop/billing business rules.

Only sequencing, skip-completed, and status transitions.

If this discipline holds, you never grow a 2,000-line `ProvisionShopService` nobody wants to touch.

---

## Contract

Every adapter is a `ProvisioningStep`:

```php
interface ProvisioningStep
{
    public function key(): string;

    public function execute(ProvisioningRequest $request): ProvisioningStepResult;
}
```

Coolify, Stancl, DNS, Bootstrap, Email — identical surface. Swap implementations without touching the orchestrator.

**Smell:** orchestrator changes during an adapter sprint (e.g. Sprint 2 Coolify). Fix the adapter, not the spine.

---

## Observability (required from Sprint 1+)

Emit (at minimum):

| Event | When |
| --- | --- |
| `ProvisioningStarted` | Request → Running |
| `ProvisioningStepStarted` | Before step execute |
| `ProvisioningStepCompleted` | Step success |
| `ProvisioningStepFailed` | Step failure |
| `ProvisioningCompleted` | Request → Completed |
| `ProvisioningFailed` | Request → Failed |

Not for dashboards today — so Shop #27 at 2 AM shows which step failed without SSH.

---

## Same language elsewhere

| Domain | Request / work | Coordinator | Workers |
| --- | --- | --- | --- |
| Operations | Repair Order | Workflow | Technicians / evidence |
| Interpretation | Document | Interpretation flow | Human confirmation |
| Stinson | Trip | Dispatch | Driver |
| **ARK Platform** | **ProvisioningRequest** | **Orchestrator** | **Adapters** |

Script vs platform: a Coolify→Stancl→DNS chain is a script. Request → Orchestrator → Steps → Completed is a platform.

---

## Sprint constraint (Coolify)

Sprint 2 replaces **only** `StubCoolifyStep` with a Coolify adapter.  
If the orchestrator must change to make Coolify work, that is a regression of this rule.
