# Certification record — Front Counter

**Certification:** Front Counter  
**Track:** A — Operations Platform  
**Owner:** Alex Rivera  
**Scenario source:** [operational-certifications.md](../operational-certifications.md) · [first-contact-floor-checklist.md](../../communications/first-contact-floor-checklist.md)

## Why this matters

After this certification, the shop can answer customer calls and texts entirely through ARK at the desk.

---

## PASS levels (dependency chain)

| Level | Status | Date | Signed by |
|-------|--------|------|-----------|
| Engineering Certified | ✓ | 2026-06-27 | Floor + production verification |
| Operationally Certified | ✓ | 2026-06-27 | Alex Rivera |
| Production Certified | ⬜ | | One week sustained live traffic |

## Capability checklist

| Check | Status | Date | Evidence | Proof |
|-------|--------|------|----------|-------|
| VVX provisions from ARK without manual SIP entry | ✓ | 2026-06-27 | Factory-reset path documented; device shows Connected | Shop → Communications device workspace |
| SIP registration to shop voice server | ✓ | 2026-06-27 | Ext 101 registered to `voice.demo-auto.test` | `pjsip show contacts` on production |
| Inbound PSTN via Elastic SIP Trunk → Asterisk | ✓ | 2026-06-27 | Twilio trunk origination to Asterisk; E.164 dialplan fix | Asterisk log: Twilio 54.172.60.x → `from-trunk` |
| Outbound PSTN via trunk termination | ✓ | 2026-06-27 | `twilio-out` PJSIP + 10-digit dialplan | CallSession `provider=asterisk` outbound completed |
| AMI → SessionEvents → CallSession | ✓ | 2026-06-27 | 17+ asterisk CallSessions including inbound completed | Production MySQL `call_sessions.provider=asterisk` |
| SMS/MMS unchanged on Twilio Messaging webhook | ✓ | 2026-06-27 | Automated texts continued after voice cutover | Operator observation 2026-06-27 |
| `*43` echo and feature codes intact | ✓ | 2026-06-27 | Dialplan includes feature-codes.conf before outbound patterns | `dialplan show from-internal` |

## Operational checklist

| Check | Status | Date | Evidence | Proof |
|-------|--------|------|----------|-------|
| Inbound cell → VVX rings at Front Counter | ✓ | 2026-06-27 | Operator confirmed on floor | User verification 2026-06-27 |
| Outbound VVX → PSTN connects | ✓ | 2026-06-27 | Operator confirmed after trunk URI fix | User verification 2026-06-27 |
| Shop settings `telephony_provider=asterisk` | ✓ | 2026-06-27 | Production shop_settings updated | Tinker / Settings UI |
| Shop → Communications shows Ready posture | ✓ | 2026-06-27 | Health projection uses ARK Voice ingress | Communications shop workspace |
| VVX350 idle appliance (station context on screensaver) | ⬜ | | Engineering cert criteria in `docs/communications/vvx350-idle-appliance-v1.md` | Right Phone floor stopwatch |

## Production criteria

| Criterion | Period | Status | Evidence | Proof |
|-----------|--------|--------|----------|-------|
| One week customer calls without Twilio voice rollback | 7 days | ⬜ | | |

## Suspension log

| Date | Level broken | Cause | Upper levels suspended |
|------|--------------|-------|------------------------|
| | | | |

## Notes

- Voice cutover: business number on Twilio Elastic SIP Trunk `Demo Auto Repair ARK Voice`; legacy Programmable Voice webhook cleared on the number.
- Rollback remains Twilio Console only — repoint Voice URL to legacy webhook; no ARK deploy required.
- Production Certified waits for one week of sustained PSTN on the trunk path without rollback.

## Corrections

- *(append only)*
