# ARKademy → BookStack Foundation Plan

**Status:** Phase 1a complete. Validate platform before SSO or migration.

**Principle:** ARK V2 remains the operating system and **identity authority**. BookStack is the **ARKademy projection** — training, SOPs, and shop documentation. Shop In A Box base content must be reusable across future shops without forking BookStack.

## Ecosystem authorities (Phase 1a milestone)

| Product | Role |
|---------|------|
| **ARK V2** | Operations authority |
| **ARKademy** (BookStack) | Knowledge authority |
| **ARK-WEB** | Public authority |
| **Arkify** (Coolify) | Infrastructure authority |

Distinct products, not one giant Laravel app. `arkademy_content_registry` lives in **ARK**; BookStack pages are storage only. Base / shop / promoted / deprecated are ARK concepts.

---

## Current state (arksmsv2)

| Area | Today |
|------|--------|
| Product name | **ARKademy** (`Branding::learnName()`) |
| Host | `learn.demo-auto.test` → 302 to `app.demo-auto.test/app/learn` |
| Content | ~59 Blade articles in `resources/views/operations/learn/{role}/` |
| Registry | PHP catalog classes (`LearnArk*Articles.php`) + `LearnArkCurriculum` |
| Auth | Staff Breeze session + `OperationsAccess`; **no SSO/OIDC** |
| Progress | MySQL (`learn_completions`, heartbeats, checkpoints, training gate) |
| Theme | Per-user accent in ARK (`accent_theme`, default `ark2` / cerulean `#0099cc`) |
| Shop branding | Logo/name only — no shop-level theme color |
| Shop In A Box | **Not in repo** — greenfield concept |

Training gate, required curriculum (18 articles), and team progress **must stay in ARK** until a deliberate Phase 2 replaces them. BookStack does not provide equivalent operational gating.

---

## Phase order (do not skip)

**Prioritization:** validate BookStack as ARKademy before marrying the ecosystem.

```
Phase 1a — Deploy + brand + backup/update ops ✅ DONE
  learn.demo-auto.test → BookStack ARKademy

Phase 1c — Canonical empty shelves (before any migration)
  Create structure, live with it ~1 day, dummy pages optional
  Do NOT migrate Blade articles until shelves feel right

Validation gate — spend days inside BookStack
  Question: Does this feel like a knowledge operating system, or a wiki?
  Yes → Phase 1b SSO
  No → stop and evaluate before building more

Phase 1b — SSO (after validation)
  ARK User → OIDC → BookStack user projection

Phase 2+ — Registry sync, migration tooling, retire Blade ARKademy
```

**Do not rush SSO.** Structure changes are cheap now; expensive after hundreds of pages.

### Phase 1c canonical shelves (empty — create before migration)

**Shop In A Box** (base — distributable)

```
Shop In A Box
├── Owner
├── Service Advisor
├── Technician
├── Operations
└── ARK V2
```

**Demo Auto Repair SOPs** (shop — private)

```
Demo Auto Repair SOPs
├── Service Advisor
├── Technician
└── Operations
```

Optional: a handful of dummy pages to test IA. No real content migration yet.

### Base content promotion (Shop In A Box growth model)

```
Local SOP → Proven in shop → Promote to Base
```

Not everything written locally becomes base. Promotion is explicit, audited in ARK (`arkademy_content_registry`).

### ARK content registry (day one — in ARK, not BookStack)

Table: `arkademy_content_registry` — ARK owns what is **base** vs **shop** and what is **distributable**.

See migration `2026_06_14_100000_create_arkademy_content_registry_table.php` and `infra/coolify/bookstack/README.md`.

---

## 1. Recommended deployment (Arkify / Coolify)

Follow the **arkweb pattern**: separate Coolify application on `ark-demo-shop-production` (`203.0.113.10`), never the control plane.

| Item | Recommendation |
|------|----------------|
| Coolify project | **ARK** |
| App name | `arkademy` or `bookstack` |
| Image | Official [BookStack Docker](https://www.bookstackapp.com/docs/admin/installation/#docker) (`lscr.io/linuxserver/bookstack` or `solidnerd/bookstack`) |
| Domain | **`https://learn.demo-auto.test`** — BookStack becomes the real ARKademy host |
| Database | New schema `bookstack` on existing **ark-mysql** (same as arkweb) |
| Storage bind | `/data/ark-shared/bookstack-uploads` → BookStack `storage/uploads` |
| Theme bind | `/data/ark-shared/bookstack-themes/arkademy` → `themes/arkademy` |
| Proxy | Coolify Traefik (`coolify-proxy`); FQDN must include `https://` prefix |
| TZ | `UTC` (match ARK timestamp authority) |

### Domain cutover (required)

Today `learn.demo-auto.test` is wired to **arksmsv2** and redirects to `/app/learn`.

Before BookStack goes live:

1. Remove `learn.demo-auto.test` from **arksmsv2** Coolify FQDNs.
2. Clear or repurpose `LEARN_DOMAIN` on arksmsv2 (embedded learn stays at `https://app.demo-auto.test/app/learn` until migration completes).
3. Assign `https://learn.demo-auto.test` to the BookStack app.
4. Update ARK nav link: ARKademy → `https://learn.demo-auto.test` (SSO handoff, not redirect loop).

### ARK integration surface (Phase 1)

- Ops nav **ARKademy** link opens BookStack (new tab or same tab after SSO auto-init).
- Keep `/app/learn/*` routes alive during transition — dual-run, not big-bang delete.
- Document in `infra/coolify/DEPLOYMENT.md` (parallel to arkweb section).

### Do not

- Bundle BookStack inside the arksmsv2 Dockerfile.
- Fork BookStack core.
- Run builds on 1GB VPS without swap.

---

## 2. Recommended SSO (ARK = identity authority)

### Constraint

ARK has **no OIDC/SAML/LDAP today**. Staff auth is email/password + Spatie roles. SSO is greenfield on the ARK side.

BookStack natively supports **OIDC** (recommended), SAML 2.0, and LDAP. OIDC is the best fit: PKCE, group sync, auto-registration, `AUTH_AUTO_INITIATE` for seamless launch from ARK.

### Recommended architecture

```
Staff user (already logged into ARK)
    → clicks ARKademy
    → learn.demo-auto.test (BookStack)
    → OIDC redirect to ARK issuer
    → ARK validates existing session (no second password)
    → tokens + claims (sub, email, name, groups)
    → BookStack creates/links user via external_auth_id
```

**Identity mapping**

| Claim | Source |
|-------|--------|
| `sub` / external ID | ARK `users.id` (set `OIDC_EXTERNAL_ID_CLAIM` if needed) |
| `email` | `users.email` |
| `name` | `users.name` |
| `groups` | Spatie roles: `admin`, `advisor`, `technician` (+ optional `owner` for master admin) |

**BookStack role sync**

Enable `OIDC_USER_TO_GROUPS=true`. Map BookStack roles to ARK role names via role “External Authentication IDs” (e.g. `admin` → Admin, `advisor` → Editor, `technician` → Viewer).

BookStack roles (suggested):

| BookStack role | ARK mapping | Purpose |
|----------------|-------------|---------|
| Admin | `admin`, master admin | Base content curation, Shop In A Box shelf |
| Editor | `advisor`, `admin` | Shop SOPs, local procedures |
| Viewer | `technician` | Read training + SOPs |
| API / Content Admin | platform service account | Migration scripts only |

### ARK OIDC issuer (new work — Phase 1b)

BookStack needs an OIDC provider. Options ranked:

| Option | Pros | Cons |
|--------|------|------|
| **A. OIDC module in ARK V2** (Passport + OIDC bridge or dedicated package) | Single identity authority, no extra service | New ARK runtime surface; must maintain issuer |
| **B. Authentik / Keycloak on Coolify** | Mature OIDC, admin UI | Second user directory unless federated from ARK |
| **C. BookStack standard auth (interim)** | Fastest foundation deploy | Separate passwords — violates goal |

**Recommendation:** **A** for production. **C** only for a short-lived foundation spike (max 1–2 weeks) while OIDC issuer is built.

OIDC issuer endpoints (target):

- Issuer: `https://app.demo-auto.test` (or `https://auth.autorepairkeeper.com` if shared across shops later)
- Discovery: `/.well-known/openid-configuration`
- Callback: `https://learn.demo-auto.test/oidc/callback`

BookStack env essentials:

```env
AUTH_METHOD=oidc
AUTH_AUTO_INITIATE=true
OIDC_NAME=ARK
OIDC_ISSUER=https://app.demo-auto.test
OIDC_CLIENT_ID=...
OIDC_CLIENT_SECRET=...
OIDC_USER_TO_GROUPS=true
OIDC_GROUPS_CLAIM=groups
OIDC_REMOVE_FROM_GROUPS=false
```

### SSO Phase checklist

- [ ] Deploy BookStack (standard auth + break-glass admin)
- [ ] Implement ARK OIDC issuer (authorize, token, userinfo, JWKS)
- [ ] Register OIDC client for BookStack
- [ ] Switch BookStack to `AUTH_METHOD=oidc`
- [ ] Bulk-set `external_auth_id` = ARK user id for existing users (or rely on auto-register)
- [ ] Verify role sync for admin/advisor/technician
- [ ] ARK nav: ARKademy opens BookStack with single sign-on
- [ ] Logout behavior: decide RP-initiated logout (`OIDC_END_SESSION_ENDPOINT`)

---

## 3. Theme — ARK blue default + user accent

### ARK blue (default)

From `tailwind.config.js` / `AccentTheme::Ark2`:

| Token | Hex |
|-------|-----|
| Primary (500) | `#0099cc` |
| Hover (600) | `#007db3` |
| Light (400) | `#38abdb` |
| Dark (800) | `#004d70` |

### BookStack theming (no fork)

**Layer 1 — Settings → Customization (required)**

- App name: **ARKademy**
- Logo: ARK / shop logo from `public/assets/ARK_SMS_FINAL_DROP_IN_PACK/` or shop upload
- Primary color: `#0099cc`
- Link color: `#007db3`

**Layer 2 — Visual theme `APP_THEME=arkademy` (minimal)**

Bind `themes/arkademy/` for:

- Custom header partial (optional “Back to ARK” link to `app.demo-auto.test`)
- Logo overrides only — avoid copying full BookStack views

**Layer 3 — Custom HTML head (accent sync — Phase 1b)**

Inject CSS variables on login via logical theme `functions.php`:

- Read `accent` from OIDC claim or signed query param on ARK → BookStack launch
- Map ARK `AccentTheme` presets to BookStack CSS variables (`--color-primary`, etc.)
- Custom user hex (`accent_color`) when theme = `custom`

**Realistic scope**

| Phase | Deliverable |
|-------|-------------|
| 1a | ARK blue default in BookStack settings + ARKademy name/logo |
| 1b | Per-user accent when launched from ARK (OIDC claim preferred over query string) |
| 2 | Per-shop default accent in `shop_settings` (future multi-tenant) |

BookStack settings pages do not apply custom HTML head — acceptable; staff live in content, not settings.

---

## 4. ARKademy information architecture

Map current role catalogs to BookStack hierarchy.

### Shelves

| Shelf | Scope | Audience |
|-------|-------|----------|
| **Shop In A Box** | Base — shared across shops | All roles; curated by platform admin |
| **Demo Auto Repair SOPs** | Shop — private | Staff; shop admin editors |

Future shops: duplicate shelf template `Shop In A Box` via export/import; add `{Shop Name} SOPs` shelf locally.

### Books (inside Shop In A Box)

| Book | Maps from | ~Pages |
|------|-----------|--------|
| Advisor Operations | `LearnArkAdvisorArticles` | ~28 |
| Technician Operations | `LearnArkTechnicianArticles` | ~7 |
| Owner Excellence | `LearnArkOwnerArticles` | ~11 |
| Admin Setup | `LearnArkAdminArticles` | ~13 |

### Chapters

Group articles by operational theme (examples):

- **Advisor:** Getting Started, Workboard & RO, Communications, **ARK Mobile**, Estimates & Approvals, Parts & Payments
- **Technician:** Getting Started, Production, Inspection, **ARK Mobile field work**
- **Owner:** Daily Rhythm, Financial Targets, Reports
- **Admin:** Staff, Integrations, Shop Settings

### Pages

One page per current Blade article slug (e.g. `advisor:getting-started` → page slug `getting-started`).

Preserve slug in ARK metadata for redirects and training gate mapping.

### Tags (canonical)

| Tag | Meaning |
|-----|---------|
| `base-content` | Part of Shop In A Box shared curriculum |
| `shop-content` | Shop-specific; not exported to other shops |
| `sop` | Standard operating procedure |
| `training` | Onboarding / required learning |
| `owner` / `admin` / `advisor` / `technician` | Role visibility hints |
| `required` | Required for training gate (ARK authority) |
| `legacy-key:{role}:{slug}` | Migration traceability |

BookStack applies tags as CSS classes on `<body>` — useful for future styling, not authority.

### Permissions

- **Shop In A Box shelf:** all roles read; only BookStack Admin (platform) edit
- **Shop SOPs shelf:** all roles read; shop Admin/Editor edit
- Avoid per-page permission sprawl — shelf + book level is enough initially

---

## 5. Base content vs shop content model

BookStack has no native “base package” flag. Use **tags + shelves + ARK metadata** (do not fork BookStack).

### Three-layer model

| Layer | Authority | Purpose |
|-------|-----------|---------|
| **BookStack** | Content storage, rendering, search | Pages, shelves, permissions |
| **Tags / shelves** | Human-visible organization | Base vs shop, role, SOP vs training |
| **ARK `arkademy_content_registry` (new table, Phase 2)** | Operational truth | Scope, promotion, legacy keys, required training |

Suggested `arkademy_content_registry` columns (future):

```
bookstack_type   enum: shelf, book, chapter, page
bookstack_id     int
scope            enum: base, shop
legacy_key       nullable string  (e.g. advisor:getting-started)
promoted_at      nullable timestamp
promoted_by      nullable user_id
required         bool  (training gate)
content_version  int   (maps to LearnArkCurriculum::VERSION idea)
```

### Workflows

**Create shop SOP**

1. Advisor/admin creates page under `{Shop} SOPs` shelf.
2. Auto-tag `shop-content`, `sop`.
3. ARK registry row: `scope=shop`.

**Promote to Shop In A Box**

1. Shop admin proposes promotion (ARK UI — future).
2. Platform admin approves → API moves/copies page to Shop In A Box book, adds `base-content` tag, updates registry `scope=base`.
3. Optional: export hook for base package refresh across fleet.

**Do not** rely on naming conventions alone — tags + registry must agree.

### Multi-shop (future)

- Each shop instance: own BookStack + own `{Shop} SOPs` shelf.
- Base content: import ZIP or API push from golden **Shop In A Box** export.
- ARK fleet admin (Autorepairkeeper platform) owns base export pipeline — not Demo Auto Repair shop admin.

---

## 6. Migration path (later — not Phase 1)

### Inventory to migrate

- ~59 Blade articles
- 18 required curriculum keys (`LearnArkCurriculum::requiredArticleKeys()`)
- Uploaded media in `storage/app/learn-media/`
- Legacy public images/videos
- In-app deep links (`route('operations.learn.show', ...)` across settings, bookend, etc.)

### Recommended migration sequence

1. **Structure only** — create shelves/books/chapters empty in BookStack
2. **Pilot batch** — 3 articles (one per role) via script; validate HTML, images, video embeds
3. **Automated export** — Blade → HTML (render in Laravel, POST to BookStack API)
4. **Media** — upload attachments via API; rewrite image URLs
5. **Registry backfill** — `legacy_key` on every migrated page
6. **Training gate bridge** — ARK polls BookStack activity API or webhooks for required page IDs
7. **Redirect layer** — `/app/learn/{role}/{article}` → BookStack URL (301 or ARK redirect controller)
8. **Dual-run period** — both surfaces live; compare progress
9. **Retire Blade** — remove catalogs, views, gate middleware targets BookStack only

### API capabilities (BookStack)

- REST API: shelves, books, chapters, pages CRUD
- HTML content upload on create/update
- Import/export ZIP for bulk shelf moves
- Webhooks: page create/update — useful for registry sync
- Activity API: last viewed — useful for training completion projection

### Keep in ARK (do not migrate to BookStack)

- Training gate (`EnsureLearnArkTrainingCurrent`)
- Snooze, team progress, owner gate toggle
- Deep links from operational surfaces (ARK generates BookStack URLs)
- Shop In A Box promotion workflow (business logic)

---

## 7. Risks and constraints

| Risk | Mitigation |
|------|------------|
| SSO delay blocks seamless ARKademy | Interim standard auth only on staging; prod waits for OIDC |
| `learn.demo-auto.test` cutover breaks old links | Keep `/app/learn` until redirect layer exists |
| Training gate regression | Do not retire Blade until BookStack completion projection proven |
| BookStack single-tenant | One instance per shop today; base content via export/import |
| No custom fields in BookStack | ARK registry table for scope/promotion/required |
| Per-user theme complexity | Phase 1b only; default ARK blue is sufficient for launch |
| OIDC issuer scope creep | Minimal issuer — staff users only, no customer portal OIDC |
| Content structure mistakes | No migration until shelves/books/tags signed off |
| VPS RAM | BookStack is lighter than arksmsv2 build; still use bind mounts |
| BookStack replaces operational truth | BookStack is documentation only — ARK keeps gate, roles, shop settings |

---

## 8. Phase 1 implementation tickets (when approved)

### 1a — Deploy (no SSO yet)

- [ ] Coolify app on production server
- [ ] MySQL schema + env
- [ ] Bind mounts for uploads + theme
- [ ] ARKademy branding in settings
- [ ] `infra/coolify/DEPLOYMENT.md` section
- [ ] Remove learn domain from arksmsv2 FQDN (coordinate cutover)

### 1b — SSO

- [ ] ARK OIDC issuer (discovery, authorize, token, userinfo, JWKS)
- [ ] BookStack OIDC client config
- [ ] Role group sync
- [ ] ARK nav link update
- [ ] Tests for OIDC token claims

### 1c — Theme

- [ ] `themes/arkademy` minimal theme
- [ ] ARK blue customization settings
- [ ] (Optional) accent claim / launch param

### 1d — IA skeleton

- [ ] Create shelves/books/chapters (empty)
- [ ] Tag taxonomy documented in BookStack
- [ ] Draft `arkademy_content_registry` migration (no data yet)

**Explicitly out of scope for Phase 1:** content import, SOP writing, Blade removal, training gate rewiring.

---

## 9. Decision log (recommended defaults)

| Decision | Choice |
|----------|--------|
| BookStack hosting | Separate Coolify app on demo-auto production |
| ARKademy URL | `learn.demo-auto.test` |
| SSO method | OIDC with ARK as issuer |
| Default theme | ARK cerulean `#0099cc` |
| Base vs shop | Shelves + tags + ARK registry |
| Promotion | Manual admin action → `base-content` tag + shelf move |
| Training gate | Stays in ARK until Phase 2 bridge |
| Embedded `/app/learn` | Keep during transition |

---

## References

- BookStack OIDC: https://www.bookstackapp.com/docs/admin/oidc-auth/
- BookStack customization: https://www.bookstackapp.com/docs/admin/visual-customisation/
- BookStack API: `{bookstack_url}/api/docs`
- ARK learn code: `app/Ark/Operations/Learn/`
- ARK deploy: `infra/coolify/DEPLOYMENT.md`
- ARK accent: `app/Ark/Runtime/Preferences/AccentTheme.php`, `resources/css/app.css`
