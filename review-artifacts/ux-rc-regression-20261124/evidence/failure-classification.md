# Failure classification — UX RC vs overnight baseline

## Confirmed remediated this gate

| Failure | Class | Action |
|---------|-------|--------|
| Core `AdvisorHomeBoardTest` (7) | **Existing** (DOM contract drift after Job Board #253/#256; present on overnight `9cc85289`) | Updated assertions to Tabler list/cards contract; **14/14 PASS** on candidate. Product unchanged. Commit `b6aa4588`. |
| SaaS `RepairOrderWorkflowTest::test_each_shop_keeps_its_own_labor_rate_setting` | **Existing** settings-contract drift (Core requires `standard_hourly_rate`) | Added field to test payload; **OK 1 test / 21 assertions**. Commit `83df07d2`. |

## Overnight baseline carry-forward (not UX-pack regressions)

| Area | Class | Notes |
|------|-------|-------|
| Communications Tabler / device workspace (~18) | Existing / Shop-adjacent | Overnight P1/P2; out of Core-only gate |
| Demo / Daily Ops / Owner settings 302s | Existing / environmental | Overnight P2 |
| SaaS MessageDelivery / BookAppointment / ShopSchedule / QzPrinting | Existing P2–P3 | Present on baseline `df735e40` |
| `RepairOrderFinancialPresenterBalanceAxesTest` query budget | Known baseline P3 | Not cents integrity |

## Staging environmental (not candidate product defect)

| Issue | Class | Notes |
|-------|-------|-------|
| Demo `/demo/enter` staff-seat exhaustion | Environmental | Guest assignments filled 10-seat location; freed for UI verify |
| Demo IP rate limit | Environmental | Cleared in tenant context for verification |

## Pending suite totals

Filled when full Core Pest + SaaS PHPUnit + financial re-run complete.
