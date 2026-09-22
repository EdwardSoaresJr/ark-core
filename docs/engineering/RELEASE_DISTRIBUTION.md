# Core release distribution

When you say **Release Core**, read [`ops/releases/distribution.yaml`](../../ops/releases/distribution.yaml). Do not name servers from memory.

A git push is not a release. Each target has its own deploy and verification result.

**Fleet deployment automation is disabled.** Do not enable it from this document.

## Live inventory

| Target | URL | Adapter | Deploy |
| --- | --- | --- | --- |
| Local Herd | `https://app.lugsnplugs.test` | This Core checkout | Verify only |
| Demo | `https://demo.arksms.com` | Docker Compose at `/opt/ark` on `104.238.144.183` | Disabled |
| LNP Production | `https://lugsnplugs.arksms.com` | Pending verification | **Disabled** |

Last verified image on Demo and LNP:

```text
ghcr.io/edwardsoaresjr/ark-core@sha256:4056297143c78f931d7ca95478686938c20a57775075f2cd7651d4ebb5609fe7
```

Do not ship `ghcr.io/edwardsoaresjr/ark` as if it were that running release.

`demo.autorepairkeeper.com` is retired. Its GoDaddy DNS record was deleted. It is not a deployment target. Do not recreate the DNS. Do not invent a Coolify application for it.

## Default sequence (when automation is later allowed)

1. Publish an immutable Core image pinned to a commit and digest.
2. Verify local Herd (correct checkout, database, assets, browser).
3. Deploy and verify Demo (Compose recreate of `app` only). Preserve Demo data.
4. After explicit production approval, deploy and verify LNP. Preserve shop data.
5. Report which targets received the digest, which passed `/up`, and which remain behind.

Until observation is trustworthy, stop after publish + local verify unless Edward authorizes a specific remote recreate.

## Safety

- Pin commit **and** digest. Branch HEAD is not a release.
- Do not copy production data into Demo or local.
- Do not Coolify Deploy LNP.
- Do not adopt Demo as a Coolify app.
- Do not treat Platform’s LNP host `144.202.74.190` as live. Documented live host is `149.28.249.13`. Reconcile read-only before enabling any LNP actuator.
- A failed or unreachable target is not successful.
- Core releases do not redeploy Platform, Foundry, or Companion.

## Next

Phase 1 is version reporting and read-only observation. Heartbeat must report the running commit/digest instead of `dev`. Then reconcile LNP 144 vs 149 and confirm Demo Compose before any Fleet deploy switch is turned on.
