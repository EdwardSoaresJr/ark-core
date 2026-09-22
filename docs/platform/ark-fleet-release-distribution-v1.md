# ARK Fleet — release distribution v1

**Status:** Inventory corrected · Fleet deploy **disabled**  
**Operator contract:** [RELEASE_DISTRIBUTION.md](../engineering/RELEASE_DISTRIBUTION.md) · [`ops/releases/distribution.yaml`](../../ops/releases/distribution.yaml)

Platform will eventually distribute approved Core releases to hosted ARK Boxes, show what each box is actually running, and roll back a bad image without touching shop data. That work is not enabled yet.

This document records the live inventory, ownership, and the observation-first sequence. It does not authorize deploys.

## Ownership

| Layer | Owns | Does not own |
| --- | --- | --- |
| Core | Shop OS; reports running commit/digest/health | Deploying other boxes; Coolify credentials |
| Platform | Hosted box registry; desired vs actual; later orchestration, audit, Admin fleet UI | Shop databases and secrets |
| Actuator | Whatever that box actually uses (Compose, Coolify, or other) | Product policy |

Self-hosted installations stay opt-in. Platform must not assume it can update them.

Core, Platform, Foundry, and Companion releases stay separate. A Core release does not redeploy Platform unless a future manifest names that dependency.

## Verified inventory (2026-09-21)

### Local Herd

Verification only. Recent local URL: `https://app.lugsnplugs.test`. Not a hosted box.

### Demo

- URL: `https://demo.arksms.com`
- Host: `104.238.144.183`
- Mechanism: **Docker Compose** at `/opt/ark`
- Files: `docker-compose.yml`, `docker-compose.vultr.yml`, `docker-compose.image.yml`, `docker-compose.demo.yml`
- Project: `ark`
- Service / container: `app` / `ark-app-1`
- Image pin: `/opt/ark/docker-compose.image.yml`
- Coolify application: **none** — do not invent one

### LNP Production

- URL: `https://lugsnplugs.arksms.com`
- Documented live host: `149.28.249.13`
- Live container: `b38otdn2epypspy0jadbgfl0-core`
- Observed ship method on that host: pin compose image, recreate `core` only
- Compose path on that host: `/data/coolify/services/waqkg4rlh7rq9pdfwpnfij8u/docker-compose.yml`

Platform adopt record (conflicts with live):

- Host `144.202.74.190`
- Coolify app `b38otdn2epypspy0jadbgfl0`
- Shop `b2f6f86f-0655-45b2-b665-e1eb9b2f1c9f`
- Installation `7d115599-cae5-4a10-a4cf-4ebe11af47ed`
- Box slug `lugsnplugs`

**LNP automation stays disabled.** Deploying the adopted Coolify app could change the wrong machine.

### Retired hostname

`demo.autorepairkeeper.com` is not a target. GoDaddy DNS for it was deleted. A leftover Traefik redirect file may still exist in-repo. Do not change DNS in follow-up work unless explicitly asked.

### Image

Live Demo and LNP were verified on:

```text
ghcr.io/edwardsoaresjr/ark-core@sha256:4056297143c78f931d7ca95478686938c20a57775075f2cd7651d4ebb5609fe7
```

This tree’s publish script still defaults to `ghcr.io/edwardsoaresjr/ark`. That name is **not** the live release target. Resolve the publish-name mismatch in a later change; do not silently retarget Fleet to `ark`.

## Desired vs actual

Platform already stores desired release on `hosted_shop_boxes.desired_state` and can record heartbeat `reported_version`. That is not enough.

- Desired = the digest Platform wants
- Actual = the digest observed on the running container, plus a signed Core heartbeat
- Unreachable is not current
- A successful deploy request is not current until observation matches

Core heartbeat today reports `dev` because version config is unset and `ark:platform-heartbeat` is not scheduled. `/app/.ark-source-commit` is written at image build and not loaded. Phase 1 must fix that before any dashboard is treated as truth.

## Adapters

| Target | Adapter | Deploy |
| --- | --- | --- |
| Local Herd | Herd checkout | Never |
| Demo | Docker Compose | Disabled until observation + rollback path |
| LNP | Pending verification | **Disabled** until 144 vs 149 reconcile |
| Future hosted boxes | Verified per box | Disabled until that box is observed |

There is no default Coolify driver for every box.

## States (when observation exists)

`unknown` · `current` · `behind` · `deploying` · `failed` · `unreachable` · `rollback-blocked`

Failed batches must not advance. Unreachable stays pending.

## Rollback (design only)

Keep the previous immutable image digest. Application rollback is allowed only when the older image is compatible with the current schema. Never automatically roll back a database. Never roll back another shop’s data. Do not offer one-click LNP or Demo rollback until backups and compatibility are recorded.

## Platform reuse (later, in `ark-platform`)

Reuse `HostedShopBox`, `ProvisionedCoreHost`, `HostedSystemDesiredState`, `HostedSystemSyncProjection`, `hosting:adopt-system`, `BoxHealth`, and the Systems list.

Do not:

- Create a second fleet registry
- `getApplication` Demo (it is not Coolify)
- Coolify Deploy `b38ot` while live Core is on 149
- Adopt Demo with invented Coolify IDs
- Re-adopt LNP to “fix” the IP
- Treat slice 4.4C as open

Compose-hosted boxes need host/Compose observation, not a fake Coolify row.

## Sequence

| Phase | Work | Deploy |
| --- | --- | --- |
| **0** | This inventory and operator contract | Off |
| **1** | Heartbeat reports commit/digest; schedule check-in; persist reported vs observed | Off |
| **1b** | Read-only Demo Compose inspect; read-only LNP 144 vs 149 reconcile | Off |
| **2** | Fleet dashboard shows actual running releases | Off |
| **3** | Enable deploy only for a box with verified adapter, observation, and rollback (Demo Compose first; LNP last) | Per box |
| **4** | Staged one / selected / pilot / fleet + rollback eligibility | Explicit approval |

## Unknown

- Whether `144.202.74.190` is leftover Coolify metadata or a dangerous wrong target
- LNP actuator until live host, container, digest, compose project, and ownership agree
- Whether installation `c9f3315a-8501-48dd-b228-b19aba78d0de` is live Demo
- Demo Vultr instance/plan ids
- Publish image name `ark` vs live `ark-core`
- Managed backups (not available)

## Must not (this phase and until observation)

- Enable Fleet deploy
- Change DNS
- Mutate production or Demo
- Invent Coolify IDs
- Copy production data into Demo or local
