# Deployment Authority v1

**Status:** Locked — ARK Edge, Ingress Endpoint, parallel evolution  
**Date:** 2026-07-19  
**Companions:** [cluster-authority-v1.md](cluster-authority-v1.md) · [deployment-flow-v1.md](deployment-flow-v1.md) · [shop-authority-v1.md](shop-authority-v1.md) · [shop-status-authority-v1.md](shop-status-authority-v1.md) · [domain-contract-v1.md](domain-contract-v1.md) · [production-runtime-host-doctrine-v1.md](../../infra/coolify/production-runtime-host-doctrine-v1.md) · [fleet-provisioning-authority-v1.md](../deployment/fleet-provisioning-authority-v1.md)

## Principle

```text
Shop → Deployment → Docker
```

Docker is an **implementation detail of the Deployment projection**. It is not “ARK.”

By the time Laravel handles a request, the request is **already on the correct machine**. Laravel never asks “which VPS am I supposed to be on?”

---

## Two internal products

Not customer-facing SKUs. Internal clarity:

| Product | Owns |
| --- | --- |
| **ARK** | The repair shop operating system |
| **ARK Cloud** | Shop provisioning, DNS intent, deployments, backups, billing hooks, upgrades, **routing registry** |

Customers never buy “ARK Cloud.” They experience it on **Start Trial**.

---

## Separation of concerns (locked)

| Layer | Owns | Does not own |
| --- | --- | --- |
| **ARK Cloud** | Shop, Deployment, Routing Target, Ingress Endpoint (truth) | Proxying bytes |
| **Coolify** | Deploy containers / apps on a target | Routing registry, hostname → Shop |
| **ARK Edge (Traefik)** | “Given `demo-auto.arksms.com`, where do I send this?” | Stancl, ROs, customers, provisioning |
| **Laravel + Stancl** | Resolve Shop on this machine; run ARK | Choosing a VPS |

Coolify may **host** Traefik on the same VPS as the front door. Coolify must **not** be the routing registry. Replace Coolify later without rewriting edge or Shop authority.

---

## Deployment fields

```text
Shop
  ↓
Deployment
  ├── Profile              shared | dedicated | enterprise | self_hosted
  ├── Routing Target       shared-cluster-a | dedicated-vps-42
  └── Ingress Endpoint     https://cluster-a.internal  (reachable URL/host:port)
        ↓
ARK Edge (Traefik)
        ↓
Docker Deployment (:80)
```

| Field | Role |
| --- | --- |
| **Profile** | Shop intent class |
| **Routing Target** | Logical destination name |
| **Ingress Endpoint** | What the edge actually dials — not Docker, Vultr, or Coolify IDs |

The edge needs only Operations Domain → Ingress Endpoint. Swap Vultr → Hetzner by changing Ingress Endpoint; Shop identity and Operations Domain stay put.

Promotion:

```text
Update Routing Target + Ingress Endpoint
  → regenerate Traefik config / reload edge
  → (migrate data as needed)
```

No DNS change. No URL change. No app config change for the Shop.

---

## ARK Edge (core) — Cloudflare optional

**Core architecture needs an edge router, not Cloudflare.**

| Layer | Role |
| --- | --- |
| **ARK Edge** | Traefik (recommended) — hostname → Ingress Endpoint |
| **Cloudflare** | Optional later: DDoS, CDN, WAF, DNS hosting, bots |

Do not depend on Cloudflare-specific routing (Workers, Tunnel-as-registry, Load Balancing pools as Shop truth). DNS may point `*.arksms.com` at the edge IP whether Cloudflare proxies or not.

### Model A — One Edge (recommended)

```text
Internet
   │
   ▼
DNS  *.arksms.com  →  one public IP
   │
   ▼
ARK Edge VPS (Traefik)
   │  (may be the Coolify control / ingress host)
   │
   ├──► Shared Cluster A
   ├──► Shared Cluster B
   └──► Dedicated / Enterprise VPS (private net or tunnel)
```

| Advantage | Why it fits ARK |
| --- | --- |
| One wildcard cert | Simple |
| One DNS record | Move shops without DNS TTL drama |
| One routing place | ARK Cloud registry → Traefik sync |
| App servers private | Less exposed surface |

### Model B — DNS per shop (avoid)

`joe.arksms.com` → distinct public IP per VPS. Works, but TTL, certs-per-server, more exposure. **Not the v1 path.**

### Traffic path (start here): reverse proxy

```text
Client ↔ Traefik ↔ App VPS
```

Yes — **traffic runs through the edge**. That is intentional for v1. You are not “passing the baton” to a direct client↔app connection yet.

| Model | Behavior | When |
| --- | --- | --- |
| **A — Reverse proxy** | Request and response via Traefik | **Start here** — dozens to hundreds of shops; bottleneck will be app/DB first |
| **B — Smart edge** | Edge decides; client talks more directly to destination | Later — more DNS/cert/migration complexity |

When one edge saturates, scale **edges**, not Shop URLs:

```text
Internet → LB → Edge A / Edge B / Edge C → clusters / dedicated
```

Deployment Authority unchanged: still Shop → Deployment → Routing Target → Ingress Endpoint.

---

## Coolify VPS as front door

Yes: the Coolify VPS (or a dedicated edge VPS next to it) can terminate `*.arksms.com` and Traefik-forward by hostname.

```text
Provision Shop
  → Deployment Profile
  → Coolify API (deploy)
  → Register Routing Target + Ingress Endpoint (ARK Cloud)
  → Edge reload (Traefik)
```

Edge never provisions. Coolify never routes (as authority).

Every deployment exposes the same shape to Traefik (e.g. HTTP `:80`). Shared vs dedicated looks identical at the edge.

---

## Stancl on every deployment (locked)

Keep Stancl on shared **and** enterprise/dedicated.

| Deployment | Stancl |
| --- | --- |
| Shared Cluster | Many Shops |
| Enterprise VPS | **One** Shop — still Stancl |

Resolution is hostname → Shop. One tenant is trivial and cheap vs the rest of a Laravel request.

**Do not disable Stancl on standalone VPSs.** That creates two boot paths, two auth stacks, two test matrices, and `if (enterprise)` forever. Optimize for **one application** unless profiling proves tenant resolution is a bottleneck (it won’t be first).

```text
tenant7.arksms.com → Traefik → Cluster B → Laravel+Stancl → Shop 7
tenant3.arksms.com → Traefik → Enterprise VPS → Laravel+Stancl → Shop 3
```

Shop count is a **Deployment characteristic**, not an application fork.

---

## Frozen architecture diagram

```text
Shop (Authority)
        │
        ▼
Deployment (Authority)
        │
        ├── Profile
        ├── Routing Target
        └── Ingress Endpoint
                │
                ▼
        ARK Edge (Traefik)     ← optional Cloudflare in front later
                │
        ┌───────┴────────┐
        ▼                ▼
 Shared Cluster     Enterprise VPS
        │                │
 Laravel + Stancl   Laravel + Stancl
   Many Shops          One Shop
        │                │
     Docker             Docker
```

---

## Regions (locked)

Do **not** put regions in public hostnames. Forever:

```text
{shop}.arksms.com
```

Edge / Routing Target decide geography.

---

## Parallel evolution (protect Demo Auto Repair)

**Never pioneer Stancl on Demo Auto Repair production.**

| Phase | Action |
| --- | --- |
| **1 Today** | Demo Auto Repair single-tenant on current VPS — feature work OK; no Stancl conversion |
| **2** | Shared Cluster A + ARK Edge — prove provision with e.g. `testgarage.arksms.com` |
| **3** | `autorepairkeeper.com` trials → Shared Cluster A; Demo Auto Repair untouched |
| **4** | After real shops prove the model → migrate Demo Auto Repair as a routine workflow |

---

## Relation to Shop Status

| Shop.status | Edge |
| --- | --- |
| PendingProvision | No published route |
| Provisioning | Target coming up; not live until Active |
| Active | Operations Domain → Ingress Endpoint published |
| Maintenance / Suspended | Policy responses at edge or app; infra may remain |
| Archived | Route removed |

Active + Failed deployment health → repair Deployment, do not invent Shop status.

---

## Explicit non-goals (v1)

- Cloudflare as required routing brain  
- DNS-per-shop Model B as default  
- Smart-edge / direct-to-VPS as day-one  
- Disabling Stancl on enterprise  
- Features freeze (still next on product board)  
- Exact Traefik file format / Coolify API payloads  

---

## Freeze order

| # | Authority | Status |
| --- | --- | --- |
| 1 | [Domain Contract v1](domain-contract-v1.md) | ✅ |
| 2 | [Shop Authority v1](shop-authority-v1.md) | ✅ |
| 3 | [Shop Status Authority v1](shop-status-authority-v1.md) | ✅ |
| 4 | Features / Entitlements | **Next** |
| 5 | **Deployment Authority v1** (this file) | ✅ |
| 6 | Provisioning Pipeline | Later |
| 7 | Billing | Later |
| 8 | Self-service onboarding | Later |
