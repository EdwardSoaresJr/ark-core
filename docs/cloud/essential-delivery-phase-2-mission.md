# Essential Delivery — Phase 2 Mission (Core)

**Status:** ESSENTIAL DELIVERY PHASE 2: COMPLETE ✅  
**Prerequisite:** [Phase 1 COMPLETE](./essential-delivery-phase-1-mission.md)  
**Companion (Cloud):** `ark-cloud/docs/essential-delivery-phase-2-mission.md`  
**Shipped:** Core `7ee9009` (demo only) · Cloud `0bfb48f` · LugsNPlugs untouched  
**Offline accepted:** 2026-09-03 · air-gapped Demo Box · carried `ARK2` · local reset + login + replay reject

## Invariants (locked)

> **An offline ARK Box must be recoverable without Cloud connecting to the Box and without the Box connecting to Cloud.**

Phase 1 invariants still apply:

> **Recovery is installation-scoped essential infrastructure. Pairing is an optional shop relationship.**

> **ARK Mail is a product. Essential Delivery is infrastructure. Never use ARK Mail entitlement to authorize account recovery.**

## Core role in Phase 2

Phase 1: Box reaches Cloud → Cloud delivers a code → Box validates locally.

Phase 2: Box **cannot** reach Cloud. The Box:

1. Generates and displays an installation-bound recovery challenge.
2. Accepts a signed authorization the user carries back from Cloud (via phone/browser).
3. Verifies the authorization locally against an **embedded Cloud public trust key**.
4. Permits staff password reset only after verification succeeds.

The Box owns the user, challenge, verification, and password mutation. Cloud never touches the account.

## What Core must not do on the offline path

- Call Cloud during offline recovery
- Accept Cloud inbound connections
- Require pairing, ARK Mail entitlement, or a “connect briefly to fix it” step
- Send passwords or password hashes to Cloud

## What crosses the air gap

Only the signed authorization artifact — installation-bound, challenge-bound, purpose-bound, expiring. No password.

## Relationship to Phase 1

| Condition | Path |
| --- | --- |
| Box can reach Cloud | Phase 1 — Essential Delivery email code |
| Box cannot reach Cloud | Phase 2 — offline challenge + carried authorization |

Both paths mutate the password on-box. Both use the installation recovery identity registered at setup (Phase 1 essential registration).

## Acceptance (Core side)

Same stop condition as Cloud mission doc — offline Box, carried authorization, local verify, reset, login, zero Mail entitlement, no pairing.

## Implementation

Core displays a fresh challenge, accepts a Cloud-signed `ARK2` authorization, verifies it against the embedded public key, mutates the recovery-owner password locally, and consumes the challenge (replay fails).

## Phase 2 offline acceptance closeout (2026-09-03)

| Item | Evidence |
| --- | --- |
| Core under test | Demo @ `7ee9009` · public key `AJJroSsitWeaIjsqIaK30jDB8Y7ifkbwfNoEBuRstu8` |
| Isolation | Box OUTPUT DROP to Cloud IP before challenge; held through reset, login, replay |
| Challenge | Issued on `/app/offline-recovery` while Cloud unreachable from Box |
| Authorization | Cloud-signed `ARK2…` carried manually; verified locally only |
| Reset + login | Password mutated on-box; `/app/today` authenticated while still isolated |
| Replay | Rejected after consume (`consumed_jti`) |
| Negatives | No Box→Cloud / Cloud→Box path used; pairing not required; Mail entitlements = 0; no password in artifact |
| LugsNPlugs | Untouched |

**ESSENTIAL DELIVERY PHASE 2: COMPLETE ✅**

## Explicitly out of scope

- Phase 1 changes
- Laravel reset tokens / token URL flows
- ARK Mail as recovery gate
- Customer portal recovery
- Phase 3 emergency codes
