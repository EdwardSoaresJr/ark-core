# Maintenance Service Authority v1

**Status:** Frozen · Engine Oil vertical slice  
**Peer patterns:** Inspection Authority · Flag Recognition  
**Slice:** `kind = engine_oil` only — other kinds earn their way through shop use

---

## Principles (before architecture)

### Maintenance Principle #1

**Unknown is better than wrong.**

Shop preparation may assist technicians. Historical Maintenance Service Events preserve technician-confirmed truth. Vehicle Specification is absent until ARK has earned authoritative manufacturer data. **Preparation must never masquerade as manufacturer specification.**

Governs VIN decoding, capacities, filters, transmission fluid, coolant, power steering — invent nothing from shop preference.

### Maintenance Principle #2

**Historical service events are evidence, not estimates.**

Once a `MaintenanceServiceEvent` exists, every downstream consumer — service history, reminders, stickers, portal, future preparation — reads from that evidence. They do **not** infer, recalculate, or substitute preparation data.

---

## Vocabulary

| Internal | Meaning | Customer-facing |
| --- | --- | --- |
| **Prepared Service** | What the shop prepared to install | Estimate: **Includes** (never “Expected”) |
| **MaintenanceServiceEvent** | Immutable historical install fact | Invoice / Portal: **Installed** + specifics |
| **Sold Service** | `RepairOrderLineType::Package` total | Package title + price |
| **Vehicle Specification** | Manufacturer truth | **NOT IMPLEMENTED** |

If columns temporarily use `expected_*`: **Expected = shop preparation, not vehicle truth.** Prefer `prepared_*`.

```text
MaintenanceService          = mutable operational state on the current RO
MaintenanceServiceEvent     = immutable historical fact the vehicle received service
```

---

## Pattern

| Inspection | Flag Recognition | Maintenance |
| --- | --- | --- |
| Observe → Evidence → Review | Concern complete → Recognition | **Prepare → Install → MaintenanceServiceEvent** |

```text
MaintenanceService
        │
Confirm Installed
        ▼
MaintenanceServiceEvent   (append-only + stable service_sequence)
```

Never a bare type name `ServiceEvent`.

---

## Four truths (never overwrite)

1. **Vehicle Specification** — NOT IMPLEMENTED; Unknown is honest  
2. **Prepared Service** — editable preparation on the session  
3. **Installed → MaintenanceServiceEvent** — historical evidence  
4. **Sold Service** — PACKAGE line on the RO  

Shop defaults may prepare. They must not impersonate vehicle specifications.

| Prefill (no prior current event) | Allowed? |
| --- | --- |
| Shop-preferred oil brand/family | Yes |
| Washer policy | Yes |
| Package price / “up to N qt” allowance | Yes — **sold** only |
| Universal filter SKU / capacity / viscosity-as-fact | **No** |

Auto-detect (A+): latest **current** (non-superseded) `MaintenanceServiceEvent` for vehicle + kind → Prepared; else shop prep defaults only.

---

## Append-only events + service_sequence

No in-place mutation of an event.

| Field | Role |
| --- | --- |
| `service_sequence` | Per vehicle + kind ordinal (Oil Service #1, #2…) — stable across corrections |
| `revision` | 0 on first event; increments on superseding correction |
| `superseded_by_event_id` | Points to replacement; null = current |

Correction of #2 creates a new row with the **same `service_sequence`**, higher revision. It does not become Oil Service #3.

Consumers read the **current** event per sequence. Originals remain forever.

---

## PACKAGE line semantics

`RepairOrderLineType::Package` is first-class sold semantics — not ordinary Labor.

Must not contaminate:

- Technician flag recognition hours  
- Effective labor rate / ordinary labor GP  
- Labor vs parts reporting that means hourly labor  

PACKAGE quantity is never flag hours. Package dollars never invent hourly labor.

---

## Sticker / history gates

| Consumer | Source |
| --- | --- |
| Estimate **Includes** | Prepared |
| Sticker **Print** (tech ticket) | Prefer current event; else Prepared / RO mileage — **not gated** on Confirm Installed |
| History / reminders / Auto Detect | Current `MaintenanceServiceEvent` only |
| Invoice / Portal | Event specifics; else honest incomplete |

Confirm snapshots **service mileage** on the event. Next-due = that mileage + shop interval.

---

## Lifecycle

- One **alive** `engine_oil` MaintenanceService per RO (idempotent Add while concern + package line exist).  
- Cancel PACKAGE ↔ tear down session (pre-event). Manual concern/line delete cancels orphan sessions so Add can run again.  
- After event: correction = superseding event only. History never reads Prepared as Installed.

### Extra quarts (beyond package)

Package sell price may include “up to N qt.” Additional quarts are **Part lines at cost** under the same repair action — never PACKAGE mutation, never Installed history. A PACKAGE (or Labor) line is a parts-attach anchor so the composer / Extra quarts control can add them.

---

## Supplier / PartsTech boundary (frozen — not built in this slice)

**Supplier data is advisory, not authoritative. Supplier fitment assists preparation and procurement. Technician confirmation creates historical truth.**

Applies equally to PartsTech, Worldpac, NAPA ProLink, dealer EPC, and any future supplier connector.

PartsTech (and any future supplier) is **two capabilities**. ARK consumes them separately. Neither becomes historical truth.

| Supplier capability | Feeds ARK authority | Must not become |
| --- | --- | --- |
| Parts lookup / fitment | **Prepared Service** (mutable preparation) | Vehicle Specification · MaintenanceServiceEvent |
| Parts procurement | **Repair Order part lines** (ordering) | Installed history · package sell price |

```text
Lookup → updates Prepared only
Import/order → part lines (procurement), independent of PACKAGE
Confirm Installed → MaintenanceServiceEvent (technician reality)
Next visit Auto Detect → latest MaintenanceServiceEvent only
```

Wrong quote, out-of-stock substitute, shelf grab of a different filter — tech corrects on Confirm. Warranty / “what did you install?” answers from the **event**, not estimate, invoice, PartsTech quote, or PO.

**Provenance badges** (shop default · supplier suggested · technician confirmed) — earned later after floor proof. Do not build until Prepared is naturally treated as a starting point and Installed as final truth.

---

## Explicit non-goals (this slice)

Transmission / coolant / differential / brake fluid · Inventory · VIN fluid DB · PartsTech oil lookup (earned after floor trial) · provenance badges · Service Catalog · Operation Authority expansion · other maintenance kinds until Engine Oil earns them on the floor.
