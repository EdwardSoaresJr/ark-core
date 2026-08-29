# ARK Branding Inventory

Source of truth for platform branding: `public/assets/ARK_SMS_FINAL_DROP_IN_PACK/` (copied verbatim from ARK-SMS).

Runtime authority: `App\Support\Branding\Branding` and `BrandingAssetRegistry`.

**Ecosystem doctrine:** `docs/branding/ecosystem-identity.md` — ARK V2, ARKademy, and Arkify share the ARK favicon from this pack. ARK-WEB (`demo-auto.test`) keeps shop branding.

**Ownership:** `docs/branding/ownership.md`

Guidelines (docs only, not runtime CSS): `docs/branding/ARK-SMS-Full-Brand-System/`.

---

## Logos

| Asset | Location | Type | Dimensions | Used in ARKv2 |
|-------|----------|------|------------|---------------|
| Horizontal logo | `ark_logo_horizontal.png` | PNG | 1488×472 | `Branding::emailLogo()`, `Branding::pdfPlatformLogo()` |
| Full white logo | `ark_logo_full_white.png` | PNG | 1432×434 | Registry only (landlord marketing parity) |
| Transparent light logo | `ark_logo_transparent_light.png` | PNG | 1432×434 | `Branding::loginImage()` — staff login |
| White with teal logo | `ark_logo_white_with_teal.png` | PNG | 1432×434 | **Unused in ARKv2 views** |
| Icon master | `ark_icon_master_1024.png` | PNG | 1024×1024 | **Unused in ARKv2 views** |

---

## Favicons

| Asset | Location | Type | Dimensions | Used in ARKv2 |
|-------|----------|------|------------|---------------|
| favicon.ico | `favicon/favicon.ico` | ICO | 64×64 | `Branding::favicon('ico')` — all layouts |
| ark-16x16.png | `favicon/ark-16x16.png` | PNG | 16×16 | `Branding::favicon('16')` |
| ark-32x32.png | `favicon/ark-32x32.png` | PNG | 32×32 | `Branding::favicon('32')`, `Branding::sidebarIcon()` |
| ark-48x48.png | `favicon/ark-48x48.png` | PNG | 48×48 | Registry only |

---

## Authentication assets

| Asset | Location | Used in ARKv2 |
|-------|----------|---------------|
| Login logo | `ark_logo_transparent_light.png` | `layouts/guest.blade.php` via `Branding::loginImage()` |

No login illustrations or background images were migrated (ARK-SMS apt-book `booking_background.jpg` is booking-plugin specific, not core staff auth).

---

## Application branding

| Surface | Asset / mechanism | ARKv2 wiring |
|---------|-------------------|--------------|
| Operations sidebar | `favicon/ark-32x32.png` | `components/operations/app.blade.php` via `Branding::sidebarIcon()` |
| Favicons (all surfaces) | favicon trio + apple-touch | `partials/branding/_favicons.blade.php` |
| Shop estimate/PDF logo | `shop_settings.logo_path` (uploaded) | **Separate shop authority** — unchanged |

---

## Email branding

| Asset | Used in ARKv2 |
|-------|---------------|
| `ark_logo_horizontal.png` | `vendor/mail/html/header.blade.php` when header slot is `Laravel` (replaces Laravel CDN fallback) |
| Shop name text | Estimate/invoice customer mail — shop-scoped, not platform logo |

---

## PDF branding

| Layer | Authority |
|-------|-----------|
| Shop letterhead logo | `EstimateSnapshotBuilder` → `shop_settings.logo_path` (dynamic upload/seed) |
| Platform logo | `Branding::pdfPlatformLogo()` available; shop PDFs continue to use shop logo |

---

## PWA / manifest

| Asset | Location | Used in ARKv2 |
|-------|----------|---------------|
| manifest.json | pack root | `Branding::manifest()` linked in `_favicons.blade.php` |
| PWA icons 72–512 | `pwa/ark-*.png` | Registry (`Branding::pwaIcon()`) — **not individually linked in views** |
| Android icons | `android/ark-*.png` | Registry only |
| iOS icons | `ios/ark-*.png` | `Branding::appleTouchIcon()` uses 180×180 |

**Note:** `manifest.json` icon paths still reference `/icons/ark-*.png` as in ARK-SMS original (preserved verbatim). PWA icon files live under `pwa/`.

---

## ARKv2 assets outside platform pack (not ARK platform branding)

| Asset | Location | Classification |
|-------|----------|----------------|
| Demo Auto Repair seed logo | `resources/seed-assets/operations/demo-auto-logo.webp` | Demo shop logo |
| Laravel Breeze SVG | `components/application-logo.blade.php` | **Unused placeholder** |
| Laravel welcome SVG | `welcome.blade.php` | **Orphaned** |
| Design reference captures | `resources/design-reference/**` | UX reference only |

---

## Unused assets discovered (in migrated pack)

These files are present in `ARK_SMS_FINAL_DROP_IN_PACK` but not referenced by ARKv2 views:

- `ark_logo_white_with_teal.png`
- `ark_icon_master_1024.png`
- `favicon/ark-48x48.png`
- Entire `android/` folder (6 icons)
- Entire `pwa/` folder (8 icons) — except via manifest reference mismatch
- Most `ios/` sizes except `ark-180x180.png` (apple-touch-icon)

Preserved for authority completeness and future PWA wiring.
