# Engineering Platform Architecture v1

**Status:** Archived research baseline (Agent 2 complete — 2026-06-26)  
**Date:** 2026-06-26  
**Audience:** Engineering agents, platform decisions, future MCP/tooling work  
**Companion:** [Engineering Authority](../README.md)

This document answers platform questions for AI-assisted development at ARK. It is **research architecture**, not implementation. Agent 2 produced this document and **stopped**. Agent 1 (implementation) consumes it only after review. Do not extend platform research until floor evidence earns Phase 2 from the phased rollout below.

---

## Charter: Agent 2 vs Agent 1

| Agent | Job | Deliverable | Must not |
|-------|-----|-------------|----------|
| **Agent 1 — Implementation** | Ship bounded PRs under Engineering Authority | Code, migrations, tests | Redesign frozen architecture; continue past ACTIVE_PR scope |
| **Agent 2 — Engineering Platform** | Research how engineering should work | Architecture documents, ADR proposals | Write product code; merge implementation |

Agent 2 exists because implementation agents optimize for *finishing*. Platform questions require *stopping*, comparing options, and recording decisions before anyone builds.

---

## Problem

ARK now has an Engineering Authority (`docs/engineering/`). Multiple agents (Cursor, ChatGPT reviews, future automation) need the same inputs and must not diverge on:

- What task is active
- What scope is allowed
- What architecture is frozen
- What shipped and why

The platform question is not "build an AI dev tool." It is:

> **How do we represent engineering work and connect agents to it without creating a second product?**

---

## Design Principles

Mirror ARK product architecture in the engineering layer:

| Product pattern | Engineering platform equivalent |
|-----------------|----------------------------------|
| Authority holds truth | Git + reviewed docs hold engineering truth |
| Projections answer questions | Agent context packs, CI summaries, session briefings |
| Identity is stable | Milestones and ADRs are stable |
| Views are disposable | Agent sessions, chat transcripts, tool outputs |
| Observe before automate | Docs + discipline first; services only when repeated pain |

**Default answer to new infrastructure:** Not yet.

---

## 1. How Should Engineering Tasks Be Represented?

### Recommendation: Docs-as-code authority, not a task database

Engineering tasks are already represented correctly in Phase 0:

```
CURRENT_MILESTONE.md   → stable objective + exit criteria
ACTIVE_PR.md           → bounded implementation unit + explicit out-of-scope
IMPLEMENTATION_LOG.md  → append-only ship history
ROADMAP.md             → phase context (read-only during implementation)
```

These files **are** the task authority. Do not introduce `EngineeringTask`, `AgentJob`, or a Jira-shaped store in v1.

### Task layers

| Layer | Representation | Mutability |
|-------|----------------|------------|
| **Milestone** | `CURRENT_MILESTONE.md` | Changes when exit criteria met |
| **PR scope** | `ACTIVE_PR.md` | Changes every PR |
| **Architecture** | Domain docs + `adr/` | Reviewed; ADRs immutable |
| **Ship record** | Git commits + `IMPLEMENTATION_LOG.md` | Git immutable; log append-only |
| **Observation** | Retrospectives (future), floor notes | Append-only |

### Optional future: structured frontmatter

If agents need machine parsing without a database, add YAML frontmatter to `ACTIVE_PR.md`:

```yaml
---
pr: PR1
status: ready
milestone: zero-touch-provisioning
in_scope:
  - CommunicationDevice schema
out_of_scope:
  - Firmware
  - ARI
---
```

Human-readable body remains authoritative. Frontmatter is a **projection** for tooling — not a parallel task store.

### Rejected for v1

- Task tables in MySQL
- GitHub Issues as authority (issues are conversation; docs + merged code are truth)
- Agent memory / vector stores as authority
- Real-time task boards for engineering work

---

## 2. How Should AI Agents Communicate?

### Recommendation: Artifact handoffs, not agent chat

Agents should not coordinate through conversational threads as authority. They coordinate through **shared artifacts**:

```
Read (ordered):
  CURRENT_MILESTONE → ACTIVE_PR → ARCHITECTURE → STANDARDS

Implement (bounded):
  code + tests within ACTIVE_PR scope

Write (append):
  IMPLEMENTATION_LOG entry

Stop:
  do not continue to next PR
```

### Communication channels

| Channel | Role | Authority? |
|---------|------|------------|
| `docs/engineering/` | Scope, doctrine, history | **Yes** |
| Git (commits, PRs, diff) | What changed | **Yes** |
| Cursor rules (`.cursor/rules/`) | Session enforcement | Configuration |
| Agent chat / transcripts | Reasoning, exploration | **No** — observation only |
| MCP tool results | Command output, file reads | **No** — disposable |
| CI checks | Verification projection | **No** — derived |

### Multi-agent pattern

```
Agent 2 (research)
  → produces architecture doc / ADR proposal
  → human review
  → merges doc (or accepts ADR)

Agent 1 (implementation)
  → reads Engineering Authority
  → ships PR1
  → appends IMPLEMENTATION_LOG

Agent 3 (review — future)
  → reads diff + ACTIVE_PR + doctrines
  → outputs scope compliance report (like PR1 audit)
  → does not merge
```

No agent publishes to another agent's context directly. The repo is the message bus.

### Session briefing (projection)

Before implementation, an agent should receive a **context pack** assembled from:

1. Engineering Authority read order (4 files minimum)
2. `git diff` against base branch
3. Relevant domain canonical doc (one path, not the whole repo)
4. ACTIVE_PR out-of-scope list verbatim

This pack is a projection — regenerated every session, never stored as authority.

---

## 3. Should We Use MCP?

### Recommendation: Yes — selectively, as tool transport

MCP fits ARK the same way Asterisk fits voice: **transport, not authority.**

Use MCP for:

| Tool surface | Example capabilities |
|--------------|---------------------|
| **Repository read** | Read engineering docs, domain architecture, ADRs |
| **Repository inspect** | `git status`, `git diff`, scoped file search |
| **Verification** | Run `php artisan test` on named paths |
| **Browser observe** | Floor-test portal/operations flows (already available) |
| **External read** | GitHub PR checks via `gh` (when needed) |

Do **not** use MCP for:

- Storing milestone state (docs are authority)
- Mutating production or local MySQL without explicit human intent
- Replacing Engineering Authority with MCP server config
- Agent-to-agent messaging

### MCP server shape (proposed)

One **`ark-engineering`** MCP server (future implementation), read-heavy:

```
resources/
  engineering://current-milestone
  engineering://active-pr
  engineering://architecture
  engineering://standards

tools/
  engineering_read_pack      → returns ordered authority bundle
  git_scope_diff             → diff since branch base
  run_tests                  → php artisan test {path}, SQLite only
  validate_active_pr_paths   → list changed files vs in/out scope (heuristic)
```

**Phase 0 (now):** Cursor native file read + shell + existing browser MCP — sufficient.

**Phase 1 (if earned):** Thin `ark-engineering` MCP wrapping doc reads and safe test invocation.

**Reject:** Multiple overlapping MCP servers duplicating git, filesystem, and browser.

---

## 4. Local Service?

### Recommendation: Defer — scripts first

A persistent local service (daemon) is justified only when:

- Repeated manual steps cause scope drift (observed ≥10 times)
- Git hooks + Cursor rules cannot enforce discipline
- Multiple IDEs/agents need the same runner beyond MCP

### v1 alternative: git hooks + npm/composer scripts

| Hook / script | Purpose |
|---------------|---------|
| `pre-commit` (optional) | Warn if `ACTIVE_PR.md` status is not `Ready` / block WIP commits to main |
| `prepare-commit-msg` (optional) | Suggest `IMPLEMENTATION_LOG` reminder in commit body |
| `composer engineering:read-pack` (future) | Print authority paths for agents |
| `composer engineering:scope-audit` (future) | Compare changed files to ACTIVE_PR keywords |

Hooks are **configuration**, not authority. They enforce discipline; docs remain truth.

### When a local service would earn existence

A **`ark-engineering-runner`** local HTTP or stdio service if:

- Non-Cursor agents (ChatGPT desktop, CLI agents) need identical tooling
- Scope audits become automated gate before PR open
- Floor-test orchestration (VVX350 provisioning loop) needs repeatable scripts

Until then: **no local daemon.**

---

## 5. Windows Service?

### Recommendation: No

ARK development runs on Windows (PhpStorm, Cursor, local MySQL). A Windows Service adds:

- Install/upgrade burden
- Permission and firewall complexity
- Divergence from macOS/Linux contributors (future)

Engineering platform tools should run as:

- **User-session processes** (Cursor, terminal, `php artisan`)
- **Git hooks** (user or repo level)
- **CI on GitHub Actions** (verification authority for merge)

If background work is needed on Windows, prefer **Task Scheduler** running a signed script at login — not a Windows Service — and only for observation (log collection), not authority.

---

## 6. Flutter?

### Recommendation: No — wrong surface

Flutter is ARK **Mobile product runtime**, not engineering infrastructure.

| Surface | Purpose |
|---------|---------|
| Flutter / ARK Mobile | Technicians and advisors in the shop |
| Cursor / CLI / MCP | Engineering agents at the desk |
| Laravel / Blade | Operational product |

Do not build engineering task UI in Flutter. Do not embed agents in the mobile binary.

**Exception (far future):** Read-only engineering status on Arkify control plane for deployment observability — that belongs to **Arkify platform**, not ARK SMS repo engineering tooling.

---

## 7. Git Integration?

### Recommendation: Essential — git is ship authority

Git integration is non-optional. The platform stack:

```
Engineering Authority (docs)     → what we intend to build
Git branch + commits           → what we actually changed
GitHub PR + review             → human gate
CI (GitHub Actions)            → verification projection
IMPLEMENTATION_LOG append      → engineering memory
```

### Branch discipline

| Pattern | Use |
|---------|-----|
| `main` | Integrated product code |
| Feature branch per PR | One milestone slice |
| Docs-only commits | `docs(engineering): ...` separated from product PRs when possible |

### PR template (recommended)

Every product PR links:

- ACTIVE_PR identifier (PR1, PR2, …)
- Milestone name
- Doctrine checklist (authorities vs projections touched)
- IMPLEMENTATION_LOG entry drafted in PR description

### CODEOWNERS (recommended)

Protect:

```
docs/engineering/adr/
docs/engineering/ARCHITECTURE.md
docs/engineering/STANDARDS.md
docs/communications/*-architecture-*.md
```

Requires intentional architecture review to merge.

### Rejected

- Agents pushing directly to `main` or `production`
- Git as sole documentation (docs explain *why*; git shows *what*)
- Automated merge without scope audit on architecture-touching PRs

---

## 8. Browser Integration?

### Recommendation: Optional observe layer — not core platform

Browser integration supports **floor verification**, not engineering authority:

| Use | Tool | Phase |
|-----|------|-------|
| Portal / operations smoke test | MCP browser (existing) | Now |
| VVX350 "Connected" milestone validation | Manual + Asterisk logs | PR1 floor test |
| Automated visual regression | Defer | Not earned |

Browser state is never authority. Screenshots and DOM snapshots are observation.

Do not build a headless browser service as engineering platform core. Use MCP browser tools inside Cursor when an agent needs to verify a flow during a bounded task.

---

## Proposed Platform Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                 ENGINEERING AUTHORITY                        │
│  docs/engineering/  +  domain architecture docs  +  adr/    │
└──────────────────────────┬──────────────────────────────────┘
                           │ read (ordered)
         ┌─────────────────┼─────────────────┐
         ▼                 ▼                 ▼
   ┌───────────┐    ┌─────────────┐   ┌─────────────┐
   │ Cursor    │    │ ChatGPT     │   │ Future CLI  │
   │ Agent 1   │    │ review      │   │ agents      │
   └─────┬─────┘    └──────┬──────┘   └──────┬──────┘
         │                 │                  │
         └─────────────────┼──────────────────┘
                           │ tools (transport)
         ┌─────────────────┼─────────────────┐
         ▼                 ▼                 ▼
   ┌───────────┐    ┌─────────────┐   ┌─────────────┐
   │ Git       │    │ MCP tools   │   │ CI (GHA)    │
   │           │    │ read, test, │   │ tests, lint │
   │           │    │ browser     │   │             │
   └───────────┘    └─────────────┘   └─────────────┘
                           │
                           ▼
              ┌────────────────────────┐
              │ IMPLEMENTATION_LOG     │
              │ (append after ship)    │
              └────────────────────────┘
```

**Agent 2 sits above implementation**, producing reviewed documents that may add ADRs or update roadmap — never shipping code.

---

## Phased Rollout

| Phase | Deliverable | Trigger |
|-------|-------------|---------|
| **0 — Now** | Engineering Authority + Cursor rule + manual scope audits | ✅ Done |
| **1** | PR template, CODEOWNERS, canonical architecture commits separated from code | Before PR1 merge |
| **2** | `engineering:scope-audit` script; optional MCP read-pack | After 3+ scope drift incidents |
| **3** | Retrospectives directory; milestone postmortems | After first milestone completes (VVX Connected) |
| **4** | Thin `ark-engineering` MCP server | Multiple agent runtimes need parity |

---

## Non-Goals

- Engineering platform as a SaaS product
- Real-time multi-agent orchestration dashboard
- Replacing Cursor with custom IDE
- Task database, sprint boards, or velocity metrics
- Windows Service or always-on local daemon (v1)
- Flutter engineering UI
- Vector DB / RAG over repo as authority
- Agents merging or deploying without human gate

---

## Open Questions (Observation Notebook)

Track before building more platform:

1. Do non-Cursor agents (ChatGPT project, future CLI) actually get used for ARK implementation?
2. How often does scope drift happen after Engineering Authority — weekly count?
3. Is manual IMPLEMENTATION_LOG append forgotten after merge?
4. Does PR template friction help or get skipped?
5. After VVX350 Connected, what repeated sentence earns MCP read-pack automation?

**No repeated sentence → no Phase 2 infrastructure.**

---

## Summary Decisions

| Question | Decision |
|----------|----------|
| Task representation | Docs-as-code (`CURRENT_MILESTONE`, `ACTIVE_PR`, log) — no task DB |
| Agent communication | Shared artifacts in repo; no agent chat as authority |
| MCP | Yes, later, as read/test transport — not authority store |
| Local service | Defer; git hooks + composer scripts first |
| Windows Service | No |
| Flutter | No — product surface, not engineering |
| Git integration | Essential — ship authority + CODEOWNERS + PR template |
| Browser integration | Optional MCP observe for verification — not core |

---

## Next Agent 2 Research Topics (if requested)

- ADR-0006 proposal: Git as ship authority vs docs as intent authority
- Retrospective template for milestone completion
- Arkify control plane: should deployment observability consume Engineering Authority?
- Multi-repo engineering (ARK SMS, ARK-WEB, Flutter mobile) — single authority or per-repo?

**Agent 2 stops here.** Implementation waits for human review of this document.
