# Adapter Rule v1

**Status:** Locked — last major domain freeze before infrastructure adapters  
**Date:** 2026-07-19  
**Phase:** [Architecture Phase 1 complete](architecture-phase-1-complete.md) · Sprint 1 orchestrator shipping  
**Companions:** [orchestrator-rule-v1.md](orchestrator-rule-v1.md) · [provisioning-request-authority-v1.md](provisioning-request-authority-v1.md) · [deployment-flow-v1.md](deployment-flow-v1.md) · [cluster-assignment-authority-v1.md](cluster-assignment-authority-v1.md)

## Principle

**Everything below `ProvisioningRequest` is replaceable.**

```text
Shop
  │
Deployment
  │
ClusterAssignment
  │
ProvisioningRequest
────────────────────────────────────  ← hard line
Coolify Adapter
Docker Runtime
Stancl Adapter
DNS Adapter
Certificate Adapter
Bootstrap Adapter
Email Adapter
```

Above the line: **ARK Platform platform truth** (authorities).  
Below the line: **adapters** — interchangeable engineering.

Nothing in the authority stack requires Laravel, Docker, Coolify, Cloudflare, Vultr, or Stancl. Those are implementations.

---

## One responsibility per adapter

| Adapter | Consumes | Produces | Never |
| --- | --- | --- | --- |
| **Coolify** | ProvisioningRequest | Deployment exists (runtime projected) | Create Shops; assign clusters; billing |
| **Stancl** | ProvisioningRequest | Tenant initialized | Choose where a tenant belongs |
| **DNS** | ProvisioningRequest | Operations Domain resolves | Shop identity; cluster placement |
| **Certificate** | ProvisioningRequest | TLS for Operations / Public Domain | Routing registry truth |
| **Bootstrap** | ProvisioningRequest | First admin, initial settings, branding, default roles | Cluster assignment |
| **Email** | ProvisioningRequest | Welcome / ops notifications | Declaring provision Complete |

Adapters **do not** create Shops, assign Clusters, or own billing. Placement and identity stay above the line.

---

## Completion Rule (permanently frozen)

**Only the Provisioning Orchestrator may transition a `ProvisioningRequest` to `Completed`.**

Not Coolify. Not Stancl. Not DNS. Not Bootstrap. Not Email.

The orchestrator owns the request lifecycle. It marks Completed only when **all required steps** for that request have succeeded.

That prevents one adapter from declaring success while another failed silently.

Adapters may report step success/failure to the orchestrator (or via structured step records later). They must **not** set `status = Completed` themselves.

Allowed adapter-adjacent transitions (if ever delegated): none for Completed. Orchestrator-only for Completed. Failed/Cancelled may be set by orchestrator when a step fails or an operator cancels — still not by a lone adapter claiming overall success.

---

## Retry Rule (frozen)

**Never retry adapters directly.**

Always retry the **ProvisioningRequest**.

```text
ProvisioningRequest
  ↓
Orchestrator
  ↓
Skip completed steps
  ↓
Retry failed adapters
  ↓
Completed (orchestrator only)
```

You do not “rerun create DNS” as a naked script. You rerun the provisioning workflow, which knows what already succeeded.

---

## Orchestrator (conceptual)

Not an adapter. Platform workflow owner:

1. Load `ProvisioningRequest` (Pending → Running).  
2. Ensure prerequisites above the line exist (Shop, Deployment, ClusterAssignment).  
3. Invoke required adapters in order.  
4. Record per-step outcomes (future step table OK — not required to freeze schema here).  
5. On all required success → `Completed`.  
6. On failure → `Failed` + `failure_reason`; retry = new attempt path or re-run same request with skip-completed — product choice later, rule stays: retry the request/workflow, not a lone adapter.

---

## Story (domain, not engineering)

1. A **Shop** exists.  
2. A **Deployment** describes how it should run.  
3. A **ClusterAssignment** decides where it belongs.  
4. A **ProvisioningRequest** asks for infrastructure.  
5. **Adapters** make that infrastructure real.

After this freeze, work is “implement the Coolify adapter,” not “what is a Shop?”

---

## Explicit non-goals (this freeze)

- Implementing any adapter  
- Choosing Coolify API shapes  
- Stancl package install  
- Orchestrator job class names  
- Per-step persistence schema (may follow without changing this rule)  

---

## Freeze board

| # | Authority / rule | Status |
| --- | --- | --- |
| 1–5d | Domain · Shop · Status · Deployment · Cluster · Assignment · ProvisioningRequest | ✅ |
| 6 | Provisioning v1 (truth → STOP) | ✅ Frozen |
| **6a** | **Adapter Rule v1** (this file) | ✅ **Locked** |
| 7+ | Coolify · Stancl · DNS · Certificate · Bootstrap · Email adapters | Engineering next |
