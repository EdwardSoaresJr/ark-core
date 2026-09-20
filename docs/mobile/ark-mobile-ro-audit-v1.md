# ARK Mobile — Repair Order Audit v1 (Shop In A Phone)

**Date:** 2026-06-28
**Method:** On-device, real production data, Motorola Razr 2025 (1080×2640). Operated as Edward (advisor/owner) opening Work → Repair Orders and driving every RO surface by hand.
**Lens:** *Shop In A Phone / Tablet* — a solo/mobile operator or owner walking the floor should **rarely, if ever, open a desktop browser**. Every RO surface is judged by: Where am I? What's happening? What do I do next? Can I finish it here?

This is the RO-focused follow-up to `ark-mobile-ux-audit-v1.md`. Design-system fixes from v1 are landing well (Work list cards, attention band, persistent identity strip). The remaining friction is concentrated in the **Repair Order** experience.

---

## 0. Fixed during this audit (verified on device)

Two defects were unambiguous breakage and were fixed and verified on the device in this pass:

| # | Defect | Root cause | Fix |
|---|--------|-----------|-----|
| **F1** | **Some ROs would not open at all** — "Could not load workspace. type '_Map<String, dynamic>' is not a subtype of type 'List<dynamic>?'". Reproduced on RO #1578. | `RepairOrderWorkspaceIntelligenceProjection::timeline()` used `->values()->take(-30)`. Laravel `take(-30)` keeps the **last 30 items but preserves their original keys**, so any RO with **>30 timeline entries** serialized `timeline` as a JSON object `{}` instead of an array. The Flutter parser cast it to `List` and threw, white-screening the entire RO. | Server: move `->values()` to **after** `take(-30)->map(...)` so the array is always reindexed. Client: added a `jsonList()` guard in the workspace models so a single malformed array degrades to empty instead of crashing the whole RO. |
| **F2** | **"Findings (0)" header rendered vertically** (one letter per line) inside the concern view. | The inline "+ Finding" button inherits the global `FilledButton` theme `minimumSize: Size.fromHeight(48)` → **infinite min width**. Inside the header `Row`, that infinite width collapsed the `Expanded` title to ~0 px and the text wrapped per character. | Bound the inline button with `FilledButton.styleFrom(minimumSize: Size(0, 48))` so it hugs its content. |

> **Latent hazard (recommended follow-up, not yet done):** `Size.fromHeight(48)` (infinite width) was the global default for FilledButton and collapsed titles in Rows (F2). **Fixed 2026-06-28** — theme now uses `Size(0, 48)` like other button classes. Full-width primary actions should use explicit `SizedBox(width: double.infinity)` where needed.

---

## 1. The headline gap: there is no money and no estimate on the mobile RO

This is the single biggest reason a mobile operator still reaches for the desktop.

Observed on every RO:

- The RO section tabs are **Overview · Concerns · Inspection · Conversations · History**. **There is no Estimate tab.**
- The header shows inspection progress (e.g. `15/35`) and status, but **no dollars anywhere** — no estimate total, no approved total, no balance due.
- Concern recommendations render **quantity only** ("R & R RADIATOR · 1.50", "BrakeBest Disc Rotor · 2.00") with **no price, no extended total, no parts/labor split**.

Consequence: an advisor or owner cannot answer the two questions that run a shop — *"What does this job total?"* and *"What's approved vs. waiting?"* — from the phone. That is a guaranteed desktop trip.

The mobile API already exposes `send-estimate` and `send-payment` **links**, so the shop can text the customer a portal estimate/payment — but the operator themselves cannot **see or build** the estimate on the device.

---

## 2. The RO command bar is technician-shaped for everyone

The bottom command bar is the same for advisors and owners as for technicians:

`Photo · Finding · Conversation · Complete Item · More`

These are **production/inspection** verbs. For an advisor/owner standing at the counter the missing primary verbs are:

- **Message customer** (exists as "Conversation", but reactive — no "Text customer" when there's no thread)
- **Send estimate** (API exists, not surfaced in the bar)
- **Send / take payment** (send-link API exists; in-person capture does not)
- **Approve work** / record approval on the customer's behalf
- **Assign technician** (exists on the Work list row, not in the RO bar)

The command bar should be **role-aware** (the projection already knows `profile` = technician/advisor/staff) so the right four verbs are within thumb reach for the person actually holding the phone.

---

## 3. "More" duplicates the tabs instead of holding actions

Tapping **More** opens a bottom sheet of: **Production · Concerns · Overview · History** — i.e. the same section navigation already present in the tab strip. It carries **zero actions**. It is pure redundancy and a dead tap. "More" should hold overflow **actions** (assign tech, send estimate, take payment, change status), not re-list sections.

---

## 4. Overview ↔ Concern data mismatch

- The **Overview** "Recommendations" panel says **"No recommendations queued yet."**
- The **Concern** under that same RO shows **"Recommendations (4)"** with line items.

Same RO, two surfaces, contradictory answers. The overview "recommendations queue" is inspection-derived (items flagged needs-attention/failed) while the concern list is the estimate-side recommendations — but the operator has no way to know that. They read as a bug. Either reconcile the vocabulary or label the source on each.

---

## 5. Timeline noise

- The Overview timeline shows **"Repair order opened" twice** in a row (a visit entry + an operational-event entry for the same moment).
- Entries are dense but unscannable: every row is left-aligned text with a small dot; status changes ("Concern disposition changed · Previously Draft") read like logs, not like a story an owner skims.

De-dupe same-moment open events and give the timeline a lighter, skimmable rhythm (group by day, quiet metadata).

---

## 6. Navigation / continuity (good, with one rough edge)

Strong, keep:

- Single back path is fixed — drilling into a concern/conversation shows one back arrow that returns to the RO shell (no more stacked AppBars), with a persistent compact identity strip (vehicle · customer · status · progress).
- Connected chips (customer / vehicle) under the header make the RO a hub, not a dead end.

Rough edge:

- Opening an RO from the Work list lands on **Overview** (technicians still land on Inspection). NEXT banner still suggests where to go next.

---

## 7. Density / hierarchy

- Inspection progress (`15/35`) is visible and good; but the **single most important number for an advisor — the estimate total — is absent**, so the hierarchy currently elevates inspection over money on a surface advisors live in.
- Recommendation tiles on concern cards now carry **subtotal** when priced lines exist; concern detail lists qty, unit price, and total per line.

---

## 8. Shop-In-A-Phone gap matrix

What can be completed on the phone today vs. what still forces a desktop browser.

| Capability | Mobile API | Mobile UI | Verdict |
|---|---|---|---|
| View RO context (customer, vehicle, status, inspection progress) | ✅ | ✅ | **On phone** |
| Inspect vehicle item-by-item, record findings + photos | ✅ | ✅ | **On phone** |
| Update concern production status / notes | ✅ | ✅ | **On phone** |
| Assign technician | ✅ | ✅ (RO command bar) | **On phone** |
| Message customer / view conversation | ✅ | ✅ | **On phone** |
| Send estimate **link** to customer | ✅ | ✅ (command bar) | **On phone** |
| Send payment **link** to customer | ✅ | ✅ (command bar, when balance due) | **On phone** |
| See estimate totals / money on the RO | ✅ | ✅ (header + Estimate section) | **On phone** |
| View estimate line items (parts/labor, pricing) | ✅ | ✅ (Estimate section, grouped by concern) | **On phone** |
| See approved vs. waiting (ARO) split | ✅ | ✅ (Estimate card, when work is recommended) | **On phone** |
| **Build / edit the estimate (add parts, labor, pricing)** | ✅ | ✅ (add, tap-to-edit, delete; labor + part v1) | **On phone** |
| Record / approve work on customer's behalf | ✅ | ✅ | **On phone** (advisor/owner — in-place concern disposition) |
| **Take / record an in-person payment** | ❌ (link only) | ❌ | **Desktop** |
| Change RO status / lifecycle transition | ✅ | ✅ | **On phone** (advisor/owner — Overview lifecycle control) |
| Close / invoice the RO | ✅ | ✅ | **On phone** (advisor/owner — close Paid/Lost; final invoice issues server-side) |
| Add / remove concerns on an existing RO | ✅ | ✅ (command bar + concern detail) | **On phone** |
| Scheduling / appointments | ❌ | ❌ | **Desktop** |

The pattern is clear: **production (technician) work is fully on the phone; advisor money work is largely on the phone** — estimate line entry closed the largest remaining gap. In-person payment capture and scheduling still desktop.

---

## 9. Proposed redesign order (remove the most operator thinking first)

Sequenced so each step removes a guaranteed desktop trip. Server stays authoritative; financial math flows through `EstimateTotalsCalculator` — mobile only renders projections.

1. **Money on the RO (read-only first).** ✅ **Done.** Estimate total in the header; **Estimate** section lists line items (qty, unit price, extended total) grouped by concern with the parts/labor/fees/tax breakdown and balance due. Approved vs. waiting (ARO) split shows on the Estimate card when work is still recommended. Pure projection through `MobileEstimateProjection` / `EstimateTotalsCalculator` (GET-safe reads; `approvedTotalsForRead` / `recommendedTotalsForRead` never recalculate or persist).
2. **Role-aware command bar.** ✅ Advisor verbs (Message / Send Estimate / Send Payment / Assign tech) are surfaced via the existing `profile`; technician verbs (Photo / Finding / Complete Item) for production.
3. **Replace "More" with actions, not tabs.** ✅ RO command bar is role-aware with real actions; app-level More tab is settings/overflow only — not section duplication on the RO.
4. **Reconcile Overview ↔ Concern recommendations** ✅ Overview panel labeled **Inspection follow-ups** (inspection-derived queue); concern **Recommendations** remain estimate-side lines with prices. **Timeline** de-dupes synthetic open events server-side; mobile groups by day with time-of-day labels.
5. **Approvals + in-person payment capture** (needs product decision — observe first per pressure-first doctrine; the send-links already cover the remote path).
6. **RO lifecycle transitions on mobile** ✅ Overview lifecycle control ships status moves and close-out for advisors/owners.

Items 1–4 are projection/UI work against authority that already exists. Items 5–6 are new authority surfaces and should be confirmed before building.
