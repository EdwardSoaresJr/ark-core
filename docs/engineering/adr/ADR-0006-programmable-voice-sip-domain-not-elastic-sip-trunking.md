# ADR-0006: Programmable Voice SIP Domain — Not Elastic SIP Trunking

**Status:** Accepted

**Clarifies / corrects:** [ADR-0005 Twilio Native Voice Transport](ADR-0005-twilio-native-voice-transport.md) (Elastic SIP wording)

**Does not change:** CallSession / Conversation authority, Programmable Voice webhook ingress, or TwiML ring policy.

## Context

Older cutover notes and ADR-0005 (Twilio native) said “Twilio Programmable Voice + Elastic SIP.” That conflates two different Twilio products:

| Product | Role in ARK history |
| --- | --- |
| **Elastic SIP Trunking** | Carrier trunk into shop Asterisk / PBX — **retired** with `ark-asterisk` |
| **Programmable Voice SIP domains** | Desk phone (VVX) registration so ARK can `<Sip>` ring endpoints — **current** |

Operator Learn already states the rule: use SIP domains; do not use Elastic SIP Trunking.

## Decision

**Sole voice transport: Twilio Programmable Voice.**

- PSTN ingress: phone number Voice URL → ARK webhooks → TwiML
- Desk phones: register to a **Programmable Voice SIP domain** (`VOICE_SIP_REGISTRAR`)
- Mobile: Twilio Client SDK
- **Do not** provision, document, or restore **Elastic SIP Trunking** for ARK

Where older docs say “Elastic SIP” for desk phones, read **Programmable Voice SIP domain**.

## Consequences

- Agent rules, runtime authority, and deploy notes use “SIP domain,” not “Elastic SIP.”
- ADR-0005 (Twilio native) transport decision stands; its Elastic SIP phrasing is obsolete under this ADR.
- Asterisk + Elastic SIP Trunk remain decommissioned (`infra/coolify/asterisk/RETIRED.md`).

## References

- Learn: `resources/views/operations/learn/admin/telephony-sip-setup.blade.php`
- [docs/runtime/voice-runtime-authority.md](../../runtime/voice-runtime-authority.md)
