# Core Product Upgrade Board

**Status:** Active shop-software track  
**Date:** 2026-09-10  
**Rule:** One slice at a time. Borrow interaction. Do not inherit competitor objects.

Recommendations + RO workspace was **slice 0**. It closed a real hole: declined and deferred work now follows the vehicle and customer instead of dying on an old RO. That is not the Core upgrade. It is one vertical thread through a still-shallow product.

The job is: LugsNPlugs floor + mature SMS craft → ARK that is noticeably better shop software.

Not: copy Tekmetric / Shopmonkey / AutoLeap. Not a 50-feature Cursor mission. Not Desk, Glass, RTE extraction, or PartsTech as substitutes for this board.

---

## How this board works

| Do | Do not |
| --- | --- |
| Ship one coherent slice | Open seven domains at once |
| Use existing authority and projections | New lifecycle statuses because a competitor named a column |
| Card / lane / line work that removes a click or a decision | Represent the whole shop as a junk-drawer board |
| Let Recommendations feed the slice | Rebuild Recommendations |
| Observe LNP after each slice | Declare Core finished because one slice felt good |

**Active slice:** 1 — Workboard / queue  
**First bite:** Shipped 2026-09-10. Stop and look at the floor before Slice 2.  
**Next after that earns trust:** 2 — Estimate Builder

---

## Slice 0 — shipped

**Recommendations + RO workspace**

Follow-up work lives on the vehicle/customer. Add-to-Estimate, RO information architecture, and estimate chrome around that path are dogfood.

Still not this board: historical recommendation backfill; RTE extraction.

---

## The board

Each row is one slice. **First bite** is the only buildable unit. Everything else in the row waits.

### 1. Workboard / queue — active

**Operator question:** At a glance, where is every vehicle, what is happening with it now, and which vehicles need attention?

**Already in ARK:** Job Board lanes (`WorkboardSwimlaneCatalog`), card projection, triage, Attention, Customer Decision Pressure, Today / Flow. Lanes already exist: Needs Diagnosis, Building Estimate, Waiting Approval, Waiting Parts, Shop Floor, Quality Check, Ready Pickup.

**First bite (2026-09-11, configurable lanes/statuses):** Five shop lanes remain the default (Estimates · Waiting Approval · Waiting Parts · Work in Progress · Completed). Those lanes and their statuses are shop configuration. Glance, attention, and card intelligence stay derived overlays. Activity instruments have a locked grammar. Screenshot, then dogfood before Slice 2.

**Not this slice:** Technician-only boards, parts receiving states, reporting charts, Glass/Desk as the command center.

**Recommendations thread:** Deferred/safety follow-up that is now live should appear as work, not only as RO history.

---

### 2. Estimate Builder

**Operator question:** Can I build a correct estimate as fast as a mature shop?

**Already in ARK:** Estimate lines, `EstimateTotalsCalculator`, labor/parts policies, snapshots, recommendation insertion, dealer-quote capture. Pricing engine is frozen.

**First bite:** Faster line construction on the builder we have — canned/common work, recommendation onto a line without leaving the estimate, matrix/customer-type behavior as shop configuration pointing at authority. Do not reopen snapshot immutability or invent a second totals path.

**Not this slice:** Catalog provider swap, RTE labor-guide extraction, a new estimate SPA.

---

### 3. DVI / inspection loop

**Operator question:** Finding → recommendation → estimate → authorization → tech → completion without a seam.

**Already in ARK:** Corner Inspection, measurements, G/Y/R, photos, findings, Recommendations. Inspection 1.0 authority. Mobile/tech inspection is still a form more than a DVI.

**First bite:** Close the finding → recommendation → estimate handoff on one RO so the advisor does not re-enter the same work. Photo/video capture only as far as that loop needs it.

**Not this slice:** A new inspection product, template marketplace, or technician handheld redesign (that is slice 5).

---

### 4. Parts / procurement

**Operator question:** Where is this part, and what is blocked until it lands?

**Already in ARK:** Parts lines, Parts Pressure, dealer-quote capture, PartsTech as a cart path. Capture v1 parked PO/receiving on purpose.

**First bite:** Useful part states on the RO that the workboard can see: ordered, partially received, received, backordered, installed, returned. The RO owns the workflow. Catalog is a provider into that, not the workflow.

**Not this slice:** PartsTech → generic Catalog/Platform (side lane). Do not let the provider migration eat this slice.

---

### 5. Technician execution

**Operator question:** What am I assigned, what is authorized, and what keeps this car in the bay?

**Already in ARK:** Technician scope doctrine, ARK Tech, assigned work, inspection checklist. Technicians are not low-permission advisors.

**First bite:** One execution surface for assigned work: authorized labor, parts readiness, inspection still required, notes/photos, the blocker. Dragon may augment later; the surface must work if Dragon is down.

**Not this slice:** Shop-wide tech discovery, comms queues, estimate editing on the handheld.

---

### 6. Customer authorization / closeout

**Operator question:** Sent, viewed, approved, declined, or callback — and the RO already knows.

**Already in ARK:** Approvals, portal links, conversation send-estimate/inspection/payment, Customer Decision Pressure, estimate-viewed observations.

**First bite:** One decision flow on the RO that makes those states obvious and writes back into workflow/workboard. No second approval authority.

**Not this slice:** Auto-text chase, CRM pipeline, new decision-status enum until this flow is trusted on the floor.

---

### 7. Operational reporting

**Operator question:** What is true about the shop today — not a chart wall.

**Already in ARK:** Executive Pulse, Day Review, queue pressure, ELR/ARO/margin, Flow design contract. Shop-excellence mapping lives in `docs/shop-excellence/`.

**First bite:** One operational report from the same projections as the workboard: Waiting Approval / Parts / Customer / Pickup, plus deferred safety/maintenance and recommendation recovery when those slices have earned numbers.

**Not this slice:** Pretty SaaS dashboards, inferred “likely to buy,” competitor KPI theater.

---

## Side lanes — not this board

These may run beside a slice. They must not replace a slice.

| Lane | Role |
| --- | --- |
| RTE → Labor Guide | Boundary. Estimating (slice 2) consumes it; it is not Estimate Builder. |
| PartsTech → Catalog / Platform | Provider. Parts (slice 4) consumes it; it is not procurement. |
| ARK Desk / Shop Glass | Parked. The command center for this upgrade is the Core workboard. |
| Historical recommendation backfill | Data. Do not block slice 1. |

---

## First slice when we build

Workboard / queue only.

Acceptance: Molly or Edward can open the board and, without opening an RO, know who is waiting, why, what to do next, and how much money is stuck. If a card cannot answer that, it does not belong on the board.

When that is true on the floor, open slice 2. Not before.
