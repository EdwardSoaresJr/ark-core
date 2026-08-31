> **Status for public Core:** Superseded by [ADR-0007](ADR-0007-stock-core-voice-transport-boundary.md). Retained as historical foundry context only.

# ADR-0005: Twilio Native Voice Transport

**Status:** Accepted — Elastic SIP product naming corrected by [ADR-0006](ADR-0006-programmable-voice-sip-domain-not-elastic-sip-trunking.md) (do not restore Elastic SIP Trunking)

**Supersedes:** [ADR-0003](ADR-0003-asterisk-is-a-projection.md) (Asterisk execution layer retired)

## Context

Demo Auto Repair production ran a hybrid stack: Twilio SMS/MMS and Programmable Voice webhooks existed in ARK, but PSTN ingress and desk phones were routed through a shop Asterisk VPS. That split blocked floor certification, duplicated transport paths, and kept a `TelephonyProgrammableVoiceGuard` gate inactive whenever `telephony_provider=asterisk`.

ARK communications authority (`CallSession`, `Conversation`, `CommunicationEvent`, `UnifiedOperationalTimeline`) was already correct. The reset targets **transport only**.

## Decision

**Twilio Programmable Voice + Elastic SIP is the sole voice transport.**

| Layer | Owner |
| --- | --- |
| PSTN ingress | Twilio → `/webhooks/communications/twilio/voice/*` |
| Ring policy | ARK `TelephonyRingGroup` / `TelephonyIncomingCallFlow` (TwiML) |
| Desk phones (VVX) | Twilio Elastic SIP registration — not shop PBX |
| Mobile in-app voice | Twilio Client SDK via `TwilioMobileVoiceTransport` |
| Call truth | `CallSession` (unchanged) |
| Relationship truth | `Conversation` / `ConversationMessage` (unchanged) |

Asterisk PHP, AMI bridge, PJSIP/dialplan sync, and shop-PBX PSTN ingress are **removed**. `VOICE_SIP_REGISTRAR` points at the Twilio SIP domain for desk phone provisioning only.

## Consequences

- `TelephonyProgrammableVoiceGuard::isActive()` is always true; Twilio webhooks are production path.
- `telephony_provider` coerces to `twilio`; no `asterisk` enum case.
- VVX certification = Twilio Elastic SIP registration + TwiML ring — not Asterisk dialplan.
- `VoiceTransportConfiguration` reads `VOICE_SIP_REGISTRAR` only — no Asterisk env paths or bridge reload URLs.
- ADR-0003 remains historical; do not edit it. This ADR is the active transport decision.

## References

- [docs/runtime/voice-runtime-authority.md](../../runtime/voice-runtime-authority.md)
- [docs/communications/production-voice-cutover-v1.md](../../communications/production-voice-cutover-v1.md) (rollback: Twilio Console Voice URL)
