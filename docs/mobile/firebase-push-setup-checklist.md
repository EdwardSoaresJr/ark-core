# Firebase push setup — Demo Auto Repair (transport only)

**Doctrine:** [firebase-mobile-push-setup-doctrine-v1.md](./firebase-mobile-push-setup-doctrine-v1.md)  
**Cost:** Spark (free) plan only. Register apps + Cloud Messaging. Do not enable Blaze or add Firestore/Auth/Analytics.

**Production status (2026-06-27):** Server push **operational** (`demo-auto-ark-mobile`, credentials in shop settings). **APNs `.p8` not yet uploaded** — iOS push pending. Android floor test next.

## App identifiers (copy into Firebase Console)

| Platform | Identifier |
|----------|------------|
| Android | `com.arksms.ark_mobile` |
| iOS | `com.arksms.arkMobile` |

Suggested Firebase project ID: `demo-auto-ark-mobile` (any unique ID works).

---

## Part 1 — Firebase Console (~10 min)

1. Open [Firebase Console](https://console.firebase.google.com) → **Add project** → name e.g. `Demo Auto Repair ARK Mobile` → **disable Google Analytics** (optional; keeps surface minimal).
2. **Project settings** → note **Project ID** (e.g. `demo-auto-ark-mobile`).
3. **Add app → Android**
   - Package: `com.arksms.ark_mobile`
   - Download `google-services.json`
4. **Add app → iOS**
   - Bundle ID: `com.arksms.arkMobile`
   - Download `GoogleService-Info.plist`
5. **Project settings → Cloud Messaging → Apple app configuration**
   - Upload APNs **Authentication Key** (.p8) from [Apple Developer](https://developer.apple.com/account/resources/authkeys/list) — Key ID + Team ID required for iOS push.
6. **Project settings → Service accounts → Generate new private key**
   - Saves `*-firebase-adminsdk-*.json` — this is the **server send** credential (FCM HTTP v1). Keep private.

Do **not** add Firestore, Authentication, or Functions.

---

## Part 2 — Run setup script (local Mac)

From `arksmsv2` (expects `ark-mobile` as sibling directory under the same parent as `arksmsv2`):

```bash
./infra/scripts/firebase-mobile-push-setup.sh \
  --project-id demo-auto-ark-mobile \
  --service-account ~/Downloads/demo-auto-ark-mobile-firebase-adminsdk-xxxxx.json \
  --google-services ~/Downloads/google-services.json \
  --google-service-info ~/Downloads/GoogleService-Info.plist \
  --enable-production
```

If `ark-mobile` lives elsewhere, add `--ark-mobile-dir /path/to/ark-mobile`.

This will:

- Copy client config into `../ark-mobile/android/app/` and `../ark-mobile/ios/Runner/`
- Upload service account to production (`/data/ark-shared/storage/app/private/firebase-mobile-service-account.json` — mounted in app container)
- Enable push in production `shop_settings` (encrypted JSON + project ID)

Skip `--enable-production` to configure files only.

---

## Part 3 — Build & verify

```bash
cd ../ark-mobile
flutter run   # or release build to device
```

1. Advisor login on phone → Settings path shows device registered.
2. `POST /api/mobile/device` response: `push_registered: true`, `push_enabled: true`.
3. Inbound SMS to shop → push notification → tap → conversation thread.

**Settings UI (alternative to script):** [Settings → Communications → Mobile](https://app.demo-auto.test/app/settings/shop?section=communications&communications-tab=mobile)

---

## Current status (Demo Auto Repair)

**Transport is live on production** as of 2026-06-27. `MobilePushSettings::current()->isOperational()` returns true.

Remaining for full Portable Station operational cert:

1. Upload APNs `.p8` to Firebase Console (iOS)
2. Rebuild `ark-mobile` on physical device
3. Advisor login + inbound SMS → push → tap → conversation (8:10 AM scenario)

---

## Rollback

Settings → Communications → Mobile → uncheck **Enable mobile push**. Client config files can stay; server stops sending.
