# Communications Roadmap → Messenger

**Purpose:** Working plan from today’s ARK v2 comms state to Facebook Messenger (Phase 5). Extends `communications-authority.md` — does not replace it.

**North star (unchanged):** One customer relationship timeline across every channel. Messenger is ingress only, not a subsystem.

---

## Where we are

| Layer | Status |
|-------|--------|
| Conversation authority (`Conversation`, `ConversationMessage`, links) | Deployed |
| Producers: estimate/invoice email, advisor notes, website leads | Wired |
| T0 Lookup Caller + `CustomerCallContextResolver` | Deployed |
| SMS inbound (`TwilioSmsIngress`) + outbound send | Deployed |
| Voice / Twilio webhooks | Deployed |
| Communications queue (SMS unread, interrupts) | Deployed |
| Customer Hub comms tab | Deployed |
| `CommunicationEvent` (workflow facts separate from messages) | **Deployed** |
| `ConversationContextPanel` (shared projection) | **Deployed** — Lookup, Customer Hub Work, RO Review |
| Provider-agnostic `ConversationIngress` | **Deployed** — `TwilioSmsIngress` |
| Messenger channel enum / contact surface / adapter | **Deployed** — `MetaMessengerIngress`, webhook, outbound, link UI |
| Meta App (platform) vs Page (shop) separation | **Deployed (PR 1)** — env `META_MESSENGER_*`; shop Page ID + encrypted Page token; webhook routes by `entry.id` |

SMS already proves the ingress pattern Messenger will reuse. **Meta App credentials belong to ARK (platform).** Shops connect Pages only. OAuth “Connect Facebook” is PR 2 — not required for Demo Auto Repair manual Page-token path.

---

## Path (do in order)

### Step 1 — T1: Call Context Everywhere (complete — operate)

**Goal:** Same relationship context wherever advisors work — not only Lookup Caller.

**Build:**

1. `ConversationContextPanel` — Blade component reading `ConversationTimeline` + `CustomerCallContextResolver`
2. Embed on Customer Hub (comms / overview)
3. Enrich RO Review conversation rail with relationship context + open-RO strip
4. Refactor Lookup Caller to use the panel (delete duplicated markup/queries)

**Success:** Advisors answer *“what have we said to this customer?”* from Customer Hub or RO without opening Lookup Caller.

**Blocks Messenger because:** Messenger threads attach to **customer relationship**, not a dedicated inbox app. T1 proves relationship-level projection before adding another channel.

**Exclude:** New routes, new stores, standalone “relationship timeline” page.

---

### Step 2 — Phase 1b: `CommunicationEvent` (complete)

**Goal:** Human messages and workflow facts stay separate.

**Build:**

1. `CommunicationEvent` model + migration
2. Migrate `OperationalCommunication` journal → events (dual-write or one-time backfill)
3. `OperationalTimeline` + `OperationalTriggers` read from authority — stop duplicating comm history
4. Estimate sent / invoice sent emit events that **reference** `ConversationMessage` ids

**Success:** RO timeline shows “estimate viewed” as an event, not a fake message. Inbox shows only human comms.

**Blocks Messenger because:** Meta delivery receipts, read receipts, and “lead received” should be events — not conversation message noise.

---

### Step 3 — Ingress contract (SMS → any channel) (complete)

**Goal:** One front door for all inbound providers.

**Build:**

1. `ConversationIngress` interface: normalize provider payload → idempotent `ConversationMessage`
2. Refactor `ProcessInboundMessageAction` → `TwilioSmsIngress` implementing the contract
3. `InboundMessagePayload` → generic `InboundConversationPayload` (provider id, contact key, body, media, metadata)
4. `ConversationResolver::forContactKey(surface, key)` — generalize phone resolver for future PSID

**Success:** Adding Messenger later = new adapter + webhook route, no schema change.

**Messenger extension point (document only for now):**

```
Meta webhook → MetaMessengerIngress → ConversationIngress → ConversationRecorder
```

Contact key: Facebook Page-Scoped ID (PSID). Surface: `ConversationContactSurface::Messenger` (new).

---

### Step 4 — Channel-ready queue + settings (complete)

**Goal:** Queue and settings already think in channels, not “SMS app.”

**Build:**

1. Add `OperationalCommunicationChannel::Messenger` (label only — no webhook yet)
2. Add `ConversationContactSurface::Messenger`
3. `UnreadInboundMessageQueue` — filter by channel param; queue UI sections per channel
4. Communications settings stub: `channels.messenger.enabled` (off by default), page id + token placeholders

**Success:** Enabling Messenger later is a settings toggle + adapter deploy, not a queue redesign.

---

### Step 5 — SMS operational proof (gate before Messenger) — **skipped**

Shop-operate gate deferred. See **`docs/sms-operational-proof-checklist.md`** when ready to operate the gate.

**Operate in shop until true:**

- Inbound SMS appears on Customer Hub + RO conversation rail without manual copy-paste
- Advisors reply from RO/customer context (not only raw queue)
- Unread queue is daily-use, not ignored
- T0 friction notebook: dominant friction is **not** “conversation is hard to find”

If SMS ingress is still flaky or unused, Messenger adds a second broken inbox.

---

### Step 6 — Messenger (Phase 5) — **complete (v1)**

**Prerequisites:** Steps 1–4 complete; Step 5 shop gate optional.

**Built:**

1. Meta Page webhook (`GET` verify + `POST` ingest, signature check)
2. `MetaMessengerIngress` → `ConversationIngress`
3. Customer match via `customers.messenger_psid`; queue link form for unmatched PSID
4. Outbound Graph API send from customer hub / API (`channel=messenger`)
5. Channels settings: page id, token, verify token, app secret

**Also built:** inbound/outbound attachments, 24-hour window + message tags, delivery/read receipts as `CommunicationEvent`, queue reply deep-links to RO Review, settings tab renamed **Messenger**.

**Still manual / later:** shop volume proof (Step 5), Meta app production configuration.

**Adapter choice:** Direct Meta Graph API first (Messenger is not SMS). Twilio Conversations Facebook channel is optional later if you want one billing surface — not required for v1.

**Out of scope for ARK-SMS (permanent):** legacy ARK-SMS Messenger backfill, AI auto-reply. A greenfield product may revisit similar ideas later; ARK-SMS v2 will not carry them forward.

**Deferred / optional:** cross-channel merge UI.

---

## What to do now

| Who | Action |
|-----|--------|
| Shop | Configure Meta app webhook → `webhooks/communications/meta/messenger` |
| Shop | Enable Messenger in Communications → Messenger; link unmatched queue threads to customers |
| Shop | Optional: run **Step 5** — `docs/sms-operational-proof-checklist.md` |
| Dev | Commit/deploy comms stack; monitor ingress in production |

---

## Acceptance: ready for Messenger

- [x] `ConversationContextPanel` on Lookup, Customer Hub, RO Review
- [x] `CommunicationEvent` live; timeline reads workflow events (no duplicate comm journal)
- [x] `ConversationIngress` with `TwilioSmsIngress` as first implementation
- [x] Queue supports multiple channels in UI (SMS live; Messenger stub off by default)
- [x] Messenger v1 adapter deployed (webhook, ingress, outbound, link UI)
- [ ] SMS used daily in shop for at least 2 weeks without “where did that text go?” friction (Step 5 gate)

---

## Related docs

- `docs/communications-authority.md` — doctrine, phases, forbidden patterns
- `app/Ark/Operations/Messaging/TwilioSmsIngress.php` — Twilio SMS adapter implementing `ConversationIngress`
