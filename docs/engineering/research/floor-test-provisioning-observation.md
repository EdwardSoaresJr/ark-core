# Floor Test Observation — Provisioning Attempt

**Purpose:** Architecture validation on physical hardware — not a pass/fail phone test.

**Objective:** Determine the first gate at which reality diverges from the model — not "get the phone working."

> The first experiment is the most valuable because it contains the fewest unknown interventions. Protect it.

Success and failure are both valid outcomes. Every observation either **strengthens or weakens** an architectural assumption.

Do not fix immediately. Capture the sequence first.

## Certification doctrine (reusable beyond Voice)

**Every session ends with one unanswered question — not ten.**

A gate is certified only when its **failure mode is also understood**. G3 is not certified today because it failed unexpectedly (500, not 404). When G3 returns 404 + `gate=NOT_FOUND`, it is certified — not merely because it passed, but because both success and failure paths are understood.

**Session rhythm:** Observation → Classification → Intervention. Classify first. Intervene once. Re-test only the gate under certification.

## Success criterion (narrow)

```
Factory reset → PoE → Provision → Connected
```

Resist expanding scope (firmware, BLF, paging, ARI) until this pipeline is proven.

## Architecture validation map

| Observation | Validates (or challenges) |
|-------------|---------------------------|
| Phone requests correct URL | Endpoint identity / MAC lookup model |
| Projection reused vs regenerated | Projection lifecycle |
| Phone rejects XML | Serialization / vendor assumptions |
| Phone accepts XML, never registers | Provisioning OK — investigate telephony |
| AMI registration + ARK shows Connected | Full pipeline: authority → projection → endpoint → telephony → observation |

The **sequence** matters more than the outcome.

## Observation panes (war room — all visible before PoE)

Do not open panes as needed during boot. Have all four visible before plug-in.

```
┌──────────────────────────────────────────────┐
│ Phone        — display; hand-write timestamps  │
├──────────────────────────────────────────────┤
│ Provisioning — endpoint.provision.request    │
├──────────────────────────────────────────────┤
│ Asterisk     — PJSIP, AMI                    │
├──────────────────────────────────────────────┤
│ ARK          — device show: URL, fingerprint,│
│                workstation, extension, state │
└──────────────────────────────────────────────┘
```

When the timeline stops, do not invent the next event.

## Device

- Model: Poly VVX350 (factory reset)
- MAC:
- Date:

## Chronology

Record in order. Paste logs, HTTP traces, and AMI output inline or link to files.

```
1. Phone booted
2. Requested: {URL / method}
3. Projection: reused | generated | missing
4. Response: {status, content-type, size, snippet}
5. Phone: accepted | rejected config
6. Registration attempted: {extension / identity}
7. Asterisk response: {PJSIP / CLI output}
8. AMI event: {event or "none observed"}
9. ARK state: {CommunicationDevice status if visible}
```

## HTTP requests

| Time | Method | Path | Status | Notes |
|------|--------|------|--------|-------|
| | | | | |

## Projection decisions

| Step | Inputs | Fingerprint match? | Action |
|------|--------|-------------------|--------|
| | | | |

## SIP / Asterisk

```
(paste relevant PJSIP, Asterisk CLI, or AMI log excerpts)
```

## Outcome

- [ ] Connected
- [ ] Failed at step ___

## Learnings (after capture — not during)

What diverged from the system's mental model?

| Assumption | Strengthened | Weakened | Evidence |
|------------|--------------|----------|----------|
| | | | |

Physical devices are stubborn. The first attempt will likely teach something the documentation didn't. That's normal — if observability shows *where*.

---

*Fixes come after the chronology is complete.*
