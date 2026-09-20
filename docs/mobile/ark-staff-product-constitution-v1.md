# ARK Staff — Product Constitution v1

**Status:** Frozen — protect, do not re-debate.  
**Scope:** ARK Staff (mobile) and `/api/mobile/*` projections that feed it.  
**Audit:** [`ark-staff-moments-audit-v3.md`](ark-staff-moments-audit-v3.md) (Shop Posture — observation complete).  
**Not:** Another platform doctrine. Seven PR questions + three invariants + one design loop.

---

## The product (one sentence)

> **The operator should never have to inspect the operation to understand its state.**

ARK is an **operating system for an automotive shop** — not a CRM with a nicer RO screen. Every surface helps the operator **regain control of the shop**, not navigate software.

**Design question (start every PR here):**

> **What state of the operation does this help the operator understand or resolve?**

Not: *What screen should this live on?*

---

## Three invariants (frozen)

| Layer | Owns |
|-------|------|
| **Authorities** | Truth — what happened, what exists |
| **Observations** | Change — what it means (*customer replied* is an observation, not a "moment layer") |
| **Posture** | The operation — how the shop feels right now |

**Everything else is presentation.**

Full loop (authority-driven — not a checklist):

```
Authority changes
    ↓
Observation created
    ↓
Posture changes (Shop · Station · Workspace)
    ↓
Finish Work appears (one thing)
    ↓
Operator finishes work
    ↓
Authority changes
    ↓
Observation resolves
    ↓
Posture improves → ARK decides what matters next
```

**Finish Work** — design intent for what was called Next Actions. Not necessarily the UI label.

> **Finish Work is not a task list. It is the minimum action required to move the operation toward FLOWING.**

*"Next action"* implies a to-do list. **Finish work** asks: *What is preventing this operation from flowing?*

When Finish Work completes, **posture changes** — then ARK surfaces the next thing that matters. Not four parallel items. **One thing.**

| Wrong (task bucket) | Right (flow restore) |
|---------------------|----------------------|
| Call customer · Send estimate · Review notes · Open vehicle | **Contact Jason about estimate.** |

| Observation | Finish Work (one operational close) |
|-------------|-------------------------------------|
| Customer replied | Reply |
| Inspection complete | Review |
| Vehicle arrived | Check in |
| Estimate viewed (4×, no reply 18h) | Call Jason |

**Tied to operational state** — each Finish Work item has a clear close:

- Waiting on customer approval
- Vehicle ready for pickup
- Parts have arrived
- Customer has arrived
- Technician blocked
- Website lead needs first response

**Not Finish Work:** unread messages · notifications · reminders · generic tasks · navigation disguised as action.

If completing it does not **automatically** improve posture (because authority genuinely advanced, not because someone checked a box), it is not Finish Work — it is information or navigation.

### Where AI fits (invisible, not replacement)

AI reduces **decision budget** inside Finish Work — it does not replace the operator.

Not: *Call customer.*

Yes:

```
Finish Work
Call Jason.

Viewed estimate 4× · No reply for 18 hours · Best approval opportunity today.

Suggested opening:
"Hi Jason, I wanted to make sure you had a chance to review the estimate..."
```

Context and suggested opening — Edward still decides and acts. AI disappears into the product.

---

## Product stack (frozen)

```
Authority
    ↓
Observation
    ↓
Shop Posture          (always visible — every workspace)
    ↓
Workspace
    ↓
Finish Work
```

**Do not add:** Moments platform layer · Screens as design units · Modules.

**Three postures, one vocabulary, three scopes:**

| Scope | Question |
|-------|----------|
| **Shop** | How does the business feel? (FLOWING · WAITING · DECISIONS · INTERRUPTED) |
| **Station** | What is this place doing? (Front Counter WAITING · Bay 2 PRODUCING) |
| **Workspace** | What does this object need? (RO Waiting Approval · Customer Waiting Reply) |

Shop posture **persists** inside every workspace. The operation does not disappear on an RO.

**Emotional rule:** The interface should **mirror the emotional posture of the operation** — not flat CRM intensity.

---

## Standing review criterion (every UI change)

> If Edward opened this in the middle of a busy Tuesday, would he immediately understand the **current state of the operation** and **what the operation needs from him**?

If no — not finished, regardless of functionality shipped.

---

## PR litmus — seven questions (required for ARK Staff PRs)

Answer in PR description. Not "did tests pass?" — **did we make the operation easier to understand?**

### 1. Decision budget

**Does this reduce the operator's decision budget?**

If Edward has to stop and think *where to go next*, it is probably not done.

### 2. State clarity

**Does this improve the operator's understanding of the operation's current state?**

Every change should either make state **clearer** or make **acting on it** easier.

### 3. Finish work, not inform

**Does this help finish work instead of just exposing information?**

A surface that informs but does not move the operation forward should be questioned.

### 4. Posture improves on completion (closed loop)

**If the operator completes this, does the posture improve automatically?**

Because authority advanced — not because someone checked a box. If no, this may be navigation or information, not Finish Work.

### 5. Operation stays visible

**Does the operation stay visible while using this feature?**

No workspace should isolate the operator from the broader state of the shop.

### 6. Cross-device coherence

**Would this still make sense if the operator switched devices right now?**

Phone, desktop, VVX, tablet — posture and Finish Work should remain coherent (presentation may differ).

### 7. State-first (implicit in all above)

**What state of the operation does this help understand or resolve?**

If the PR cannot answer in one sentence, scope is wrong.

---

## What we do not re-litigate

- Beat generic CRM mobile apps on features — ARK is shop OS, not a contact database.
- Moments as a platform layer — use Observations.
- Unlock-only posture — posture everywhere.
- Modules — capabilities · workspaces · posture.
- More philosophy docs — apply this constitution.

---

## Primary metric

**Decision budget** — count stops where the operator chooses *where* before *what*. Fewer decisions, not fewer taps.

---

## Success

Edward anywhere in the app knows:

1. **Shop posture** (and workspace posture when in an object)
2. **One Finish Work** — the minimum action to restore flow
3. Without inspecting lists, queues, badges, or tabs

When Finish Work completes, posture improves and items **disappear because the operation advanced** — not because someone checked a box. That is fundamentally different from a CRM or traditional shop system.

The app learns Edward. Edward does not learn ARK.
