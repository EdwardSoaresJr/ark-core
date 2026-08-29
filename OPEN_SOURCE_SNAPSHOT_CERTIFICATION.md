# ARK Public Snapshot Certification

**Tree:** `/Users/edwardsoares/Herd/ark-public-staging`  
**Commit under test:** `1f61f7fa` — *Initial open-source release of ARK*  
**Author:** Edward Soares Jr. `<10336569+EdwardSoaresJr@users.noreply.github.com>`  
**Date:** 2026-08-28  
**Remote:** none  
**Commits on `main`:** 1  

**Scope:** Final inspection of the **public staging snapshot** (not the private foundry audit).  
**Non-actions:** no GitHub repo · no LICENSE · no Dragon boundary lock · no amend of `1f61f7fa` in this pass

---

## Executive verdict

# **NOT READY TO CREATE THE GITHUB REMOTE**

Hard publication blockers from the foundry (RTE CSVs, Dragon dump, owner PII, Coolify secrets) are **absent** from this snapshot. That is real progress.

Remaining before remote:

1. Scrub or accept **~176 tracked files** still mentioning Demo Auto Repair (mostly doctrine/docs/narrative)
2. Legal review of **Preline Fair Use**, **OSL-3.0** (Square stack), **LGPL** (pdfparser)
3. Decide **license** and **Dragon boundary**
4. Add public scaffolding (SECURITY, CONTRIBUTING, …) then **amend/squash into** the intentional first commit (or a deliberate second docs commit)
5. Full test suite + stranger MySQL boot certification (currently **partial**)

---

## Gate checklist

| Gate | Result |
| --- | --- |
| Single intentional commit, noreply author, no remote | **PASS** |
| No live secrets in git history | **PASS** (gitleaks: 3 hits, all test fixtures / false positives) |
| No `.env` / backup credentials / OIDC PEMs tracked | **PASS** |
| RTE labor CSV/SQL fuel absent | **PASS** |
| Dragon arkai import dump absent | **PASS** |
| Hard owner/employee PII patterns absent (`esoares9483`, Chelton, `7196414984`, Landon age memory) | **PASS** |
| Forbidden production IPs / Twilio live SID / Coolify app id | **PASS** |
| Demo Auto Repair narrative scrubbed from public tree | **FAIL** (~176 files still reference) |
| `rte/rte_job_menu.txt` redistribution rights clear | **UNKNOWN** (menu text from labor-guide export — review) |
| Dependency licenses cleared for chosen ARK license | **UNKNOWN** (flags remain) |
| ARK LICENSE decided | **NO** |
| Dragon public/private boundary decided | **NO** (provisional: runtime open / fuel private) |
| Full test suite green from this tree | **PARTIAL** |
| Ready to create public GitHub remote | **NO** |

---

## 1. Git state

| Check | Result |
| --- | --- |
| Branch | `main` |
| Status | clean working tree at certification start (cert file may appear untracked) |
| History | **1** commit |
| Remotes | **none** |
| Author | GitHub noreply (verified-style, mailbox private) |

### Ignored (present on disk, not in git) — expected

- `vendor/` (composer install local)
- `database/testing.sqlite`
- No `.env` at root (removed after keygen)

### Tracked names containing “credentials/secret”

Code/migrations/tests only (PartsTech, ShopIntegrationCredentials, etc.) — **not** secret blobs. OK.

---

## 2. Secret scan (gitleaks on staging history)

**1 commit scanned · 3 findings · 0 confirmed live**

| Finding | Classification |
| --- | --- |
| Growth / Mobile push test `BEGIN PRIVATE KEY` fixtures | **Test-only / nonfunctional** |
| `jeep-wrangler-2014` in VehicleVinWorkflowTest | **False positive** |
| Dockerfile `APP_KEY=base64:AAAA…AAAA=` | **Placeholder** (build-time dummy) |

**Working tree:** no tracked live OpenAI/Twilio/Square/Firebase secrets. Local `vendor/` and sqlite are ignored.

---

## 3. PII / customer data

| Pattern | Result |
| --- | --- |
| `esoares9483@gmail.com` | Absent |
| `3445 Chelton` | Absent |
| `7196414984` / `+17196414984` | Absent (replaced with `7195550199` synthetic) |
| Landon age Dragon memory | Absent (dump excluded) |
| Seed shop phone | `719-555-0100` synthetic Demo Auto |
| Demo customers | `*@example.test` / `7195550xxx` style |

**Customer/employee hard PII in publishable snapshot history:** effectively **cleared** for known blockers.

---

## 4. Forbidden content

| Item | Result |
| --- | --- |
| `rte/*.csv` / `rte/*.sql` / `ark_labor_flat.csv` | **Absent** |
| `database/data/dragon-arkai-import-v1.json` | **Absent** (README only) |
| `docs/shop-excellence/cecil-bullard`, `lucas-underwood`, `sources.yaml` | **Absent** |
| `infra/coolify`, production deploy scripts, backups | **Absent** |
| Growth GSC analytics dumps | **Absent** |
| `rte/rte_job_menu.txt` (~4.7 KB job category menu) | **Present** — treat as **review item** (derived from labor-guide export; may need drop or rewrite) |
| `rte/schema.md` | Present — schema docs only; lower risk |

---

## 5. Residual shop identity (non-blocker for secrets, blocker for “feels intentional”)

**~176 tracked files** still contain `demo-auto` / `Demo Auto Repair`, including:

- Private IDE / agent rule packs (excluded from public candidate)
- Engineering / communications docs
- Shop Glass workflow artifact name `Demo Auto Repair-Shop-Glass-Setup-…`
- Dragon bakeoff comment “Frozen Demo Auto Repair floor set”
- Manifest/README explaining exclusions (acceptable)

This does **not** reintroduce secrets or licensed CSV fuel, but a public first impression must not read like an accidental private-shop dump. **Scrub pass required** (or move shop-specific doctrine to private foundry only).

---

## 6. Dependency / license inventory

### Composer (165 packages)

| License | Count | Note |
| --- | --- | --- |
| MIT | 129 | OK |
| BSD-2/3 | 33 | OK |
| Apache-2.0 | 1 | OK |
| GPL-2.0 / GPL-3.0 (dual w/ BSD — nette) | 2 pkgs | Usually OK under BSD choice |
| LGPL-3.0 (`smalot/pdfparser`) | 1 | Review obligations |
| OSL-3.0 (`apimatic/jsonmapper`, Square stack) | 1 | **Review** before ARK license choice |

### npm

- Mostly MIT/ISC/Apache
- **`preline`:** MIT **and** Preline UI Fair Use License — **legal review required**

**Third-party redistribution rights known for ARK’s eventual license?** **NO** until counsel clears flags.

---

## 7. Boot / tests (this tree)

| Check | Result |
| --- | --- |
| `composer install` | Succeeds |
| `tests/Unit/Operations/PhoneNumberTest.php` | **2 passed** |
| `tests/Feature/Dragon/DragonMemoryLevel3Test.php` | **12 passed · 1 failed** (`authorized_staff_can_inspect_and_forget_memory_in_settings` expected 200, got **302**) |
| Full `php artisan test` | **Not run** |
| MySQL migrate + seed stranger boot | **Not run** |
| `npm install` / Vite build | **Not run** this pass |

Failure note: 302 on Dragon memory settings looks like auth/middleware redirect debt in staging config — investigate before claiming green.

---

## 8. What must happen next (order)

1. **Scrub pass** — Demo Auto Repair narrative out of public-tracked docs/rules/workflow names; decide fate of `rte_job_menu.txt`
2. **Decide** license + Dragon boundary (still open)
3. **Legal** Preline Fair Use + OSL/LGPL notes
4. **Public scaffolding** — SECURITY.md, CONTRIBUTING, issue/PR templates (optional second commit or fold into first)
5. **Amend/squash** into clean history under same noreply identity (still no remote)
6. **Re-run this certification** on the amended tip
7. **Only then** create GitHub remote

---

## Final answers

| Question | Answer |
| --- | --- |
| Is `1f61f7fa` a clean publication *shape*? | **YES** (one commit, noreply, no remote, no foundry history) |
| Are known secret/PII/fuel blockers gone from this snapshot? | **YES** |
| Is the snapshot certification clean enough to create GitHub? | **NO** |
| Biggest remaining snapshot debt | Demo Auto Repair narrative scrub · license/Dragon decisions · Preline/OSL review · full tests · optional `rte_job_menu` |

**STOP.** Do not create the remote yet.
