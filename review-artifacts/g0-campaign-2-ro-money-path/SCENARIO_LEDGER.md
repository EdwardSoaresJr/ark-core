# G0 Campaign 2 — Scenario ledger

## A. Defect dispositions

### DEF-G0-01 — Shop Fees open-estimate document sync — **Fixed**

- **Cause:** `ShopFinancialSettingsController::updateFees()` stamped living fee profiles, then called `syncOpenEstimateDocuments()` → `EstimateDocumentService::refreshOpenDocumentsForShopSettingsChange()`, rewriting open estimate snapshots without an advisor document action.
- **Fix:** Removed the Shop Fees `syncOpenEstimateDocuments()` side effect. Living RO fee stamps and new-RO stamp-on-create remain. Commit `19aa80a`.
- **Tests:** `ShopFeeFlatDollarTest` — open / presented / approved / closed snapshots unchanged; approval events unchanged; new RO picks up new flat fee.

### DEF-G0-02 — “Card processing is temporarily unavailable.” — **Staging path restored (config)**

- **Cause (not a Core code defect):** Alpha lacked `card-capture` entitlement; `ARK_SAAS_PAYMENTS_DELIVERY` was `record` (FakeCardProcessor); Square was not a visible processor; no shop Square credentials; HostedCaptureClient returned entitlement/provider unavailability.
- **Staging remediation (alpha only):** operator grant `card-capture`; Headquarters Square payment exception; `ARK_SAAS_PAYMENTS_DELIVERY=provider`; adopt staging sandbox Square merchant into shop account store; verified ListLocations ACTIVE + card processing.
- **Result:** readiness `ready=true`, `supports_keyed=true`, `environment=sandbox`, message `null`.
- **Not completed here:** live browser sandbox charge / decline / retry / webhook idempotency suite.

### DEF-G0-03 — Sticky footer intercepts Add Labor — **Fixed**

- **Cause:** Docked `[data-ro-orientation-header]` overlay covered concern compose actions; `.ops-estimate-layout` had insufficient bottom padding; first CSS selector required a direct `page-body` child (footer actually sits under `container-fluid`).
- **Fix:** Clearance padding + scroll-margin in `resources/css/tabler.css` (commits `b7c86fc`, `e2aa732`). Sticky footer design preserved.
- **Proof:** Browser click on Add Labor succeeds after deploy; labor line `#53` Diagnostic 1.00 hr $165.00 saved on RO #20.

### DEF-G0-04 — Mobile truncated rate-mode labels — **Not Run**

Deferred until financial path completion per campaign order.

## B. Commit manifest

| Layer | SHA | Notes |
| --- | --- | --- |
| Core | `19aa80a` | Stop Shop Fees settings from rewriting open estimate documents |
| Core | `b7c86fc` | Clear concern compose actions above the docked RO footer |
| Core | `e2aa732` | Match RO footer clearance to real page-body nesting |
| SaaS pin | `1f19772f` | Pin Core `b7c86fc` (superseded) |
| SaaS pin (live staging) | `664d8485` | Pin Core `e2aa732` |

## C. Financial regression results

| Suite | Result |
| --- | --- |
| `ShopFeeFlatDollar` (8) | Pass |
| `TablerStickyHeaderTest` + fee filter (13) | Pass |
| Broader financial / RO suites after cutover | Not re-run in this stop (focused gates only) |

## D. Full RO scenario ledger

| ID | Step | Expected | Actual | Status | Evidence |
| --- | --- | --- | --- | --- | --- |
| B01 | Open RO #20 | Opens synthetic RO | Opened; status advanced to Building Estimate after labor | Pass | Browser |
| B02 | Select diagnostic concern | Concern actionable | CEL diagnostic concern used | Pass | Browser |
| B03 | Add Troubleshooting labor | Labor saved with rate/minimums | Diagnostic 1.00 hr @ $165.00 line `#53` | Pass | DB + modal screenshot |
| B04–B19 | Parts → estimate → partial auth → hostile edits → sandbox payment → close → immutability | Full money path | **Not Run** — campaign stopped for owner review after blocker removal | Not Run | — |

## E. Payment evidence

| Case | Status | Notes |
| --- | --- | --- |
| Readiness connected (sandbox keyed) | Pass | Backend projection after staging config |
| Successful sandbox payment | Not Run | UI path not completed this stop |
| Decline / retry / duplicate webhook / partial | Not Run | — |

## F. Forward-only verification

Not Run this stop (Phase 5). DEF-G0-01 automated coverage protects Shop Fees document snapshots.

## G. Remaining blockers (release risk)

1. **High** — Complete hostile RO money path with real Square sandbox charge evidence (success, decline, retry, idempotency).
2. **High** — Forward-only settings drill on living RO vs new RO with document/history proof.
3. **Medium** — Broader financial/RO regression after staging pin.
4. **Low** — DEF-G0-04 mobile rate-mode label truncation.

## H. Next gate recommendation

**BLOCKED** — Do not label READY FOR RELEASE. Production remains unauthorized. Staging blockers G0-01/G0-03 fixed and pinned; Square sandbox readiness restored; first diagnostic labor line on RO #20 proven. Owner review required before Campaign 3.
