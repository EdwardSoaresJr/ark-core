# ARK Ecosystem Branding Ownership

**Audit date:** 2026-06-14  
**Scope:** Favicon and touch-icon consistency across operational surfaces. Not a logo redesign.

## Source of truth

| Layer | Location | Owner |
|-------|----------|-------|
| **Asset pack** | `public/assets/ARK_SMS_FINAL_DROP_IN_PACK/` | ARK V2 repo (`arksmsv2`) |
| **Runtime registry** | `App\Support\Branding\Branding` + `BrandingAssetRegistry` | ARK V2 |
| **Blade partial** | `resources/views/partials/branding/_favicons.blade.php` | ARK V2 |
| **BookStack theme** | `infra/coolify/bookstack/themes/arkademy/public/` | ARK V2 infra |
| **Arkify static assets** | `/data/coolify/custom/ark-branding/public/ark/` on control plane | ARK infra scripts |

When favicons change, update the **pack first**, then sync downstream surfaces.

## Surface policy

| Surface | Host | Branding policy | Mechanism |
|---------|------|-----------------|-----------|
| **ARK V2** | `app.demo-auto.test`, `portal.demo-auto.test` | ARK ecosystem mark | `Branding::favicon()` via `_favicons.blade.php` |
| **ARKademy** | `learn.demo-auto.test` | ARK ecosystem mark | Theme `/theme/arkademy/favicon/*` + `app-custom-head` in BookStack settings |
| **Arkify** | `platform.autorepairkeeper.com` | ARK ecosystem mark | `infra/branding/apply-arkify-branding.sh` + guardrails cron |
| **ARK-WEB** | `demo-auto.test` | **Shop branding (exception)** | Demo Auto Repair favicon + OG in `arkweb` — do not force ARK mark |

### ARK-WEB exception (documented)

`demo-auto.test` is customer-facing marketing. It keeps **Demo Auto Repair** favicon, OG image, and SEO copy. Operational hosts (`app.*`, `learn.*`, `platform.*`) share the ARK tab mark so staff recognize ecosystem tools.

## Upgrade survival

| Surface | Survives upgrade how |
|---------|---------------------|
| ARK V2 | Assets in git + deploy pipeline |
| ARKademy | Bind mount `/data/ark-shared/bookstack-config`; re-run `apply-branding.sh` after BookStack image bump if settings reset |
| Arkify | Host assets at `/data/coolify/custom/ark-branding/`; `ark-guardrails/apply.sh` cron re-copies favicons and re-patches `base.blade.php` after Coolify upgrade |

## Operational commands

```bash
# Dev: sync pack → BookStack theme (before commit)
./infra/branding/sync-ecosystem-favicons.sh

# Production ARKademy
./infra/branding/deploy-bookstack-branding.sh

# Production Arkify (on control plane or via ssh)
ssh root@203.0.113.20 '/data/coolify/custom/ark-branding/apply-arkify-branding.sh'

# Verify all surfaces
./infra/branding/verify-ecosystem-branding.sh
```

## Verification artifacts

See `docs/branding/verification/` for fetched favicon binaries and audit notes.

## Related docs

- `docs/branding/ecosystem-identity.md` — doctrine
- `docs/branding/inventory.md` — full asset inventory
- doctrine `ark-ecosystem-identity.mdc` — agent enforcement
