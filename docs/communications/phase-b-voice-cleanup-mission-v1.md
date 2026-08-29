# ARK Voice Cleanup Sprint — Phase B Mission

**Status:** APPROVED · **Priority:** P0  
**Objective:** Erase every trace of Twilio Programmable Voice from ARK and ark-mobile while preserving 100% production PBX behavior.

**Companion:** [communications-voice-cleanup-sprint-v1.md](./communications-voice-cleanup-sprint-v1.md) · [ark-mobile-voice-cleanup-inventory-v1.md](../mobile/ark-mobile-voice-cleanup-inventory-v1.md)

---

## Mission

We are **not** refactoring, modernizing, or introducing new architecture. We are **deleting an obsolete architecture**.

Twilio Programmable Voice is dead. Asterisk is the PBX. Twilio is only Elastic SIP Trunk (Carrier), SMS, and MMS.

---

## Product truth

```text
Customer → Twilio Elastic SIP Trunk → Asterisk → Extension → Endpoint (VVX / ARK Phone)

ARK Phone: ArkVoiceDialer → ArkVoiceTransport → sip_ua → Asterisk
```

If any code contradicts this model, assume obsolete until proven otherwise.

---

## Absolute rules

| # | Rule |
| --- | --- |
| 1 | No new abstractions without two production implementations |
| 2 | Keep **`ArkVoiceTransport`** — never rename to `AsteriskVoiceTransport` |
| 3 | Delete — do not deprecate, wrap, or leave for later |
| 4 | **No behavior changes** — cleanup only (see ark-cleanup-sprint-discipline.mdc) |

---

## Phase B1 — Inventory (no code changes)

Deliverable: [ark-mobile-voice-cleanup-inventory-v1.md](../mobile/ark-mobile-voice-cleanup-inventory-v1.md)

Columns: Item · Runtime Authority · Classification · Action · Proof · Why

Unknown → investigate → classify. **Zero Unknown at B1 sign-off.**

---

## Phase B2 — Mechanical erasure

Delete Dead · Rename Rename · nothing else.

**PR footer (required):**

```text
Deleted: XX files
Renamed: XX symbols
Behavior changes: 0
```

`twilio_voice`: remove code imports → build → APK launch → then `pubspec.yaml`.

---

## Protected (do not modify in Phase B)

Asterisk behavior · dialplan · PJSIP · SIP routing · registration · RTP · trunks · VVX provisioning

---

## Backend (Phase D — **not started**; observe first)

Run the shop after Phase B before deleting backend PV. See [voice-runtime-authority.md](../runtime/voice-runtime-authority.md#observation-period-before-phase-d).

**Phase D may delete:** Twilio PV · TwiML · VoiceGrant · voice webhooks · runtime selection · dead routes/controllers/services/docs

**Not allowed:** Asterisk execution layer changes

See [voice-runtime-inventory-v1.md](./voice-runtime-inventory-v1.md) for arksmsv2 classification.

---

## Acceptance counters (post-cleanup)

| Metric | Target |
| --- | --- |
| Alternative runtime paths | 0 |
| Transport selectors | 0 |
| Voice provider selectors | 0 |
| Voice factories | 0 |
| Twilio client runtime | 0 |
| **Voice runtime entry points** | **1** |
| Behavior changes | 0 |

---

## Acceptance test

New engineer clones both repos; within 30 seconds:

1. Customer call path: Trunk → Asterisk → Extension → Endpoint  
2. ARK Phone path: `ArkVoiceDialer` → `ArkVoiceTransport` → `sip_ua` → Asterisk  

No ProviderManager · Strategy · RuntimeSelector · Twilio Programmable Voice.

**Goal:** delete confusion, not lines of code — one obvious voice architecture.

> **Success measure:** The measure of success is not how much Twilio code was deleted. The measure of success is whether a new engineer can identify the entire production voice runtime in under 30 seconds without encountering obsolete architectural concepts.
