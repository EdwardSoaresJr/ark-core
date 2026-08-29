# Firebase Mobile Push Setup Doctrine

**Status:** v1 — Demo Auto Repair reference implementation  
**Sequence:** Authority → Observation → Transport (when proven) → **Setup wiring** (this doc)

**Companions:** [ark-mobile-notification-doctrine.md](./ark-mobile-notification-doctrine.md) · [ark-operator-continuity-doctrine.md](./ark-operator-continuity-doctrine.md) · [firebase-push-setup-checklist.md](./firebase-push-setup-checklist.md)

## Product sentences

**ARK owns notification authority. Firebase delivers packets — nothing else.**

**One Firebase project per App Store / Play Store binary — not per shop.**

**Client config lives on devices. Server credentials live on ARK. Neither belongs in git.**

**Setup is transport wiring, not product architecture.**

## Rule

Firebase exists in ARK Mobile push only as **FCM transport**. Setup doctrine governs **how credentials are provisioned and where they live** — not what ARK decides to send or when.

If setup steps introduce Auth, Firestore, Analytics, Functions, or Remote Config as operational dependencies, the boundary has been violated regardless of intent.

## Three credential layers

Push requires three independent credentials. Missing any layer breaks one platform or direction — not ARK workflow.

| Layer | What | Where | Git |
| --- | --- | --- | --- |
| **Client bootstrap** | `google-services.json`, `GoogleService-Info.plist` | `ark-mobile/android/app/`, `ark-mobile/ios/Runner/` | Never — gitignored |
| Server send (FCM HTTP v1) | Firebase Admin service account JSON | Production: mounted file at `/data/ark-shared/storage/app/private/` → container `/app/storage/app/private/firebase-mobile-service-account.json`; local dev: `storage/app/private/firebase-mobile-service-account.json` | Never |
| **Apple relay (iOS only)** | APNs Authentication Key (`.p8`) | Firebase Console → Cloud Messaging → Apple app | Never in repo — Console only |

ARK SMS sends push via `FirebasePushTransport` → FCM HTTP v1. Flutter registers `fcm_token` via `POST /api/mobile/device`. ARK decides **who** receives **which** packet; Firebase knows only **deliver to this token**.

## Canonical setup path

**Prefer the script over manual steps.** It validates JSON, installs client files, copies local dev fallback, and optionally enables production.

```bash
cd arksmsv2
./infra/scripts/firebase-mobile-push-setup.sh \
  --project-id demo-auto-ark-mobile \
  --service-account ~/Downloads/*-firebase-adminsdk-*.json \
  --google-services ~/Downloads/google-services.json \
  --google-service-info ~/Downloads/GoogleService-Info.plist \
  --enable-production
```

`ark-mobile` defaults to a sibling checkout (`../ark-mobile` relative to `arksmsv2`). Override with `--ark-mobile-dir` when layout differs.

**Alternative:** `./infra/coolify/ensure-demo-auto-firebase-push.sh` — idempotent production wiring (preferred for ops).

## Production wiring (Demo Auto Repair)

| Item | Value |
| --- | --- |
| Firebase project | `demo-auto-ark-mobile` |
| Android package | `com.arksms.ark_mobile` |
| iOS bundle ID | `com.arksms.arkMobile` |
| Server operational check | `php artisan ark:mobile-push:verify` |
| Host credentials file | `/data/ark-shared/storage/app/private/firebase-mobile-service-account.json` |
| Container env | `FIREBASE_CREDENTIALS=/app/storage/app/private/firebase-mobile-service-account.json` |
| Container env | `FCM_ENABLED=true` |
| Shop toggle only | `mobile_push.enabled` — dispatch on/off per shop |
| Settings surface | `/app/settings/shop?section=communications&communications-tab=mobile` |

**Do not** point `FIREBASE_CREDENTIALS` at `/data/ark-shared/...` host paths inside the container — use `/app/storage/app/private/...`. Run `infra/coolify/ensure-demo-auto-firebase-push.sh` after env-lock or deploy if push breaks.

Production enablement stores credentials on the **mounted platform file**, not in shop settings JSON. Shop settings hold only the dispatch toggle.

**Cost:** Firebase Spark (free). Register apps + Cloud Messaging only. Do not enable Blaze unless adding non-FCM Firebase products (forbidden by transport doctrine).

## Android: shared FCM entry point

One device token serves two transports on Android:

1. **Twilio Voice** — invite payloads handled first in `ArkFirebaseMessagingService`
2. **ARK comms** — continuity packets forwarded to Flutter `firebase_messaging`

Do not split into separate Firebase projects or duplicate messaging services for ARK vs Twilio on the same app binary.

## iOS prerequisite

iOS push **does not work** until APNs `.p8` is uploaded in Firebase Console (Key ID + Team ID from Apple Developer). Android can be verified first without APNs.

Skip Firebase Console wizards for Gradle BoM, Swift Package Manager, or SwiftUI init — Flutter already owns `firebase_core` + `firebase_messaging`. Disable Google Analytics when creating the project.

## Operational verification

1. Rebuild `ark-mobile` on a **physical device** with real client config files.
2. Advisor login → `POST /api/mobile/device` returns `push_enabled: true`, `push_registered: true`.
3. Inbound SMS to shop line → push notification → tap → conversation thread (Portable Station 8:10 scenario).

Settings UI shows **Operational** when enabled + project ID + credentials resolve.

**Rollback:** uncheck Enable mobile push in Settings. Client files may remain; server stops sending.

## Architectural test

**If Firebase disappeared tomorrow:**

- Push delivery stops until another `PushTransport` is configured.
- Login, conversations, orientation, RO workspace, Attention polling, and permissions **continue**.

If any of those stop, Firebase has leaked across the authority boundary — that is a bug, not a setup step.

## Forbidden

- Per-shop Firebase projects for the same App Store binary
- Committing `google-services.json`, `GoogleService-Info.plist`, or Admin SDK JSON
- Using Firebase Auth, Firestore, Realtime Database, Functions, Analytics, or Remote Config as ARK authority
- Treating FCM token as device authority (token is a transport hint on `mobile_devices`)
- Enabling push before observation justified transport (see Pressure First) — **Demo Auto Repair exception:** Portable Station Phase 1 operational cert earned transport for advisor continuity

## Agent checklist

When touching mobile push setup:

1. Read transport boundary: `ark-mobile-notification-doctrine.md`
2. Never commit credential files — confirm `.gitignore`
3. Use `firebase-mobile-push-setup.sh` for repeatability
4. Verify `isOperational()` after production changes
5. Document APNs gap explicitly if iOS untested
6. Append `IMPLEMENTATION_LOG.md` when production push state changes

## Related

- `infra/scripts/firebase-mobile-push-setup.sh`
- `ark-mobile/docs/firebase-transport-only.md`
- `docs/product/certifications/portable-station-phase-1.md`
- doctrine `ark-firebase-mobile-push-setup.mdc`
