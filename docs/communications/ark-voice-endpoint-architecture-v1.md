# ARK Voice — Endpoint Architecture v1

**Status:** Frozen baseline (2026-06-26)  
**Supersedes:** ad-hoc provisioning design in migration plan drafts v1–v3  
**Companions:** [communications-bounded-context-v1.md](communications-bounded-context-v1.md) · [ark-voice-vision.md](ark-voice-vision.md) · [production-voice-cutover-v1.md](production-voice-cutover-v1.md) · ark-projection-rule.mdc

**Change policy:** Architectural changes must justify departure from this document. Do not reopen fundamentals without floor evidence.

---

## Doctrine

> **A communication device never has business identity. It only has hardware identity. Business identity belongs to the workstation through telephony authority. Provisioning projects that authority onto the device.**

> **An endpoint is disposable. A workstation is persistent.**

If a technician drops a VVX350 off a ladder: remove device, scan new MAC, reprovision. Nothing else changes — no extension move, no customer data move, no call history move, no workstation change.

---

## Purpose

ARK Voice endpoint architecture defines how **communication devices** (desk phones, softphones, mobile legs, paging speakers, door stations) receive configuration derived from operational authority — without embedding business logic in Asterisk, vendor firmware, or static config files.

This is **endpoint provisioning**, not phone provisioning. The provisioning file (Poly `.cfg` today) is one **serialization** of endpoint state, not the whole model.

---

## Projection stack (consistent with ARK)

| Authority | Projection | Question answered |
|-----------|------------|-------------------|
| Customer | CustomerProjection | Who is this customer right now? |
| RepairOrder | RepairOrderProjection | What does this RO look like on this surface? |
| Conversation | ConversationProjection | What is the relationship posture? |
| CallSession | CallProjection | What is happening on this call? |
| CommunicationDevice + Telephony + Policies | **EndpointConfigurationProjection** | **What should this endpoint look like right now?** |

**EndpointConfigurationProjection is a first-class read model** — not a cache, not an optimization afterthought. It answers one question with deterministic, inspectable output.

---

## Authority inputs

EndpointConfigurationProjection is derived from authority and configuration policy. It does not own any of these.

| Input | Layer | Examples |
|-------|-------|----------|
| **CommunicationDevice** | Authority | MAC (hardware identity), model, workstation link, active flag, firmware reported |
| **Telephony** | Authority | Extension on workstation, SIP secret, endpoint identity |
| **Workstation** | Authority | Persistent desk identity, current operator (presence) |
| **Firmware policy** | Configuration | `communication_device_models` — min/recommended/latest |
| **BLF / button policy** | Configuration | Future — DSS keys, line keys |
| **Presence policy** | Configuration | Future — availability hints on device |
| **Locale / display policy** | Configuration | Future — timezone, language, wallpaper |

```
Telephony Authority
Communication Device Authority
Firmware Policy
BLF Policy
Button Policy
Presence Policy
        │
        ▼
EndpointConfigurationProjection     ← read model
        │
        ▼
ProvisionBuilder → vendor builder   ← serialization
        │
        ▼
.cfg (or other vendor format)
```

---

## Identity chain

Provisioning **reads**. Telephony **writes** identity.

```
Create Workstation
    ↓
Assign Extension to Workstation          ← Telephony (POST only)
    ↓
Create CommunicationDevice               ← hardware: MAC + model
    ↓
Assign device to Workstation
    ↓
Regenerate EndpointConfigurationProjection
    ↓
GET /provision/{mac}.cfg serves serialization
```

**Forbidden:** allocating extension numbers inside provisioning builders or on provision GET.

**MAC address** is hardware **identity** (lookup key), not authentication. Serve gates: device active, workstation assigned, extension exists, shop scope. Future auth: signed provision token or mTLS — not MAC-as-secret.

---

## EndpointConfigurationProjection

### Responsibilities

- Package all inputs once into a stable read model
- Store generated vendor config body (e.g. Poly `.cfg` text) plus metadata
- Enable: diff since yesterday, stale detection, admin inspect, rollback, audit trail
- Invalidate and regenerate when any input changes

### Regeneration triggers

Invalidate (mark stale → regenerate on next serve or eager rebuild) when:

- Workstation assignment changes
- Extension number or secret changes
- Firmware policy row changes
- Device model / builder changes
- Device `is_active` toggles
- BLF / button layout policy changes (future)
- Presence or locale policy changes (future)

### Persistence (Phase 1)

Table: `endpoint_configuration_projections` (or equivalent)

| Field | Purpose |
|-------|---------|
| `communication_device_id` | FK |
| `inputs_fingerprint` | Hash of authority inputs — detect stale |
| `serialized_config` | Vendor body (`.cfg` text for Poly) |
| `builder` | e.g. `poly` |
| `format` | e.g. `poly_cfg` |
| `generated_at` | |
| `superseded_at` | nullable — previous versions retained for rollback/history |

Current projection: latest row per device where `superseded_at` is null. History rows answer projection questions without treating storage as authority.

### Serve path

```
GET /provision/{mac}.cfg
  → find CommunicationDevice by MAC (shop-scoped)
  → 404 if missing
  → 403 if gates fail (inactive, no workstation, no extension)
  → if projection fresh (fingerprint matches inputs) → return serialized_config
  → else EndpointConfigurationProjection::regenerate($device) → persist → return
```

GET does not mutate telephony authority. Read/Write Rule stands.

---

## Endpoint provisioning (vendor serialization)

`ProvisionBuilder` dispatches by `communication_device_models.builder`:

```
ProvisionBuilder
    ├── PolyProvisionBuilder           ← VVX350 first implementation
    ├── YealinkProvisionBuilder        (stub)
    ├── FanvilProvisionBuilder         (stub)
    ├── CiscoProvisionBuilder          (stub)
    ├── SoftphoneProvisionBuilder      (future)
    ├── MobileProvisionBuilder         (future)
    ├── PagingSpeakerProvisionBuilder    (future)
    └── DoorStationProvisionBuilder    (future)
```

Vendor builders consume **EndpointConfigurationProjection inputs** (resolved extension, secrets, URLs, policy) and emit one serialization. The projection read model may later hold multiple serializations per device; Phase 1 ships Poly `.cfg` only.

---

## Onboarding workflows (shop policy)

Both supported. Claim flow is **optional** (`claim_flow_enabled` in shop communications settings).

| Mode | Flow |
|------|------|
| **Known device** | Admin enters MAC → assign workstation → plug in → serve projection → Connected |
| **Claim device** (Phase 2) | Unknown MAC → bootstrap → claim code on screen → assign workstation → reprovision → Connected |

Demo Auto Repair default UX: known MAC (phone in hand). Claim flow enables zero-touch at scale.

---

## Namespace (monolith today, product extractable later)

```
App/Ark/Communications/
├── Provisioning/
│   ├── EndpointConfigurationProjection.php    ← read model generator
│   ├── RegenerateEndpointConfigurationAction.php
│   ├── InvalidateEndpointConfigurationAction.php
│   ├── ProvisionBuilder.php
│   ├── Builders/
│   │   └── PolyProvisionBuilder.php
│   ├── ServeEndpointProvisionAction.php
│   └── EndpointProvisionController.php        ← GET /provision/{mac}.cfg
├── Telephony/
├── Presence/
└── Conversations/
```

Asterisk remains a **telephony projection** target (Phase 3), not authority.

---

## Phased roadmap

### Phase 1 — Device Identity

- `communication_devices` + MAC, firmware reported, `is_active`
- `communication_device_models` (firmware policy + builder)
- `telephony_extensions.workstation_id`
- `AssignExtensionToWorkstationAction`
- `EndpointConfigurationProjection` + Poly serialization
- `GET /provision/{mac}.cfg` with serve gates
- Shop UI: known-MAC workflow

**Production milestone:** zero-touch provisioning → device shows **Connected**. Not calls, ARI, or voicemail.

### Phase 2 — Device Management

- Claim flow (shop policy)
- Firmware file service
- BLF / button templates
- Admin: inspect projection, diff, stale devices, rollback

### Phase 3 — Telephony Projection

ARK is authority; Asterisk is projection (PJSIP today; other backends later).

- DB-backed secrets
- `ProjectTelephonyToAsteriskAction`
- Remove static PJSIP templates

### Phase 4 — Communications Engine

- ARI / Stasis, routing, presence, park, queue, paging, recording
- Conversation + CallSession integration

---

## Phase 1 schema summary

**`communication_devices`:** `mac_address`, `firmware_version`, `is_active`, `communication_device_model_id` (FK)

**`communication_device_models`:** slug, manufacturer, label, firmware columns, `builder`, `enabled`

**`telephony_extensions`:** `workstation_id`, optional `communication_device_id`, encrypted `secret` (Phase 3)

**`endpoint_configuration_projections`:** read model persistence per device + history

---

## Migration ordering

Laravel runs migrations in **filename order**. History must not look accidental.

| Original | Problem | Replacement |
|----------|---------|-------------|
| `2026_06_26_140001_add_workstation_fields_to_communication_devices.php` | Ran before `2026_06_30_100000_create_communication_devices_table.php` — FK to table that did not exist yet | `2026_06_30_105000_add_workstation_fields_to_communication_devices.php` |

**Why `06_26` became `06_30`:** The workstation FK migration was authored with a June 26 timestamp while `communication_devices` was created June 30. Fresh `migrate` failed. The fix is a **rename**, not a logic change — same columns, correct sequence after the create migration.

**Production:** If `2026_06_26_140001` already ran on a host, do not run the renamed file; reconcile manually. See [TECHNICAL_DEBT.md](../engineering/TECHNICAL_DEBT.md).

---

## PR scope boundaries

| PR | Delivers | Does not include |
|----|----------|------------------|
| **PR1** | Schema, `CommunicationDeviceModel`, projection table + Eloquent read model, MAC normalizer, **policy enums** (`EndpointProvisionBuilder`, `EndpointProvisionFormat`), `AssignExtensionToWorkstationAction`, workstation relationships | `ProvisionBuilder`, vendor builders, regenerate/invalidate actions, GET serve route |
| **PR2** | Projection regenerate/invalidate, `PolyProvisionBuilder`, `GET /provision/{mac}.cfg`, structured provision logging | Shop UI, floor test |
| **PR3A** | Observability — MAC, model, provision URL, projection fingerprint/timestamp, admin config preview | Workstation/extension assignment UX |
| **PR3B** | Assignment UX | — |
| **First Contact** | Factory-reset VVX350 → Connected (see [first-contact-floor-checklist.md](first-contact-floor-checklist.md)) | Firmware, ARI, dynamic Asterisk |

**Rule:** One milestone, one PR, one review. Files that have no caller in the current PR belong in the PR that first executes them. Policy enums on schema rows are PR1; execution classes are PR2.

---

## PR review checklist

- [ ] Device has hardware identity only; workstation + telephony owns business identity
- [ ] Endpoint is disposable; workstation is persistent
- [ ] EndpointConfigurationProjection is first-class read model, not "just cache"
- [ ] GET `/provision/*` never allocates extensions or mutates telephony authority
- [ ] MAC is identity with serve gates — not authentication
- [ ] Vendor builder selected via model table, not string matching
- [ ] Projection invalidated on all authority/policy input changes
- [ ] Phase 1 exit = Connected device, not working calls
- [ ] New code under `App/Ark/Communications/Provisioning/`

---

## Implementation entry

When building Phase 1, start with PR1 (schema + projection table + telephony workstation extension) → PR2 (projection regenerate + Poly builder + GET serve) → PR3 (shop UI) → PR4 (VVX350 floor proof).

This document is the authority for implementation.
