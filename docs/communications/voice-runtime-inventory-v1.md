# Voice Runtime Inventory v1

**Date:** 2026-07-04  
**Status:** Read-only audit — post Phase A (owner UI erasure), pre Phase B  
**Companion:** [communications-voice-cleanup-sprint-v1.md](./communications-voice-cleanup-sprint-v1.md)

This is not a deletion plan. It classifies what exists so authority cleanup can proceed without breaking production telephony or confusing **voice runtime selection** with **Laravel service providers**, **Riverpod providers**, or **SIP transport configuration**.

---

## Target architecture (locked)

```text
Voice  →  Asterisk (execution — dialplan, PJSIP, media)
Carrier  →  Twilio Elastic SIP Trunk (PSTN) + Twilio Messaging (SMS/MMS)
```

Twilio must **not** appear in the voice runtime graph as a peer to Asterisk. It is carrier + messaging account — not a shop “voice provider.”

```text
Business Number
      ↓
Call Routing Policy   ← ARK authority (owner edits business behavior)
      ↓
Asterisk PBX          ← execution (compiler output)
      ↓
Extension (identity)  ← Edward · Ext. 105
      ↓
Endpoints (devices)   ← ARK Phone · VVX · desktop · cell backup
```

---

## Phase A — what shipped vs what remains

### Shipped (owner Settings)

| Before | After |
| --- | --- |
| Primary Telephony Provider | *(removed from UI)* |
| Twilio Voice / TwiML / Voice API keys | *(removed from UI)* |
| SIP desk phone setup (Twilio) guide | *(removed)* |
| Ring Group tab label | **Call routing** |
| ARK Voice (SIP) | **ARK Phone** |
| Push transport | **Push notifications** |
| Shop Twilio number | **Business number** |

**Discipline held:** no PJSIP, dialplan, VVX, or trunk routing changes.

### Phase A½ — complete (2026-07-04)

- Learn owner/advisor stale **ARK Voice** / **Twilio now, PBX later** copy updated
- `TelephonyHealth::mobileVoiceClient*` removed; PV mobile operational notes removed

### Phase A½ gaps (historical — resolved)

These still exist outside the Settings blades we cleaned. None should block deploy; all should be tracked.

| Surface | Finding | Classification |
| --- | --- | --- |
| `TelephonyHealth.php` | `mobileVoiceClientLabel()` still says “Voice API Key + TwiML App” | **Dead code path** — UI block removed; method + PV operational notes remain for `telephony_provider=twilio` shops |
| Learn → **owner** `communications-setup` | Still says “ARK Voice” | **Legacy copy** — rename to ARK Phone; keep cutover facts |
| Learn → **admin** articles | Programmable Voice, TwiML, rollback SIP setup | **Legacy / rollback docs** — keep until Phase D; mark rollback-only |
| Learn → **advisor** `incoming-calls-floor` | “Twilio now, shop PBX later” | **Stale** — production is Asterisk |
| `communications-general-overview` | Twilio legacy shops still see “Voice inbound / SIP outbound” webhook rows | **Legacy** — hidden when `usesAsteriskVoice()`; OK until Phase D |
| Call routing tab | Still edits `telephony_endpoints` with Type/Target columns | **Legacy authority** — behavior unchanged by design until Phase C parity |
| `shop_settings.telephony_provider` column | Still written via PATCH if posted; UI dropdown gone | **Legacy column** — production `asterisk`; retire in Phase D |

---

## Repo-wide term audit

Searched `arksmsv2` PHP, Blade, and docs (2026-07-04). Classify every hit as **Infrastructure**, **Legacy**, **Dead**, or **Still required**.

| Term | Approx. hits (app views + PHP) | Classification | Notes |
| --- | ---: | --- | --- |
| **Programmable Voice** | 7 PHP/Blade (+ docs) | **Legacy** | Guard, deprecated mobile transport, learn rollback articles, tests asserting absence |
| **TwiML** | 8 PHP/Blade (+ docs) | **Legacy** | Twilio PV stack, `TelephonyConferenceTwiml`, ringtone comment, learn docs |
| **TwiML App** | 3 | **Legacy / Dead** | `twilio_voice_twiml_app_sid` column, `MobileVoiceCredentials`, health strings |
| **VoiceGrant** | 1 | **Dead** | `TwilioMobileVoiceTransport.php` only — `@deprecated` |
| **Voice API Key** | 2 | **Legacy / Dead** | `TelephonyHealth` strings; settings columns remain in DB |
| **Voice SID / Voice Application SID** | 0 in product code | — | Only in sprint doc ban list |
| **Incoming Voice URL / Outgoing Voice URL** | 0 literal | **Legacy concept** | Expressed as named routes `webhooks.communications.twilio.voice.*` |
| **Primary Telephony Provider** | 0 UI; tests assert gone | **Legacy** | `TelephonyProviderType` + `telephony_provider` column remain |
| **telephony_provider** | ~15 PHP (+ migration) | **Legacy selection seam** | Production `asterisk`. Still gates PV guard + health projections |
| **Twilio Voice** | scattered | **Legacy** | Distinct from **Twilio Messaging** (keep) and **Elastic SIP trunk** (carrier) |
| **Registrar / WSS** | provisioning + mobile projection PHP | **Infrastructure** | `VoiceTransportConfiguration` — not owner UI; do not delete |
| **VoiceTransportConfiguration** | platform | **Infrastructure** | SIP registrar/WSS for Asterisk + VVX + ARK Phone — **not** a runtime picker |
| **TelephonyProviderManager** | 1 manager + callers | **Legacy shim** | Twilio **vs** Asterisk selection — **delete in Phase D** after PV stack gone |
| **TwilioTelephonyProvider** | provider + ~15 webhooks | **Twilio Voice / Legacy** | Entire TwiML ingress + ring execution path |
| **AsteriskTelephonyProvider** | provider | **Asterisk** | May **rename** to execution facade — not delete |
| **TwilioWebhookVerifier** | messaging + voice webhooks | **Shared** | SMS signature verify — **keep** |
| **TwilioMessagingSender** | messaging | **Twilio Messaging** | **Keep** |
| **TwilioVoiceApi** | telephony | **Twilio Voice / Legacy** | REST helper for PV call legs — Phase D |
| **SessionProvider** (Realtime) | `app/Ark/Operations/Realtime/*` | **Shared** | CallSession event normalization — **not** shop voice provider UI |
| **AppServiceProvider** | Laravel | **Do not delete** | Framework provider — unrelated |

### Owner-facing surfaces (beyond Settings)

| Surface | PV / TwiML language? | Action |
| --- | --- | --- |
| Settings → Communications | **Clean** (Phase A) | — |
| Shop → Communications (voice workspace) | Operator/station language — no TwiML forms found | Monitor |
| Learn → owner articles | **ARK Voice** still used | Phase A½ rename |
| Learn → admin articles | Rollback + cutover jargon | Keep; label rollback-only |
| Attention / Comms / Calls & VM | Uses `CallSession` — no PV settings | **Still required** |

---

## The provider graph problem (confirmed)

Today the codebase still encodes:

```text
telephony_provider  →  TelephonyProviderManager  →  TwilioTelephonyProvider | AsteriskTelephonyProvider
```

That implies two voice runtimes. Production reality:

```text
Voice  →  Asterisk only
Twilio  →  Carrier (trunk) + Messaging — not in the voice graph
```

**Mobile is already simplified:** `MobileVoiceTransportManager` resolves **only** `AsteriskMobileVoiceTransport`. `TwilioMobileVoiceTransport` remains as dead `@deprecated` code.

**Still required temporarily:** `TelephonyProgrammableVoiceGuard` returns `<Hangup/>` on PV webhooks when `telephony_provider=asterisk` — prevents double ingress if Twilio number still points at old webhook.

---

## Laravel class inventory

### Asterisk — execution (keep; rename OK)

| Class | Role |
| --- | --- |
| `Asterisk/*` (15 classes) | Ingress auth, call events, media, recording storage, bridge, simulate |
| `MobileVoice/AsteriskMobileVoiceTransport` | Mobile SIP session issuer |
| `MobileVoice/MobileVoicePjsipSync` | Generated PJSIP stanzas |
| `MobileVoice/MobileVoiceInboundDialplanSync` | Inbound dialplan compiler (execution) |
| `MobileVoice/MobileVoiceAsteriskConfigSync` | Config publish to runtime |
| `MobileVoice/MobileVoiceAsteriskRuntimePublisher` | Runtime sync orchestration |
| `MobileVoice/AsteriskVoiceGreetingSync` | Greeting audio → Asterisk |
| `MobileVoice/AsteriskTelephonyWavNormalizer` | Media normalize |
| `Providers/AsteriskTelephonyProvider` | Asterisk-side telephony facade |
| `Media/Sources/ArkVoiceCallSessionMediaSource` | Recording playback from shop storage |
| `Platform/VoiceTransportConfiguration` | SIP registrar / WSS / outbound proxy (**infrastructure**) |
| `Platform/VoiceTransportRuntimeConfig` | Boot-time apply |

### Twilio Voice — legacy runtime (Phase D delete)

| Class | Role |
| --- | --- |
| `Providers/TwilioTelephonyProvider` | TwiML ingress + `<Dial>` ring group executor |
| `TwilioVoiceApi` | Twilio REST for call legs |
| `TelephonyWebhookController` + 14 sibling `*WebhookController` | PV HTTP ingress |
| `TelephonyConferenceTwiml`, `TelephonyStaggeredRing*` | TwiML generation |
| `TelephonyRingGroup` | Legacy ring policy → TwiML (authority until Phase C½) |
| `TelephonyIncomingCallFlow` | Hours + ring orchestration (Twilio path) |
| `TelephonyProgrammableVoiceGuard` | Hangup shim when Asterisk primary |
| `MobileVoice/TwilioMobileVoiceTransport` | `@deprecated` JWT + VoiceGrant mobile path |
| `MobileVoice/MobileVoiceCredentials` | Twilio API key / TwiML app SID reads |
| `Media/Sources/TwilioCallSessionMediaSource` | PV recording fetch |
| `TelephonyProviderManager` | Twilio vs Asterisk switch |
| `TelephonyProviderType` | `twilio` \| `asterisk` \| `fake` enum |
| `TelephonyShopSettings` | Reads `telephony_provider` |

### Twilio Messaging — keep

| Class | Role |
| --- | --- |
| `Messaging/TwilioMessagingSender` | Outbound SMS/MMS |
| `Messaging/TwilioSmsIngress` | Inbound parse |
| `Messaging/MessagingWebhookController` | SMS webhook |
| `Messaging/TwilioWebhookVerifier` | Signature verify (SMS + legacy voice routes) |
| `Messaging/*Twilio*` | Status, media fetch, parsers |

### Carrier / PSTN (infrastructure — keep)

| Location | Role |
| --- | --- |
| `infra/coolify/asterisk/twilio-trunk-cutover.md` | Elastic SIP trunk cutover runbook |
| `config/voice-transport.php` | SIP edge config |
| Twilio account SID / auth token in `shop_settings` | Messaging + trunk account |

### Shared authority (keep)

| Class | Role |
| --- | --- |
| `CallSession` | Telephony truth |
| `TelephonyExtension` | Extension = identity |
| `TelephonyEndpoint` | Legacy ring targets (until policy migration) |
| `TelephonyCallFlowSettings` | Hours, greetings, recording flags |
| `TelephonyHealth` | Operational projection (needs PV string cleanup) |
| `CallSessionOwnershipAssigner`, `IncomingCallBroadcast`, queue controllers | Floor workflows |
| `Mobile/*` voice API controllers | `/api/mobile/telephony/voice-*` |
| `Realtime/SessionProvider*` | Event normalization — **not** voice runtime selection |

### Dead / deprecated (safe to delete after Phase B–D)

- `TwilioMobileVoiceTransport`
- `TelephonyHealth::mobileVoiceClient*` (no UI consumer)
- PV credential columns usage in `ShopCommunicationsSettingsController` validation (fields hidden; DB columns remain)
- 15× `/webhooks/communications/twilio/voice/*` routes (Phase D — after parity)

### Do NOT delete (naming traps)

| Name pattern | Why |
| --- | --- |
| `*ServiceProvider` (Laravel) | Framework boot |
| `VoiceTransportConfiguration` | SIP **infrastructure**, not Flutter transport enum |
| `SessionProvider` (Realtime) | Call event adapter |
| `MobileVoiceEndpointRegistrar` | Extension registration — class name says “Registrar” but it’s domain logic |
| `TelephonyHealth` | Health projection — rename strings, don’t delete |
| `TwilioWebhookVerifier` | Still needed for SMS |

---

## Migrations (voice-related)

| Migration | Category |
| --- | --- |
| `2026_06_13_100000_create_telephony_endpoints_table` | Shared authority (legacy ring) |
| `2026_06_27_100000_create_telephony_extensions_table` | Shared authority (identity) |
| `2026_06_27_110000_add_asterisk_voice_to_shop_settings` | Asterisk |
| `2026_06_27_120000_add_telephony_provider_to_shop_settings` | **Legacy seam** |
| `2026_06_28_100000_add_mobile_voice_telephony_settings` | **Twilio Voice columns** (API key, TwiML app, FCM/APNs VoIP SIDs) |
| `2026_06_16_100000_add_telephony_call_flow_to_shop_settings` | Shared (hours, greetings) |
| `2026_06_*` call_sessions / media columns | Shared authority |

Do not drop columns until Phase D migration plan explicitly migrates or abandons rollback.

---

## HTTP routes & APIs

### Production voice ingress (Asterisk — keep)

| Route | Purpose |
| --- | --- |
| `POST /voice/call-events` | Asterisk → ARK call lifecycle |
| `POST /voice/call-media` | Media metadata |
| `POST /voice/device-registration` | Device check-in |
| `GET /voice/health` | Ingress health |

### Legacy Twilio Programmable Voice webhooks (Phase D delete)

15 routes under `/webhooks/communications/twilio/voice/*` — incoming, sip-outbound, status, dial-complete, ring-status, conference-*, staggered-expand, callback-answer, client-outbound/incoming, cell-whisper/accept, recording, voicemail.

### Mobile voice API (keep — Asterisk payloads)

| Route | Controller |
| --- | --- |
| `POST /api/mobile/telephony/voice-session` | `MobileTelephonyVoiceSessionController` |
| `POST /api/mobile/telephony/voice-connect` | `MobileTelephonyVoiceConnectController` |
| `POST /api/mobile/telephony/voice-answer` | `MobileTelephonyVoiceAnswerController` |
| `POST /api/mobile/telephony/voice-registration-event` | observation only |
| `GET /api/mobile/telephony/people` | blind transfer directory |

Shell `transport` key today: issued by `AsteriskMobileVoiceTransport::transportKey()` (not a client-side enum switch in this repo).

---

## Flutter (`ark-mobile` — separate repo)

**Not in `arksmsv2`.** Documented expected leftovers from [ark-phone-mission-v1.md](../mobile/ark-phone-mission-v1.md) and [ark-mobile-communications-authority-contract.md](../mobile/ark-mobile-communications-authority-contract.md):

| Search term | Expected finding | Phase |
| --- | --- | --- |
| `twilio_voice` package | Likely still in `pubspec` | **B — remove** |
| `TwilioVoiceTransport` | Alternate transport impl | **B — delete** |
| `VoiceTransport` interface + switch | Multi-transport selection | **B — delete** |
| `transport: 'twilio'` | Shell/demo payload | **B — remove** |
| `ArkVoiceTransport` | `sip_ua` → shop Asterisk WSS | **Keep — rename product copy to ARK Phone** |
| `voiceProvider` / `transportType` enums | Client-side runtime picker | **B — delete if present** |

**Do not delete** Flutter/Riverpod classes named `*Provider` unless they are **voice runtime selection**, not state injection.

Phase B rule: same as backend — **authority cleanup deploys with zero SIP packet change.**

---

## Call routing — north star (Phase C)

Owner should eventually edit **business behavior**, not PBX objects:

```text
Business Number
      ↓
Daytime  →  Edward · Benjamin · Front Desk
      ↓
After Hours  →  Voicemail
      ↓
Emergency  →  Edward Cell
```

Phase A renamed the tab to **Call routing** but the underlying model is still `telephony_endpoints` (Type / Target / Delay). Phase C ships `CallRoutingPolicy` + dialplan compiler **with floor parity** before retiring ring group authority.

---

## Recommended sequence

| Phase | Work | Deploy changes SIP? |
| --- | --- | --- |
| **A** ✅ | Owner Settings language | **No** |
| **A½** | Learn owner copy, health dead strings, admin doc labels | **No** |
| **B** | Flutter single transport | **No** (client only) |
| **C** | `CallRoutingPolicy` + compiler parity | **Only via controlled dialplan sync** |
| **C½** | Retire `TelephonyRingGroup` / endpoints as policy authority | **After signed parity** |
| **D** | Delete PV webhooks, TwiML, provider enum/shim | **Requires C½ complete** |

---

## Deploy discipline (carry through every cleanup PR)

> If a PR is labeled **authority cleanup**, it must be possible to deploy without changing a single SIP packet on the wire.

VVX certification remains the production gate. UI copy, dead class removal, and Flutter transport erasure qualify. Dialplan/compiler changes do not — those belong to explicit routing phases with parity checklists.

---

## Surprise finding

Backend mobile voice **already** collapsed to Asterisk-only in `MobileVoiceTransportManager`. The larger dead weight is the **Twilio PV webhook + TwiML + TelephonyProviderManager** layer (~40+ PHP classes, 15 routes) — still loaded, still guarded, still referenced in tests and rollback docs.

The UI cleanup was necessary but **insufficient** for the engineering acceptance test (“one voice architecture”). This inventory is the map for Phases B–D.
