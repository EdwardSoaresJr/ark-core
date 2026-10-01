# Firebase push setup (transport only)

**Doctrine:** [firebase-mobile-push-setup-doctrine-v1.md](./firebase-mobile-push-setup-doctrine-v1.md)  
**Cost:** Spark (free) plan only. Register apps + Cloud Messaging. Do not enable Blaze or add Firestore/Auth/Analytics.

## App identifiers (copy into Firebase Console)

| Platform | Identifier |
|----------|------------|
| Android | `com.example.ark_mobile` |
| iOS | `com.example.arkMobile` |

Suggested Firebase project ID: `example-ark-mobile` (any unique ID works).

---

## Part 1 - Firebase Console (~10 min)

1. Open [Firebase Console](https://console.firebase.google.com) → **Add project** → name e.g. `LugsNPlugs ARK Mobile` → **disable Google Analytics** (optional; keeps surface minimal).
2. **Project settings** → note **Project ID** (e.g. `lugsnplugs-ark-mobile`).
3. **Add app → Android**
   - Package: `com.lugsnplugs.ark_mobile`
   - Download `google-services.json`
4. **Add app → iOS**
   - Bundle ID: `com.lugsnplugs.arkMobile`
   - Download `GoogleService-Info.plist`
5. **Project settings → Cloud Messaging → Apple app configuration**
   - Upload APNs **Authentication Key** (.p8) from [Apple Developer](https://developer.apple.com/account/resources/authkeys/list) - Key ID + Team ID required for iOS push.
6. **Project settings → Service accounts → Generate new private key**
   - Saves `*-firebase-adminsdk-*.json` - this is the **server send** credential (FCM HTTP v1). Keep private.

Do **not** add Firestore, Authentication, or Functions.

---

## Part 2 - Run setup script (local Mac)

From the Core checkout (expects `ark-mobile` as a sibling directory):

Install the Firebase client files into the `ark-mobile` sibling checkout, then set:

```text
FIREBASE_CREDENTIALS=/app/storage/app/private/firebase-mobile-service-account.json
FCM_ENABLED=true
```

Confirm with `php artisan ark:mobile-push:verify`.

Place the client config in `ark-mobile/android/app/` and `ark-mobile/ios/Runner/`. Keep the service-account JSON on the server, outside the image, and point `FIREBASE_CREDENTIALS` at the in-container path.

---

## Part 3 - Build & verify

```bash
cd ../ark-mobile
flutter run   # or release build to device
```

1. Advisor login on phone → Settings path shows device registered.
2. `POST /api/mobile/device` response: `push_registered: true`, `push_enabled: true`.
3. Inbound SMS to shop → push notification → tap → conversation thread.

**Settings UI:** Settings, Communications, Mobile.

---

## Current status (LugsNPlugs)

**Transport is live on production** as of 2026-06-27. `MobilePushSettings::current()->isOperational()` returns true.

Remaining for full Portable Station operational cert:

1. Upload APNs `.p8` to Firebase Console (iOS)
2. Rebuild `ark-mobile` on physical device
3. Advisor login + inbound SMS → push → tap → conversation (8:10 AM scenario)

---

## Rollback

Settings → Communications → Mobile → uncheck **Enable mobile push**. Client config files can stay; server stops sending.
