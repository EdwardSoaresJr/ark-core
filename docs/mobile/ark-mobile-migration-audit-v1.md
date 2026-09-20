# ARK Mobile Migration Audit v1

**Date:** June 2026  
**Purpose:** Harvest operational value from legacy shop mobile before building more ARK Mobile screens.

## Executive summary

The rebuild did **not** evolve the original app. It built a **new Flutter client** (`ark-mobile`) against a **new API projection** (`/api/mobile/*` on ARK V2). That was intentional per `ark-mobile-projection-v1.md`:

> Legacy `arksms_shop` is reference only — not port wholesale.

**Risk:** Years of floor-tested workflows in `arksms_shop` are not automatically carried forward. The new app is architecturally cleaner but functionally narrower.

| App | Path | Backend | Status |
|-----|------|---------|--------|
| **Original** | `legacy private shop app path (not redistributed)` | ARK v1 tenant API (`/api/tenant/v1/mobile/*`, `/api/mobile/*`) | Production usage, 79 Dart files |
| **New** | `private ark-mobile sibling (not redistributed)` | ARK V2 (`https://app.demo-auto.test/api/mobile/*`) | Phase 1 + v1.1/v1.2 UX, 32 Dart files |

**Recommendation:** Pause feature expansion until Phase 2 harvest plan is agreed. Do **not** retire `arksms_shop` until VIN/intake/advisor parity is defined and shipped on V2 projections.

---

## 1. Original app inventory (`arksms_shop`)

### Shell & navigation

| Feature | Location | Notes |
|---------|----------|-------|
| Multi-tab shop shell | `shop_shell_screen.dart` | Home · Intake · Jobs · Activity · More |
| Role-based nav | `tenant_roles.dart` | Tech floor-only vs advisor/admin surfaces |
| Push deep links | `push_routing.dart`, `push_service.dart` | FCM → Intake tab, Activity, RO |
| Session / domain auth | `auth_service.dart`, `secure_storage.dart` | Tenant domain + token |
| Idle marketing overlay | `idle_marketing_overlay.dart` | Advisor kiosk-style |

### Home / command center

| Feature | Location | Notes |
|---------|----------|-------|
| Shop home hero | `shop_home_screen.dart` | Pending uploads, new intakes, jobs open count |
| Advisor command center | `advisor_ops_screen.dart` | Attention queue, scoreboard, money leaks |
| Follow-up queue | `advisor_hub_service.dart` | Server-ranked advisor actions |
| Shop playbook | `advisor_hub_service.dart` | Smart prompts, proof tasks |
| VIN decode tool (manual) | `advisor_ops_screen.dart` | NHTSA decode via API |

### Intake (highest-value mobile surface)

| Feature | Location | Notes |
|---------|----------|-------|
| **Mobile intake workflow** | `intake_screen.dart` (~3.6k lines) | Full check-in / vehicle capture |
| **VIN barcode scan** | `vin_scan_screen.dart` | `mobile_scanner`, Code39/128 |
| **VIN decode** | `intake_service.dart` | `POST …/mobile/tools/vin-decode` |
| Plate entry | `intake_screen.dart` | With RO prefill lock |
| Customer prefill | `intake_screen.dart` | From RO route args |
| Concern-scoped photos | `concern_attach_sheet.dart`, `intake_concern_store.dart` | Photos per concern |
| OBD vehicle scan | `obd_*`, `intake_scan_progress.dart` | BLE dongle, DTCs, attach to RO |
| Photo offline queue | `photo_queue_store.dart`, `photo_queue_processor.dart` | Retry when connectivity returns |
| Intake submit | `intake_service.dart` | Idempotent POST mobile intakes |
| Batch photo upload | `intake_service.dart` | Multipart to tenant API |
| Diagnostic guidance | `diagnostic_guidance.dart` | In-intake hints |
| Canned responses | `canned_responses_sheet.dart` | Advisor text snippets |
| Photo proof tags | `photo_proof_tag_sheet.dart` | Tag photos for proof |

### Jobs / technician work

| Feature | Location | Notes |
|---------|----------|-------|
| Jobs list | `jobs_screen.dart` | Shop-wide or tech-filtered |
| Job detail | `job_detail_screen.dart` | Notes, **status updates** |
| Open intake from job | `job_detail_screen.dart` | Prefill VIN/plate/customer |
| Customer timeline | `customer_timeline_sheet.dart` | From job detail |
| Vehicle repair history | `vehicle_repair_history_sheet.dart` | From job detail |
| Tech simple list mode | `jobs_screen.dart` | Floor technician density |

### Repair order surfaces

| Feature | Location | Notes |
|---------|----------|-------|
| RO hub | `repair_order_screen.dart` | Scan, inspection entry, diagnostics |
| OBD scan attach to RO | `repair_order_api_service.dart` | Server-side concern linking |
| OBD suggestion → labor | `repair_order_screen.dart` | Convert diagnostic to line |
| Digital inspection | `digital_inspection_screen.dart` | **Placeholder** — nav only |
| RO concern picker | `ro_concern_picker_sheet.dart` | |

### Advisor / revenue ops

| Feature | Location | Notes |
|---------|----------|-------|
| Approval sheet | `approval_sheet.dart` | Estimate approval actions |
| RO quick actions | `advisor_ro_quick_actions_sheet.dart` | |
| Money leaks | `advisor_hub_service.dart` | |
| DTC guidance API | `advisor_hub_service.dart` | |
| Time clock in/out | `advisor_hub_service.dart`, `shop_more_screen.dart` | Location-aware |

### Activity & notifications

| Feature | Location | Notes |
|---------|----------|-------|
| Activity feed | `activity_screen.dart`, `activity_service.dart` | Shop event stream |
| FCM push | `firebase_messaging`, `device_token_service.dart` | Real-time, not poll |
| Activity badges | `shop_shell_screen.dart` | New mobile intake count |

### Integrations & device

| Feature | Notes |
|---------|-------|
| BLE OBD (flutter_blue_plus) | vGate, ELM327 dongles |
| Camera / image picker | Intake + proof photos |
| Connectivity awareness | `queue_connectivity.dart` |
| Upload deduplication | `upload_dedup_store.dart` |

---

## 2. New app inventory (`ark-mobile`)

### Implemented (March–June 2026)

| Feature | Screen / layer | Backend |
|---------|----------------|---------|
| Sanctum login | `login_screen.dart` | `POST /auth/login` |
| Permission shell | `mobile_shell.dart`, `/me` | Capabilities from Spatie |
| My Work cards | `my_work_screen.dart` | `GET /work` |
| Customer-first hierarchy | My Work + RO detail | Projection |
| Status colors (desktop tones) | `repair_order_status_colors.dart` | `status_tone` |
| RO detail workspace | `repair_order_detail_screen.dart` | `GET /repair-orders/{id}` |
| Concern cards + counts | RO detail | `findings_count`, `photo_count`, … |
| **Concern detail** | `concern_detail_screen.dart` | `GET …/concerns/{id}` |
| **Finding detail** | `finding_detail_screen.dart` | `GET …/findings/{id}` |
| Finding capture + photo | `finding_capture_screen.dart` | `POST …/findings` |
| Concern-linked findings | `FindingDraft.concernId` | `repair_order_concern_id` |
| Comms thread **list** | `communications_screen.dart` | `GET /communications` |
| Notifications poll | `notifications_screen.dart` | `GET /notifications` (45s) |
| Device registration | `platform_info.dart` | `POST /device` |
| Web dev login prefill | `app_config.dart` | Debug only |

### API implemented but UI missing

| API | Gap |
|-----|-----|
| `GET /communications/{id}` | No thread detail screen |
| `POST …/messages` | No reply composer |
| `POST …/internal-notes` | No internal note UI |

### Not started

Push (FCM), offline queue, VIN, intake, OBD, advisor hub, status changes, time clock, activity feed, vehicle/customer lookup, parts, approvals.

---

## 3. Gap analysis

| Feature | Old (`arksms_shop`) | New (`ark-mobile`) | Status | Recommendation |
|---------|---------------------|--------------------|--------|----------------|
| **VIN barcode scan** | Yes (`mobile_scanner`) | No | **Missing** | **Required before old app retirement** — highest ROI phone feature |
| **VIN decode (NHTSA)** | Yes | No | **Missing** | **Required before production rollout** — port as V2 mobile tool projection |
| **License plate capture** | Yes (manual field) | No | **Missing** | **Required before rollout** — no scan yet in old app either; add lookup on V2 |
| **Customer / vehicle check-in** | Yes (intake tab) | No | **Missing** | **Required Phase 2** — must map to ARK V2 Intake authority, not v1 intake API |
| **Vehicle lookup** | Via decode + RO prefill | No | **Missing** | **Required Phase 2** |
| **Concern-scoped photo intake** | Yes | Partial (findings only) | **Partial** | Extend finding capture; harvest concern attach UX |
| **Offline photo queue** | Yes | No | **Missing** | **Required before rollout** for greasy-floor reliability |
| **OBD / DTC scan** | Yes (BLE) | No | **Missing** | **Nice to have** until V2 backend owns OBD attach; high value for Demo Auto Repair |
| **My Work / assigned ROs** | Yes (Jobs API) | Yes | **Parity** | New app better hierarchy (v1.1) |
| **RO detail** | Yes (job detail + RO screen) | Yes | **Parity+** | New concern/finding drill-down stronger |
| **Findings + photos** | Via intake + inspection placeholder | Yes | **Partial** | New app is finding-first (correct doctrine) |
| **Digital inspection checklist** | Placeholder only | No | **Neither** | **Retire checklist UI** — finding-first is correct for V2 |
| **Inspection → finding vocabulary** | Mixed | Yes | **New wins** | Keep ARK V2 approach |
| **Status updates on RO** | Yes (job detail) | No | **Missing** | **Required Phase 2** for techs (production status / concern scope) |
| **Job notes** | Yes | No | **Missing** | **Required Phase 2** — internal notes on RO/conversation |
| **Comms / SMS** | Planned in old app | List only | **Partial** | **Required Phase 2** — thread + reply (API exists) |
| **Push notifications** | Yes (FCM) | Poll + Attention | **By design for now** | **Deferred** — poll until observation proves need; see notification doctrine |
| **Advisor command center** | Yes | No | **Missing** | **Phase 3** — project from V2 Attention, not v1 hub |
| **Approvals / estimate review** | Yes (approval sheet) | No | **Missing** | **Phase 3** advisor |
| **Money leaks / playbook** | Yes | No | **Missing** | **Retire / replace** with V2 Attention + Owner digest |
| **Activity feed** | Yes | Alerts poll only | **Partial** | **Phase 3** — map to Attention queue projection |
| **Time clock** | Yes | No | **Missing** | **Nice to have** — only if shop still uses mobile clock |
| **Multi-tenant domain login** | Yes | No (single shop) | **By design** | **Retire** for Demo Auto Repair single-tenant V2 |
| **Customer timeline** | Yes | No | **Missing** | **Phase 3** — Customer Hub projection |
| **Vehicle repair history** | Yes | No | **Missing** | **Phase 2** tech scope (assigned RO only) |
| **Diagnostic → labor convert** | Yes | No | **Missing** | **Phase 3** — needs V2 OBD/diagnostic authority |
| **Role-based nav** | Rich | Capabilities from API | **Partial** | Expand as V2 roles mature |
| **Auth** | Domain + token | Sanctum | **Replaced** | Keep V2 |

---

## 4. Mobile product map (target state)

### Technician (Phase 1 ✅ → Phase 2)

| Workflow | Old | New target |
|----------|-----|------------|
| See assigned work | Jobs tab | **Today** (rename later) ✅ |
| Open RO context | Job detail | RO detail ✅ |
| Record findings | Intake photos | Finding capture ✅ |
| Concern drill-down | Partial | Concern detail ✅ |
| VIN at vehicle | Intake scan | **Port** — camera → V2 vehicle identity |
| Status / production | Job detail | **Build** — concern production status |
| Comms on assigned RO | — | **Build** — thread detail + reply |
| OBD | Full BLE | **Later** — after V2 attach API |

### Advisor (Phase 3+)

| Workflow | Old | New target |
|----------|-----|------------|
| Customer check-in | Intake tab | V2 **Advisor Intake** mobile projection |
| VIN scan + decode | Intake + hub tool | Shared mobile tool service on V2 |
| Plate / vehicle lookup | Intake | Customer/Vehicle search API |
| Quick RO creation | Via intake submit | V2 intake create RO |
| Comms + reply | Partial | Full thread (API ready) |
| Estimate approval | Approval sheet | Portal/RO approval projection |
| Attention / needs action | Command center | V2 **Attention** mobile slice — not v1 hub |

### Owner / manager (Phase 4)

| Workflow | Old | New target |
|----------|-----|------------|
| Today board | Home + advisor ops | V2 Operational Report mobile |
| Needs action | Command center | Attention queue mobile |
| Pipeline snapshot | Money leaks | Shop pressure projection |
| Notifications | Activity + FCM | Push + Attention |

---

## 5. Migration plan (not a feature list)

### Principle

```
Harvest workflows → V2 authority → Mobile projection → Flutter UI
```

Never port v1 API shapes wholesale. For each old feature, ask: **which V2 authority owns this now?**

| Old v1 concept | V2 authority |
|----------------|--------------|
| Mobile intake POST | `AdvisorIntakeService`, `RepairOrder`, concerns |
| VIN decode | Vehicle identity + NHTSA enrich (existing web path) |
| Jobs list | `RepairOrder` + assignment filter |
| Findings / photos | `InspectionItem` ✅ already wired |
| Comms | `Conversation` ✅ already wired |
| Advisor command center | Attention + Today projections |
| OBD attach | Needs explicit V2 authority decision |

### Phase 2 — Harvest (next sprint freeze until agreed)

**Goal:** Advisors can check in a vehicle on the lot; techs can complete field capture without desktop.

1. **Mobile tools API (V2)**
   - `POST /api/mobile/tools/vin-decode` — wrap existing NHTSA/vehicle enrich
   - `GET /api/mobile/vehicles/lookup?vin=&plate=` — read-only search projection

2. **Flutter: VIN scan + decode module**
   - Port `vin_scan_screen.dart` + decode UX (not intake monolith)
   - Reuse `mobile_scanner` in `ark-mobile`

3. **Flutter: Advisor check-in flow**
   - New screen chain: Scan/enter VIN → decode confirm → customer match → concern → create/open RO
   - Backend: mobile intake projection on V2 Intake (not v1 `mobile/intakes`)

4. **Flutter: Comms thread detail + reply**
   - API already exists; highest advisor ROI after VIN

5. **Flutter: Offline photo queue**
   - Port `photo_queue_store` pattern for finding capture

6. **Backend: Production status mobile write**
   - Scoped POST for concern `production_status` (tech assigned RO only)

### Phase 3 — Advisor parity

- Attention mobile slice (not v1 command center)
- Approval / estimate review read-only mobile
- Customer timeline on RO (Conversation + events projection)
- Push transport (APNs/FCM) after observation — not a rollout blocker; poll Attention/notifications until then

### Phase 4 — Owner + retire old app

- Today / bookend mobile pulse
- Retire `arksms_shop` when Phase 2 + 3 acceptance tests pass on floor
- Keep old repo archived; do not dual-maintain features

### Do not migrate (retire / obsolete)

- v1 multi-tenant domain login UI
- v1 money leaks / playbook (replace with V2 Attention)
- Digital inspection **checklist** UI (finding-first replaces it)
- v1 advisor command center layout (revenue theater — rebuild from V2 Attention)

---

## 6. Acceptance tests before retiring `arksms_shop`

**Advisor walk-around**

1. Scan VIN on truck in lot → decode → match/create customer → open RO with concern  
2. Send customer SMS from mobile  
3. See item on Attention-equivalent surface  

**Technician bay**

1. Open assigned RO from Today  
2. Tap concern → add finding with photo → see on RO  
3. Update scope production status  
4. Works with brief offline → photo uploads when back online  

**Owner spot check**

1. Open mobile → see shop pressure counts (later phase)  

Until these pass on **ark-mobile + ARK V2**, keep **`arksms_shop`** installed for staff who depend on VIN/intake.

---

## 7. Answer to “did the rebuild start a new app?”

**Yes.** And that was documented doctrine — not an accident.

What the rebuild did well: V2-aligned projections, finding-first inspection UX, technician scope, Sanctum, test coverage.

What the rebuild skipped: **harvest inventory** from `arksms_shop` before sprinting on RO/findings polish.

**Next move:** Agree Phase 2 harvest (VIN + check-in + comms thread + offline queue). Do not add net-new surfaces until VIN scan exists — it is the highest ROI bridge between old and new.

---

## References

- Legacy app: `legacy private shop app path (not redistributed)`
- New app: `private ark-mobile sibling (not redistributed)`
- V2 doctrine: `docs/mobile/ark-mobile-projection-v1.md`
- Comms transport lock: `docs/mobile/ark-mobile-communications-authority-contract.md`
- V2 mobile API: `app/Ark/Mobile/`, `routes/api.php`
- Workspace contract: `docs/workspace-strip-contract.md` (marks `arksms_shop` out of scope for v1 web)
