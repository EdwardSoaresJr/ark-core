# Tabler UX pack — release candidate

**Point-in-time:** 2026-11-24  
**Integration owner session:** merge + SaaS pin + staging deploy attempt  
**Production deploy:** **not authorized** by this document

## 1. Final SHAs

| Layer | Full SHA | Notes |
|-------|----------|-------|
| Core `main` | `bc5b8187008f3002a6d28039f86eb4a44841484e` | Merged #254, #256, #257, #258, #259 |
| SaaS `main` | `67762712fe5fb6a65307450dd54b57ab464ed159` | Pin PR #349 |
| SaaS → Core pin | `bc5b8187008f3002a6d28039f86eb4a44841484e` | In `composer.json` / `composer.lock` |
| Rollback SaaS pin | `9cc852899b2ab9754185675b7d83d656978b2b33` | Prior pin (#253 era) |
| Staging rollback SaaS HEAD (pre-deploy) | `df735e4096e94eb8f0440f5124794ea924a88d2a` | Last healthy staging before this cutover |

## 2. Merged PR inventory (Core)

| PR | Role | Result |
|----|------|--------|
| #254 | Job Board List UX | Merged (also contained in #256) |
| #256 | Job Board Cards | Merged |
| #259 | Preline drop / asset isolation | Merged (rebased onto post-#256) |
| #258 | UiLayoutPreference + System appearance | Merged (rebased) |
| #257 | Customers/Vehicles/RO List+Cards | Merged after rebase onto #258; wired to shared preference API |
| #255 | DataTables eval | **Not merged** (draft; do not productize) |

## 3. Integration fix on #257

`CollectionLayoutPreference` is a thin adapter over `UiLayoutPreference`. Browser store is `ark.ui.layout.v1:{tenant}:{user}:{module}`. Collection JS uses `ark-ui-layout-preference.js`, strips `?view=`, dual-writes legacy cookies. Filter/sort query strings no longer emit `view=`.

## 4. Tests executed this session

| Suite | Result |
|-------|--------|
| Focused UI/prefs (43) after #257 wire-up | **passed** |
| Job Board + Collection + AuthorizedCentDrift sample (43) | **passed** |
| AuthorizedCentDrift alone (13) | **passed** |
| Core GitHub `Tests` on `bc5b8187` | **success** |
| SaaS `EncryptCookiesExceptTest` (8 assertions) | **passed** |
| Full local `php bin/pest` | **IN PROGRESS / buffered** — do not claim PASS until EXIT recorded |
| Overnight financial FINDINGS P1 (F-RPT-01/02, F-DOC-01, etc.) | **Open product debt** — outside this UX pack; not claimed fixed |

## 5. SaaS integration

- Branch/PR: https://github.com/EdwardSoaresJr/ark-stancl/pull/349 (merged)
- EncryptCookies constructor calls `UiLayoutPreference::legacyCookieNames()` via `disableFor`
- Staging git: host lacked GitHub deploy key; tip imported via `git bundle` then `deploy.sh`

## 6. Staging

| Item | Value |
|------|-------|
| Host | `157.230.172.143` (`ark-stancl-staging`), `APP_ENV=staging` |
| Deployed SHA | `67762712fe5fb6a65307450dd54b57ab464ed159` |
| Pre-deploy / rollback HEAD | `df735e4096e94eb8f0440f5124794ea924a88d2a` |
| Pre-deploy backup | `ark-stancl-20261124T154445Z.tar.gz.gpg` |
| Deploy log | `/var/log/ark-stancl-predeploy-cursor-ux-20261124.log` |
| Status | **DEPLOYED** — health OK; `https://staging.arksms.com/up` → 200; Horizon/Reverb activated; shop asset HTTP probes OK |
| Core pin on host | `bc5b8187008f3002a6d28039f86eb4a44841484e` (`UiLayoutPreference` present) |
| Built assets | `app-US1jlZt9.js` / `tabler-DOFHxzQL.css`; no Preline in built JS |
| Outbound | Staging limits on (not production) |

**Note:** Staging GitHub deploy key was missing; tip was imported with a local `git bundle` before `deploy.sh`.

### Browser acceptance

| Check | Result |
|-------|--------|
| `demo.staging.arksms.com/app/login` | Loads (demo banner: texts/calls/email/payments off) |
| `demo.staging.arksms.com/demo` guest enter | **Failed** — POST `/demo/enter` redirected back to `/demo` (guest advisor entry not completing). Authenticated Job Board / Customers List-Cards / System theme UI **not** re-verified in-browser this session |
| Synthetic curl after failed guest enter | `/app` and `/app/customers/search` → 302 (unauthenticated) |

Owner (or a follow-up session with working demo credentials / fixed guest entry) should confirm List|Cards toggles and System appearance on a logged-in staging shop before production.

## 7. Production recommendation

**BLOCKED — staging authenticated UI acceptance + full Pest still required before READY FOR PRODUCTION:**

1. ~~Staging deploy healthy~~ **done**.
2. Staging **authenticated** browser acceptance of List/Cards + preferences + theme (guest enter currently broken on demo staging).
3. Full Pest exit code recorded (or explicit owner waive against known-failure baseline). This session: **56/56** focused UI/financial gate suite passed; unrestricted full `php bin/pest` was started but killed after long zero-output buffering — **not** claimed as PASS.
4. Explicit owner command: **Deploy the approved release** naming artifact + `lugsnplugs.arksms.com` (or Landlord shop-release path).

### Classification

**READY FOR RELEASE** (Core + SaaS tips merged) with **staging cutover done**, but **not** READY FOR PRODUCTION until §7 items 2–4 clear.

### Proposed production command (do not run until owner authorizes)

Hosted shop path remains Landlord `host/shop-release/shop-release.sh` via `cursor-release` after an immutable Core image / hosted artifact is prepared (`Prepare this for release.`). Merging Core/SaaS alone does **not** cut over LugsNPlugs.

Rollback staging (if needed): `bash /var/www/ark-stancl/deploy/deploy.sh df735e4096e94eb8f0440f5124794ea924a88d2a`
