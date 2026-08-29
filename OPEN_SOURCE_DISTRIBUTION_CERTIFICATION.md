# ARK Public Distribution Certification

**Tree:** `local public-candidate staging tree`  
**Base commit (intentional):** `1f61f7fa` — *Initial open-source release of ARK*  
**Working tree:** installer + Compose milestone — **uncommitted** (do not squash yet)  
**Remote:** none  
**Date:** 2026-08-28  

**Question under test:** Can a stranger clone the public snapshot, bring up the stack, visit `/setup`, finish installation against a brand-new MySQL database, restart everything, and still have a working ARK?

---

## Executive verdict

# **DISTRIBUTION PATH: YES (certified locally) · PUBLIC REMOTE: STILL NO**

| Gate | Result |
| --- | --- |
| Disposable empty-MySQL install E2E (host PHP → Compose MySQL) | **PASS** — `INSTALL_OK` · `RESTART_OK` · migrations=292 |
| Docker Compose stranger path (`up -d --build` → `/setup` → install → recreate) | **PASS** — `SETUP_HTTP_OK` · `COMPOSE_INSTALL_OK` · `COMPOSE_RESTART_OK` · host `http://127.0.0.1:8088` **200** |
| Installer feature tests | **PASS** — 8/8 |
| Keep `1f61f7fa` as first intentional snapshot | **YES** — no squash / no amend |
| Create GitHub remote | **NO** |
| Decide LICENSE + Dragon boundary | **YES** — AGPL-3.0-only + `NOTICE`; Dragon empty tanks (see `DRAGON_PUBLIC_BOUNDARY.md` / strategy) |

---

## What was proven

### A. Host installer → disposable MySQL

Script: `docker/selfhost/certify-install.sh`

1. `docker compose up -d mysql` with fresh volume  
2. Empty `ark` database  
3. `CompleteInstallationAction` against `127.0.0.1:3307`  
4. MySQL container restart  
5. Admin auth + migrations still present  

### B. Compose stranger path

Artifacts:

- `docker-compose.yml` — MySQL 8.4 + Apache/PHP app  
- `docker/selfhost/Dockerfile` — `php:8.4-apache-bookworm` (not Coolify)  
- `docker/selfhost/entrypoint.sh` — APP_KEY + `.env` persistence under `storage/app/install/`  
- Host port **8088→80** (host 8080 is often already bound on developer machines)

Story:

```bash
docker compose up -d --build
# open http://localhost:8088 → /setup
```

Wizard DB on Compose network: host `mysql`, port `3306`, db/user/pass `ark`.

Certification used the same `CompleteInstallationAction` the wizard calls (not a second installer). After `docker compose up -d --force-recreate --no-deps app`, install lock + admin password verification held; host HTTP to `/setup` returned **200** (redirect-follow to locked/login surface).

### C. Fixes earned during certification (kept in working tree)

| Issue | Fix |
| --- | --- |
| Image baked host `installed` state / `.env` | `.dockerignore` excludes `.env` + `storage/app/install/**` |
| `.env.example` defaulted to Redis (+ duplicate `SESSION_DRIVER`) | First-run defaults: `file` / `sync`; duplicates removed |
| Env writer left later duplicate keys | Replace **all** occurrences |
| Container recreate dropped installer `.env` | Persist `storage/app/install/dotenv` |
| `artisan serve` behind Docker Desktop | Apache document root |
| Host `:8080` RST | Another local process bound `127.0.0.1:8080` — publish **8088** |

---

## Public-tree scrub (re-run, includes installer)

| Check | Result |
| --- | --- |
| RTE labor CSV fuel | **ABSENT** |
| Dragon arkai import dump | **ABSENT** |
| Coolify app id / `144.202.74.190` | **ABSENT** |
| Hard owner phones / gmail patterns from foundry audit | **ABSENT** from product paths |
| Demo Auto Repair narrative mentions | **~169 files** still reference (mostly docs/doctrine) — **not cleared** |
| Flutter fixture names (`Landon Carter`, `Edward Soares`) | Present in `apps/*/test` fixtures — synthetic bench names; review before publish |
| `rte/rte_job_menu.txt` | Still review redistribution rights if present |

Installer additions did **not** reintroduce secrets, Coolify IDs, or Dragon fuel.

---

## License / Preline questions (inventory only — no decision)

See [DEPENDENCY_LICENSE_NOTES.md](./DEPENDENCY_LICENSE_NOTES.md).

Resolved by project decision (not counsel):

1. **Preline UI** — MIT + Fair Use — attributed in root `NOTICE`  
2. **OSL-3.0** — `apimatic/jsonmapper` — **not in default lock**; optional via `ark/payments-square` only; disclosed in `NOTICE`  
3. **LGPL-3.0** — `smalot/pdfparser` — noted in `NOTICE`  
4. **GPL dual on Nette** — transitive via League; noted in `NOTICE`

**Counsel is not part of the release sequence.**

---

## Explicitly not done

- No GitHub remote / public repo  
- ~~No LICENSE file~~ → **LICENSE** + **NOTICE** present (AGPL-3.0-only)  
- Dragon public boundary locked (empty tanks)  
- No squash into *Add first-run installation and self-host setup*  
- No more installer wizard features / polish  
- No full suite green certification of the entire tree (installer suite only in this pass)

---

## Next (when you say so)

1. Human review of license delta + prior RC remediations  
2. Personal curated commit(s) on `main`  
3. Create public GitHub repo → add origin → push (human only)

**Scripts to re-run anytime:**

```bash
./docker/selfhost/certify-install.sh
./docker/selfhost/certify-compose.sh
```
