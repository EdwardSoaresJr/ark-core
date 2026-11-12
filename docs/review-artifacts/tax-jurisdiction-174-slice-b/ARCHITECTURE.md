# #174 Tax jurisdiction — architecture audit & Slice B contracts

**Base:** Core `main` @ `018ef6a` (PR #189 merged)  
**Branch:** `feat/tax-jurisdiction-slice-b`  
**Boundary:** Automated jurisdiction lookup stays in **ARK SaaS only**. Core is provider-neutral and offline-capable.

## 1. What exists after #189

| Contract | Role |
|---|---|
| `ShopTaxCatalog` | Settings catalog: named profiles, components (`state`/`county`/`city`/`special_district`/`other`), cities, ZIP associations, combined-rate projection to legacy shop columns |
| `TaxProfile` schema **1** | RO stamp: `enabled`, `label`, `rate` (combined), `taxable_*`, `schema` — stamped on create / lazy living stamp |
| `EstimateTotalsCalculator::taxCents` | Single tax engine: per-line HALF_UP on taxable base (+ taxable shop fee when enabled) |
| `RepairOrderPricingContext` | Resolves frozen `tax_profile`; terminal ROs never invent from live settings |
| Shop Fees schema **3** | Independent fee engine with `applies_to`; not a second tax engine |
| Approvals | `ApprovedWorkScope` stores line amounts including `tax_cents`; approved read path caps raised lines |

## 2. Address / location inventory (reuse first)

| Source | Fields | Notes |
|---|---|---|
| Shop (`shop_settings`) | `address_line_1/2`, `city`, `state`, `postal_code` | Default in-shop sourcing candidate |
| Customer | `address_line_1/2`, `city`, `state`, `postal_code` | Billing / customer location |
| Appointment | `service_location_type`, `service_address` (JSON) | Mobile/fleet service address already modeled |
| RepairOrder | `tax_profile` JSON only today | **No** RO service-address or tax-selection fields yet |
| Vehicle | identity only | Not a tax authority |

Slice B adds RO tax-sourcing metadata; does not invent a parallel address system.

## 3. Gaps (industry-standard jurisdiction)

| Gap | Slice |
|---|---|
| Freeze full selected profile + components on RO (schema 2) | **B (this PR)** |
| Suggest profiles from city/ZIP/state without auto-assign | **B** |
| Explicit RO profile selection / confirmation + uncertainty warning | **B** |
| Record tax sourcing location kind + optional service address on RO | **B** |
| County associations on catalog profiles | **B** |
| Post-auth tax **increase** cannot raise collectible dollars without supplemental auth | **B** (extend existing approved-dollar path; no second engine) |
| Independent per-component taxable bases / itemized calc | **C** |
| Itemized estimate/invoice/PDF/portal tax lines | **C** (presentation preference already stored) |
| Customer tax exemption evidence ≠ Billing Class | **C** |
| Rate effective-date windows / version timelines | **C+** |
| SaaS automated lookup (SUTS/TaxJar/etc.) | **SaaS only — never Core** |

## 4. Slice B contracts (implement)

### TaxProfile schema 2 (RO stamp)

Extends schema 1 without rewriting history:

- `schema: 2`
- `profile_key`, `profile_name`
- `enabled`, `label`, `rate` (combined, still used by calculator in B)
- `taxable_labor` / `taxable_parts` / `taxable_shop_fees`
- `document_presentation` (`combined` \| `itemized`) — stored; itemized rendering deferred to C
- `components[]`: `{id, name, rate, jurisdiction_type, reporting_code, sort_order, is_active}`
- `associations`: `{state, counties, cities, zip_codes}` snapshot at selection
- `sourcing`: `{location_kind, address?, selection_status, confirmed_at?, matcher_note?}`

Schema 1 stamps remain valid; calculator continues to use combined `rate` + taxability flags.

### TaxJurisdictionMatcher (Core, offline)

Input: optional state/city/ZIP (+ county when present) + shop catalog.  
Output: ranked candidates with match reasons; **never** silently assigns. Ambiguous → require confirmation. Empty → fallback to shop default with uncertainty flag.

### Selection / immutability

- New ROs stamp schema 2 from **default** catalog profile (+ shop as default sourcing).
- Pre-auth profile change: rewrite stamp + recalc through `EstimateTotalsCalculator`.
- Post-auth: living lines may show higher tax; **approved/collectible** totals stay under existing approved-amount ceiling until supplemental authorization. Terminal / issued invoices untouched.
- Live catalog edits stamp null-profile living ROs first (existing Slice A pattern); never rewrite schema 2 stamps.

## 5. Explicit non-goals for this PR

- No provider credentials, HTTP lookups, GIS, or SaaS metering in Core.
- No Colorado-only hardcoding.
- No second tax/pricing/approval engine.
- No production deploy; Alpha staging only after tests.
- No claim of full E2E tax completion until Slice C surfaces land.

## 6. Calculation policy note (penny risk)

Tax is allocated **per line** with `RoundingMode::HALF_UP`. Sum-of-line tax can differ by ±$0.01 from tax-on-subtotal. Customer documents must display **stored line/snapshot cents**, never re-tax from formatted dollars. See `PENNY_RECONCILIATION.md`.
