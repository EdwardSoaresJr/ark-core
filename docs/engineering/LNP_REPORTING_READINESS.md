# LNP reporting readiness

**Status:** Prepared · not deployed  
**Fleet deploy:** disabled  
**Not this gate:** Coolify Deploy, DNS, Demo recreate, Platform image rebuild

Gate 2 Demo reporting is closed and independently verified. This file is the production gate checklist. Do not recreate `core` because this file exists.

## Live shop (unchanged)

| Item | Value |
| --- | --- |
| URL | `https://lugsnplugs.arksms.com` |
| `/up` | 200 |
| Host | `149.28.249.13` (`ark-lugsnplugs-production`) |
| Container | `b38otdn2epypspy0jadbgfl0-core` |
| Image | `ghcr.io/edwardsoaresjr/ark-core@sha256:4056297143c78f931d7ca95478686938c20a57775075f2cd7651d4ebb5609fe7` |
| Laravel | 13.29.0 |
| PHP | 8.4.25 |
| Source commit file | missing |
| Installation UUID | `7d115599-cae5-4a10-a4cf-4ebe11af47ed` (matches Platform) |
| Pairing | connected |

## Platform inventory (corrected)

`hosting:reconcile-observed-host` wrote the managed host IPv4 from `144.202.74.190` to `149.28.249.13`. Previous IPv4 is kept on the host payload. No Coolify, Vultr, or DNS calls. `http_requests_sent=0`.

| Item | Value |
| --- | --- |
| Box slug | `lugsnplugs` |
| Coolify application UUID | `b38otdn2epypspy0jadbgfl0` (container name prefix; not the live actuator) |
| Host label | `ark-lugsnplugs-production` |
| Public IPv4 | `149.28.249.13` |
| Previous IPv4 | `144.202.74.190` |
| Last heartbeat | `2026-09-20 14:10:02` (`0d3b3e8` / `dev`) |
| Desired release | `ghcr.io/edwardsoaresjr/ark-core@sha256:f4abe244858262dac653d821cd2c6b947ff4931e6766153fcfa29695f40e2888` |
| Previous desired | `ghcr.io/edwardsoaresjr/arksmsv2:production` (kept on the box payload) |

Desired is the Demo-verified reporting pin. `pending_change: true` is **not** permission to recreate `core`. Do not recreate until desired and the running digest are the same pin, after explicit LNP approval.

Do not treat `144.202.74.190` as live. Do not re-adopt the box to “fix” the IP.

## Production deployment method

Observed on the running host. **Compose recreate of `core` only.**

```text
compose project   waqkg4rlh7rq9pdfwpnfij8u
compose file      /data/coolify/services/waqkg4rlh7rq9pdfwpnfij8u/docker-compose.yml
service           core
pull_policy       never
env_file          /data/coolify/applications/b38otdn2epypspy0jadbgfl0/.env
```

The running container has empty Coolify labels. Do not Coolify Deploy. Pin the digest in that compose file, then recreate **only** `core`. Leave mysql, redis, nginx, Foundry, and Traefik alone.

Image rollback uses the same path with the previous digest. `pull_policy: never` means the digest must already be on the host (or loaded from the rollback tar).

## Reporting gaps on the current image

`ark:platform-heartbeat` exists, but `schedule:list` does not run it. That is why Platform still shows the 20 Sep check-in.

A later reporting-only LNP image would need, at minimum:

- scheduled `ark:platform-heartbeat` every five minutes
- `/app/.ark-source-commit`
- Laravel / PHP / image digest on the heartbeat

That image is **not** this gate. The pin already exists: Demo’s `a42b5c64` / `sha256:f4abe244…`. Platform desired now names that digest. Recreating LNP onto it still needs its own production approval.

## Authenticated timings (current image `40562971`)

Official pre-release baseline: [LNP_PERFORMANCE_BASELINE.md](LNP_PERFORMANCE_BASELINE.md).

Warmed medians: Attention kernel **823 ms** (234 KB); RO `#1747` kernel **992 ms** (698 KB). Public HTTPS TTFB 867 ms / 1,210 ms.

RO show time is mostly **view render + lazy SQL + schema probes**, not the controller. Breakdown and repeat steps are in that file. Repeat after the reporting recreate, then again after a separately approved poller change.

## Backup stamp (taken on the current image)

`/data/ark-shared/backups/lnp-reporting-readiness-20260922T050035Z`

| Artifact | Bytes | Check |
| --- | --- | --- |
| `arkv2.sql.gz` | 33191265 | gzip-ok |
| `storage-app.tar.gz` | 93426910 | gzip-ok; 495 files extracted to `/tmp` |
| `core-image-40562971.tar.gz` | 771659187 | current running digest |
| `docker-compose.yml` | 15K | pin copy |

`lnp-reporting-readiness-latest` points at this stamp.

SQL restore overwrites shop data — separate approval, never bundled with an image recreate. Isolated `storage-app` extract succeeded. **Live bind-mount restore is not certified.** Same qualification as Demo.

Application files live at `/data/ark-shared/storage/app` (bind-mounted). Framework and logs are not in this archive.

## Later production gate (not authorized)

1. Keep this backup stamp and the `40562971` image tar on the host.
2. Do not publish a new image. Reuse `sha256:f4abe244…` / `a42b5c64`.
3. On approval, pin that digest in the compose file, recreate **`core` only**, independently inspect RepoDigest + `/up` + source commit. Desired already names that digest; running Core must match it after recreate.
4. Confirm Platform heartbeat versions. Heartbeat is still not deploy proof.
5. Stop if identity, backup, image, health, or version checks fail.

Do not Coolify Deploy. Do not include Laravel 13.32, the poller fix, RO request-time optimization, or dirty worktree work unless that production gate names them.
