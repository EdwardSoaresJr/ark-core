# Demo reporting-only release candidate

**Status:** Prepared · not published · not deployed  
**Fleet deploy:** disabled  
**Target:** Demo Compose `104.238.144.183` (`https://demo.arksms.com`) only  
**Not targets:** LNP `149.28.249.13`, adopted host `144.202.74.190`, `demo.autorepairkeeper.com`

This is the operator checklist for the first Fleet exercise: identify, publish, observe, and verify one known Core image. It does not authorize the recreate.

## Release candidate (Core)

| Item | Value |
| --- | --- |
| Branch | `fleet/phase-1-observation` |
| Source commit | `a42b5c6408a0f1590e8e68eefe422e78a575ae0e` |
| Laravel in that commit | **13.29.0** (not 13.32) |
| Heartbeat | `ark:platform-heartbeat` every five minutes (`*/5 * * * *`) |
| Commit metadata | `/app/.ark-source-commit` from `GIT_SHA`; file preferred over `APP_COMMIT` |
| Digest validation | `ARK_IMAGE_DIGEST` must contain `sha256:` + 64 hex; git SHAs rejected |
| GHCR digest | **not published** — recorded only after an approved publish |
| Image name | `ghcr.io/edwardsoaresjr/ark-core` |

Uncommitted call-queue, QZ, payments, and Laravel 13.32 work are **not** in this commit. The publish script will refuse the current dirty worktree until that work is stashed or left uncommitted off the publish checkout.

Live Demo today: Laravel **13.29.0**, image `ghcr.io/edwardsoaresjr/ark-core@sha256:4056297143c78f931d7ca95478686938c20a57775075f2cd7651d4ebb5609fe7`, no `/app/.ark-source-commit`, no `installation_uuid`, Platform unpaired. `/up` returns 200.

## Platform reporting release (required first)

Production Platform (`origin/main`, `https://cloud.arksms.com` `/up` 200) accepts heartbeat `core_version`, `release`, `commit`, and `php_version`. It persists **version, release, and commit only**. It does not persist Laravel or image digest. The Systems list does not show short commit. `hosting:register-compose-box` is not on production.

Local Platform `fleet/phase-1-observation` is **3 commits ahead of `origin/main`, unpushed:**

| Commit | Why it is required |
| --- | --- |
| `9629f99` | Persist Laravel, PHP, image digest (migration) |
| `136272d` | Show version · short commit on Systems |
| `325dc92` | Compose register without Coolify; `deploy_verified` needs digest + `/up` |

**Approval gate:** push/merge that Platform branch, deploy Platform to `cloud.arksms.com`, run migrations. Confirm the live Coolify application before any deploy (Stage 1 recorded `8cxd2p0seg3axt5sht4pwjof`; later fleet notes used `2chd519pq55trejg7y7msgl4`). Do not invent a Coolify id for Demo. Do not Coolify Deploy LNP.

Without this Platform release, Demo can still pair and check in a commit, but Fleet will not show framework version or digest, and Compose registration cannot run.

## Recovery (keep visible)

| Path | Result |
| --- | --- |
| Demo SQL | gzip-ok at `/var/backups/ark-box/20260922T032306Z`. Restore previously tested on the box. |
| Demo file volumes | Archive present (`storage.tar.gz`, 770175 bytes, 25 files). Isolated extract into throwaway Docker volumes succeeded and was repeatable (same file-list hash). **Live Demo volume restore is still uncertified.** |
| Backup vs live image | Stamp records `sha256:a258814a…`. Live pin is `sha256:40562971…`. Not a backup of the current running image. Take a fresh stamp after approval, before recreate. |
| LNP stamps | SQL + compose only. File volumes are not in those stamps. Not a certified full recovery path. |

Isolated restore did not stop Demo, did not write Demo volumes, and removed the throwaway volumes.

## Identity (prepared, not written)

| Item | Value |
| --- | --- |
| UUID | `5dba0d3f-fbbd-4c45-8b2d-2e0ea550d7b6` |
| Status | assigned, not written |
| Pairing | not started (`platform_status` empty) |

After the reporting image is running (storage volume keeps the file across app recreate):

```bash
docker exec ark-app-1 php artisan ark:installation-identity write --uuid=5dba0d3f-fbbd-4c45-8b2d-2e0ea550d7b6
docker exec ark-app-1 php artisan ark:installation-identity show
```

Pair with existing `ark:platform-pair start` / `claim` against `https://cloud.arksms.com`. Then on Platform:

```bash
php artisan hosting:register-compose-box \
  --label=ark-demo-compose \
  --ipv4=104.238.144.183 \
  --slug=demo \
  --name=Demo \
  --installation=5dba0d3f-fbbd-4c45-8b2d-2e0ea550d7b6 \
  --compose-root=/opt/ark \
  --url=https://demo.arksms.com \
  --release='ghcr.io/edwardsoaresjr/ark-core@sha256:<PUBLISHED_DIGEST>'
```

Do not pass a Coolify application UUID.

## Publish (after Platform, still not a Demo recreate)

Working tree must be clean. Checkout `a42b5c6408a0f1590e8e68eefe422e78a575ae0e`.

```bash
SOURCE_COMMIT=a42b5c6408a0f1590e8e68eefe422e78a575ae0e \
  ./infra/build-runner/mac/publish-ghcr-ark.sh
```

Record the digest from `docker buildx imagetools inspect ghcr.io/edwardsoaresjr/ark-core:a42b5c6408a0f1590e8e68eefe422e78a575ae0e`. Pin by digest, not by tag.

## App-only recreate (final approval)

On `104.238.144.183` only. MySQL, Redis, Caddy, Foundry stay up.

```bash
cd /opt/ark
cp docker-compose.image.yml docker-compose.image.yml.bak-before-reporting
# set services.app.image to ghcr.io/edwardsoaresjr/ark-core@sha256:<PUBLISHED_DIGEST>
docker compose \
  -f docker-compose.yml \
  -f docker-compose.vultr.yml \
  -f docker-compose.image.yml \
  -f docker-compose.demo.yml \
  pull app
docker compose \
  -f docker-compose.yml \
  -f docker-compose.vultr.yml \
  -f docker-compose.image.yml \
  -f docker-compose.demo.yml \
  up -d --no-deps --force-recreate app
```

## Independent verification (heartbeat is not enough)

```bash
curl -fsS -o /dev/null -w '%{http_code}\n' https://demo.arksms.com/up
docker inspect ark-app-1 --format '{{.Image}}'
docker image inspect "$(docker inspect -f '{{.Image}}' ark-app-1)" --format '{{index .RepoDigests 0}}'
docker exec ark-app-1 cat /app/.ark-source-commit
docker exec ark-app-1 php artisan --version
docker exec ark-app-1 php artisan schedule:list | grep platform-heartbeat
docker exec ark-app-1 php artisan ark:platform-heartbeat
```

Deploy success: observed digest equals the pin **and** `/up` is 200 **and** `.ark-source-commit` equals `a42b5c6408a0f1590e8e68eefe422e78a575ae0e`. A signed heartbeat is inventory only.

## Rollback (image only)

```bash
cd /opt/ark
# restore docker-compose.image.yml to
#   ghcr.io/edwardsoaresjr/ark-core@sha256:4056297143c78f931d7ca95478686938c20a57775075f2cd7651d4ebb5609fe7
docker compose \
  -f docker-compose.yml \
  -f docker-compose.vultr.yml \
  -f docker-compose.image.yml \
  -f docker-compose.demo.yml \
  up -d --no-deps --force-recreate app
curl -fsS -o /dev/null -w '%{http_code}\n' https://demo.arksms.com/up
```

Do not import SQL as part of image rollback. Do not restore storage onto live Demo unless that restore is separately approved.

## Approval actions (in order)

1. Stash or isolate unrelated Core dirty work so publish can run from `a42b5c64`.
2. Push/merge Platform `fleet/phase-1-observation` and deploy Platform + migrate.
3. Publish `ark-core` at `a42b5c64`; record digest.
4. Fresh Demo backup stamp (current latest is a different digest).
5. Write Demo UUID, pair, register Compose box.
6. Pin and recreate Demo `app` only.
7. Verify digest, `/up`, source commit, then heartbeat.

Stop until Edward approves each of those. Fleet automation stays off.
