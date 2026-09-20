# Essential Delivery — Phase 1 Mission (Core)

**Status:** PHASE 1 COMPLETE ✅  
**Companion (Cloud):** `ark-cloud/docs/essential-delivery-phase-1-mission.md`  
**Shipped:** Core `44db4f9` on `demo.autorepairkeeper.com` only  
**Transport accepted:** 2026-09-03 · demo paired Box · Postmark delivery to `esoares9483@gmail.com`

## Invariants (locked)

> **Recovery is installation-scoped essential infrastructure. Pairing is an optional shop relationship.**

> **ARK Mail is a product. Essential Delivery is infrastructure. Never use ARK Mail entitlement to authorize account recovery.**

## Phase 1 scope

Prove staff password recovery for the installation recovery owner — **not** every staff account.

### Two acceptance paths (both required)

1. **Paired Demo Box** — signs Cloud requests with pairing credential.
2. **Stock unpaired install** — setup writes recovery owner + essential secret; signs with bootstrap secret.

Same recovery mechanics, same Cloud template, same local password mutation.

### Core authority

| Piece | Role |
| --- | --- |
| `RecoveryOwnerIdentity` | Setup admin email (`storage/app/install/recovery_owner_email`) |
| `EssentialDeliverySecret` | Bootstrap HMAC secret for unpaired installs |
| `StaffRecoveryChallenge` | Local hashed 6-digit code, expiry, attempts |
| `StaffRecoveryService` | Create challenge → call Cloud → verify locally → reset password |
| `EssentialDeliveryClient` | Register at install; deliver code; sync recovery owner |

Forgot-password only succeeds for the recovery owner email. Generic response prevents enumeration.

### Credential resolution

- Cloud paired → `CloudConnection` credential
- Otherwise → `EssentialDeliverySecret` from install

Pairing is optional. Recovery is not.

### Explicitly out of scope

- Laravel `ResetPassword` notification
- Token URL reset links
- ARK Mail activation as recovery gate
- Customer portal recovery
- Phase 2+ offline portal

## Acceptance

### Path A — Paired Demo Box

Recovery owner falls back to master admin email if install file missing (legacy demo).

### Path B — Stock unpaired install

`CompleteInstallationAction` writes recovery identity and calls essential register.

## Tests

```bash
./vendor/bin/pest tests/Feature/Auth/StaffRecoveryTest.php tests/Feature/Auth/PasswordResetTest.php
./vendor/bin/pest tests/Feature/Install/EssentialDeliveryInstallRegistrationTest.php
```

## Phase 1 closeout (2026-09-03)

**Functional:** Path B proved the architecture — fresh unpaired install registers Essential Delivery during setup (`44db4f9`), recovers locally, never touches ARK Mail or shop pairing. Path A proved the paired credential path on the Demo Box.

**Transport:** Demo Box forgot-password → Cloud Essential Delivery → Postmark sent `recovery@mail.arksms.com` → code entered on Core → reset → login. Zero ARK Mail entitlement.

**Next:** [essential-delivery-phase-2-mission.md](./essential-delivery-phase-2-mission.md) — offline challenge/response recovery without Cloud↔Box network access.
