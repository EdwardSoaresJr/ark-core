# ARK Voice Phase 1 — Parallel Ingress Spec

**Status:** Active build contract  
**Doctrine:** doctrine `ark-authority-vs-configuration.mdc` · doctrine `ark-telephony-settings-doctrine.mdc`  
**Vision:** [ark-voice-vision.md](ark-voice-vision.md)

---

## Goal

Prove Asterisk can run **side-by-side** with Twilio without a PSTN cutover, without Flutter SIP, and without parallel call authority.

Phase 1 is **transport ingress + settings**, not full shop voice OS.

---

## SaaS constraint (non-negotiable)

> Can two shops have different telephony behavior without code changes?

Phase 1 must answer **yes** for everything it ships:

| Behavior | Where it lives |
|----------|----------------|
| Primary telephony provider | Settings |
| Asterisk listener on/off | Settings (`asterisk_voice`) |
| Extension registry | Settings → `telephony_extensions` |
| Ingress shared secret | `.env` (`ASTERISK_INGRESS_TOKEN`) only |
| Call lifecycle truth | Code → `CallSession` |

**Demo Auto Repair is bootstrap data, not the product model.** No hardcoded 101/102/103/104 in application code.

---

## In scope

### Authority (code)

- `CallSession` — single call authority for Twilio and Asterisk
- `CustomerCallContextResolver` — customer / vehicle / RO matching
- `TelephonyExtension` — per-shop extension registry (rows, not constants)
- `TelephonyProvider` contract + Twilio + Asterisk adapters

### Settings (per shop)

- **Communications → ARK Voice:** listener toggle, extension CRUD, health, webhook URL, test event
- **Communications → General:** primary telephony provider selection
- Extension fields: number, display name, optional staff owner, device type, location, enabled

### Ingress

- `POST /webhooks/communications/asterisk/call-events`
- Header: `X-Ark-Asterisk-Token`
- Body: `event`, `unique_id`, `caller_id`, `called_extension`, `direction`, optional `was_answered`
- Normalizer → `ProcessAsteriskCallEventAction` → same incoming/status pipeline as Twilio

### Projections

- `CallSessionCallerContextProjection` — staff API for pop fields
- `GET /app/api/telephony/call-sessions/{callSession}/caller-context`

### Tests

- Asterisk event lifecycle (ringing → answered → ended)
- Auth rejection (bad token, listener disabled)
- Twilio ingress unchanged when Asterisk enabled
- Settings save + simulate Asterisk call

---

## Out of scope (Phase 1)

| Deferred | Why |
|----------|-----|
| PSTN cutover | Parallel observation first |
| Flutter SIP / AMI | Mobile stays `/api/mobile/*` |
| `AsteriskCall` parallel table | Violates authority doctrine |
| Ring groups (named) | Settings surface Phase 2 — endpoints exist today |
| Page groups | Settings surface Phase 2+ |
| Routing tables (hours / overflow / voicemail routes) | Phase 2+ — hours exist; route *targets* do not |
| Caller pop field toggles | Phase 2 — projection prefs in settings |
| FreePBX admin UI | Transport provisioning stays outside ARK |
| Sync jobs / health automation | Authority adoption — observe first |

---

## Phase 2 preview (settings-first)

When floor pain justifies build, add **configuration surfaces** before automation:

1. **Ring groups** — named groups → `TelephonyEndpoint` membership
2. **Page groups** — named paging targets
3. **Route map** — business / after-hours / voicemail / overflow → group or endpoint
4. **Caller pop preferences** — which projection fields each shop shows

Each row passes the SaaS test: Shop A and Shop B differ without deploy.

---

## Parallel run model

```text
PSTN (today)          Shop LAN (Phase 1)
     │                        │
     ▼                        ▼
  Twilio webhooks        Asterisk listener
     │                        │
     └──────────┬─────────────┘
                ▼
           CallSession
                ▼
        Projections (pop, queue, mobile)
```

Twilio remains production PSTN. Asterisk proves internal event path and extension metadata.

---

## Done when

- [ ] Listener + extensions configurable per shop in Settings
- [ ] Provider selectable per shop (not `.env`)
- [ ] Real or simulated Asterisk events create `CallSession` with `provider=asterisk`
- [ ] Caller context API returns same shape as Twilio pop
- [ ] No Demo Auto Repair-specific constants in product code
- [ ] Tests green on SQLite CI

---

## Review checklist

- [ ] Behavior in Settings, truth in authority?
- [ ] Would Shop #2 need a code change for different extensions or provider?
- [ ] Any new enum on `CallSession` for Asterisk-only state? (reject)
- [ ] GET paths mutation-free?
