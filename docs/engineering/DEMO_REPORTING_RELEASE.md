# Demo reporting-only release

**Status:** Closed · verified  
**Target:** Demo Compose `104.238.144.183` (`https://demo.arksms.com`) only  
**Not targets:** LNP `149.28.249.13`, `144.202.74.190`, `demo.autorepairkeeper.com`

Do not republish this image. Do not recreate Demo `app`. Platform observe is already live (`sha256:23d8e3d4…`). LNP is a separate production gate: [LNP_REPORTING_READINESS.md](LNP_REPORTING_READINESS.md).

## Live Core (Demo)

| Item | Value |
| --- | --- |
| Source commit | `a42b5c6408a0f1590e8e68eefe422e78a575ae0e` (`/app/.ark-source-commit`) |
| OCI revision | `a42b5c6408a0f1590e8e68eefe422e78a575ae0e` |
| Image pin | `ghcr.io/edwardsoaresjr/ark-core@sha256:f4abe244858262dac653d821cd2c6b947ff4931e6766153fcfa29695f40e2888` |
| linux/amd64 manifest | `sha256:ccf4e7dd343a932fda44d1352b9eeaef48e904924d4aa985af0e5b421f38a6a3` |
| `ARK_IMAGE_DIGEST` | `sha256:f4abe244858262dac653d821cd2c6b947ff4931e6766153fcfa29695f40e2888` |
| Laravel | **13.29.0** (not 13.32) |
| PHP | 8.4.25 |
| Heartbeat | `ark:platform-heartbeat` every five minutes |
| `/up` | 200 |
| Container | `ark-app-1` recreated `2026-09-22T04:53:42Z` |
| Poller `cf169e76` | not in this image (`x-init="init()"` still present) |

MySQL, Redis, Caddy, and Foundry were not recreated.

## Platform (observe image live — do not redeploy for this Core record)

`https://cloud.arksms.com` (`216.128.139.225`, `/up` 200). Coolify application **`2chd519pq55trejg7y7msgl4`**. Image `0d5f640` / `sha256:23d8e3d4ade3bc5c2877edacd6dd19a8cf4fe2ddaab3468976b6329ddb8e8acd`. Rollback `sha256:c5a48ae0834d2678ef65bcd034f00249e2906e10b8f24e3dc688c6ec8a9e5242`. Laravel 13.29.0.

## Identity (written)

| Item | Value |
| --- | --- |
| UUID | `5dba0d3f-fbbd-4c45-8b2d-2e0ea550d7b6` |
| Pairing | connected (`shop_public_id` `cd95693f-fea7-4c90-81f5-49f691bd4d79`) |
| Platform box | slug `demo`, Coolify application none, host `ark-demo-compose` / `104.238.144.183` |

Heartbeat recorded on Platform at `2026-09-22T04:55:15Z`: commit `a42b5c64…`, Laravel 13.29.0, PHP 8.4.25, digest `sha256:f4abe244…`. That is inventory only.

## Systems projection

Heartbeat recorded Laravel 13.29.0, PHP 8.4.25, commit `a42b5c64…`, and digest `sha256:f4abe244…`. That is inventory. Independent RepoDigest + `/up` 200 set `deploy_verified`. Systems **Verified**. Do not repeat Demo verification.

This reporting-release record is frozen.

## Recovery

Pre-recreate stamp `/var/backups/ark-box/20260922T045145Z` is the previous Demo image `sha256:4056297143c78f931d7ca95478686938c20a57775075f2cd7651d4ebb5609fe7`. SQL gzip-ok. File-volume restore remains uncertified.

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

Do not import SQL as part of image rollback. Do not restore storage onto live Demo unless that restore is separately approved. Do not Coolify Deploy LNP. Do not put the poller image on Demo or LNP from this file.
