# ARK Integration Readiness Audit

**Audit type:** Independent integration / UI / release-readiness review  
**Auditor role:** Evidence only — no merges, deploys, pin changes, or implementation  
**Point-in-time:** 2026-11-24 (America/Denver)  
**Core remote evaluated:** `origin/main` = `9cc852899b2ab9754185675b7d83d656978b2b33`  
**SaaS remote evaluated:** `EdwardSoaresJr/ark-stancl` `origin/main` = `df735e4096e94eb8f0440f5124794ea924a88d2a`  
**SaaS → Core pin:** `ark/core` `9cc852899b2ab9754185675b7d83d656978b2b33` (matches Core `main`)

Any conclusion below is valid only for the SHAs named in that section. Later commits on open PRs require **PENDING RECHECK**.

---

## Executive summary

### Production baseline

| Surface | Evidence | Core / pin |
|---------|----------|------------|
| Public Core `main` | GitHub `origin/main` | `9cc85289` — includes merged Job Board List\|Cards switcher (#253), **not** #254–#259 |
| SaaS / Stancl `main` | `composer.json` / `composer.lock` package `ark/core` | Pinned to Core `9cc85289` |
| Named production shop | Release protocol / Stancl topology docs | Target `lugsnplugs.arksms.com`; older protocol note still cites historical image Core `d926813` — **live digest not re-probed in this audit** (production protection). Treat production Core SHA as **UNVERIFIED LIVE** beyond the SaaS pin on `main`. |
| Staging | Not re-probed over SSH | **UNVERIFIED LIVE** — SaaS pin on `main` is the intended integration candidate pin |

**Verdict on deploy of Tabler UX stack:** **NOT READY.** None of #254–#259 are on `main`. SaaS does not need a pin bump until Core merges land.

### Current integration candidate

Stack of **unmerged** Core PRs on top of `9cc85289`, all reporting focused/targeted/lint/security/communications-boundary **SUCCESS**, with Regression `pest` **SKIPPED** on several:

| PR | Tip SHA (evaluated) | Role |
|----|---------------------|------|
| #254 | `f10b70f5` | Job Board List UX |
| #256 | `d35acf75` | Job Board Cards (contains #254) |
| #257 | `9b5bc832` | Customers / Vehicles / RO List+Cards |
| #258 | `4d20aa7f` | Layout preferences + System appearance |
| #259 | `b54c4864` | Drop unused Preline |
| #255 | `3b614614` **DRAFT** | DataTables eval — **do not productize** |

### Completed (on `main`)

- Tabler shell for reachable staff pages (see `TABLER.md` inventory).
- Job Board List\|Cards switcher (#253) with cookie preference and `?view=` persistence model of that era.
- Coexistence: `app.css` + `tabler.css` + PostCSS island exclusion.

### Completed but unmerged (owner review / not accepted)

- Job Board List polish (#254) and Cards hierarchy (#256) — **READY FOR OWNER REVIEW** (visual).
- Collection List/Cards (#257) — **READY FOR OWNER REVIEW** with integration caveats vs #258.
- Preferences / System theme (#258) — **READY FOR OWNER REVIEW**; collection indexes **not** wired to new store.
- Preline removal (#259) — **READY FOR OWNER REVIEW** (asset isolation only).

### Remaining

- Owner visual acceptance on #254/#256/#257/#258.
- Preferred merge sequence and conflict resolution (matrix doc).
- Wire #257 to #258 preference API (or land #258 first and rebase #257).
- Legacy islands still force `app.css` on every ops Tabler page.
- Full Pest / Regression suite remains **SKIPPED** on these PR workflows — not a green full-suite signal.
- SaaS EncryptCookies / `ShopChannelScope` tenant key readiness before hosted cutover of preference cookies/localStorage behavior.
- Financial / communications / onboarding workstreams outside this UI pack remain separate (not re-audited end-to-end here).

### Critical blockers (release of this UI pack)

1. **No owner acceptance** recorded for #254–#259.
2. **Preference dual-authority risk** if #257 and #258 both merge without integration: collection cookie path vs browser-local scoped store.
3. **Shared-file conflicts** (`tabler.css`, Job Board blades/tests, `app.js`, `app.blade.php`, `bootstrap/app.php`) — merge order required.
4. **Pest skipped** — do not treat focused CI as full regression PASS.
5. **Production live SHA** not freshly verified in this session — do not claim DEPLOYED AND HEALTHY for any of these PRs.

### Recommended merge sequence (after owner approval)

1. Close / leave draft **#255** (eval only).
2. Merge **#256** (includes #254) — Job Board visual pack.
3. Merge **#259** — Preline drop (rebase if `app.blade.php` / docs collide).
4. Merge **#258** — preference + appearance authority.
5. Rebase **#257** onto the above; replace collection cookie-only path with `UiLayoutPreference` / `ark-ui-layout-preference.js`; then merge.
6. Only then: Prepare / Deploy per `RELEASE_PROTOCOL.md` with explicit artifact + target (not authorized by this audit).

### Test gaps

- Nightly / labeled full `pest` not run on these tips in the PR check rollups reviewed.
- No integrated SaaS tenant browser acceptance of #257/#258.
- Multi-viewport matrix (320–1920) not re-executed in this audit pass; relies on agent screenshot packages (see UI status doc).
- Dark / System appearance OS-change behavior: unit/feature coverage on #258; not re-browsered here against tip after branch switch.
- Local re-run (audit session): `JobBoardListViewTest` preference filters **21 passed**; `DisplayThemeBootTest` **1 failed** (`operations_shell_boots_display_theme_in_head_before_assets` — boot marker not found / wrong tip or missing #258 assets). `CustomerHubTablerTest|RepairOrderLifecycleQueueTest` filter run **inconclusive** (Pest JSON parse error in harness, exit 1 — not a clean pass/fail).

### Screenshots requiring owner review

| Package | Path |
|---------|------|
| Job Board Cards | `review-artifacts/` on #256 branch (card hierarchy acceptance) |
| Collection List/Cards | `review-artifacts/tabler-collection-list-cards/screenshots/` (#257) |
| Preferences | `review-artifacts/unified-layout-preferences/screenshots/` (#258) |
| Preline retirement | `review-artifacts/tabler-legacy-asset-retirement/screenshots/` (#259) |

### Next five highest-value engineering tasks

1. Owner visual accept/reject **#256** (Job Board).
2. Land **#258** preference contract, then rebase **#257** onto it (single layout authority).
3. Merge **#259** Preline removal once `app.blade.php` conflicts with #258 are resolved.
4. Run **full Regression** (`regression.yml` / `full-regression` label) on the integration branch before any Prepare/Deploy.
5. SaaS dry-run: EncryptCookies exceptions + tenant scope binding checklist (no pin bump until Core merge).

### Overall release-readiness verdict

**NOT READY FOR PRODUCTION / NOT READY FOR STAGING CUTOVER of this UI pack.**

Subsystems may be **READY FOR OWNER REVIEW** individually. The integrated candidate is **BLOCKED** on owner acceptance, conflict-aware merge order, preference unification, and a non-skipped full regression run.

---

## 1. Repository state

### Core

| Item | Value |
|------|-------|
| `origin/main` | `9cc85289` (#253 Job Board List\|Cards switcher) |
| Audit branch | `docs/integration-readiness-audit-2026-11-24` (docs only) |
| Local checkout at audit start | Had been `feat/tabler-legacy-asset-retirement` `b54c4864`; local `public/build` still reflected Preline-removed assets while source returned to `main` — **local asset/source parity drift** |

### SaaS (`ark-stancl`)

| Item | Value |
|------|-------|
| `origin/main` | `df735e40` |
| Core pin | `9cc85289` |
| Open PRs (sample) | Topology/docs/release/capacity — **none** are the Tabler List/Card UX stack |
| Pin change required for #254–#259 | **Yes, after Core merges** — not before |

### Open Core PRs in scope

| PR | Draft | Mergeable | Focused CI | Pest regression | Owner accepted? |
|----|-------|-----------|------------|-----------------|-----------------|
| #254 List UX | no | CLEAN | SUCCESS | not in rollup reviewed | **No** |
| #255 DataTables eval | **yes** | CLEAN | SUCCESS | n/a | **Rejected for productization** (intent) |
| #256 Cards | no | CLEAN | SUCCESS | SKIPPED | **No** |
| #257 Collection List/Cards | no | CLEAN | SUCCESS | SKIPPED | **No** |
| #258 Preferences / System | no | CLEAN | SUCCESS | SKIPPED | **No** |
| #259 Preline retirement | no | CLEAN | SUCCESS | (green focused) | **No** |

Stacking: **#254 is an ancestor of #256**. Prefer merging #256 alone.

---

## 2. Local environment freshness and parity

| Check | Finding |
|-------|---------|
| Core/SaaS remotes fetched | Yes, this audit |
| Composer pin SaaS ↔ Core main | Match `9cc85289` |
| `public/hot` | Absent during measurements |
| Compiled assets vs branch | **Drift risk:** Herd `public/build` after #259 build does not automatically reset when checking out `main`; screenshots on #259 tip are valid for that tip only |
| Standalone Core vs SaaS | Preference tenant key uses `ShopChannelScope` when bound — **Core-alone tests are not equivalent to multi-tenant SaaS acceptance** |
| Migrations in #254–#259 | No product DB migrations identified in these UX PRs (preference store is cookie/localStorage; appearance remains `users.display_theme`) |

Invalidated if reused without rebuild: any screenshot or network trace taken after switching branches without `npm run build`.

---

## 3. Primary workflow audit (point-in-time on `main` + PR claims)

Scope: UI-pack impact. Deep financial/comms/onboarding re-test was **not** re-executed end-to-end in this auditor session.

| Area | On `main` | With open PRs | Regression risk |
|------|-----------|---------------|-----------------|
| Job Board | List\|Cards switcher (#253) | #254/#256 polish/hierarchy | Medium — shared list/card partials |
| Customers / Vehicles / RO index | Tabler cards/search | #257 adds shared List+Cards + sort | Medium — new controllers/views; must not merge Job Board |
| Check In / RO workspace | Tabler shell + worksheet islands | Untouched by #257; #258 appearance boot | Low for structure; appearance FOUC fix is positive |
| Communications | Tabler workspace; event-bubble islands | Untouched by UX pack | Unchanged |
| Financial / AR / Labor Matrix | Existing Tabler/settings | Untouched | Unchanged by this pack |
| Platform / entitlements | SaaS | No pin change | Unchanged until pin advances |
| Printing | Legacy island helpers | Untouched | Keep `app.css` |

**Disappeared features:** None identified as removed on `main`. #255 must not replace Job Board with DataTables.

---

## 4–6. See companion docs

- UI migration inventory → `ARK_UI_MIGRATION_STATUS.md`
- PR dependency / merge matrix → `ARK_RELEASE_DEPENDENCY_MATRIX.md`

---

## 7. Regression and coverage

| Signal | Interpretation |
|--------|----------------|
| Focused / targeted SUCCESS on #254–#259 | Necessary, **not sufficient** |
| Pest SKIPPED on #256/#257/#258 Regression workflow | **Do not report as passing full suite** |
| Known-failure baseline | `.github/regression-known-failures.txt` (~208 lines on `main`) — full suite still has accounted failures |
| Browser-only | Preference URL stripping, System theme OS listener, Preline network absence — tip-specific |

---

## 8–10. Classifications and roadmap

See workstream table in `ARK_RELEASE_DEPENDENCY_MATRIX.md` and prioritized roadmap there.

---

## Operating boundaries honored

- No merges, deploys, pin changes, production migrations, or live customer mutations.
- Other agents’ branches not rewritten.
- Incomplete work not marked accepted.
- Skipped Pest not reported as PASS.
