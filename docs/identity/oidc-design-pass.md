# ARK OIDC Design Pass

**Status:** **Accepted** — frozen 2026-06-14. Phase 1b.0 staging spike authorized. No production tokens until spike go/no-go.  
**Version:** 1.1 — 2026-06-14  
**Inputs:** `docs/identity/identity-authority-contract.md` (accepted v1.1)  
**Sequence:** Contract → **Design pass** → Spike (1b.0) → Implementation

---

## 1. Purpose

The Identity Authority Contract defines **who owns what**. This document selects **how** ARK becomes an OIDC issuer without establishing the wrong foundation.

**Central question:**

> Can ARK become the identity authority for the entire ecosystem **without introducing a second authority**?

**Answer: Yes** — if these hold:

| Requirement | How ARK satisfies it |
|-------------|----------------------|
| Single user directory for staff | `users` table — no Authentik/Keycloak directory |
| Single customer directory (future) | `customers` table — separate guard, same issuer pattern |
| Products project on login | OIDC authorize + token; no sync jobs |
| Issuer replaceable transport | Passport or first-party code is **implementation**; claims and `sub` stability are **contract** |
| No external IdP as source of truth | Rejected: Authentik, Keycloak, BookStack-as-directory |

ARK becomes **Identity Authority** the same way it is **Operations Authority** and **Knowledge Registry Authority** — one truth layer, many projections.

Once tokens are issued, the ecosystem inherits claim shapes, client model, JWKS lifecycle, and authorize gates. One design pass now avoids `Problem → OIDC package → Regret`.

---

## 2. Five design invariants (what matters — not Passport)

Passport, a first-party issuer, or any OAuth library is **replaceable transport**. These five invariants are **not**.

### 2.1 Subject stability

| Rule | Decision |
|------|----------|
| `sub` value | `(string) users.id` for Phase 1b |
| Immutability | **Never** changes for the life of the identity |
| Forbidden | Email, username, phone, display name |
| Future | Optional `users.uuid` set at create if PK remapping ever needed — still never email |
| Consumer rule | BookStack `external_auth_id` = `sub` forever; email updates do not re-link |

**Test:** Change user email in ARK → same `sub` → same BookStack user row.

### 2.2 Client independence

Clients receive a **closed claim surface**. They never learn how ARK stores users.

**Allowed claims (staff clients):**

```json
{
  "sub": "42",
  "email": "ben@demo-auto.test",
  "name": "Ben",
  "groups": ["advisor"],
  "products": ["ark_v2", "arkademy"],
  "shop_id": "1",
  "email_verified": true
}
```

**Forbidden in tokens:** internal ARK ids as extra claims, permission names, shop_settings keys, database hints, Partstech fields, hashed passwords, session ids.

BookStack maps `groups` → BookStack roles. It does not need — and must not receive — ARK schema knowledge.

### 2.3 Product claim authority

Product access may become **more important than roles** for ecosystem provisioning.

```
Authorize request (client_id = arkademy)
    ↓
Resolve user.products (defaults + overrides)
    ↓
client.required_product ∈ user.products ?
    ↓ no → access_denied (no token, no projection)
    ↓ yes
Issue ID token with products claim
    ↓
Client auto-provisions on first login
```

**Future Shop In A Box:** grant `ark_v2` + `arkademy` to a new shop employee without role gymnastics — product slugs are the provisioning vocabulary.

**Examples (role ≠ product access):**

| Person | Roles (`groups`) | Products |
|--------|------------------|----------|
| Customer | — (customer guard, not staff) | `portal` |
| Technician | `technician` | `ark_v2`, `arkademy` |
| Technician (restricted) | `technician` | `ark_v2` only |
| Marketing contractor | — | `ark_web_admin` |
| Owner | `admin` | all staff products |

Authorize gate uses **products**, not **groups**, to admit a client.

### 2.4 JWKS lifecycle (required before production issuance)

Key management must be defined **before** the first production token — not retrofitted.

| Phase | Behavior | Command / trigger |
|-------|----------|-------------------|
| **Creation** | Generate RS256 keypair; assign `kid`; store private key outside git; publish public JWK | `ark:oidc:keys:create` (initial deploy) |
| **Rotation** | Generate new keypair with new `kid`; publish **both** keys in JWKS; sign new tokens with new key only; overlap ≥ 24h | `ark:oidc:keys:rotate` |
| **Revocation** | Remove retired public key from JWKS after overlap; reject tokens signed with revoked `kid` after `exp`; audit log entry | `ark:oidc:keys:revoke {kid}` |

**Storage:** `storage/app/oidc/keys/{kid}.pem` (private) + `oidc_signing_keys` table (metadata: kid, created_at, revoked_at, active).

**Discovery:** JWKS URI in OpenID configuration; BookStack fetches via `OIDC_ISSUER_DISCOVER=true`.

**Production gate:** No production `client_secret` issuance until Creation + Rotation + Revocation commands exist and rotation test passes in staging.

Passport path: wrap Passport keys with this lifecycle. First-party path: `OidcKeyRepository` owns all three phases.

### 2.5 Staff identity vs customer identity

| | Staff | Customer |
|---|-------|----------|
| **Directory** | `users` | `customers` |
| **OIDC clients** | `arkademy`, future `ark_web_admin` | future `portal` |
| **`sub` namespace** | staff user id | customer id (separate — never collide) |
| **`groups` claim** | Spatie staff roles | absent or customer-specific |
| **`products` claim** | `ark_v2`, `arkademy`, … | `portal` |
| **Phase 1b** | In scope | **Explicitly out of scope** |
| **Design now** | Full issuer | Reserve client ids + product slugs only |

**Forbidden forever:** one merged “mega-user” table; customer Spatie staff roles; staff portal login via `customers` without explicit product model.

Customer OIDC can wait years. The issuer architecture must not assume all subjects are staff.

---

## 3. Contract review (constraints)

Non-negotiables from the accepted contract:

| Constraint | Implication for design |
|------------|------------------------|
| ARK owns user; products project via OIDC | Issuer lives in ARKv2; no external IdP as directory |
| Projection on login, not sync | No SCIM, no nightly user sync to BookStack |
| Role ≠ product access | Authorize checks `products`; `groups` is not the gate |
| `sub` immutable | `users.id` only — see §2.1 |
| Closed claim surface | See §2.2 |
| `shop_id` reserved | Constant for Demo Auto Repair in 1b |
| One issuer, many clients | Client registry from day one |
| Staff ≠ customer directories | Separate clients and `sub` namespaces — see §2.5 |
| Breeze stays on ARK V2 | Issuer reuses same `users` + password |
| BookStack first consumer | Auth code + PKCE, `groups`, external id |

**Rejected:** Authentik, Keycloak, BookStack-as-issuer, shared DB auth, email-as-`sub`.

---

## 4. Issuer host

| Option | Recommendation |
|--------|----------------|
| **`https://app.demo-auto.test`** | **Phase 1b** — existing staff session, operational host |
| `https://auth.demo-auto.test` | Defer |
| Per-shop issuers | Reject — violates single authority |

Routes: `/oauth/*`, discovery at `/.well-known/openid-configuration`.  
BookStack: `OIDC_ISSUER=https://app.demo-auto.test`.

---

## 5. Implementation transport (replaceable)

> **Passport can be replaced.** The five invariants in §2 cannot.

### 5.1 Laravel Passport + OIDC bridge

OAuth2 core (clients, codes, PKCE). OIDC via bridge package. **Evaluate in 1b.0 spike only.**

Must support: custom claims (`groups`, `products`, `shop_id`), authorize hook for product gate, JWKS with `kid`, immutable `sub` override.

### 5.2 First-party minimal issuer

Fallback: `app/Ark/Runtime/Identity/Oidc/` — discovery, authorize, token, userinfo, JWKS, key repository.

### 5.3 Authentik / Keycloak

**Rejected** — second authority to operate; inverts ARK ownership.

### 5.4 Recommendation

**Spike Passport + bridge first.** If bridge fails invariant checklist → first-party issuer.  
**Spike success criterion:** BookStack login with stable `sub`, product gate, JWKS rotation test — not “package installed.”

---

## 6. Authorize pipeline (canonical)

```
1. Validate client_id, redirect_uri, PKCE
2. Authenticate staff user (Breeze session or login form)
3. Deny if user inactive
4. Resolve user.products
5. Deny if client.required_product ∉ user.products
6. Deny if shop membership invalid (future; pass-through 1b)
7. Issue authorization code
8. Token endpoint: ID token with closed claim set (§2.2)
9. Client projects user on first login
```

**Product gate is step 5 — before any token exists.**

---

## 7. Claims model

### 7.1 ID token (staff clients)

| Claim | Required | Source |
|-------|----------|--------|
| `iss` | yes | `https://app.demo-auto.test` |
| `sub` | yes | `(string) users.id` — immutable |
| `aud` | yes | client_id |
| `exp`, `iat`, `auth_time` | yes | standard |
| `email`, `email_verified`, `name` | yes | profile |
| `groups` | yes | Spatie role names |
| `products` | yes | resolved product access |
| `shop_id` | yes | `"1"` (Demo Auto Repair) in 1b |

No other claims without contract amendment.

### 7.2 Userinfo

Same closed surface as ID token profile claims.

### 7.3 Product access resolution (Phase 1b)

```
defaults = role_to_products(user.roles)
overrides = user_product_access(user.id)
products = merge(defaults, overrides)
```

| Role default | Products |
|--------------|----------|
| admin | ark_v2, arkademy |
| advisor | ark_v2, arkademy |
| technician | ark_v2, arkademy |

Per-user overrides win (e.g. technician → `ark_v2` only).

### 7.4 BookStack role mapping

| `groups` | BookStack External Auth ID |
|----------|----------------------------|
| admin | Admin |
| advisor | Editor |
| technician | Viewer |

---

## 8. Client registration model

| client_id | required_product | Redirect URI | Phase |
|-----------|------------------|--------------|-------|
| `arkademy` | arkademy | `https://learn.demo-auto.test/oidc/callback` | 1b |
| `ark-portal` | portal | TBD | future |
| `ark-web-admin` | ark_web_admin | TBD | future |

One secret per client; redirect URI exact match; staff and customer clients never share secrets.

---

## 9. JWKS operations (detail)

### 9.1 Creation (deploy prerequisite)

1. Run `ark:oidc:keys:create`
2. Write private PEM to secure storage
3. Insert metadata row; mark active
4. Publish JWKS with single key
5. Record `kid` in runbook

### 9.2 Rotation (scheduled or incident)

1. Run `ark:oidc:keys:rotate` → new `kid`
2. JWKS returns old + new public keys
3. New tokens signed with new key only
4. Wait ≥ 24h (max existing token TTL)
5. Revoke old `kid`

### 9.3 Revocation (compromise or decommission)

1. Run `ark:oidc:keys:revoke {kid}`
2. Remove from JWKS immediately
3. Tokens with that `kid` fail validation after expiry
4. Rotate if active signing key compromised

**Staging gate:** rotate once before production cutover; verify BookStack still accepts tokens during overlap.

---

## 10. BookStack integration (first consumer)

```
learn.demo-auto.test → authorize on app.demo-auto.test
→ product gate (arkademy)
→ code → token → claims: sub, email, name, groups, products, shop_id
→ BookStack upsert external_auth_id = sub
```

**BookStack env:**

```
AUTH_METHOD=oidc
AUTH_AUTO_INITIATE=true
OIDC_ISSUER=https://app.demo-auto.test
OIDC_CLIENT_ID=arkademy
OIDC_CLIENT_SECRET=<rotatable>
OIDC_USER_TO_GROUPS=true
OIDC_GROUPS_CLAIM=groups
OIDC_EXTERNAL_ID_CLAIM=sub
OIDC_ISSUER_DISCOVER=true
```

BookStack never reads ARK database. Verify against BookStack v26.x during spike.

---

## 11. Logout (Phase 1b limitation)

| Action | 1b behavior |
|--------|-------------|
| Logout ARK V2 | ARK session cleared; BookStack session may persist |
| Logout BookStack | BookStack only |

`end_session_endpoint` deferred to 1b.3 if UX requires.

---

## 12. Planned schema (not migrated yet)

| Addition | Purpose |
|----------|---------|
| OAuth / OIDC client tables | Client registry |
| `oidc_signing_keys` | Key lifecycle metadata |
| `user_product_access` | Per-user product overrides |
| `users.deactivated_at` | Issuer deny |
| Product slug enum | `ark_v2`, `arkademy`, `portal`, `ark_web_admin` |

Optional later: `users.uuid` for immutable `sub` decoupled from PK.

---

## 13. Testing (before production)

| Test | Validates |
|------|-----------|
| `sub` stable across email change | §2.1 |
| Token contains only closed claims | §2.2 |
| Missing product → authorize denied | §2.3 |
| JWKS rotation overlap | §2.4 |
| Staff client rejects customer user | §2.5 |
| BookStack first login creates projection | Contract |
| Deactivated user denied | Contract |

---

## 14. Implementation phases

```
1b.0 — Staging spike (go/no-go)
  - Bridge or first-party prototype
  - BookStack one login
  - JWKS rotation test
  - Product gate test
  - NO production tokens

1b.1 — Issuer core + key lifecycle commands
1b.2 — BookStack production cutover
1b.3 — Silent SSO + optional logout endpoint
```

---

## 15. Spike checklist (1b.0)

Transport (Passport bridge if used):

- [ ] Laravel 13 + PHP 8.4 compatible
- [ ] Custom claims: groups, products, shop_id
- [ ] Authorize middleware for product gate
- [ ] Immutable `sub` = users.id
- [ ] JWKS with kid + rotation support

Invariant proof (required regardless of transport):

- [ ] Email change → same sub → same BookStack user
- [ ] User without arkademy product → access_denied
- [ ] Token claim set matches §2.2 exactly
- [ ] Rotate keys; BookStack accepts during overlap
- [ ] Customer user cannot authorize staff client

If any invariant fails → fix design or switch transport. **Do not ship production.**

---

## 16. Acceptance checklist (this document)

- [x] Single authority answer (§1) accepted (2026-06-14)
- [x] Subject stability (§2.1) accepted
- [x] Client independence / closed claims (§2.2) accepted
- [x] Product claim authority / authorize gate (§2.3) accepted
- [x] JWKS creation + rotation + revocation before prod (§2.4) accepted
- [x] Staff vs customer separation (§2.5) accepted
- [x] Issuer host `app.demo-auto.test` accepted
- [x] Transport spike-first (Passport or fallback) accepted
- [x] Logout limitation for 1b accepted

**Accepted 2026-06-14.** Phase 1b.0 staging spike authorized. No production issuer until spike go/no-go recorded.

---

## 17. Summary

| Invariant | Decision |
|-----------|----------|
| Authority | ARK only — no Authentik |
| `sub` | `users.id` forever — never email |
| Client claims | Closed set: sub, email, name, groups, products, shop_id |
| Access gate | Product access at authorize — before token |
| Keys | Create → rotate → revoke before production |
| Staff vs customer | Separate directories and clients — portal later |
| Transport | Passport + bridge spike; replaceable |
| Next step | Accept this pass → 1b.0 spike → still no prod tokens until go |

**Gate sentence:** If the spike proves ARK can issue tokens meeting §2 without a second authority, Phase 1b.1 is justified. If not, fix the design — not the package.
