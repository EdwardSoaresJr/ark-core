# Inspection Workspace

**Status:** Canonical — evolves with floor observation  
**Authority:** [Inspection Authority](inspection-authority.md) — do not redefine here  
**Supersedes:** Interaction portions of [Inspection Authority v1.5](inspection-authority-v1.5.md) and [Workflow Principle 1.5](inspection-workflow-principle-1.5.md)

Part of the inspection stack: **Authority** (immutable) → **Projection** (audience) → **Workspace** (this document).

---

## Principle

The **checklist is the inspection workspace.**

There is no primary “Finding,” no “Capture” sheet, no separate inspection form. The technician walks configured **inspection points**; each point **is** the living record.

```
RO Workspace → Inspection
─────────────────────────────────
Front Brakes

Condition    ○ OK   ○ Monitor   ○ Replace

Previous visit    Front pads 5 mm · 4 months ago

Measurements · Photos & video · Notes
Recommendation hint (read-only until advisor acts)

[ Next → ]
─────────────────────────────────
Rear Brakes
...
```

**Next →** not Save. Everything **autosaves**. The technician thinks about the vehicle, not persistence.

---

## Operator rhythm

Every interaction in the Inspection Workspace should preserve the technician's **physical rhythm around the vehicle**.

The software follows the technician. The technician should never have to follow the software.

Auto-save, **Next →**, walk order, persistent orientation, and inline evidence all exist to preserve that rhythm. Any interaction that interrupts it must justify its existence through repeated observation.

### Walk sequence

1. Open assigned RO → **Inspection** (production surface — not Estimate Review tab).
2. Walk the vehicle in **walk order** (shop-configured; today: template `position` until walk-order settings ship).
3. Tap **condition** on each point — autosaves immediately.
4. Glance **previous visit** before entering today’s evidence.
5. Add measurement / photo / video / note when the point requires or merits it.
6. **Next →** to the following point.

Categories (Tires, Brakes, Fluids) **collapse** the list. They do not drive navigation.  
Primary order = **walk order** across the vehicle.

---

## Configuration (shop-owned)

| Setting | Describes |
|---------|-----------|
| **Template** | **What** — labels, measurement definitions, photo policy per point |
| **Walk order** | **How** — sequence shops actually move around the car |
| **Condition labels** | **Language** — OK/Monitor/Replace vs Pass/Monitor/Fail |

Different shops inspect differently:

```
Shop A:  Exterior → Interior → Engine
Shop B:  Engine → Lift → Road test
```

Walk order belongs in **Settings**. Template describes *what*. Walk order describes *how*. Very different concerns.

### Condition labels (projection)

Authority stays `observed_state` enums. Buttons are configurable per shop — not hardcoded Good/Monitor/Failed, not mandatory Green/Yellow/Red.

### Photo policy (per point — not per condition)

| Policy | Example |
|--------|---------|
| **Required** | Windshield crack |
| **Recommended** | Battery when replacing |
| **Forbidden / pointless** | Oil level dipstick |

Photo rules live on **template point configuration**, not “always photo on red.” Condition may **prompt** evidence; policy **defines** what the point expects.

---

## Living record field order

When a technician opens one point:

1. **Condition** — tap targets (autosave)
2. **Previous visit** — last condition/measurement for this point (projection)
3. **Measurements**
4. **Photos & video**
5. **Notes**
6. **Recommendation hint** — advisor-facing projection; inspection does not create work

Previous visit before photos: *“Last time 5 mm — today 1 mm”* changes the inspection before they capture anything.

---

## Concern linkage (differentiating rhythm)

**Vehicle health** — point with no concern link:

```
Front Brakes → Replace → evidence on point
```

**Complaint-driven** — same point, linked scope:

```
Customer: Grinding
    ↓ linked
Front Brakes → Pads 1 mm
```

Inspection and diagnosis merge. No duplicate entry, no copying from `verified_findings`, no retyping.

Show concern-linked points under **scope headers** on production and review surfaces. Vehicle-health points stay on the walk.

---

## Vocabulary gap workflow

When the checklist cannot express reality:

1. **Checklist first** — use or extend template.
2. **Reality wins** — add a point to the template when the gap repeats.
3. **Freeform last** — ad-hoc point or prose only when vocabulary has not caught up yet.

Track freeform usage as **vocabulary gap signal**, not workflow preference.

---

## Adoption (floor — not dashboard)

**Phase:** Architecture is validated. **Adoption is the test now.**

The question is no longer *did we build the right thing?* It is *did the shop naturally choose to use it?* A technician can like a feature and bypass it, or complain and still use it because it helps. **Behavior is the authority.**

Do not build adoption dashboards for at least a month. `php artisan ark:inspection-adoption` prints traces that may **confirm** what you already noticed on the floor — not decide what matters.

After Landon's week, run the same *home* question across subsystems: Intake, Inspection, Conversation, Board — did this workspace become the natural place to work without navigation forcing it?

---

## ARK translators (toward decisions)

| Surface | Translates |
|---------|------------|
| **Operational Language** (intake) | Customer → shop meaning |
| **Inspection** | Reality → operational truth (condition) |
| **Conversation** | History → relationship |
| **Workspace** | Truth → action |

Everything translates toward **decision-making**, not storage.

---

## Web vs mobile

One inspection experience — **shared projections in Operations** — projected onto different surfaces. Improving inspection improves Inspection, not "mobile inspection" or "desktop inspection."

| Surface | Posture |
|---------|---------|
| **Operations projections** | `InspectionWalkWorkspaceProjection`, `InspectionItemLivingRecordProjection`, `InspectionChecklistItems` |
| **Web** | Walk workspace, auto-template, condition autosave, Next → |
| **Mobile API** | Same living record + checklist; surface-specific evidence URLs |

Do not fork authority or duplicate living-record logic per surface.

### Role surfaces

| Role | Default entry | Inspection |
|------|---------------|------------|
| **Technician** | Production / My Work | Walk + condition taps; hide advisor tabs |
| **Advisor** | Estimate review | Read conditions; promote recommendations |

---

## Non-goals (workspace)

- Primary `+ Finding` / intent-first capture modal
- Category-first navigation or completion % as headline
- Save buttons on the walk (autosave only)
- Status-driven photo enforcement overriding point policy
- Required checklist completion as billing or lifecycle gate
- Customer portal / Send Inspection Link (until delivery phase)

---

## Build priority (after P0)

P0 shipped: web walk workspace, auto-template, shared Operations projections, vocabulary-gap demotion.

Earn next through observation — not roadmap momentum:

1. Walk order settings (shop sequence vs template position).
2. Photo policy per point (required / recommended / forbidden).
3. Concern-linked points under scope headers.
4. Derivation automation (recommendation → estimate candidates) — only if adoption holds.

---

## Day 1 notebook (four sections only)

Nothing else until these stay healthy for a week.

### 1 — Begin

Did the tech **open Inspection first**, or bypass to something else (RO overview, conversation, verified findings, prose elsewhere)?

### 2 — Return

Did they naturally come back?

```
Inspection → Conversation → Inspection
```

or

```
Inspection → RO overview → Inspection
```

If yes, Inspection is becoming **home**.

### 3 — Vocabulary gaps

Every freeform escape hatch use is **product input**, not technician error.

```
Needed:     Trailer wiring inspection
Didn't fit: Electrical checklist
```

→ tomorrow's template.

### 4 — Rhythm breaks

Not bugs. Moments where the **physical inspection stopped because of software**:

- Had to put the flashlight down
- Needed both hands
- Camera opened too slowly
- Couldn't reach Next with gloves on

These are **momentum** problems. They matter more than UI polish.

Operator rhythm is **literal** here — walk order, phone, flashlight, lift, hood. If ARK asks the technician to stop moving around the vehicle, it failed.

