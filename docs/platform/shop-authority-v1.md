# Shop Authority v1

**Status:** Locked — authority before onboarding / Stancl / billing UI  
**Date:** 2026-07-19  
**Companions:** [domain-contract-v1.md](domain-contract-v1.md) · [shop-status-authority-v1.md](shop-status-authority-v1.md) · [deployment-authority-v1.md](deployment-authority-v1.md) · [shop-identity-v1.md](shop-identity-v1.md) · [shop-registry-implementation-design-v1.md](../deployment/shop-registry-implementation-design-v1.md)

## Principle

A **Shop** is the unit that ARK provisions, deploys, bills, brands, and operates.

Onboarding, deployment, billing, provisioning, and Stancl are **projections of Shop authority** — not sources of fields. Forms collect facts needed to create a Shop; they do not invent Shop shape.

```text
Shop (authority)
  ↓
Domains · Deployment Profile · Subscription · Branding · Features · Locations
  ↓
Infrastructure · Stancl · DNS · Coolify · Vultr  (projections / adapters)
```

This is the transition from “multi-tenancy” to **Shop provisioning**. Stancl identifies a Shop. It does not define one.

---

## Shop tree

```text
Shop
├── Identity
├── Operations Domain
├── Public Domain
├── Preview Domain
├── Deployment Profile
├── Subscription
├── Branding
├── Features
├── Locations
└── Infrastructure          ← projection, not authority
```

---

## 1. Identity (authority)

These do not change because infrastructure changed.

| Field | Role |
| --- | --- |
| `id` | Opaque Shop id (fleet / OIDC `shop_id`) — immutable |
| `slug` | Human handle + Operations Domain stem — **immutable after provisioning** |
| `legal_name` | Legal entity |
| `display_name` | Operator-facing name |
| `status` | Shop lifecycle — see [shop-status-authority-v1.md](shop-status-authority-v1.md) |
| `created_at` | Created |

Example:

| Field | Value |
| --- | --- |
| slug | `demo-auto` |
| display_name | Demo Auto Repair |
| legal_name | Demo Auto Repair LLC |

### Slug immutability (locked)

After provisioning, **`slug` never changes**.

```text
demo-auto  →  always  demo.arksms.example
```

Rebrand later by changing **display name**, **Public Domain**, logo, website — not the Operations Domain.

That protects bookmarks, mobile configs, API endpoints, integrations, and login habits.

**Supersedes** [shop-registry-implementation-design-v1.md](../deployment/shop-registry-implementation-design-v1.md) §3.2 (“slug may change with care”). Slug remains a human handle, but post-provision it is **immutable**. Reserve carefully; do not treat rename as a product feature.

---

## 2. Domains (business concepts — not DNS records)

Per [domain-contract-v1.md](domain-contract-v1.md):

| Concept | Example | Audience |
| --- | --- | --- |
| **Operations Domain** | `demo.arksms.example` | Staff |
| **Public Domain** | `demo-auto.example` | Customers (optional until connected) |
| **Preview Domain** | `demo-preview.example` | Temporary public (trial) |

DNS records, certificates, and Stancl domain rows are **projections** of these concepts.

Product language: **Public Domain** (not “alias”). Stancl may store it as a domain alias; ARK does not speak that way outside the adapter.

---

## 3. Deployment Profile

Where infrastructure **intent** begins. Frozen values:

| Profile | Meaning |
| --- | --- |
| `shared` | Shared cluster |
| `dedicated` | Dedicated cluster |

**Enterprise is a Subscription / pricing plan — not a DeploymentProfile.**  
Do not couple billing vocabulary to infrastructure enums.

Not VPS. Not Docker. Not Vultr. Those belong under Infrastructure / Cluster.

**Promotion** (Shared → Dedicated) is a Cluster Assignment decision (+ later infra job). Shop identity and Operations Domain stay put.

See [cluster-assignment-authority-v1.md](cluster-assignment-authority-v1.md).

---

## 4. Subscription

Commercial eligibility — **orthogonal to Deployment Profile**.

| Plan (v1 vocabulary) |
| --- |
| Trial |
| Starter |
| Professional |
| Enterprise |

Valid combination example: **Professional** on **Shared** during early life. Billing must not imply dedicated hardware.

Stripe customer/subscription ids are projection links — never Shop identity ([registry design](../deployment/shop-registry-implementation-design-v1.md)).

---

## 5. Branding

Shop-facing brand facts (logo, colors, public copy pointers). Mutable. Does not alter `slug` or Operations Domain.

---

## 6. Features (entitlements)

Capability gates on the Shop, not infrastructure:

- Voice  
- Messenger  
- Website  
- Multi-location  
- AI  
- Day Review  
- (extend only when an entitlement is real)

Distinct from fleet **ProductAccess** (which ecosystem products are provisioned: ARK V2, ARKademy, …). Features answer “what may this Shop use?” ProductAccess answers “which products exist for this Shop in the fleet.”

---

## 7. Locations

Relationship only:

```text
Shop  →  1..N Locations
```

Locations are operational places (Front Counter, Bay 3, …) — not deployment hosts. Station doctrine remains under Operations; Locations hang off Shop for multi-site shops.

---

## 8. Infrastructure (projection — not Shop)

```text
Deployment Profile
  ↓
Deployment
  ↓
Server · Database · Storage · Health
```

Coolify app UUID, server IP, Vultr instance, MySQL schema name, certificate ids — all replaceable. Swapping Vultr for another host **must not rewrite Shop identity**.

Stancl tenant id / domain rows are the **infrastructure adapter** for multi-Shop routing — not product vocabulary.

---

## Provisioning spine (projection of Shop create)

No onboarding screen owns these steps. Onboarding only collects enough to create a Shop; the platform performs:

```text
Create Shop
  → Reserve Slug
  → Generate Domains          (Operations + Preview; Public optional)
  → Assign Deployment Profile
  → Provision Infrastructure
  → Configure Stancl          (adapter)
  → Create First User
  → Ready
```

---

## Authority vs projection (quick test)

| Question | Layer |
| --- | --- |
| Which repair business is this? | **Shop identity** |
| What hostname do staff use? | **Operations Domain** (Shop) |
| What hostname do customers use? | **Public / Preview Domain** (Shop) |
| Shared or dedicated? | **Deployment Profile** (Shop intent) |
| Which plan are they on? | **Subscription** (Shop commercial) |
| Which capabilities are enabled? | **Features** (Shop entitlements) |
| Which VPS / Coolify app? | **Infrastructure** (projection) |
| Stancl tenant UUID? | **Adapter** (projection of Shop) |

If a field exists only because a form, a provider, or Stancl needed it — it is not Shop authority until it answers a Shop question above.

---

## Explicit non-goals (v1)

- Onboarding wizard field design  
- SQL schema / migrations  
- Stripe webhook shape  
- Coolify / Vultr API details  
- Collapsing Shop + Deployment into one mutable “environment” row  
- Renaming `slug` as a supported product operation after provision  

---

## Relation to prior docs

| Doc | Relationship |
| --- | --- |
| [domain-contract-v1.md](domain-contract-v1.md) | Hostname audiences; Operations / Public / Preview |
| [shop-identity-v1.md](shop-identity-v1.md) | HTTP vs SIP; `SHOP_BASE_URL` on Operations Domain |
| [shop-registry-implementation-design-v1.md](../deployment/shop-registry-implementation-design-v1.md) | Control-plane entity split (Subscription, Deployment, Domain rows). Shop Authority v1 is the **product authority spine**; registry design remains the fleet store sketch. **Slug immutability after provision** supersedes registry §3.2 mutability. |
