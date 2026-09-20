# ARK Platform Manifesto v1

**Status:** Culture — not doctrine, not engineering principles  
**Date:** 2026-07-19  
**Alongside:** [architecture-phase-1-complete.md](architecture-phase-1-complete.md) · [engineering-phase-1-adapters.md](engineering-phase-1-adapters.md) · [engineering-principles.md](engineering-principles.md)

This is how the platform team works. It is not an authority catalog and not a sprint checklist.

---

We discover architecture.

We do not invent it.

Authorities emerge from repeated pressure.

They are never speculative.

---

We freeze language before code.

Once an authority is frozen, engineering implements it.

Engineering does not redefine it.

---

We replace infrastructure, not business truth.

Docker is replaceable.

Coolify is replaceable.

Stancl is replaceable.

The Shop is not.

---

Orchestrators coordinate.

Adapters integrate.

Authorities own truth.

Events communicate.

Everything has one job.

---

Every sprint replaces a stub.

No sprint reshapes the spine.

If a sprint requires changing the spine, we stop and ask why.

---

We optimize for understanding.

A simple architecture that survives ten years is better than a clever one that survives one release.

---

We earn abstractions.

Nothing becomes an authority until reality demands it.

Nothing becomes a doctrine until it survives pressure.

Nothing becomes infrastructure until it can be replaced.

---

## Center of the conversation

Not Laravel versions, Asterisk, Docker, queues, Twilio, microservices, or frameworks.

| Ask |
| --- |
| Who owns the truth? |
| What is the workflow? |
| What is replaceable? |
| Where does responsibility begin and end? |

---

## Language we aim for

Not: “I’m adding Coolify.”

Instead:

- “I’m replacing `StubCoolifyStep`.”  
- “I’m implementing the DNS adapter.”  
- “I’m wiring the Bootstrap adapter.”  

Architecture stays the foundation. Implementation is a series of small, confidence-building steps.

When infrastructure work feels routine instead of risky — Engineering Phase 1 did its job.

---

## Protect this

Phase 1’s real deliverable is not the provisioning spine.

It is that the next fifty decisions already have a language.

Measure success by how rarely frozen authorities must change — not by how fast Coolify ships.

---

## After Phase 1 — skepticism is the job

Architecture co-design is done. Review gets harder, not softer — because the model is now valuable enough to protect.

First question: **What pressure is forcing this?**  
No pressure → **keep the stub.**

Default answers:

| Someone says | First response |
| --- | --- |
| We need another authority | Why isn’t this an adapter? |
| We need another table | Why isn’t this an observation? |
| We need another workflow | Why isn’t this another ProvisioningStep? |
| Shop field / orchestrator knows Coolify | Why? (authority gap vs adapter leak) |

The answer to “How does ARK provision a shop?” should stay:

**A ProvisioningRequest is created.**

Everything after that is implementation.

Healthy PRs are boring: stub → adapter, tests green, architecture unchanged.
