# Operational Certifications

**Status:** Frozen v3 — complete enough to govern decisions. Do not add levels, documents, or certification names without shop evidence that the catalog is too coarse.

**Companion:** [day-in-the-life/](./day-in-the-life/) · [orientation-platform.md](./orientation-platform.md) · [certifications/](./certifications/) (historical records)

**Done means:** *We can perform real shop work using this* — not *we implemented feature X.*

**Trust bar:** *Would I trust this while Molly is helping a customer?*

---

## Platform rules (frozen)

| Rule | Meaning |
|------|---------|
| Authorities own truth | What happened and what exists |
| Stations own orientation | Where work happens briefs the operator |
| Capabilities project workflow | Comms, payments, etc. never own ROs or customers |
| Every capability must be certifiable | Real shop work — not API 200 or widget rendered |
| **Levels are a dependency chain** | A level cannot stay valid if the level below is red |
| **Keep the catalog coarse** | ~9 observable shop capabilities — not 70 Jira tickets |

---

## Two layers per certification

| Layer | Question | Examples |
|-------|----------|----------|
| **Capability** | Can the system technically do it? | Phone registers · Push arrives · MMS sends |
| **Operational** | Can the shop actually work with it? | Customer calls · Customer texts · Checkout |

---

## Three PASS levels (dependency chain)

```text
Engineering Certified
        ↓  (must stay green to trust Operational)
Operationally Certified
        ↓  (must stay green to trust Production)
Production Certified
```

**Not three independent badges.**

If Engineering breaks (e.g. VVX registration fails tomorrow):

```text
Engineering   ❌
Operational   suspended  (was ⚠ or ✅ — no longer trustworthy)
Production    suspended  (was ⬜ or ✅ — no longer trustworthy)
```

Operational experience may still *appear* to work; the foundation is no longer certified. Re-certify Engineering before claiming Operational or Production again.

| Level | Meaning |
|-------|---------|
| **Engineering** | Capability checklist green; PHPUnit guards regression |
| **Operational** | Shop completed real work once — only valid if Engineering green |
| **Production** | Sustained live shop trust — only valid if Operational green |

---

## Evidence vs proof

| | **Evidence** | **Proof** |
|---|--------------|-----------|
| **What** | What happened (narrative checklist) | Artifacts someone can verify later |
| **Examples** | Factory-reset VVX · Customer call · Customer text | Video · Screenshot · Log reference · RO # · Call SID |

Evidence explains the story. Proof lets you answer *when did we actually production-certify Front Counter?* a year later.

Record both in [certifications/](./certifications/).

---

## Why this matters

Every certification ends with one sentence — **business capability, not engineering achievement:**

> **After this certification, the shop can…**

| Certification | Why this matters |
|---------------|------------------|
| Front Counter | …answer customer calls and texts entirely through ARK at the desk. |
| Portable Station Phase 1 | …complete customer communication away from the Front Counter. |
| Orientation Platform v1 | …act without reconstructing repair order state first. |
| Bay | …document inspection and production beside the vehicle without desktop. |
| Voice Transport | …receive customer calls through ARK-backed desk phones. |

---

## Coarse certification catalog

Observable in the shop. **Do not splinter** (no per-endpoint certs). Phases (e.g. Portable Station Phase 1) are checklist slices inside one cert — not new certification names.

| Certification | Track | Primary observation |
|---------------|-------|---------------------|
| **Front Counter** | A | Desk station: call, text, approve, checkout |
| **Bay** | B | Assigned RO work beside vehicle |
| **Portable Station** | B | Phone as station peer |
| **Orientation** | C | Briefing before action on every surface |
| **Voice Transport** | A | PSTN → SessionEvent → ring |
| **Payments** | A | Collect and close on RO |
| **Customer Intake** | B | Check-in without desktop |
| **Inspection** | B | Checklist + photos + findings |
| **Parts** | A | Production blockers cleared |

Living dashboard (example):

```text
Operations Platform — release view

Engineering     ✅ Voice Transport  ✅ Orientation
Operational     ✅ Front Counter    ⚠ Portable Station Phase 1
Production      ⬜ Production cutover (Voice Transport)
```

---

## Certification record

File in [certifications/](./certifications/) when signing a level. Use [_template.md](./certifications/_template.md).

**Fields:** owner · dates per level · **evidence** (what happened) · **proof** (verifiable artifacts) · **why this matters**

---

## Example suites (abbreviated)

Full scenarios in [day-in-the-life/](./day-in-the-life/).

### Front Counter

**Why this matters:** After this certification, the shop can answer customer calls and texts entirely through ARK at the desk.

**Capability:** provision VVX · inbound call → CallSession · inbound/outbound SMS  
**Operational:** open shop · call · text · approve estimate · checkout

### Portable Station (Phase 1 checklist)

**Why this matters:** After this certification, an advisor can complete customer communication away from the Front Counter.

**Capability:** push · mark read · MMS · orientation home API  
**Operational:** orientation home · push → reply · desktop parity · station switch

### Orientation Platform v1

**Why this matters:** After this certification, staff act without reconstructing RO state first.

**Capability:** frozen contract on all briefing surfaces · density presenters · no duplicated derivation  
**Operational:** same RO — consistent briefing at Interrupt vs Full on floor observation

### Voice Transport

**Why this matters:** After this certification, customer calls reach the shop through ARK-backed transport.

**Engineering:** VVX registers · SessionEvents → CallSession  
**Operational:** one real inbound call  
**Production:** one week customer calls without rollback

---

## Feature gate & code review

> **Which certification does this move forward?**

If **none** — warning sign; work may not align with current priorities.

At code review, prefer this over *does the code look good?*

| Proposal | Cert | Verdict |
|----------|------|---------|
| BLF | None | Don't build |
| Orientation Home | Portable Station (Operational) | Build |
| MMS route | Portable Station (Capability) | Build |

Also name: layer (Capability/Operational) · checklist row · target level · dependency impact (does this keep Engineering green?).

---

## Release notes

Certifications shipped — not version theatrics. Use the release dashboard shape above.

---

## Framework complete

No more levels. No more documents. Govern decisions with product certification docs
