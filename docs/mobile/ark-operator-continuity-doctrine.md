# ARK Operator Continuity Doctrine

**Status:** v1  
**Classification:** **Projection / composition only — not authority**

## Product sentence

ARK is not building a notification system. ARK is building a **continuity system**.

The product is making sure Edward never loses the thread of what is happening in the shop — wherever he is, on whatever surface.

Push is one delivery mechanism. Not the product.

**North star question for every new device:**

> What continuity should this device preserve for this operator?

Not: *"How do we notify it?"*

---

## Stack

```
Authorities
    │
    ▼
Authority Events
    │
    ▼
Operational Observations
    │
    ▼
Operator Continuity  ← projection only; no continuity database
    │
    ├── Home
    ├── Badge
    ├── Push (transport)
    ├── Call Pop
    ├── Front Counter
    ├── VVX Microbrowser
    ├── Bay Display
    └── Watch / Live Activity
```

### What is missing

There is **no continuity database**.

There is **no continuity truth**.

`OperationalObservationStream` holds interpretive stream entries — observation authority, not continuity authority. Continuity **composes** observations (and eventually other projections) at read time.

Continuity simply asks:

> Given what ARK currently knows, what must this operator not lose?

That prevents Continuity from becoming another bounded context that accumulates state.

---

## Continuity vs notifications

| Notifications mindset | Continuity mindset |
| --- | --- |
| Send a push | Preserve the thread |
| Unread message count | Unresolved observation count |
| Channel thinking | Operator thinking |
| Build notification features | Build continuity surfaces |

**Badge metric:** unresolved observations relevant to this operator — not unread SMS count.

---

## One snapshot, many surfaces

`GET /api/mobile/continuity` returns one compact projection. Home, widgets, Live Activities, badges, and watch complications consume the same payload — different presentation, not different pipelines.

**Current shape (v1):**

```json
{
  "badge": 7,
  "continuity": {
    "count": 7,
    "highest_priority": "customer_replied",
    "oldest_age_seconds": 428,
    "updated_at": "2026-06-28T12:00:00Z",
    "since": "2026-06-27T17:18:44Z"
  },
  "moments": [ "..." ],
  "next_best_action": { "label": "...", "deep_link": "..." },
  "today": null,
  "station": null,
  "poll_after_seconds": 45
}
```

| Consumer | Uses |
| --- | --- |
| App icon | `badge` → `7` |
| Watch complication | `continuity.highest_priority` → `CALL` |
| Lock screen widget | `moments[0].title` → `Sarah waiting` |
| Portable Station Home | full snapshot + orientation |
| VVX idle microbrowser | `moments` + station context (future) |

`GET /api/mobile/continuity/badge` remains a lightweight legacy poll; prefer `/continuity`.

---

## Role-filtered, truth-shared

Everyone sees the same operational truth, filtered by role and station:

- **Edward (advisor):** Customer replied, warranty approved, customer arrived
- **Landon (technician):** Inspection assigned, parts arrived, RO waiting
- **Molly (manager):** Customer waiting 18 min, vehicles overdue, payment received

No duplicate logic. Same observation vocabulary. Different priorities per operator profile.

---

## VVX microbrowser

The Poly VVX idle screen should **consume continuity**, not telephony internals.

```
Front Counter · Edward
──────────────────────
Sarah replied
2 waiting approvals
Jason arriving 3:30
──────────────────────
Ready
```

Phone rings → continuity updates. Customer arrives → continuity updates. Warranty approved → continuity updates.

The VVX is a continuity surface — not a SIP device with a webpage glued on.

---

## Implementation (current)

| Layer | Role |
| --- | --- |
| Authorities + events | Truth |
| `OperationalObservationStream` | Curated observation stream (interpretive) |
| `OperatorContinuityProjection` | **Composes** snapshot — no writes |
| `MobilePushService` | One mobile push transport |
| `PushTransport` | Deliver packet to device token |

Do **not** build more notification features. Build continuity surfaces.

---

## Architectural tests

> If Firebase disappeared tomorrow, does operator continuity break?

**No.** Push stops. Continuity snapshot, orientation, and floor surfaces keep working.

> If we added a `continuity_items` table, would that be correct?

**No.** That would make continuity authority. Compose at read time instead.

---

## Companions

- [ark-mobile-notification-doctrine.md](./ark-mobile-notification-doctrine.md) — transport boundary
- ark-observations.mdc — observation vocabulary
- ark-projection-rule.mdc — compute once, render many
- ark-orientation-pattern.mdc — briefing before action
