# Certification record — Voice Transport

**Certification:** Voice Transport  
**Track:** A — Operations Platform  
**Owner:** Alex Rivera  
**Scenario source:** [production-voice-cutover-v1.md](../../communications/production-voice-cutover-v1.md)

## Why this matters

After this certification, customer calls reach the shop through ARK-backed desk phones.

---

## PASS levels

| Level | Status | Date | Signed by |
|-------|--------|------|-----------|
| Engineering Certified | ✓ | 2026-06-27 | Production stack verified |
| Operationally Certified | ✓ | 2026-06-27 | Real inbound + outbound on VVX |
| Production Certified | ⬜ | | One week without rollback |

## Capability checklist

| Check | Status | Date | Evidence | Proof |
|-------|--------|------|----------|-------|
| `ark-asterisk` healthy, AMI bridge connected | ✓ | 2026-06-27 | `voice.demo-auto.test/health` ami_connected | Production health endpoint |
| Twilio Elastic SIP Trunk with origination URI | ✓ | 2026-06-27 | Trunk `TK5dbba969…` → `sip:voice.demo-auto.test:5060` | Twilio API |
| Termination credentials + IP ACL for outbound | ✓ | 2026-06-27 | Credential list + 203.0.113.10 ACL on trunk | Twilio API |
| SessionEvent ingress enabled | ✓ | 2026-06-27 | `asterisk_voice.ingress_enabled=true` | shop_settings |

## Operational checklist

| Check | Status | Date | Evidence | Proof |
|-------|--------|------|----------|-------|
| One real inbound PSTN call answered on VVX | ✓ | 2026-06-27 | Inbound CallSession `provider=asterisk` | call_sessions id 360+ |
| One real outbound PSTN call from VVX | ✓ | 2026-06-27 | Outbound completed asterisk sessions | call_sessions |

## Production criteria

| Criterion | Period | Status | Evidence | Proof |
|-----------|--------|--------|----------|-------|
| One week customer calls on trunk path | 7 days | ⬜ | | |

## Notes

- Inbound failure root cause (2026-06-27): Twilio sends E.164 `+17194136227`; dialplan `_X.` did not match — fixed with `_+X.`.
- Outbound failure root cause (2026-06-27): malformed quotes in rendered `pjsip-trunk.conf` contact URI — fixed.

## Corrections

- *(append only)*
