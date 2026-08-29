# Install the Shop Glass

Windows front-counter glass for Demo Auto Repair. **ARK is the shop. Flutter is the glass. Dragon is optional.**

Product name: **ARK Shop Glass**

## Where Edward downloads the installer

1. Open GitHub (signed in): [EdwardSoaresJr/arksmsv2 Releases](https://github.com/EdwardSoaresJr/arksmsv2/releases)
2. Open the latest **`glass-v…`** release (example: `glass-v0.1.0`)
3. Download **`Demo Auto Repair-Shop-Glass-Setup-0.1.0.exe`**

The repository is private. Use the GitHub account that already has access. No Flutter, Git, or Visual Studio is required on the shop PC.

If the release is missing, a Windows GitHub Actions run must finish first:

```text
git tag glass-v0.1.0
git push origin glass-v0.1.0
```

Workflow: `.github/workflows/shop-glass-windows.yml` (`windows-latest`).

If GitHub-hosted minutes are unavailable, build on any Windows PC that already has Flutter (not the shop glass PC):

```bat
cd apps\advisor_station
flutter pub get
flutter test
flutter build windows --release
```

Then compile `windows/packaging/shop-glass.iss` with [Inno Setup 6](https://jrsoftware.org/isinfo.php). Output:

`apps/advisor_station/windows/packaging/output/Demo Auto Repair-Shop-Glass-Setup-0.1.0.exe`

## Install (shop PC)

1. Run the Setup `.exe`
2. Install (per-user is allowed)
3. Launch **ARK Shop Glass** from Start Menu or desktop shortcut
4. On ARK (server/admin), issue a device token — plaintext is shown **once**:

```bash
php artisan station:token-issue front-counter-glass
```

5. On the glass **Pair This Station** screen:
   - ARK Base URL: `https://app.demo-auto.test` (change for Herd/dev)
   - Station Token: `stn_…`
6. Tap **Test & Pair**
7. Confirm **ARK: Online** and **Dragon: Not configured**

Do not paste a Dragon `drg_…` token. This glass does not use Dragon for shop state. Dragon machine tokens are not a shop-device credential.

## Updates

Download a newer Setup `.exe` and run it. Pairing is stored outside the install folder and should survive the upgrade.

- Station token: Windows Credential Manager (`ark.shop_glass.station_token`)
- ARK URL / device id / station name: Windows per-user app data via Shared Preferences (not the Program Files install directory)

## Unpair

Admin → **Unpair Station**. This removes the credential from **this PC**. It does **not** revoke the token in ARK.

## Recovery

### New Windows PC

Install the Setup `.exe`, issue or reuse a station token per shop policy, pair.

### Lost / stolen / replaced PC

```bash
php artisan station:token-revoke {id}
php artisan station:token-issue front-counter-glass
```

Pair the replacement with the new `stn_…` token.

### Token accidentally exposed

Revoke immediately, issue a new token, pair again.

## Start with Windows

Not enabled automatically. To launch at login: copy the Start Menu shortcut into the user’s Startup folder.

## Shop mode

The app opens 1920×1080 and can go fullscreen (shop mode). Admin → **Exit shop mode** returns to a windowed 1920×1080 canvas. Layouts reflow at 100% / 125% / 150% Windows scaling.

## ARK migration (required before pairing production)

The glass needs table `station_device_tokens` on the **MySQL** ARK database (not SQLite tests).

Migration file:

`database/migrations/2026_08_22_180000_create_station_device_tokens_table.php`

This was **not** applied to production as part of this packaging work. After a normal ARK ship (`main` = `production`, Mac `deploy-production.sh`), run inside the app container:

```bash
php artisan migrate --force --no-interaction
```

Then issue the station token.

## Dragon architecture gap

Optional Dragon still uses a **Dragon service token** (`drg_…`) meant for arkai/bridge, not a counter PC. This milestone leaves Dragon **Not configured**. Do not bake `drg_…` into the installer. A future device-safe Dragon pairing can be added later.
