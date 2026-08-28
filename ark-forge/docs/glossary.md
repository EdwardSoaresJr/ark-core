# ARK Forge Glossary

Pinned vocabulary for Forge bounded context. Use these terms consistently — in code comments, ADRs, Workbench copy, and agent prompts.

**Phase 0 complete:** language frozen. Implementation earns the next abstractions.

---

## Capability

A **generic workstation operation** registered in Forge Core with a stable identity (`core.{domain}.{resource}.{operation}`).

A capability is implementation-independent: it does not mention Flutter, Rust, Windows, or ARK product domains.

Capabilities are either **invoke** (side effect) or **observe** (returns data). See [ADR-0001](adr/ADR-0001-capability-identity.md).

**Not:** a button, a workflow step, or an engineering milestone action.

---

## Capability Identity

The permanent ID and metadata record for a capability: domain, operation, arguments, version, permissions, stability.

Capability IDs are **immutable** once published — like public APIs. See [ADR-0001](adr/ADR-0001-capability-identity.md).

---

## Command

A **Workbench-facing action** the operator can choose — e.g. "Open GitHub", "Run Git Status", "Open Cursor".

Commands map **intent** to **capability identity** + arguments. Commands live in the Workbench (eventually a command palette), not in Core.

**Not:** a shell command string (unless explicitly invoking `core.shell.command.run` or similar).

---

## Intent

What the operator wants to accomplish in human terms: open the repo in Cursor, check git state, open the GitHub remote.

Intent is interpreted and mapped by the **Workbench**. Core never receives intent labels — only capability IDs and typed arguments.

---

## Invoke

A Core operation mode that **causes a side effect** on the workstation.

Examples: open URL in browser, launch application, run shell command.

```
POST /api/v1/core/capabilities/core.browser.url.open/invoke
```

Invoke capabilities return `result: null` unless a minimal acknowledgment is needed.

---

## Observe

A Core operation mode that **reads workstation state** without mutating it.

Examples: git status, process list, installed SDK detection, workspace health.

Observe capabilities return structured **result** data. Workbench and Projection render it; Core does not assign engineering meaning.

Same identity system as invoke; different contract (data out, no side effect).

---

## Forge Core

Local **capability authority** for this machine. Answers: what can this workstation do right now?

Owns capability registry, invoke/observe execution, runtime rejection of product-domain verbs.

Must never know provisioning, milestones, customers, or engineering workflow. See [runtime-authority-v1.md](runtime/runtime-authority-v1.md) and [doctrine.md](doctrine.md).

---

## Engineering Projection

**Interpreted engineering state** built from Git + `docs/engineering/`. Answers: what does the repo say about milestone, PR, ADR, reviews?

Served at `/api/v1/engineering/*`. Disposable — rebuild from repo truth. Not capability authority.

---

## Workbench

Flutter (and future) **user interaction layer**. Owns intent mapping, commands, and rendering of projection + observe results.

Must not call OS APIs directly for capabilities Core already exposes. Maps commands → capability invoke/observe.

Evolves toward a **command palette**, not a grid of permanent product buttons.

---

## Session

A **future bounded context** for grouping workstation resources that belong together: open repos, editor windows, terminals, browser tabs scoped to a project.

Named and reserved — not implemented. Capability IDs `core.shell.session.*` delegate here when built.

Sessions are not engineering truth and not Windows itself. See [sessions-bounded-context-v1.md](sessions-bounded-context-v1.md).

---

## Repository (Engineering Authority)

Git + `docs/engineering/` — **engineering truth**. Milestones, ADRs, ship records, reviews.

Separate from Forge. Forge reads it for Projection; Core never owns it.

---

## Registry

The catalog of capability identities Core exposes at `GET /api/v1/core/capabilities`.

Includes metadata (arguments, stability, permissions) so clients build UI without hardcoding capability knowledge.

---

## Phase 0

Forge **language freeze**: architectural baseline, ADR-0001, doctrine, sessions named, glossary.

Implementation proceeds without redesigning the foundation until floor use earns the next abstraction.

---

## Related

| Document | Role |
|----------|------|
| [doctrine.md](doctrine.md) | Three Forge rules |
| [adr/ADR-0001-capability-identity.md](adr/ADR-0001-capability-identity.md) | Capability language |
| [ROADMAP.md](ROADMAP.md) | Implementation sequence |
