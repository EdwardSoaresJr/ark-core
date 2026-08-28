# QZ Certificate Investigation

**Status:** Investigated 2026-06-03 — deployment blocker until PEMs are placed on disk.

## Findings (production server `root@24.144.81.19`)

| Check | Result |
|-------|--------|
| ARK-SMS shared `.env` | `QZ_CERTIFICATE_PATH` / `QZ_PRIVATE_KEY_PATH` point to `/home/master/applications/production/public_html/qz/*.pem` |
| Files at configured paths | **Not found** |
| `ark-sms` / `autorepairkeeper` shared dirs | No `.pem` files discovered |
| ARK V2 `.env` | No QZ vars before this pass |

Signing may still work on advisor workstations if QZ Tray was manually trusted (unsigned/dev mode), but **server-signed production mode requires valid PEM files**.

## V2 deployment plan

1. Locate the live PEM pair used when ARK-SMS signing last worked (shop backup, QZ install folder, or regenerate per [qz.io signing docs](https://qz.io/docs/signing)).
2. Place files outside `public/`, e.g.:
   - `/var/www/sites/autorepairkeeper/shared/qz/digital-certificate.txt`
   - `/var/www/sites/autorepairkeeper/shared/qz/private-key.pem`
3. Set on V2 production `.env`:
   ```
   QZ_CERTIFICATE_PATH=/var/www/sites/autorepairkeeper/shared/qz/digital-certificate.txt
   QZ_PRIVATE_KEY_PATH=/var/www/sites/autorepairkeeper/shared/qz/private-key.pem
   ```
4. Permissions: private key `600`, cert `644`, owned by PHP-FPM user.
5. Verify: `GET /app/api/qz/sign-health` returns `status: ok` when configured.

## Shared across tenants?

ARK-SMS used one PEM pair per deployment (not per tenant). ARK V2 is single-shop — **one pair per VPS** is correct.

## Unsigned fallback

When PEMs are missing, `ARK_QZ_MODE` stays `dev` and QZ Tray may prompt on each workstation until manually allowed. Parity testing can proceed locally; production should use signed mode.
