# Inspection Reporting v1 — Proposal (audit)

**Status:** Approved · Implemented · **STOP for visual review**  
**Date:** 2026-07-26 · Implemented 2026-07-27  
**North star:** One Inspection truth → Simple / Detailed (view modes) → Portal / Print / PDF  

**Architecture (frozen):**

```text
Inspection → InspectionReportProjection(Simple|Detailed) → Portal / Print → PDF
```

No report tables. No second interpretation layer.  
**Evidence rule (HARD):** customer-facing purpose allowlist — unknown/new purposes default hidden.

**Authority invariant (frozen for this capability):**

> Inspection reporting presents inspection truth. It does not create repair truth.  
> Comparison observations may be illustrated; they must not diagnose, create Concerns, or recommend repairs.

---

## 1. Existing Portal / DVI infrastructure

| Piece | State | Path |
| --- | --- | --- |
| `InspectionAccessToken` + SMS send | **Shipped** (Phase 2B) | `CreateOrReuseInspectionAccessTokenAction`, `SendInspectionLinkAction`, `portal.inspections.*` |
| Customer HTML page | **Thin live** | `PortalInspectionShowController` → `PortalInspectionSnapshot` → `portal/inspection.blade.php` |
| What it shows today | Recorded findings only (`isRecorded`), intent from notes prefix, first measurement, all photos/videos | No checklist condition, no OK rollup, no Simple/Detailed |
| Staff preview / copy / send | **Exists** | Rail partial + mobile commands |
| Authenticated portal discovery | **Missing** | Vehicle hub / visits show estimates, not inspection |
| Simple \| Detailed | **Missing** | |
| Print / PDF for inspection | **Missing** | Estimate has PDF parity; inspection does not |

**Today’s customer sentence:** “Here are the findings we recorded.”  
**Desired sentence:** “Here is your vehicle inspection report — Simple first, evidence when you want it.”

---

## 2. Existing PDF / print infrastructure

| Pattern | Engine | Reuse? |
| --- | --- | --- |
| Operational sheets (intake / tech) | `HtmlPdfBuilder` → Spatie Browsershot | **Yes — primary path** |
| Estimate / invoice PDF | `HeadlessChromiumPdfRenderer` + frozen `documents/pdf/document.blade.php` | Shop presentation layers yes; **do not** merge into estimate Blade |
| Thermal labels | Separate | No |
| DomPDF / `window.print()` as PDF authority | Forbidden by PDF doctrine | No |

**Reuse:** Chrome-free Blade under `documents/sheets/` (or `documents/inspection/`) + `_pdf-document-header` + shop `logo_data_uri` from `EstimateSnapshotBuilder::presentationLayers()['shop']`.  
**Portal vs print:** **Shared projection, separate Blades** (same as estimate Portal HTML vs PDF).

---

## 3. Proposed shared report projection

```text
Inspection (authority)
  → InspectionReportProjection  (disposable; rebuildable)
       → Portal interactive Blade   (Simple | Detailed + Print/PDF CTAs)
       → Print Blade (no chrome)    (browser print + HtmlPdfBuilder)
```

**Name:** `InspectionReportProjection`  
**Modes:** `simple` | `detailed` — **view modes of one payload**, never two stored reports.  
**Consumers:** Portal token page, authenticated portal entry (v1), print HTML, PDF bytes.  
**Deprecate/thin:** `PortalInspectionSnapshot` becomes a thin adapter calling the report projection (or is replaced by it). Finding cards may still feed point packaging where useful.

**No new authority tables.** No `inspection_reports`, no snapshot JSON store required for v1 (live projection). Optional later: immutable PDF bytes if email/archive earns it (estimate pattern).

---

## 4. Simple report — information hierarchy (exact)

**Default customer view.**

1. **Shop identity** — logo, name, phone (letterhead-light on Portal; full on print/PDF)  
2. **Title** — Vehicle Inspection  
3. **Vehicle + visit** — Y/M/M · mileage in (if present) · date inspected · RO # · template name (Standard / PPI) · technician name (if recorded)  
4. **Summary strip** — counts only:  
   - N Need Attention (Needs Attention + Failed)  
   - N Monitor  
   - N Checked OK (Pass)  
   - N/A / Not performed called out honestly when relevant (road test N/A, etc.) — not buried  
5. **Needs Attention / Failed** — each point:  
   - Condition label (restrained type, not emoji)  
   - Structured measurements that matter (brake I/O, tire O/C/I, PSI when relevant)  
   - Comparison **observation** language only when derived (e.g. “Uneven wear was observed on the left front brake.”) — **never** “replace caliper”  
   - Tech note (intent prefix stripped)  
   - Associated photo(s)  
6. **Monitor** — same shape, quieter  
7. **Checked & OK** — collapsed: category or comma list of labels + count (no green card forest)  
8. **Footer** — shop contact; optional “Ask us about anything on this report”

**Simple excludes:** full template walk of every Pass point, internal brake “take another look” coaching copy, internal ARK vocabulary, estimate/Concern links as repair conclusions.

---

## 5. Detailed report — information hierarchy (exact)

**Evidence / records / PPI buyer / warranty / another shop.**

1. Same identity header as Simple  
2. Same summary strip (orientation)  
3. **By template category** (physical / seed order), every **visible** walk point (respect Disc/Drum axle filter):  
   - Label  
   - Condition (Good / Monitor / Needs Attention / Failed / N/A)  
   - Full structured measurements (all slots)  
   - N/A / not performed represented honestly (road-test findings when performed = N/A)  
   - Notes  
   - Photos/video for that point  
   - Scan evidence callout on PPI scan points (attachment present)  
4. **Comparison observations** (customer-safe sentence only) adjacent to the measured points — not a separate “diagnosis” section  
5. **Road-test** section intact (performed + observations)  
6. Optional small “Not checked” honesty if walk incomplete — do not pretend coverage

Detailed is still a **projection**, not a DB dump: typography, measurement pairs, photos with findings.

---

## 6. Portal integration

| Requirement | Proposal |
| --- | --- |
| Preserve `InspectionAccessToken` SMS delivery | Keep routes; feed report projection |
| Visible from vehicle/visit without fresh SMS | **v1:** add inspection entry on authenticated portal vehicle/visit when an Inspection has addressable evidence (and customer owns vehicle). Deep-link may mint/reuse token or use portal-auth gated report route — **prefer portal-auth for signed-in; keep token for SMS** |
| Default Simple | Query/UI: `?view=simple` default; `?view=detailed` |
| Simple \| Detailed toggle | Client switch or links; same projection |
| Print · Save PDF | CTAs → print Blade URL / PDF download (token- or auth-scoped) |
| Staff preview | Keep amber banner; do not log customer view |

**Photo hygiene (must ship with report):** Portal/print/PDF include photos with purpose `customer` (and optionally `before`/`after` if used customer-facing). **Exclude `internal`** unless explicitly approved later. Today’s snapshot leaks all purposes — fix as part of this capability.

---

## 7. Print / PDF path

```text
InspectionReportProjection
  → resources/views/.../inspection-report-print.blade.php   (no nav, no buttons, no sticky)
  → browser Print (CSS @page Letter)
  → HtmlPdfBuilder::toPdfBytes()                            (same Blade)
```

- Demo Auto Repair shop letterhead via existing presentation layers (not ARK product `Branding`)  
- Page-break-aware sections (`page-break-inside: avoid` on finding blocks)  
- Photos sized for print; embed as data URIs or absolute URLs Chromium can load  
- Footer: shop · RO · report date · page numbers (print CSS)  
- QR/link to live Portal — **optional v1**, not required  
- Black-and-white readable: condition via type weight/labels, not color-only  

**Do not** print Portal chrome. **Do not** invent a second measurement mapper for PDF.

---

## 8. Photo / evidence handling

| Rule | v1 |
| --- | --- |
| Association | Photos sit with the finding/point they belong to |
| Purpose filter | Customer-facing purposes only on Portal/Print/PDF |
| Video | Portal: playable; Print/PDF: poster/thumbnail + “video available in online report” if stills insufficient |
| Token photo routes | Keep gated streaming; print/PDF must not depend on interactive session alone |

---

## 9. Measurement visualization approach

**Intentional, restrained, non-diagnostic.**

| Measurement | Presentation |
| --- | --- |
| Brake Inboard / Outboard | Labeled pair + optional two horizontal bars scaled to same max (visual Δ only) |
| Tire Outer · Center · Inner | Three labeled values + optional three-bar tread silhouette |
| Tire PSI | Four-corner grid LF/RF/LR/RR |
| PPI battery / charging | Voltage / result as labeled figures |

**Copy when Δ exceeds shop threshold (customer):** observational only — e.g. “Uneven pad wear was observed on this wheel.”  
**Forbidden:** inventing cause, parts to replace, or Concern titles from Δ.  
**Tech “take another look” helper:** walk-only; **not** on customer report.

---

## 10. Historical inspection behavior

| Fact | Behavior |
| --- | --- |
| One Inspection per RO | Report is always for that RO’s inspection session |
| Template edits after capture | Report reads **item rows + measurements on the Inspection**, not live template seed — historical evidence preserved |
| Reset walk | Clears session evidence; report empties / “not ready” — no parallel history table |
| Prior visits | Optional Detailed glance via existing prior-visit projection — not required for Simple v1 |
| Incomplete walks | Honest counts; do not claim “23 Checked OK” if coverage incomplete without labeling incompleteness |

---

## 11. Authorization / privacy boundaries

| Audience | Access |
| --- | --- |
| Customer + SMS token | Token routes (existing throttle) |
| Signed-in portal customer | Vehicle ownership / RO ownership checks |
| Staff preview | Manage capability; no customer view log |
| Internal photos | Never on customer report |
| Brake coach prompts | Never on customer report |
| Recommendation hints / Concern drafts | Not on customer report as conclusions |

---

## 12. Gaps requiring new authority?

| Gap | New authority? |
| --- | --- |
| Simple/Detailed packaging | **No** — projection |
| Print/PDF Blade + HtmlPdfBuilder | **No** — presentation |
| Portal vehicle discovery | **No** — routing + auth gate |
| Photo purpose filter | **No** — already on `InspectionItemPhoto.purpose` |
| Customer-safe comparison sentence | **No** — derived from measurements + threshold; do not persist as diagnosis |
| Completed lifecycle / Finish Inspection | **Out of scope** — do not invent via report |
| Auto Concern from Δ | **Forbidden** |
| Stored report PDF archive | **Not required v1**; earn later if email/history demands |

**No new inspection/report authority tables for v1.**

---

## 13. Visual language (binding craft)

- Demo Auto Repair identity, strong typography, evidence as interest  
- Condition recognizable without emoji/alert spam  
- Prefer:

```text
NEEDS ATTENTION
LF Brake
3 mm inboard · 7 mm outboard
```

- Avoid: card forests, pill forests, gradients, dashboard chrome, internal ARK terms (`observed_state`, `point_key`, “DVI module”)

---

## 14. Explicit non-goals (this capability)

- Separate Simple vs Detailed stored reports  
- PDF that reinterprets measurements differently from Portal  
- Auto diagnosis / Concerns / estimate lines from comparisons  
- Finish Inspection / lifecycle completion  
- Replacing technician walk UX  
- Giant redesign of estimate PDF stack  

---

## 15. Suggested build sequence (after approval only)

1. `InspectionReportProjection` (Simple + Detailed fields; photo purpose filter; customer-safe comparison sentences)  
2. Portal Blade rewrite consuming projection + Simple \| Detailed  
3. Print Blade + `@page` + PDF via `HtmlPdfBuilder`  
4. Authenticated portal vehicle/visit entry  
5. Tests: same projection bytes for Portal/PDF sample points; purpose filter; no internal prompts; incomplete honesty  

---

## STOP

Awaiting approval of this proposal before any implementation.

**Open product choices to confirm on approval:**

1. Signed-in portal: portal-auth report route vs always token-mint behind the scenes?  
2. Include Failed inside “Need Attention” count (recommended) or separate count?  
3. Video on PDF: skip / thumbnail / link-only?  
4. QR to live report on print v1: yes/no?
