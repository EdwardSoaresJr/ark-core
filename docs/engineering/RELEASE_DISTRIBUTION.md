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

Last verified image on Demo:

```text
ghcr.io/edwardsoaresjr/ark-core@sha256:f4abe244858262dac653d821cd2c6b947ff4931e6766153fcfa29695f40e2888
```

Source commit `a42b5c6408a0f1590e8e68eefe422e78a575ae0e` is in `/app/.ark-source-commit`. LNP is still on `sha256:4056297143c78f931d7ca95478686938c20a57775075f2cd7651d4ebb5609fe7` and stays there.

Publish to `ghcr.io/edwardsoaresjr/ark-core`.

`demo.autorepairkeeper.com` is retired. GoDaddy DNS was deleted. It is not a deployment target.

## Heartbeat

`fleet/phase-1-observation` schedules `ark:platform-heartbeat` every **five minutes** (`*/5 * * * *`). That is the live implementation on this branch.

A parallel branch used one-minute check-ins plus `ARK_IMAGE_DIGEST` and a source-commit file. Those reporting fields are on this branch. The schedule stays five minutes. Platform `BoxHealth` already treats three five-minute misses as Delayed.

A successful heartbeat is inventory check-in. It is **not** deploy success. Demo Systems is **Verified** from independent host inspect plus `/up`. Do not repeat that Demo gate. `desired` is not current. Each target’s `deployed.image` is the live pin. `desired.pending_change: true` names a pin; it is not permission to recreate.

## Observed (read-only)

- Demo `ark-app-1` is on `sha256:f4abe244…` / `a42b5c64`. No Coolify application. Paired to Platform shop `cd95693f-fea7-4c90-81f5-49f691bd4d79`. Systems **Verified** (Compose inspect + `/up`). Do not repeat Demo verification.
- Demo installation UUID **written**: `5dba0d3f-fbbd-4c45-8b2d-2e0ea550d7b6`. Compose box slug `demo`. Do not invent a Coolify application id.
- Demo Compose backups exist at `/var/backups/ark-box`. Latest stamp includes `storage.tar.gz` (gzip ok). `restore-box.sh` can restore that archive. SQL restore has been tested. File-volume restore is coded, not certified.
- LNP is still on `sha256:40562971…` in `b38otdn2epypspy0jadbgfl0-core` on `149.28.249.13`. Installation UUID `7d115599-cae5-4a10-a4cf-4ebe11af47ed` matches Platform.
- Platform LNP host IPv4 was reconciled to `149.28.249.13`. Previous `144.202.74.190` is retained on the host payload only. Do not deploy to 144. Do not re-adopt.
- Platform LNP desired release is `ark-core@sha256:f4abe244…` (same pin as Demo). Previous desired `arksmsv2:production` is kept on the box payload. `desired.pending_change: true` is **not** authorization to recreate `core`.
- LNP backup/rollback: [LNP_BACKUP_AND_ROLLBACK.md](LNP_BACKUP_AND_ROLLBACK.md). Reporting readiness: [LNP_REPORTING_READINESS.md](LNP_REPORTING_READINESS.md). RO request-time is a separate stream: [RO_REQUEST_TIME.md](RO_REQUEST_TIME.md).

## Default sequence (when automation is later allowed)

1. Publish a reporting-only Core image (commit file + digest). Do not mix that with unrelated Laravel patches.
2. Verify local Herd.
3. One controlled Demo Compose recreate of `app` only. Confirm reported commit/framework, digest, `/up`, and backup/rollback notes.
4. After explicit production approval, consider LNP separately.

Reporting-only Demo is closed: [DEMO_REPORTING_RELEASE.md](DEMO_REPORTING_RELEASE.md). LNP is prepared, not deployed: [LNP_REPORTING_READINESS.md](LNP_REPORTING_READINESS.md). Fleet deploy stays disabled. Do not recreate Demo or redeploy Platform from a stale pending note.

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
