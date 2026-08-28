# Inspection Templates v1 — Frozen Proposal

**Status:** Frozen for implementation · 2026-07-26  
**Authority:** existing `inspection_templates` → categories → items · one Inspection per RO  
**Legacy:** General Vehicle Inspection → archive (editable, non-default) · do not mutate into Standard/PPI

---

## Completion requirement vocabulary (binding)

| Code | Meaning |
| --- | --- |
| **CR** | Condition required — tech must choose Good / Monitor / Needs Attention / Failed / N/A (N/A only where noted) |
| **SM** | Structured measurement required — point is **not addressed** by condition alone; blank measurements = incomplete |
| **OM** | Optional measurement — may record; not required to address |
| **CE** | Conditional evidence — note and/or photo required when condition is Needs Attention/Failed **or** comparison prompt fires |
| **N/A ok** | N/A is a valid completion of the condition (must not masquerade as Good) |

**Invariant:** Tire tread (3-zone) and disc brake pad thicknesses are **SM**. Selecting Good with blank measurements does **not** count as addressed.

**Comparison prompts:** tech attention + optional teaching helper only. Never diagnose. Never create Concern/estimate.

---

## Shared brake / axle rules

**Rear axle first:** Disc | Drum (one choice for the axle).

**Disc wheel slot:**

```
{Corner} Brake
  Inboard pad:  ___ mm     [SM]
  Outboard pad: ___ mm     [SM]
  Rotor: Good | Monitor | Needs Attention | Failed | N/A   [CR]
  Notes / photo when CE
```

**Drum wheel slot:**

```
{Corner} Drum Brake
  Lining / shoe: Good | Monitor | Needs Attention | Failed | N/A   [CR]
  Hardware / adjuster: Good | Monitor | Needs Attention | Failed | N/A   [CR]
  Notes / photo when CE
```

**Tech prompt (same-wheel or L/R axle Δ ≥ shop threshold):**

> Pad wear differs by X mm. Take another look before moving on.  
> *Helper:* Uneven wear can come from pad movement, hardware, slides, caliper operation, or other brake problems.

**Threshold seed (tech attention only):** 2.0 mm same-wheel · 2.0 mm L/R axle · configurable · advisor abnormal-wear alert not frozen.

---

# Standard Vehicle Inspection (frozen)

**Default on every production RO · ~5–10 min · 30 points**

### Phase A — Arrival / outside

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| 1 | Warning lights / dash indicators | CR | CE if warn lit |
| 2 | Exterior lights (head / brake / turn / plate) | CR | — |
| 3 | Wipers / washer | CR | — |
| 4 | Obvious body / underbody damage (ground view) | CR | CE if damage |

### Phase B — Tires

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| 5 | LF tire — condition / damage | CR + **SM** Outer·Center·Inner `/32"` | CE if uneven/damage |
| 6 | RF tire — condition / damage | CR + **SM** Outer·Center·Inner | CE if uneven/damage |
| 7 | LR tire — condition / damage | CR + **SM** Outer·Center·Inner | CE if uneven/damage |
| 8 | RR tire — condition / damage | CR + **SM** Outer·Center·Inner | CE if uneven/damage |
| 9 | Tire pressure | CR + **SM** LF·RF·LR·RR **PSI** | — |

### Phase C — Under hood

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| 10 | Engine oil level / condition | CR | — |
| 11 | Coolant level / condition | CR | — |
| 12 | Brake fluid — level / condition | CR | CE if dark/contaminated |
| 13 | Battery / terminals (visual only) | CR | CE if corrosion · **no load/CCA on Standard** |
| 14 | Belts / obvious hose concerns | CR | — |
| 15 | Air filter | CR | — |
| 16 | Visible under-hood leaks | CR | CE if leak |

### Phase D — Lift / under vehicle

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| 17 | Fluid leaks (underbody) — note/photo source when found | CR | CE if leak |
| 18 | Steering linkage / tie rods | CR | — |
| 19 | Ball joints / obvious joint play | CR | — |
| 20 | Front struts/shocks — leaks or damage | CR | CE if leaking |
| 21 | Rear shocks/struts — leaks or damage | CR | CE if leaking |
| 22 | Rear bushings / components — obvious looseness or damage | CR | — |
| 23 | CV boots / driveline visual | CR | CE if torn |
| 24 | Exhaust condition | CR | — |

### Phase E — Brakes

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| — | **Rear axle type** Disc \| Drum | CR (gate before 27–28) | — |
| 25 | LF brake | CR rotor + **SM** inboard/outboard mm | CE if NA/Failed or Δ prompt |
| 26 | RF brake | same | same |
| 27 | LR brake | Disc SM+rotor **or** drum CR×2 | same |
| 28 | RR brake | same as 27 path | same |

### Phase F — Road / operational

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| 29 | Road test performed | CR · **N/A ok** (not safe / not performed / not applicable) | — |
| 30 | Road-test noise / vibration / drivability observation | CR · **N/A ok** if #29 is N/A | CE if Needs Attention/Failed |

**#29** = did we drive it? · **#30** = what happened? · #30 must not be markable Good if #29 is N/A (force N/A or lock until #29 = performed).

---

# Pre-Purchase Inspection (frozen)

**Advisor-selected · paid comprehensive · 79 points**  
No summary/conclusion green-buttons.

### Phase 1 — Exterior

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| 1 | Body panels — dents / prior repair clues | CR | CE if notable |
| 2 | Paint / clearcoat condition | CR | CE if poor |
| 3 | Rust / corrosion (visible exterior) | CR | CE if present |
| 4 | Glass / mirrors | CR | — |
| 5 | Headlights function | CR | — |
| 6 | Brake lights function | CR | — |
| 7 | Turn signals / hazards function | CR | — |
| 8 | License plate / marker lights | CR | — |
| 9 | Wipers | CR | — |
| 10 | Washer | CR | — |
| 11 | Horn | CR | — |

### Phase 2 — Cabin / HVAC / accessories

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| 12 | Seats — condition / operation | CR | — |
| 13 | Seat belts | CR | — |
| 14 | Airbag / SRS warning state | CR | CE if warn |
| 15 | Dash warning lamps (key-on) | CR | CE if lit |
| 16 | HVAC blower operation | CR | — |
| 17 | A/C cool (as applicable) | CR · N/A ok | — |
| 18 | Heat (as applicable) | CR · N/A ok | — |
| 19 | Power windows | CR | — |
| 20 | Power locks | CR | — |
| 21 | Power mirrors | CR | — |
| 22 | Odor / moisture / water intrusion clues | CR | CE if present |

### Phase 3 — Scan / readiness

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| 23 | Scan — stored codes | CR · capture via **evidence/import preferred**; manual code typing not required when attachment exists | Scan evidence required to address |
| 24 | Scan — pending codes | same | same |
| 25 | Scan — permanent codes (where supported) | CR · N/A ok if tool/module unsupported | same |
| 26 | Emissions readiness / monitors | CR · Ready / Not ready / Incomplete / N/A | CE screenshot preferred |

### Phase 4 — Tires / wheels

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| 27 | LF tire condition / damage | CR + **SM** Outer·Center·Inner | CE uneven/damage |
| 28 | RF tire | CR + **SM** | same |
| 29 | LR tire | CR + **SM** | same |
| 30 | RR tire | CR + **SM** | same |
| 31 | Tire pressure | CR + **SM** LF·RF·LR·RR PSI | — |
| 32 | Tire age / DOT date (if readable) | CR · N/A ok if unreadable | CE if aged |
| 33 | Wheel condition / curb damage | CR | CE if damage |
| 34 | Spare / inflate kit | CR · N/A ok | — |

### Phase 5 — Under hood / battery & charging

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| 35 | Engine oil level / condition | CR | — |
| 36 | Coolant level / condition | CR | — |
| 37 | Brake fluid — level / condition | CR | CE if poor · **single source** (no duplicate later) |
| 38 | Power steering fluid (if equipped) | CR · N/A ok | — |
| 39 | Washer fluid | CR | — |
| 40 | Transmission fluid (dipstick) / sealed note | CR · N/A ok if sealed | — |
| 41 | Battery terminals / physical condition | CR | CE if corrosion |
| 42 | Battery test — voltage / measured CCA or tool result (where practical) | CR + **SM** (voltage and/or CCA/result) · N/A ok if unsafe/unavailable + reason | CE of tester screen preferred |
| 43 | Charging system test — charging voltage / result (where practical) | CR + **SM** · N/A ok if unavailable + reason | CE preferred |
| 44 | Belts | CR | — |
| 45 | Hoses | CR | — |
| 46 | Air filter | CR | — |
| 47 | Cabin filter (if accessible) | CR · N/A ok | — |
| 48 | Visible under-hood leaks / residue | CR | CE if leak |
| 49 | Engine / transmission mounts (visual) | CR | CE if torn/collapsed |

*(Removed: charging-system visual-only.)*

### Phase 6 — Lift / underbody / driveline

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| 50 | Fluid leaks map (underbody) | CR | CE if leak |
| 51 | Steering linkage / tie rods | CR | — |
| 52 | Front ball joints | CR | — |
| 53 | Front control arms / bushings | CR | — |
| 54 | Front struts/shocks | CR | CE if leak |
| 55 | Rear shocks/struts | CR | CE if leak |
| 56 | Rear control arms / bushings / trailing components | CR | — |
| 57 | Sway bar links / bushings (visual) | CR | — |
| 58 | Wheel bearings — play / noise (LF/RF/LR/RR as checked) | CR | CE if play/noise |
| 59 | CV axles / boots | CR | CE if torn |
| 60 | Driveshaft / U-joints (if RWD/AWD as equipped) | CR · N/A ok | — |
| 61 | Exhaust / heat shields | CR | — |
| 62 | Transmission / transfer case seepage | CR | CE if seep |
| 63 | Differential seepage (if equipped) | CR · N/A ok | CE if seep |
| 64 | Frame / unibody / underbody rust or impact | CR | CE if present |
| 65 | Brake lines / hoses | CR | — |

### Phase 7 — Brakes

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| — | Rear axle type Disc \| Drum | CR gate | — |
| 66 | LF brake | CR rotor + **SM** pads | CE / Δ prompt |
| 67 | RF brake | same | same |
| 68 | LR brake | Disc or drum path | same |
| 69 | RR brake | same | same |
| 70 | Parking brake function | CR | — |

*(Merged away duplicate brake-fluid detail point.)*

### Phase 8 — Road test

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| 71 | Road test performed | CR · Yes / **N/A** · **reason required if N/A** | — |
| 72 | Start / idle / abnormal noises | CR · locked unless #71 = Yes · else N/A | — |
| 73 | Acceleration / hesitation | same | — |
| 74 | Transmission shift quality (as equipped) | CR · N/A ok if N/A equipped · locked unless #71 = Yes | — |
| 75 | Brake performance / pull | locked unless #71 = Yes | — |
| 76 | Steering pull / wander | locked unless #71 = Yes | — |
| 77 | Vibration / NVH | locked unless #71 = Yes | — |
| 78 | Cruise / ABS event if triggered (observe only) | CR · N/A ok · locked unless #71 = Yes | — |
| 79 | Post-drive leak recheck | locked unless #71 = Yes | CE if new leak |

**Invariant:** Road-test finding points cannot be Good if #71 is N/A.

### Phase 9 — Maintenance evidence

| # | Point | Req | Evidence |
| --- | --- | --- | --- |
| 80 | Service history clues / stickers / visible evidence | CR · N/A ok if none | CE if present |

**PPI total: 80 numbered points + rear axle gate** (was 78; +bearing, +battery/charging split, −visual charging, −duplicate fluid, +road-test performed split).

---

## RO assignment (frozen product)

```
Builder — Inspection
  ☑ Standard Vehicle Inspection — included every vehicle
  ○ Pre-Purchase Inspection — sold / requested this visit

Tech Work order
  {Template name} · Not Started | N of M checked · Start/Continue Inspection
```

One Inspection per RO · template required for visit · no silent Standard fallback when PPI selected · historical evidence immutable when templates edit.

---

## Implementation plan (next — not started)

### Gaps vs today’s schema

1. Multi-slot measurements per template point (tread×3, PSI×4, pad in/out, rotor condition slot, battery/charging results).
2. Completion rules: SM must block “addressed” when blank.
3. Disc/Drum axle gate + conditional point shape.
4. Comparison thresholds in shop settings + tech prompt + advisor observation (no diagnosis).
5. RO ↔ template assignment; default Standard; Builder PPI select.
6. Scan points: evidence/import first; no required manual P-code typing when attachment present.
7. Road-test performed gate locking dependent findings.
8. Seed Standard + PPI; archive General Vehicle Inspection (non-default, editable).
9. Settings: ensure shop can edit name/categories/points/measurements/evidence without seed overwrite of shop-edited templates.

### Build sequence (suggested)

| Step | Deliverable |
| --- | --- |
| 0 | Authority audit confirmed · migration design for multi-slot + assignment + thresholds |
| 1 | Template measurement slots + completion enforcement on walk |
| 2 | Seed Standard + PPI · archive GVI |
| 3 | Default Standard on RO · Builder PPI select · tech landing shows correct name |
| 4 | Brake Disc/Drum + comparison prompts |
| 5 | PPI scan evidence path · road-test performed gate |
| 6 | Tests per prior acceptance matrix · floor observation |

### Explicit non-goals this capability

Finish Inspection · lifecycle blocks · passwordless capture · Shop Memory · work verification · auto Concern from Δ · tire 3-zone comparison rules (observe first) · advisor abnormal-wear alert threshold (later).

---

**Frozen.** No further checklist expansion unless a listed change requires splitting a point during implementation.
