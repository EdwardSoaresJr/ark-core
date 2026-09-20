# OIDC Staging Validation Evidence (Phase 1b.0)

**Date:** 2026-06-14  
**Issuer:** `https://app.demo-auto.test` (`OIDC_ENABLED=true` — validation only)  
**Relying party:** `https://learn.demo-auto.test` (BookStack / ARKademy)  
**Contract:** `identity-authority-contract.md` v1.1 · **Design:** `oidc-design-pass.md` v1.1

---

## Verdict

| # | Scenario | Result |
|---|----------|--------|
| 1 | Happy path — Ben → OIDC → BookStack auto-provision | **PASS** |
| 2 | Product gate — technician without `arkademy` | **PASS** |
| 3 | Subject stability — email change, same `sub` | **PASS** |
| 4 | JWKS rotation — rotate, overlap, revoke, login works | **PASS** |
| 5 | Deactivation — inactive user denied at authorize | **PASS** |

**Go/no-go:** All five validations passed on the validation host. Identity is no longer a design exercise — production rollout is an operational decision, not an architecture question.

---

## Validation 1 — Happy Path (projection-on-login)

**Actor:** Benjamin Burling (`users.id=4`, roles: admin, advisor)

**Flow proven:**

```
BookStack POST /oidc/login
  → ARK GET /oauth/authorize (PKCE, staff session simulated as Ben)
  → BookStack GET /oidc/callback?code=…
  → ARK POST /oauth/token (HTTP Basic client auth — BookStack pattern)
  → BookStack user auto-created
```

**Evidence:**

```sql
-- BookStack MySQL after first OIDC login
SELECT id, name, email, external_auth_id FROM users WHERE external_auth_id = '4';
-- id=3  name=Benjamin Burling  email=benjamin@demo-auto.test  external_auth_id=4
```

**Token claims (issuer):** `sub=4`, `groups=["admin","advisor"]`, `products=["ark_v2","arkademy"]`

**Public endpoints:**

- `GET /.well-known/openid-configuration` → 200
- `GET /.well-known/jwks.json` → 200

---

## Validation 2 — Product Gate (identity ≠ authorization)

**Actor:** Landon Carter (`users.id=6`, technician) with explicit deny override:

```sql
user_product_access: user_id=6, product_slug=arkademy, granted=0
```

**Result:** `GET /oauth/authorize` → redirect to callback with `error=access_denied`  
**No token issued.** Authentication would succeed; authorization blocked before code exchange.

---

## Validation 3 — Subject Stability

**Invariant:** `sub` = `users.id` forever.

| Step | Email | `sub` in id_token |
|------|-------|-------------------|
| Login 1 | `benjamin@demo-auto.test` | `4` |
| Email changed | `benjamin+oidc-val@demo-auto.test` | `4` |
| Restored | `benjamin@demo-auto.test` | — |

**Result:** **PASS** — same BookStack row (`external_auth_id=4`) would match on next login.

---

## Validation 4 — JWKS Rotation

**Drill:**

1. `ark:oidc:keys:rotate` — JWKS published **both** old and new `kid`
2. Token exchange via HTTP Basic — **PASS**
3. `ark:oidc:keys:revoke {old-kid}` — old key removed from JWKS
4. Token exchange again — **PASS**

**Note:** CLI-created keys default to `root:www-data` dir mode `2700`; PHP-FPM (`www-data`) could not read keys until `g+rx` applied. Fix committed in `OidcKeyRepository::ensureKeyStoragePermissions()`.

---

## Validation 5 — Deactivation

**Action:** `User::forceFill(['is_active' => false])` on Ben (mass-assignment guard requires `forceFill`)

**Result:** `GET /oauth/authorize` → `error=inactive_user`  
**No BookStack cleanup. No BookStack admin action. ARK remains authority.**

---

## Blockers found during validation (fixed)

| Blocker | Root cause | Fix |
|---------|------------|-----|
| JWKS HTTP 500 | Orphan DB keys + missing PEM files; then dir permissions | Skip missing PEMs in JWKS; chmod keys dir for `www-data` |
| Token HTTP 500 / HTML to BookStack | BookStack sends `client_id` + `client_secret` via **HTTP Basic only** (RFC 6749 §2.3.1); validation ran before merging Basic credentials | Merge Basic auth into request before validation |
| BookStack "Expected JSON" | Same as above — validation exception returned HTML redirect | Same fix |
| BookStack 503 after recreate | Container not on `coolify` network (MySQL DNS) | `docker network connect coolify …` after recreate |

**Local commits pending deploy:** ~~hotpatched~~ — deployed in `cf4f02c`.

---

## Phase 1b.0 hardening (2026-06-14)

| Step | Status |
|------|--------|
| Commit validation fixes | **Done** — `cf4f02c` |
| Deploy via Coolify | **Done** — `resolveClientCredentials` in running container |
| OIDC keys on bind mount | **Done** — `/data/ark-shared/storage/app/private/oidc/keys/` |
| BookStack `coolify` network | **Done** — compose + external network on production |
| Client secret rotated post-validation | **Done** — BookStack `.env` only (not in git) |
| Re-run five validations | **Done** — V1–V5 PASS (2026-06-14 post-deploy) |
| PHPUnit `OidcSpikeTest` | **Done** — 9/9 local |

**Phase 1b.0:** **Complete.** Repo matches production; no hotpatch drift.

**Coolify persistence note:** Add `OIDC_ENABLED`, `OIDC_ISSUER`, `OIDC_SHOP_ID` as production environment variables in the Coolify UI (not only hand-edited `.env`), or they are dropped on the next git-triggered redeploy.

---

## Operational gaps before production cutover

1. **OIDC PEM persistence** — keys live in container ephemeral storage; bind mount required (e.g. `/data/ark-shared/oidc-keys`) before production rollout.
2. **BookStack `coolify` network** — compose should include external `coolify` network so MySQL DNS survives recreate (manual reconnect was required twice during validation).
3. **Client secret rotation** — spike secret was re-seeded during validation; production BookStack env must be updated on deploy; rotate away from validation secret.
4. **`user_product_access` override for Landon** — validation row left in place (`granted=0` for arkademy on user 6); remove or keep intentionally for future gate tests.

---

## Automated proof (local)

```
./vendor/bin/pest tests/Feature/Identity/OidcSpikeTest.php
# 9 passed (includes HTTP Basic client auth test)
```

---

## Recommendation

**Proceed to production rollout decision.** Do not add identity features until:

1. Hotfix commits deployed via Coolify (not docker cp)
2. OIDC key bind mount configured
3. BookStack network persistence patched
4. Production client secret rotated and stored in BookStack env only

Then: enable `OIDC_ENABLED=true` on production app, verify Ben browser login once, record go/no-go, rotate break-glass BookStack admin password.
