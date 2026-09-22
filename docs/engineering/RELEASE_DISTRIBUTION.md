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

Last image observed with the QZ Tray client still inside it:

```text
ghcr.io/edwardsoaresjr/ark-core@sha256:4056297143c78f931d7ca95478686938c20a57775075f2cd7651d4ebb5609fe7
```

That image does not contain the current sidebar. Do not deploy it to restore printing.

LNP Core running now (read-only, 2026-09-22):

```text
ghcr.io/edwardsoaresjr/ark-core@sha256:f113969e71f21e3aeb110bab84719ba9ca4e728b390971f971c22e44284c7283
```

Source commit `38334bd8127c4c9a41c50e6cf4fea83ac60a175a`. The QZ client is not in this image. An earlier sidebar image `sha256:04b199b4…` (`ba386cce`) is not what the shop is running.

Candidate image, built and published, not deployed:

```text
ghcr.io/edwardsoaresjr/ark-core@sha256:67e8e577742d0121e10807502d0493f65b50766fae0da0afe0d216eab7cbdfe4
```

Source commit `ce8dd014097047e27433b65f19300d3eeb69ab32`. Tag `ghcr.io/edwardsoaresjr/ark-core:ce8dd014097047e27433b65f19300d3eeb69ab32` points at that digest. Do not deploy `latest` or any other floating tag.

Publish to `ghcr.io/edwardsoaresjr/ark-core`. Pin the digest. Do not deploy a floating tag.

## QZ label printing

A Core image build fails if `public/vendor/qz/qz-tray.js` or `public/js/ark/qz-tray.js` is missing. `.dockerignore` ignores `/vendor` and keeps `public/vendor`. `scripts/assert-canonical-core-publish.sh` refuses to publish a tree that drops either file or the Dockerfile check. `/up` returning 200 does not accept a release. `scripts/qz-label-smoke.sh` must see both script URLs return 200.

The browser loads `/vendor/qz/qz-tray.js` from the image. `QZ_CERTIFICATE_PATH` and `QZ_PRIVATE_KEY_PATH` live on `/data/ark-shared/storage` via the core volume mounts. An image recreate does not replace them.

Required sequence:

1. Pre-deployment baseline: `scripts/qz-label-smoke.sh` against the shop URL, with `QZ_SMOKE_COOKIE` from an admin session.
2. Pull the approved digest on the LNP host. Change only the `core` image pin. Recreate only `core`.
3. Run the smoke script again. Inside the container, `php artisan ark:printing:qz-check` must pass.
4. In a browser, start a key-tag print and confirm QZ Tray loads without a script error.
5. On LNP, Edward confirms a physical label. Automated checks do not replace that.

If step 3 or 4 fails, the release is not accepted. Put the `core` image pin back to the digest that was running before this release:

```text
ghcr.io/edwardsoaresjr/ark-core@sha256:f113969e71f21e3aeb110bab84719ba9ca4e728b390971f971c22e44284c7283
```

That rollback keeps the current sidebar and leaves printing broken. The older digest `sha256:40562971…` still contains the QZ client and does not contain this sidebar. Do not use it unless Edward explicitly chooses that tradeoff.

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
- Adopted host `144.202.74.190` timed out on SSH. Do not deploy there. Inventory correction is `hosting:reconcile-observed-host` after ownership confirmation — not re-adopt, not Coolify Deploy. Do not run it until explicitly approved.
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
