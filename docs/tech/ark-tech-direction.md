# ARK Tech — direction lock (DVI handheld)

**Status:** v0.1 stood up (staff `/api/tech` + `apps/ark_tech`). Shop Glass remains the sellable-track command center; ARK Tech is a **separate technician product**, not a Glass/Mobile mode.

**Certification hardware (first):** existing Demo Auto Repair **Android tablets** — rugged SKU deferred until floor log is filled.

**Auth:** human staff Sanctum via `POST /api/tech/auth/login` (technician or admin). Not `stn_` / `drg_` / `vce_`.

**APIs (thin projection over existing inspection actions):**

| Need | Route |
|------|--------|
| Login | `POST /api/tech/auth/login` |
| My Work | `GET /api/tech/me/work` |
| RO | `GET /api/tech/repair-orders/{id}` |
| DVI | `GET .../inspection` |
| Measurements / photo | `PATCH .../inspection-items/{item}` |
| Voice proposal (no write) | `POST .../voice` |
| Confirm | `POST .../voice/confirm` |
| Rewrite | `POST /api/tech/dragon/rewrite` |

ESP / ReSpeaker / CoreS3 remain **R&D** (`docs/voice/handheld-v0.1.md`), not Landon’s DVI device.


---

## Direction

We were inventing a **device** because **voice** looked like the product.

The product is **technician DVI capture**. Voice is one input. Camera is one input. Dragon proposes; the tech confirms. ARK remains the system of record.

That makes hardware boring in a good way: a rugged Android already has camera, screen, battery, Wi-Fi, mic, speaker, OS, and docks. If Look → Photo → Talk → Confirm → ARK is faster than web DVI on **tablets we already own**, we pick a 5–6″ handheld later from evidence. If it is not faster, we do not buy XCover units and we do not mill enclosures.

```
ARK              system of record
Shop Glass       advisor command center (1920×1080)
ARK Tech         technician handheld (portrait, glove, bay)
Hosted Dragon    AI employee inside ARK — augments, does not own
OpenAI           current reasoning/STT provider
```

ARK Tech must still inspect if Dragon/OpenAI is down.

**Not:** full ARK in a WebView. **Not:** Shop Glass with a `if (tech)` flag. **Not:** ESP walkie as Landon’s daily driver.

---

## Flutter architecture

| Option | Verdict |
|--------|---------|
| **A — fold into `apps/advisor_station`** | **Reject.** That app is Shop Glass: landscape 1080p, station pairing, advisor glance. Mixing DVI camera + PTT into it produces one giant conditional shell. |
| **B — distinct `apps/ark_tech`** | **Accept.** Technician UX, portrait, camera, PTT, pending uploads. |

**Shared later (extract when duplication hurts, not day one):**

- `packages/ark_api` — Sanctum staff client (same origin as today’s `/api/mobile/*`)
- `packages/ark_auth` — staff session, not `stn_` / `drg_` / `vce_`
- design tokens if they stay coherent
- media upload helper (idempotent photo queue)

v0.1 may copy a thin HTTP client from `advisor_station` rather than boiling a monorepo package. **Do not** share a single `MaterialApp` with Glass.

**Auth:** existing **staff mobile Sanctum** (`POST /api/mobile/auth/login` + device name). Writes attributed to `User`. Technicians already scoped to assigned work via `MobileStaffAccess`.

---

## ARK APIs (reuse, don’t fork)

Technician product language (`/api/tech/...`) is a **projection name**. Implementation for v0.1 is the **existing mobile staff API** plus a thin voice-proposal endpoint.

| Need | Existing / next |
|------|-----------------|
| Login | `POST /api/mobile/auth/login` |
| Me / profile | `GET /api/mobile/me` |
| My Work | `GET /api/mobile/work` (`MobileWorkProjection`) |
| Open RO | `GET /api/mobile/repair-orders/{id}` |
| DVI list / item | `GET .../inspection-checklist`, `.../items/{item}` |
| Save condition + measurements + photo | `PATCH .../inspection-checklist/items/{item}` |
| Voice utterance (proposal only) | **New:** `POST /api/mobile/voice/proposal` — STT + Dragon structured JSON, **no write** |
| Confirm proposal | **New:** `POST /api/mobile/voice/proposal/{id}/confirm` — then same update path as PATCH |
| Dragon rewrite | **New:** `POST /api/mobile/dragon/rewrite` — returns text; Apply is a note PATCH |
| What am I missing? | **Later** — advisory, not v0.1 |

Do not expose payments, estimates, customer CRM, or arbitrary RO mutation on this client.

**Voice lab** (`POST /api/voice/lab/utterance`) stays a **hardware R&D** hook, not ARK Tech auth.

---

## DVI data model (already ARK)

Authority: `Inspection` → `InspectionItem` (condition) → `InspectionItemMeasurement` + `InspectionItemPhoto`.

**Condition** (`InspectionObservedState`) — do not invent a second enum:

| Tech prompt | ARK value | Note |
|-------------|-----------|------|
| Good | `pass` | |
| Monitor | `monitor` | |
| Needs attention | `needs_attention` | **Not** automatically unsafe |
| Failed | `fail` | Separate; human-chosen |
| Not inspected | `not_checked` | |
| N/A | `na` | |

**Brake vertical slice (structured, not a paragraph):**

```
Brakes / Rear pads
  LR: { value: "3", unit: "mm", position: "LR" }
  RR: { value: "2", unit: "mm", position: "RR" }
  observed_state: needs_attention   // tech chose, not inferred from mm
Rear rotors
  condition note / observation: grooved
  photos[] → inspection_item_id = rear rotors or rear pads (explicit)
```

v0.1 measurement rows today: `name`, `value`, `unit`, `position`. Add when writing confirm path: `source` (`manual` \| `voice_confirmed`), `entered_by_user_id` if not implied by photo uploader + audit log.

Photos: `InspectionItemPhoto` already has `uploaded_by_user_id`, RO via inspection. Keep originals; thumbnails server-side. **Do not** send images to OpenAI unless an explicit later vision action.

---

## Photo pipeline

```
ARK Tech camera (full-res)
  → local pending queue (visible)
  → POST/PATCH item with photo (idempotency key)
  → InspectionItemPhoto on that item
  → RO + inspection + tech + time
```

No second “pick RO” after shutter. Retake/delete only while pending. Failed upload stays labeled pending — never silent success.

---

## Voice

```
PTT (on-screen required; hardware key optional VoiceTrigger)
  → WAV/M4A
  → ARK STT (OpenAI, shop key)
  → Hosted Dragon tools/schema: propose measurements + observations
     context: staff, RO, item, existing values
  → UI proposal (and optional TTS readback later)
  → Confirm
  → same InspectionItem write as manual
```

No write before confirm. Low confidence on numbers/laterality → ask again, don’t guess. Transcript may be kept; **raw audio default discard**.

Text and taps always work without this path.

---

## Dragon

| Adds | Never required for |
|------|---------------------|
| Speech → structured proposal | Saving mm, photos, conditions |
| Optional note rewrite (facts only) | Completing DVI |
| Later: “what am I missing?” | Camera, My Work |

Outage: hide Ask Dragon / Rewrite; DVI continues. No fabricated safety language.

---

## Hardware evaluation (paper — **do not buy**)

**First cert platform:** current shop Android tablets. Same Flutter layout must stay usable on ~5–6″ later (responsive, not tablet-only chrome).

| | **Samsung Galaxy XCover7 Pro** (or XCover7 class) | **Samsung Galaxy Tab Active5** | **Consumer Pixel / Galaxy A** |
|--|--|--|--|
| Camera / AF close-up | Strong candidate (phone optics) | Weaker than phone; tablet distance | Varies; fine for software cert |
| Flash / undercar | Phone torch | Often weaker | Varies |
| Gloves | Dedicated mode on XCover | S Pen / gloves mixed | Poor |
| Physical PTT | Programmable key | Usually none | Volume combo hack only |
| Battery / swap | Large; some replaceable | Dock-friendly | Sealed |
| Pogo / toolbox dock | Available in rugged line | DeX/pogo on Active | USB-C cable hell |
| Drop / dirty | IP + MIL | Rugged tablet | No |
| OS support | Samsung enterprise | Samsung enterprise | Short |
| Price | Mid rugged phone | Higher (tablet you may already own) | Cheap proto |
| Fit for Landon carry | **Best long-term handheld bet** | **Best current shop asset for workflow** | **Best for Flutter debug** |

**Zebra TC-class:** do not buy for prestige. Camera/UX often worse for pad photos than a Samsung phone.

**ESP walkie:** still valid for **stationary / cheap PTT accessory** after Android DVI is real. Not the DVI platform.

---

## Real hardware test

**Not run in this direction lock.** No APK was installed on a shop tablet in this pass. Next engineering slice (when started) installs on **existing Demo Auto Repair Android tablets**.

---

## Brake vertical slice (to prove — not built here)

1. Staff login  
2. My Work → assigned RO  
3. DVI → Rear brakes  
4. Manual LR 3 mm / RR 2 mm  
5. Two photos on that item  
6. Voice: “Left rear three, right rear two, rotors heavily grooved.”  
7. Proposal → confirm → `source=voice_confirmed`  
8. Rewrite rough note → apply/edit/cancel  
9. Progress %  
10. Kill OpenAI → steps 4–5 still save  

**Feel gate:** if photo+talk+confirm is not faster than web DVI, stop adding features.

---

## Offline / failure (v0.1 intent)

- Typed values + photos sit in a **pending** queue while the app is open  
- Retry, idempotent upload, no duplicate photos  
- Do not claim saved if the PATCH failed  
- Not a full offline replica of ARK  

---

## Tests

**Not added this pass** (no app slice). When built:

- ARK: staff auth, shop isolation, assigned RO, measurement save, photo relation, proposal ≠ write, audit source, rewrite doesn’t invent mm, Dragon down  
- Flutter: My Work, brakes LR/RR, pending upload, confirm, edit, network loss  

---

## ESP / voice terminal

See `docs/voice/handheld-v0.1.md` — **FUTURE R&D**. Do not PCB. Do not make it Landon’s DVI device.

---

## Final answers

| Question | Answer |
|----------|--------|
| Can ARK Tech function as a complete technician DVI tool without Dragon? | **YES** (by design). **Not shipped** yet. |
| Can Dragon turn technician speech into structured DVI proposals? | **NOT YET PROVEN** on a handheld. Pipeline designed; no silent writes. |
| Are measurements/photos tied to the correct RO and inspection item? | **YES** in existing ARK authority (`InspectionItem*`). Tech client must keep using that, not a new store. |
| Does the technician confirm critical values before write? | **Required by this lock.** Not implemented in ARK Tech UI yet. |
| Does Dragon outage leave core DVI functional? | **YES** (by design). Manual PATCH already exists on mobile inspection. |
| Is rugged Android more promising than custom ESP as the **primary technician platform**? | **YES** |
| Is custom hardware justified yet? | **NO** |
| Buy XCover / Zebra now? | **NO** — tablet workflow first |

**Do not start this as the next coding milestone until Shop Glass work is explicitly paused or this slice is scheduled. This document is the lock, not the implementation.**
