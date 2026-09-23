# Addendum - PartsTech entitlement / integration

**Status:** Investigation only. Do not implement from this file.  
**Date:** 2026-09-15  
**Audience:** ARK Platform v1 production-completeness audit  
**Repos:** Core `arksmsv2` · Platform `ark-cloud` (there is no separate `ark-platform` repo)

PartsTech is the first real commercial entitlement to prove on Platform. Do not invent a dummy feature flag.

Locked boundary:

```text
ARK Platform  - shop entitlement + admin enable/disable
ARK Cloud     - PartsTech credentials, adapter, authorization
ARK Core      - estimating/parts workflow; selected-part snapshots
```

Core must not decide “this shop gets PartsTech because they are Pro.” Core must not hold the shop’s global PartsTech credentials once the managed path is live. Plans may later include the entitlement; the entitlement itself stays discrete.

---

## Why it is not on the floor

The Core integration is **not deleted**. It is gated on shop credentials. Buttons hide when `partsTechCatalogConfigured()` is false.

On Hosted LugsNPlugs (docs snapshot 2026-09-08):

| Layer | State |
| --- | --- |
| Platform entitlement `parts` | **none** |
| Platform transport | **stub** (`ARK_PLATFORM_PARTS_TRANSPORT=stub`) - fake quote lines, no PartsTech network |
| Core client | **none** - no `ArkPartsClient` |
| Core foundry code | **present** - catalog launch, GraphQL cart prep, Pull Quote, import |
| Public Core overlay | `NotConfiguredPartsCatalogLauncher` if that image is used; LNP stays on foundry |

LNP restoration docs already said: do not cut Core over to the stub. That still holds.

Likely floor failure: empty `shop_settings` / env `PARTSTECH_*` after Hosted secret stripping, plus no Platform grant. Not a missing Core feature.

---

## Entitlement key

User intent: discrete `catalog.partstech = enabled | disabled`, not a Starter/Pro hard-code.

What already exists:

| Item | Value |
| --- | --- |
| Service key | `parts` |
| Catalog name | ARK Parts Catalog |
| Implementation | `development` |
| APIs | `GET /api/v1/services/parts/readiness` · `POST …/sessions/prepare` · `GET …/sessions/{cartReference}/quote` |
| Gate | `EntitlementService::assertEntitled($installation, 'parts')` |
| Admin Enable/Disable | **omitted** - `POLICY_SERVICES = ['mail', 'sms', 'payments', 'voice']` |
| Portal shop status | **omitted** - `ShopManagedServiceStatus` lists sms/voice/mail/payments only |

**Recommendation:** keep the existing discrete key `parts`. Do not add a second key `catalog.partstech`. Platform UI label for this shop: **PartsTech Catalog**. Plans, manual grants, beta, and later add-ons all write the same `service_entitlements` row. If a dotted alias is wanted later, map `catalog.partstech` → `parts`; do not fork two entitlements.

VIN decode (`PartsTechProvider` REST `/api/v2/vehicles/decode`) is a different path. Platform already has a planned `vin` service. Do not block catalog restore on VIN.

---

## What previously existed in Core (keep)

Working foundry path:

```text
RO show (edit, non-terminal)
  → Parts Catalog  → POST prepare (GraphQL cart + VIN/YMM) → window.open(app.partstech.com)
  → Pull Quote     → GET preview → import panel → POST import → RepairOrderLine (type=part, matrix sell)
```

| Area | Authority / code |
| --- | --- |
| HTTP / GraphQL | `PartsTechHttpClient` - cookie `POST /api/login`, then `/graphql` |
| Credentials | Shop columns on `shop_settings` (encrypted password + api_key) with env fallback; optional per-user seat on `users` |
| Launch | `PartsTechCatalogLauncher` - VIN/YMM query string, PO `R{shopNumber}` |
| Cart | `PartsTechCartPreparer` + `PartsTechRepairOrderCartLocator` - create/activate/PO/vehicle; 423 session lock |
| Quote | `PartsTechActiveCartQuoteReader` → `PartsTechQuoteLine` |
| Import | `PartsTechQuoteImporter` - concern/work-group, matrix pricing, `EstimateTotalsCalculator`, event `source=partstech` |
| VIN/plate | `PartsTechProvider` in `VehicleIntelligenceManager` (API key; NHTSA fallback) |
| UI | toolbar, import panel, Settings, profile seats, Learn articles |
| Tests | `PartsTechCatalogTest` (16), `PartsTechQuoteImportTest` (16), credential + attribute unit tests |

**Captured on import:** description, qty, cost (`customerPrice` else `price`), part number, vendor, brand, position.  
**Fetched unused:** `listPrice`.  
**Not in ARK:** supplier location, stock, ETA, place-order. Purchase stays in the PartsTech browser UI.

Routes (permission `RepairOrdersManage`):

- `operations.repair-orders.partstech.prepare`
- `operations.repair-orders.partstech`
- `operations.repair-orders.partstech.import.preview`
- `operations.repair-orders.partstech.import`
- `operations.settings.shop.partstech.update`
- `profile.partstech.update`

Core today has **no entitlement check**. Gate is credentials + RO open + permission.

---

## What exists on Platform (do not ship stub to LNP)

`PartsCatalogService` already asserts `parts` and delegates `PartsCatalogTransport`. Default is `StubPartsCatalogTransport`: `ready() === true`, fake catalog URL, stub oil/cabin filters. Tests: `tests/Feature/PartsCatalog/PartsCatalogStubTest.php`.

Copy **payments**, not mail, for the Core wire: `ArkPaymentsClient` + readiness + HMAC already exist. Mail is the doctrinal “provider stays off the Box” precedent; payments is the working client template.

Shop-level PartsTech username/password/api_key belong in Cloud the same way Square lives on `PaymentMerchantConnection`. Optional per-advisor PartsTech seats are operational shopping identity (concurrent carts). They may remain on Core or ride in the prepare payload; they are not the commercial entitlement.

---

## Minimum restore (shortest safe path)

Preserve Core importer, matrices, RO UI, cart-reference `R{n}`, and tests. Move network + shop secrets + entitlement off the Box.

### Platform v1 sequence (insert; do not wait on Square)

Square and PartsTech are different providers. PartsTech is the entitlement proof. Do not keep it parked behind `/go`.

1. **Entitlement surface**  
   Add `parts` to `POLICY_SERVICES`. Admin Enable/Disable on the Box. Grant **active** for LugsNPlugs. Portal row: PartsTech Catalog. No plan matrix.

2. **Cloud PartsTech transport**  
   Lift Core `PartsTechHttpClient` / cart prepare / quote reader behind `PartsCatalogTransport`. Store shop credentials on Platform. `ARK_PLATFORM_PARTS_TRANSPORT=partstech` for LNP only. Stub stays tests/dev. Never entitle LNP onto stub.

3. **Core consumes**  
   Add `ArkPartsClient` (clone `ArkPaymentsClient`). Prepare + quote go through `/api/v1/services/parts/*`. Toolbar shows only when Platform says entitled **and** ready. Keep `PartsTechQuoteImporter` and line snapshots in Core. Stop using shop-global `PARTSTECH_*` on Hosted.

4. **Proof (LugsNPlugs)**  
   Platform ON → Core shows Parts Catalog / Pull Quote → search/select in PartsTech → pricing returns → part on RO. Platform OFF → Core hides access and prepare/quote 403 `not_entitled`.

5. **Later (not this restore)**  
   Nexpart as adapter #2 on the same transport contract. VIN as `vin`. Personal seats if multi-advisor cart lock is still painful. Ordering/stock if the shop asks.

### Explicit non-goals for this restore

- Rebuilding the Core import UI
- Cutting LNP to stub lines
- Hard-coding Starter/Pro
- Putting PartsTech secrets back into Core `.env` as the managed design
- Building Nexpart
- Waiting for a `catalog.partstech` rename before granting `parts`

---

## Tests to add when implementing (not now)

- Platform: grant/revoke `parts` → readiness 200 vs 403
- Platform: real transport refuses when credentials missing (`ready === false`)
- Core: toolbar hidden when not entitled even if leftover shop credentials exist
- Core: import still writes matrix-priced part lines from Platform quote payload
- Round trip: disable entitlement removes access without deleting historical part lines
