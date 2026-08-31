# Lead Intake Authority v1

**Status:** Active  
**Internal names:** Lead Intake (authority) · **Public Surface** (the website — not a separate product)  
**Retire:** ARK-WEB, Botble, marketing CMS as separate worlds.

> **Freeze:** *Lead stays boring on purpose.* A Lead answers: Did somebody contact us? Have we responded? Did this become work? — not tasks, notes, pipelines, or scoring. If it sounds like Salesforce, reject it.

## Problem

Demo Auto Repair has mature RO, estimate, and workflow tooling. The weak point is **before the RO exists**: website forms, calls, texts, Messenger, and future channel ingress leak into email, hope, or disconnected surfaces.

## Primary funnel

```
Touch (any channel)
  → Lead
  → Conversation (what was said)
  → Intake / recognition
  → Repair Order
```

**Not:** Encounter → Lead → Estimate. Encounter authority is retired.

## Operating stack (evolving)

ARK's center of gravity is expanding from shop workflow alone to a full front-to-back operating system:

```
Lead → Conversation → Customer → Vehicle → RO → Invoice → Retention
```

A year ago the core was Customer · Vehicle · RO · Invoice. **Lead** and **Conversation** at the front, **Retention** at the back, make the system complete — customer acquisition and relationship continuity on the same doctrine as production and closeout.

**North star:** Every customer touch before an RO exists is visible somewhere in ARK. That is not "shop management" alone — it is the beginning of a **customer acquisition and retention system**.

## Lead authority

`Lead` is the first-class **pre-RO operational object**.

A Lead represents a potential customer opportunity before it becomes a repair order.

Lead is **not**:

- an email
- a CMS form submission record
- an Encounter
- a CRM opportunity / pipeline stage store duplicated from RO lifecycle

Lead exists so advisors can **see, contact, age, convert, and stop losing opportunities**.

A Lead should stay **almost boring**:

```
Customer touched us
  → there is a potential opportunity
  → has anyone worked it?
```

That's it.

### Authority separation (keep this clean)

| Authority | Truth |
|-----------|--------|
| **Conversation** | Relationship — what was said |
| **Lead** | Opportunity — pre-RO potential work |
| **Customer** | Identity — who they are |
| **Vehicle** | Vehicle identity |
| **Repair Order** | Work — lifecycle once converted |

Do not collapse these. Elegance is each layer answering one question.

### Advisor surface (2026-07)

The dedicated **Leads index** (`/app/leads`) and ops-rail peer are **retired**. Advisors work open website/SMS opportunities from **Communications → Needs attention**. Lead disposal (Check In, Mark contacted, Lost, Spam) lives on the Comms context panel when `lead_id` is present.

`operations.leads.index` remains as a **bookmark redirect** to Needs attention. Spam observation lives on **Website → Performance** (owner admin) — not advisor Inbox.

### Anti-CRM guardrails

The danger is Lead becoming Salesforce for auto shops. **Reject** building on Lead authority:

- Tasks, reminders, nurture sequences
- Lead notes parallel to Conversation
- Arbitrary pipeline stages beyond minimal v1 states
- Kanban / forecast / quota / rep leaderboards
- Opportunity scoring, AI lead grading
- Salesperson ownership as a CRM object (light `assigned_user_id` later is ops routing, not a sales org chart)

Notes and messages belong on **Conversation**. Work belongs on **RO**. Lead tracks **opportunity state** and links — not a second inbox.

If a feature sounds like a CRM, it probably does not belong on Lead.

### Lead owns

- source (`website`, `call`, `sms`, `messenger`, `manual`, `unknown`)
- state (minimal v1 lifecycle)
- concern
- contact name, phone, email
- rough vehicle fields when provided
- timestamps (`first_contacted_at`, `scheduled_at`, `arrived_at`, `converted_at`, `lost_at`)
- `lost_reason` when closed lost
- optional `assigned_user_id` (later)
- links: `conversation_id`, `customer_id`, `vehicle_id`, `repair_order_id`

### Conversation owns

- messages, replies, channel history, what was said

### Customer / Vehicle own

- identity once recognized at intake

### Repair Order owns

- work lifecycle once converted

**Do not duplicate RO lifecycle inside Lead.** After conversion, estimate / approved / closed posture is read from RO projections — not copied into Lead.

## Lead states v1

| State | Meaning |
|-------|---------|
| `received` | Ingress recorded; advisor has not acted |
| `contacted` | Shop reached out |
| `waiting_customer` | Ball in customer's court |
| `scheduled` | Appointment / visit time set |
| `arrived` | Vehicle on site or visit recognized |
| `converted` | Linked to RO — lead job done |
| `lost` | Closed without RO |
| `spam` | Auto-flagged or advisor-marked junk — **excluded from lead pressure** |

No automation rules, auto-text, scoring, or CRM stages in v1.

**Pressure first:** surface counts and ages on Attention / nav before enforcement or nurture workflows.

## Lead spam observation (frozen)

Public forms get customers, bots, and spam. Botble hid this; ARK surfaces it. **Do not add CAPTCHA first** — friction kills real leads.

### Capture on every website ingress (observation)

| Field | Purpose |
|-------|---------|
| `ingress_ip` | Same IP vs rotating botnets |
| `ingress_user_agent` | Browser vs script signature |
| `ingress_referrer` | Harvested form vs direct |
| `form_rendered_at` | Time-on-form signal |
| `spam_signals` | e.g. `too_fast` |

### Auto-flag (smallest filter)

| Signal | Rule | Result |
|--------|------|--------|
| Honeypot (`company_website`) | Filled | Silent thanks — **no lead** |
| `too_fast` | Submit &lt; 3s after render | Lead `state=spam` — **no conversation**, excluded from pressure |
| Rate limit | 10 POST / min / IP | HTTP 429 |

Observe patterns on **Website → Performance → Spam observation** (owner admin) before adding rules. Do not put spam rows on advisor Inbox.

### Comms is not acquisition (spam corollary)

If lead pressure fills with junk, advisors learn to ignore it — worse than the spam. Spam state must not pollute **Lead pressure** counts.

### Explicit non-goals

- reCAPTCHA / Turnstile on first pass
- SMS / Messenger / Call inboxes
- ML spam scoring before observation window

## Unknown Inbound Contact Rule (frozen)

**Production evidence (2026-06):** `303-905-6841` texted *"Are you open on the weekends?"* — Conversation ✓ · Comms Attention ✓ · Advisor read ✓ · **Lead ✗**. ARK handled the message and lost the opportunity.

**Rule:** Unknown inbound contact = **Lead candidate**, regardless of channel.

| Channel | Applies |
|---------|---------|
| Website | ✓ |
| SMS | ✓ |
| Messenger | ✓ |
| Phone call | ✓ |
| Google Business Messaging | ✓ (future) |

**Not applicable:** known customer communications — those already have relationship context (Customer → Conversation → RO). No Lead required.

### Relationship-based ingress (not channel-based)

```
Known relationship                         Unknown relationship
──────────────────                         ────────────────────
SMS / call / Messenger / web         →     SMS / call / Messenger / web
Conversation                         →     Conversation (what was said)
Customer context                     →     Lead (potential work — required)
RO workflow                          →     Intake → Customer → RO
```

Examples:

| Sender | Message | Destination |
|--------|---------|-------------|
| Known customer | "Vehicle is ready?" | Conversation |
| Unknown | "My AC isn't cold." | **Lead** |
| Unknown | "How much for brakes?" | **Lead** |
| Unknown | "Are you open Saturday?" | **Lead** |

Channel is transport. **Relationship state** determines destination.

## Comms Is Not Acquisition (frozen)

Two queues answer different questions:

| Surface | Question |
|---------|----------|
| **Comms / Attention** | Who needs a **response**? |
| **Leads** | Who represents **potential work**? |

They sometimes overlap. For strangers they **diverge** — and burying unknown SMS in Comms lets advisors **clear a message** without capturing an **opportunity**. That is exactly the message-87 failure mode.

**Explicit rejection:** The production finding does **not** justify building SMS Inbox, Messenger Inbox, or Call Inbox. The problem is not lack of an inbox. The problem is unknown opportunity did not enter **Lead Truth**.

## Public surface goal

`demo-auto.example` is a **thin public surface inside ARK SMS** — not a brochure CMS.

Homepage job: capture high-quality leads (concern + phone), not page views or blog traffic.

Stack: Laravel, Blade, Tailwind, Alpine — same runtime as operations.

## Channel ingress

| Channel | Status | Pattern |
|---------|--------|---------|
| Website form | ✓ Done | Lead + Conversation |
| Manual (advisor intake path) | ✓ Done | Lead + Conversation |
| SMS (unknown) | ✓ Phase 2A | Conversation → **reconcile/open Lead** |
| SMS (known customer) | ✓ | Conversation only |
| Messenger (unknown) | Phase 2B | Conversation → reconcile/open Lead |
| Call (unknown) | Phase 2C | CallSession → reconcile/open Lead |
| Google Business | Future | → Lead (attribution) |

Website lead body lands as inbound `ConversationMessage` on `OperationalCommunicationChannel::Website`.

### Phase 2 — ingress reconciliation

**Problem (proven in production):** Website → Lead → worked. SMS → Conversation → forgotten. Those are not equivalent.

**Target pattern:**

```
Unknown inbound (any channel)
  → ConversationMessage / CallSession (authority — what happened)
  → reconcile or open Lead (authority — potential work)
  → same pipeline as website
```

**Wording:** **Reconcile/open Lead** — not merely "create Lead." The same unknown contact may need separate opportunity rows per concern over time.

**Phase 2 design decision — one open Lead per concern (not per phone):**

| Approach | Risk |
|----------|------|
| One open Lead per contact (phone / PSID) | Hides new opportunities inside an old lead — e.g. AC lead still "open" when brakes text arrives two weeks later |
| **One open Lead per concern** ✓ | Each distinct customer-stated need is its own pipeline row; same contact can have multiple open leads |

Example:

1. Customer texts: *"My AC isn't cold."* → **Lead #1** (`received`, concern = AC)
2. Two weeks later: *"My brakes are grinding."* → **Lead #2** (`received`, concern = brakes) — **not** an update to Lead #1

Lead #1 may be `converted`, `lost`, or still open independently. Conversation timeline stays unified on the relationship; Lead rows track **opportunities**, not contact identity alone.

**Reconciliation rules (draft):**

- New inbound customer message → new Lead when concern is materially distinct from existing **open** leads for that contact
- Same concern thread → attach to existing open Lead; do not duplicate
- Do not create Lead on every outbound shop message or RO lifecycle event
- Reconcile from existing `ConversationRecorder` ingress — no parallel channel stores
- Unmatched call → Lead with `source=call`, concern from call context / summary
- When customer has open RO for the same concern, link Lead to RO context; do not fork RO lifecycle

Concern matching can start **advisor-visible** (simple: always new Lead on new inbound; advisor merges/lost) and tighten later — pressure-first. Do not over-automate dedup before floor observation.

This is **reconciliation**, not CRM. Conversation stays authoritative for messages; Lead stays authoritative for pre-RO opportunity state.

#### Phase 2 rollout

| Phase | Scope | Status |
|-------|-------|--------|
| **2A** | Unknown SMS → Conversation → reconcile/open Lead | **Shipped** — `LeadReconciler` on `TwilioSmsIngress` |
| **2B** | Unknown Messenger → Lead | Next |
| **2C** | Unknown call → Lead | Next |

**2A validation:** Text shop from unknown number; confirm Lead authority with `source=sms` and concern from message body — visible on **Communications → Needs attention** when shop-turn — even if Comms row is marked read.

### Growth measurement (Day Review / owner rhythm)

Before Lead Truth, owner review skews to closed-work truth: revenue, GP, ARO — **how did the shop perform?** after the fact.

After Lead Truth, the limiting factor becomes measurable **before** the month ends — **why didn't the shop perform?** while there is still time to act.

| Metric | Question |
|--------|----------|
| New leads | How much demand arrived? |
| Contacted % | Did we respond? |
| Scheduled % | Did we book? |
| Arrived % | Did they show? |
| Converted % | Did it become an RO? |
| Source mix | Which channels convert? |

**Lead Leakage** (preferred over "lost leads" — leakage asks where flow stopped, not sales intent):

Count opportunities that **entered** a stage but never reached the next — funnel drop, not CRM disposition.

Example (monthly Day Review projection):

```
Leads Created:     87
Contacted:         72
Scheduled:         41
Arrived:           35
Converted:         29

Leakage:
  Created → Contacted    15
  Contacted → Scheduled  31
  Scheduled → Arrived     6
  Arrived → Converted     6
```

The bottleneck becomes visible: e.g. *31 leads contacted but never scheduled* is an advisor/scheduling problem, not a "lost opportunity" moral judgment.

Phase 1 explicitly excluded Day Review changes. Add **Lead Leakage** projection only after Phase 2 ingress + sandbox validates the full chain.

## Prioritized next steps

1. **Validate Phase 2A on sandbox/production** — unknown SMS → Lead → Communications Needs attention
2. **Phase 2B** — Messenger unknown → reconcile/open Lead
3. **Phase 2C** — unknown call → reconcile/open Lead
4. **Google Business attribution** — source labeling + ingress when API exists
5. **Day Review / owner growth projections** — after funnel is proven in observation
6. **SEO / content engine** — last; not the growth bottleneck

## Intake connection

Lead does not bypass intake.

```
Lead → open intake (prefilled from lead / conversation)
     → recognize customer + vehicle
     → advisor opens RO
     → Lead.state = converted, repair_order_id set
```

Existing helpers: `ConversationRecorder::recordWebsiteLead`, `IntakeEntryQuery::fromWebsiteLead`, advisor `POST /app/intake/leads`.

## Milestone (Phase 1)

For the first time, the **public website participates in operational truth** inside the same runtime as intake and RO workflow:

```
Website → Lead → Conversation → Intake → RO
```

That is a bigger milestone than replacing Botble. Botble was a brochure; Lead Intake is **Lead Truth** at the front door.

After sandbox validates the full chain (website → lead → conversation → intake → RO → converted), **stop talking about ARK-WEB**. The website is **Public Surface** — another ingress point into the same operating system, not a separate product alongside ARK-SMS, Botble, CMS, forms, and email notifications.

Architecture gets simpler; business capability gets larger.

## Phase 1 non-goals

- CMS, blog, service catalog, page builder
- SEO platform
- Full CRM, nurture automation, scoring
- Appointment scheduler UI
- Google Business / Messenger expansion (beyond existing Messenger comms)
- Encounter revival
- Day Review / report changes
- Separate `arkweb` repo or Botble port
- Production DNS cutover (documented separately; not executed in code pass)

## Companion docs

- `docs/deployment/demo-auto-public-surface-cutover-v1.md` — Botble → public surface cutover
- `docs/communications-authority.md` — Conversation as relationship authority
- `.cursor/rules/ark-no-encounters.mdc` — Encounter retired
- `.cursor/rules/ark-pressure-first.mdc` — observe before automate
