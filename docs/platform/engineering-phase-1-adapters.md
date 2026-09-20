# Engineering Phase 1 — Adapters

**Status:** Active  
**Opened:** 2026-07-19  
**Prior phase:** [Architecture Phase 1 complete](architecture-phase-1-complete.md)  
**Daily pointer:** [NEXT.md](NEXT.md)  
**Culture:** [ARK Platform Manifesto v1](ark-platform-manifesto-v1.md)  
**Practice:** [engineering-principles.md](engineering-principles.md) · [sprint-2-coolify-adapter.md](sprint-2-coolify-adapter.md)

## The phase change

| Architecture Phase 1 | **Engineering Phase 1** |
| --- | --- |
| Discover authorities | **Earn confidence** |
| Did we find the right model? | **Can we replace infrastructure without changing the model?** |
| Co-design abstractions | **Skepticism — burden of proof flipped** |

Architecture is frozen enough. Work is constrained **by** architecture — not driven by the next Coolify quirk.

### Pressure review (not architecture review)

First question on every proposal: **What pressure is forcing this?**

No pressure → almost always: **keep the stub.**

Walk this sequence:

1. Can this stay a **stub**?  
2. Can this live entirely inside an **adapter**?  
3. Can this be an **observation** instead of stored truth?  
4. Can this be another **step** in an existing workflow?  
5. Can this be **configuration** instead of architecture?  
6. Only then: has reality earned a **new authority**?

Getting to step 6 only a handful of times per year is success.

| Proposal sounds like… | First ask |
| --- | --- |
| New authority | Why isn’t this an **adapter**? |
| New table | Why isn’t this an **observation**? |
| New workflow | Why isn’t this another **ProvisioningStep**? |
| Field on Shop / orchestrator knows Coolify | **Why?** (missing authority vs adapter leak) |

```text
Infrastructure?     → Adapter
Workflow?           → Orchestrator
Operational attempt? → Request
Placement?          → Assignment
Business config?    → Deployment
Business identity?  → Shop
…or a brand-new authority?
```

## Roadmap

```text
Architecture Phase 1          ✅ Complete

Engineering Phase 1
    Sprint 1  Orchestrator           ✅
    Sprint 2  Coolify Adapter        ✅
    Sprint 3  Stancl Adapter         ▶ next (after live Coolify milestone walk)
    Sprint 4  DNS Adapter            ▶
    Sprint 5  Bootstrap Adapter      ▶
    Sprint 6  Email Adapter          ▶

Production Adoption           ▶ After adapters prove themselves
```

**Not on the critical path:** Kubernetes · multi-region · auto-scaling · multi-cloud · Coolify resource creation · migration tooling.

### Sprint 2 checkpoint (frozen)

| Layer | Status |
| --- | --- |
| Architecture | Unchanged ✅ |
| Engineering | Proven ✅ |
| Reality | Pending live Coolify |

**Success criterion achieved (engineering):**

> Replacing StubCoolifyStep required zero architectural decisions. ✅

**Reality exit:** Live checklist in [sprint-2-coolify-adapter.md](sprint-2-coolify-adapter.md) — milestones 1→2→3 boring before 4/5; then Sprint 3.

### Sprint 3 should feel the same

```text
StubStanclStep
        ↓
StanclAdapter
        ↓
StanclClient
        ↓
LocalStanclClient (or Http if needed)
```

Same process. Different implementation.  
If Sprint 3 feels fundamentally different, ask whether Stancl crossed a boundary — or the adapter leaked.

**Healthier question now:** How do we prove one more adapter without disturbing everything else?

---

## Sticky note (one sentence)

**Every sprint should replace a stub, never reshape the spine.**

Sprint 3 → `StubStanclStep`  
Sprint 4 → DNS stub  
Sprint 5 → Bootstrap stub  
…

If a sprint needs to change Shop · Deployment · ClusterAssignment · ProvisioningRequest · Orchestrator — **stop and investigate** before continuing.

---

## Definition of Done (every adapter)

An adapter is done when:

- [ ] The orchestrator did **not** change  
- [ ] The `ProvisioningStep` contract did **not** change  
- [ ] The `ProvisioningRequest` schema did **not** change  
- [ ] Existing platform tests still pass  
- [ ] **Only** the adapter implementation became real  

Objective. No vibes.

---

## Replaceable infrastructure (the Forge test)

“We’re replacing Coolify.”

```text
Delete:  HttpCoolifyClient
Create:  ForgeClient
Done.
```

No meetings. No architecture review. No doctrine rewrite. No Shop or ProvisioningRequest changes.

That is what replaceable means.

---

## Adapter shape (symmetry is the pattern)

```text
CoolifyAdapter  → CoolifyClient  → HttpCoolifyClient
StanclAdapter   → StanclClient   → …
DnsAdapter      → DnsClient      → …
BootstrapAdapter → BootstrapService → …
```

Same contract every time:

```php
interface ProvisioningStep
{
    public function key(): string;

    public function execute(ProvisioningRequest $request): ProvisioningStepResult;
}
```

---

## What we are not doing

- Renaming this into a “general workflow engine” because Backup/Migrate *might* use it  
- New authorities without repeated operational pressure  
- Architecture Phase 2 by committee  

Let ops earn the next abstraction.

---

## Mindset

Six months ago, architecture followed implementation.  
Today, **implementation is constrained by architecture**.

The architecture tells engineering what may change — and what must not.

That stability is the prerequisite for inviting real customers onto the platform: small, low-risk adapter replacements instead of redesigning the core.
