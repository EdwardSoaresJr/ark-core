# ARK Voice Vision

**Status:** Vision — observe before build  
**Audience:** ARK V2, ARK Mobile, shop floor hardware, communications  
**Sequence:** Authority → Observation → Transport → Projections

**Companions:** [communications-authority.md](../communications-authority.md) · [ark-mobile-communications-authority-contract.md](../mobile/ark-mobile-communications-authority-contract.md) · ark-telephony-roadmap.mdc · ark-authority-vs-configuration.mdc · ark-telephony-settings-doctrine.mdc · [ark-voice-phase1-spec.md](ark-voice-phase1-spec.md)

---

## Purpose

Demo Auto Repair needs more than SMS and cloud ring-to-cell.

The shop needs a **communications operating system**:

- Caller context **before** anyone answers
- Desk phones, mobile app, and shop display as **one system**
- Internal call transfers without cellular hops
- Shop paging (*80*, overhead speakers, bay announcements)
- One operational event → desk pop, mobile notification, display board, optional page

That is not "SMS messaging." It is shop voice infrastructure owned by ARK — with replaceable transport underneath.

---

## Guiding principle

**ARK should not know Twilio or Asterisk by name at the product layer.**

ARK should know **Communication Endpoints** and **what happened on them**.

Transport decides *how* a page is announced or a call is bridged. ARK decides *who* the caller is, *which RO* is active, and *what the shop should see*.

```text
Customer calls shop
        │
        ▼
   Transport layer        ← Asterisk today, Twilio hybrid tomorrow, ARK-native later
        │
        ▼
   ARK communications authority
        │
 ┌──────┼──────┬──────────┐
 ▼      ▼      ▼          ▼
Desk   Mobile  Display   Customer
Phone   App    Board     Portal
```

If Twilio disappeared and the shop moved entirely to Asterisk/FreePBX on-prem:

**Flutter, desk phone UI, and shop display should not require rewrites.**

Only transport adapters change.

---

## What this is not

| Not this | Why |
|----------|-----|
| ARK becomes FreePBX admin | Endpoint provisioning may use FreePBX; ARK does not rebuild extension/trunk UI |
| ARK builds a contact center | No IVR trees, hunt-group product, or SaaS comms vendor |
| Separate ARK Voice deployable | Legacy standalone `ark-voice` was decommissioned 2026-06-17; voice lives **inside ARK SMS runtime** |
| Flutter as SIP client | Mobile consumes `/api/mobile/*` projections — never Asterisk AMI or SIP directly |
| Replacing Conversation authority | Voice feeds `CallSession` + `Conversation`; it does not create parallel inbox stores |

**Asterisk/FreePBX is allowed as shop-local transport.** Building a phone company inside ARK is not.

---

## Authority (ARK owns)

These are operational truth. Transport emits events; ARK persists meaning.

| Authority | Owns today / evolves to |
|-----------|-------------------------|
| **Conversation** | Relationship timeline — SMS, email, messages |
| **ConversationMessage** | Human-readable comm acts |
| **CallSession** | Call lifecycle, ownership, handled, recording metadata |
| **CommunicationEvent** | Operational facts (missed call, voicemail received, estimate approved signal) |
| **TelephonyEndpoint** → **CommunicationEndpoint** | Desk phone, mobile app, cell, SIP leg, page target — *where* shop staff receive comms |
| **Extension** (future) | Internal dial plan identity mapped to endpoint + user |
| **Queue** (future) | Ring targets, stagger, overflow — operational ring policy, not PBX hunt UI |
| **PageGroup** (future) | Shop paging targets — bays, all-call, front counter, role groups |

Naming may evolve (`TelephonyEndpoint` → `CommunicationEndpoint`). The boundary does not: **endpoint is authority; provider extension number is transport detail.**

---

## Projections (surfaces read authority)

| Projection | Question it answers |
|------------|---------------------|
| **Front counter phone / pop** | Who is calling and what RO context before I answer? |
| **Flutter mobile app** | RO, findings, photos, messages on my assigned work — during or after a call |
| **Communications workspace** | Calls waiting, recovery, relationship context |
| **Shop display board** | Approved work, bay status, shop-wide announcements |
| **Customer portal** | What the customer sees — never internal paging or staff extensions |
| **Workboard / Attention** | Pressure from comms + workflow — not a phone log |

One event. Multiple projections. No duplicate authority.

**Example — RO approved:**

1. Customer approves estimate → `ApprovalEvent` + `CommunicationEvent`
2. Shop display updates approved posture
3. Flutter push to assigned tech (via ARK API)
4. Optional Asterisk TTS: *"Repair Order Fifteen Eighty-Six approved"*
5. Optional page to bay group

ARK emits once. Projections and transport adapters consume.

---

## Transport (replaceable)

| Transport | Role |
|-----------|------|
| **Twilio** (today) | PSTN ingress, SMS/MMS, ring-to-cell, Elastic SIP to endpoints |
| **Asterisk / FreePBX** (target) | Desk phones, internal extensions, blind/warm transfer, shop paging, overhead speakers, on-prem latency |
| **Hybrid** (likely) | Twilio PSTN in → Asterisk shop fabric → desk/mobile/SIP |
| **ARK Voice native** (later) | Custom transport only if Asterisk + adapters prove insufficient |

Provider selection is **shop infrastructure**, configured in **Settings → Communications** — not hardcoded per tenant and not `TELEPHONY_PROVIDER` in `.env`. See ark-telephony-settings-doctrine.mdc.

Existing seam: `TelephonyProvider` contract + `TwilioTelephonyProvider`. Future: `AsteriskTelephonyProvider`, unified ingress normalizer → `CallSession`.

Mobile contract unchanged: [ark-mobile-communications-authority-contract.md](../mobile/ark-mobile-communications-authority-contract.md).

---

## Floor scenarios (why Asterisk wins over Twilio-only)

### Incoming call — context before answer

```text
Incoming Call
719-555-1234

Mike Kindig
2017 GMC Sierra 2500

RO #1586 · Approved
Vehicle ID Needed
```

Pop appears on front counter **and** mobile **before** answer. Resolver reads Customer, Vehicle, open ROs, Conversation — same `CustomerCallContextResolver` path as today, richer projection.

### Technician mobile during production

Landon's Flutter app shows for the active / assigned RO:

- Approved work posture
- Vehicle history
- Findings + photos
- Customer messages

No browser. No desktop. Still **not** a SIP stack in Flutter — call control actions go through ARK API (`POST /api/mobile/calls/{id}/transfer` conceptual), transport executes.

### Call transfers (internal)

```text
Front Counter ──transfer──► Advisor ──transfer──► Technician
```

Stays on shop LAN / Asterisk bridge. No cellular double-hop. ARK records transfer as `CallSession` events + ownership changes.

### Shop paging

Twilio is not designed for overhead paging. Asterisk is.

| Action | Example |
|--------|---------|
| Dial *80 | Page all |
| Page group | *81 Bay 1, *82 Front counter |
| ARK-initiated | "Landon to Bay 1" after observation or advisor action |
| ARK + TTS | "Customer waiting at front counter" |

Paging is a **CommunicationEndpoint** target (`PageGroup`), not a new inbox.

---

## Architecture test

**Question:** Twilio gone tomorrow; Asterisk handles PSTN via SIP trunk. What changes?

| Layer | Changes? |
|-------|----------|
| Flutter `/api/mobile/*` | **No** |
| Desk phone pop UI | **No** (reads same projection) |
| `CallSession`, `Conversation` schema | **No** (maybe new metadata fields) |
| `TwilioTelephonyProvider` | **Replace** with Asterisk adapter |
| Shop display / paging integration | **Transport wiring only** |

If Flutter or Blade must learn Asterisk AMI, the boundary leaked.

---

## Build sequence (pressure first)

Do not install FreePBX because the vision doc exists.

```text
Observe floor pain
    → Surface in existing projections (pop, Calls Waiting, mobile RO context)
    → Measure (transfers failed? paging ad hoc? context after answer?)
    → Transport experiment (Asterisk trunk + one desk phone + one page group)
    → Authority extensions (PageGroup, transfer events) only when proven
```

| Phase | Delivers | Gate |
|-------|----------|------|
| **V0** | This document + endpoint vocabulary alignment | Now |
| **V1** | Richer incoming pop + mobile call context projection | T4 Twilio stable; mobile Phase 2+ |
| **V2** | Asterisk ingress adapter → same `CallSession` path | Shop pain: desk phones / paging / internal transfer |
| **V3** | Transfer + page API (ARK authority, Asterisk transport) | V2 on floor ≥ 30 days |
| **V4** | Shop display + observation-driven TTS/page hooks | One proven event (e.g. RO approved) |
| **V5** | Hybrid PSTN (Twilio in, Asterisk fabric) if needed | Operational measurement |

SMS/MMS priority remains per ark-telephony-roadmap.mdc. Voice expansion follows **observed** shop pain — not roadmap momentum.

---

## Relationship to current V2

| Today | Vision alignment |
|-------|------------------|
| `CallSession` | Stays call authority |
| `TelephonyEndpoint` (cell, SIP) | Seed of `CommunicationEndpoint` |
| `TelephonyProvider` | Expand to full voice transport interface |
| Incoming pop + Calls Waiting | Front counter + recovery projections |
| `SendOutboundMessageAction` | SMS transport — parallel pattern for voice actions |
| Decommissioned `ark-voice` app | Do not resurrect as separate product; voice layer merges into ARK SMS |

---

## Anti-patterns

- Flutter or desk UI calling Asterisk AMI directly
- `PageGroup` as a separate message store
- Rebuilding FreePBX extension admin inside ARK Settings
- Skipping observation → jumping to full PBX migration
- Letting provider SIDs become customer or RO identity
- New `AttentionItem` / SMS inbox / channel-first stores (see attention doctrine)

---

## Success metric

A customer call rings the shop. Before anyone speaks:

- Front counter sees name, vehicle, RO, posture
- Assigned tech sees the same RO on mobile if relevant
- Advisor can transfer internally without leaving ARK
- Manager can page a bay without yelling across the shop
- When estimate approves, the shop **hears** and **sees** it once

The shop runs communications. ARK owns truth. Asterisk (or Twilio) is wiring.

---

## Review checklist (future PRs)

- [ ] Does this add authority or projection?
- [ ] Could transport swap without Flutter changes?
- [ ] Does mobile stay on `/api/mobile/*` only?
- [ ] Is paging/transfer an endpoint action, not a new inbox?
- [ ] Was floor pain observed before transport work?
