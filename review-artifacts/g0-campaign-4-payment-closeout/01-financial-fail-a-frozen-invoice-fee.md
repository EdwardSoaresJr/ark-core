# Fail A — Frozen-invoice fee expected 1500, actual 0

**Classification:** Test debt / outdated assumption after forward-only `shop_fee_profile` stamp.  
**Not classified as:** Production underbilling or invoice freeze defect.  
**Severity if misread:** High (financial integrity). After tracing: **low product risk**; **high test-signal noise**.

## Symptom

`FinancialCoreAuthorityTest` → `issued invoice snapshot stays frozen when estimate changes after partial payment`

- Expected `feesCents()` = `1500`
- Actual = `0`

Broader suite: **129/131**.

## Intended product policy (current)

1. On RO create, `RepairOrder` stamps `shop_fee_profile` from live `ShopSettings` (`RepairOrder` creating callback).
2. `RepairOrderPricingContext::shopFeeProfile()` prefers the stamped profile. Live `ShopSettings` is used only when the stamp is **null** (legacy rows).
3. Changing Shop Fees mid-life must **not** silently reprice an existing open RO. That is the forward-only contract (#117605f and related Shop Fees document-sync work).

## Test setup mismatch

`financialCloseoutRepairOrder()` in `tests/Support/FinancialCloseout.php`:

1. Sets `shop_fee_enabled => false` **before** create.
2. Creates the RO → stamps a **disabled** fee profile.
3. Adds a $150.00 labor line and recalculates.

The failing test then:

1. Enables shop fees at 10% on live `ShopSettings`.
2. Recalculates the same RO.
3. Asserts fees = 1500 (10% of 15000).

Under forward-only rules, step 2 correctly keeps the disabled stamp → fees stay `0`. The assertion assumes pre-stamp live-settings bleed-through.

## Probe confirmation (local)

Isolated probe (deleted after run; 2/2 pass):

- Fees disabled at create → mid-test enable + recalculate → fees remain `0` (stamp wins).
- Fees enabled at create → fees = `1500` as the freeze test needs.

## Historical billability

Under historical **stamped** rules for this fixture RO, the expected $15 is **not** billable: the RO was born with fees off. Under historical **live-settings** rules (pre-stamp), the test’s mid-life enable would have applied. The product moved; the test did not.

## Recent #181–#183 / Shop Fees sync

Those changes reinforce stamp preference and document sync. They do not zero a fee that was stamped enabled. Fail A is not a calculation regression in the stamp path; it is the freeze test colliding with forward-only.

## Proposed correction (not applied — needs separate approval)

Do **not** change the assertion to `0` alone (that weakens freeze coverage). Prefer one of:

1. **Setup fix:** Enable the desired fee policy **before** `financialCloseoutRepairOrder` / create so the stamp carries 10%, then keep freeze assertions at 1500/16500.
2. **Split tests:**
   - Forward-only: mid-life ShopSettings fee enable does **not** change an existing stamped-disabled RO (expect 0).
   - Freeze: create with fees on, issue invoice, mutate estimate, assert issued invoice totals stay frozen at the stamped fee total.
3. **Explicit advisor restamp action** (only if product adds one): test that path separately; do not imply silent restamp.

**Stop condition honored:** no financial business-logic change in this campaign without separate owner approval.

## Baseline note

Comparing against a pre-stamp Core tip in an isolated worktree would show the old test passing via live bleed-through. That “pass” is exactly the behavior forward-only removed.
