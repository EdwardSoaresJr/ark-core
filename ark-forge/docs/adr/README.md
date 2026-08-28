# ARK Forge Architecture Decision Records

Short, frozen decisions that constrain Forge platform work.

**Separate from** `docs/engineering/adr/` — Forge is its own bounded context.

## Immutability

**Never edit an accepted ADR.** If a decision changes, write a new ADR:

```
ADR-0002
Supersedes ADR-0001 (partial)
```

History is preserved. Superseded ADRs remain with status updated — the body is not rewritten.

## Index

| ADR | Title | Status |
|-----|-------|--------|
| [ADR-0001](ADR-0001-capability-identity.md) | Capability Identity | Accepted |

## Companion

- [doctrine.md](../doctrine.md) — three Forge doctrines
- [glossary.md](../glossary.md) — pinned vocabulary
- [runtime/runtime-authority-v1.md](../runtime/runtime-authority-v1.md) — Core authority
- [sessions-bounded-context-v1.md](../sessions-bounded-context-v1.md) — fourth context (named, not built)
