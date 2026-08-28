# Forge Core — Local Capability Authority v1

**Status:** Frozen baseline (ARK Forge bounded context)  
**Companion:** [../architecture-v1.md](../architecture-v1.md)

---

## One sentence

**Forge Core is the authority for local workstation capabilities.**

Not engineering. Not AI. Not product domain.

---

## What Forge Core owns

Facts about **this machine right now**:

| Capability domain | Examples |
|-------------------|----------|
| **Processes** | Is Cursor running? |
| **Git** | Branch, clean/dirty, remotes — as machine operations |
| **Filesystem** | Read file at path, watch path |
| **Workspace** | Open terminal, browser, editor |
| **Toolchain** | Is Rust installed? Flutter? |
| **Containers** | Is Docker running? (v0.2+) |
| **SSH / WSL** | Keys present? WSL available? (earned) |
| **Notifications / Clipboard / Windows** | Platform surfaces (earned) |
| **MCP** | Transport (v0.4+) |

Forge Core answers: **What can this workstation do right now?**

Nobody else can answer that authoritatively on this machine.

---

## What Forge Core must never know

Forge Core has **no product domain vocabulary**.

It must not know:

- Provisioning, workstations, communications, customers
- Milestones, ADRs, implementation logs (as concepts)
- Repair orders, ARK Voice, Asterisk as domain

Forge Core knows:

- `"I can read a file at this path."`
- `"I can invoke the Cursor capability."`
- `"I can observe git in this directory."`

It does **not** know:

- `"Open the provisioning page."`
- `"This is the active PR."`

Those are **application / projection** concerns.

---

## Generic verbs only (guardrail)

Forge Core **actions** must be generic verbs. v0.2 replaces action strings with **Capability Identity** per [ADR-0001](../adr/ADR-0001-capability-identity.md).

| Allowed (v0.1 transitional) | Forbidden |
|---------|-----------|
| `read_file` | `open_ark_voice` |
| `open_url` | `test_provisioning` |
| `open_application` | `review_pr1` |
| `run_command` | `deploy_lugsnplugs` |
| `git_status` | any milestone / product / domain name |
| `list_processes` | |

**Target (v0.2):** stable IDs — `core.browser.url.open`, `core.git.status.read`, `core.shell.application.open`

**Bad:** product shortcuts as capability IDs or actions.

Higher-level intent belongs in **Workbench / Projection**. Core executes capability identity only.

See [doctrine.md](../doctrine.md).

---

## Capability graph (not method calls)

Flutter does not call `launchCursor()`.

It asks the capability graph:

```
Capability: Cursor
    ↓
Action: OpenWorkspace { path }
```

v0.1 capabilities:

```
✓ Git
✓ Filesystem
✓ Processes
✓ Browser
✓ Cursor
✓ Terminal
○ Docker      (v0.2)
○ SSH         (earned)
○ WSL         (earned)
○ MCP         (v0.4)
```

---

## Three authorities (do not merge)

| Authority | Owns | Examples |
|-----------|------|----------|
| **Git + Engineering docs** | Engineering truth | Milestone, PR, ADR, ship record |
| **Forge Core** | Workstation capability truth | Process running, tool installed, file readable |
| **Workbench (Flutter)** | Interaction | Renders projections, invokes capabilities |

---

## Layer stack

```
Forge Workbench (Flutter)
        │
        ▼
Engineering Projection     ← knows milestones, ADRs, reviews
        │
        ▼
Forge Core                 ← capability graph only
        │
        ▼
Windows / macOS / Linux
```

**Engineering Projection** reads files via Core, parses `EngineeringState`, serves Flutter.

**Forge Core** never parses milestones.

---

## Workstation health (future projection)

Not AI. Capability observation composed into a projection:

```
Repository: ARK Voice
✓ Git clean
✓ Flutter installed
✓ Rust installed
✓ Cursor running
⚠ PHPUnit failed
```

Each line is a **capability probe** — not engineering authority stored in Core.

---

## Storage rule

Forge Core must **never persist** engineering milestone, PR, review, or task state.

Capability cache (if any) is **machine observation**, not engineering truth. Prefer derive-on-request in v0.1.

---

## Platform bounded contexts

Windows (and later macOS/Linux) are bounded contexts.

Each platform has authorities: processes, filesystem, clipboard, notifications, installed tools.

**Forge Core owns those** per platform. Flutter stays identical; Core implementations swap.

---

## Change policy

Capability additions require: repeated friction in notebook, architecture review, no product domain leakage into Core.
