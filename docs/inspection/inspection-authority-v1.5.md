# Inspection Authority v1.5

**Status:** Superseded for interaction — see [Inspection Workspace](inspection-workspace.md). Authority: [Inspection Authority](inspection-authority.md).  
**Sequence:** Authority → Workspace → Derivation (2.x) → Customer delivery (2.0+)  
**Companions:** [Inspection Authority](inspection-authority.md) · [Inspection Workspace](inspection-workspace.md) · [1.0 Contract](inspection-authority-1.0-contract.md) (historical)

---

## Authority lock

**InspectionItem remains the inspection authority.**

**Finding** is technician-facing vocabulary only — same pattern as:

| Authority | Vocabulary |
|-----------|------------|
| `RepairOrder` | RO |
| `OperationalObservation` | Attention row language |
| `InspectionItem` | **Finding** |

### Do not create

- `findings` table
- Duplicate authority
- Parallel storage model
- Separate DVI module

### Source of truth (unchanged)

```
Inspection
  └─ InspectionItem          ← authority (UI: Finding)
       ├─ InspectionItemMeasurement
       ├─ InspectionItemPhoto
       └─ notes / observed_state (condition)
```

No schema change required for 1.5 workflow. No migration.

---

## ARK grammar (same shape as the rest of the product)

Inspection now follows the same stack as communications, labor, Flow, and recommendations:

```
Inspection Authority
  ↓ Finding (vocabulary)
  ↓ Observation
  ↓ Recommendation
  ↓ Estimate
```

Parallel to:

```
Events → Observations → Projections
Labor Authority → Doctrine → Projection
```

One grammar. Different authority sources.

---

## Workflow goal

Technicians should think:

> I found something.

Not:

> I am filling out an inspection.

The inspection experience must become **finding-first**, not **category-first**.

Inspection should feel like a **camera app**, not a checklist.

### Wrong (current posture)

```
Open Inspection
  → See categories
  → See progress (X of Y)
  → See inspection items
  → Record finding
```

### Right (1.5 target)

```
Open RO
  → I found something
  → Record it
  → Done
```

---

## Primary action

Replace checklist-first workflow with:

**+ Finding**

as the primary inspection action when a technician opens inspection.

Categories (Brakes, Tires, Battery, …) remain available for **organization and projection** — not as the primary workflow entry point.

Do not lead with:

- Category grid
- Completion percentages
- "X of Y items recorded" as the headline
- Seeded MPI rows on blank first open

### Landing screen (1.5)

When a technician opens inspection, lead with:

1. **+ Finding** (primary action)
2. **Recent Findings** (this RO — reverse chronological)

Categories (Brakes, Tires, Battery, …) exist for **organization**, not as the landing workflow. Findings are workflow; categories are projection.

---

## Speed constraint (adoption gate)

**+ Finding must be faster than typing a note in `verified_findings`.**

If Landon can type `RF pad 3mm` into verified findings faster than using + Finding, he will keep using verified findings. That is the adoption test.

Do not optimize 1.5 for completeness. Optimize for:

```
Phone → Camera → Measurement → Save
```

Target: **under 10 seconds** for a minimal finding (title + photo, or title + measurement + photo).

Intent, note, and concern link can be optional or deferred — never block the fast path.

---

## Finding capture (technician projection)

Mobile-first flow:

1. Tap **+ Finding**
2. Choose **intent:**
   - Safety
   - Maintenance
   - Diagnostic
   - Verification
3. Enter **title** (e.g. Front brake pads)
4. Add **measurement** (optional — e.g. 3 mm)
5. Take **photo(s)** (strongly encouraged; evidence-first)
6. Add **note** (optional)
7. Save

Behind the scenes: create `InspectionItem` + measurement rows + photo rows exactly as today.

**Intent** is capture vocabulary in 1.5 UI. Persist through existing authority fields (label, observed state, concern link, category inference, notes) until floor observation earns a dedicated column.

---

## Three inspection modes (projections — not authority)

Templates and UI modes project from the same `InspectionItem` authority:

| Mode | When | Examples |
|------|------|----------|
| **Vehicle Health** | Every vehicle; optional MPI-style template | Battery, brakes, tires, fluids, leaks, lights |
| **Concern Specific** | Customer complaint drives diagnosis | Overheating → cooling system, pressure test, fan operation |
| **Repair Verification** | After repair | No leaks, pressure holds, road test passed |

Demo Auto Repair default rhythm is **concern-first** (Layer 2), not menu-service MPI (Layer 1).

MPI templates seed items only. They must not become authority.

---

## Derivation chain (2.x — not 1.5 build)

After adoption is proven, the same Finding authority feeds the operational pipeline:

```
Finding (InspectionItem authority)
  ↓
Observation candidate
  ↓
Recommendation candidate
  ↓
Estimate candidate
```

**Advisor review required at every promotion step.**

- No automatic estimate generation
- No automatic customer recommendations
- No auto-created concerns without advisor action

This mirrors ARK's existing stack:

```
Events → Observations → Projections
```

Inspection findings are another **authority source** into the same grammar — not a parallel DVI product.

### Three audience projections (future)

| Projection | Shows | Hides |
|------------|-------|-------|
| **Technician** | Facts, measurements, photos | Customer language, pricing |
| **Advisor** | Finding + candidates + promote/ignore | Checklist chrome, completion % |
| **Customer** | Photo, measurement, plain recommendation | Internal notes, labor codes |

One authority row. Three projections. Zero duplicated truth.

---

## Non-goals (1.5 and until adoption earns more)

Do **not** build:

- `findings` table or Finding authority
- Inspection scoring
- Completion percentages as persisted or headline truth
- Green / yellow / red authority
- Required checklist completion
- Separate DVI module bolted beside the RO
- Customer portal / Send Inspection Link (Phase 2.0+)
- Auto recommendation or estimate engines tied to inspection rows
- **Auto Observation / Auto Recommendation / Auto Estimate** — even when technically feasible; prove adoption first (see below)

Inspection remains **RO-native authority**. Checklist templates remain **projections**.

### Derivation activation gates (do not skip)

Prove each step on the floor before building the next automation:

| Gate | Question |
|------|----------|
| 1 | Do technicians record findings (+ Finding)? |
| 2 | Do advisors review and act on findings? |
| 3 | Do customers respond to evidence-backed recommendations? |

Only after those survive contact with the shop should observation/recommendation/estimate **candidates** become active workflow.

---

## Adoption notebook (floor — not dashboard)

Track weekly alongside `php artisan ark:inspection-adoption`:

| Question | Why |
|----------|-----|
| Did the tech create a Finding? | Adoption |
| Was a photo attached? | Evidence adoption |
| Did advisor act on it? | Workflow adoption |
| Did customer approve from it? | Outcome |

The goal is not inspections. The goal is:

```
Finding → Evidence → Recommendation → Approval
```

If that chain does not happen, inspection is still documentation.

---

## Success criterion (1.5)

A technician **naturally chooses + Finding over `verified_findings`** when recording an observed fact.

Measure with `php artisan ark:inspection-adoption` — notebook, not dashboard theater.

Ask on the floor:

> You find 3 mm front brake pads. Where do you put that?

| Answer | Meaning |
|--------|---------|
| **+ Finding** | 1.5 workflow is working |
| **`verified_findings`** | 1.5 still loses to prose — fix friction before derivation or portal |

---

## ARK sequence (do not skip)

```
Doctrine → Authority → Observation → Workflow → Projection → Derivation → Delivery
```

1.0 established authority. **1.5 fixes workflow.** Derivation and customer delivery wait on adoption evidence.

---

## Shop build priority (Demo Auto Repair — sequencing)

Inspection v1.5 is spec-ready but **not the front-door blocker**. Prioritize when scheduling implementation work:

1. **Today / Active Repair Orders board** — advisors struggling to find ROs is daily operational friction affecting every repair order.
2. **Portal / public website unification** — customer surface continuity.
3. **Inspection v1.5 UI pass** — finding-first workflow after the above, or in parallel only if RO discovery is unblocked.

Inspection doctrine is stable. Do not let inspection UI work displace urgent workboard/RO discovery fixes.

Advisor discoverability: see `docs/operations/advisor-cockpit-discoverability-v1.md` — **Phase A (advisor cockpit) outranks inspection UI** at Demo Auto Repair.
