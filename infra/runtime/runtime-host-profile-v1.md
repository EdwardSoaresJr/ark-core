# Runtime Host Profile v1

**Status:** Accepted — frozen operational baseline for all Arkify production runtime hosts  
**Version:** 1.0 — 2026-06-15  
**Sequence:** [Doctrine](../coolify/production-runtime-host-doctrine-v1.md) → **Profile** (this file) → [Shop implementation](../coolify/demo-auto-runtime-profile.md)

Every new ARK deployment starts from this profile. Shop-specific documents record **host identity, observed metrics, and justified deviations** — not a rediscovery of these baselines.

---

## Runtime host baseline

| | |
|---|---|
| **Purpose** | Run production workloads |
| **Not** | Build workloads |

A runtime host runs pulled containers, databases, queues, realtime, proxy, backups, and monitoring. It does not compile application images.

**Default deployment architecture:** pull-only (GitHub Actions → GHCR → Coolify pull). Build-on-host requires written justification and a sunset date in the shop implementation profile.

Evidence: [Production Memory Audit](../coolify/production-memory-audit.md) · Roadmap: [Pull-Only Deployments](../coolify/pull-only-deployments.md)

---

## Docker

Runtime hosts accumulate image tags and build cache even after cutover from legacy on-host builds.

| Target | Baseline |
|--------|----------|
| Build cache on runtime host | **< 2 GB** (should trend toward **0** after pull-only) |
| Reclaimable images (`docker system df`) | **< 25%** of total image disk |
| Prune review | **Monthly** — or when reclaimable > 25% |
| Prune during deploy | **Never** |

**Monthly review commands:**

```bash
docker system df
docker image prune -f          # when reclaimable images elevated
docker builder prune -f      # only if build cache > 2 GB (legacy or regression)
```

Build cache on a pull-only host is **operational debt**, not a feature. Investigate if it grows.

---

## Memory

**Swap is a safety margin, not operating mode.**

Steady-state production should run primarily in RAM. Persistent swap use under normal traffic indicates undersizing or mis-tuning — not a reason to accept swap as baseline.

**Investigate when:**

| Signal | Likely cause (check in order) |
|--------|-------------------------------|
| Sustained swap growth (24h+ steady state) | Runtime undersize, FPM/Horizon headroom, MySQL reservation |
| OOM events (`dmesg`, container restarts) | cgroup limits vs actual RSS; deploy overlap |
| Deploy-induced memory pressure | **Build on host** — fix deployment architecture before shrinking MySQL or FPM |

Do not treat swap consumed during **on-host builds** as application memory demand. That is build authority leaking into runtime.

---

## PHP-FPM

**Baseline for 2–4 GB runtime hosts** (single ARKv2 app container):

| Setting | Value |
|---------|-------|
| `pm` | `dynamic` |
| `pm.max_children` | **3** |
| `pm.start_servers` | **1** |
| `pm.min_spare_servers` | **1** |
| `pm.max_spare_servers` | **2** |
| `pm.max_requests` | **500** |
| PHP `memory_limit` | **128M** (per worker) |

Implement via `infra/coolify/php-fpm-www.conf` COPY in Dockerfile when shop profile approves application.

**Upsize hosts (8 GB+):** Re-measure from `docker stats` and concurrent advisor count before raising `max_children`. Do not copy generic hosting defaults.

---

## MySQL

**Doctrine (non-negotiable):**

> Do not shrink MySQL to compensate for deployment architecture.

MySQL cgroup limits reflect **observed runtime RSS**, shared tenants on the instance, backup windows, and growth — not RAM freed for `npm ci` or BuildKit.

If MySQL OOMs during deploy:

1. Suspect on-host build or container-recreate overlap first.
2. Fix pull-only deployment path.
3. Re-measure steady-state RSS before changing MySQL limits.

Shop-specific `mem_limit`, `memswap_limit`, and CPU values live in the **shop implementation profile**, not here.

---

## Redis

**Keep Redis small.**

Memory authority belongs to **MySQL** and the **application runtime** (FPM, Horizon, Reverb). Redis holds queue metadata, cache, and Horizon state — not bulk data.

| Baseline | |
|----------|---|
| Custom memory limits | Only when observed RSS or eviction metrics justify |
| Default on new deployments | Coolify defaults; monitor via `docker stats` |
| Policy | Do not pre-provision Redis RAM "for growth" at the expense of MySQL |

---

## Horizon

**Scale workers from observed queue pressure.**

| Baseline | |
|----------|---|
| `maxProcesses` | Start from **1**; raise only when queue wait metrics or job backlog justify |
| Config ceiling | `config/horizon.php` production `maxProcesses: 4` is an **upper bound**, not a target |
| Policy | Do not pre-allocate workers "just in case" |
| Worker `memory` | 128 MB default; master `memory_limit` 64 MB |

Measure with Horizon dashboard and `horizon:status` before increasing processes.

---

## Swappiness

Host-level `vm.swappiness` is documented here; **not applied automatically** on existing hosts.

| Setting | Value |
|---------|-------|
| Linux default (observed) | **60** |
| Candidate runtime profile | **10** |
| Adoption | Requires **observation period** (≥ 1 week steady state) after pull-only cutover |
| Persist | `/etc/sysctl.d/99-ark.conf` when approved per shop |

**Do not lower swappiness while on-host builds continue** — build spikes still need swap headroom on small hosts.

MySQL container `mem_swappiness: 10` in Coolify compose is complementary; host sysctl still matters on some kernels.

---

## Deployment authority (Arkify default)

**New production deployments default to pull-only architecture.**

| Default | Exception (requires justification) |
|---------|-------------------------------------|
| Coolify app source: **Docker image** from GHCR | Git repo + Dockerfile build on shop host |
| CI builds on GitHub Actions | Ad-hoc `docker build` on runtime host |
| Deploy = pull + recreate | Deploy = compile + build + recreate |

Exceptions must be recorded in the shop implementation profile with owner, reason, and sunset date. Build-on-host is **legacy**, not a template for Shop In A Box.

**Build authority:** `.github/workflows/docker-publish.yml` → `ghcr.io/<org>/arksmsv2:{main,sha}`

**Runtime authority:** Coolify env, bind mounts, entrypoint post-deploy — secrets never baked into image.

---

## Shop implementation pattern

Each hosted shop maintains an implementation document:

```
infra/coolify/<shop>-runtime-profile.md
```

| Layer | File | Contains |
|-------|------|----------|
| Doctrine | `production-runtime-host-doctrine-v1.md` | Why runtime ≠ build |
| Profile | `runtime-host-profile-v1.md` (this file) | Frozen baselines every shop inherits |
| Implementation | `demo-auto-runtime-profile.md` | IP, containers, observed RSS, phase, deviations |

**Deviation examples:** Demo Auto Repair still runs legacy on-host build until P2; FPM baseline not yet copied into Dockerfile; swappiness still 60.

When hardware changes, update the **shop implementation** — not this profile unless the fleet baseline itself changes.

---

## Related docs

- [Production Runtime Host Doctrine v1](../coolify/production-runtime-host-doctrine-v1.md)
- [Demo Auto Repair Runtime Profile](../coolify/demo-auto-runtime-profile.md) — first shop implementation
- [Pull-Only Deployments](../coolify/pull-only-deployments.md) — P0–P2 cutover
- [DEPLOYMENT.md](../coolify/DEPLOYMENT.md) — Demo Auto Repair Coolify layout
