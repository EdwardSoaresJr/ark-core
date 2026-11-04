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
| **ARK-WEB** | `demo-auto.test` | **Shop branding (exception)** | Demo Auto Repair favicon + OG in `arkweb` - do not force ARK mark |

### ARK-WEB exception (documented)

`demo-auto.test` is customer-facing marketing. It keeps **Demo Auto Repair** favicon, OG image, and SEO copy. Operational hosts (`app.*`, `learn.*`, `platform.*`) share the ARK tab mark so staff recognize ecosystem tools.

## Customer pages: the shop's identity, not ARK's

Website shell, booking, portal sign-in and dashboard, estimates, inspections, repair status, and payment
pages (`x-customer.shell`) carry the shop's identity: its name (tab title), logo, contact facts
(`ShopSettings`), and its theme, `App\Ark\Customer\Theme\CustomerTheme`:

- Main, accent, and dark colors (hex only), page background (must be light), heading and text fonts
  (fixed list, loaded from fonts.bunny.net), corners, header style (`light`, `dark`, `brand`), and an
  optional favicon path on the shop's own host.
- Core derives button text, link, hover, and dark-mode link colors so text keeps 4.5:1 contrast, and
  writes them as `--public-*` custom properties (`<style data-customer-theme>`). Customer CSS and views
  use only those tokens; no customer rule names a brand hex.
- Without a host source a self-hosted install gets Core's defaults (neutral blue, Figtree, light header,
  the ARK favicon). A host binds `CustomerThemeSource` (theme values) and optionally
  `CustomerSiteNavigation` (its website's page links); both are read on every request, never cached
  across shops, and a failing source falls back to the defaults.
- A theme changes presentation only: amounts, approvals, tokens, and payments never read it.

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

```

## Verification artifacts

See `docs/branding/verification/` for fetched favicon binaries and audit notes.

## Related docs

- `docs/branding/ecosystem-identity.md` - doctrine
- `docs/branding/inventory.md` - full asset inventory
