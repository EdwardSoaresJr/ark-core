# Cluster Assignment Authority v1

**Status:** Active — scaffolding before Provisioning v1  
**Date:** 2026-07-19  
**Companions:** [cluster-authority-v1.md](cluster-authority-v1.md) · [deployment-flow-v1.md](deployment-flow-v1.md) · [shop-authority-v1.md](shop-authority-v1.md)

## Principle

Placement is a **decision with history**, not a mutable foreign key.

```text
Shop
  ↓
Deployment
  ↓
Cluster Assignment   ← authority for “where this Shop lives”
  ↓
Cluster
```

`Deployment.cluster_id` is the **current** placement pointer.  
`ClusterAssignment` records **why / who / when / from where**.

If `cluster_id` alone changes, promotion and migration history is lost.

---

## What an assignment answers

| Question | Field |
| --- | --- |
| Why was this shop on Shared A? | `reason` + `source` |
| Who moved it? | `assigned_by_user_id` |
| When? | `assigned_at` |
| Automatic or manual? | `source` |
| What cluster before? | `previous_cluster_id` |

---

## Flow (Provisioning v1)

```text
Create Shop
  ↓
Choose Deployment Profile   (Shared | Dedicated only)
  ↓
ClusterAssignmentPolicy
  ↓
Deployment (current cluster_id)
  ↓
Stop
```

The policy **does not provision**. It only decides: *this Shop belongs on Shared Cluster A.*

Reusable later for: provisioning, migrations, balancing, promotions, disaster recovery.

---

## ClusterAssignmentPolicy

Domain service. Provisioning must not search clusters ad hoc forever.

v1 behavior (embarrassingly simple):

```text
assign(Shared)
  → Shared clusters
  → Healthy
  → accepting_new_shops
  → lowest utilization (deployment count)
  → assign
```

Later growth without changing the provisioning workflow:

- Skip maintenance / degraded  
- Skip full clusters (`accepting_new_shops = false`)  
- Prefer region (if ever)  
- Honor dedicated deployments  

---

## DeploymentProfile (frozen)

| Value | Meaning |
| --- | --- |
| **Shared** | Infrastructure: shared cluster |
| **Dedicated** | Infrastructure: dedicated cluster |

**Enterprise is a Subscription / pricing plan — not a DeploymentProfile.**  
Do not couple billing vocabulary to infrastructure enums.

---

## Cluster.assignable

Boolean authority flag: `accepting_new_shops`.

| Cluster | Status | Accepting |
| --- | --- | --- |
| Shared A | Healthy | ✔ |
| Shared A | Healthy | ✘ (full / freeze) |

Provisioning selects: Shared → Healthy → Assignable → lowest utilization.

---

## Boundary with Provisioning v2

```text
Cluster assignment (business decision)
  ↓
Deployment created / updated
  ↓
Dispatch provision job (future)
  ↓
Coolify · Stancl · DB · first user · Ready
```

Where the Shop lives ≠ making it exist there.
