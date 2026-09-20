# Evidence Authority v1

**Status:** Frozen · Slice 1 complete · Stop  

**Classification:** Foundational Operations authority  
**Companions:** Inspection photos (parallel until migrate) · Flag Recognition immutability · MaintenanceServiceEvent

## Principle #1

**Evidence is proof, not explanation.**

Evidence records what was seen or captured. Captions may describe. Evidence never diagnoses.

| Allowed | Forbidden |
| --- | --- |
| Inner brake pad 2 mm | Failed water pump |
| Coolant residue at water pump | Bad transmission |
| Torn outer CV boot | Needs engine |

Diagnosis belongs to Concerns, Inspection findings, Recommendations, and Maintenance.

## Naming

| Layer | Meaning |
| --- | --- |
| **Evidence** (this authority) | File proof — photo / video / pdf on a Repair Order |
| Journey / Briefing “evidence” | Explainability Show-me DTOs — not blobs |

## Immutability

- File bytes are immutable. `storage_path` never changes after create.
- Wrong capture → upload another; **retire** the bad row (`deleted_at`).
- Caption may be edited. Visibility changes **only** through audited action.
- `RetireEvidenceAction` soft-retires. Bytes remain on disk in v1. Physical purge is a later retention capability. Never delete disk bytes while the row claims the file exists.

## Schema (authority)

### `evidence`

`repair_order_id` · `type` (photo|video|pdf) · `source` (camera|upload|migration|system) · immutable `storage_path` · mime/size/name · `uploaded_by_user_id` · `taken_at` · `caption` · `visibility` (internal|pending|shared) · `shared_at` · `first_customer_viewed_at` · `sort_order` · `deleted_at`

Default visibility: **internal**.

### `evidence_attachments`

Morph to attachable · `is_primary` (composition) · unique (evidence, morph).

### `evidence_visibility_history`

Append-only: old · new · actor · `changed_at`. Every visibility transition (including create → internal) writes a row.

## RO boundary (invariant)

`evidence.repair_order_id` **must equal** the attachable’s `repair_order_id`.  
No cross-RO attachment. Same-RO reuse across concerns is fine. Cross-visit copy is a future audited action.

## Visibility

| State | Audience |
| --- | --- |
| Internal | Advisor + tech |
| Pending | Advisor + tech (needs review) |
| Shared | Advisor + tech + customer |

Show to Customer is an explicit advisor write. Customer surfaces allowlist **Shared** only; unknown values stay hidden.

### Customer viewed (precise meaning)

`first_customer_viewed_at` means the customer successfully opened a **customer-facing surface that presented** this Shared evidence (Portal estimate/report render, or an explicit view event from that surface).

**Not** set from media stream, thumbnail preload, or retry. Media routes remain authorization-only.

### Shared timestamps

- `shared_at` — first transition to Shared  
- `first_customer_viewed_at` — first qualifying presentation (once)

## Primary

`SetPrimaryEvidenceAction` is the only writer of `is_primary`:

1. Same RO as attachable  
2. Clear current primary for that morph target  
3. Set requested attachment primary  
4. Reject retired evidence  

**Fallback:** customer surfaces → first active **Shared** by `sort_order`; staff → first active regardless of visibility.

## One gallery

One RO Evidence gallery. Filters: All · Concern · General. Concern cards filter the same authority. No parallel concern-photo store.

## One renderer

Consumers use `EvidenceProjection`. Never per-domain photo renderers.

## Slice 1 consumers

- Morph: `RepairOrderConcern` · `RepairOrder` (General)  
- Staff gallery + visibility + primary + retire  
- Portal Shared rendering + presentation-time viewed  
- Minimal mobile upload/stream API  

## Non-goals (Slice 1)

- Migrate `InspectionItemPhoto`  
- Other morph consumers  
- `EvidenceObservation` / AI  
- RO timeline UI (events must remain emit-capable)  
- Physical byte purge  
- MMS / estimate PDF documents folded in  

## Slice 2 (later)

Inspection migrate · Maintenance attach · Timeline surface · Retention purge
