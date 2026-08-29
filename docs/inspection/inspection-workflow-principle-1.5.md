# Inspection Workflow Principle (1.5)

**Status:** Superseded — see [Inspection Workspace](inspection-workspace.md)  
**Companion:** [Inspection Authority](inspection-authority.md)

Inspection Authority and Inspection Workflow are separate concerns.

| Layer | Question |
|-------|----------|
| **Inspection Authority** | Where do observed facts live? |
| **Inspection Workflow** | How does a technician record those facts? |

The authority layer is established through **Inspection**, **InspectionItem**, **Measurements**, and **Photos**.

Workflow changes must **not** redefine authority.

---

## Guiding principle

**Recording an observation must be easier than typing `verified_findings`.**

Not equal. **Easier.**

If recording a fact in Inspection requires more effort than writing prose, technicians will naturally choose prose — and the authority layer will not be adopted.

Humans take the path of least resistance.

---

## Default mental model

Inspection is **not** a required multi-point inspection.

Inspection is a place to record **observations discovered during diagnosis and vehicle inspection**.

The first screen should teach:

> Record what you found.

Not:

> Complete this inspection.

**Do not** respond to low adoption by adding required categories, completion percentages, severity colors, or more pre-seeded rows. That drifts back toward MPI-first thinking.

---

## Preferred workflow

1. Technician identifies an observation.

   Examples:

   - Front brake pads measure 2 mm
   - Battery leaking
   - Inner tie rod loose
   - Coolant seep at water pump

2. Technician records a **Finding** (UI label; authority row is still `InspectionItem`).

3. The authority stores:

   - Category
   - Label
   - State
   - Measurements
   - Photos
   - Notes

Recommendations, customer presentations, colors, and future portal views are **projections** that read inspection facts. They are not alternate places to store truth.

---

## Demo Auto Repair workflow (not traditional DVI)

Traditional DVI assumes:

```
Vehicle arrives → Perform MPI → Find issues → Build estimate
```

This shop often works:

```
Vehicle arrives → Customer concern → Diagnosis → Observation → Estimate
```

That naturally leads to **+ Finding** as the primary action — not a checklist opened on first visit.

---

## Future MPI support

A multi-point inspection workflow may be offered later as an **optional projection** (e.g. Start MPI Template).

MPI templates must **not** become the authority layer.

Inspection Truth remains authoritative whether facts originate from:

- Concern-driven diagnosis
- Additional findings discovered while working
- Formal MPI workflows (optional, later)

---

## Observation week (before building 1.5)

Run the floor as-is. Use `php artisan ark:inspection-adoption` — not a dashboard — to see whether techs engage seeded rows, use Add Item only, or skip Inspection for prose.

Ask one question:

> You find 2 mm brake pads. Where do you put that?

| Answer | Meaning |
|--------|---------|
| **+ Finding** / inspection | Doctrine is working; 1.5 is friction and first impression |
| **`verified_findings`** | 1.5 must make Inspection easier than prose before any customer-facing work |

---

## Success criterion

A technician **naturally chooses Inspection over `verified_findings`** when recording an observed fact.

---

## 1.5 build spec

See [Inspection Authority v1.5](inspection-authority-v1.5.md). Summary:

| Change | Rationale |
|--------|-----------|
| Blank default on first open | Stop teaching checklist on first impression |
| **+ Finding** (not Add Inspection Item) | Record what you found; camera-app rhythm |
| Intent-first capture | Safety · Maintenance · Diagnostic · Verification |
| Categories secondary | Organization/projection only |
| Optional **Start MPI Template** | Defer; templates seed items, never authority |

Portal, Send Inspection Link, derivation chain, and severity colors remain out of scope until floor adoption is proven.

---

## ARK sequence (do not skip steps)

```
Doctrine → Authority → Observation → Workflow → Projection
```

Identity, Communications, and Inspection all follow this pattern. Do not jump from doctrine to features, or from low adoption to MPI enforcement.
