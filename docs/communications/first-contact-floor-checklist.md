# Milestone 1: First Contact — Floor Checklist

**Definition of done:** A factory-reset VVX350 obtains its complete configuration from ARK, registers with Asterisk, and appears as **Connected** without manual phone configuration beyond pointing it at the provisioning server.

**Not in scope for this milestone:** firmware management, claim flow, ARI, dynamic PJSIP, BLF, paging, call routing.

**Posture:** This is a **certification**, not a debug session. After G3 passes, software is no longer under test — the **system** is. If something fails, name the gate (e.g. "Gate G5 failed"), not "provisioning failed."

**Single-variable rule:** One phone, one workstation, one extension, one provisioning server, one known firmware. Change one thing at a time if retrying.

---

## Certification gates

| Gate | Question | Expected | ☐ |
|------|----------|----------|---|
| **G1** | Is production healthy? | `/up` = 200 | |
| **G2** | Is the schema current? | Migrations applied; models seeded | |
| **G3** | Is provisioning alive? | Unknown MAC → **404** (not 500) | |
| **G4** | Do provisioning gates work? | Staged device: **404 → 403 → 200** | |
| **G5** | Does the phone consume the configuration? | Phone accepts config | |
| **G6** | Does SIP registration succeed? | PJSIP registered | |
| **G7** | Does ARK observe reality? | AMI → **Connected** | |

Record **timestamps** at every gate transition. The timeline is the baseline for future performance regressions.

Example:

```
09:14:02  G5 — Phone boots
09:14:07  G4 — GET /provision/48256730757F.cfg → 200, projection REUSED
09:14:11  G5 — Phone applies config
09:14:17  G6 — SIP REGISTER
09:14:17  G6 — AMI Registered
09:14:18  G7 — Connected
```

Full timeline → [first-contact-report.md](first-contact-report.md).

---

## Pre-flight (ARK) — G1 through G4

- [ ] Migrations applied (`communication_device_models`, `endpoint_configuration_projections`, device MAC fields, extension workstation fields)
- [ ] `CommunicationDeviceModelSeeder` run (VVX350 policy present)
- [ ] Workstation created in Shop → Communications
- [ ] Device created with **MAC** and **model** (VVX350)
- [ ] Extension assigned to workstation (Shop → Communications → Assign extension)
- [ ] Device show page shows **Provision URL**, **Current** projection, fingerprint
- [ ] Admin projection preview matches expected Poly `.cfg` (extension, host, no credentials in UI)
- [ ] `ASTERISK_PROVISIONING_HOST` / `telephony.asterisk.provisioning.host` = `voice.demo-auto.test`
- [ ] PJSIP password for extension exists (env map or `telephony_extensions.secret`)

---

## Staged device gate proof (G4, no phone)

MAC `AA:BB:CC:DD:EE:FF` → URL `/provision/AABBCCDDEEFF.cfg`

| Device state in ARK | Expected |
|---------------------|----------|
| No row | 404 |
| Device only (no workstation / extension) | 403 |
| Workstation + extension assigned | 200 + Poly body |

---

## Phone preparation — G5 through G7

- [ ] Factory reset VVX350
- [ ] Disable Poly ZTP / cloud provisioning (if enabled)
- [ ] DHCP provides network + DNS
- [ ] Provisioning server URL points at ARK: `https://app.demo-auto.test/provision/` (Poly custom server)

---

## Observation sequence

Record each step. Structured logs (`endpoint.provision.request`) should align with UI.

| Step | Checkbox | Expected |
|------|----------|----------|
| Phone requests config | [ ] | HTTP GET `/provision/{MAC}.cfg` in app logs |
| Gate | [ ] | `gate: PASS` |
| Projection | [ ] | `REUSED` or `REGENERATED` |
| XML returned | [ ] | 200, `text/plain`, Poly `.cfg` body |
| SIP credentials present | [ ] | `reg.1.auth.userId`, `reg.1.auth.password`, host |
| Phone downloads config | [ ] | Phone UI / packet capture |
| Phone reboots / applies | [ ] | Automatic after provision |
| SIP register | [ ] | Asterisk `pjsip show endpoint` or AMI |
| AMI event received | [ ] | Bridge POST → device registration webhook |
| Connected in ARK | [ ] | Shop → device shows **Connected** |

---

## Troubleshooting map

| Symptom | Check |
|---------|--------|
| 404 on provision | MAC in ARK matches phone (normalized 12 hex) |
| 403 on provision | Device active, workstation assigned, extension on workstation |
| Wrong extension in cfg | Workstation extension authority; projection fingerprint stale? |
| Register fails | Asterisk static PJSIP password vs projection secret |
| ARK stays Offline | AMI bridge, `provider_identifier` match, ingress token |

---

## After First Contact

Only then:

- PR3B — assignment UX polish
- **Provisioning Diagnostics** page (engineer-only: MAC → gates, projection, last request, last registration, rendered XML)
- Integration tests — every real bug becomes automated regression (404 → 403 → 200 → register → AMI → Connected)
- Phase 2 — claim flow, firmware, BLF
- Phase 3 — dynamic Asterisk projection
- Retire entries in [TECHNICAL_DEBT.md](../engineering/TECHNICAL_DEBT.md)
