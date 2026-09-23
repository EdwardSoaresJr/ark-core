# ARK Fleet - release distribution v1

**Status:** Phase 1 observation · Fleet deploy **disabled**  
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

## Heartbeat on this branch

`fleet/phase-1-observation` runs `ark:platform-heartbeat` every five minutes. A one-minute schedule existed on `fleet/box-runtime-observation`; it was not merged. Image digest (`ARK_IMAGE_DIGEST`, sha256 only) and source-commit file preference from that work **are** on this branch.

Platform `BoxHealth` Delayed/Offline windows assume a five-minute interval. Keep five minutes.

Heartbeat is check-in. Deploy success is observed digest **and** `/up`. `HostedSystemSyncProjection.deploy_verified` stays false until both are supplied independently of heartbeat.

## Verified inventory (read-only 2026-09-22)

### Local Herd

Verification only. Recent local URL: `https://app.lugsnplugs.test`. Not a hosted box.

### Demo

- URL: `https://demo.arksms.com`
- Host: `104.238.144.183` (`hostname=demo`)
- Mechanism: **Docker Compose** at `/opt/ark` - container `ark-app-1` running
- Image: `ghcr.io/edwardsoaresjr/ark-core@sha256:4056297143c78f931d7ca95478686938c20a57775075f2cd7651d4ebb5609fe7`
- Coolify application: **none**
- Installation UUID **assigned, not written:** `5dba0d3f-fbbd-4c45-8b2d-2e0ea550d7b6`
- Pairing: not started. Register with Platform `hosting:register-compose-box` (no Coolify id). Write the UUID with Core `ark:installation-identity write`.
- Backup: `/var/backups/ark-box` via `/usr/local/sbin/ark-box-backup`. SQL restore tested. `storage.tar.gz` is present and `restore-box.sh` can restore it; file-volume restore has not been certified. **Not** Platform managed backup.

### LNP Production

- URL: `https://lugsnplugs.arksms.com`
- Live host: `149.28.249.13` (`ark-lugsnplugs-production`) - SSH reachable
- Container: `b38otdn2epypspy0jadbgfl0-core` running the same digest
- Compose: `/data/coolify/services/waqkg4rlh7rq9pdfwpnfij8u/docker-compose.yml` pins that digest on `core`
- Installation UUID: `7d115599-cae5-4a10-a4cf-4ebe11af47ed` (matches Platform adopt - ownership proven)
- Adopted host `144.202.74.190`: SSH timed out - **unreachable**
- Backup/rollback: [LNP_BACKUP_AND_ROLLBACK.md](../engineering/LNP_BACKUP_AND_ROLLBACK.md)

**LNP automation stays disabled.** Do not Coolify Deploy. Do not target 144. Correct IPv4 only with `hosting:reconcile-observed-host --confirm-ownership=<uuid>` after explicit approval.

### Retired hostname

`demo.autorepairkeeper.com` is not a target. GoDaddy DNS for it was deleted.

### Image

Live Demo and LNP run:

```text
ghcr.io/edwardsoaresjr/ark-core@sha256:4056297143c78f931d7ca95478686938c20a57775075f2cd7651d4ebb5609fe7
```

Publish to `ghcr.io/edwardsoaresjr/ark-core`. Those live images do not contain `/app/.ark-source-commit` or OCI revision labels. Heartbeat will report real commits only after a new image built with `GIT_SHA` is deployed.

## Desired vs actual

- Desired = the digest Platform wants
- Actual = the digest observed on the running container
- Health = `GET /up`
- Heartbeat = signed inventory check-in (commit, Laravel, PHP, optional digest)
- Unreachable is not current
- A successful deploy request is not current until digest and `/up` match

## Adapters

| Target | Adapter | Deploy |
| --- | --- | --- |
| Local Herd | Herd checkout | Never |
| Demo | Docker Compose | Disabled |
| LNP | Docker Compose on 149 | **Disabled** |
| Future hosted boxes | Verified per box | Disabled until that box is observed |

There is no default Coolify driver for every box.

## States (when observation exists)

`unknown` · `current` · `behind` · `deploying` · `failed` · `unreachable` · `rollback-blocked`

Failed batches must not advance. Unreachable stays pending.

## Rollback (design only)

Keep the previous immutable image digest. Application rollback is allowed only when the older image is compatible with the current schema. Never automatically roll back a database. Never roll back another shop’s data. Do not offer one-click LNP or Demo rollback until backups and compatibility are recorded.

LNP procedure is documented. Demo SQL restore is tested; Demo file-volume restore is uncertified.

## Platform commands (local / approved DB write only)

| Command | Job |
| --- | --- |
| `hosting:register-compose-box` | Record Demo without a Coolify application id |
| `hosting:reconcile-observed-host` | Move LNP host IPv4 after UUID ownership confirmation |

Do not:

- Create a second fleet registry
- `getApplication` Demo (it is not Coolify)
- Coolify Deploy `b38ot` while live Core is on 149
- Adopt Demo with invented Coolify IDs
- Re-adopt LNP to “fix” the IP
- Treat slice 4.4C as open

## Sequence

| Phase | Work | Deploy |
| --- | --- | --- |
| **0** | Inventory and operator contract | Off |
| **1** | Heartbeat reports commit / Laravel / PHP / digest; identity + recovery docs | Off |
| **1b** | Reporting-only Demo image; verify commit, digest, `/up` | Demo only, when approved |
| **2** | Fleet dashboard shows actual running releases | Off |
| **3** | Enable deploy only for a box with verified adapter, observation, and rollback | Per box; LNP last |
| **4** | Staged one / selected / pilot / fleet + rollback eligibility | Explicit approval |

## Unknown

- Whether mail-cert installation `c9f3315a-8501-48dd-b228-b19aba78d0de` is live Demo
- Demo Vultr instance/plan ids
- Why adopted host `144.202.74.190` still exists in Platform (unreachable from this network)
- LNP file-volume restore
- Managed backups (not available)

## Must not

- Enable Fleet deploy
- Change DNS
- Mutate production or Demo in this phase
- Invent Coolify IDs
- Copy production data into Demo or local
- Combine the first Fleet reporting image with unrelated Laravel patches
- Treat Demo Compose backups as Platform managed backup
- Treat a heartbeat as deploy success
