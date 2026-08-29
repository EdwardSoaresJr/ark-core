# ARK Ecosystem UX Doctrine

**Status:** Active — 2026-06-14 (ARK-WEB retired from fleet 2026-06-19)  
**Scope:** ARK V2, ARKademy (BookStack), Arkify; public shop host via ARK V2 Public Surface

## Purpose

Make the ARK ecosystem feel **connected** without cloning ARK V2 chrome into BookStack and without forking BookStack.

Each product keeps its native purpose:

| Product | Role |
|---------|------|
| **ARK V2** | Operations — work, customers, ROs, comms |
| **ARK V2 Public Surface** | Public lead intake — `demo-auto.test` (same runtime as ARK V2) |
| **ARKademy** | Knowledge — training, SOPs, Shop In A Box |
| **Arkify** | Infrastructure — deploy, hosts, Coolify control plane |

## Branding rule

| Surface | Branding |
|---------|----------|
| Operational hosts (`app`, `learn`, `platform`) | ARK ecosystem mark + cerulean family |
| Public shop (`demo-auto.test`) | Demo Auto Repair shop branding — intentional exception (served by ARK V2 Public Surface, not a separate product) |

See `docs/branding/ecosystem-identity.md` for favicon and asset ownership.

## Authority boundaries

| Concern | Owner |
|---------|-------|
| Identity (who, email, roles, product access) | **ARK** `users` + OIDC issuer |
| Training gate / completion truth | **ARK** |
| Content registry (base vs shop, legacy keys) | **ARK** `arkademy_content_registry` |
| Knowledge pages, shelves, books | **BookStack** (storage + UX engine) |
| Infrastructure | **Arkify** / Coolify |

BookStack is the **knowledge engine**, not identity authority. Do not build account management or parallel directories in BookStack.

## Ecosystem switcher

Lightweight “where else in ARK?” menu — not a second app shell.

| Surface | Implementation | Placement |
|---------|----------------|-----------|
| **ARK V2** | `x-operations.ecosystem-switcher` | Topbar, left of page title |
| **ARKademy** | Theme `ark-ecosystem-switcher.js` + `/ark/ecosystem-prefs.js` | Header, after logo |
| **Arkify** | Document only — Coolify vendor chrome; guardrails cron for favicon | Manual / future theme injection |

**Not in switcher:** Public Surface (`demo-auto.test`) — customer-facing; staff reach it via browser/bookmarks, not ecosystem menu. Botble **ARK-WEB retired 2026-06-19**.

**Permission rules:**

- Operations — `operations.access` or `ark_v2` product grant
- ARKademy — `arkademy` product grant
- Platform — `admin` role or `is_master_admin` only

Unavailable products are **hidden**, not shown as disabled links.

Config: `config/ark-ecosystem.php` (`ARK_OPERATIONS_URL`, `ARK_ARKADEMY_URL`, `ARK_PLATFORM_URL`).

## Theme aggressively, fork reluctantly

Meaning for ARKademy and ecosystem UX:

1. **Prefer configuration** — BookStack settings, env vars, Coolify env
2. **Prefer theme overrides** — `infra/coolify/bookstack/themes/arkademy/` (`layouts/`, `public/`, `functions.php`)
3. **Prefer mounted assets** — favicons, CSS, JS under theme `public/`
4. **Prefer BookStack APIs** — import command, registry backfill; not Blade duplication in ARK V2
5. **Avoid vendor patches** — no edits inside BookStack container `app/www` except theme mount
6. **Avoid forks** — unless BookStack blocks core ARKademy doctrine (identity projection, OIDC, base/shop IA)

ARK V2 chrome must **not** be copied into BookStack. Connection is: shared mark, accent/display theme sync, ecosystem switcher, and deep links — not shared navigation or layout.

## ARK V2 ↔ ARKademy bridges (phase 1)

Deterministic links only — no AI article matching, no progress sync.

| Source | Target | Class |
|--------|--------|-------|
| Left rail **Learn** | ARKademy home / cutover URL | Existing |
| Call intelligence | `advisor:incoming-calls-floor` legacy page | `EcosystemArkademyBridge` |
| Coaching debrief | Same training page | Partial |
| Daily coaching digest email | Phone floor training URL | Digest projection |

Future: comms coaching opportunity → SOP slug when registry stable.

## Identity presentation

| Element | ARK V2 | ARKademy |
|---------|--------|----------|
| Display name | Topbar avatar + name | BookStack user menu (native) |
| Email | Profile link `title` tooltip | Native account menu |
| Avatar | Initials circle | BookStack default |
| Logout | Settings / Breeze session | BookStack logout (separate session) |
| Cross logout | **Not implemented** — sessions are per product |

OIDC login syncs display theme and accent; ecosystem switcher provides cross-product navigation.

**Display theme sync:** ARK sets `ark_display_theme` on `.demo-auto.test`. BookStack theme middleware reads that cookie on every authenticated request and updates `dark-mode-enabled` before HTML renders — no OIDC re-login required after toggling dark mode in ARK V2.

## Non-goals (this pass)

- Fork BookStack or clone ARK V2 chrome
- Content migration or placeholder SOP authoring at scale
- Training gates, completion tracking, AI recommendations
- Cross-product single logout
- Arkify switcher in vendor UI (documented only)
- ARK-WEB public branding change (Botble retired; Public Surface owns `demo-auto.test`)

## Verification

After deploy:

1. ARK V2 topbar shows **ARK ▾** when user has multiple product grants
2. `learn.demo-auto.test` header shows matching switcher after OIDC login
3. Call intelligence → **ARKademy · phone floor** opens learn URL
4. `demo-auto.test` still shows shop favicon (not ARK)

## Related docs

- `docs/branding/ecosystem-identity.md` — favicon pack
- `docs/ecosystem/arkademy-landing-ia.md` — ARKademy home structure
- `docs/identity/identity-authority-contract.md` — OIDC and product access
- `docs/arkademy/bookstack-foundation-plan.md` — BookStack foundation
