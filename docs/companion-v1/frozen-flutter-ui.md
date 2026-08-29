# Frozen — Flutter UI only

**Frozen:** `ark-mobile` (Flutter) — **do not** incrementally fix, refactor, or extend the current UI during Companion v1 design.

**Not frozen:** Laravel backend · `/api/mobile/*` · new APIs when the product spec requires them.

---

## Why UI is frozen

Earlier iterations modified a product it never fully understood. Companion v1 is designed **first**, built **second** — in new Flutter (or clean branch), not by patching the old shell.

---

## Backend is open

If a screen spec needs data the API doesn't expose — **add the endpoint**. Do not compromise the screen to match legacy API shape.

Read existing mobile API for reuse; extend arksmsv2 when the product demands it. No architecture sprint required — ship what the spec needs.

---

## Legacy app is reference only

- What exists today (`docs/mobile/ark-mobile-ux-audit-v1.md`)
- What APIs already work
- What **not** to repeat (nested Scaffolds, lost identity on drill-in)

It does **not** define navigation, hierarchy, or visual design for Companion v1.

---

## After design gate

Build Flutter from [`screens/`](screens/) specs. Legacy repo may donate HTTP client patterns only.
