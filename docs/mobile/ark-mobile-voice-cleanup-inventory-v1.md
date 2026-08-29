# ark-mobile Voice Cleanup Inventory v1

**Status:** B1 ✅ · **B2 ✅ (ark-mobile)** — pending commit  
**Report:** [ark-mobile-voice-runtime-authority-report-v1.md](../mobile/ark-mobile-voice-runtime-authority-report-v1.md)  
**Date:** 2026-07-04  
**Repo:** `private ark-mobile sibling (not redistributed)` (sibling to `arksmsv2`)  
**Mission (architecture):** [phase-b-voice-cleanup-mission-v1.md](../communications/phase-b-voice-cleanup-mission-v1.md) · **Runtime:** [../runtime/voice-runtime-authority.md](../runtime/voice-runtime-authority.md)  
**Sprint (evidence):** [communications-voice-cleanup-sprint-v1.md](../communications/communications-voice-cleanup-sprint-v1.md)

---

## B1 deliverables checklist

- [x] Full-repo voice/Twilio/SIP transport artifacts classified
- [x] **Production graph** (30-second engineer test)
- [x] **Dead graph** (B2 deletion checklist)
- [x] **Zero Unknown rows**
- [x] Reviewer approves B2 scope

**B1 rule observed:** No code changes in ark-mobile or arksmsv2.

---

## Production dependency graph

New engineer answer in under 30 seconds:

```text
ARK Phone (UI + bootstrap)
        │
        ▼
ArkVoiceDialer                    lib/services/ark_voice_dialer/ark_voice_dialer.dart
        │
        ▼
ArkVoiceTransport                 lib/services/ark_voice_dialer/transports/ark_voice_transport.dart
        │
        ▼
sip_ua (+ flutter_webrtc)         pubspec.yaml · package:sip_ua
        │
        ▼
Asterisk (WSS REGISTER/INVITE)    Session from POST /api/mobile/telephony/voice-session
        │
        ▼
Twilio Elastic SIP Trunk          arksmsv2 backend only — not referenced in ark-mobile code
```

**Production proof (backend issues `transport: ark_voice` only today):**

- `MobileVoiceTransportManager::current()` → `AsteriskMobileVoiceTransport` only (`app/Ark/Operations/Telephony/MobileVoice/MobileVoiceTransportManager.php`)
- `AsteriskMobileVoiceTransport::transportKey()` → `'ark_voice'`
- `TwilioMobileVoiceTransport` is `@deprecated` and **not** registered in the manager

**Callers into production path:**

| Caller | Callee | File |
| --- | --- | --- |
| `VoiceDialerBootstrap` | `ArkVoiceDialer`, `ArkVoiceTransport` | `lib/services/voice_dialer_bootstrap.dart` |
| `IncomingCallHost`, `InAppCallOverlay`, `ActiveInboundCallBanner` | `ArkVoiceDialer` | `lib/widgets/*.dart` |
| `CallPlacer` | `ArkVoiceDialer.connectOutbound` | `lib/utils/call_placer.dart` |
| `ArkVoiceDialer.initializeSession` | `_bindTransport` → `ArkVoiceTransport` | `ark_voice_dialer.dart:69` |
| `ArkVoiceTransport.registerSession` | `SIPUAHelper.start` | `ark_voice_transport.dart` |
| `MobileApi.voiceSession` | `/telephony/voice-session` | `lib/api/mobile_api.dart:65` |

---

## Dead dependency graph (B2 deletion checklist)

**Phase B ends when this graph is empty.**

```text
twilio_voice (pubspec + native deps)
        │
        ▼
TwilioVoiceTransport
        │
        ▼
(no production callers — backend never sends transport=twilio)

ArkVoiceDialer._bindTransport (runtime selector)
        │
        ├──► TwilioVoiceTransport  (branch: transport == 'twilio')
        ├──► NoopVoiceTransport   (branch: unknown transport)
        └──► ArkVoiceTransport    (only production branch)

VoiceTransport (abstract interface)
        │
        ▼
(three implementers — only ArkVoiceTransport is production)

transport key checks (client-side multi-runtime)
        │
        ├── home_shell.dart (transport == 'ark_voice' \|\| 'asterisk')
        ├── incoming_call_host.dart (reject non-ark/asterisk)
        ├── voice_dialer_bootstrap.dart (_usesArkVoiceTransport)
        └── voice_registration_snapshot.dart (isArkVoiceTransport)

lugs_demo_data.dart
        │
        ▼
transport: 'twilio' (demo-only fixture)

Twilio native build artifacts
        │
        ├── pubspec.yaml twilio_voice
        ├── android/app/build.gradle.kts com.twilio:voice-android
        ├── android/app/proguard-rules.pro Twilio keep rules
        ├── android/settings.gradle.kts patchTwilioVoiceProguardForAgp9
        ├── tool/patch_twilio_voice_ios.sh
        ├── tool/patch_twilio_voice_android.sh
        └── macos/Flutter/GeneratedPluginRegistrant.swift (regenerates)

Stale Twilio docs / README sections
        │
        └── phase0-twilio-lifecycle-study.md, README patch instructions, firebase-transport-only stale line
```

| Dead graph node | B2 action | Inventory row |
| --- | --- | --- |
| `TwilioVoiceTransport` | Delete file | § TwilioVoiceTransport |
| `twilio_voice` package | Delete after code | § twilio_voice pubspec |
| `_bindTransport` switch | Delete → direct `ArkVoiceTransport` | § `_bindTransport` |
| `VoiceTransport` interface | Delete → methods on `ArkVoiceTransport` only | § VoiceTransport |
| `NoopVoiceTransport` | Delete | § NoopVoiceTransport |
| Transport key gates | Simplify → `inAppReady` only | § transport key checks |
| Demo `transport: twilio` | Delete / set `ark_voice` | § lugs_demo_data |
| Twilio Gradle/ProGuard/patches | Delete | § native Twilio artifacts |
| Stale Twilio docs | Delete or update | § documentation rows |

---

## Inventory table

### Packages

| Item | Runtime Authority | Classification | Action | Proof | Why |
| --- | --- | --- | --- | --- | --- |
| `twilio_voice` ^0.3.2+2 | None (dead) | Dead | Delete (pubspec **last**) | Imported only by `twilio_voice_transport.dart`; `grep twilio_voice lib/` → 1 Dart file + generated macOS registrant | Obsolete PV mobile SDK |
| `sip_ua` ^1.1.0 | SIP | Infrastructure | Keep | `ArkVoiceTransport` imports `package:sip_ua/sip_ua.dart`; production REGISTER/INVITE | Production SIP stack |
| `flutter_webrtc` ^1.5.2 | SIP | Infrastructure | Keep | Transitive dependency of `sip_ua`; media for WSS calls | Required by sip_ua |

### Mobile voice runtime (ARK Phone)

| Item | Runtime Authority | Classification | Action | Proof | Why |
| --- | --- | --- | --- | --- | --- |
| `ArkVoiceDialer` | Mobile Voice | Infrastructure | Keep | Singleton; called from bootstrap, overlays, `call_placer.dart`; wires session/connect API | Production dialer shell |
| `ArkVoiceTransport` | Mobile Voice | Infrastructure | Keep | Only transport used when backend sends `ark_voice`; `SipUaHelperListener`; WSS to shop Asterisk | Production client runtime — **do not rename** |
| `VoiceTransport` (abstract) | None (dead abstraction) | Dead | Delete | 3 implementers; only `ArkVoiceTransport` on production path; two-implementation rule | Interface exists only to switch runtimes |
| `TwilioVoiceTransport` | None (dead) | Dead | Delete | Only referenced from `ark_voice_dialer.dart` `_bindTransport` + import; never selected in production (`transportKey` from API is always `ark_voice`) | Obsolete PV transport |
| `NoopVoiceTransport` | None (dead) | Dead | Delete | Only `_bindTransport` default branch when transport ∉ {ark_voice, asterisk, twilio}; production never hits | Fallback for removed runtimes |
| `ArkVoiceDialer._bindTransport` | None (dead abstraction) | Dead | Delete (inline) | Switch at `ark_voice_dialer.dart:151-156`; sole multi-runtime selector | Violates single-runtime rule |
| `ArkVoiceDialer` Twilio branches | None (dead) | Dead | Delete | `is TwilioVoiceTransport` at lines 50-51, 162-164; dead when Twilio transport deleted | PV push-token path |
| `voice_session.dart` | Product | Infrastructure | Keep | Payload types for `/telephony/voice-session` and `/voice-connect`; defaults `transport: ark_voice` | Server projection consumer |
| `voice_registration_callbacks.dart` | Mobile Voice | Infrastructure | Keep | Wired from `voice_dialer_bootstrap.dart` → `ArkVoiceTransport.setRegistrationCallbacks` | Registration UI callbacks |
| `VoiceDialerBootstrap` | Mobile Voice | Infrastructure | Keep | Post-login SIP registration; direct `ArkVoiceTransport.instance` for health | Production bootstrap |
| `voice_registration_provider.dart` | Product | Infrastructure | Keep | Riverpod snapshot from shell `telephony.voice.*` | UI registration state |
| `voice_registration_snapshot.dart` | Product | Legacy | Rename | `isArkVoiceTransport` checks `asterisk` alias at line 36; simplify to single runtime in B2 | Client-side alias for retired key |
| `MobileVoicePosture.transport` | Product | Legacy | Rename | Parsed from shell JSON; used for runtime gating — after B2, field may remain but gating drops transport switch | Server still sends key; client should not branch |

### Transport key gates (client runtime selection)

| Item | Runtime Authority | Classification | Action | Proof | Why |
| --- | --- | --- | --- | --- | --- |
| `home_shell.dart` transport poll gate | Product | Legacy | Delete branch | Lines 99, 219: `transport == 'ark_voice' \|\| 'asterisk'` — backend only emits `ark_voice` | Multi-runtime gate obsolete |
| `incoming_call_host.dart` transport gate | Mobile Voice | Legacy | Delete branch | Lines 40-42: skips host when transport not ark/asterisk | Twilio host path dead |
| `voice_dialer_bootstrap._usesArkVoiceTransport` | Mobile Voice | Legacy | Delete / simplify | Lines 80-83: same dual-key check | Replace with `inAppReady` only |
| Shell default `transport: 'ark_voice'` | Product | Infrastructure | Keep | `mobile_shell.dart:113`, `voice_session.dart:51,94` — harmless default | Matches backend |

### SIP support layer

| Item | Runtime Authority | Classification | Action | Proof | Why |
| --- | --- | --- | --- | --- | --- |
| `sip_lifecycle_logger.dart` | SIP | Infrastructure | Keep | Used by `ArkVoiceTransport`, bootstrap, dialer dispose | Engineering telemetry |
| `sip_app_context.dart` | SIP | Infrastructure | Keep | `SipTeardownPath` enum referenced in transport teardown | Teardown tracing |
| `voice_reconnect_policy.dart` | SIP | Infrastructure | Keep | `ArkVoiceTransport.canSoftRecover`; tested in `voice_reconnect_policy_test.dart` | Registration survival |
| `voice_network_monitor.dart` | SIP | Infrastructure | Keep | Used by bootstrap health polling | Network-triggered recovery |
| `voice_registration_health_monitor.dart` | Mobile Voice | Infrastructure | Keep | Bootstrap registration truth | UI health |
| `voice_phone_telemetry.dart` | Product | Infrastructure | Keep | Posts to `/telephony/voice-registration-event` | Server lifecycle evidence |
| `tool/patch_sip_ua_web.sh` | SIP | Infrastructure | Keep | Patches `sip_ua` for web compile — unrelated to Twilio | sip_ua maintenance |

### UI (call control — production)

| Item | Runtime Authority | Classification | Action | Proof | Why |
| --- | --- | --- | --- | --- | --- |
| `incoming_call_host.dart` | Mobile Voice | Infrastructure | Keep | Listens `ArkVoiceDialer.callStates`; answer/hangup | Inbound call UX |
| `in_app_call_overlay.dart` | Mobile Voice | Infrastructure | Keep | Mute/hold/transfer via `ArkVoiceDialer` | Active call UX |
| `active_inbound_call_banner.dart` | Mobile Voice | Infrastructure | Keep | `answerIncoming()` entry | Inbound banner |
| `voice_posture_banner.dart` | Product | Infrastructure | Keep | Registration posture display | Operator feedback |
| `call_placer.dart` | Mobile Voice | Infrastructure | Keep | `ArkVoiceDialer.connectOutbound` via server `dial_method` | Outbound placement |
| `call_launch.dart` | Product | Infrastructure | Keep | Native fallback dial — not Twilio SDK | Non-in-app fallback |

### API layer

| Item | Runtime Authority | Classification | Action | Proof | Why |
| --- | --- | --- | --- | --- | --- |
| `MobileApi.voiceSession` | Product | Infrastructure | Keep | POST `/telephony/voice-session` | Session authority |
| `MobileApi.voiceConnect` | Product | Infrastructure | Keep | POST `/telephony/voice-connect` | Outbound authority |
| `MobileApi.voiceRegistrationEvent` | Product | Infrastructure | Keep | Telemetry POST | Lifecycle |
| `MobileApi.voiceAnswer` | Product | Infrastructure | Keep | Claim/answer coordination | Inbound |

### Native / build (Twilio PV)

| Item | Runtime Authority | Classification | Action | Proof | Why |
| --- | --- | --- | --- | --- | --- |
| `android/app/build.gradle.kts` `com.twilio:voice-android:6.9.0` | None (dead) | Dead | Delete | Direct Gradle dep for Twilio Voice SDK; only needed by `twilio_voice` plugin | PV native SDK |
| `android/app/proguard-rules.pro` Twilio `-keep` | None (dead) | Dead | Delete | Lines 1-5 comment "legacy rollback transport" | PV ProGuard |
| `android/settings.gradle.kts` `patchTwilioVoiceProguardForAgp9` | None (dead) | Dead | Delete | Patches hosted `twilio_voice-*` plugin Gradle | Build hack for dead package |
| `tool/patch_twilio_voice_ios.sh` | None (dead) | Dead | Delete | README references; patches plugin Swift | UIScene patch for dead package |
| `tool/patch_twilio_voice_android.sh` | None (dead) | Dead | Delete | Same | AGP patch for dead package |
| `macos/Flutter/GeneratedPluginRegistrant.swift` | None (dead) | Delete (regenerate) | `import twilio_voice` line 21 — auto file; removed after `flutter pub get` without dep | Generated |

### Demo / fixtures

| Item | Runtime Authority | Classification | Action | Proof | Why |
| --- | --- | --- | --- | --- | --- |
| `lugs_demo_data.dart` `transport: 'twilio'` | None (dead) | Dead | Delete / set `ark_voice` | Lines 60, 99 — demo shell only; not production path | Obsolete demo fixture |

### Documentation

| Item | Runtime Authority | Classification | Action | Proof | Why |
| --- | --- | --- | --- | --- | --- |
| `docs/engineering/phase0-twilio-lifecycle-study.md` | None (dead) | Dead | Delete | Entire doc studies Twilio SDK lifecycle for retired transport | PV study obsolete |
| `README.md` Twilio patch section | None (dead) | Dead | Delete | Lines 99-104 patch scripts for `twilio_voice` | Misleading setup |
| `docs/firebase-transport-only.md` Twilio invite line | Messaging | Legacy | Rename | Line 54 stale: "handles Twilio Voice invites first" — `ArkFirebaseMessagingService.kt` comment says PV retired | Doc drift |
| `docs/engineering/phase0-phone-telemetry-v1.md` | Product | Future | Keep | CallKit/Telecom Phase 1 note — not Twilio transport | Planned platform work |

### Not ARK Phone (exclude from B2 telephony erasure)

| Item | Runtime Authority | Classification | Action | Proof | Why |
| --- | --- | --- | --- | --- | --- |
| `VoiceCaptureService` | Product | Infrastructure | Keep | `speech_to_text` for inspection dictation — zero SIP/Twilio imports | Speech capture, not telephony |
| `VoiceFindingParser` | Product | Infrastructure | Keep | Parses measurement strings from speech transcript | Inspection NLP, not telephony |

---

## Delete gate summary (production Twilio path)

### `TwilioVoiceTransport`

1. **Who calls this?** Only `ArkVoiceDialer._bindTransport` when `transportKey == 'twilio'`.
2. **What production behavior depends on it?** None — `MobileVoiceTransportManager` never returns `twilio`; Demo Auto Repair production API issues `ark_voice`.
3. **What replaces it?** `ArkVoiceTransport` (already production).

### `twilio_voice` package

1. **Who calls this?** Only `twilio_voice_transport.dart` (`import package:twilio_voice/twilio_voice.dart`).
2. **What production behavior depends on it?** None on current production path.
3. **What replaces it?** `sip_ua` via `ArkVoiceTransport`.

### `VoiceTransport` interface

1. **Who calls this?** `ArkVoiceDialer` types `_transport` as interface; three implementers.
2. **What production behavior depends on it?** Indirection only — production impl is always `ArkVoiceTransport`.
3. **What replaces it?** Direct `ArkVoiceTransport` on dialer (no interface).

---

## B2 execution order (recommended PR sequence)

Mechanical only. Each PR ends with `Behavior changes: 0`.

| PR | Scope |
| --- | --- |
| B2-1 | Delete `TwilioVoiceTransport`, `_bindTransport` switch, Twilio branches in dialer → direct `ArkVoiceTransport` |
| B2-2 | Delete `NoopVoiceTransport`, `VoiceTransport` interface; inline transport type |
| B2-3 | Remove transport key gates (`home_shell`, `incoming_call_host`, bootstrap, snapshot) |
| B2-4 | Remove Twilio code imports; build + APK launch proof |
| B2-5 | Remove `pubspec.yaml` `twilio_voice`, Gradle Twilio dep, ProGuard, patch scripts, demo fixture |
| B2-6 | Delete stale docs; fix `firebase-transport-only.md` line |

**Package rule:** B2-4 before B2-5 (code before pubspec).

---

## arksmsv2 backend (Phase B2 — separate track)

Not inventoried row-by-row in this document. Pre-classified in [voice-runtime-inventory-v1.md](../communications/voice-runtime-inventory-v1.md).

**Safe to delete in backend B2 (no Asterisk/PJSIP/dialplan change):** `TwilioMobileVoiceTransport`, PV webhooks/TwiML stack per inventory — **after** ark-mobile B2 proves mobile path is single-runtime.

**Protected:** Asterisk dialplan sync, PJSIP, VVX provisioning, `VoiceTransportConfiguration`, trunk config.

---

## B2 scope reminder

Every B2 PR description **must** end with:

```text
Deleted: XX files
Renamed: XX symbols
Behavior changes: 0
```

See ark-cleanup-sprint-discipline.mdc.
