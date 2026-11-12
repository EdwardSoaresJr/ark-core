# Follow-ups after #188/#189 integration

## 1. Configurable Shop Fees eligibility

**Status:** Implemented on the integrated #189 tip (`624ce35` and later evidence tip). HOLD #189 merge until owner review of eligibility + Tax Slice A coexistence.

**Delivered:**
- Setting **Apply shop fees to**: Labor only / Parts only / Labor and parts (default).
- Applies to percent, flat-per-RO, and flat-per-concern in the existing fee engine.
- `ShopFeeProfile` schema 3 freezes `applies_to` on new ROs.
- Schema 1/2 profiles without `applies_to` keep historical Labor+Part+Package eligibility (Sublet/Fee never eligible; Package counts with labor).

## 2. Tax Slice B — RO jurisdiction selection

**Status:** **Do not start** until owner accepts Shop Fees eligibility + Tax Slice A on the same Core pin and decides the #189 merge.

**Intent:** Select tax jurisdiction on the RO, including service address for mobile mechanics and fleet customers (service location, not HQ). Schema 2 immutable snapshot; supplemental auth on tax **increase** only; no separate tax engine.

**Boundary (owner #174 clarification — preserve in Slice B/C plans):**
- Automated tax-jurisdiction lookup belongs in **ARK SaaS only**, not Core.
- Core owns named profiles/components, manual city/ZIP associations, RO jurisdiction selection, frozen calc/exemptions/auth/invoices/docs, and a provider-neutral way to apply externally supplied jurisdiction data.
- SaaS owns SUTS/GIS and other providers, credentials, rate freshness, caching, metering, automatic suggestions, and SaaS entitlements for lookups.
- Core must remain usable offline/self-hosted with manually configured tax profiles. No provider-specific code in Core.
