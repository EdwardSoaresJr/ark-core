# ARK Release Dependency Matrix

**Point-in-time:** 2026-11-24  
**Core baseline:** `origin/main` `9cc85289`  
**SaaS baseline:** `ark-stancl` `origin/main` `df735e40` → Core pin `9cc85289`  
**Companion:** `ARK_INTEGRATION_READINESS_AUDIT.md`, `ARK_UI_MIGRATION_STATUS.md`

No merges, rebases of other agents’ branches, pin bumps, or deploys were performed by this audit.

---

## 1. PR dependency graph (UI pack)

```
main (9cc85289)
  │
  ├── #254 Job Board List UX (f10b70f5)
  │     └── #256 Job Board Cards (d35acf75)  [base: feat/job-board-list-ux-audit]
  │
  ├── #257 Collection List/Cards (9b5bc832) ── needs rebase onto #258 API after #258 merges
  │
  ├── #258 Layout prefs + System appearance (4d20aa7f)
  │
  ├── #259 Preline drop (b54c4864)
  │
  └── #255 DataTables eval (3b614614) DRAFT ── DO NOT MERGE into product path
```

| Edge | Relationship |
|------|----------------|
| #256 → #254 | #256 **contains** #254 (base branch is list-UX). Merging #256 alone is enough for Job Board visual pack; close #254 after or merge via #256. |
| #257 ⇄ #258 | **Soft conflict / dual authority.** Shared intent (layout preference); different stores (cookies vs localStorage). Prefer merge #258 then rebase #257 onto preference helper. |
| #256 ⇄ #258 | Overlap: Job Board blades, layout JS, possibly tests. Land visual pack (#256) before preference rewrite (#258), or rebase #258 after #256. |
| #259 ⇄ #258 / #256 | Overlap: `resources/js/app.js`, possibly `app.blade.php`, Vite/Tailwind. Prefer #259 after Job Board visual or rebase #259 onto merged tip. |
| #255 | Independent eval; no product dependency. Keep draft / close without merge. |

### Shared-file hotspots (expect conflict / careful merge)

| File / area | Touched by |
|-------------|------------|
| Job Board blades / list+card partials | #254, #256, #258 |
| `resources/css/tabler.css` | #254–#259 (layout rules) |
| `resources/js/app.js` | #258 (boot), #259 (Preline removal) |
| Layout JS (`ark-job-board*`, collection, preference) | #256, #257, #258 |
| `resources/views/layouts/app.blade.php` | #258 (theme boot), possibly #259 |
| `bootstrap/app.php` / middleware cookie except | #257, #258 |
| Acceptance / Pest Job Board tests | #254, #256, #258 |

### Migrations

| Change | DB migration? |
|--------|----------------|
| #254 / #256 Job Board UI | No |
| #257 Collection List/Cards | No (cookies) |
| #258 Appearance System | **Yes if** display-theme column / settings path is new vs `main` — verify on tip before deploy; sequence after Core merge, before SaaS pin if SaaS reads same schema |
| #259 Preline | No (assets only; rebuild Vite) |

**Assets:** Any merge that touches CSS/JS requires a fresh Vite build in the release artifact. Do not mix a `main` source tree with a tip’s `public/build` from another agent’s Herd hot file.

---

## 2. Workstream readiness table

| Feature | Implementation | Tests | UI | Dependencies | Risks | Class | Next action |
|---------|----------------|-------|-----|--------------|-------|-------|-------------|
| Job Board List UX | #254 `f10b70f5` | Focused CI SUCCESS; Regression pest **SKIPPED** | Agent screenshots in PR | Subsumed by #256 | Stale if #256 tip moves | **READY FOR OWNER REVIEW** | Accept visually via #256; do not merge #254 alone if #256 preferred |
| Job Board Cards | #256 `d35acf75` | Focused SUCCESS; pest **SKIPPED** | Card acceptance package | #254 content; then #258 for prefs | Shared-file conflicts with #258 | **READY FOR OWNER REVIEW** | Owner visual accept → merge first in UI pack |
| DataTables Job Board | #255 `3b614614` draft | Eval only | N/A product | None | Productization would bypass List/Card contract | **DEFERRED** | Keep draft; do not merge |
| Customers/Vehicles/RO List+Cards | #257 `9b5bc832` | Focused SUCCESS; pest **SKIPPED** | 1440/390 agent shots | Prefer after #258 | Cookie dual-authority vs #258; not owner-accepted | **NEEDS CORRECTION** *(integration)* / reviewable as UX | Owner UX review OK; **must** rebase onto #258 before merge |
| Layout prefs + System theme | #258 `4d20aa7f` | Focused SUCCESS; pest **SKIPPED** | Job Board + appearance shots | After #256 recommended; SaaS tenant key | Collection modules reserved not wired; EncryptCookies / ShopChannelScope on hosted | **READY FOR OWNER REVIEW** | Owner accept → merge after #256/#259 sequencing |
| Preline drop | #259 `b54c4864` | Asset/bootstrap asserts; pest **SKIPPED** | Spot screenshots | Rebase if `app.js` conflicts | Comment-only false positives in scans | **READY FOR OWNER REVIEW** | Merge after or with Job Board pack; rebuild assets |
| Tabler shell (staff) | On `main` | Historical | Live | Islands remain | `app.css` still global | **READY FOR OWNER REVIEW** *(shell complete)* | Continue island retirement separately |
| Drop `app.css` on Tabler | Not started | — | — | Guide/worksheet/print/estimate islands | High visual regression | **BLOCKED** | Island migrations first |
| SaaS Core pin bump for this pack | Not started | — | — | All Core merges + Vite artifact | Wrong pin / hot-file drift | **NOT STARTED** | After Core `main` advances; Prepare/Deploy only with owner command |
| Production cutover this pack | Not started | — | — | Owner accept + staging | Live SHA unverified this audit | **BLOCKED** | Owner approval required |
| Financial / auth amounts / labor matrix | Outside this pack | Not re-audited E2E | — | Separate | Do not couple to UI pack | **DEFERRED** *(this audit scope)* | Separate acceptance track |
| Comms email/SMS/voice | Boundary: Core must not add; SaaS owns | assert-no-communications | — | SaaS PRs | False “Core missing comms” | **DEFERRED** *(boundary)* | SaaS track only |
| Onboarding / entitlements | SaaS | Not re-audited | — | Stancl open PRs unrelated | — | **DEFERRED** *(this audit)* | SaaS owner track |

---

## 3. Safe merge order (owner-approved only)

1. **Do not merge #255** (draft eval).
2. **Merge #256** → Job Board List+Cards visual pack lands on `main` (closes #254 intent).
3. **Merge #259** (rebase onto post-#256 if `app.js` conflicts) → Preline gone from runtime.
4. **Merge #258** (rebase onto post-#256/#259 if Job Board/layout/theme files conflict).
5. **Rebase #257** onto post-#258 tip; switch collection indexes to `UiLayoutPreference` / `ark-ui-layout-preference.js`; drop competing cookie-only authority; re-run focused tests; then merge.
6. **Do not** bump SaaS `ark/core` pin until Core `main` contains the intended SHAs and a release artifact is named.
7. **Staging acceptance** on integrated SaaS (not standalone Core Herd) before production.
8. **Deploy** only via explicit “Deploy the approved release.” with artifact + `lugsnplugs.arksms.com` (or named target) — **not** authorized by this audit.

Branches requiring rebase before/after merge: **#257** (required onto #258), **#258** and **#259** (likely onto #256), **#256** if `main` moves.

Changes that must remain separated: #255; financial/comms/SaaS topology PRs; Dependabot bumps (orthogonal risk).

---

## 4. Integrated staging acceptance checklist (post-merge)

| Check | Standalone Core ≠ SaaS |
|-------|-------------------------|
| Tenant key in layout preference storage | Must match `ShopChannelScope` / shop id |
| Cookie encryption except list | Hosted EncryptCookies middleware |
| Vite manifest from release image | No Herd `hot` file in container |
| Permissions / OperationsAccess | Real tenant roles |
| Theme System under OS dark/light | Real browser, not fixture-only |
| Full Pest / Regression | Run or explicitly waive with owner sign-off — skipped CI ≠ PASS |

---

## 5. Prioritized roadmap

### 1. Immediate regressions and blockers
- Prefer single layout-preference authority before dual merge of #257+#258.
- Confirm no production freeze/maintenance owned by another session before any later deploy (not this audit).
- Treat pest **SKIPPED** as coverage gap, not green.

### 2. Job Board owner acceptance
- Review #256 (includes #254) screenshots + live tip `d35acf75` on correct Vite build.
- Accept or return with concrete visual defects.

### 3. Unified Customers/Vehicles/RO List/Card
- UX review of #257 tip `9b5bc832`.
- After #258: rebase + wire shared preference; re-test clean URLs, per-module independence, mobile Cards default.

### 4. Preference and theme integration
- Owner review #258; verify System theme + FOUC boot.
- SaaS: tenant-scoped key + cookie except parity.

### 5. Legacy ARK UI retirement
- Land #259 Preline drop after owner review.
- Keep `app.css` until islands migrated (guide, worksheet, print, estimate article).
- Update stale docs that claim shell chrome still drives `app.css`.

### 6. Outstanding operational workflows
- Check-in, estimates/auth, tech assignment, queues, inspections, parts, scheduling — **not** claimed complete by this UI pack; spot-check after Job Board merges for nav/layout regressions only.

### 7. Financial and authorization correctness
- Separate track; do not gate or unblock via List/Card PRs.

### 8. SaaS onboarding and tenant readiness
- Separate Stancl PRs; pin bump only after Core merges.

### 9. Performance and scalability
- Preline removal is the near-term win (#259). Further `app.js` splitting is backlog.

### 10. Production release sequencing
- Owner accept → merge order above → Core `main` SHA → SaaS pin → staging integrated accept → Prepare → Deploy named artifact/target → health on `lugsnplugs.arksms.com` only claims **DEPLOYED AND HEALTHY**.

---

## 6. Next five highest-value engineering tasks

1. Owner visual accept (or reject) **#256** Job Board pack.
2. Merge sequence **#256 → #259 → #258** after acceptances (owner-authorized merges).
3. Rebase **#257** onto #258 preference API; eliminate dual layout stores.
4. Integrated **SaaS staging** pass (tenant key, EncryptCookies, no Vite hot).
5. Run or schedule **full Pest/Regression** on the stacked tip — do not ship on focused CI alone.

---

## 7. Overall release-readiness verdict

| Question | Answer |
|----------|--------|
| Ready for owner acceptance of individual open UI PRs? | **Yes** — #254/#256/#257/#258/#259 tips are reviewable at SHAs above |
| Ready to merge without conflict plan? | **No** — use §3 sequence |
| Ready to bump SaaS Core pin? | **No** — Core `main` unchanged for this pack |
| Ready for production deploy of this pack? | **No** — **NOT READY** / **BLOCKED** pending owner accept, merges, integrated staging, named deploy command |
| Owner-accepted? | **No** for #254–#259 |

**Audit stop:** evidence and plan only. Owner approval required before merging or deploying anything.
