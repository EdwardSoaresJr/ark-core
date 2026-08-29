# Engineering Standards

## Document Protection

Architecture documents are **reviewed**. Implementation documents are **updated**.

| Document | Change policy |
|----------|---------------|
| `ARCHITECTURE.md` | Architecture review required |
| `adr/` | Immutable once accepted — supersede, never edit |
| `STANDARDS.md` | Architecture review required |
| `CURRENT_MILESTONE.md` | Update as milestones change |
| `ACTIVE_PR.md` | Update every PR |
| `IMPLEMENTATION_LOG.md` | Append only |

Treat `docs/engineering/adr/` and permanent architecture docs like a core domain model: almost never change without intentional architecture review. A CODEOWNERS rule (or team convention) should protect these paths.

Domain canonical architecture (e.g. `docs/communications/ark-voice-endpoint-architecture-v1.md`) follows the same review discipline — it does not live under `docs/engineering/`.

## Architectural Rules

- Authorities never read projections.
- Projections never mutate authorities.
- GET endpoints never allocate business identity.
- Business identity belongs to authorities.
- Hardware identity belongs to devices.
- Authorities are stable; projections are disposable; no projection may become an authority. ([ADR-0005](adr/ADR-0005-authorities-are-stable-projections-are-disposable.md))

## ADRs

- Never edit an accepted ADR.
- Supersede with a new ADR: `ADR-0005 supersedes ADR-0003`.
- Preserve history; do not rewrite decisions.

## Pull Requests

- One milestone.
- One PR.
- One review.
- Stop.

## Process Rules

- Observe → measure → automate. Same sequence as product features.
- **Automation follows stable process. Never the other way around.**
- **Complexity must earn its existence.** No subsystem, daemon, MCP server, or orchestration layer ships because it is interesting. It ships because repeated pain is named and measured.
- **Every new subsystem must begin as observation before becoming automation.**

  ```
  Queue          → Observe → Metrics → Automation
  Provisioning   → Observe → Projection → Firmware
  Engineering    → Observe → Dashboard → Daemon → MCP
  ```

- Do not build task queues, MCP servers, daemons, or orchestration before the manual process is stable and repeated pain is observed.
- The filter for every proposed feature: **What repeated pain does this remove today?** If it cannot answer, it waits.

## Engineering discipline

- Do not continue into the next milestone unbidden.
- Stop after completing the requested change set.
- Do not redesign frozen architecture.
- Document architectural deviations in [IMPLEMENTATION_LOG.md](IMPLEMENTATION_LOG.md) when that log is present in the working tree.

## Customer shell contract

**Status:** Stable — do not revisit without compelling reason. Product doctrine: [ark-website-doctrine-v1.md](../platform/ark-website-doctrine-v1.md).

**Rule:** No customer-facing page may bypass `x-customer.shell` without an **explicit documented exception** in the PR or implementation log.

Delegates are allowed — they must render through the shell:

- `x-public.lead-intake` → `x-customer.shell`
- `x-portal.app` → `x-customer.shell`

**Legitimate exceptions** (document when used):

- Emergency maintenance page
- Authentication callback
- Error pages (when a full shell would break recovery)
- Health checks (`/up`, probes)

**Must inherit the shell:** marketing pages, lead intake, estimates, payments, vehicle history, approvals, and any other normal customer workflow.

**Vocabulary:** Customer UI uses Sign In, My Account, My Vehicles, My Repairs, My Estimates. `portal.*` routes and `Portal*` classes are implementation detail — not customer copy.

**When customer-shell work is allowed** (otherwise → Growth lane; see [CURRENT_MILESTONE.md](CURRENT_MILESTONE.md)):

1. A page violates the shell contract
2. Production visual drift between anonymous and authenticated states
3. A customer-facing flow breaks

No other customer-shell or doctrine refinement without compelling production reason.

---

## Active sprint (2026-07)

**Communications Workspace v1** — lead → reply → revenue slice. Not Communications v2.

Contract: [communications-workspace-sprint-v1.md](../communications/communications-workspace-sprint-v1.md)

Metric: median first-response time (lead submitted → advisor first outbound). Advisor effort target ≤ 30s (click → send). Growth is parallel maintenance, not primary lane.

**Operational rule:** Every conversation always has someone whose turn it is. Advisor's turn → Needs Attention. Customer's turn → off attention.

## Naming

- Prefer authority terminology over CRUD terminology.
- Prefer projections over caches.
- Prefer explicit names over abbreviations.
