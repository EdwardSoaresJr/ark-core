# ARK Complete Hosted — Hosting Model & Capacity Doctrine v1

**Status:** Accepted — dedicated-compute default (realigned 2026-09-07)  
**Supersedes:** Shared-host packing as the default Complete Hosted topology (2026-09-07 shared-compute pass remains valid as retained primitives)  
**Companions:** [fleet-provisioning-authority-v1.md](fleet-provisioning-authority-v1.md) · [shop-runtime-fleet-template-v1.md](shop-runtime-fleet-template-v1.md) · [ark-complete-hosted-storage-doctrine-v1.md](ark-complete-hosted-storage-doctrine-v1.md) · Platform `hosting:shared-compute` (fleet primitives)  
**Evidence:** LNP Public ARK cutover · Managed Host Capacity Realignment (`vhf-3c-8gb`) · Dedicated-compute realignment closeout

This document freezes the **hosting and capacity** model for ARK Complete Hosted. It does not redesign Core, Website features, or communications.

---

## 1. Canonical model (default)

```text
One Hosted shop = one dedicated managed VPS/VM.
One Shop = one Core installation (Box).
One Box = one dedicated compute boundary by default.
```

```text
ARK PLATFORM / CONTROL PLANE
        |
        +---- Shop A Managed VPS
        |       +-- ARK Core
        |       +-- Website runtime (web-only)
        |       +-- MySQL
        |       +-- Redis
        |       +-- Horizon / Reverb / scheduler
        |       +-- storage/media
        |
        +---- Shop B Managed VPS
        |       +-- same topology
        |
        +---- Shop C Managed VPS
                +-- same topology
```

ARK Core remains **single-shop / self-hostable**. Do **not** introduce application-level multi-tenancy (`stancl/tenancy` or equivalent) into Core.

Dedicated compute is the **default Hosted product**, not a premium upgrade.

Quantity of hosts is an **automation / fleet-management** problem. Cross-shop co-tenancy on one machine is **not** the normal failure domain.

---

## 2. Priority order

1. Customer experience — ARK always feeling fast  
2. Reliability  
3. Isolation  
4. Operational simplicity  
5. Security  
6. Recovery  
7. Support burden  
8. Healthy commercial margin  

Infrastructure savings from packing multiple paying shops onto one VPS do **not** outrank the above for Complete Hosted.

If a dedicated VPS costs roughly \$5 / \$10 / \$20 / \$40 per month depending on workload, that is normal cost-of-service.

---

## 3. Box isolation (every Hosted shop)

| Boundary | Requirement |
| --- | --- |
| Compute | Dedicated managed VPS/VM by default |
| Core runtime | Dedicated application container |
| Secrets | Unique `APP_KEY` and shop secrets |
| Identity | Installation + Platform Shop relationship |
| MySQL | Dedicated MySQL on that shop’s VPS (not shared across customers) |
| Redis | Dedicated Redis on that shop’s VPS (not shared namespaces across customers) |
| Storage | Dedicated durable storage root — Hosted: S3-compatible object storage (see storage doctrine); not years of media on VPS disk |
| Workers | Horizon / scheduler / Reverb for that Box only |
| Website | Lightweight per-shop runtime on the same VPS |
| Routing | Shop domains → that Box |
| Backup | Per-Box / per-VPS recovery identity |

**Grandfathering:** Live hosts adopted before this realignment (e.g. LNP on `144.202.74.190`) may retain pre-existing co-tenant side apps (weida, walton, BookStack) and historical MySQL/Redis layout until an explicit, approved cleanup. Do **not** migrate or rebuild production for aesthetics. Do **not** place another ARK customer on LNP’s machine because spare RAM exists.

---

## 4. Website runtime

Default Complete Hosted: **one lightweight Website runtime per shop** on that shop’s dedicated VPS.

Do **not** combine many customer Websites into one multi-shop Laravel Website runtime merely to save containers.

Prefer: nginx + PHP-FPM only. Do **not** auto-run Horizon, Core queues, Reverb, or Core scheduler on Website unless a future Website feature requires a narrowly scoped worker.

---

## 5. Capacity doctrine (per dedicated host)

Capacity answers:

> Does **this shop’s** dedicated host have adequate headroom?  
> If not → **resize this VPS** (vertical), not rebalance other shops onto it.

Capacity does **not** answer:

> How many independent paying shops can we pack here?

### Signals (host health)

- available memory · swap use / swap activity · CPU · disk free / growth  
- DB pressure · Redis pressure · queue / worker health  
- request / database latency when available · backup viability  

### Two modes

| Mode | Meaning | Typical action |
| --- | --- | --- |
| **Healthy headroom** | Shop’s host is fine | **Leave alone** |
| **Intervention / pressure** | Existing shop workload approaching unhealthy | **Recommend or perform vertical resize** |

`placement_allowed` / colocated placement on a dedicated host defaults to **false**. Spare capacity is headroom for **this** shop — not an invitation to schedule Shop B.

Do **not** treat host density as a product success metric. Do **not** require multi-Box density evidence before Hosted proceeds.

### Sizing

Do **not** canonize 8 GiB (or 8/16) as the Hosted standard because LNP temporarily runs on `vhf-3c-8gb`.

LNP’s 3/8 host is an **upper-headroom dogfood** data point. Future dedicated shops should gather **per-shop sizing** evidence (how low can a normal shop start safely; when to resize).

Machine sizing is Platform metadata/capability. Prefer safe initial headroom. Vertical resize is the normal growth response.

Do not invent permanent commercial tiers (small/normal/busy) until measurements earn them.

---

## 6. Shared-compute primitives (retained, non-default)

Platform retains:

| Primitive | Role under dedicated default |
| --- | --- |
| `register-host` | Register a managed VPS (default **dedicated**) |
| `adopt-box` | Bind Shop/Installation to host |
| `assess-capacity` | Host health + resize pressure for **this** host |
| `place-box` | **Non-default** infrastructure primitive (lab, migration, DR, enterprise, future opt-in shared tier) |
| host health / capacity state | Fleet monitoring |
| Box / installation identity | Unchanged |

`place-box` may remain for special topologies. It must **not** be the default Complete Hosted provisioning path.

Opt-in shared hosts (`host_role=shared`) may still use colocated placement gates. That is not the product default.

---

## 7. Future Hosted provisioning path

```text
new Hosted shop
    ↓
determine initial sizing (safe headroom; evidence-based over time)
    ↓
provision new VPS/VM
    ↓
register managed host (dedicated)
    ↓
deploy shop stack (Core + Website + MySQL + Redis + workers)
    ↓
adopt/register installation/Box
    ↓
health verification
    ↓
capacity monitoring
    ↓
vertical resize when necessary
```

Coolify remains acceptable as the deployment/management layer. Many independently isolated machines, managed centrally.

---

## 8. Enterprise / portability

| | Complete Hosted | Complete Enterprise |
| --- | --- | --- |
| Compute | ARK-owned/managed VPS | Customer-owned VPS/VM |
| Stack | Same Complete shop stack | Same Complete shop stack |
| Differs by | ownership · management · licensing · entitlements · support/SLA | same |

Hosted vs Enterprise must **not** require a different Core architecture. Multi-location enterprise = multiple Core installations (one per shop/location), not a multi-location tenant database inside Core.

---

## 9. Platform authority

Platform owns: managed host inventory · capacity/health · Shop↔Box↔Installation · hosting lifecycle · (eventually) create/resize/retire VPS automation.

Coolify executes deploy/runtime management. Core does not choose its host.

Default product path: **new shop → new dedicated host**. Fail closed when health is unknown or isolation/secrets cannot be established safely.

---

## 10. LNP dogfood

LugsNPlugs Production is Box #1 on its managed host — **no architectural privilege**, and **no casual rebuild**.

- Do not resize LNP solely to match doctrine aesthetics.  
- Do not start Box #2 on LNP.  
- Do not place another production Hosted shop on LNP because `assess-capacity` shows headroom.  
- Headroom on LNP means: **Great. Leave it alone.** Gather per-shop sizing evidence separately.

---

## 11. Implementation pointers

| Surface | Location |
| --- | --- |
| Capacity assessment | `App\Domains\Hosting\AssessSharedHostCapacityAction` |
| Colocated placement (non-default) | `App\Domains\Hosting\PlaceIsolatedShopBoxAction` |
| Host registration | `App\Domains\Hosting\RegisterExistingManagedHostAction` |
| Dedicated VPS provision (MVP) | `App\Domains\Hosting\ProvisionCoreHostAction` |
| Operator CLI | `php artisan hosting:shared-compute …` |
| Config | `config/ark-platform.php` → `hosting.topology` · `hosting.shared.*` (capacity thresholds) |

---

## Revision

Revise when clusters of Hosted shops prove sizing/thresholds wrong — not because one shop felt busy on a Tuesday. Shared multi-shop hosts only if measured economics clearly outweigh blast radius, noisy neighbors, isolation, debugging, recovery, and support cost.
