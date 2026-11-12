# Follow-ups after #188/#189 integration (not in this assignment)

## 1. Configurable Shop Fees eligibility

**Status:** Tracked only. Do not implement in the integration candidate.

**Intent:** Let a shop choose whether the shop fee applies to **labor only**, **parts only**, or **both**.

**Why deferred:** Keep this integration to one coherent main + staging build (fees + tax catalog). Eligibility changes pricing bases and authorization ceilings; it must not interrupt the merge gate.

**Suggested home:** Follow-on Core issue after #189 lands (or a dedicated fees issue linked from #188).

## 2. Tax Slice B — RO jurisdiction selection

**Status:** Next feature after owner accepts this integrated candidate. Do not start until then.

**Intent:** Select tax jurisdiction on the RO, including service address for mobile mechanics and fleet customers (service location, not HQ). Schema 2 immutable snapshot; supplemental auth on tax **increase** only; no separate tax engine.
