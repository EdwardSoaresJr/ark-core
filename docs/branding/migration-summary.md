# ARK Branding Migration Summary

## Migrated from ARK-SMS

**Source:** `local ARK SMS drop-in branding pack (not redistributed)`

**Destination:** `public/assets/ARK_SMS_FINAL_DROP_IN_PACK/` (29 files, byte-identical copy)

**Guidelines docs:** `docs/branding/ARK-SMS-Full-Brand-System/` (text/CSS reference only — not wired into runtime styles per scope)

## Authority layer created

| File | Role |
|------|------|
| `app/Support/Branding/BrandingAssetRegistry.php` | Canonical asset lookup, variant maps, inventory |
| `app/Support/Branding/Branding.php` | Static facade: `logo()`, `favicon()`, `loginImage()`, `sidebarIcon()`, `emailLogo()`, etc. |
| `resources/views/partials/branding/_favicons.blade.php` | Shared favicon + manifest + apple-touch links |

## Views wired to branding authority

| View | Change |
|------|--------|
| `layouts/guest.blade.php` | Favicons + login logo via `Branding::loginImage()` |
| `components/operations/app.blade.php` | Favicons + sidebar icon via `Branding::sidebarIcon()` |
| `layouts/app.blade.php` | Favicons |
| `components/public/app.blade.php` | Favicons |
| `components/portal/app.blade.php` | Favicons |
| `components/errors/page.blade.php` | Favicons |
| `vendor/mail/html/header.blade.php` | ARK horizontal logo replaces Laravel CDN fallback |

## Intentionally unchanged

- Shop-uploaded estimate/PDF logos (`shop_settings.logo_path`) — per-shop authority, not platform branding
- CSS, colors, typography, layout structure
- Text-only shop identity in customer estimate/invoice emails
- `resources/design-reference/**` reference images

## Verification checklist

| Surface | Status |
|---------|--------|
| Login page logo | ARK transparent light logo via registry |
| Sidebar branding | ARK 32×32 favicon via registry (ARK-SMS AdminLTE parity) |
| Favicons | ARK favicon.ico + 16 + 32 on all primary layouts |
| PDF branding | Shop logo unchanged; platform logo available via `Branding::pdfPlatformLogo()` |
| Email branding | Laravel fallback replaced with ARK horizontal logo |

## Tests

`tests/Unit/Support/BrandingAssetRegistryTest.php` — asset existence, URL resolution, layout wiring.
