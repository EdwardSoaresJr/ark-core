# Certification record — Customer Arrival Workflow

**Certification:** Customer Arrival Workflow · **Workflow 1 of 5**  
**Track:** B — ARK Staff (primary execution surface)  
**Role:** Advisor (Edward)  
**Owner:** Alex Rivera  
**Scenario source:** Customer walks in or arrives on lot · phone only  
**Workflow doc:** [workflow-completion-certification.md](../../engineering/workflow-completion-certification.md)

## Why this matters

A customer arrives. Edward handles the entire arrival — find or create customer, scan VIN, verify vehicle, capture concern and photos, open RO, assign technician — from his pocket. **Desktop never touched.**

---

## PASS levels

| Level | Status | Date | Signed by |
|-------|--------|------|-----------|
| Engineering Certified | ⬜ | | End-to-end path on device |
| Operationally Certified | ⬜ | | Floor scenario |
| Production Certified | ⬜ | | One week daily use |

---

## Operational acceptance — phone only

```
Customer arrives
        ↓
Find / create customer
        ↓
Scan VIN
        ↓
Verify vehicle
        ↓
Capture concern
        ↓
Vehicle walk-around (camera-first)
  Front · Driver · Passenger · Rear · Odometer · Damage
        ↓
Create / open RO
        ↓
Assign technician
        ↓
Done
```

| Step | Acceptance | Status | Date | Proof |
| --- | --- | --- | --- | --- |
| Continuity / observation | Waiting customer surfaces on Home | ⬜ | | |
| Find or create customer | Customers tab or intake | ⬜ | | |
| Scan VIN | Camera-first — not typed from memory | ⬜ | | |
| Verify vehicle | Year/make/model/plate confirmed | ⬜ | | |
| Capture concern | On RO or intake | ⬜ | | |
| Vehicle condition documented | Walk-around: Front/Driver/Passenger/Rear/Odometer (+ Damage) auto-attached to RO | ⬜ | | Engineering path ✓ — floor proof pending |
| Create or open RO | Authority persisted | ⬜ | | |
| Assign technician | Landon sees on Work | ⬜ | | |
| Context preserved | No back-out-and-search | ⬜ | | |
| Desktop never touched | Full chain phone-only | ⬜ | | |

**Fail condition:** Any operational step requires desktop, or operator loses thread.

**Design gate:** What can Edward finish while standing next to the vehicle?

---

## Engineering capability checklist

| Check | Status | Evidence |
| --- | --- | --- |
| Continuity + orientation | ✓ | `GET /api/mobile/continuity` |
| Customer Workspace | ✓ | `GET /api/mobile/customers/{id}` |
| Mobile intake + VIN decode API | ✓ | `/api/mobile/intake/*`, vin-decode |
| Technician assignment API | ✓ | PATCH assignment |
| Flutter: arrival spine (identify → customer → open RO → assign → RO workspace) | ✓ | `check_in_screen.dart` — one continuous flow, ends in RO workspace |
| Flutter: start arrival from open customer/vehicle (no re-search) | ✓ | `CheckInScreen(initialCustomerId)` from Customer FAB · Vehicle "Start RO" |
| Flutter: check-in is a real leaveable screen | ✓ | Scaffold + AppBar (customer name = persistent identity) |
| Flutter: vehicle walk-around (camera-first, auto-attach) | ✓ | `vehicle_walk_around_screen.dart` — camera opens per angle, attaches to RO as `Observation` finding, advances; runs in arrival after RO opens; also on Vehicle "Walk-around" FAB |
| Backend: condition photos attach to RO authority | ✓ | Reuses inspection evidence (`Observation` intent) — no parallel media silo; test: "advisor can document vehicle condition at arrival" |
| Flutter: end-to-end arrival chain (device proof) | ⬜ | On-device recording — all steps now wired |
| Desktop reflects phone truth after | ⬜ | Open RO on desktop — all captures present |

---

## Operational scenario (floor script)

1. Customer on lot; Edward has phone only.
2. ARK Staff → find/create customer → scan VIN → verify vehicle.
3. Concern + photos → create/open RO → assign Landon.
4. Landon confirms assignment on Work tab.
5. Edward opens desktop later — everything already there.

Record: video or RO # + timestamp.

---

## Notes

- Workflow 1 of 5 toward [Phone-First Shop](./phone-first-shop.md).
- Portable Station Phase 1 engineering foundation: [portable-station-phase-1.md](./portable-station-phase-1.md).

## Corrections

- **2026-06-28:** Chain expanded — VIN scan, find/create, verify vehicle (primary execution surface framing).
- **2026-06-27:** Arrival spine wired as one continuous, leaveable flow. `CheckInScreen` now owns a Scaffold/AppBar (was a bare `ListView` pushed with no back/title) and accepts `initialCustomerId` so arrival starts from a customer/vehicle already on screen — no back-out-and-search. Entry added to Customer Workspace FAB ("Check in") and Vehicle "Start RO" (both preseed the customer). Flow ends in the RO workspace (no dead end).
- **2026-06-27 (hardening):** Walk-around made floor-ready — guided capture loop now actually auto-advances angle to angle (was gated by the busy flag and stopped after the first shot); each captured angle shows a **thumbnail** (visual proof it saved); a failed upload **keeps the photo and offers Retry** instead of forcing a re-shoot (lot-wifi tolerant); finishing incomplete prompts a confirm; an accidental back-swipe mid-walk-around is guarded.
- **2026-06-27:** Photo gap closed as **Vehicle Walk-Around** (`vehicle_walk_around_screen.dart`) — not an "add photo" feature. Camera-first: each angle (Front · Driver · Passenger · Rear · Odometer, + optional Damage) opens the camera, auto-attaches to the RO, and advances; no gallery/source picker/upload dialog. Runs automatically in arrival after the RO opens, and from the Vehicle "Walk-around" FAB. Photos attach to the **existing inspection evidence authority** as `Observation` (document-only) findings — no parallel media silo, doctrine-aligned. Backend unchanged (advisor-permitted `Observation` intent already supported). All required steps now wired; Engineering Certified pending on-device recording.
