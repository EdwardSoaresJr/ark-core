# LNP Core backup and rollback

**Status:** Recorded from the live host. Rollback is **not** enabled. Do not Coolify Deploy.

Live Core is Compose recreate of `core` on `149.28.249.13` (`ark-lugsnplugs-production`), container `b38otdn2epypspy0jadbgfl0-core`. Platform’s adopted IPv4 `144.202.74.190` is not this host.

Installation UUID `7d115599-cae5-4a10-a4cf-4ebe11af47ed` is on the live box and on the Platform adopt row. That match is the ownership gate for any later inventory correction.

## What exists on the box

| Path | What it is |
| --- | --- |
| `/data/ark-shared/backups/` | Dated stamps (SQL + compose files) |
| `/data/ark-shared/backups/shop-dashboard-20260921T133703Z/` | `arkv2.sql.gz` + `docker-compose.yml` |
| `/data/ark-shared/backups/pre-public-cutover-fresh-20260920T141130Z/` | `arkv2-pre-switch.sql.gz`, compose pin, checksum, table counts |
| `/root/lnp-core-compose-backup-*.yml` | Compose file copies |
| `/root/lnp-core-rollback-e56b967e-2efa7a8281ab-20260920T134206Z.tar` | Saved Core image for that cutover |

There is no `/usr/local/sbin/ark-box-backup` on LNP. Demo’s Compose backup script is not installed here.

Those stamps are database dumps and compose pins. They do **not** contain application file volumes (`storage`). Do not treat them as a full box restore.

## Application rollback (image only)

1. Confirm the previous digest is still present (`docker images` or the rollback tar).
2. Pin that digest on `core` in `/data/coolify/services/waqkg4rlh7rq9pdfwpnfij8u/docker-compose.yml`.
3. Recreate **only** the `core` service.
4. Confirm `docker inspect` RepoDigest and `https://lugsnplugs.arksms.com/up`.

Do not Coolify Deploy. Do not retarget `144.202.74.190`. Do not roll back the database as part of an image rollback.

## Database restore

SQL files in `/data/ark-shared/backups/` are manual. Restoring one overwrites shop data. That is a separate, explicitly approved operation - never automatic, never bundled with an image recreate.

## File volumes

Not covered by the stamps above. A working image rollback does not restore uploaded files. Do not claim file-volume recovery until a restore of those volumes is actually run and checked.

## Fleet

`deploy_enabled` stays false. A successful heartbeat is not rollback proof and not deploy proof.
