# Technical Debt

**Purpose:** What must eventually disappear.  
**Purpose (continued):** This file records what still violates the target architecture. Git records what shipped.

**Rule:** Do not build on items listed here. New work routes through the replacement named in each entry.

---

## Persistent Surface Anti-Pattern

**Named pattern:** Capability shipped → surface persisted → better workspace absorbed the job → old surface not pruned.

**Problem:** A feature introduced a new page or nav tab. A later workspace absorbed the operational job. The original surface stayed discoverable - duplicate navigation, fragmented workflow, advisors asking "which page do I use?"

**Constitution:** [workspace-constitution-v1.md](../ecosystem/workspace-constitution-v1.md) - Law 5 (new surfaces must replace something), Law 6 (deletion is a feature).

**Audit inventory:** [workspace-surface-audit-v1.md](../operations/workspace-surface-audit-v1.md)

**Active retirements (Phase 1):** Comms Inbox/History/Workboard routes → Attention; ops rail admin links → Settings; legacy conversation reply page → Attention thread.

**Rule:** Before shipping a new workspace route, name what surface becomes simpler. If nothing - do not ship the page.

---

## ARK Voice / Endpoint Provisioning

### `CommunicationDeviceProvisionConfigBuilder`

**Location:** `app/Ark/Operations/Communications/CommunicationDeviceProvisionConfigBuilder.php`

**Problem:** Legacy provisioning path. Resolves extension identity via `assigned_user_id` → user-owned `TelephonyExtension`, or auto-allocates the next free extension in the 101–199 range. Violates workstation-owned business identity.

**Replacement:** `App/Ark/Communications/Provisioning/` - `PolyProvisionBuilder` + `EndpointConfigurationProjection` + `GET /provision/{mac}.cfg`.

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

**Replacement:** Phase 3 - `ProjectTelephonyToAsteriskAction`, DB-backed secrets on `telephony_extensions.secret`.

**Remove when:** Dynamic PJSIP projection ships and floor-validated.

---

### Shop communications user-centric device grouping

**Location:** `CommunicationsShopProjection` - `devicesByUser` keyed on `assigned_user_id`

**Problem:** Coverage and device rows still assume user-bound phones.

**Replacement:** Workstation-centric device list (already partially wired on index).

**Remove when:** PR3B assignment UX complete and `assigned_user_id` retired.

---

## Migration history

### Renamed workstation FK migration

**Was:** `2026_06_26_140001_add_workstation_fields_to_communication_devices.php`  
**Now:** `2026_06_30_105000_add_workstation_fields_to_communication_devices.php`

**Why:** Original timestamp ran **before** `2026_06_30_100000_create_communication_devices_table.php`, causing fresh installs to fail on FK to a non-existent table. Laravel migration order is filename order - history must not look accidental.

**Production note:** If `2026_06_26_140001` already ran on a host, do not re-run the renamed migration; reconcile manually or mark migrated.

**Documented in:** [ark-voice-endpoint-architecture-v1.md](../communications/ark-voice-endpoint-architecture-v1.md) § Migration ordering.

---

## Infrastructure / Deployment

### Container discovery via `grep b38ot`

**Location:** `infra/coolify/DEPLOYMENT.md`, deploy runbook SSH examples

**Problem:** `docker exec $(docker ps … | grep b38ot …)` is convenient but fragile - wrong container if topology or naming changes.

**Replacement:** Explicit Coolify app container name (or label) in shop runtime profile and deploy docs.

**Remove when:** Every migrate/artisan one-liner names the target container deterministically.

---

## Operations

### Two rules for who owns in-progress work

**Location:** `WorkboardLens::filterRepairOrders()` and `technicianOwnsWorkOn()` in `app/Ark/Operations/Workboard/WorkboardLens.php`; `TodayOperationalComposer::forTechnician()` and `technicianActions()` in `app/Ark/Operations/Today/Surface/TodayOperationalComposer.php`

**Problem:** Two parallel ownership checks disagree. The workboard filter keeps an In Progress repair order for a technician only when the technician owns one of its concern work groups. The Today composer runs that filter first, then treats `repair_orders.assigned_technician_id` as the owner. A repair order that is In Progress and assigned to the technician through `assigned_technician_id`, with no work group they own, is dropped before the composer sees it. So the technician's "Assigned work" lane on Today stays empty for it. `TodaySurfaceTest` › "technician lens still surfaces assigned work lanes" fails on `main` (`5342586`) for this reason. Recorded in [PR #105](https://github.com/EdwardSoaresJr/ark-core/pull/105).

**Replacement:** One ownership rule for technician work, used by the workboard filter and the Today composer alike.

**Remove when:** An In Progress repair order a technician owns appears in their "Assigned work" lane, and that test passes.

---

### Customer decision schedule redirects to the Job Board

**Location:** `app/Ark/Operations/Work/CustomerDecisionScheduleStoreController.php` and `CustomerDecisionScheduleClearController.php`

**Problem:** Both controllers redirect to `operations.index` (the Job Board at `/app`) with their flash, whatever page submitted the form. Schedule and Clear live on the Customer Decisions work queue (`operations.work.queue`, decisions), so saving or clearing a schedule takes the advisor off that queue. Recorded in [PR #105](https://github.com/EdwardSoaresJr/ark-core/pull/105).

**Replacement:** Return to the page that submitted the form, falling back to the Customer Decisions queue.

**Remove when:** Saving or clearing a schedule from the Customer Decisions queue lands back on that queue with the flash shown.

---

### Adding a staff member can fail after the user is created

**Location:**
- `StaffMemberController::store()` in `app/Ark/Operations/Staff/StaffMemberController.php`, lines 44–65.
- `StaffInvitationIssuer::send()` in `app/Ark/Operations/Staff/StaffInvitationIssuer.php`, lines 27–28.

**Problem:** `store()` creates the user, syncs roles, and records compensation history before it sends the invitation. `send()` throws `RuntimeException("Email isn't configured yet.")` unless `ManagedMailGate::platformSend()` is true, which needs an ARK Platform connection. Nothing wraps the create and the send in a transaction or catches the exception, and `bootstrap/app.php` does not render it. A standalone shop with no Platform connection therefore gets a server error, and is left with an active user who has roles and a placeholder password but no setup link. `StaffInvitationTest` covers only the connected path. Recorded in [PR #107](https://github.com/EdwardSoaresJr/ark-core/pull/107).

**Expected:** Adding a staff member on a standalone shop either completes with a way for that person to get a password (for example, the command-line recovery `PRODUCT_BOUNDARY.md` requires), or fails with a validation message and creates nothing.

**Remove when:** A test adds a staff member with no Platform connection. The response is not a server error, and the shop is left either with no new user or with a user it can finish setting up without ARK Platform.

---

### Inspection checklist checkboxes cannot be turned off

**Location:**
- The form, `resources/views/operations/settings/partials/inspection-template-settings.blade.php`: template Enabled at line 21, item Enabled at line 51, Photo required at line 55.
- `InspectionTemplateSettingsUpdater` in `app/Ark/Operations/Inspections/InspectionTemplateSettingsUpdater.php`, lines 19 and 51–56.

**Problem:** The checkboxes send no hidden `0`, so an unchecked box sends nothing. The updater changes `enabled` and `requires_photo` only when the key is present, and otherwise keeps the stored value. Checking a box saves `true`; unchecking it leaves it `true`. Seen in the browser on [PR #107](https://github.com/EdwardSoaresJr/ark-core/pull/107), where unchecking Photo required and saving brought it back checked. The field names and behavior are the same as before that PR.

**Expected:** Unchecking a template's Enabled, an item's Enabled, or an item's Photo required and saving stores `false`.

**Remove when:** A test saves the inspection checklist form with each of those three boxes unchecked, and the stored template and item show `enabled = false` and `requires_photo = false`.

---

### A new custom status cannot start on the technician board

**Location:**
- `resources/views/operations/settings/partials/ro-status-catalog.blade.php`, line 198 (Add custom status, Technician board).
- `RepairOrderStatusCatalogUpdater` in `app/Ark/Operations/RepairOrders/Status/RepairOrderStatusCatalogUpdater.php`, line 113.

**Problem:** The checkbox submits `value="0"`, the same value as the hidden input before it. The updater stores `filter_var('0', FILTER_VALIDATE_BOOLEAN)`, which is `false`, so a new status always saves with `show_on_technician_board = false`, whether the box is checked or not. The status can be moved onto the technician board afterward, using the existing status's own checkbox. Recorded in [PR #107](https://github.com/EdwardSoaresJr/ark-core/pull/107).

**Expected:** Checking Technician board when adding a custom status creates it with `show_on_technician_board = true`.

**Remove when:** A test adds a custom status with Technician board checked and finds `show_on_technician_board = true` on the new row, and another test with it unchecked finds `false`.

---

## Tests

### The Core test suite is not green on `main`

**Location:** `tests/`, `tests/Support/HostedDragon.php`, `app/Ark/Runtime/Database/SchemaPresence.php`, `bin/pest`, `.github/workflows/tests.yml`

**Problem:** Four separate issues, each verified in [PR #48](https://github.com/EdwardSoaresJr/ark-core/pull/48) and its [pre-merge comment](https://github.com/EdwardSoaresJr/ark-core/pull/48#issuecomment-6537280134):

- **Baseline failures.** `php bin/pest --configuration phpunit.xml --parallel` on `main` `06aa91e`: 3961 tests, 172 failed, 30 errors.
- **Dragon test isolation.** When a sibling `../ark-saas` checkout exists, `bootHostedDragon()` registers an `Ark\Saas` autoloader and `migrateHostedDragon()` creates the Dragon tables. `SchemaPresence` caches `dragon_service_advisor_applications` as present in a static that is not flushed between tests. A later test in the same process that renders `repair-order-concern-narrative-card.blade.php` fails with `no such table: dragon_service_advisor_applications`. Reproduced without parallelism on `main` `06aa91e` by running `RepairOrderBuilderWorkspaceModalTest` before `WorksheetContinuityAuthorizationTest` and `VisitReasonConcernProposeTest`. Under `--parallel`, which tests fail depends on worker assignment.
- **Order contamination seen during the Tabler repair order work.** Running `VisitReasonConcernProposeTest`, `VisitReasonPhase0Test`, `CustomerVehicleRepairOrderDraftTest`, `RecommendationWorkspaceTest`, `WorksheetContinuityLinesTest`, `RepairOrderBuilderWorkspaceModalTest`, `RepairOrderConcurrencyTest`, `RepairOrderFinancialSidebarLayoutTest`, and `RepairOrderEstimateInstrumentProjectionTest` together on `main` `8f1b6e9` fails 11 tests. Seven of them (the four `RepairOrderFinancialSidebarLayoutTest` tests and three in `RepairOrderConcurrencyTest`) return HTTP 500 only after `RepairOrderBuilderWorkspaceModalTest` has run; each file passes alone. This matches the Dragon isolation issue above. Separately, `RepairOrderBuilderWorkspaceModalTest` › `storing a line returns Saved flash and the created line id on the worksheet` fails on its own on `main`. Until this is fixed, compare targeted runs against `main` with the same files in the same order.
- **GitHub Actions `pest`.** The `pest` job in `tests.yml` fails on `main` (`06aa91e`, `c8c1824`). Most tests report `file_get_contents(/tmp/ark-core-tests-<run>/environment/.env): Failed to open stream: No such file or directory`; 63 pass. The cause has not been investigated. The job is not a required check.
- **`bin/pest` configuration.** `bin/pest` writes a temporary configuration that rewrites `bootstrap="vendor/autoload.php"`, but `phpunit.xml` bootstraps `tests/bootstrap.php`. Without `--configuration phpunit.xml`, `php bin/pest` stops with `Cannot open bootstrap script`.

A local `.env` in the checkout also changes results: with one present, 13 more provisioning tests failed under `--parallel` in PR #48. Compare runs without a local `.env`.

**Replacement:** Fix each issue in its own pull request. Then make `pest` a required check on `main`, as `RELEASE_PROTOCOL.md` recommends.

**Remove when:** `php bin/pest --parallel` passes on `main` with no extra arguments, and the `pest` job in `tests.yml` passes.

---

## How to add entries

1. Name the **authority violation** or **parallel path**, not the symptom.
2. Point to **replacement** architecture.
3. State **remove when** with a measurable exit (milestone, observation, or ADR).

Do not use this file for roadmap ideas - only debt that exists in code today.
