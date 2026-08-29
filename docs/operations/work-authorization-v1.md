# Work Authorization v1

**Status:** Frozen doctrine · **v1 Testing Package slice shipped** · Observe  
**Classification:** Operations authority (grammar) — packages are the customer contract for authorized work  
**First consumer:** Testing Package v1 (this document · implemented)  
**Companions:** [Maintenance Service v1](maintenance-service-v1.md) · [Evidence Authority v1](evidence-authority-v1.md) · [Repair Action assignment](repair-action-assignment-and-labor-recognition-v1.md) · [Financial Authority v2](../ARK-FINANCIAL-AUTHORITY-V2.md) · [Repair Portal v1](repair-portal-v1.md)

**Business rationale (craft, not doctrine):** Go Fuel “Level Testing Amplifier” — stop selling open-ended diagnostic hours; sell structured, bounded testing. ARK implements that as **authorized work packages**, not Level labor SKUs.

---

## Constitutional question

**What work has the customer authorized us to perform?**

Not: How many hours?  
Not: Which labor category rate?  
Not: What did the tech flag?

Hours belong to production / compensation. Dollars belong to Pricing Policy + Estimate / Financial Position. Work Authorization owns **authorized scope and outcome**.

---

## Invariant #0 — Permission, not execution

**Work Authorization owns permission, not execution.**

Analogous to Financial Position’s *owns nothing; answers everything* — this authority answers a narrow question and refuses to become the systems it composes.

| Answers | Does not become |
| --- | --- |
| What has the customer approved? | Repair Actions |
| What scope was approved? | Evidence |
| Is further work still authorized? | Maintenance Events |
| How did this authorization conclude? (Outcome) | Recommendations as a free-floating CRM |
| | Labor · Pricing · Payroll · Compensation · Financial Position |

Those remain separate authorities. Work Authorization **composes** them for orientation; it does not absorb them.

```text
Customer Concern
        │
Needs Work Authorization?
        │
Work Authorization          ← permission / scope / outcome
   ┌────────┴────────┐
Testing           Maintenance     ← package types (peers)
Package           Package
        │
 Repair Actions                 ← execution
        │
    Evidence                    ← proof
        │
    Outcome → Recommendation
```

Labor, Pricing, Payroll, Technician compensation, and Financial Position stay **outside** this tree — they compose *around* authorization.

---

## Principle #1

**Customers authorize packages. ARK performs work.**

| Customer buys | ARK runs |
| --- | --- |
| Testing Package (e.g. Level 1 Testing) | Repair Actions · Evidence · Outcome · Recommendation |
| Maintenance Package (oil) | Prepare → Install → Event |
| Future Repair / Programming / Inspection packages | Same grammar |

Level names (`Level 1 Testing`, `Level 2 Testing`) are **shop policy labels**, not internal authority nouns.

---

## Principle #2

**Testing ends with an answer, not a repair.**

A Testing Package may **recommend** a repair. Completing testing never silently becomes the repair. Repair is a separate authorization (or a future Repair Package).

---

## Principle #3

**Escalation is the product rhythm — not more hours.**

```text
Testing Package
      │
   Outcome
      ├── Resolved → Repair Recommended | No Fault Found | Done
      └── Escalate → next Testing Package (customer re-authorizes)
```

Advisor language: *“We've completed the first testing package. Based on what we found, we recommend the next testing package because this concern spans multiple systems.”*

Never: *“We need another hour…”*

---

## Vocabulary

| Internal (authority) | Meaning | Customer / Demo Auto Repair policy (example) |
| --- | --- | --- |
| **Work Authorization** | Grammar: customer-authorized package of work | — |
| **Package Type** | Testing · Maintenance · (future) Repair · Programming · Inspection | — |
| **Testing Package** | Authorized testing scope on a Concern / RO | Level 1 / 2 / 3 Testing |
| **Testing Scope** | What systems / depth this package covers | “Single system” / “Multi-system / intermittent” |
| **Testing Outcome** | How testing ended | See Outcomes |
| **Testing Recommendation** | What we advise next | Repair / escalate / stop |
| **Repair Action** | Work to perform inside the package | Tech checklist (scan, pin test, …) |
| **Evidence** | Proof attached to work | Portal Shared photos / measurements |

---

## Owns

Work Authorization (and Testing Package as first type) owns:

1. **That authorized work exists** as a package (not a free-floating diagnostic labor hour)
2. **Package type** (Testing first)
3. **Link to Concern / Repair Order** (where authorization sits)
4. **Scope key** (shop-policy identity — e.g. `testing.level_1` — not customer copy)
5. **Status** of the authorization (authorized · in progress · completed · declined further)
6. **Outcome** when testing completes (see Outcomes)
7. **Recommendation** text / structured next step after outcome
8. **Escalation link** to a subsequent package when outcome is Escalate

---

## Does not own

| Not owned | Belongs to |
| --- | --- |
| Package sell price, multiples, flat fees | Pricing Policy / Estimate lines (`Package` type) |
| Flag hours / tech pay guarantees | Compensation / recognition (Labor only) |
| Customer Level labels (“Level 1 Testing”) | Shop configuration / presentation |
| Evidence bytes or visibility | Evidence Authority |
| Repair Action ownership / status / updates | Repair Action (work groups) |
| Financial Position / invoice / deposits | Financial Authority |
| Diagnosis as caption on photos | Concerns / recommendations — Evidence never diagnoses |
| Vehicle specification / OEM procedures | Not invented here |
| Migrating Maintenance into this table on day one | Maintenance stays; **composes** the same grammar |

---

## Composes (do not reinvent)

```text
Concern
   │
Needs authorized work?
   │
Work Authorization ── Testing Package (v1)
   │
   ├── Repair Actions     (work to perform)
   ├── Evidence           (proof)
   ├── Estimate Package line  (customer price — Financial contract)
   └── Outcome + Recommendation
           │
           ├── Repair Recommended → separate approval / repair work
           └── Escalate → new Testing Package authorization
```

| Existing capability | Role under Work Authorization |
| --- | --- |
| **Repair Actions** | Contained work units for the package |
| **Evidence** | Measurements, photos, captures — Shared to portal |
| **Package line type** | Sold price on Estimate (never flag hours) |
| **Financial Position** | Approved packages + repairs → Projected Balance |
| **Portal** | Progress, Shared evidence, recommendation |
| **Maintenance Service** | Peer package type already shipping — same customer question |

---

## Testing Package v1 (first consumer)

### Owns (Testing-specific)

- Scope key for this authorization (`testing.*` policy keys)
- Testing Outcome (required when package completes)
- Testing Recommendation (when outcome warrants)
- Escalation target (next scope key) when outcome = Escalate

### Outcomes (operational truth — not labor, not pricing)

| Outcome | Meaning |
| --- | --- |
| **Resolved** | Testing complete; answer reached without further testing package |
| **Repair Recommended** | Testing complete; repair authorization is the next customer decision |
| **Escalate Testing** | Testing incomplete relative to concern; next Testing Package recommended |
| **No Fault Found** | Testing complete; no repair indicated |
| **Customer Declined Further Testing** | Shop offered escalate or repair path; customer stopped |

### Escalation

Escalation is a **new Work Authorization**, not an edit that mutates the completed package’s outcome into “more hours.”

```text
Package A completed → Outcome = Escalate Testing
        ↓
Customer approves Package B (policy: Level 2 Testing)
        ↓
New Repair Actions / Evidence under Package B
```

Historical Package A remains evidence of what was already authorized and concluded.

### Customer vs tech surfaces

| Audience | Sees |
| --- | --- |
| **Customer / advisor sell** | Policy label (Level 1 Testing), price, what the package includes, outcome, recommendation, Shared evidence |
| **Technician** | Testing Package → Repair Actions → Evidence → measurements → status — **not** “Level 2” as a labor concept |

---

## Pricing Policy (outside this authority)

```text
Testing Package (authority)
        ↓
Pricing Policy (shop configuration)
        ↓
Estimate Package line → Financial Position
```

Examples (illustrative only — not Demo Auto Repair defaults in code):

| Shop | Level 1 | Level 2 | Level 3 |
| --- | --- | --- | --- |
| Multiples | Base × 1.5 | Base × 2.5 | Base × 4 |
| Flats | $199 | $349 | Quote |

Same Testing Package keys. Different Pricing Policy. Work Authorization never stores rates.

Tech pay multipliers from coaching PDFs (guaranteed flag hours per level) are **compensation policy**, not Testing Outcome.

---

## Non-goals (still frozen — do not expand yet)

Do **not**:

- Add Level 1 / 2 / 3 as labor categories or labor line presets
- Sell testing as `1.0 Diagnostic` / `2.0 Diagnostic` hours
- Migrate Maintenance into a shared table before Testing earns floor trust
- Open Repair / Programming / Inspection package types
- Put Level labels in PHP as product defaults for all shops
- Auto-escalate without customer authorization
- Fold repair performance into the Testing Package outcome
- Reopen Financial Invoice / Refresh paths for packaged testing
- Build portal progress meters / evidence attach UI for Testing before observation
- Pricing Policy multiples / shop Settings for Level prices

---

## Shipped slice (v1)

| Delivered | Not yet |
| --- | --- |
| `work_authorizations` table | Scope keys (`testing.level_*`) |
| Authorize Testing Package on RO | Levels / customer policy labels |
| Concern + Repair Action + Package line ($0) | Pricing Policy |
| Record Outcome + recommendation | Escalation create-next-package flow |
| RO builder panel | Portal progress / Shared evidence wiring |

Code: `app/Ark/Operations/WorkAuthorization/*` · Pest `WorkAuthorizationTestingPackageTest`

---

## Observation gate (after v1)

Run ~20–30 real Testing authorizations. Notebook — not dashboard.

| Signal | Ask |
| --- | --- |
| **Advisor language** | Do they say “authorizing a Testing Package” — or still “adding diagnostics”? Language fight ≠ architecture fight. |
| **Tech ignore** | Do techs jump straight to Repair Actions and never look at the package? Authorization may stay advisor/customer-side. |
| **Escalate rate** | Rare → Levels may not matter. 30–40% → earn next-package workflow. |
| **Pricing creep** | Repeated “can we just put the price here?” → thin WA ↔ Pricing Policy composition — do **not** fold price into permission. |

Question under test: **Is Work Authorization the right abstraction?** — not “did we build enough features?”

**Next slice only after notebook evidence** — not roadmap momentum. No Levels · Portal · Evidence attach · Auto-escalation · Progress meters.

---

## Relationship to Maintenance

Maintenance already answers *what did the customer authorize / what was installed?* for oil.

Work Authorization is the **shared grammar**. Maintenance remains the shipped peer. Testing is the first **new** package type under this doctrine. Do not force a mechanical merge until both have been observed.

---

## Stop

v1 Testing Package slice is enough to prove permission exists.

- Observe Authorize + Outcome on real ROs  
- No Levels, Pricing Policy, portal progress, or escalate-create until earned  
- PDF Level Testing = craft input; ARK Work Authorization = product doctrine
