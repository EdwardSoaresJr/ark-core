# Inspection Authority

**Status:** Canonical — immutable  
**Companion:** [Inspection Workspace](inspection-workspace.md) (interaction — evolves)  
**Supersedes:** Authority substance in [1.0 Contract](inspection-authority-1.0-contract.md); interaction in [v1.5](inspection-authority-v1.5.md) and [Workflow Principle 1.5](inspection-workflow-principle-1.5.md)

## Inspection stack

```
Inspection
├── Authority      (this document — immutable)
├── Projection     (audience-specific — defined elsewhere)
└── Workspace      (interaction — inspection-workspace.md)
```

Same sequence as the rest of ARK:

```
Authority → Projection → Workspace → Delivery
```

**Projections** read inspection authority for different audiences. They are intentionally **not** defined here:

| Audience | Projection sees |
|----------|-----------------|
| Technician | **Condition** |
| Advisor | **Recommendation** |
| Customer (later) | **Vehicle health** |
| Manager (later) | **Coverage** |

One authority row. Multiple projections. Zero duplicated truth.

---

## Invariant: inspection preserves condition

> **Inspection does not create work. Inspection preserves condition.**  
> **Recommendations create work.**

Inspection records reality on the vehicle. Estimates, recommendations, and repair orders remain **separate authorities** because they answer different questions.

---

## What inspection authority is

One **inspection** per repair order. Each **inspection point** (`InspectionItem`) records the **current condition** of a configured vehicle check — supported by measurements, photos, video, and notes.

The authority is **objective** — about the vehicle, not the technician.

```
Inspection point
    ↓
Condition
    ↓
Evidence
```

The technician records **condition**. Photos, measurements, video, and notes are **evidence** for that condition — not a separate “finding” authority.

---

## Authority layers

| Layer | Owns |
|-------|------|
| `Inspection` | One session per `repair_order_id`; `started_at`, `notes` |
| `InspectionItem` | Label, **condition** (`observed_state`), notes, position, optional concern link, optional template link |
| `InspectionItemMeasurement` | Name, value, unit — rows, not JSON |
| `InspectionItemPhoto` | Storage path, purpose, content type — rows, not JSON. **Images and video** (MP4, MOV, WebM) share this store; `content_type` distinguishes them. |
| `InspectionTemplate` | Shop-configured **what** to inspect — seeds points, does not replace authority |

Templates seed inspection points. They are not authority.

---

## Vocabulary

| Code | UI / floor |
|------|------------|
| `InspectionItem` | **Inspection point** — or the label alone: *Front Brakes*, *LF Tire* |
| `observed_state` | **Condition** — not “status” (RO, conversation, and vehicle already own status) |

Do not expose “inspection item,” “finding,” or implementation class names to technicians.

---

## Condition (authority enum: `observed_state`)

Persisted values:

- `not_checked`
- `pass`
- `monitor`
- `needs_attention`
- `fail`
- `measure`
- `na`

Button labels (OK · Monitor · Replace · Pass · Fail · …) are **shop projection** — see [Inspection Workspace](inspection-workspace.md).  
Literal color names (`red`, `yellow`, `green`) are **forbidden** as persisted truth.

---

## Linkage

- Every point belongs to one `Inspection`.
- Optional `repair_order_concern_id` — links vehicle-health points to a customer complaint scope.
- Optional `inspection_template_item_id` — ties a point to shop template configuration.

---

## Living object (one point, one record)

Everything belongs to the inspection point. There is no sibling “finding” authority.

```
Inspection point (InspectionItem)
├── Condition
├── Previous visit (projection — prior RO rows for same template point)
├── Measurements
├── Photos & video (`InspectionItemPhoto` — `content_type` distinguishes)
├── Notes
└── Recommendation hint (projection — advisor promotes; not inspection authority)
```

---

## Invariant: vocabulary gap

**Freeform findings should only exist when the inspection vocabulary cannot yet express reality.**

Every freeform entry is **product feedback** — evidence that the inspection vocabulary is incomplete, not technician preference.  
If reality does not fit the checklist: **improve the checklist**, not another prose field.

`verified_findings` and ad-hoc free text are escape hatches until vocabulary catches up.

---

## Authority test

> If this inspection point disappeared, what operational knowledge would disappear with it?

| Answer | Belongs? |
|--------|----------|
| “Nothing.” | Probably not an inspection point |
| “We would lose brake condition history across five visits.” | Yes — core authority |

---

## Operational pillars (siblings)

| Pillar | Preserves |
|--------|-----------|
| **Operational Language** (intake) | Intent |
| **Operational Condition** (inspection) | Reality |
| **Operational Truth** (history) | Durable condition across visits |
| **Workspace** | Momentum |

```
Operational Language  →  concern / scope enters the shop
Operational Condition →  reality discovered on the floor
Operational Truth     →  durable history across visits
```

Inspection **translates reality into operational truth** — toward decision-making, not storage for its own sake.

---

## Future authority (not before earned)

### `InspectionPointConcept`

Parallel to `ScopeEntryConcept`. **Do not build until:**

> Repeated template evolution demonstrates that multiple template points describe the same operational meaning.

Same earning rule as scope entry concepts. Prevents concept fever.

When earned: unlocks history, analytics, templates, and workflows across visits **without AI**.

---

## Forbidden

- `findings` table or parallel finding authority
- Persisted red / yellow / green color authority
- Auto-generated estimate lines from inspection rows
- Customer portal / Send Inspection Link (delivery phase — not authority)
- Auto recommendation or observation engines tied to inspection rows
- Completion percentage or scoring as **authority**
- MPI/DVI as a separate truth layer beside `InspectionItem`

---

## Success criterion

A technician can record the **condition** of configured inspection points on a repair order — with evidence — without a separate workflow object.

Vehicle history across visits reads **measurement and condition rows**, not concern prose blobs.
