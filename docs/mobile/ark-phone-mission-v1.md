# ARK Phone Mission v1

**Status:** Engineering doctrine — research before code  
**Companions:** ark-phone-production-telephony-lock.mdc · [ark-voice-endpoint-architecture-v1.md](../communications/ark-voice-endpoint-architecture-v1.md) · `ark-mobile` repo

---

## Mission

**Stop inventing telephony.**

Research existing, production-proven implementations before writing code.

The objective is **not** to build "our own SIP stack."

The objective is to build a **Flutter endpoint that behaves exactly like a Poly VVX endpoint on an Asterisk PBX.**

---

## Production requirements

The mobile app must function as a **first-class business extension**.

| Capability | Required |
| --- | --- |
| SIP registration | Yes |
| Reliable reconnect | Yes |
| Incoming calls | Yes |
| Outgoing calls | Yes |
| Two-way audio | Yes |
| Hold / resume | Yes |
| Blind transfer (REFER) | Yes |
| Attended transfer | Yes |
| DTMF | Yes |
| Call waiting | Yes |
| Multiple simultaneous calls | Yes |
| Presence / BLF | Yes |
| Voicemail indication | Yes |
| Background operation | Yes |
| CallKit (iOS) | Yes |
| Android ConnectionService | Yes |
| PushKit / APNs VoIP | Yes |
| Stable for an entire business day | Yes |

---

## Engineering rule

**Research before coding.**

For every capability:

1. Find production implementations.
2. Read upstream documentation.
3. Read issue trackers.
4. Identify known limitations.
5. Decide whether to **adopt**, **wrap**, or **extend**.

Do not invent solutions where established implementations exist.

**Do not rely on generic web search alone.** Deliberately study well-maintained open-source telephony clients that have already solved registration, reconnect, CallKit, PushKit, transfers, and background survival — then adapt those patterns to ARK.

---

## Preserve production

Production Asterisk is **not** the experimentation environment.

Do **not** modify to make mobile work:

- desk phone transports
- Twilio routing
- production dialplans
- production PJSIP
- production trunks

Mobile must adapt to the PBX baseline. See ark-phone-production-telephony-lock.mdc.

**VVX first:** If mobile fails and VVX would succeed under the same conditions, the problem is in the mobile client until proven otherwise.

---

## Acceptance test

Edward can leave his desk with only his phone.

He can:

- answer inbound calls
- place outbound calls
- hold
- resume
- blind transfer to the VVX
- attended transfer
- receive transferred calls
- survive Wi‑Fi / LTE changes
- survive backgrounding
- survive a normal workday

…without breaking production desk phones.

**Until that passes, continue researching proven implementations instead of creating new telephony behavior.**

---

## Required research topics

Investigate current best practices and production implementations for:

| Topic | RFC / spec |
| --- | --- |
| Flutter SIP clients | — |
| SIP over WebSocket | RFC 7118 |
| Asterisk PJSIP WebRTC | Asterisk docs |
| SIP core | RFC 3261 |
| REFER (blind transfer) | RFC 3515 |
| Session timers | RFC 4028 |
| Call transfer events | RFC 5359 |
| Attended transfer | RFC 3891 (Replaces) |
| CallKit integration | Apple CallKit |
| PushKit / VoIP push | Apple PushKit |
| Android Telecom / ConnectionService | Android docs |
| SIP keepalive | OPTIONS / session-timer practice |
| ICE / STUN / TURN | WebRTC ICE |
| RTP handling | Media path in client SDK |
| Registration lifecycle | Expires, 401, reconnect |
| Background reconnect | OS lifecycle + push wakeup |
| Audio routing | Speaker, earpiece, BT, wired headset |

Document what is already solved **before** modifying ARK.

---

## Mature reference implementations (study first)

These are not endorsements to swap stacks blindly — they are **prior art** maintainers must read before inventing.

### Flutter / Dart SIP clients

| Project | Role | Study for |
| --- | --- | --- |
| **[sip_ua](https://pub.dev/packages/sip_ua)** / [dart-sip-ua](https://github.com/cloudwebrtc/dart-sip-ua) | Current ARK transport (`ArkVoiceTransport`) | WSS registration, INVITE, hold, REFER hooks, known reconnect bugs |
| **[flutter_webrtc](https://pub.dev/packages/flutter_webrtc)** | Media layer behind sip_ua | ICE, audio tracks, speaker routing limits |
| **Linphone SDK** ([linphone-sdk](https://gitlab.linphone.org/BC/public/linphone-sdk)) | Production mobile SIP reference | CallKit, ConnectionService, push, multi-call, BLF — **gold standard OSS mobile phone** |
| **PJSIP** ([pjsip.org](https://www.pjsip.org/)) | Stack under Asterisk + many clients | Registration, transfer, session timer behavior at the metal |

### Platform native telephony integration

| Project / API | Study for |
| --- | --- |
| **Apple CallKit** | Native incoming UI, lock screen, Bluetooth routing |
| **Apple PushKit (VoIP push)** | Background wakeup without polling WSS |
| **Android ConnectionService / Telecom** | System call UI, car mode, BT headset integration |
| **Twilio Voice SDK** (`twilio_voice` in ark-mobile) | Alternate transport — CallKit patterns on iOS; compare before reimplementing |
| **react-native-callkeep** | Bridge patterns for CallKit + ConnectionService (conceptual reference even if not adopted) |

### Server-side (read-only — do not experiment on production)

| System | Study for |
| --- | --- |
| **Asterisk PJSIP** | WSS transport, WebRTC codecs, endpoint templates VVX already uses |
| **Asterisk ARI / AMI** | Call state events, attended transfer server behavior — **observe**, do not become client dependency |
| **FreePBX / Asterisk transfer docs** | Attended vs blind dialplan semantics matching VVX |

### Prior art principle

> The fastest path usually isn't "write better code" — it's "understand why mature implementations handle registration, reconnects, CallKit, PushKit, and transfers the way they do, then adapt those patterns to ARK."

---

## Current ARK stack audit (2026-07-04)

Repo: `ark-mobile` · transport: `ArkVoiceTransport` → `sip_ua` over WSS to shop Asterisk.

| Capability | Status | Notes |
| --- | --- | --- |
| SIP registration (WSS) | **Partial** | Works when network stable; 20s timeout; teardown/reconnect under observation |
| Reliable reconnect | **Gap** | Lifecycle logger exists; no PushKit/FCM VoIP wakeup pattern yet |
| Incoming / outbound | **Partial** | Single-call model; UI banners on home shell |
| Two-way audio | **Partial** | WebRTC path via sip_ua |
| Hold / resume | **Implemented** | `call.hold()` / `call.unhold()` |
| Blind transfer (REFER) | **Implemented** | `call.refer()` — needs floor certification vs VVX |
| Attended transfer | **Missing** | Requires consult call + Replaces or Asterisk-native pattern — study Linphone + RFC 3891 |
| DTMF | **Missing** | Not exposed on transport |
| Call waiting / multi-call | **Missing** | Single `_activeCall` reference |
| Presence / BLF | **Missing** | `onNewNotify` stub |
| Voicemail indication | **Missing** | MWI / NOTIFY not handled |
| Background operation | **Gap** | No ConnectionService / CallKit integration |
| CallKit (iOS) | **Missing** | Required for production iOS phone replacement |
| Android ConnectionService | **Missing** | Required for native call UI + BT |
| PushKit / APNs VoIP | **Missing** | FCM exists for ops push — separate from VoIP push |
| Speaker routing | **Stub** | Log-only in `toggleSpeaker` |
| Twilio fallback transport | **Present** | `TwilioVoiceTransport` — study its CallKit path before reinventing |

**Next engineering moves should close gaps by adopting/wrapping proven patterns — not by writing new SIP semantics.**

---

## Decision framework

| Situation | Action |
| --- | --- |
| Capability exists in Linphone / Twilio SDK / sip_ua upstream | Read upstream; wrap or extend — do not rewrite |
| Capability exists only in native iOS/Android APIs | Thin Flutter platform channel or adopt SDK that already bridges |
| Capability requires server change | **Stop** — mobile adapts to VVX baseline unless VVX also needs it |
| No mature OSS pattern found | Document the gap; notebook observation — do not ship invented behavior |

---

## Related certifications

- VVX + ARK Phone: ark-phone-production-telephony-lock.mdc
- Phone-first shop week: [phone-first-shop.md](../product/certifications/phone-first-shop.md)
- Voice transport cert: [voice-transport.md](../product/certifications/voice-transport.md)
