# LugsNPlugs public Core cutover

Prepare only. Do not run until Edward authorizes a deploy.

## Preserve the live image first

Host `149.28.249.13` has no older Core image.

```text
ghcr.io/edwardsoaresjr/arksmsv2@sha256:2efa7a8281abdb9b27d370abe90115c8603b2a2d7eb0e8d796e29eafe258c2bb
commit e56b967e564b1be00553e4dd2679ff954e6de8ce
container b38otdn2epypspy0jadbgfl0-core
compose /data/coolify/services/waqkg4rlh7rq9pdfwpnfij8u/docker-compose.yml
project waqkg4rlh7rq9pdfwpnfij8u
service core
```

On the host, before any public image is loaded:

```bash
docker save ghcr.io/edwardsoaresjr/arksmsv2@sha256:2efa7a8281abdb9b27d370abe90115c8603b2a2d7eb0e8d796e29eafe258c2bb \
  -o /root/lnp-core-rollback-e56b967e-2efa7a8281ab.tar
cp /data/coolify/services/waqkg4rlh7rq9pdfwpnfij8u/docker-compose.yml \
  /root/lnp-core-compose-pre-public-cutover.yml
docker exec lnp-mysql mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers arkv2 \
  | gzip > /data/ark-shared/backups/lnp-pre-public-cutover.sql.gz
```

Do not use Coolify Deploy.

## Public image (not published in this pass)

Source: `ark` repo `32f33049` on `recovery/shared-core-from-arksmsv2` after the cutover-blocker commits.

```bash
SOURCE_COMMIT=$(git rev-parse HEAD) IMAGE=ghcr.io/edwardsoaresjr/ark ./infra/build-runner/mac/publish-ghcr-ark.sh
```

Pin compose `core.image` to the printed digest (`ghcr.io/edwardsoaresjr/ark@sha256:…`). Leave Foundry, MySQL, Redis, Traefik, and `lnp-core-ops-router` unchanged.

Set on Core only:

```text
ARK_PRESERVE_WEBSITE_GROWTH_SCHEMA=true
```

Then recreate **core** only:

```bash
docker compose -f /data/coolify/services/waqkg4rlh7rq9pdfwpnfij8u/docker-compose.yml \
  -p waqkg4rlh7rq9pdfwpnfij8u up -d --no-deps --force-recreate --pull never core
```

Do not run `2026_09_05_140000_drop_website_growth_schema_from_core`. The preserve flag keeps that file out of the migrator.

## Rollback

1. Restore compose `core.image` to the `2efa7a8281ab…` digest (or `docker load` the tar above).
2. Recreate **core** only, same compose command.
3. If any public migration wrote data you cannot keep, restore the gzip dump onto `compose_mysql-data` before starting Core.

## Isolation

Do not point a shadow Core at production MySQL or production Platform payment credentials. Stubbed capture only until a shop-floor charge is authorized on an isolated ledger.
