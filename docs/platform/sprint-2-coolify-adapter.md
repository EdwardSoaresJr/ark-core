# Sprint 2 — Coolify Adapter (prove the contract)

**Status:** ✅ Frozen (code) — Reality pending live control plane  
**Phase:** [Engineering Phase 1](engineering-phase-1-adapters.md)  
**Goal:** Prove the adapter contract — **not** “Coolify integration.”  
**Companions:** [orchestrator-rule-v1.md](orchestrator-rule-v1.md) · [engineering-principles.md](engineering-principles.md)

**Sticky note:** Every sprint replaces a stub, never reshapes the spine.

**Celebrate when:** *Replacing StubCoolifyStep required zero architectural decisions.*  
Not when “Coolify works.”

---

## Sprint 2 exit criteria

| Layer | Status |
| --- | --- |
| **Architecture** | Unchanged ✅ |
| **Engineering** | Proven ✅ (tests, contract, zero spine changes) |
| **Reality** | **Pending** — live Coolify must prove the contract |

The sprint is not complete because the code compiles.  
It is complete when the real control plane proves the contract.

### Live validation checklist (raise one milestone at a time)

**Milestone 1 — stop after**

- [ ] `php artisan ark:coolify:check`
- [ ] Authenticate succeeds
- [ ] Token validated
- [ ] Correct team returned

**Milestone 2 — stop after**

- [ ] Discover servers
- [ ] Assigned Cluster `deployment_target` resolves correctly
- [ ] No assumptions about server IDs

**Milestone 3 — stop after**

- [ ] Discover projects
- [ ] Discover applications
- [ ] Configured application UUID resolves
- [ ] Mapper produces expected deployment command
- [ ] Compare expected application / server / project UUIDs against Coolify UI  
  If they don’t line up → **adapter changes, not the platform**

**Only then — Milestone 4**

- [ ] Trigger deploy on the **existing preconfigured** application
- [ ] Watch Coolify dashboard while adapter observes  
  If dashboard and adapter disagree → **trust the dashboard first**

**Then — Milestone 5**

- [ ] Successful deployment
- [ ] Failed deployment
- [ ] Timeout  
  Only when all three are predictable is the adapter truly “real.”

Do not start Sprint 3 until milestones 1–3 are boring on a live instance.

---

### Implementation (2026-07-19)

| Item | Value |
| --- | --- |
| Shape | `CoolifyAdapter` → `CoolifyClient` → `HttpCoolifyClient` \| `FakeCoolifyClient` |
| Execution artifacts | `CoolifyExecutionStore` (idempotency refs — not workflow state) |
| Config | `config/ark-platform.php` → `coolify.*` |
| Endpoints | `GET /api/v1/teams`, `/servers`, `/projects`, `/applications`, `/deploy`, `/deployments/{uuid}` |
| Default milestone | `1` (authenticate only) |
| Env | `COOLIFY_ENABLED`, `COOLIFY_BASE_URL`, `COOLIFY_API_TOKEN`, `COOLIFY_ADAPTER_MILESTONE`, `COOLIFY_DEPLOY_APPLICATION_UUID`, poll timeouts |
| Diagnostic | `php artisan ark:coolify:check` (no deploy) |
| Spine | Orchestrator / Step / Request / Shop / Assignment **unchanged** |

## Success criteria

When Sprint 2 finishes, **only this box** may have changed:

```text
ProvisioningRequest
        │
        ▼
ProvisioningOrchestrator     ← unchanged
        │
        ├── CoolifyAdapter   ← REAL (this sprint)
        ├── StanclStep       ← Stub
        ├── DnsStep          ← Stub
        ├── BootstrapStep    ← Stub
        └── EmailStep        ← Stub
```

If Shop, Deployment, ClusterAssignment, ProvisioningRequest, or Orchestrator must change — **stop and ask why.**

---

## Adapter mission (one responsibility)

**Transform a `ProvisioningRequest` into a deployed application.**

Never:

- create a Shop  
- choose a Cluster  
- create Stancl tenants  
- send email  
- update billing  
- write cluster assignment history  

Those belong elsewhere.

---

## Intentionally under-build

Do **not** start with a full production stack. Prove capabilities in order:

| Milestone | Capability | Gate |
| --- | --- | --- |
| **1** | Authenticate with Coolify | PASS before #2 |
| **2** | Discover servers | PASS before #3 |
| **3** | Discover projects / apps | PASS before #4 |
| **4** | Trigger one deployment | PASS before #5 |
| **5** | Observe deployment completion | Then auto-create apps |

Config: `COOLIFY_ADAPTER_MILESTONE=1` … `5`. Default **1**.

---

## Transport abstraction

```text
CoolifyAdapter (ProvisioningStep)
        ↓
CoolifyClient (interface)
        ↓
HTTP  (or Fake for tests / swap)
```

Feel like Stripe/Twilio:

```php
$result = $coolify->deploy(...);
```

Not a pile of `Http::post` inside the step.

### Swap test

Replace `CoolifyClient` with `ForgeClient` or `KubernetesClient` **without touching**:

Orchestrator · ProvisioningRequest · Shop · Deployment · ClusterAssignment

If yes — the contract held.

---

## Resist generalization

This remains a **Provisioning Orchestrator**.

Do not rename it into a “platform workflow engine” because Backup/Upgrade/Migrate *might* use it. Let six months of ops earn that title through repetition.
