# Core release distribution

When you say **Release Core**, read [`ops/releases/distribution.yaml`](../../ops/releases/distribution.yaml). Do not name servers from memory.

A git push is not a release. Each target has its own deploy and verification result.

**Fleet deployment automation is disabled.** Do not enable it from this document.

## Live inventory

| Target | URL | Adapter | Deploy |
| --- | --- | --- | --- |
| Local Herd | `https://app.lugsnplugs.test` | This Core checkout | Verify only |
| Demo | `https://demo.arksms.com` | Docker Compose at `/opt/ark` on `104.238.144.183` | Disabled |
| LNP Production | `https://lugsnplugs.arksms.com` | Compose recreate-core on `149.28.249.13` | **Disabled** |

Running 2026-09-23 on Demo and LNP. Local Herd is this checkout. Fleet deployment automation stays disabled.

```text
ghcr.io/edwardsoaresjr/ark-core@sha256:741cd088847b739841e8fad22b6d437b403557bf63c1d726718fcecaeaec9017
```

Source commit `6a59a915a5c2dfc1250d76d3cf92d550fc528715` on `main`. `/up` returned 200 on local, Demo, and LNP. Only Demo `app` and LNP `core` were recreated.

Immediate rollback is the image that was running before this recreate:

```text
ghcr.io/edwardsoaresjr/ark-core@sha256:2e3ada9bc5e77525dd9bf60397ff4d3e265fb53f85e355770fd0a7ad817ebb69
```

Source commit `bbd46ff61064b59e64b17d47340d81f14039efbf`. Compose backups: `/root/demo-compose-image-pre-6a59a915.yml` and `/root/lnp-core-compose-pre-6a59a915.yml`.

LNP Core accepted 2026-09-22. Shop confirmation: one physical label on one sticker, and the inbound SMS popup appeared.

```text
ghcr.io/edwardsoaresjr/ark-core@sha256:6f81679961b092ca23a2b073684bdabfc7a1242b2e9ee2ac1079f459d7c9b6c9
```

Source commit `b6bfb8c1562466cb0fa516ecd62f7f9897668263` on `release/one-label-inbox-sms`. QZ client, one-label PDF printing, inbox layout, and the hosted SMS interrupt are in this image. Brother QL rasterization is not.

Rollback if a label is wrong or the popup misbehaves. Compose backup on the host: `/root/lnp-core-compose-pre-combined-b6bfb8c15624.yml`.

```text
ghcr.io/edwardsoaresjr/ark-core@sha256:67e8e577742d0121e10807502d0493f65b50766fae0da0afe0d216eab7cbdfe4
```

Source commit `ce8dd014097047e27433b65f19300d3eeb69ab32`. One label, PDF path, no inbox layout, no hosted SMS interrupt.

Do not redeploy these. Both force Brother QL raster, and a physical label ran across two stickers:

```text
ghcr.io/edwardsoaresjr/ark-core@sha256:816385826054dc8a1c1da0b569737717be166359cd1d822e6a58eac1aa9644c2
ghcr.io/edwardsoaresjr/ark-core@sha256:0ab8e2f5fb1e8eac13ffce4b7d757caa990cad9fd8de3292790f9b4e97d3f6eb
```

Communications workspace tests on this commit: 7 passed, 4 failed, 1 error. The failures are missing `ops-comms-workspace__thread-header`, missing `ops-comms-workspace__visit-strip`, and `Route [webhooks.communications.twilio.messaging.incoming] not defined`. Do not treat that suite as passing.

Publish to `ghcr.io/edwardsoaresjr/ark-core`. Pin the digest. Do not deploy a floating tag. This release branch does not share history with `origin/main`. Do not merge them together.

## Production schema and the running image

Observed 2026-09-22 on the live LNP database while Core was pinned to `sha256:6f81679961b092ca23a2b073684bdabfc7a1242b2e9ee2ac1079f459d7c9b6c9` (source `b6bfb8c1562466cb0fa516ecd62f7f9897668263`). `migrate:status --pending` reported nothing pending. The migrations table still has six rows whose files are not in that image:

- `2026_06_26_140001_add_workstation_fields_to_communication_devices`
- `2026_07_23_220000_migrate_concern_advisor_notes_to_private_note_lines`
- `2026_08_08_110000_create_customer_documents_table`
- `2026_09_06_090000_add_platform_connection_columns_to_shop_settings`
- `2026_09_18_010000_drop_shop_settings_postmark_columns`
- `2026_09_21_230000_add_companion_suggestion_dismissed_hash_to_repair_orders`

A database built only by migrating that image does not fully reproduce production. Confirmed on production and absent from that fresh schema: table `customer_documents`, and column `repair_orders.companion_suggestion_dismissed_hash`. These rows are older than the local candidate on `main`.

SQLite can prove a new migration’s order. It does not prove MySQL foreign keys. Before any production rollout that adds tables or changes deletes, confirm the foreign keys on MySQL. An image rollback does not roll back the database. Once `approved_work_scopes` or `authorization_exceptions` rows exist, leave those tables and their delete protection in place.

## QZ label printing

A Core image build fails if `public/vendor/qz/qz-tray.js` or `public/js/ark/qz-tray.js` is missing. `.dockerignore` ignores `/vendor` and keeps `public/vendor`. `scripts/assert-canonical-core-publish.sh` refuses to publish a tree that drops either file or the Dockerfile check. `/up` returning 200 does not accept a release. `scripts/qz-label-smoke.sh` must see both script URLs return 200.

The browser loads `/vendor/qz/qz-tray.js` from the image. `QZ_CERTIFICATE_PATH` and `QZ_PRIVATE_KEY_PATH` live on `/data/ark-shared/storage` via the core volume mounts. An image recreate does not replace them.

Required sequence:

1. Pre-deployment baseline: `scripts/qz-label-smoke.sh` against the shop URL, with `QZ_SMOKE_COOKIE` from an admin session.
2. Pull the approved digest on the LNP host. Change only the `core` image pin. Recreate only `core`.
3. Run the smoke script again. Inside the container, `php artisan ark:printing:qz-check` must pass.
4. In a browser, start a key-tag print and confirm QZ Tray loads without a script error.
5. On LNP, Edward confirms a physical label. Automated checks do not replace that.

If step 3 or 4 fails, the release is not accepted. Put the `core` image pin back to the rollback digest in the live inventory above.

Core-only recreate, after the digest is pulled onto the host (`pull_policy: never`):

```text
cd /data/coolify/services/waqkg4rlh7rq9pdfwpnfij8u
docker compose up -d --no-deps --force-recreate core
```

Do not recreate `lnp-mysql`, `lnp-redis`, `foundry`, `traefik`, or `ops-router`. Do not run compose against the whole file. Leave `/data/ark-shared/storage` and the core env file in place. Do not roll back the database.

Persistent environment, not image layers:

- `QZ_CERTIFICATE_PATH`
- `QZ_PRIVATE_KEY_PATH`
- `QZ_PRIVATE_KEY_PASSPHRASE` when the key is encrypted
- `QZ_SIGNATURE_ALGORITHM` (`sha512`)

Do not print the private key in logs, health responses, or test output.

`demo.autorepairkeeper.com` is retired. GoDaddy DNS was deleted. It is not a deployment target.

## Heartbeat

`fleet/phase-1-observation` schedules `ark:platform-heartbeat` every **five minutes** (`*/5 * * * *`). That is the live implementation on this branch.

A parallel branch used one-minute check-ins plus `ARK_IMAGE_DIGEST` and a source-commit file. Those reporting fields are on this branch. The schedule stays five minutes. Platform `BoxHealth` already treats three five-minute misses as Delayed.

A successful heartbeat is inventory check-in. It is **not** deploy success. Current requires an observed image digest and `/up`.

## Observed (read-only)

- Demo Compose is at `/opt/ark` on `104.238.144.183`, container `ark-app-1`. No Coolify application. Platform shop pairing is empty.
- Demo installation UUID **assigned** (not written on the box): `5dba0d3f-fbbd-4c45-8b2d-2e0ea550d7b6`. Write with `php artisan ark:installation-identity write --uuid=…`. Pair later. Do not invent a Coolify application id.
- Demo Compose backups exist at `/var/backups/ark-box`. Latest stamp includes `storage.tar.gz` (gzip ok). `restore-box.sh` can restore that archive. SQL restore has been tested. File-volume restore is coded, not certified.
- LNP Core is `b38otdn2epypspy0jadbgfl0-core` on `149.28.249.13`, pinned to the running digest in the live inventory above. Installation UUID `7d115599-cae5-4a10-a4cf-4ebe11af47ed` matches Platform adopt.
- Adopted host `144.202.74.190` timed out on SSH. Do not deploy there. Inventory correction is `hosting:reconcile-observed-host` after ownership confirmation - not re-adopt, not Coolify Deploy. Do not run it until explicitly approved.
- LNP backup/rollback: [LNP_BACKUP_AND_ROLLBACK.md](LNP_BACKUP_AND_ROLLBACK.md).

## Default sequence (when automation is later allowed)

1. Publish a reporting-only Core image (commit file + digest). Do not mix that with unrelated Laravel patches.
2. Verify local Herd.
3. One controlled Demo Compose recreate of `app` only. Confirm reported commit/framework, digest, `/up`, and backup/rollback notes.
4. After explicit production approval, consider LNP separately.

Until each remote box has trustworthy observation **and** a recorded rollback path, stop after publish + local verify unless Edward authorizes a specific remote recreate.

## Safety

- Pin commit **and** digest. Branch HEAD is not a release.
- Do not copy production data into Demo or local.
- Do not Coolify Deploy LNP.
- Do not adopt Demo as a Coolify app.
- Do not treat Platform’s LNP host `144.202.74.190` as live.
- A failed or unreachable target is not successful.
- A heartbeat is not a successful deploy.
- Core releases do not redeploy Platform, Foundry, or Companion.
- Demo Compose backups exist on the box. Platform managed backup is still unavailable. Do not treat them as the same product.
