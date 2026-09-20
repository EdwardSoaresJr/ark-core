# BUG: Alex Rivera RO production failure

**Status:** Open · evidence-gathering
**Stream:** Bug (does not block ARK Staff product development)
**Opened:** 2026-06-27
**Severity:** High (acceptance-test gate for ARK Staff RO workspace)

## Summary

Opening Alex Rivera' test repair order in ARK Staff (mobile) produces an error
on real production data. Synthetic/happy-path data does not reproduce it.

## What we know

- RO workspace happy path is **green**: `tests/Feature/Mobile/MobileApiTest.php`
  RO/workspace/concern tests pass (13 tests / 148 assertions).
- The structural projection (`RepairOrderWorkspaceProjection`) is sound.
- Therefore the failure is **almost certainly data-specific** — a malformed,
  partial, or legacy field on this particular RO / customer / vehicle row.

## Leading hypotheses

1. **Null cast in mobile model parsing.** Flutter models used non-null casts on
   fields that can be null/legacy in production, e.g.:
   - `WorkCard.status` / `status_label` (`as String`)
   - `WorkCard.repair_order_id` / `CustomerWorkspaceRepairOrder.repair_order_id`
     / `CustomerWorkspaceCustomer.id` (`as int`)
   - `MobileUser.id` / `name` / `email`
   These now use defensive coercion (`lib/utils/parse_utils.dart`). If the crash
   was client-side parsing, this likely resolves it.
2. **GET-path mutation.** `RepairOrderWorkspaceProjection::ensureInspectionReady`
   seeds/applies inspection templates during a GET. On a legacy RO with partial
   inspection state this could throw. (Also violates `ark-read-write-rule`.)
3. **Missing action routes.** `mobile_api.dart` `sendEstimateLink` /
   `sendPaymentLink` call `/repair-orders/{id}/send-estimate` and `/send-payment`
   which are not registered in `routes/api.php` → 404 if triggered from the RO
   workspace. Action-triggered, not load-triggered.

## Evidence still needed (from Edward)

- Production stack trace from `storage/logs/laravel.log` for the failing request, OR
- The RO number + the exact error text/screenshot shown in ARK Staff, OR
- Whether the error appears on **load** vs after tapping an **action** (estimate/payment).

## Resolution checklist

- [ ] Reproduce with the real RO id (needs production data access / stack trace)
- [ ] Confirm whether client-side parse hardening already resolves it
- [ ] Move `ensureInspectionReady` writes off the GET path (read/write rule)
- [ ] Register or remove `send-estimate` / `send-payment` mobile routes
- [ ] Add a regression test seeded with the offending data shape
