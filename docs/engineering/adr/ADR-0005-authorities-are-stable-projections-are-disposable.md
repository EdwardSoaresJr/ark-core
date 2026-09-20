# ADR-0005: Authorities Are Stable, Projections Are Disposable

**Status:** Accepted

## Context

Projections are convenient. Over time, teams promote read models, caches, and generated config into sources of truth — especially under delivery pressure. ARK's authority/projection boundary exists across Voice, Operations, Communications, and future products.

## Decision

**Authorities are stable. Projections are disposable. No projection may become an authority.**

## Consequences

- Deleting or regenerating a projection must never destroy operational truth.
- If business logic requires persistence, it belongs in an authority — not a projection table.
- "Cache" and "read model" are projection vocabulary; neither grants authority status.
- Promotion of a projection to authority requires a new ADR and architecture review — not an incremental refactor.
