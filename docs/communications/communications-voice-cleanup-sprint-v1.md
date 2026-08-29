# ARK Communications Authority Cleanup Sprint v1

**This is not a Twilio cleanup. This is an authority cleanup.** Twilio Programmable Voice is the biggest casualty — not the mission.

**Status:** Phase A ✅ · Phase A½ ✅ · **Phase B ✅ (ark-mobile)** · **Observe** · Phase C/D gated  
**Runtime authority:** [docs/runtime/voice-runtime-authority.md](../runtime/voice-runtime-authority.md) · [runtime catalog](../runtime/README.md)  
**Hard rule:** Zero change in production telephony behavior until each phase explicitly says otherwise (dialplan, PJSIP, VVX, trunk routing, registration, call routing, Twilio trunk).

**Platform rules:** ark-two-implementations.mdc · ark-cleanup-sprint-discipline.mdc

---

## Non-negotiable rule (read before every phase)

> Every Twilio Programmable Voice abstraction — and every multi-provider voice abstraction — that exists solely because ARK once supported multiple voice runtimes should be **presumed obsolete**. Unless a **second production voice provider exists today**, **remove the abstraction** rather than replacing it with another abstraction.

Do not swap `TwilioTelephonyProvider` for a prettier `VoiceProviderInterface`. Delete shims that only existed to choose Twilio Voice vs Asterisk. **Do not delete a class because its name contains “Provider”** — inspect what it does. If it encapsulates Asterisk ingress, voicemail sync, provisioning, or recordings, **rename or simplify** it; do not amputate working infrastructure.

---

## Acceptance criteria (two tests)

### Engineering test

A new engineer clones ARK, spends 30 minutes in the voice codebase, and concludes:

1. ARK has **exactly one voice architecture**.
2. Twilio provides the **PSTN trunk** (messaging uses the same account).
3. **Call routing policy** is ARK authority — who rings, when, under what conditions.
4. **Asterisk is execution** — ARK compiles policy to dialplan; Asterisk runs it. Policy survives Asterisk internals changing (ARI, dialplan refactors, etc.).
5. **Identities** own **extensions**; multiple **endpoints** register to the same extension (Teams model).
6. **Move call** (same identity, different endpoint) and **transfer** (different identity) are distinct operations — not conflated in UI or authority.
7. There is **no evidence** Twilio Programmable Voice was ever a viable runtime — except migration history.

### Owner test

A shop owner configures **who answers the phone, in what order, under what conditions** without ever seeing:

SIP · PJSIP · UDP · TCP · WSS · TwiML · provider · transport · registrar · AOR · URI · endpoint (as a technology term)

**Extension numbers are fine.** Owners say “Ext. 101” and “Front Counter.” They do not need SIP stack vocabulary.

---

## Policy vs execution (non-negotiable separation)

```text
Business Number
        ↓
Call Routing Policy          ← ARK authority (owner edits)
        ↓
Asterisk PBX                 ← execution (ARK compiles policy → dialplan)
        ↓
Identity                     ← Edward · Front Counter · Benjamin
        ↓
Extension                    ← operational number (105 · 101 · …)
        ↓
Endpoints                    ← VVX · ARK Phone · Desktop · Web · Cell backup
```

| Layer | Role |
| --- | --- |
| **Call routing policy** | What the shop wants — business rules, not telephony |
| **Asterisk PBX** | How customer calls happen — dialplan, ARI, media; replaceable execution |
| **Identity** | Who on the floor — person or station role; **not** a phone |
| **Extension** | Numbered line identity registers against — one extension, many endpoints |
| **Endpoints** | Devices registered **as** that extension |

**Owner edits policy.** **ARK translates policy into Asterisk dialplan.** **Asterisk executes customer call control.** Identity + extension model must survive so **Move call**, **transfer**, and **multi-device ring** work without re-architecting later.

---

## Philosophy

**We are not removing Twilio.**

We are removing every parallel voice authority ARK accumulated while Twilio acted as a fake PBX.

Twilio remains in ARK as:

| Role | What it is | Owner sees |
| --- | --- | --- |
| **PSTN + SMS account** | Elastic SIP Trunk + Messaging API | **Business number** — connected ✓ |
| **Messaging** | SMS / MMS (and shared account credentials) | Part of Communications → Messaging |

Twilio is **not** voice routing, **not** mobile voice, **not** desk phone registration, **not** a shop-configurable “provider.”

---

## Identity model (locked — enables Move, Transfer, multi-device ring)

**People are not the authority. Identities are.** Edward is not a phone. Edward is an **identity** that owns an **extension**. Every device registers **as that extension**.

```text
Identity: Edward
    Extension: 105

    Endpoints (all register as Ext. 105)
      ✓ VVX desk phone        · Registered
      ✓ ARK Phone             · Registered
      ○ Desktop softphone     · Offline (future)
      ○ Web client            · Offline (future)
```

**Incoming customer call** (routing policy targets identity/extension):

```text
Business number → Routing policy → Extension 105
                                      ↓
                              VVX · ARK Phone · Desktop
```

Edward answers on whichever device he chooses — same model as Teams, RingCentral, Zoom Phone. Policy targets **extension 105**, not a single SIP URI or one device.

**Do not** model Edward as “cell endpoint” vs “desk endpoint” as separate routing targets when both are Edward. Cell **backup** in policy is a **condition** on the same identity, not a second identity.

This sprint must **not** bake in single-device-per-user assumptions that block shared extension registration.

---

## Call control vs intercom (separate products)

Two capabilities. Different architecture. Do not route intercom through Asterisk or fake it as phone calls.

### Call control — Asterisk (customer voice)

Owned by **Voice** authority. PBX execution.

| Capability | Same identity? | Example |
| --- | --- | --- |
| Inbound / outbound customer calls | — | Shop line → policy → extension |
| Hold | — | Customer on hold |
| **Move call** | **Yes** | Edward on ARK Phone → **Move to desk phone**; customer unaware; same Ext. 105, same dialog |
| **Transfer (blind / attended)** | **No** | Edward (105) → Benjamin (106); advisor → technician |
| Voicemail · parking · pickup | — | PBX features |

**Move call ≠ Transfer.** Different floor problems → **different buttons** in ARK Phone.

- **Move call:** same identity, different endpoint (mobile → VVX while walking inside).
- **Transfer:** different identity, different extension, different owner of the conversation.

Cleanup sprint **does not** ship Move/Transfer UI — but **identity + shared extension registration** is a hard prerequisite. Do not delete or simplify extension model in ways that assume one device per staff user.

### Intercom — ARK realtime (operations voice)

**Not SIP. Not Asterisk. Not a phone call.**

Push-to-talk **chirp** for shop floor coordination — advisors, techs, bays — without GMRS radios, without dialing, without ringing.

```text
Operations
    ↓
Intercom
    ↓
Identities / Groups
    (Advisors · Technicians · Management · Everyone)
```

Hold 🎤 → speak → release. Low latency (WebRTC / Opus over WebSocket). Presence-aware. Group targets.

Technicians often **should not** receive inbound customer calls; they **do** need “Edward: come to Bay 3” and “Ben: need approval on the Silverado.” That is **intercom**, not call routing policy.

**Out of scope for Phases A–D** (authority cleanup). Document now so cleanup does not collapse Voice and Intercom into one blob or implement chirp as Asterisk intercom/paging unless explicitly chosen later.

---

## ARK Phone shell (north star — post-cleanup)

ARK Phone is not “Twilio mobile.” It is the staff communications + operations surface:

```text
☎ Business calls      ← Asterisk / call control
📢 Intercom           ← ARK chirp (future)
💬 Messages           ← Conversation authority
👥 Presence
📋 Finish work
🚗 Customer context
```

First four are communications; last two are what make ARK different on the floor. This sprint clears the legacy voice stack so **Business calls** rests on identity + policy + Asterisk without Twilio-shaped holes.

---

## Voice authority (locked)

One authority model. Policy and execution are separate layers (see above).

| Layer | Owns | Owner question |
| --- | --- | --- |
| **Business number** | What customers call | “What’s our shop line?” |
| **Call routing policy** | Who rings, when, under what conditions | “When someone calls, who should ring — and when?” |
| **Identity** | Edward, Front Counter, Benjamin — **not** a device | “Ring Edward.” |
| **Extension** | Number the PBX rings (101, 105, …) | “Edward is Ext. 105.” |
| **Endpoints** | Devices registered as that extension | “Edward’s phone is online.” |

### Identity owns extension; endpoints attach

```text
Identity: Edward
  Extension: 105

  Endpoints
    ✓ ARK Phone
    ✓ Desktop (future)
    ✓ VVX (optional shared desk)
    ✓ Cell backup (policy condition only)
```

Routing policy, move call, voicemail, DND, and presence key off **identity / extension** — not device MAC, not SIP URI, not “Edward’s cell” as a parallel identity.

Nobody edits SIP URIs. Nobody picks a PBX in settings.

---

## Call routing — capability stays, implementation changes

### What we are NOT doing

We are **not** eliminating the ability to choose which phones ring and when. Shops will always need:

- Who rings first?
- Who rings after hours?
- What if someone is already on a call?
- When does voicemail pick up?
- Should technicians receive inbound customer calls?
- Saturdays only ring mobile; lunch ring Molly first; skip desk after hours; skip ARK Phone if not registered; cell backup only if mobile offline.

Those are **business rules**. ARK must own them.

### What we ARE doing

**Legacy Twilio ring groups → retired.**  
**Replaced by call routing policy** expressed in shop language — not Twilio execution vocabulary.

| Twilio model (retire) | ARK model (keep + strengthen) |
| --- | --- |
| Incoming number → TwiML → `<Dial>` → SIP URI / cell | Business number → **call routing policy** → extensions → endpoints |
| Ring Group tab + `sip:desk1@….sip.twilio.com` | **Call Routing** tab + named targets + delays + conditions |
| `TelephonyEndpoint` + destination field | Policy rows referencing **identities / extensions** |
| Stagger / whisper / TwiML verbs | Policy steps Asterisk dialplan sync consumes |

**Behavior is still a ring group.** VVX, ARK Phone, cell backup, and voicemail can all still participate — often with **more** expressiveness than today, because conditions become first-class (registered, offline, after hours, role, etc.) instead of hidden in Twilio plumbing.

### Call routing policy — where ARK differentiates

Policy steps are **business rules**, not telephony verbs. Example (business hours):

```text
Ring Front Counter immediately

If unanswered after 20s
  → Ring Edward

If Edward is already on a call
  → Skip Edward

If ARK Phone offline
  → Ring cell backup

If nobody answers after 60s
  → Voicemail
```

Same expressiveness shops need: after-hours paths, lunch routing, “technicians never receive inbound customer calls,” Saturday mobile-only, simultaneous vs stagger — all **policy**, compiled to Asterisk.

### Parity safeguard (engineering)

**Legacy ring group configuration cannot be removed until every existing routing behavior can be represented by call routing policy.**

Maintain a parity checklist before retiring `TelephonyRingGroup` / `telephony_endpoints` authority: stagger tiers, simultaneous ring, cell + SIP, whisper (if still used), after-hours bypass numbers, holiday/closed dates, presence gating, voicemail timeout, **multi-endpoint same extension**, etc.

**Future capabilities (document in parity appendix, not required for Phase C½):** move call (same extension), blind/attended transfer (cross-identity). Cleanup must not regress or foreclose them.

Do not discover six weeks later that Twilio ring groups supported something the policy editor does not yet express.

### Owner UI target — Call Routing tab

Preferred name: **Call Routing** (alternates: Incoming Calls · Call Flow · Call Dispatch).

```text
Call Routing

Business number
  719-555-0100 · Connected ✓

Dispatch policy

  ✓ Front Counter (Ext. 101)
      Immediately

  ✓ Edward (Ext. 105)
      After 20 seconds
      Ring all registered endpoints

  ✓ Benjamin (Ext. 106)
      After 35 seconds

  ✓ Edward · Cell backup
      After 45 seconds
      Only if ARK Phone offline

  ✓ Voicemail
      After 60 seconds
```

No SIP URIs. No TwiML. No `<Dial>`. No “target” column. Just business behavior.

### Where Asterisk fits (execution only)

Asterisk is the PBX layer between policy and ringing devices. ARK compiles policy to dialplan (today `MobileVoiceInboundDialplanSync`; future ARI). Asterisk executes:

- Ring extension 101 immediately.
- Ring extension 105 after 20s; skip if busy or offline per policy.
- Cell backup per policy conditions.
- Voicemail after 60s.

Shop staff never edit dialplan. Engineers tune execution; owners edit policy.

### Implementation retirement (Phase C+ — after parity)

- Introduce **`CallRoutingPolicy`** (name TBD) as **ARK authority** backed by extensions + conditions.
- **Parity first:** policy editor + compiler match legacy ring behavior on the floor.
- **Then** retire **`TelephonyRingGroup`** + **`telephony_endpoints`** as routing authority and TwiML-oriented paths — not before.

**Never hardcode** “desk only” or “everyone rings.” Every shop differs. Policy is configuration, not code.

---

## Communications split (internal model)

```text
Communications
├── Messaging
│      SMS · MMS · Email · Facebook Messenger
│
├── Voice (customer calls — Asterisk execution)
│      Business number · Call routing policy
│      Identities · Extensions · Endpoints
│      Business hours · Voicemail · Recording · Move · Transfer
│
├── Operations (future — not Asterisk)
│      Intercom / chirp · Groups · Bay paging
│
└── Platform credentials (not owner Voice)
       Twilio account (messaging + trunk)
       Elastic SIP Trunk (engineer/deployment)
```

**Do not expose “Carrier” to owners.** They bought a business number.

---

## Owner vocabulary (hard ban)

Owners and advisors must **never** see:

SIP · PJSIP · UDP · TCP · WSS · Registrar · AOR · URI · TwiML · Programmable Voice · Voice API · Voice Provider · Voice Token · Voice Grant · Voice SID · **Ring group** (legacy tab name) · Primary telephony provider · transport · endpoint (as tech jargon)

Owners **do** see:

**Business number · Call routing · Desk phone · Mobile phone · ARK Phone · Ext. 101 · Online · Offline · Registered · Voicemail**

Extension **numbers** and station names are shop language — not “extension technology.”

Engineering implementation stays in `infra/`, provisioning, and collapsed engineer toggles.

---

## Owner UI (other surfaces)

### Voice status (General tab)

```text
Voice · Connected ✓
Desk phones · 1 online
Mobile phones · 2 registered
```

**Infrastructure** (engineer only, collapsed): Twilio SIP Trunk · configured

### Shop owns

- Business number  
- **Call routing policy** (who rings, when, conditions)  
- Business hours  
- Voicemail · recording  

### Mobile settings tab

| Current | Target |
| --- | --- |
| ARK Voice (SIP) | **ARK Phone** |
| Push transport | **Push notifications** |

### Simulate inbound call

Keep; rename **Simulate inbound call**.

---

## Terminology migration (PR gate)

| OLD | NEW |
| --- | --- |
| Twilio Voice · Programmable Voice · TwiML · Voice API · Voice Grant · Voice Token | *(gone)* |
| Primary telephony provider · provider dropdown | *(gone)* |
| Ring Group (tab) · SIP URI target · endpoint destination | **Call routing** · **dispatch policy** · extension + conditions |
| Carrier (owner UI) | **Business number** |
| ARK Voice (SIP) | **ARK Phone** |
| `TelephonyRingGroup` / `telephony_endpoints` (routing authority) | **`CallRoutingPolicy`** (or equivalent) compiled to dialplan |
| `TwilioTelephonyProvider` · TwiML webhooks | **Deleted** (Phase D) |
| Move call · Transfer | **Move** = same identity/endpoint family · **Transfer** = different identity |
| Intercom · chirp · push-to-talk | **Operations / Intercom** — ARK realtime, not PBX |
| `TelephonyProvider` / `ProviderManager` | **Audit first** — delete if Twilio-vs-Asterisk shim only; rename if Asterisk service facade |

---

## Sprint scope boundary

| In Phases A–D (this sprint) | Documented now · build later |
| --- | --- |
| Authority cleanup · policy · identity model | Intercom / chirp |
| Retire Twilio Programmable Voice | ARK Phone intercom tab |
| Call routing policy editor (parity first) | Group push-to-talk |
| Shared extension registration (must not regress) | Move call UI · Transfer UI polish |
| Flutter single `ArkVoiceTransport` | Desktop / web softphone endpoints |

**Can Edward move a live call from ARK Phone to VVX?** Required long-term via **Move call** (same Ext. 105). Not a Phase A deliverable — but Phase A–D must not assume one device = one extension or one routing target per person.

---

## Flutter — one voice stack (business calls only)

**Phase B goal is not "remove Twilio."** The goal is: **there is exactly one voice runtime inside ark-mobile.**

Delete multi-transport selection (`VoiceTransport` interface, `TwilioVoiceTransport`, transport enum, shell `transport` key). **`ArkVoiceDialer` → `ArkVoiceTransport` only.** Product name: **ARK Phone**. Intercom/chirp is a separate realtime stack — not another `VoiceTransport`.

**Banned in Phase B:**

- Replacing `TwilioVoiceTransport` with `VoiceTransportInterface` + `ArkVoiceTransport` (abstraction swap)
- Renaming `ArkVoiceTransport` → `AsteriskVoiceTransport` (ARK owns the client; Asterisk is the PBX it speaks to)
- Any B2 work before B1 inventory is complete and reviewed

### Phase B1 — Inventory (no code changes)

Walk the entire **ark-mobile** repo (sibling to `arksmsv2`). **Zero code changes.** Deliverable: [ark-mobile-voice-cleanup-inventory-v1.md](../mobile/ark-mobile-voice-cleanup-inventory-v1.md).

Search terms (minimum):

- `twilio_voice`
- `TwilioVoiceTransport`
- `VoiceTransport`
- `transport:`
- `voice_provider`
- `provider:`
- `strategy`

#### Classification schema (every row)

| Classification | Meaning | Allowed action |
| --- | --- | --- |
| **Dead** | Proven unused | Delete |
| **Legacy** | Still referenced; scheduled for retirement | Keep (document why) |
| **Infrastructure** | Production runtime | Keep |
| **Future** | Planned phase (CallKit, ConnectionService, …) | Keep |
| **Rename** | Good implementation, wrong vocabulary | Rename only |
| **Unknown** | Cannot prove yet | **Investigate** — must not remain at B1 sign-off |

#### Unknown escalation (B1 incomplete until resolved)

Unknown is not a parking lot:

```text
Unknown → Who calls it? → Runtime trace → Classify
```

Every Unknown must become Dead · Legacy · Infrastructure · Future · Rename before B1 sign-off.

#### Inventory columns

| Item | Runtime Authority | Classification | Action | Proof | Why |

**Runtime Authority** — who owns this (not only *can I delete it?*):

Mobile Voice · SIP · PBX · Carrier · Messaging · Product · None (dead)

**Proof** = evidence (grep, caller chain, runtime path) — not intuition.

Example rows:

| Item | Runtime Authority | Classification | Action | Proof | Why |
| --- | --- | --- | --- | --- |
| `ArkVoiceTransport` | Mobile Voice | Infrastructure | Keep | Created by `ArkVoiceDialer`; active path | Production client runtime |
| `sip_ua` | SIP | Infrastructure | Keep | Called by `ArkVoiceTransport` | SIP stack |
| `TwilioVoiceTransport` | None (dead) | Dead | Delete | No references after grep | Obsolete |
| `VoiceTransportFactory` | None (dead) | Dead | Delete | Only returns `ArkVoiceTransport` | Two-implementation rule |

#### Delete gate (mandatory)

Before **Dead** / **Delete**, answer with proof:

1. **Who calls this?**
2. **What production behavior depends on it?**
3. **What replaces it?** (or "Nothing.")

Cannot answer all three → **Unknown** → escalate. Never guess from naming.

#### Two graphs (B1 required)

**1. Production graph** — 30-second engineer test:

```text
ARK Phone
        │
        ▼
ArkVoiceTransport          (Mobile Voice)
        │
        ▼
sip_ua                     (SIP)
        │
        ▼
Asterisk                   (PBX)
        │
        ▼
Twilio Elastic SIP Trunk   (Carrier — backend)
```

**2. Dead graph** — B2 deletion checklist. **When empty, Phase B is over.**

```text
TwilioVoiceTransport → (no callers)

VoiceTransportFactory → ArkVoiceTransport → (one implementation)
```

**B1 acceptance:** Every hit classified with Proof and Runtime Authority. **Zero Unknown rows.** Both graphs complete. Reviewer approves B2 without opening ark-mobile.

### Phase B2 — Erasure (mechanical only)

Only after B1 inventory is merged and reviewed.

- Every row **Delete** → disappears (code first for packages — see below)
- Every row **Rename** → renamed
- Delete dead-graph nodes until graph is **empty**
- **Nothing else**

**B2 PR may contain only:** deletions · renames · import fixes · tests.

**Every B2 PR must end with:**

```text
Deleted: XX files
Renamed: XX symbols
Behavior changes: 0
```

Exactly zero — not expected. Cannot honestly write that line → different sprint.

**Automatic fail:** reconnect logic · retry timing · SIP parameters · UI behavior · provider logic · lifecycle changes.

**Package order (`twilio_voice`):**

1. Remove all code importing `twilio_voice`
2. Prove app builds, APK launches, voice path unchanged
3. **Then** remove from `pubspec.yaml`

Forbidden in B2: architecture, SIP fixes, registration work, UI redesign, backend changes in `arksmsv2`.

### Phase B acceptance (engineering — 30 second test)

Can a new engineer clone **ark-mobile**, open the voice codebase, and determine the voice runtime in **under 30 seconds**?

The answer must be immediate — not a treasure hunt through:

```text
VoiceProvider · VoiceTransport · TransportStrategy · RuntimeSelector · ProviderFactory
```

It must be:

```text
ArkVoiceTransport
        ↓
sip_ua
        ↓
Asterisk
```

Nothing else. No enums. No transport switching. No factories. No provider selection. No fallback.

**Out of scope for Phase B:** `TelephonyProviderManager`, `TwilioTelephonyProvider`, Asterisk dialplan/PJSIP, PV webhooks, trunk config.

---

## Backend — provider classes (nuanced)

**Delete without mercy:**

- Twilio Programmable Voice stack (TwiML, voice webhooks, `TwilioVoiceApi`, VoiceGrant paths)
- Shop **provider selection** UI and `TelephonyProviderType::Twilio` rollback path (Phase D)
- `TelephonyRingGroup` as Twilio ring executor — **replace** with policy → dialplan compiler

**Audit before delete:**

- `TelephonyProvider` · `TelephonyProviderManager` · `AsteriskTelephonyProvider`  
  If the class only switches Twilio Voice vs Asterisk → **delete**.  
  If it groups Asterisk ingress, media, or provisioning services → **rename** (e.g. `AsteriskVoiceIngress`, `VoiceDialplanSync`) and drop the “pick a provider” seam.

**Keep:**

- `TwilioWebhookVerifier` (SMS)  
- Messaging + trunk infra  
- `telephony_extensions` · provisioning · `CallSession`  
- Dialplan sync as **execution layer**, not policy authority  

---

## Phase plan (behavior-safe)

### Sequencing (locked)

```text
Phase A     ✅  UI vocabulary (arksmsv2)
Phase A½    ✅  Dead owner language (learn + TelephonyHealth)
────────────────────────────────────────
Phase B     ✅  ark-mobile runtime collapse
────────────────────────────────────────
Observe     ← YOU ARE HERE (no voice code; log in voice-baseline-v1.md)
────────────────────────────────────────
Stabilize       ≥1 uneventful business week — no telephony commits
────────────────────────────────────────
Baseline frozen ← known-good date in voice-baseline-v1.md
────────────────────────────────────────
Phase C         Call Routing Policy (arksmsv2)
────────────────────────────────────────
Phase D         Delete backend PV runtime (one-way door; rollback closed)
```

**Monday success:** Did the shop **forget about telephony?** Boring is the feature.

**Do not start Phase D** until Observe + Stabilize pass: ARK Phone registered · reconnect OK · VVX boring · no hidden transport dependencies · uneventful week · baseline frozen.

**Phase D gate:** VVX stable · ARK Phone not destabilizing production · rollback to Programmable Voice explicitly closed · **Stabilize complete** · baseline signed off.

### Phase A — Language + settings erasure ✅

- Business number + voice status UI; banned terms removed  
- Remove Primary telephony provider, Programmable Voice credentials, Twilio desk-phone guide  
- Rename/hide legacy **Ring Group** tab toward **Call Routing** (v1 may be read-only projection of current behavior)  
- Mobile tab: ARK Phone · Push notifications  

**Acceptance:** Owner sees call routing vocabulary; routing behavior unchanged.

### Phase A½ — Learn + health dead code ✅

- Stale Learn copy (ARK Voice, Twilio-now-PBX-later)  
- `TelephonyHealth` PV mobile / TwiML client strings removed  

**Acceptance:** Same as hard rule — no SIP/PJSIP/dialplan/trunk/VVX/provisioning/registration change.

### Phase B — Flutter erasure (ark-mobile only)

**B1 — Inventory (no code):** [inventory doc](../mobile/ark-mobile-voice-cleanup-inventory-v1.md) — Runtime Authority · Proof · Unknown escalation · production + dead graphs. Zero Unknown at sign-off.

**B2 — Erasure (mechanical):** Per inventory; empty dead graph = Phase B done. PR closure: `Behavior changes: 0` — cleanup sprint discipline.

- Single production path: **`ArkVoiceTransport`** (not `AsteriskVoiceTransport`)
- Remove Twilio mobile SDK and all transport selection
- **No** arksmsv2 backend provider collapse in this phase

**Acceptance:** 30-second engineer test (Flutter section); floor unchanged; VVX unchanged.

### Phase C — Call routing policy authority (capability preserved)

- Ship **`CallRoutingPolicy`** editable in shop terms  
- Compiler: policy → Asterisk dialplan; **prove parity** against legacy ring group behavior on floor  
- Remove dead Programmable Voice **client** code  
- **Do not** retire legacy ring group authority until parity checklist is signed off  

**Acceptance:** Owner test passes on Call Routing UI. All phones that ring today still ring. Parity document exists.

### Phase C½ — Legacy ring group retirement (gated)

- Retire `telephony_endpoints` / `TelephonyRingGroup` as **policy authority** only after Phase C parity  
- Migrate stored config into policy rows  

**Acceptance:** No regression vs pre-retirement routing matrix.

### Phase D — Twilio voice runtime erasure (gated)

**Do not start until:**

- VVX has been stable on the floor  
- ARK Phone is no longer destabilizing production  
- Rollback to Programmable Voice is explicitly closed (not "probably won't need it")

Once Phase D deletes `TelephonyProviderManager`, Twilio PV webhooks (~15 routes), and `TwilioTelephonyProvider`, **rollback becomes much harder.**

- Delete voice webhooks, TwiML engine, Twilio-vs-Asterisk shims (requires Phase C½ complete)  
- Simplify or rename remaining Asterisk **execution** classes  
- Keep SMS + trunk  

**Acceptance:** Engineering test + owner test pass; policy → compiler → Asterisk is the only path.

---

## FAQ (for the floor)

**Are we eliminating ring groups?**  
No. We are eliminating **Twilio ring groups** — SIP URI targets, TwiML `<Dial>`, and the Ring Group settings tab as implemented today.

**Will all phones still ring?**  
Yes. Call routing policy defines who rings, in what order, after what delay, and under what conditions. Asterisk executes it.

**Can we still choose which phones ring and when?**  
Yes — that is the point of **Call Routing**. The shop edits policy; ARK owns it; Asterisk runs it.

**Can we transfer Edward → Benjamin?**  
Yes — **transfer** (different identity/extension) is Asterisk call control; distinct from **move** (same identity, different endpoint).

**Is chirp in this sprint?**  
No. Intercom is ARK realtime, separate from Voice cleanup. Documented so we do not implement it as SIP or Asterisk paging by accident.

---

## Inventory reference

File-level checklist from 2026-07-04 inventory. **This document is authority.**

---

## Success sentence

> ARK owns call routing and identity. Edward’s desk and mobile are one extension. Move and transfer are different moves. Chirp is operations, not the PBX. Asterisk rings customers; ARK coordinates the shop.

---

## Product framing

This sprint shifted from **delete Twilio** to **make ARK own call routing**. Preserve behavior while replacing implementation. With a disciplined parity safeguard, the shop gets a system that is easier to maintain and easier for owners to understand.
