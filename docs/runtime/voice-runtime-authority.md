# Voice Runtime Authority

**Status:** Twilio-native reset · **Last verified:** 2026-07-05  
**Baseline:** Programmable Voice webhooks + Twilio Messaging + Programmable Voice SIP domain desk phones (not Elastic SIP Trunking)

---

## Runtime (production)

```text
Customer PSTN
        ↓
Twilio (carrier + Programmable Voice)
        ↓
POST /webhooks/communications/twilio/voice/incoming
        ↓
ProcessIncomingCallAction → CallSession
        ↓
TelephonyIncomingCallFlow (TwiML) → TelephonyRingGroup
        ↓
<Sip> desk · <Number> cell · <Client> mobile app
```

SMS/MMS uses the same Twilio account via Messaging webhooks → `ConversationMessage`.

Recordings and voicemail arrive on Twilio recording/voicemail webhooks → `CallSession` media fields.

---

## Authorities

| Authority | Owns |
| --- | --- |
| **CallSession** | Present call state, handled, ownership, recording/voicemail URLs |
| **SessionEvent** | Telephony lifecycle facts (started, answered, ended, …) |
| **Conversation** | Relationship scope by contact address |
| **ConversationMessage** | SMS/MMS and internal notes |
| **CommunicationEvent** | RO workflow facts (estimate sent/viewed, …) |
| **UnifiedOperationalTimeline** | Disposable thread projection — not authority |

Twilio is **transport only**. ARK Communications is the product.

---

## Desk phones (VVX)

Poly phones register to a Twilio **Programmable Voice SIP domain** via `/provision/{mac}`.  
Outbound from desk uses `POST .../twilio/voice/sip-outbound`.

**Not Elastic SIP Trunking** — that was the Asterisk/PBX trunk product and is retired.

No shop PBX. No Asterisk containers. No AMI bridge.

---

## Mobile voice

ARK Companion / Phone uses **Twilio Client SDK** (`TwilioMobileVoiceTransport`):

- `POST /api/mobile/telephony/voice-session` — access token
- Client webhooks: `client-inbound`, `client-outbound`

---

## Acceptance

| Check | Expected |
| --- | --- |
| Asterisk containers | **0** |
| `/voice/call-events` route | **removed** |
| Programmable Voice guard | **always active** |
| `telephony_provider` | **twilio** |
| PBX language in operator UI | **none** |

---

## Rollback

Twilio Console: point business number Voice URL at webhook or previous handler (< 5 min). No deploy required for PSTN rollback.
