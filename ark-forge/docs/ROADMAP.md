# ARK Forge Roadmap

**Architectural Baseline v1:** ✅ `fd34f040` — Core boundary proven (Workbench → invoke → generic capability)

**Phase 0 (language):** ✅ ADR-0001, doctrine, sessions named, glossary — architecture frozen for implementation.

Feature-complete: no. Architecturally complete: yes.

## Sequence (current)

| Step | Item | Status |
|------|------|--------|
| 1 | Push architectural baseline | ✅ |
| 2 | [ADR-0001 Capability Identity](adr/ADR-0001-capability-identity.md) | ✅ |
| 3 | [Forge doctrine](doctrine.md) | ✅ |
| 4 | [Sessions bounded context](sessions-bounded-context-v1.md) — document only | ✅ |
| 5 | [Glossary](glossary.md) — pinned vocabulary | ✅ |
| 6 | Migrate registry to Capability Identity metadata | Next |
| 7 | Open GitHub → `core.browser.url.open` | Pending |
| 8 | Git Status → `core.git.status.read` (first observable) | Pending |
| 9 | Open Terminal → `core.shell.session.open` | Pending (after Sessions observation) |

## Explicit deferrals

- AI / agent orchestration
- MCP transport
- Session authority implementation
- Command palette UI (follows registry metadata migration)

## Version sketch

| Version | Scope |
|---------|--------|
| **v0.1** | Core + Projection + Workbench proof (Open Cursor) |
| **v0.2** | Capability Identity registry + Open GitHub + Git Status |
| **v0.3** | Command palette + session capabilities |
| **v0.4** | Cursor / MCP clients on same API |

Complexity earns existence.
