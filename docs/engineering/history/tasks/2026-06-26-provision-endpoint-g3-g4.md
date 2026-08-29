# Task: Provision endpoint gates (G3 / G4)

**Goal:** [Complete First Contact Certification](../goals/first-contact-certification.md)  
**Worker:** human  
**Status:** In progress — G4 production URL returns 200; G5–G7 not bench-certified yet

---

## Events

### goal.referenced

2026-06-26 — Opened against First Contact Certification. Bench session targeting G3 (unknown MAC) and G4 (known MAC provision URL).

---

### planner.hypothesis.proposed

**Hypothesis:** Unknown valid-format MAC with no device row should return **404**, not 500.  
**Falsifiable by:** `GET /provision/AABBCCDDEEFF.cfg` on production.

---

### observer.evidence.captured

**Source:** Production HTTP (curl)  
**Measured:**

- Fake MAC `AABBCCDDEEFF` → **404** (route + lookup OK)
- Known MAC `48256730757F` → **500** HTML error page (device exists; failure during regenerate/build)

**What changed my mind:** G3 gate was not the problem. Known-device provision path was broken before G4 could matter.

---

### planner.hypothesis.proposed

**Hypothesis:** Production failure is misconfiguration (migrations, `ASTERISK_PROVISIONING_HOST`, SIP credentials) — not bad MAC or phone.  
**Falsifiable by:** Plain-text 503 with specific reason after preflight deploy; env vars present in running container.

---

### worker.declared_done

**Proposed artifacts (commits on `main` / `production`):**

- `47dbc087` — 503 plain-text preflight instead of opaque 500
- `2310c6e8` — lock `ASTERISK_PROVISIONING_HOST` from `VOICE_SIP_REGISTRAR` in env sync
- `749a3c8f` — resolve voice SIP registrar from env, shared secrets, Asterisk transport env
- SSH: `sync-arksms-voice-transport.sh` on control plane; backfill `ark-production.env`

**Declaration only** — observer events below.

---

### observer.evidence.captured

**Source:** Production HTTP after redeploy + env sync  
**Measured:**

- `48256730757F.cfg` → **503** plain text: `ASTERISK_PROVISIONING_HOST is not configured` (preflight working; env still missing)
- After Coolify env sync + redeploy: container `printenv VOICE_SIP_REGISTRAR` = `voice.demo-auto.test`
- `48256730757F.cfg` → **200** — Poly body with `voice.demo-auto.test`, extension 101, microbrowser URL

**What changed my mind:** Observer layer (HTTP status + body) separated config gap from code gap. 503 text pointed at env; 200 confirmed G4 URL path without trusting worker summary alone.

---

### reviewer.decision.recorded

**Decision:** Stop bench certification at G3/G4 software path; **proceed to G5** (phone server URL on device).  
**Because:** Observer evidence shows production provision URL returns valid Poly config for registered MAC. G3 unknown-MAC behavior was already 404. Remaining work is physical bench (factory reset, PoE, G5–G7), not more provision-route code until floor contradicts.

---

### observer.evidence.captured

**Source:** Doctrine commit (meta — engineering process)  
**Measured:** `f6578c05` — ARK Engineering Doctrine v1 frozen on `main` only (not production). Separates shop runtime from how platform is built.

**What changed my mind:** N/A for Voice gate — recorded because this task surfaced the engineering-loop pattern worth dogfooding.

---

## Open

- Bench G5–G7 with physical VVX350
- PJSIP password sync if registration fails after G5
- **Next observer question:** Does phone register to `voice.demo-auto.test` after pulling config?
