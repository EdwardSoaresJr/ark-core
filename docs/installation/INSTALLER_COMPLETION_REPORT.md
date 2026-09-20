# First-run installer — completion report

**Tree:** `/Users/edwardsoares/Herd/ark-public-staging`  
**Date:** 2026-08-28  
**Remote:** none (unchanged)  
**Publication commit tip before work:** `1f61f7fa`  
**Working tree:** dirty with installer implementation (not committed — squash into next public milestone when ready)

---

## Git

| Item | Value |
| --- | --- |
| HEAD (last commit) | `1f61f7fa` — Initial open-source release of ARK |
| Branch | `main` |
| Remotes | none |
| Commits created this session | **0** (files left uncommitted for curated history) |
| Dirty | Installer + docs + tests + bootstrap wiring |

---

## Installer architecture

| Concern | Implementation |
| --- | --- |
| Installation-state authority | `App\Ark\Install\InstallationState` — file `storage/app/install/state.json` |
| States | `not_installed` · `in_progress` · `installed` |
| Pre-DB behavior | `UseInstallerRuntime` + `AppServiceProvider::register` force `session=file`, `cache=file`, `queue=sync` when not installed |
| Env persistence | `InstallerEnvironmentWriter` — **allowlist only** |
| Immutable env | Detects non-writable `.env`; skips write; requires platform-injected bootstrap |
| Install lock | `EnsureSetupAllowed` — GET → login; POST → 403 after installed |
| Concurrency | `InstallationMutex` flock on `storage/app/install/install.lock` |
| Retry | Checkpoints via state file; admin/workstation ensure-idempotent; APP_KEY preserved if valid |

---

## Wizard steps

| Step | Route | Purpose | Storage |
| --- | --- | --- | --- |
| Welcome | `GET /setup` | Intro | — |
| System | `GET /setup/system` | Requirements | runtime check only |
| Database | `GET/POST /setup/database*` | URL + MySQL test | draft JSON (no password) + session password |
| Shop | `GET/POST /setup/shop` | Identity | draft → later `ShopSettings` |
| Admin | `GET/POST /setup/admin` | First admin | draft + session password |
| Integrations | `GET/POST …/skip` | Optional skip | draft flag |
| Review / Install | `GET/POST /setup/review|install` | Finalize | migrate + auth seeder + admin + shop + optional workstation + lock |
| Complete | `GET /setup/complete` | Success | — |

---

## Database

- **Driver:** MySQL only for installer
- **Test:** PDO `SELECT 1` — no migrations
- **Non-empty:** fail closed (`empty` \| `ark` \| `suspicious`)
- **Migrate:** `artisan migrate --force` only after safety pass — never `migrate:fresh` / `db:wipe`

## Admin

- `User` model · Spatie `admin` role · `is_master_admin=true` · `Hash::make` · `ArkAuthorizationSeeder` first

## Shop

- `ShopSettings` fields: name, timezone, phone, email, address…
- Optional workstation **Main Shop** via `StoreWorkstationAction` if none exist
- **No `shop_id` · no Location model**

## Integrations (all optional)

| Integration | When absent |
| --- | --- |
| Dragon / OpenAI | Not configured — existing `DragonProviderUnavailable` |
| Square | Fake/unconfigured client paths already exist |
| Telephony / SMS | Optional |
| Mail | log mailer default |
| Labor guide | Not configured — no fuel bundled |

## Dragon

- No provider required to install
- No private memories seeded
- Keys never in browser responses

## Security

- Env allowlist · CSRF on POSTs · rate limits on test/install · secrets not in draft/logs responses · no arbitrary shell · no `?force=` unlock · mutex on final install

## Tests

`tests/Feature/Install/FirstRunInstallerTest.php`

| Result | Count |
| --- | --- |
| Passed | **8** |
| Failed | **0** |
| Assertions | 107 |

Covers: redirect when uninstalled, wizard OK, lock after install, env allowlist reject, env preserve unrelated keys, system check shape, `ark:install-status`, recover refuses INSTALLED.

---

## Certification

| Scenario | Result |
| --- | --- |
| Fresh traditional install (full MySQL wizard→migrate→login) | **NOT TESTED** (no disposable empty MySQL in this pass) |
| Setup redirect | **PASS** (automated) |
| DB test (unit path / fail messaging) | **PASS** (code + tester; live MySQL NOT TESTED) |
| Install finalize E2E | **NOT TESTED** |
| Setup lock | **PASS** |
| Login after install | **NOT TESTED** |
| Restart persistence | **NOT TESTED** |
| No Dragon / Square / telephony / mail / labor | **PASS** by design (skip path + existing graceful code) |
| Docker fresh install | **NOT SUPPORTED** (no compose first-run certified) |

---

## Open-source scan (installer surface)

| Check | Result |
| --- | --- |
| Secrets in install code/views/docs | None found |
| PII / Demo Auto Repair in install surface | None |
| Licensed fuel added | None |
| Unresolved publication blockers (tree-wide Demo Auto Repair narrative, license, Preline) | Still open from snapshot certification — **unchanged** |

---

## Final gate

| Question | Answer |
| --- | --- |
| Non-developer deploy without giant `.env`? | **PARTIAL** — wizard exists; full E2E MySQL cert pending |
| Setup before DB-dependent app? | **YES** |
| DB test without mutate? | **YES** |
| Refuse suspicious DB? | **YES** |
| APP_KEY generate/preserve? | **YES** |
| First real admin? | **YES** (code) |
| Existing settings authority? | **YES** |
| Min workstation only if wanted? | **YES** |
| Skip all integrations? | **YES** |
| Operate without Dragon/Square/telephony/labor? | **YES** (design) |
| Setup locks permanently? | **YES** |
| Safe retry / concurrency? | **YES** (designed + mutex) |
| Immutable env mode? | **YES** |
| No arbitrary `.env` / shell? | **YES** |
| No `shop_id` / Location / Windows? | **YES** |
| No Demo Auto Repair defaults / private memory / licensed fuel in installer? | **YES** |
| Staging still safe toward publication? | **PARTIAL** — installer OK; prior scrub/license gates remain |
| **Shop owner browser setup to clean ARK without Laravel internals?** | **PARTIAL** |

### Remaining blockers for FULL YES

1. End-to-end certify against a disposable empty MySQL (migrate → lock → login → restart)
2. Prior snapshot scrub (Demo Auto Repair narrative, license, Preline Fair Use)
3. Optional: Compose `docker compose` path
4. Squash installer into curated public commit before remote

**STOP.** No GitHub remote created.
