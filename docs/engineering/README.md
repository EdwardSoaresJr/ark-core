# Engineering Authority

This directory is the **Engineering Authority** for ARK-SMS. It is the single source of truth for engineering maintainers and automated review tools before making implementation decisions.

This hierarchy does **not** replace existing domain documentation. Communications architecture, bounded-context docs, and ecosystem doctrine remain canonical for their respective domains. The Engineering Authority references those documents and tracks current implementation state.

**Separation of concerns:**

| Directory | Answers |
|-----------|---------|
| `docs/communications/` (and sibling domain docs) | How does this product domain work? |
| `docs/engineering/` | What are we building right now? |

Canonical product architecture stays in domain docs. Engineering docs track process, milestones, and implementation state.

## Document Lifecycle

**Architecture documents are reviewed. Implementation documents are updated.**

| Document | Normal PRs |
|----------|------------|
| [ARCHITECTURE.md](ARCHITECTURE.md) | Rarely changes |
| [adr/](adr/) | Immutable once accepted — new ADRs supersede old ones |
| [STANDARDS.md](STANDARDS.md) | Rarely changes |
| [CURRENT_MILESTONE.md](CURRENT_MILESTONE.md) | Changes frequently |
| [ACTIVE_PR.md](ACTIVE_PR.md) | Changes every PR |
| [IMPLEMENTATION_LOG.md](IMPLEMENTATION_LOG.md) | Append only |
| [reviews/](reviews/) | Append per review — engineering judgment, not ADRs |

This distinction prevents architecture drift.

## Before You Implement

Every engineering agent must read these files **in this order** before writing code:

1. [CURRENT_MILESTONE.md](CURRENT_MILESTONE.md) — what we are trying to achieve right now (+ fresh-session knowledge map)
2. [operation-follows-operator-v1.md](../product/operation-follows-operator-v1.md) — product north star
3. [operations/README.md](../operations/README.md) — operation videos (watch before deep doctrine)
4. [ACTIVE_PR.md](ACTIVE_PR.md) — scope of the current pull request
5. [workflow-completion-certification.md](workflow-completion-certification.md) — workflow ↔ operation mapping
6. [ARCHITECTURE.md](ARCHITECTURE.md) — permanent engineering doctrines (when needed)
7. [STANDARDS.md](STANDARDS.md) — architectural rules, PR discipline, engineering discipline
8. [ROADMAP.md](ROADMAP.md) — major engineering phases (context only)
9. [IMPLEMENTATION_LOG.md](IMPLEMENTATION_LOG.md) — append-only history of what shipped
10. [scheduling-runtime-authority.md](../runtime/scheduling-runtime-authority.md) — when touching appointments / capacity / Living Demo

**Sibling product:** WeiDA (`weida` repo) — restaurant OS; current P0 is KDS. Payments/Atlas/Register questions live there.

## After You Implement

Append an entry to [IMPLEMENTATION_LOG.md](IMPLEMENTATION_LOG.md). Do not rewrite prior entries.

## Architecture Decision Records

Frozen decisions live in [adr/](adr/). **Never edit an accepted ADR.** If a decision changes, write a new ADR that supersedes the old one. History is preserved.

## Reviews

Architecture and scope review records live in [reviews/](reviews/). ADRs capture decisions; reviews capture judgment.

## Canonical References (do not duplicate here)

| Domain | Authority |
|--------|-----------|
| **Truth stack (events → evidence)** | [docs/ecosystem/ark-truth-stack-v1.md](../ecosystem/ark-truth-stack-v1.md) |
| **Engineering process (roles, evidence)** | [ark-engineering-doctrine-v1.md](ark-engineering-doctrine-v1.md) |
| **Engineering history (event replay)** | [history/README.md](history/README.md) |
| Endpoint architecture | [docs/communications/ark-voice-endpoint-architecture-v1.md](../communications/ark-voice-endpoint-architecture-v1.md) |
| Communications bounded context | [docs/communications/communications-bounded-context-v1.md](../communications/communications-bounded-context-v1.md) |
| **Customer continuity workspace (binding UI/impl guardrail)** | [communications-workspace-rules.md](communications-workspace-rules.md) · `ark-communications-workspace-guardrail` — relationship state first; not a messaging inbox |
| ARK Voice vision | [docs/communications/ark-voice-vision.md](../communications/ark-voice-vision.md) |
