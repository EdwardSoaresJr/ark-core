# First Contact — Engineering Report

**Milestone:** Milestone 1 — First Contact  
**Date:** _fill after certification_  
**Device:** Poly VVX350  
**Shop:** Demo Auto Repair  

> Certification record — not a debug log. Name failed **gates** (G1–G7), not subsystems.

---

## Environment

_Not success or failure — the lab snapshot for future comparison (Yealink, Fanvil, etc.)._

| Field | Value |
|-------|-------|
| Phone model | |
| Firmware | |
| Provisioning URL | |
| Asterisk version | |
| Commit | |
| Deployment | |

---

## Certification result

| Gate | Question | Expected | Pass? | Timestamp |
|------|----------|----------|-------|-----------|
| G1 | Production healthy? | `/up` = 200 | Session 2 ✓ | |
| G2 | Schema current? | Migrations + seeder | Presumed ✓ (verify SSH) | |
| G3 | Provisioning alive? | Unknown MAC = 404 | Session 2 ✓ | |
| G4 | Gates work? | 404 → 403 → 200 | | |
| G5 | Phone consumes config? | Config applied | | |
| G6 | SIP registration? | PJSIP registered | | |
| G7 | ARK observes reality? | AMI → Connected | | |

**Failed gate (if any):** G___ — _describe observation, not hypothesis_

---

## Timeline

_Capture timestamps at every gate. This becomes the performance baseline._

```
__:__:__  G1 —
__:__:__  G2 —
__:__:__  G3 —
__:__:__  G4 —
__:__:__  G5 —
__:__:__  G6 —
__:__:__  G7 —
```

Example reference:

```
09:14:02  G5 — Phone boots
09:14:07  G4 — GET /provision/48256730757F.cfg → 200, projection REUSED
09:14:11  G5 — Phone applies config
09:14:17  G6 — SIP REGISTER / AMI Registered
09:14:18  G7 — Connected
```

---

## Hardware

_Serial, MAC, PoE switch/port, bench location._

---

## Firmware

_Version before/after factory reset. Poly ZTP disabled?_

---

## Network

_DHCP, VLAN, DNS, provisioning server URL on phone._

---

## Provision request (G4 / G5)

_First real MAC request — HTTP status, `endpoint.provision.request` log line._

---

## Projection

_Fingerprint, REUSED vs REGENERATED, admin preview vs phone-received body._

---

## Registration (G6)

_Extension, host, credential source, Asterisk evidence._

---

## AMI (G6 / G7)

_Bridge event, registration webhook._

---

## Outcome

- [ ] **G7 — Connected** in ARK Shop → Communications
- [ ] Or: certification stopped at gate G___

---

## Lessons learned

_What the system proved. What a gate taught us. What becomes a regression test._

---

**Pattern:** Doctrine → Authority → Projection → Observability → Reality

---

## Session 1

**Observation:** Valid unknown MAC → **500**. Certification stopped correctly before bench.

**Open question:** Why can't the provisioning handler produce NOT_FOUND for a valid unknown MAC?

**Status:** Superseded by Session 2 observation — not rewritten.

---

## Session 2

**G1 PASS** — `GET /up` → 200

**G3 PASS** — `GET /provision/AABBCCDDEEFF.cfg` → **404**

**Observed:** Production now behaves as expected for an unknown valid MAC.

**Conclusion:** The earlier HTTP 500 is no longer reproducible and was most likely caused by production deployment state (migrations and/or deployment mismatch). The original hypothesis is considered **resolved by observation**, not by assumption.

**G2:** Presumed satisfied by current production behavior; verify via SSH when available (migrations + `CommunicationDeviceModelSeeder`).

**Frontier:** G4 next → G5 → G6 → G7.
