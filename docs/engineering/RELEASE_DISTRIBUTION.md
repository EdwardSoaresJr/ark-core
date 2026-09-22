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

Last verified image on Demo and LNP:

```text
ghcr.io/edwardsoaresjr/ark-core@sha256:4056297143c78f931d7ca95478686938c20a57775075f2cd7651d4ebb5609fe7
```

Publish to `ghcr.io/edwardsoaresjr/ark-core`. Current live images do not contain `/app/.ark-source-commit`; the next published image must.

`demo.autorepairkeeper.com` is retired. GoDaddy DNS was deleted. It is not a deployment target.

## Observed (read-only)

- Demo is running that digest in `ark-app-1`. No Coolify application. Platform shop pairing is empty; installation UUID is unknown.
- Demo Compose backups exist at `/var/backups/ark-box` (`/usr/local/sbin/ark-box-backup`). That is not Platform managed backup.
- LNP is running that digest in `b38otdn2epypspy0jadbgfl0-core` on `149.28.249.13`. Installation UUID `7d115599-cae5-4a10-a4cf-4ebe11af47ed` matches Platform adopt.
- Adopted host `144.202.74.190` timed out on SSH. Do not deploy there.

## Default sequence (when automation is later allowed)

1. Publish an immutable Core image pinned to a commit and digest.
2. Verify local Herd (correct checkout, database, assets, browser).
3. Deploy and verify Demo (Compose recreate of `app` only). Preserve Demo data.
4. After explicit production approval, deploy and verify LNP. Preserve shop data.
5. Report which targets received the digest, which passed `/up`, and which remain behind.

Until each remote box has trustworthy heartbeat observation **and** a recorded rollback path, stop after publish + local verify unless Edward authorizes a specific remote recreate.

## Safety

- Pin commit **and** digest. Branch HEAD is not a release.
- Do not copy production data into Demo or local.
- Do not Coolify Deploy LNP.
- Do not adopt Demo as a Coolify app.
- Do not treat Platform’s LNP host `144.202.74.190` as live.
- A failed or unreachable target is not successful.
- Core releases do not redeploy Platform, Foundry, or Companion.
- Demo Compose backups exist on the box. Platform managed backup is still unavailable. Do not treat them as the same product.
