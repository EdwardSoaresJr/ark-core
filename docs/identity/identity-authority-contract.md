# ARK Identity Authority Contract

**Status:** **Accepted** — frozen 2026-06-14. Do not implement OIDC until `docs/identity/oidc-design-pass.md` is accepted.  
**Version:** 1.1 — 2026-06-14  
**Predecessor:** `docs/arkademy/bookstack-foundation-plan.md` (Phase 1a/1a.5 complete; branding audit complete)  
**Sequence:** Doctrine → **Contract** → Implementation

---

## 1. What this is

Phase 1b is not “BookStack login.” It is **ARK Identity Platform** — one authority for who a person is, what they may do, and which shop they belong to. Every other product **projects** that truth; none may become a peer directory.

```
ARK User (authority)
    ↓ OIDC
Product User (projection)
    ├── ARK V2 session (native — same authority)
    ├── ARKademy / BookStack user
    ├── Portal customer (future — separate guard, same pattern)
    └── ARK-WEB admin (future)
```

**First consumer:** ARKademy (`learn.demo-auto.test`).  
**Design requirement:** The same issuer must serve future products without redesign.

---

## 2. Authority vs projection

| Concern | Owner | Notes |
|---------|-------|-------|
| **User** (identity record: id, name, email, active) | **ARK** (`users`) | Single staff directory. No BookStack-native accounts long-term. |
| **Credential** (password, MFA, recovery) | **ARK** | BookStack `AUTH_METHOD=oidc`; no parallel passwords in production. |
| **Role** (operational posture: admin, advisor, technician) | **ARK** (Spatie `roles`) | What the person **is** in the shop. Drives permissions inside ARK V2. BookStack roles are **mapped projections** of role, not source of truth. |
| **Product access** (which products a user may enter) | **ARK** | What the person **may use**. Separate axis from role. See §2.1. |
| **Permission** (fine-grained capability) | **ARK** (`permissions`, policies) | BookStack shelf/book ACLs stay BookStack-local; **staff access tier** comes from ARK role mapping only in Phase 1b. |
| **Shop membership** (which shop a staff user belongs to) | **ARK** (future `shop_id` / tenant claim) | Today: single shop (Demo Auto Repair). Contract assumes multi-shop without schema rewrite. |
| **Customer identity** | **ARK** (`customers` + portal tokens) | Portal is not Phase 1b. Do not merge customer and staff directories. |
| **BookStack user row** | **Projection** | Created/updated on OIDC login; `external_auth_id` = ARK `users.id`. |
| **BookStack groups/roles** | **Projection** | Synced from OIDC `groups` claim on login; ARK role change → next login refresh. |
| **Training gate / curriculum progress** | **ARK** | Unchanged. BookStack does not own completion truth. |
| **Session** | **Per product** | ARK Breeze session on V2; BookStack session after OIDC; no shared cookie jar required. |

**Rule:** If a product needs name, email, role, or product access, it **reads from OIDC claims or ARK API** — never the reverse.

---

## 2.1 Role vs product access (separate axes)

**Role** and **product access** answer different questions. Do not conflate them.

| Axis | Question | Examples |
|------|----------|----------|
| **Role** | What is this person in the shop? | `admin`, `advisor`, `technician`, `customer` |
| **Product access** | Which products may they enter? | `ark_v2`, `arkademy`, `portal`, `ark_web_admin` |

A user has **one or more roles** and **one or more product grants**. Role drives ARK V2 permissions; product access drives whether the OIDC issuer will authorize a given client.

**Examples:**

| Person | Roles | Product access |
|--------|-------|----------------|
| Edward (owner) | `admin` | `ark_v2`, `arkademy`, `portal`, `ark_web_admin` |
| Ben (advisor) | `advisor` | `ark_v2`, `arkademy` |
| Technician | `technician` | `ark_v2`, `arkademy` (override: `ark_v2` only if ARKademy revoked) |
| Customer | `customer` | `portal` only — **no** `ark_v2` |
| Marketing contractor | (none or limited staff role) | `ark_web_admin` only — **no** `ark_v2` |

**Phase 1b defaults (single shop):**

| Role | Default product access |
|------|------------------------|
| `admin`, `advisor`, `technician` | `ark_v2`, `arkademy` |
| `customer` | none (staff OIDC clients reject) |

Defaults may be overridden per user in ARK admin without changing role.

**Issuer behavior:** At `/oauth/authorize`, ARK checks **both**:

1. User is active and authenticated.
2. User has product access matching the requesting `client_id` (e.g. `arkademy` requires `arkademy` product).

If product access is missing, issuer returns `access_denied` — no BookStack user is created.

**Storage (target):** `user_product_access` or equivalent in ARK (not BookStack). Phase 1b may implement defaults-from-role first; schema must allow per-user overrides without redesign.

**Claims:** `groups` carries **roles**; `products` carries **product access** (array of slugs). BookStack ignores `products`; it only cares that authorize succeeded.

---

## 3. Host and branding context (identity-adjacent)

Operational hosts share ARK ecosystem branding; public shop hosts stay shop-branded. Identity issuer URL should live on an **operational** host.

| Class | Examples | Branding | OIDC |
|-------|----------|----------|------|
| Operational | `app.demo-auto.test`, `learn.demo-auto.test`, `platform.autorepairkeeper.com` | ARK | Yes — staff issuer and clients |
| Public shop | `demo-auto.test`, future `acmeautorepair.com` | Shop | No staff OIDC on marketing site |
| Future multi-shop ops | `shop1.arksms.com`, `shop2.arksms.com` | ARK | Same issuer; `shop_id` claim scopes tenant |

See `docs/branding/ownership.md`.

---

## 4. OIDC architecture decision

**Decision (recommended):** ARK V2 becomes the **OIDC provider** (issuer on operational host, e.g. `https://app.demo-auto.test`).

**Rejected for production:**

| Option | Why not |
|--------|---------|
| BookStack reads ARK database directly | BookStack becomes coupled to ARK schema; not a product boundary |
| Authentik / Keycloak as primary directory | Second user store unless fully federated; ops burden |
| BookStack as identity peer | Violates projection rule |

**Issuer surfaces (target, Phase 1b implementation):**

| Endpoint | Purpose |
|----------|---------|
| `/.well-known/openid-configuration` | Discovery |
| `/oauth/authorize` | Authorization code + PKCE |
| `/oauth/token` | Token exchange |
| `/oauth/userinfo` or JWT claims | Profile + groups |
| `/oauth/jwks` | Key rotation |

**Implementation note:** Prefer Laravel-native issuer (Passport OIDC bridge or minimal first-party issuer). No new identity product until ARK issuer proves insufficient.

---

## 5. Claims contract (staff)

### 5.1 Subject stability (`sub`)

**`sub` is forever.** It must never change for the lifetime of an identity record.

| Rule | Detail |
|------|--------|
| **Phase 1b value** | `(string) users.id` — ARK primary key |
| **Future option** | Immutable UUID column (`users.uuid`) if id remapping ever required — set once at create, never updated |
| **Forbidden as `sub`** | Email, username, phone, name, Partstech username, or any mutable profile field |

Products store `external_auth_id` = `sub`. Email changes update profile claims only; projection linkage survives.

### 5.2 Staff claim surface (client-facing)

OIDC clients receive **only** this claim set — nothing about ARK schema, tables, or internal ids beyond `sub`:

| Claim | Source | Mutable? |
|-------|--------|----------|
| `sub` | Stable user identifier | **Never** |
| `email` | `users.email` | Yes (profile) |
| `name` | `users.name` | Yes (profile) |
| `groups` | Spatie role names | Yes (on next login) |
| `products` | Product access slugs | Yes (on next login) |
| `shop_id` | Active shop membership | Yes (tenant switch, future) |
| `email_verified` | `users.email_verified_at` | Yes |

Clients must not receive ARK internal fields (`users.id` as separate claim, password hashes, Spatie permission names, shop_settings keys, etc.).

Minimum claims for all staff OIDC clients:

| Claim | Source | Purpose |
|-------|--------|---------|
| `sub` | `users.id` (immutable; see §5.1) | Stable external ID for all projections |
| `email` | `users.email` | BookStack email match |
| `name` | `users.name` | Display name |
| `email_verified` | `users.email_verified_at` | BookStack account trust |
| `groups` | Spatie role names | BookStack role mapping (`admin`, `advisor`, `technician`) |
| `products` | ARK product access slugs | Issuer-side only; authorize gate per OIDC client |
| `shop_id` | Shop membership (nullable until multi-shop) | Future tenant isolation |
| `display_theme` | `users.display_theme` | Light/dark/system — ARK V2 + ARKademy display sync |
| `accent_theme` | `users.accent_theme` | ARK V2 + ARKademy accent personalization |

Optional later (not Phase 1b blockers):

| Claim | Purpose |
|-------|---------|
| `ark_user_id` | Explicit alias of `sub` if consumers expect prefixed claim |

---

## 5.3 Staff identity vs customer identity

**Two directories, one issuer architecture — not one merged user table.**

| Dimension | Staff identity | Customer identity |
|-----------|----------------|-------------------|
| **Authority table** | `users` | `customers` |
| **Guard** | Staff Breeze / staff OIDC clients | Portal guard (future) |
| **Roles** | `admin`, `advisor`, `technician` | none — not Spatie staff roles |
| **Product access** | `ark_v2`, `arkademy`, `ark_web_admin`, … | `portal` only |
| **Phase 1b** | Issuer + BookStack | **Out of scope** — no customer OIDC |
| **Future** | Same issuer, **separate OIDC clients** | `portal` client; different authorize gate |
| **Forbidden** | Customer row in `users` for portal login | Staff row in `customers` for operations |

A **customer** may have `portal` product access and **no** `ark_v2`. A **marketing contractor** may have `ark_web_admin` and **no** `ark_v2`. Role alone cannot express this; product access can.

Customer OIDC may be years away. Phase 1b still **reserves** separate client ids, product slugs, and claim scoping so customer identity never collides with staff.

**BookStack config (target):**

```
AUTH_METHOD=oidc
OIDC_ISSUER=https://app.demo-auto.test
OIDC_USER_TO_GROUPS=true
OIDC_GROUPS_CLAIM=groups
OIDC_EXTERNAL_ID_CLAIM=sub
```

BookStack roles map via External Authentication IDs — not manual user admin.

---

## 6. How BookStack learns profile without becoming authority

BookStack **never** owns staff identity. On each OIDC login:

1. User hits `learn.demo-auto.test` → redirect to ARK issuer (or `AUTH_AUTO_INITIATE`).
2. ARK authenticates (existing Breeze session or login form).
3. Issuer returns authorization code → BookStack exchanges for tokens.
4. BookStack **upserts** local user row:
   - `external_auth_id` = `sub`
   - name/email from claims
   - roles from `groups` → BookStack role mapping
5. BookStack session is product-local; ARK session remains on `app.*`.

**Forbidden:**

- Creating staff in BookStack admin as primary onboarding
- BookStack password auth in production
- Editing ARK role by changing BookStack role (BookStack role changes are overwritten on next login)
- Bidirectional sync jobs (ARK ↔ BookStack)

**Allowed:**

- BookStack-local content permissions (shelf visibility) that do not redefine staff tier
- Service account for API automation (separate contract, not staff SSO)

---

## 7. Provisioning lifecycle

### 7.1 New employee (happy path)

| Step | System | Action |
|------|--------|--------|
| 1 | **ARK** | Admin creates user (or invite flow): name, email, role, shop membership |
| 2 | **ARK** | User sets password (or accepts invite link) on `app.demo-auto.test` |
| 3 | **ARKademy** | User visits `learn.demo-auto.test` → OIDC → BookStack user **auto-created** on first login |
| 4 | **ARK** | Training gate / curriculum unchanged — ARK tables, not BookStack |

**No pre-provisioning in BookStack required.** First OIDC login is the projection create.

### 7.2 Product access and ARKademy

| Condition | ARKademy access |
|-----------|-----------------|
| User has `arkademy` product access | Yes — OIDC authorize succeeds; user **auto-provisions on first login** |
| User lacks `arkademy` product access | Issuer denies — no BookStack session |
| `customer` role | No staff OIDC clients |
| Deactivated ARK user | Issuer denies |

Default: staff roles (`admin`, `advisor`, `technician`) receive `ark_v2` + `arkademy` unless admin removes product access.

**No user sync jobs.** Projection happens on login only.

### 7.3 Deactivation

| Event | ARK | BookStack |
|-------|-----|-----------|
| Admin deactivates user in ARK | `users.active = false` or soft-delete policy; issuer denies token | User cannot obtain new session; existing session TTL expires |
| Role change | Spatie role updated | Next OIDC login refreshes BookStack groups |
| Termination | Deactivate in ARK only | Do not rely on manual BookStack delete |

**No orphan cleanup job required for Phase 1b** if issuer denial is authoritative. Optional later: disable BookStack user when ARK deactivates (API/webhook).

### 7.4 Invite vs self-registration

| Path | Phase 1b |
|------|----------|
| Admin-created staff | **Yes** — primary path |
| Public self-registration on ARK | **No** for staff |
| BookStack registration | **Disabled** |

---

## 8. Multi-product future (same issuer)

Phase 1b issuer MUST be designed for **multiple OIDC clients** without schema changes:

| Client | Phase | Notes |
|--------|-------|-------|
| `arkademy` | 1b | BookStack on `learn.*` |
| `ark-operations` | implicit | Native V2 session; may later use OIDC for mobile/API |
| `ark-portal` | later | Customer guard — **separate client**, not staff claims |
| `ark-web-admin` | later | CMS staff for shop sites |
| Future products | later | Register client; same `sub` + `shop_id` |

**Requirements:**

- One issuer, many clients (client_id + redirect URI per product)
- Staff and customer clients **must not** share role claims
- Rotating secrets per client
- RP-initiated logout policy documented before Portal joins

If a proposed design requires a **second issuer** per product, stop and redesign.

---

## 9. Shop In A Box / multi-tenant

Today ARK is single-shop. The identity contract assumes **tenant isolation** without forking issuers per shop.

**Model (target):**

```
Issuer: app.arksms.com (or per-environment operational host)
Claim: shop_id (or shop_slug)
Membership: user belongs to 1..n shops; token includes active shop context
```

| Question | Answer |
|----------|--------|
| Can `shop1.arksms.com` and `shop2.arksms.com` share one issuer? | **Yes** — same issuer; `shop_id` claim + app routing |
| Is user data isolated per shop? | **Yes** — operations data already shop-scoped; identity carries membership |
| Can one human work at two shops? | **Yes** — multiple memberships; active shop selected at login or host-derived |
| Does BookStack get one instance per shop? | **Phase 1b:** single Demo Auto Repair instance. **Future:** shop-scoped shelves or instance-per-shop — projection still from same ARK user |

**Phase 1b scope:** Implement issuer with `shop_id` claim **reserved** (constant for Demo Auto Repair). Do not build full multi-shop switching UI until Shop In A Box demands it.

---

## 10. ARK V2 native session vs OIDC

ARK V2 staff login **remains Breeze** on `app.*` in Phase 1b. OIDC issuer sits **alongside** existing auth — same `users` table, same password verification inside issuer endpoints.

BookStack does not redirect through V2 session cookie; it uses standard OIDC. Optional UX: if user already has ARK session, issuer authorize step is silent (SSO within ecosystem).

**Do not** replace Breeze with BookStack login or merge into one cookie domain in Phase 1b.

---

## 11. Non-goals (Phase 1b)

- Customer portal OIDC
- MFA (document hook only)
- BookStack API service accounts via SSO
- SCIM provisioning
- Syncing BookStack content permissions from ARK permissions matrix
- Replacing embedded Blade ARKademy (`/app/learn`) — separate migration phase
- Authentik/Keycloak deployment

---

## 12. Acceptance checklist

- [x] This contract accepted (2026-06-14)
- [x] Role vs product access defined as separate axes (§2.1)
- [ ] Issuer host confirmed (`app.demo-auto.test` vs dedicated `auth.*`) — see design pass
- [ ] BookStack admin password rotated; local accounts documented as break-glass only
- [x] Role → BookStack role mapping table agreed (`admin` / `advisor` / `technician`)
- [x] Deactivation behavior agreed (issuer deny sufficient for 1b)
- [ ] Logout behavior documented (V2 logout vs BookStack session) — see design pass
- [x] `shop_id` claim reserved in token schema
- [x] `products` claim reserved in token schema
- [ ] OIDC design pass accepted — **before issuer code**
- [x] OIDC design pass accepted (2026-06-14) — Phase 1b.0 spike authorized

---

## 13. Implementation phases (after acceptance)

```
1b.0 — Issuer skeleton (discovery, JWKS, single test client)
1b.1 — BookStack OIDC client + auto-register + group sync
1b.2 — Break-glass local admin + runbook
1b.3 — Silent SSO when ARK session present (optional polish)
```

Each sub-phase ships with tests and runbook updates. No content migration or training gate changes in 1b.

---

## 14. Related documents

| Document | Relationship |
|----------|--------------|
| `docs/arkademy/bookstack-foundation-plan.md` | Infrastructure + SSO appendix |
| `docs/branding/ownership.md` | Operational vs public host branding |
| `docs/branding/ecosystem-identity.md` | Presentation layer consistency |
| `docs/identity/oidc-design-pass.md` | Implementation approach — **next gate before code** |
| doctrine `ark-surfaces.md` | Staff vs portal surface separation |

---

## 15. Summary sentence

**ARK owns the user; products receive a projection through OIDC; BookStack must never become a peer identity store.**

That sentence is the Phase 1b gate.
