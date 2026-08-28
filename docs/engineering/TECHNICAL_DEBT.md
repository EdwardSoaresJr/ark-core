# Technical Debt

**Purpose:** What must eventually disappear.  
**Companion:** [IMPLEMENTATION_LOG.md](IMPLEMENTATION_LOG.md) records what changed; this file records what still violates target architecture.

**Rule:** Do not build on items listed here. New work routes through the replacement named in each entry.

---

## Persistent Surface Anti-Pattern

**Named pattern:** Capability shipped → surface persisted → better workspace absorbed the job → old surface not pruned.

**Problem:** A feature introduced a new page or nav tab. A later workspace absorbed the operational job. The original surface stayed discoverable — duplicate navigation, fragmented workflow, advisors asking "which page do I use?"

**Constitution:** [workspace-constitution-v1.md](../ecosystem/workspace-constitution-v1.md) — Law 5 (new surfaces must replace something), Law 6 (deletion is a feature).

**Audit inventory:** [workspace-surface-audit-v1.md](../operations/workspace-surface-audit-v1.md)

**Active retirements (Phase 1):** Comms Inbox/History/Workboard routes → Attention; ops rail admin links → Settings; legacy conversation reply page → Attention thread. **Next:** Observation Sprint — [floor-observations-july-2026.md](../operations/floor-observations-july-2026.md).

**Rule:** Before shipping a new workspace route, name what surface becomes simpler. If nothing — do not ship the page.

---

## ARK Voice / Endpoint Provisioning

### `CommunicationDeviceProvisionConfigBuilder`

**Location:** `app/Ark/Operations/Communications/CommunicationDeviceProvisionConfigBuilder.php`

**Problem:** Legacy provisioning path. Resolves extension identity via `assigned_user_id` → user-owned `TelephonyExtension`, or auto-allocates the next free extension in the 101–199 range. Violates workstation-owned business identity.

**Replacement:** `App/Ark/Communications/Provisioning/` — `PolyProvisionBuilder` + `EndpointConfigurationProjection` + `GET /provision/{mac}.cfg`.

**Remove when:** First Contact milestone proven; admin generate/download paths fully delegate to projection stack.

---

### `CommunicationDevice.assigned_user_id`

**Location:** `communication_devices.assigned_user_id`, shop UI, `CommunicationsShopProjection` (devices grouped by user)

**Problem:** Pre-workstation identity model. Business identity belongs on **workstation + telephony extension**, not on the device row.

**Replacement:** `workstation_id` + `AssignExtensionToWorkstationAction`.

**Remove when:** Floor observation confirms advisors operate workstations, not user-bound desk phones.

---

### Admin disk provision fallback

**Location:** `GenerateCommunicationDeviceConfigAction`, `CommunicationDevice::provisionConfigPath()`, `hasProvisionConfig()`

**Problem:** Writes `.cfg` to local disk for manual download. Parallel path to dynamic MAC-based serve.

**Replacement:** Phones pull from `GET /provision/{mac}.cfg`; admin UI shows provision URL + projection preview (PR3A).

**Remove when:** Zero-touch provisioning is default; no shop workflow requires USB/file transfer.

---

### Static PJSIP templates

**Location:** `infra/coolify/asterisk/config/templates/pjsip.conf` (extensions 101/102 hardcoded)

**Problem:** Asterisk holds credentials and endpoint rows outside ARK authority.

**Replacement:** Phase 3 — `ProjectTelephonyToAsteriskAction`, DB-backed secrets on `telephony_extensions.secret`.

**Remove when:** Dynamic PJSIP projection ships and floor-validated.

---

### Shop communications user-centric device grouping

**Location:** `CommunicationsShopProjection` — `devicesByUser` keyed on `assigned_user_id`

**Problem:** Coverage and device rows still assume user-bound phones.

**Replacement:** Workstation-centric device list (already partially wired on index).

**Remove when:** PR3B assignment UX complete and `assigned_user_id` retired.

---

## Migration history

### Renamed workstation FK migration

**Was:** `2026_06_26_140001_add_workstation_fields_to_communication_devices.php`  
**Now:** `2026_06_30_105000_add_workstation_fields_to_communication_devices.php`

**Why:** Original timestamp ran **before** `2026_06_30_100000_create_communication_devices_table.php`, causing fresh installs to fail on FK to a non-existent table. Laravel migration order is filename order — history must not look accidental.

**Production note:** If `2026_06_26_140001` already ran on a host, do not re-run the renamed migration; reconcile manually or mark migrated.

**Documented in:** [ark-voice-endpoint-architecture-v1.md](../communications/ark-voice-endpoint-architecture-v1.md) § Migration ordering.

---

## Infrastructure / Deployment

### Container discovery via `grep b38ot`

**Location:** `infra/coolify/DEPLOYMENT.md`, deploy runbook SSH examples

**Problem:** `docker exec $(docker ps … | grep b38ot …)` is convenient but fragile — wrong container if topology or naming changes.

**Replacement:** Explicit Coolify app container name (or label) in shop runtime profile and deploy docs.

**Remove when:** Every migrate/artisan one-liner names the target container deterministically.

---

## How to add entries

1. Name the **authority violation** or **parallel path**, not the symptom.
2. Point to **replacement** architecture.
3. State **remove when** with a measurable exit (milestone, observation, or ADR).

Do not use this file for roadmap ideas — only debt that exists in code today.
