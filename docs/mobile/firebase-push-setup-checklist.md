# Firebase push setup - LugsNPlugs (transport only)

**Doctrine:** [firebase-mobile-push-setup-doctrine-v1.md](./firebase-mobile-push-setup-doctrine-v1.md)  
**Cost:** Spark (free) plan only. Register apps + Cloud Messaging. Do not enable Blaze or add Firestore/Auth/Analytics.

**Production status (2026-06-27):** Server push **operational** (`lugsnplugs-ark-mobile`, credentials in shop settings). **APNs `.p8` not yet uploaded** - iOS push pending. Android floor test next.

## App identifiers (copy into Firebase Console)

| Platform | Identifier |
|----------|------------|
| Android | `com.lugsnplugs.ark_mobile` |
| iOS | `com.lugsnplugs.arkMobile` |

Suggested Firebase project ID: `lugsnplugs-ark-mobile` (any unique ID works).

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

From this repository (`ark-mobile` is a sibling checkout):

```bash
./infra/scripts/firebase-mobile-push-setup.sh \
  --project-id your-firebase-project \
  --service-account ~/Downloads/firebase-adminsdk.json \
  --google-services ~/Downloads/google-services.json \
  --google-service-info ~/Downloads/GoogleService-Info.plist
```

If `ark-mobile` lives elsewhere, add `--ark-mobile-dir /path/to/ark-mobile`.

This will:

- Copy client config into `../ark-mobile/android/app/` and `../ark-mobile/ios/Runner/`
- Copy the service account to `storage/app/private/` for local development

Point `FIREBASE_CREDENTIALS` at that file on the server. Do not commit it.

---

## Part 3 - Build & verify

```bash
cd ../ark-mobile
flutter run   # or release build to device
```

1. Advisor login on phone → Settings path shows device registered.
2. `POST /api/mobile/device` response: `push_registered: true`, `push_enabled: true`.
3. Inbound SMS to shop → push notification → tap → conversation thread.

**Settings UI:** Settings → Communications → Mobile.

---

## Rollback

Settings → Communications → Mobile → uncheck **Enable mobile push**. Client config files can stay; server stops sending.
