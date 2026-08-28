# ARK Platform — Engineering Principles

**Status:** Active practice — not doctrine, not authorities  
**Date:** 2026-07-19  
**Context:** [engineering-phase-1-adapters.md](engineering-phase-1-adapters.md) · [architecture-phase-1-complete.md](architecture-phase-1-complete.md) · [sprint-2-coolify-adapter.md](sprint-2-coolify-adapter.md)

This is not architecture. It is not a new authority.

These are **implementation guardrails** for contributors building adapters and workflows on a finished language.

---

## Doctrine freeze (until reality pushes back)

Phase 1 frozen enough:

Domain Contract · Shop · Status · Deployment · Cluster · Cluster Assignment · Provisioning Request · Adapter Rule · Orchestrator Rule

**No new authority without repeated operational pressure.**

If Sprints 2–4 fit the current model, the model was correct.  
If an adapter stretches the model, *then* discover the next authority — not before.

Do not invent `PlatformOperation`, multi-region routing, or cluster balancers from imagination.

---

## Practical rules

1. **Prefer adapters over conditionals.**  
   New infrastructure = new `ProvisioningStep` (or equivalent), not `if ($provider === …)` in the orchestrator.

2. **Prefer events over direct coupling.**  
   Listeners observe `ProvisioningStepFailed` / Completed. Steps do not call each other.

3. **Retry requests, not steps.**  
   Re-enter via `ProvisioningRequest` + orchestrator skip-completed. Never “just rerun DNS” as a naked script.

4. **Infrastructure is replaceable.**  
   Everything below `ProvisioningRequest` can be swapped (Coolify → something else) without reshaping Shop / Deployment / Assignment.

5. **Orchestrators coordinate only.**  
   No API calls, Docker, or business rules in the orchestrator. Sequencing + lifecycle + events.

6. **Authorities own truth.**  
   Shops, placements, and requests are truth. Forms, wizards, and jobs do not invent parallel state.

7. **Observations are computed.**  
   Utilization, shop counts, health aggregates — derive them. Do not store sync-debt counters as authority.

8. **Projections never become authorities.**  
   Cluster assignment history is authority. Edge routing tables and Coolify UUIDs are projections/refs.

9. **Smell: orchestrator changes during an adapter sprint.**  
   Sprint 2 = Coolify step only. If the spine must move, stop and ask whether pressure earned a new concept — or the adapter is wrong.

10. **Same language across products.**  
    ARK Operations, Companion, Stinson, ARK Cloud: authorities own truth · workflows are requests · orchestrators coordinate · adapters touch infrastructure.

11. **Defend the boundary.**  
    If an implementation detail reaches upward, stop. If business truth reaches downward, stop. The boundary exists to be defended.

12. **Pressure before invention.**  
    Prefer “keep the stub” unless repeated operational pressure forces a change. Review is a pressure review, not an elegance review.

---

## What you are building

Not a provisioning script.

A **workflow engine** whose first specialty is shop provisioning.

Today’s steps: Coolify · Stancl · DNS · Bootstrap · Email  

Tomorrow’s possible steps: Backup · Restore · Upgrade · Migrate · Destroy  

The orchestrator, request, and event stream should not care. Only the steps change.

A broader “platform operation” abstraction may appear after months of real ops. **It is not a design ticket today.**

---

## Anti-pattern this protects against

```text
ProvisionShopService → 4,000 lines → untestable → unretryable → unobservable → unreplaceable
```

Instead:

```text
Request → Orchestrator → Independent Steps → Events
```

That shape stays understandable years later.

---

## Contributor checklist (before a PR)

- [ ] Did I add a new authority? If yes — what repeated pressure earned it?  
- [ ] Did I put infra or conditionals in the orchestrator?  
- [ ] Can this step be retried by re-running the request?  
- [ ] Do failures emit events with a step key?  
- [ ] Would swapping Coolify/Stancl/DNS require rewriting Shop or Deployment?
