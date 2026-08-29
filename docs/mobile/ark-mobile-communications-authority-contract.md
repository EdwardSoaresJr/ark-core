# Mobile Communications Authority Contract

**Status:** Active  
**Audience:** ARK V2, ARK Mobile, ARK Voice, Communications  
**Companion:** [ark-mobile-projection-v1.md](./ark-mobile-projection-v1.md) · ark-telephony-roadmap.mdc

---

## Purpose

The Flutter mobile application must never know whether communications are delivered by Twilio, Asterisk, ARK Voice, Telnyx, or any future provider.

Mobile consumes ARK projections.

ARK owns communications authority.

Providers are transport only.

---

## Doctrine

The mobile application is a projection layer.

It is not a telephony application.

It is not an SMS application.

It is not a SIP client.

It is not a Twilio client.

It is not an Asterisk client.

**In-app voice exception:** Flutter embeds a **transport adapter inside `ArkVoiceDialer`** (ARK Voice / shop Asterisk WebRTC-SIP via opaque session payloads from `/api/mobile/telephony/voice-*`). Twilio Programmable Voice is **retired** for mobile. UI branches on server `dial_method` and `transport`, never on shop provider config.

Mobile displays and interacts with operational truth already stored inside ARK.

---

## Authority chain

```text
Conversation
ConversationMessage
CallSession
CommunicationEvent (later)
```

These objects are authoritative.

Everything else is projection.

Mobile must not introduce parallel stores (`SmsThread`, `AttentionItem`, provider inboxes, etc.).

---

## Mobile contract

Flutter communicates only with ARK APIs.

Examples:

```http
GET  /api/mobile/communications
GET  /api/mobile/communications/{id}
POST /api/mobile/communications/{id}/messages
POST /api/mobile/communications/{id}/internal-notes
POST /api/mobile/telephony/callback
POST /api/mobile/telephony/voice-session
POST /api/mobile/telephony/voice-connect
POST /api/mobile/telephony/voice-answer
GET  /api/mobile/notifications
```

Dial priority is **server-projected** on `/api/mobile/me`:

```json
{
  "dial_method": "in_app",
  "voice": {
    "in_app_ready": true,
    "transport": "twilio",
    "fallback": "shop_callback",
    "supports_inbound": true
  }
}
```

Flutter fallback chain when placing calls: **`in_app` → `shop_callback` → `native` (`tel:`)**. On in-app failure, retry the next method automatically.

Flutter must never communicate directly with:

- Twilio APIs
- Asterisk APIs
- SIP endpoints
- SMS gateways
- Provider webhooks

---

## Transport separation

ARK owns transport selection.

```text
Flutter
    │
    ▼
  ARK V2  (/api/mobile/*)
    │
    ▼
Communications authority  (Conversation, CallSession, SendOutboundMessageAction, …)
    │
 ┌──┼──────────────┐
 ▼  ▼              ▼
Twilio        Asterisk      ARK Voice
```

Provider selection is an implementation detail inside Laravel.

Changing providers must not require a Flutter rebuild.

---

## Required rule

Mobile must not contain:

```dart
TwilioClient
AsteriskClient
SipClient
TelnyxClient
```

or any provider-specific implementation **outside** the isolated `ArkVoiceDialer` transport adapter layer.

Allowed:

```dart
ArkVoiceDialer          // transport adapter; ARK session payloads only
CommunicationsRepository
MobileApi
NotificationsRepository
```

---

## Server-side provider abstraction (direction)

ARK should route outbound/inbound transport through an internal provider boundary — not expose it to mobile.

Example shape (conceptual; implementations live in `app/Ark/Operations/` today):

```php
interface CommunicationProvider
{
    public function sendMessage(...);

    public function placeCall(...);

    public function fetchVoicemail(...);

    public function fetchRecording(...);
}
```

Implementations may include `TwilioCommunicationProvider`, `AsteriskCommunicationProvider`, `ArkVoiceCommunicationProvider`.

ARK selects the provider.

Mobile does not.

**Current V2 path:** outbound SMS → `SendOutboundMessageAction` → `ConversationMessage`; telephony → `CallSession`. Mobile controllers call these actions only.

**ARK Voice direction:** desk phones, transfer, paging via Asterisk as transport — `docs/communications/ark-voice-vision.md`. Flutter boundary unchanged.

---

## Future features

These may be added later without changing the mobile architecture:

- SMS / MMS (customer reply via mobile — **uses existing message POST**)
- Voicemail
- Call recordings
- Call notes
- Click-to-call
- Push transport (optional `fcm_token` via `/api/mobile/device` — ARK-owned delivery; FCM/APNs transport only). **Deferred** until floor observation — see `docs/mobile/ark-mobile-notification-doctrine.md`
- ARK Voice
- Provider failover
- Multi-provider routing

All remain behind `/api/mobile/*` projections.

---

## Architectural test

If Twilio disappeared tomorrow and ARK switched entirely to Asterisk:

**Question:** Would the Flutter app require code changes?

**Desired answer:** No.

Only ARK transport implementations should change.

If Flutter must change, provider details have leaked across the authority boundary. That is a bug.

---

## Compliance checklist (review on every mobile comms PR)

| Check | Pass criteria |
|-------|----------------|
| Flutter imports | No Twilio/Asterisk/SIP REST clients or provider URLs outside `ArkVoiceDialer` |
| API surface | `/api/mobile/conversations*`, `/api/mobile/telephony/*`, `/api/mobile/notifications` |
| Voice path | Session/connect via `/api/mobile/telephony/voice-*`; `dial_method` from `/me` |
| Reply path | `POST …/messages` → server writes `ConversationMessage` |
| Thread body | `UnifiedOperationalTimeline` / conversation projection — not raw provider payloads |
| New feature | New mobile endpoint wraps existing authority — not a parallel inbox |

---

## Guiding principle

Mobile is a projection of ARK.

ARK is the authority.

Telephony providers are replaceable transport.

Protect this boundary at all costs.
