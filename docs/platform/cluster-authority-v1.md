# Cluster Authority v1

**Status:** Active — scaffolding only (no production behavior change)  
**Date:** 2026-07-19  
**Companions:** [deployment-authority-v1.md](deployment-authority-v1.md) · [shop-authority-v1.md](shop-authority-v1.md) · [deployment-flow-v1.md](deployment-flow-v1.md)

## Principle

```text
Shop
  ↓
Deployment
  ↓
Cluster
  ↓
Coolify
  ↓
Docker
```

**Cluster** is a first-class deployment authority. Shops never know VPS names or Docker. Deployment references a Cluster. Traefik routes to a Cluster. Coolify deploys a Cluster.

Not:

```text
Shop → VPS
Shop → Docker
```

---

## What Cluster owns

| Concern | Authority |
| --- | --- |
| Identity | `id`, `name`, `slug` |
| Type | Shared \| Dedicated |
| Status | Provisioning \| Healthy \| Maintenance \| Degraded \| Offline |
| Accepting new shops | `accepting_new_shops` — assignable gate for provisioning |
| Deployment target | Coolify/server label (e.g. `coolify-server-01`) |
| Ingress endpoint | What ARK Edge dials (e.g. `https://shared-a.internal`) |
| Application version | `current_version` running on the cluster |

---

## Frozen types

| Type | Hosts |
| --- | --- |
| **Shared** | Many Shops |
| **Dedicated** | Exactly one Shop |

Engineering vocabulary is **Shared Cluster** / **Dedicated Cluster**. Marketing may later call Dedicated “Enterprise.” Enterprise is **not** a technical cluster type.

Stancl runs on Shared and Dedicated alike ([deployment-authority-v1.md](deployment-authority-v1.md)).

---

## Observations (not stored on Cluster)

Do **not** persist:

- `current_shop_count`
- `capacity` / utilization

Compute when needed:

| Observation | Source |
| --- | --- |
| Current Shops | `cluster->deployments()->count()` |
| Capacity / utilization | Future policy / metrics — not Cluster authority columns |

Storing counters creates sync debt forever.

---

## Relationships

```text
Shop hasOne Deployment
Deployment belongsTo Shop
Deployment belongsTo Cluster          ← current placement pointer
Shop / Deployment hasMany ClusterAssignment  ← placement history
Cluster hasMany Deployments
```

Placement decisions: [cluster-assignment-authority-v1.md](cluster-assignment-authority-v1.md).

---

## Rules

- Shops never know VPS names.  
- Shops never know Docker.  
- Deployment references Cluster.  
- Traefik routes to Cluster (via Ingress Endpoint).  
- Coolify deploys Cluster (via Deployment Target).  
- No Coolify/Stancl/DNS integration in this scaffolding pass.

---

## Scaffolding scope (this PR)

| Included | Excluded |
| --- | --- |
| Docs, enums, models, migration | Provisioning engine |
| Dev seeder: Shared Cluster A | Production Demo Auto Repair changes |
| Hidden read-only admin table | Edit/actions, nav link |
| Relationships only | Stancl, routing, Coolify API |

See [deployment-flow-v1.md](deployment-flow-v1.md).
