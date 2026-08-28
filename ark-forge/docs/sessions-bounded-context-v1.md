# Sessions — Bounded Context v1

**Status:** Named, not implemented  
**Date:** 2026-06-26  
**Companion:** [ADR-0001 Capability Identity](adr/ADR-0001-capability-identity.md)

---

## One sentence

**Sessions group workstation resources that belong together for a period of work.**

Not engineering truth. Not Windows. Not Forge Core capabilities themselves.

---

## Why name this now

Without a named boundary, session-like behavior leaks into Core as one-off verbs:

```
open_terminal          ✗  — ad hoc, no room to grow
shell.session.open     ✓  — capability that will delegate to Session authority
```

Naming early prevents accidental coupling. Implementation comes after Capability Identity and observable Git proof.

---

## What Sessions would own

Questions Sessions answer — **nobody else should**:

| Question | Example |
|----------|---------|
| Which repositories are open? | `arksmsv2`, `arkweb` |
| Which editor windows belong to this workspace? | Cursor instances tied to repo path |
| Which terminals belong to this project? | PowerShell in `ark-forge/core` |
| Which browser tabs belong to this project? | GitHub PR, docs tab |
| What is the active session? | Operator focus for this work block |

Sessions are **operational grouping on this machine**, not cloud state, not engineering milestones.

---

## What Sessions must never own

| Not Sessions | Belongs to |
|--------------|------------|
| Milestone, PR, ADR content | Git + `docs/engineering/` |
| Git branch meaning | Engineering Projection |
| Capability execution | Forge Core |
| User intent / command choice | Workbench |
| Customer, provisioning, voice | ARK product domains |

---

## Relationship to other contexts

```
Workbench
    │  "Open Terminal for this repo"
    ▼
Capability: core.shell.session.open
    │
    ▼
Forge Core (invoke)
    │
    ▼
Session authority (future)
    │  create or focus terminal session scoped to repo
    ▼
Windows / macOS / Linux
```

Core **invokes** session operations. Session authority **tracks** which resources belong together.

Engineering Projection may **suggest** context (active repo path, PR URL) — Workbench decides whether to open a session.

---

## Planned capability identities (not implemented)

| Identity | Operation | Stability |
|----------|-----------|-----------|
| `core.shell.session.open` | Open or focus a terminal session | experimental |
| `core.shell.session.close` | Close a scoped terminal session | experimental |
| `core.shell.session.list` | List active terminal sessions | experimental |
| `core.shell.session.focus` | Focus an existing session | experimental |

Future (earned):

| Identity | Notes |
|----------|-------|
| `core.browser.session.open` | Tab group scoped to project |
| `core.editor.session.open` | Editor window scoped to repo |

---

## Sequence guard

Do **not** implement Sessions until:

1. ✅ Architectural Baseline v1 (Core boundary proven)
2. ☐ Capability Identity ADR accepted and registry migrated
3. ☐ Open GitHub via `core.browser.url.open`
4. ☐ Git Status via `core.git.status.read` (first observable capability)
5. ☐ Floor observation: operators repeat "which terminal was for which repo?" friction

Sessions are a **measurement-gated** bounded context — same discipline as ARK Pressure First.

---

## Anti-patterns

- Storing milestone or PR IDs in session records
- Session state in Core's capability registry as authority
- Workbench hardcoding terminal spawn without session scope
- `open_terminal` as a permanent capability ID

---

## References

- [doctrine.md](doctrine.md)
- [runtime/runtime-authority-v1.md](runtime/runtime-authority-v1.md)
