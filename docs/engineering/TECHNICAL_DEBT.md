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

## Staff UI

### Two staff navigation definitions

**Location:** `resources/views/components/operations/app.blade.php` (legacy rail) and `resources/views/components/operations/shell/tabler.blade.php` (Tabler sidebar). The account menus are duplicated the same way: `components/operations/user-menu.blade.php` and `components/operations/shell/tabler-user-menu.blade.php`.

**Problem:** A parallel path. Each shell lists the same destinations and permission checks in its own markup. `TablerShellTest` catches drift between them; it does not prevent it.

**Replacement:** One navigation definition (sections, items, permissions, active routes, live counts) rendered by whichever shell a view uses. See [TABLER.md](TABLER.md#navigation).

**Remove when:** Before a change adds, removes, or re-permissions a destination, or before the fifth view migrates, whichever comes first. The account menu follows the same rule.

---

## Tests

### The Core test suite is not green on `main`

**Location:** `tests/`, `tests/Support/HostedDragon.php`, `app/Ark/Runtime/Database/SchemaPresence.php`, `bin/pest`, `.github/workflows/tests.yml`

**Problem:** The suite has real failing tests. The harness problems that hid or multiplied them were fixed in the CI stabilization pull request; what remains is test debt.

- **Baseline failures.** `vendor/bin/pest` (serial, as CI runs it) on `main` `c55a949` with no sibling `../ark-saas`: 3990 tests, 174 failures, 34 errors. GitHub Actions reports the same set (209 failed on `c55a949`; one extra, `it re-resolves chrome when cached path is stale`, depends on the runner's binaries). These are assertion and behavior failures across about 100 files, not environment errors.
- **A sibling `../ark-saas` checkout changes results.** With it present, `bootHostedDragon()` loads ARK SaaS, so the Dragon-hosted tests run: 16 tests that fail without it pass, and before the fix 87 more failed through the schema cache below (283 failed in total).
- **The `pest` job.** It fails because of the baseline failures, not the environment. Its log also showed 3718 warnings, `file_get_contents(.../environment/.env): Failed to open stream`, on tests that passed; those are fixed (below) and never failed a test.

Fixed in the CI stabilization pull request, each with a regression test:

- **Missing `.env` warning.** `TestEnvironment` points the app at an empty `environment/` directory. Dotenv reads the missing `.env` with `@file_get_contents`, and PHPUnit 12 still reports suppressed warnings, so every test that booted the app warned. The isolated directory now holds an empty `.env`.
- **Dragon schema cache.** `SchemaPresence` keeps positive answers in a static. A Dragon test that creates `dragon_service_advisor_applications` left it cached for later tests in the same process, which then failed with `no such table`. `TestCase::setUp` now flushes it.
- **A local `.env` under `--parallel`.** Laravel's parallel runner boots an app in the parent process; without a `Tests\CreatesApplication` trait it requires `bootstrap/app.php` directly, loads the checkout's `.env` with `putenv`, and the workers inherit those values. `TestCase` now creates its app through `Tests\CreatesApplication`, so the parent uses the isolated environment too.
- **`bin/pest` without `--configuration`.** Its temporary configuration kept the relative `bootstrap="tests/bootstrap.php"`, which resolved inside the temp directory. Relative `bootstrap` and schema paths are now made absolute.

**Not verified after the fixes:** a full serial run with the fixes stopped producing output at the start of `RepairOrderEstimateWorkspaceTest`, after `RepairOrderEstimateVersionCoverageTest`, while using a full CPU core. The same point passed on `main`. Whether a fix causes it, or the run was disturbed, has not been determined. Confirm before relying on the fixed numbers.

**Replacement:** Confirm the full suite completes with the fixes, then fix the baseline failures by area in their own pull requests. Then make `pest` a required check on `main`, as `RELEASE_PROTOCOL.md` recommends.

**Remove when:** `php bin/pest --parallel` passes on `main` with no extra arguments, and the `pest` job in `tests.yml` passes.

---

## How to add entries

1. Name the **authority violation** or **parallel path**, not the symptom.
2. Point to **replacement** architecture.
3. State **remove when** with a measurable exit (milestone, observation, or ADR).

Do not use this file for roadmap ideas - only debt that exists in code today.
