# ARK Ecosystem Identity Standard

One ecosystem, multiple products. Users should recognize ARK in every browser tab.

## Products

| Product | Host / surface | Repo | Favicon status |
|---------|----------------|------|----------------|
| **ARK SMS** | `app.demo-auto.test`, `portal.demo-auto.test` | `arksmsv2` → **`arksms`** (Phase 2) | **Standard** — `Branding::favicon()` on all layouts |
| **ARKademy** | `learn.demo-auto.test` | BookStack theme: ARK favicon + `header-logo.blade.php` mark + cerulean colors |
| **ARK-WEB** | `demo-auto.test` | `EdwardSoaresJr/arkweb` | **Audit required** — copy pack into `arkweb` public assets |
| **Arkify** | `platform.autorepairkeeper.com` | Coolify (vendor) | **Manual** — instance branding / uploaded favicon |

Product names stay distinct. **Favicon, logo family, primary blue, and typography** stay aligned.

## Favicon source of truth

| Asset | Path |
|-------|------|
| ICO | `favicon/favicon.ico` |
| 16×16 | `favicon/ark-16x16.png` |
| 32×32 | `favicon/ark-32x32.png` |
| Apple touch | `ios/ark-180x180.png` |

Pack root: `public/assets/ARK_SMS_FINAL_DROP_IN_PACK/`

## ARK V2 (implemented)

- Views: `resources/views/partials/branding/_favicons.blade.php`
- Included on operations, portal, public, guest, and error layouts
- PWA: `manifest.json` + registry icons (see `docs/branding/inventory.md`)

## ARKademy / BookStack (implemented)

1. Favicons live in `infra/coolify/bookstack/themes/arkademy/public/favicon/` (synced from pack).
2. `apply-branding.sh` injects favicon `<link>` tags via BookStack `app-custom-head` (upgrade-safe — no vendor edits).
3. `bootstrap-storage.sh` syncs theme to `/data/ark-shared/bookstack-config`.

**After deploy or BookStack upgrade:**

```bash
./infra/branding/sync-ecosystem-favicons.sh          # local / CI
ssh production 'cd .../bookstack && ./bootstrap-storage.sh && ./apply-branding.sh'
```

## ARK-WEB (`demo-auto.test`)

**Policy exception:** keep **Demo Auto Repair** shop favicon, OG image, and SEO. Do not force ARK ecosystem mark on the public marketing site. Operational hosts share ARK favicons; customer-facing web stays shop-branded.

Copy the ARK pack into arkweb only if a future **staff/admin** surface is added under arkweb — not for public pages.

## Arkify / Coolify control plane

Automated: `infra/branding/deploy-arkify-branding.sh` → `/data/coolify/custom/ark-branding/`

Guardrails cron re-applies layout patch and `docker cp` favicons after Coolify upgrades.

## Verification checklist

| Check | ARK V2 | ARKademy | ARK-WEB | Arkify |
|-------|--------|----------|---------|--------|
| Tab favicon = ARK mark | ✓ | ✓ after deploy | Shop (intentional) | ✓ after deploy |
| Apple touch icon | ✓ | ✓ theme | Shop | ✓ |
| Login / header logo family | ARK transparent light | BookStack app name | Demo Auto Repair | Instance name |
| Primary blue `#0099cc` | Ops chrome | BookStack + theme CSS | Shop theme | — |

Run: `./infra/branding/verify-ecosystem-branding.sh`

Ownership: `docs/branding/ownership.md`

## Future (not now)

Per-product accent on the **same** ARK mark (blue V2, teal ARKademy, purple Arkify). Standardize the mark first; color variants later.

## Branding enforcement

Agents must read doctrine `ark-ecosystem-identity.mdc` before changing favicons, login branding, or cross-product head metadata.

Ecosystem UX (switcher, bridges, ARKademy landing): `docs/ecosystem/ecosystem-ux-doctrine.md`
