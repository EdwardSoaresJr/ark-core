# ARK Forge Architecture v1

**Status:** Product baseline — Engineering Workbench  
**Bounded context:** ARK Forge only — not ARK Voice, not product code

---

## Product identity

| Name | What it is |
|------|------------|
| **Engineering Workbench** | The product — observability, workspace, task running, AI coordination (earned later) |
| **Dashboard** | One projection of engineering state |
| **Forge Runtime** | Desktop runtime — owns local machine capabilities |
| **Flutter app** | Presentation layer only |

A dashboard answers *what's happening*. A workbench is *where engineering happens*.

---

## Layer model (mirrors ARK Voice)

| ARK Voice | ARK Forge |
|-----------|-----------|
| Authority | Git + `docs/engineering/` |
| Projection | Engineering State |
| Transport / execution | Forge Runtime |
| Surface | Flutter |

```
               ARK Forge Workbench
────────────────────────────────────
Engineering Authority  (Git + docs)
Task Runner            (v0.2+)
Forge Runtime          (capabilities)
Workspace              (launchers)
Observability          (dashboard projection)
AI Coordination        (v1+, earned)
```

---

## Architecture

```
Flutter
   │  HTTP (protocol contract)
   ▼
Forge Runtime                    ← owns workstation capabilities
   │
───┼────────────────────────────────────────
   │
 Git · Cursor · Browser · Terminal
 Filesystem · File Watcher · Settings
 Docker · SSH · MCP              (earned later)
```

**Flutter must not:**

- Invoke `git` directly
- Inspect OS processes
- Launch terminals or browsers
- Read engineering files from disk

Flutter asks the runtime. The runtime implements capabilities per platform (Windows today, macOS/Linux later — Flutter unchanged).

---

## Two authorities (do not confuse)

| Authority | Owns | Must not store |
|-----------|------|----------------|
| **Engineering** | Milestone, PR, ADR, ship record | — lives in Git + docs |
| **Forge Runtime** | How to read git, launch cursor, watch files on *this machine* | Milestone, PR, review, task state |

The runtime derives engineering projections from the repo. It never becomes the source of engineering knowledge.

---

## v0.1 capabilities

| Capability | Runtime | Flutter |
|------------|---------|---------|
| Read Engineering Authority | ✓ | displays |
| Git observe | ✓ | displays |
| Launch Cursor / browser / terminal | ✓ | button → API |
| Read filesystem (docs) | ✓ | doc viewer |
| Watch engineering files | ✓ | refresh on change (later wire) |
| Settings (repo path, port) | ✓ | — |
| AI / MCP / orchestration | ✗ | ✗ |

---

## Protocol

[engineering-state.schema.json](../protocol/engineering-state.schema.json) defines the **Engineering State** projection shape.

Runtime serves it at `GET /api/v1/state`. Flutter renders it. Transport may change; shape should not.

Future capability endpoints (v0.3+):

```
POST /cursor/open
POST /cursor/context
GET  /cursor/status
```

Flutter stays the same. Runtime gains endpoints.

---

## Roadmap

| Version | Scope |
|---------|--------|
| **v0.1** | Runtime + Flutter dashboard |
| v0.2 | Browser automation, test runner, Docker |
| v0.3 | Cursor integration |
| v0.4 | MCP |
| v1 | Multiple AI agents |

---

## Complexity earns existence

Infrastructure inside Forge still follows observe → measure → automate.

The runtime is v0.1 **because** it defines one stable capability API — not because of AI. Flutter must not embed platform logic that duplicates across Windows/macOS/Linux.

AI, MCP, and multi-agent belong at v1 — after the workbench is used daily.

---

## Agent boundaries

| Agent | Bounded context |
|-------|-----------------|
| Agent 1 | ARK SMS product — never thinks about Forge during implementation |
| Agent 2 | ARK Forge only — workbench, runtime, protocol |

Do not pull ARK Voice goals into Forge reviews.
