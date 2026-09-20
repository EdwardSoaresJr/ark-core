# ARK Staff — The Operation Follows the Operator

**Status:** Active product doctrine  
**North star:** [operation-follows-operator-v1.md](../product/operation-follows-operator-v1.md)  
**Certification:** [workflow-completion-certification.md](../engineering/workflow-completion-certification.md)

## Product sentence

**The operation follows the operator.**

Not the phone. Not the desktop. When Edward moves counter → lot → bay → office, the work stays with him. Devices are views. Stations are places.

Phone-First Shop is evidence, not the product.

## Gates

**PR:** Which shop work can finish that couldn't yesterday? Does it improve the **Customer Arrival video**?

**Build:** What can Edward finish standing next to the vehicle?

**Month:** Can Edward, Molly, Landon run the floor without device-thinking?

**Artifact:** [operations/README.md](../operations/README.md) — film operations, not certifications.

---

## Threshold

The backend authority model is healthy. The API is healthy. Authentication, capabilities, projections, configuration doctrine, and communication authority are in place.

The problem is no longer "build more screens."

The problem is:

> Build an operating system technicians and advisors can run an entire day from.

---

## Product identity (workflow engine)

ARK Staff is **NOT** a miniature desktop. It is **NOT** CRUD on a small screen.

**ARK Staff is a workflow engine** — the primary execution surface for floor operations.

Every interaction must answer:

> **What does the user need to do next?**

Not: *What data do we have?*

If it does not help the operator finish work at the vehicle, at the counter, or on the walk — rethink it.

---

## Old framing (retained for search)

~~ARK Mobile~~ → **ARK Staff**. Stop building screens. Build shop workflows.

## Workflow first

Do not build isolated features. Build complete vertical workflows.

```
Customer arrives
  ↓
Advisor checks in
  ↓
VIN scan
  ↓
Vehicle lookup
  ↓
RO created/opened
  ↓
Assign technician
  ↓
Technician receives work
  ↓
Open concern
  ↓
Take photos
  ↓
Record findings
  ↓
Create recommendation
  ↓
Advisor notified
  ↓
Customer contacted
  ↓
Customer approves
  ↓
Technician notified
  ↓
Repair performed
  ↓
Repair complete
  ↓
Pickup
  ↓
Closed
```

If any step forces unnecessary desktop usage, **observe why** — that observation becomes the roadmap.

---

## Technician experience

The technician is standing beside a vehicle. The app should assume that.

Technicians think:

- "I'm at the LF wheel."
- "I found something."
- "I need a picture."
- "I need to measure it."
- "I need to recommend it."

NOT:

- "I should navigate to Finding Detail."

---

## Concerns become workspaces

Each concern is its own workspace. Inside a concern:

- Customer concern
- Findings
- Recommendations
- Photos
- Measurements
- Advisor discussion
- History

No bouncing between unrelated screens.

---

## Findings become evidence

A Finding is not a note. A Finding is **evidence**.

Each Finding should eventually support:

| Field | Status |
| --- | --- |
| Title | v1 |
| Measurement | partial |
| Intent | partial |
| Severity | planned |
| Recommendation | planned |
| Photos | v1 |
| Video | future |
| Voice notes | v1 (native) |
| Created by / date | v1 |
| History | planned |
| Advisor comments | planned |
| Customer visibility | authority |
| Edit history | planned |

---

## Camera first

The camera is the center of technician workflow.

```
Take photo → Annotate → Measurement → Recommendation → Save → Next photo
```

Minimize navigation. After save, immediately ready for the next capture.

---

## Advisor experience

Advisors should almost never need the desktop for communication.

Conversation should expose immediate actions without changing workspaces:

- Call
- Text
- Open RO
- Open customer
- Send estimate
- Send payment
- Send inspection
- Leave internal note

---

## Notifications

Notifications are **interruptions**, not inboxes.

They represent operational events:

- Customer replied
- Vehicle arrived
- Estimate approved
- Parts received
- Technician completed work
- Advisor mentioned you
- Incoming call / missed call / transfer waiting

Tap notification → go directly to work.

Push transport is deferred; poll-first posture per [notification doctrine](./ark-mobile-notification-doctrine.md).

---

## My Work

Cards communicate **pressure**, not merely status.

Each card answers:

| Question | Example |
| --- | --- |
| Who? | Mike Kindig |
| What vehicle? | 2017 GMC Sierra |
| Why do I care? | Customer waiting · Estimate viewed |
| What should I do next? | Follow up with customer |

Projection fields: `next_action`, `attention_reason`, `age_label`, `primary_concern_id` (deep link into concern workspace).

---

## Check-in

Optimize for speed. Preferred order:

1. Scan VIN
2. Scan plate
3. Existing customer
4. Manual entry

Do not optimize for forms. Optimize for getting a vehicle into ARK in **under one minute**.

---

## Communications

Conversation is not separate from operations.

Conversation should understand: current RO, customer, vehicle, outstanding approvals, recent findings, estimate, payments, appointments, phone call context.

Eventually, incoming calls open the correct conversation and RO immediately.

---

## Tablet experience

Tablets are not large phones. When width allows:

- Master/detail layouts
- Split view
- Persistent concern list
- Persistent findings
- Persistent conversation

Less navigation. More working.

---

## Desktop parity

Mobile should eventually support an entire repair visit without desktop interaction.

That does **NOT** mean duplicating every desktop feature. It means **completing workflows**.

---

## Current priority

Stop expanding horizontally. **Certify one workflow at a time** — see [workflow-completion-certification.md](../engineering/workflow-completion-certification.md).

| Priority | Workflow certification |
| --- | --- |
| **1** | [Customer Arrival](../product/certifications/customer-arrival-workflow.md) — find/create → VIN → verify → concern → photos → RO → assign |
| **2** | [Technician Start](../product/certifications/technician-start-workflow.md) — assigned work → inspection → photos → recommendations → note |
| **3** | [Advisor Communication](../product/certifications/advisor-communication-workflow.md) — observation → workspace → call/text/estimate/note |
| **4** | [Vehicle Pickup](../product/certifications/vehicle-pickup-workflow.md) — invoice → payment → receipt → close |
| **5** | [Shop Walk](../product/certifications/shop-walk-workflow.md) — bay-to-bay continuity, no search |
| **★** | [Phone-First Shop](../product/certifications/phone-first-shop.md) — full operational day on phone |

## Surface grammar

Every mobile surface exposes:

**Current Situation → Next Best Action → Quick Actions**

What's true? What should I do? Can I do it right here?

---

## Previous priority table (superseded 2026-06-28)

| Priority | Workflow |
| --- | --- |
| ~~**1**~~ | ~~Advisor check-in → vehicle lookup → create/open RO → assign technician~~ → Customer Arrival cert |
| **2** | Technician inspection → concern → finding → recommendation → photos → complete |
| **3** | Advisor communications → customer replies → estimate → approval → payment |
| **4** | Repair completion → pickup → close RO |

Only after these workflows feel excellent should we add new capabilities.

---

## Architectural guardrails

```
Authority → Observation → Projection → Configuration → Transport
```

- Flutter remains **projection only**
- No duplicated business logic
- No parallel authority
- No shop-specific branching
- No hardcoded Demo Auto Repair workflows
- Everything configurable lives in Settings
- Everything operational lives in ARK authority

See [projection v1](./ark-mobile-projection-v1.md) and [communications contract](./ark-mobile-communications-authority-contract.md).

---

## Success metric

Success is **NOT**: "We built another screen."

Success **IS**: A technician or advisor completes an entire customer visit without wondering where to go next.

If the user hesitates, leaves the workflow, or reaches for the desktop — observe why. Those observations become the roadmap.

Do not optimize for feature count. Optimize for **uninterrupted shop workflow**.

---

## Related docs

| Doc | Role |
| --- | --- |
| [Production Workspace v1](./ark-mobile-production-workspace-v1.md) | Technician production surface — concerns, camera-first, tablet |
| [Notification doctrine](./ark-mobile-notification-doctrine.md) | Poll-first; push deferred |
| [Projection v1](./ark-mobile-projection-v1.md) | API transport layer |
| doctrine `ark-mobile-workflow-doctrine.mdc` | doctrine enforcement |
