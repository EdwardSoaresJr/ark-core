# LNP Core backup and rollback

**Status:** Recorded from the live host. Image rollback is possible from the retained digest and tar. Database and file-volume restore are **not** enabled. Do not Coolify Deploy.

Live Core is Compose recreate of `core` on `149.28.249.13` (`ark-lugsnplugs-production`), container `b38otdn2epypspy0jadbgfl0-core`. Platform’s managed host IPv4 is now this address. Previous adopt IPv4 `144.202.74.190` is stored as `previous_public_ipv4` only.

Installation UUID `7d115599-cae5-4a10-a4cf-4ebe11af47ed` is on the live box and on the Platform row.

## Reporting-readiness stamp

`/data/ark-shared/backups/lnp-reporting-readiness-20260922T050035Z` (`lnp-reporting-readiness-latest`)

Taken while Core was `sha256:4056297143c78f931d7ca95478686938c20a57775075f2cd7651d4ebb5609fe7`.

| Path | What it is |
| --- | --- |
| `arkv2.sql.gz` | `arkv2` dump, gzip-ok |
| `storage-app.tar.gz` | `/data/ark-shared/storage/app` (bind mount). Isolated extract: 495 files. Live bind-mount restore **uncertified** |
| `core-image-40562971.tar.gz` | `docker save` of the running digest |
| `docker-compose.yml` | Compose pin copy |

There is no `/usr/local/sbin/ark-box-backup` on LNP.

## Older stamps (SQL + compose only)

| Path | What it is |
| --- | --- |
| `/data/ark-shared/backups/shop-dashboard-20260921T133703Z/` | `arkv2.sql.gz` + compose |
| `/data/ark-shared/backups/pre-public-cutover-fresh-20260920T141130Z/` | pre-switch SQL + compose |
| `/root/lnp-core-compose-backup-*.yml` | Compose copies |
| `/root/lnp-core-rollback-e56b967e-2efa7a8281ab-20260920T134206Z.tar` | Older Core image (not the current digest) |

Those older stamps do **not** contain application file volumes.

## Application rollback (image only)

1. Confirm `sha256:40562971…` is still present, or `docker load` `core-image-40562971.tar.gz`.
2. Pin that digest on `core` in `/data/coolify/services/waqkg4rlh7rq9pdfwpnfij8u/docker-compose.yml`.
3. Recreate **only** the `core` service (`pull_policy: never`).
4. Confirm `docker inspect` RepoDigest and `https://lugsnplugs.arksms.com/up`.

Do not Coolify Deploy. Do not retarget `144.202.74.190`. Do not roll back the database as part of an image rollback.

## Database restore

SQL files overwrite shop data. Separate, explicitly approved operation — never automatic, never bundled with an image recreate.

## File volumes

Application files are bind-mounted from `/data/ark-shared/storage/app`. The reporting-readiness archive extracted in `/tmp`. Restoring that archive onto the live bind mount has not been certified. Do not claim file-volume recovery until that restore is actually run and checked.

## Fleet

`deploy_enabled` stays false. A successful heartbeat is not rollback proof and not deploy proof.
