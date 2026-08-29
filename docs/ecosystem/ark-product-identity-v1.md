# ARK Product Identity v1

**Status:** Frozen product doctrine — **last doctrine addition.** No new doctrine documents until observation earns revision.  
**Not:** A roadmap, feature list, or architecture diagram  
**Purpose:** North star so nobody accidentally steers ARK away from what it is.

---

## First sentence

**Every interaction begins with a person and ends with work.**

The customer calls. A conversation begins. The advisor understands the person. Only then does the repair order become relevant. That is how a good service advisor thinks — and what the architecture now reflects.

---

## Three foundational identities

| Product | Mission | Primary question |
| --- | --- | --- |
| **ARK Platform** | Operating system | How does the shop run? |
| **ARKv2** | Operations workspace | What work needs to be done? |
| **ARK Companion** | Communications workspace | Who needs me right now? |

That feels complete. Shared authorities power all three. Shared screens do not.

| If you are… | Use |
| --- | --- |
| Answering a customer · following up · recovering a thread | **Companion** |
| Editing an RO · production · parts · reporting · owner rhythm | **ARKv2** |
| Authority · configuration · transport · platform wiring | **Platform** (not operator-facing product center) |

Moving between workspaces should feel like one product — not switching applications.

---

## Authority and projection (at product scale)

Engineering doctrine: **authority owns truth; projection packages answers for an audience.**

The reset applied that to the products themselves:

| Layer | Role | Owns / projects |
| --- | --- | --- |
| **ARK Platform** | **Authority** | Shops · identity · products · communications · operations — what is true |
| **ARKv2** | **Projection** | Operations — queue · repair orders · parts · scheduling · business |
| **ARK Companion** | **Projection** | Communications — inbox · calls · texts · voicemail · Advisor Brief · Operational Context |

ARKv2 and Companion are not siblings competing for features. They are **two views of the same authorities.**

Technology (Laravel, Flutter, Twilio, Docker) is implementation. The product is what you design. Protect that separation — future decisions ask *does this make the advisor's day better?* not *what is the best technology?*

---

## The Two Workspace Rule

**Every new capability must have a natural home.**

If it belongs equally in ARKv2 and Companion, the product boundary is probably wrong.

- **ARKv2** optimizes for **managing work**.
- **Companion** optimizes for **communicating with people**.

Shared authorities power both. Shared screens do not.

This one rule prevents years of feature creep.

---

## The reset (why this matters)

**Before:**

```text
Repair Order
     ↓
Phone
```

**Now:**

```text
Customer
     ↓
Conversation
     ↓
Repair Order
```

The repair order is no longer the first thing you see when the phone rings. **The person is.**

That is how advisors actually think. The biggest win of the telephony reset was not Twilio — it was asking *what are we actually building?*

The answer was not a phone system. It was: **a communications workspace that understands repair shops.**

---

## Authority stack (communications)

```text
Identity → Conversation → Advisor Brief → Operational Context
```

Companion informs. ARKv2 operates. Conversation stays primary.

Production feel wraps the hierarchy. It does not compete with it.

---

## Feature creep litmus

Apply **The Two Workspace Rule** first. Then:

1. **Which question does this answer?** Operations (*what work?*) or communications (*who needs me?*)
2. **Does it duplicate the other workspace?** If yes — reject or narrow.
3. **Does it reinforce the customer-first path?** Customer → conversation → work — not RO-first interrupt.

---

## Frozen vocabulary

When a new engineer joins, these words have **one meaning**. If they stay stable, the software can evolve without losing identity.

| Term | Meaning |
| --- | --- |
| **Customer** | Relationship identity — who we are talking to and working for |
| **Conversation** | Relationship authority — what we have said to this customer (not SMS inbox, not channel threads) |
| **Advisor Brief** | What the advisor should know before responding — posture, signals, one recommendation (AI is implementation) |
| **Operational Context** | Progressive disclosure of vehicle/work state on a thread — collapsible, subordinate to conversation |
| **Repair Order** | Workflow authority for a visit — work to be done, not the first thing when the phone rings |
| **Observation** | Interpretive truth — what happened and why it matters (not UI, not a task) |
| **Pressure** | Operational pain made visible before enforcement — observe, surface, measure first |
| **Authority** | What is true — append-only operational truth (customers, ROs, messages, calls) |
| **Projection** | Packaged answer for an audience — disposable, rebuildable from authority (never becomes truth) |

Do not invent parallel vocabulary for the same concept. Earn new words through the notebook loop.

---

## Development loop (Era 4)

**Don't open another doctrine document for a while. Open the shop.**

```text
Pressure → questions → observations → vocabulary → product
```

Use Companion. Write observations. Ship the clusters that earn themselves.

If six months from now the notebook has changed the product more than brainstorming would have, the doctrine has become how ARK evolves — not just how it is documented.

**Do not build major Companion features until the notebook earns them.**

Notebook: [`../companion-v1/companion-pocket-notebook.md`](../companion-v1/companion-pocket-notebook.md)

Companion mission (frozen): [`../companion-v1/MISSION.md`](../companion-v1/MISSION.md)

---

## Future notebook (not yet)

After Companion has been in pocket long enough — maybe three or four months — start a second log:

**Things We No Longer Do**

The inverse of a bug tracker. Not what is broken — what work ARK has eliminated.

```text
We no longer…
…open ARKv2 to return a call.
…ask which vehicle the customer owns.
…wonder who promised what.
…lose voicemail context.
…switch apps to answer a customer.
```

That notebook validates whether the product is actually changing how the shop operates. Create it when observation proves elimination — not before.

---

## Companions

- [ark-constitution-v1.md](ark-constitution-v1.md) — constitutional principle: coherence over capability
- [communications-foundational-doctrine-v1.md](../communications/communications-foundational-doctrine-v1.md) — communications authority
- ark-surfaces.mdc — three applications, one runtime
