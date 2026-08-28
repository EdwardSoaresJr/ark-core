# ARK OIDC Spike Runbook (Phase 1b.0 — staging only)

**Status:** Staging spike — **no production tokens** until go/no-go.  
**Contract:** `docs/identity/identity-authority-contract.md` (v1.1 accepted)  
**Design:** `docs/identity/oidc-design-pass.md` (v1.1 accepted)

---

## What the spike proves

1. ARK issues OIDC-compatible staff auth  
2. `sub` = `users.id` (immutable)  
3. BookStack login via OIDC  
4. Product gate: `arkademy` required  
5. Closed claims only  
6. JWKS endpoint  
7. JWKS rotation in staging  
8. BookStack user auto-provision on first login  
9. Inactive / unauthorized users denied before token issuance  

Automated proof: `php artisan test --filter=OidcSpike`

---

## Staging enable (ARK V2)

On **staging only** — do not set on production until go/no-go:

```env
OIDC_ENABLED=true
OIDC_ISSUER=https://app.demo-auto.test
OIDC_SHOP_ID=1
```

```bash
php artisan migrate
php artisan ark:oidc:keys:create
php artisan ark:oidc:spike:seed
```

Store the emitted `client_secret` in BookStack env only — not in git.

---

## BookStack staging config

Point BookStack at ARK issuer (staging host):

```env
APP_URL=https://learn.demo-auto.test
AUTH_METHOD=oidc
AUTH_AUTO_INITIATE=true
OIDC_ISSUER=https://app.demo-auto.test
OIDC_CLIENT_ID=arkademy
OIDC_CLIENT_SECRET=<from ark:oidc:spike:seed>
OIDC_USER_TO_GROUPS=true
OIDC_GROUPS_CLAIM=groups
OIDC_EXTERNAL_ID_CLAIM=sub
OIDC_ISSUER_DISCOVER=true
```

**Role mapping (BookStack admin → Roles → External Authentication IDs):**

| ARK `groups` | BookStack role |
|--------------|----------------|
| admin | Admin |
| advisor | Editor |
| technician | Viewer |

Disable BookStack registration. Keep one break-glass local admin.

---

## Verification checklist (staging)

- [ ] Discovery: `curl https://app.demo-auto.test/.well-known/openid-configuration`
- [ ] JWKS: `curl https://app.demo-auto.test/.well-known/jwks.json`
- [ ] Staff user with `arkademy` product → BookStack login succeeds
- [ ] First login creates BookStack user with `external_auth_id` = ARK user id
- [ ] User without `arkademy` → `access_denied` at authorize
- [ ] Inactive ARK user → denied
- [ ] Email change in ARK → same BookStack user (same `sub`)
- [ ] `ark:oidc:keys:rotate` → JWKS shows overlap → BookStack still accepts tokens
- [ ] PHPUnit `OidcSpikeTest` green in CI

---

## JWKS rotation (staging drill)

```bash
php artisan ark:oidc:keys:rotate
# verify JWKS has old + new kid
# login to BookStack still works
php artisan ark:oidc:keys:revoke {old-kid}
```

---

## Production gate

Do **not** enable `OIDC_ENABLED` on production until:

- Staging BookStack OIDC login verified end-to-end  
- Spike go/no-go recorded  
- BookStack admin password rotated / break-glass documented  
- Production `client_secret` rotated from spike value  

---

## Phase 1b.0 validation hardening (complete)

Validation evidence: `docs/identity/oidc-validation-evidence.md`

**Code fixes (deployed via git):**

- HTTP Basic client auth on `/oauth/token` (BookStack RFC 6749 §2.3.1)
- JWKS skips DB rows with missing PEM files
- OIDC key directory permissions for php-fpm after CLI key creation

**Infrastructure:**

- Signing keys persist on existing `storage/app` bind mount (`/data/ark-shared/storage/app/private/oidc/keys/`)
- BookStack compose joins external `coolify` network (MySQL DNS after recreate)
- Rotate `OIDC_CLIENT_SECRET` after validation; store in BookStack env only

**Re-verify after deploy:**

```bash
php artisan test --filter=OidcSpike   # 9 tests
# Five manual validations — see oidc-validation-evidence.md
```

---

## Implementation note

Phase 1b.0 uses a **first-party minimal issuer** in `app/Ark/Runtime/Identity/Oidc/` (no Passport dependency in spike). Transport may change after go/no-go; **invariants are contract-bound**, not package-bound.
