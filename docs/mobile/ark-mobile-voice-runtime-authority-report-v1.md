# ark-mobile Voice Runtime Authority Report

**Status:** Phase B ship artifact · **Canonical:** [../runtime/voice-runtime-authority.md](../runtime/voice-runtime-authority.md) · **Baseline:** [../runtime/voice-baseline-v1.md](../runtime/voice-baseline-v1.md)

After Phase D, move to [../archive/voice-cleanup/](../archive/voice-cleanup/).

---

## Voice runtime (production)

```text
ArkVoiceDialer
        ↓
ArkVoiceTransport
        ↓
sip_ua
        ↓
Asterisk
        ↓
Twilio Elastic SIP Trunk   (carrier — arksmsv2 backend only)
```

## Acceptance counters

| Metric | Count |
| --- | --- |
| Alternative runtime paths | **0** |
| Transport selectors | **0** |
| Voice provider selectors | **0** |
| Voice factories | **0** |
| Twilio client runtime | **0** |
| **Voice runtime entry points** | **1** |

**Entry point:** `VoiceDialerBootstrap` → `ArkVoiceDialer.initializeSession` / `connectOutbound` only.

## Final audit (2026-07-04)

No Twilio Programmable Voice client artifacts remain in ark-mobile.

```text
Deleted: 7 files
Renamed: 0 symbols
Behavior changes: 0
```

**Result:** Pass — single path `ArkVoiceDialer → ArkVoiceTransport → sip_ua → Asterisk`.
