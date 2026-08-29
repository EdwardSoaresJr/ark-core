# ARK Mobile — Android release deploy

**App ID:** `com.arksms.ark_mobile`  
**API default:** `https://app.demo-auto.test`  
**Repo:** `ark-mobile` (Flutter)

Release signing lives in `android/app/build.gradle.kts`. With `android/key.properties` present, release builds use the upload keystore. Without it, release falls back to debug keys (USB floor test only — not Play Store).

---

## One-time: upload keystore

From the `ark-mobile/android` directory:

```bash
keytool -genkey -v \
  -keystore upload-keystore.jks \
  -keyalg RSA -keysize 2048 -validity 10000 \
  -alias upload
```

Store `upload-keystore.jks` and passwords in a secure backup (1Password, ops vault). Both are gitignored.

Copy the template and fill in values:

```bash
cp key.properties.example key.properties
```

`key.properties` (gitignored):

```properties
storePassword=...
keyPassword=...
keyAlias=upload
storeFile=upload-keystore.jks
```

Paths in `storeFile` are relative to `android/`.

---

## Build commands

From `ark-mobile` project root:

| Goal | Command | Output |
|------|---------|--------|
| USB floor test | `flutter run --release -d <device-id>` | Installed on device |
| Sideload APK | `flutter build apk --release` | `build/app/outputs/flutter-apk/app-release.apk` |
| **Play Store** | `flutter build appbundle --release` | `build/app/outputs/bundle/release/app-release.aab` |

Verify signing before first Play upload:

```bash
jarsigner -verify -verbose -certs build/app/outputs/bundle/release/app-release.aab
```

---

## Google Play Console

1. Create app with package `com.arksms.ark_mobile` (if not already registered).
2. Upload `app-release.aab` to **Production** or **Internal testing**.
3. Complete store listing, content rating, and target API requirements.
4. Enable **Play App Signing** — Google holds the app signing key; you upload with the upload key above.

Bump version in `pubspec.yaml` (`version: x.y.z+build`) before each store release — `+build` maps to Android `versionCode`.

---

## OBD / iCar Pro

Bluetooth OBD requires the **native Android app**, not a browser build. Grant nearby-devices / location permissions when prompted.

---

## ARKademy

Staff-facing install guide: **Shop In A Box → Admin → ARK Mobile Android deploy** on [learn.demo-auto.test](https://learn.demo-auto.test).
