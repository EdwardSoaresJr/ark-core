# Communications Bounded Context v1

**Status:** **Locked** — Twilio-native transport (2026-07-05); communications authorities frozen  
**Effective:** 2026-06-26 · **Transport reset:** 2026-07-05

**Foundational doctrine (read first):** [communications-foundational-doctrine-v1.md](communications-foundational-doctrine-v1.md) — frozen authorities, transport/product/companion split, ARKv2 vs Companion intent.

**Foundational bounded context:** Communications is one of ARK's hard-to-change architectural cores — alongside **Repair Orders** and **Inspection**. Treat changes to authorities, operator questions, and domain boundaries with the same rigor as RO lifecycle or inspection truth.

**Governing sentence:**

> **Communication is never the work. Communication exists to support the work.**

The phone exists to support the repair. SMS, email, transfer, paging — all exist to support the repair. **The repair remains the center of the universe.**

**Design question:**

> **How does communication disappear so only the repair remains?**

**Drift test:** If we build a screen that is more about managing phone calls than helping someone fix a car, we have drifted.

**Companions:** [communications-authority.md](../communications-authority.md) · [ark-voice-vision.md](ark-voice-vision.md) · [ark-voice-phase1-spec.md](ark-voice-phase1-spec.md) · [ark-mobile-communications-authority-contract.md](../mobile/ark-mobile-communications-authority-contract.md)

---

## Operator questions (read this first)

Communications is organized around **what operators are doing** — not how infrastructure is stored.

Same shift as Inspection: stop asking *how should inspection data be stored?* and ask *what is the technician doing?*

| Domain | Operator question | Software answers |
|--------|-------------------|------------------|
| **Relationship** | *Who are we communicating with?* | Conversation, messages, relationship timeline |
| **Realtime** | *What is happening right now?* | Active call, live coordination, paging in flight |
| **Workspace** | *What do I need to do because of this communication?* | Answer, reply, transfer, open RO, mark handled |
| **Transport** | *How did it get here?* | Twilio (voice, SMS, MMS) — **implementation only**; replaceable adapter |

Transport naturally belongs at the bottom. Operators never ask *which SIP trunk* — they ask *who is calling* and *what should I do*.

Configuration, Provisioning, and Projection serve these questions. They are not domains operators think in.

---

## Implementation principles

The Communications bounded context is **locked**.

Implementation should **simplify**, not redesign.

When implementing Communications:

- Prefer **fewer authorities** over more.
- Prefer **projections** over duplicated state.
- Prefer **configuration** over hardcoded behavior.
- Prefer **operational language** over telecommunications vocabulary.
- Prefer **append-only history** over mutable state.
- Prefer **conversation continuity** over channel-specific features.

When uncertain, do not ask:

> *"How would a PBX solve this?"*

Ask:

> *"How would this help the shop continue the repair?"*

This is the compass for every Communications PR.

### Operational continuity (informal thread)

Not formal doctrine yet — but the thread connecting Communications to the rest of ARK:

**Operational continuity** is continuity of the **work**, not the phone call.

Every transport exists to preserve continuity. A transfer, voicemail, recording, text, or page preserves continuity. Everything points back to the repair.

ARK already names related ideas: operational language, operational condition, operational truth, operational momentum. Continuity is the Communications contribution to that family.

---

## Historical bias warning

The previous ARK Voice implementation solved **different product questions**.

When reviewing historical code:

- **Do not assume** previous entity names, namespaces, services, or relationships remain correct.
- **Prefer adapting** existing implementation to current doctrine over preserving historical structure.

> **Historical code is evidence.**  
> **Current architecture is authority.**

If historical naming conflicts with this doc — doctrine wins. Extract behavior; discard shape.

*Platform note:* this evidence-vs-authority rule applies beyond Communications — Inspection, Intake, Workspace, Voice. Consider elevating to platform doctrine.

---

## Historical implementation inventory

Architecture must **never assume** the archived repository represents the complete historical implementation.

**Known sources:**

| Source | Location | Notes |
|--------|----------|-------|
| Archived ARK Voice application | Git: parent of `6bb0b4cd` (`ark-voice/`) | Twilio v0 + `CallEvent` stream; not full shop PBX |
| Current ARK V2 telephony | `app/Ark/Operations/Telephony/`, `Conversations/`, `Communications/`, `Messaging/` | Living reference; migrates into this bounded context |
| Historical Asterisk deployment knowledge | **Not currently represented in this repository** | AMI/ARI, paging, provisioning, hybrid PSTN — floor/infra evidence |

**Catalog before implementation:** If additional historical repositories, branches, deployment scripts, host configs, or infrastructure artifacts are located, add them to this table with path and date **before** porting behavior.

### Archived `ark-voice/` (removed 2026-06-17)

**Git:** `git show 6bb0b4cd^:ark-voice/`

**Valuable evidence:**

| Asset | Migrate to |
|-------|------------|
| `CallEvent` + `CallEventType` append-only stream | **`SessionEvent`** (Realtime) |
| `CallEventRecorder` normalization | Transport ingress → event recording |
| Twilio webhook + verifier patterns | `Transport/Voice/Twilio/` |
| Recording / voicemail callbacks | `SessionEvent` + optional `ConversationMessage` |

**Do not resurrect:** standalone app, parallel call authority, extension-first UI, old namespaces.

### V2 telephony (current)

**Keep and evolve:** `CallSession`, `Conversation`, `CommunicationEvent`, Twilio T1–T4, Asterisk ingress, caller context, Calls Waiting, mobile API boundary.

**Adapt carefully:** `MobileVoice/*` → `Transport/Voice/Mobile/`; must not define architecture.

### Migration rule (every reuse)

1. **Which current authority owns this?**
2. **Which operator question does this serve?** (Relationship / Realtime / Workspace — not Transport-first)
3. **Which layer?** — authority, configuration, transport, provisioning, projection, workspace

**Migrate behavior into today's architecture. Never migrate folders first. Never migrate architecture first.**

### What must NOT be resurrected

- PBX-first vocabulary, extension-first workflows, SIP-centric UI
- Manual provisioning surfaces, phone-centric ownership
- Voice as standalone product or deployable
- Old namespaces or folder layout (`ark-voice/` app, parallel call stores)

---

## Domains (grounded in operator questions)

### Relationship — *Who are we communicating with?*

| Authority | Owns |
|-----------|------|
| **Conversation** | Relationship container, ownership, posture |
| **ConversationMessage** | Things said, sent, received (append-only) |
| **ConversationLink** | Pointers to Customer, Vehicle, RO |
| **CommunicationEvent** | RO-scoped workflow facts (estimate viewed, missed call signal) — **not call transfers** |

### Realtime — *What is happening right now?*

| Authority | Owns |
|-----------|------|
| **CallSession** | **Current truth only** for voice sessions (first realtime shell) — see below |
| **SessionEvent** | **History** — append-only; every event answers *what changed?* |

**SessionEvent is intentionally general** — not `CallEvent`. Voice is the first consumer. The same realtime backbone will eventually power:

- Voice (Twilio, Asterisk, WebRTC)
- Live technician collaboration
- Live advisor notifications
- Vehicle arrival events
- Parts counter interactions
- Shop displays and live dashboards

Inspection taught ARK how to observe **condition**. Communications teaches ARK how to observe **activity**. That pattern will outlive telephony.

Voice is the first Realtime consumer. Transport translates provider vocabulary into **SessionEvent**; workspace and RO code never learn provider dialect.

### Workspace — *What do I need to do because of this communication?*

Not authority. Interaction surfaces that orbit the RO:

- Incoming pop with customer/vehicle/RO context
- Conversation rail, comms workspace, Calls Waiting recovery
- Mobile shell, VVX microbrowser, transfer/hold/reply actions

Workspace **reads** Relationship + Realtime projections. It does not own call truth.

### Transport — *How did it get here?*

Replaceable adapters only. No authority. No workspace logic. Operators never see this layer.

```text
Transport/
├── Sms/
├── Voice/          ← Twilio, Asterisk, mobile client tokens
├── Email/
├── Portal/
└── Push/
```

---

## Session model (platform primitive)

**Sprint 1 must not know what a phone is.**

If code names events `CallAnsweredEvent`, the abstraction is already lost. Sprint 1 knows **sessions** only — not telephony.

**SessionEvent** is a **platform primitive** — born in Communications, shared by ARK eventually. Not Communications-only.

Future examples (years away, same primitive):

```text
Live Inspection     → SessionStarted → SessionJoined (remote advisor) → SessionShared (screen share)
```

Get the primitive right once. Do not invent a second realtime event model later.

### Operational Activity vs Operational Condition

| Lens | Question | Changes |
|------|----------|---------|
| **Operational Condition** (Inspection) | What condition is the vehicle in? | Slowly |
| **Operational Activity** (Communications / Realtime) | What activity is occurring around the work? | Constantly |

Condition describes the **vehicle**. Activity describes the **people around the vehicle**. Together they tell the complete shop story.

*Informal platform vocabulary (not formal doctrine yet):* Operational Language · Operational Condition · Operational Truth · Operational Momentum · Operational Continuity · **Operational Activity**

---

### CallSession — *What is true right now?* (first session shell)

Voice exercises this first. The **session shell** may gain siblings later; **SessionEvent** vocabulary stays telephony-agnostic.

```text
Identity          provider ref, links to customer / RO / conversation
Lifecycle         started_at, answered_at, ended_at, duration
Current owner     owned_by_user_id
Current state     started | answered | held | ended (minimal enum)
```

Truth belongs on the **session shell** (CallSession today). Never encode history as mutable flags.

**Vocabulary (protect):**

- **SessionEvent** → historical truth (*what happened*)
- **CallSession** → present operational state (*what is happening now*) — a read model projected from events, not a second history store

*Sprint 2+ mental model:* `ApplySessionEventToCallSessionAction` may become `ProjectCurrentSessionState`. Do not rename until provider adapters land.

*End state:* CallSession stays very small — identity, started/ended, current owner, current status, provider ref, active recording flag, latest event pointer. Everything else lives on SessionEvent.

---

### SessionEvent — *What changed?*

**Immutable. Append-only. Never update. Never delete.** Same discipline as ConversationMessage.

If a transfer occurs, history is:

```text
SessionStarted → SessionAnswered → SessionTransferred → SessionEnded
```

Never mutate `transferred = true` on the session row.

#### Vocabulary (telephony-agnostic)

```text
SessionStarted
SessionAnswered
SessionHeld
SessionTransferred      { from, to }
SessionRecordingStarted
SessionRecordingEnded
SessionEnded
```

Future (same enum family): `SessionJoined`, `SessionShared`, `SessionPaged`, etc.

#### Provider translation (providers stay dumb)

Providers **emit observations**. Authorities **persist truth**.

```text
Provider (Twilio / Asterisk / Fake / future WebRTC)
    ↓ normalize (adapter only)
SessionEvent (append)
    ↓ derive current state
CallSession update (current truth)
```

**NOT:**

```text
Asterisk → updates CallSession directly   ← forbidden
```

Provider-specific events (`AMI BlindTransfer`, `ChannelUp`, `PeerConnected`) **never escape the adapter**.

| Provider dialect | ARK records |
|------------------|-------------|
| Twilio: `answered` | `SessionAnswered` |
| Asterisk: `ChannelUp` | `SessionAnswered` |
| WebRTC: `PeerConnected` | `SessionAnswered` |

#### Explainability invariant

Every SessionEvent must be understandable **without knowing the provider**.

| Good | Bad |
|------|-----|
| SessionTransferred — From Edward, To Molly | AMI BlindTransfer Event |

Every SessionEvent answers: **"What changed?"** — not *"What is true?"*

---

### Projections are surfaces, not architecture

VVX microbrowser, Flutter, desktop pop, and future shop displays are **projections**. The architecture does not know or care which surface renders a session. That is the boundary test passing in product form.

---

## True authorities (six)

| # | Authority | Domain | Notes |
|---|-----------|--------|-------|
| 1 | Conversation | Relationship | Who we communicate with |
| 2 | ConversationMessage | Relationship | What was said |
| 3 | CallSession | Realtime | Current truth — boring |
| 4 | SessionEvent | Realtime | **Platform primitive** — immutable, append-only, telephony-agnostic |
| 5 | CommunicationEvent | Relationship | RO workflow facts — separate from SessionEvent |
| 6 | CommunicationDevice | Configuration | Device registry with capabilities |

**Unified timeline** = projection merging Relationship + Realtime. Not a new authority.

---

## Bounded context layout (implementation target)

**Not** `ARK Voice`. **Communications** at `app/Ark/Communications/`.

```text
app/Ark/Communications/

├── Relationship/
├── Realtime/
├── Transport/          ← bottom of the stack — implementation only
├── Configuration/
├── Provisioning/
├── Projection/
└── Workspace/
```

Folder moves follow authority introduction — not the other way around.

---

## Shop vocabulary (Settings) vs internal model

| Shop sees | ARK models | Hidden (transport) |
|-----------|------------|-------------------|
| Staff member | `User` + voice enabled | — |
| Device | **CommunicationDevice** | extension, SIP, client identity |
| Who should answer? | **Destination** + routing policy | dialplan, queue |
| Page technicians | **Destination** (paging capability) | paging codes |
| Transfer to Molly | **Destination** | bridge mechanics |

---

## Interruptibility (not telephone presence)

Operational question: **Can I interrupt this person right now?**

Derived projection from RO posture, active CallSession ownership, customer-waiting flags — not SIP registered/busy/idle.

---

## Provisioning

```text
Generate → Apply → Verify → Monitor
```

Shop sees: *Edward's iPhone · Ready.*  
ARK owns vendor-specific provisioning (Yealink, Poly, Twilio, Asterisk).

---

## Legacy → Communications migration map

| Historical capability | Reference | Target | Layer |
|----------------------|-----------|--------|-------|
| Twilio ingress/outbound | `ark-voice/` + V2 `TwilioTelephonyProvider` | `Transport/Voice/Twilio/` | Transport |
| Asterisk ingress | V2 `Asterisk/*` + off-repo knowledge | `Transport/Voice/Asterisk/` | Transport |
| Call event stream | `ark-voice/CallEvent*` | `SessionEvent` | Authority |
| Call lifecycle shell | V2 `CallSession` | Slim `CallSession` | Authority |
| Recording / voicemail | V2 webhooks + columns | `SessionEvent` + media refs | Authority + Transport |
| Transfers / paging | Historical Asterisk evidence | `SessionEvent` + transport adapter | Authority + Transport |
| Extension registry | V2 `TelephonyExtension` | `CommunicationDevice` metadata | Configuration |
| Ring targets | V2 `TelephonyEndpoint`, ring groups | **Destination** + routing | Configuration |
| Caller pop | V2 caller context projection | `Projection/` | Projection |
| Comms workspace | V2 `Operations/Communications/` | `Workspace/` | Workspace |
| SMS | V2 `Messaging/` | `Transport/Sms/` | Transport |
| Mobile voice | V2 `MobileVoice/*` | `Transport/Voice/Mobile/` | Transport |

---

## Explicit non-goals

- FreePBX clone, dialplan/extension/queue editors, SIP credential screens
- PBX dashboards, AMI/ARI consoles in operational UI
- Separate `ark-voice` deployable
- Flutter as general SIP/PBX client
- `AttentionItem`, SMS inbox, channel-first stores
- Screens centered on call management instead of repair work

---

## Acceptance test

A new shop enables Voice, adds three staff, hands them phones — everything works.

Nobody asks *what extension am I?* Nobody sees SIP, dialplan, queue, or trunk.

Staff live in **Repair Orders**. Communication orbits the work.

---

## Implementation sequence

Architecture is frozen. From here, implementation **discovers details** — it does not redesign the model.

No more doctrine. No more architecture renames. Write code against this doc.

### First sprint — realtime backbone (not Voice)

Sprint 1 is **not about Voice**. It establishes **reusable realtime architecture** that Voice happens to exercise first.

Keep the sprint disciplined:

| Deliver | Notes |
|---------|--------|
| **SessionEvent** | Append-only platform primitive — `SessionStarted`, not `CallAnsweredEvent` |
| **Slim CallSession** | Current truth only — updated **from** SessionEvent stream, never by provider directly |
| **SessionProvider interface** | Provider emits observations; adapter normalizes to SessionEvent |
| **FakeSessionProvider** | **Permanent first-class provider** — not a test helper; lives in provider registry beside Twilio and Asterisk |
| **Twilio behind SessionProvider** | Wrap existing; translate only in adapter |
| **Asterisk as second provider** | Historical evidence — not architecture |

**Explicitly out of first sprint:** UI redesign, VVX browser, provisioning, Flutter SIP, workspace magic.

**Sprint 1 code must not know what a phone is.** No `Call*Event` types. Session vocabulary only.

#### Sprint 1 success criterion

Not *"Can we make a phone call?"*

**Can ARK observe a realtime session without caring who the provider is?**

That normalization is the victory. Everything else is translation.

#### Sprint 1 acceptance — FakeSessionProvider

```text
SessionProvider registry (permanent):
├── FakeSessionProvider      ← default for dev, tests, VVX/Flutter UX without a PBX
├── TwilioSessionProvider
└── AsteriskSessionProvider
```

```text
FakeSessionProvider
    → SessionStarted → SessionAnswered → SessionTransferred → SessionHeld → SessionEnded
    → Conversation timeline updates
    → CallSession current state updates
```

No Twilio. No Asterisk. No SIP. No network. If ARK behaves correctly — **Sprint 1 is done.**

If Twilio is required to validate the architecture, **the architecture is not finished.**

#### Sprint 1 transport replaceability test

If the entire voice **Transport** implementation is swapped for **FakeSessionProvider only**, every workspace, Conversation, Repair Order, and projection must continue to function on simulated events.

Proves: transport is replaceable; SessionEvent is the backbone; CallSession is current truth only; Communications owns the model — not Twilio or Asterisk.

#### Sprint 1 boundary test (Sprint 2 proof)

```text
Twilio configured   → works
Swap config only
Asterisk configured → works
```

No Conversation changes. No Workspace changes. No RO changes. That is the proof.

#### Sprint 1 pipeline (invariant)

```text
Provider → Normalize → SessionEvent (append) → CallSession (current truth)
```

Providers never create authorities. Providers never mutate session history flags.

**Sprint 1 complete (`18df6e89`):** FakeSessionProvider → SessionEvent → CallSession → timeline. No architectural violations found.

### Sprint 2 — provider normalization (one objective only)

**Goal:** Prove transport is replaceable. Nothing more.

Not improve Twilio. Not bring back Asterisk. Not make phones ring. **Prove the boundary.**

#### In scope

```text
Twilio webhook          Asterisk AMI/ARI ingress
       ↓                         ↓
TwilioSessionProvider   AsteriskSessionProvider
       ↓                         ↓
   Normalize                 Normalize
       ↓                         ↓
       └──── RecordSessionEventAction ────┘
```

Both must produce **identical** `SessionEvent` streams — not similar. **Identical.**

Example operational sequence both providers must normalize to:

```text
SessionStarted → SessionAnswered → SessionHeld → SessionTransferred → SessionEnded
```

The provider disappears after normalization.

#### Normalization discipline

Do **not** normalize too early. Not every transport signal becomes a `SessionEvent`.

Ask: *Does ARK need to know this operationally?*

Twilio `initiated` / `ringing` may remain **transport-only** and never become `SessionEvent`. That is correct.

Expose every **operationally meaningful** event. Keep vocabulary clean.

#### Sprint 2 merge gate — three providers, one stream

Regression test required before merge:

```text
Fake    → [Started, Answered, Transferred, Held, Ended]
Twilio  → [Started, Answered, Transferred, Held, Ended]   ← must equal Fake
Asterisk→ [Started, Answered, Transferred, Held, Ended]   ← must equal Fake
```

Then assert all three project to the **same** `CallSession` present state.

Compare **event streams first**, then projected shell. If streams diverge, transport leaked.

#### Sprint 2 success demo (no phone)

```bash
php artisan communications:simulate-provider fake
php artisan communications:simulate-provider twilio
php artisan communications:simulate-provider asterisk
```

Each prints the same normalized event sequence. Timeline identical. `CallSession` identical. Conversation projection identical.

#### Sprint 2 hard rule (reject PR if violated)

> **No provider mutates CallSession directly. Every provider speaks only through SessionEvent.**

Kill: `Webhook → Update CallSession` (`CallSessionRecorder::applyStatus` from ingress without `SessionEvent`).

`RecordSessionEventAction::begin()` remains valid — identity creation, not history.

#### Explicitly rejected in Sprint 2 (send PR back)

- VVX browser, Flutter SIP, provisioning
- Presence, paging UI, transfer UI, recording UI
- New settings screens, call popups
- Any workspace / Conversation / RO changes

Those are Sprint 3+.

**Sprint 3 unlock:** only after three-provider event-stream parity is proven.

### Later phases (Sprint 3+)

```text
3. CommunicationDevice + Destination (Configuration)
4. Port transport under app/Ark/Communications/Transport/
5. Provisioning service
6. Workspace projections (pop, mobile, VVX, presence, paging, transfers)
```

Observe floor pain between phases. See [ark-voice-phase1-spec.md](ark-voice-phase1-spec.md) for Asterisk parallel ingress gates.

---

## Review checklist (every PR touching Communications)

- [ ] Compass question: does this help the shop continue the repair?
- [ ] Which operator question does this serve?
- [ ] Which authority owns this?
- [ ] Session vocabulary only — no `Call*Event` or telephony types outside Transport adapter?
- [ ] Provider → Normalize → SessionEvent → session shell (never provider → session shell direct)?
- [ ] SessionEvent immutable — append-only, never update/delete?
- [ ] SessionEvent explainable without provider name?
- [ ] SessionEvent for *what happened*; CallSession for *what is happening now*?
- [ ] Relationship, Realtime, Workspace, or Transport?
- [ ] Would Shop B differ without a deploy?
- [ ] Does this make communication disappear or become the center?
- [ ] Historical code reused — migration rule answered?
- [ ] Historical entity names preserved without doctrine mapping? (reject)
- [ ] GET paths mutation-free?
