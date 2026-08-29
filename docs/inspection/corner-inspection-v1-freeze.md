# Demo Auto Repair Standard Vehicle Inspection — Corner Inspection v1.0

**Status:** Frozen · Phase 2A · Permanent doctrine  
**Scope:** Corner Inspection only — do not expand into Steering, Suspension, Under Vehicle, Under Hood, Exterior, or Road Test from this freeze.  
**Authority companions:** [inspection-authority.md](inspection-authority.md) · Builder architecture freeze (session) · [inspection-workspace.md](inspection-workspace.md)

---

## Philosophy

The software exists to support the inspection process.  
The inspection process does **not** exist to support the software.

The Standard Vehicle Inspection exists for one purpose:

> To consistently identify safety concerns, obvious reliability concerns, and developing maintenance needs before the technician becomes focused on the customer's original repair.

It prevents technician tunnel vision. It is **not** diagnosis, estimate, sales, teardown, or disassembly. Observation comes first. Recommendations, diagnosis, and repair happen later.

---

## Corner Inspection doctrine

The technician should never mentally jump around the vehicle. The inspection follows physical movement. Software adapts to the technician.

**Frozen walk order (v1):**

1. Left Front  
2. Left Rear  
3. Right Rear  
4. Right Front  

Complete everything at a corner before walking to the next.

### Every corner contains

| Group | Role |
| --- | --- |
| **Tire** | Tread depth (32nds) + pressure (PSI) — measured, not inferred |
| **Wheel** | Visual |
| **Brake assembly** | Pads (mm inner/outer) · Rotor (visual) · Caliper (visual) |
| **Brake hose** | Visual — belongs to the corner, not a later section |

Brand is ignored on Standard. No rotor thickness on Standard. No slide-pin teardown on Standard.

### Once per vehicle (Corner stage, after corners)

- **Brake fluid** — level + condition (once)  
- **Parking brake** — Green / Yellow / Red like other points  
- **Rear axle type** — Disc / Drum gate before rear corner brake paths  

Brake-fluid correlation prompts (pads healthy + fluid low → verify) are future observation helpers. They must **never diagnose**.

---

## Condition projection (Demo Auto Repair)

Technician workflow uses **Green · Yellow · Red**.

| Projection | Persisted `observed_state` |
| --- | --- |
| Green | `pass` |
| Yellow | `monitor` |
| Red | `fail` |

Literal color names are **never** authority. Colors are shop projection labels from Builder metadata (`condition_palette: gyr`). Other shops may use different labels without a code deploy.

**Green** — no expand.  
**Yellow / Red** — expand: structured observation · technician note · photo.

Demo Auto Repair policy: Yellow and Red require photographs. That policy lives in Builder metadata (`photo_policy: when_not_green`), not hardcoded platform law.

---

## Observation libraries

Structured observations (tire wear patterns, rotor conditions, hose conditions, etc.) are Builder metadata. Yellow/Red should prefer library chips over free-text-first. Free-text remains for “Other.”

Do not invent diagnostic language (“seized caliper”). Observable conditions only.

---

## Builder authority

The Inspection Template Builder remains the configuration authority for:

- measurements · observation libraries · color workflow · evidence requirements · technician prompts · corner / stage ordering  

Technician UI projects that metadata. No technician behavior should be hardcoded for Corner knowledge once Builder metadata is present.

Runtime authority remains `Inspection` → `InspectionItem` → measurements / photos / selected observations. Templates seed; they do not replace condition truth.

---

## Inspection cart (process, not software)

Assumed shop equipment: tablet, flashlight, tire pressure gauge, tread gauge, brake pad gauge, battery tester, scan tool, shop rag. Optional mirror / pick / pry for triggered work only.

---

## Non-goals (this freeze)

- Steering · Suspension · Under Vehicle · Under Hood · Exterior · Road Test redesign  
- Full dynamic Builder admin UI  
- Auto Concerns / estimates from corner findings  
- Rotor thickness SM on Standard  
- Hardcoding Demo Auto Repair photo policy as platform-invariant  
