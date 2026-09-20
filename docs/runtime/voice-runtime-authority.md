# Voice Runtime Authority

**Status:** Stock Core — provider-neutral domain only · **Updated:** 2026-08-31  
**See:** [ADR-0007](../engineering/adr/ADR-0007-stock-core-voice-transport-boundary.md)

---

## Stock Core

```text
Call session domain + ring-group intent + advisor ownership
        ↓
OutboundVoiceCallControl / TelephonyProvider contracts
        ↓
Not configured in stock Core (honest unavailable)
        — or —
Custom / managed transport implementation
```

Stock Core does **not** ship carrier SDKs, TwiML webhook stacks, or paste-credential Settings for voice.

Live PSTN requires a transport implementation outside stock Core (or a managed ARK service when offered).

Historical Twilio-native inventory notes remain under private foundry docs, not as the public shipping model.
