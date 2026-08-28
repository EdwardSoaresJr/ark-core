# Provisioning Request Authority v1

**Status:** Active — scaffolding (no infrastructure adapters)  
**Date:** 2026-07-19  
**Companions:** [adapter-rule-v1.md](adapter-rule-v1.md) · [deployment-flow-v1.md](deployment-flow-v1.md) · [cluster-assignment-authority-v1.md](cluster-assignment-authority-v1.md) · [shop-status-authority-v1.md](shop-status-authority-v1.md)

## Principle

Provisioning is a **workflow**, not a property of a Shop.

```text
Shop
  ↓
Deployment
  ↓
ClusterAssignment     ← where the Shop lives (platform truth)
  ↓
ProvisioningRequest   ← attempt to make it exist (workflow authority)
  ↓
Coolify · Docker · Stancl · DNS · first user   ← infrastructure adapters
```

Everything above `ProvisioningRequest` is platform truth.  
Everything below it is infrastructure.

---

## Questions this authority answers

| Question | Field |
| --- | --- |
| Was provisioning requested? | row exists |
| Is it pending / running? | `status` |
| Did it fail? Why? | `Failed` + `failure_reason` |
| Can I retry? | new request (or reopen policy later) |
| Who requested it? | `requested_by_user_id` |
| Automatic or manual? | `source` |
| Cancelled? | `Cancelled` |

Those are not Shop fields.

---

## Fields

| Field | Role |
| --- | --- |
| `id` | Opaque id |
| `shop_id` | Shop |
| `deployment_id` | Deployment being provisioned |
| `status` | Pending \| Running \| Completed \| Failed \| Cancelled |
| `source` | automatic \| manual |
| `requested_by_user_id` | Nullable actor |
| `requested_at` | Request created |
| `started_at` | Worker began |
| `completed_at` | Success |
| `failed_at` | Failure |
| `failure_reason` | Nullable text |

No Docker. No Coolify. No Stancl ids on this row in v1 — adapters may add projection refs later without becoming authority.

---

## States

```text
Pending
  → Running
  → Completed

Pending / Running
  → Failed
  → Cancelled
```

Retry = new `ProvisioningRequest` (v1). Do not rewrite Completed history.

---

## Boundary with Provisioning v1 / v2

### Provisioning v1 — frozen (truth only)

```text
Create Shop
  → Create Deployment
  → ClusterAssignmentPolicy
  → Create ClusterAssignment
  → STOP
```

No `ProvisioningRequest`. No jobs. No infrastructure.

### After this authority — still no adapters yet

Scaffolding only: model + migration + relationships.

### Provisioning v2 — infrastructure

```text
Create Shop
  → Create Deployment
  → ClusterAssignment
  → Create ProvisioningRequest (Pending)
  → Dispatch job (consumes request)
  → Coolify · Stancl · DNS · first user
  → Mark request Completed (or Failed)
```

---

## Freeze order (from here)

| # | Step | Status |
| --- | --- | --- |
| ✅ | Domain · Shop · Status · Deployment · Cluster · Assignment | Locked / scaffolding |
| ✅ | **Provisioning Request Authority** (this file) | Scaffolding |
| ✅ | [Adapter Rule v1](adapter-rule-v1.md) | Locked |
| Next | Coolify · Stancl · DNS · Certificate · Bootstrap · Email adapters | Engineering |
| | Provisioning Orchestrator (owns Completed) | Engineering |

Adapters hang off this request. See Adapter Rule: only the orchestrator marks Completed; retries go through the request, not naked adapters.
