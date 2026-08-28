# ARK Forge Doctrine

Three rules. Everything else is earned.

Forge is a **separate bounded context** from ARK SMS / ARK Voice. These doctrines constrain Forge only.

---

## 1. Capabilities are generic

Forge Core exposes **workstation verbs** — read file, open URL, git status, spawn process.

Core must never expose product-domain capabilities:

- `open_ark_voice`
- `test_provisioning`
- `review_pr1`
- `deploy_lugsnplugs`

Generic at the identity layer:

```
core.browser.url.open     ✓
core.git.status.read      ✓
open_github_for_repo      ✗
```

Enforcement is **runtime**, not convention. See `verbs.rs` and [ADR-0001](adr/ADR-0001-capability-identity.md).

---

## 2. Intent belongs to the Workbench

The Workbench (and future clients) map **human intent** to capability identity:

| Operator says | Workbench maps to |
|---------------|-------------------|
| Open Cursor | `core.shell.application.open` + `{ application: cursor, path }` |
| Open GitHub | `core.browser.url.open` + `{ url }` |
| Run Git Status | `core.git.status.read` |

Core does not know "Open GitHub for this milestone." Projection may supply context; Workbench chooses the command.

The Workbench evolves toward a **command palette**, not a grid of permanent buttons.

---

## 3. Core owns capabilities, never workflow

| Layer | Owns |
|-------|------|
| Git + `docs/engineering/` | Engineering truth |
| **Forge Core** | What this machine can do |
| **Engineering Projection** | Interpreted engineering state |
| **Workbench** | User interaction and intent |
| **Sessions** (future) | Grouped workspace context |

Core answers: *Can this workstation open a URL?*  
Core never answers: *Should we deploy PR1?* or *Which milestone is active?*

Workflow, sequencing, and engineering judgment live above Core.

---

## Companion documents

| Document | Role |
|----------|------|
| [adr/ADR-0001-capability-identity.md](adr/ADR-0001-capability-identity.md) | Stable capability language |
| [glossary.md](glossary.md) | Pinned vocabulary — terms must not drift |
| [runtime/runtime-authority-v1.md](runtime/runtime-authority-v1.md) | Core authority boundary |
| [sessions-bounded-context-v1.md](sessions-bounded-context-v1.md) | Fourth context — named, not built |

## ADR immutability

Forge ADRs follow the same rule as engineering ADRs: **supersede, never edit.** See [adr/README.md](adr/README.md).
