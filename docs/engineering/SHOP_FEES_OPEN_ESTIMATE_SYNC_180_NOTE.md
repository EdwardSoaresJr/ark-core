# Shop Fees open-estimate sync residual (#180 note)

**Status:** Documented for owner review. Not changed in #180.  
**Scope:** Narrow finding from the #180 implementation-readiness audit.

## Behavior

`ShopFinancialSettingsController::updateFees()` stamps living shop-fee profiles, then still calls `syncOpenEstimateDocuments()`:

```php
// Forward-only: do not recalculate living ROs when fee settings change.
$this->syncOpenEstimateDocuments();
```

`syncOpenEstimateDocuments()` → `EstimateDocumentService::refreshOpenDocumentsForShopSettingsChange()`.

## Impact

Shop Fees saves may refresh open estimate **document** snapshots after the fee-profile stamp. That is separate from recalculating living RO line money, but it can still rewrite open estimate document payloads without an explicit advisor document action.

Billing Classes / Labor Class Settings paths intentionally do **not** call this sync after commercial stamps.

## Recommended follow-up

Track as its own issue. Safest isolated correction is to remove or gate the Shop Fees `syncOpenEstimateDocuments()` call behind explicit document-refresh intent, with focused regression coverage that open estimate snapshots stay unchanged when only shop fee settings change.

Do not bundle that change into #180 Labor / Billing Classes acceptance.
