# Inspection lifecycle policy — Stage A

**Status:** Implemented (closeout gate)  
**Follow-on:** Stage B — Tabler Shop Settings UI, visit-type rules, safer reset/history

## Authority

| Concept | Owner |
|---------|--------|
| Obligation (optional / recommended / required) | `InspectionRequirementResolver` |
| Checklist coverage | `InspectionPostureProjection` (template points only) |
| Evidence gaps | `InspectionPointCompletion::evidenceGaps` |
| Closeout unfinished work | `RepairOrderOperationalOutstandingWork` |

## Stage A behavior

- Shop default: **Optional** (`shop_settings.inspection_lifecycle_policy`, default `optional`).
- RO override: nullable `repair_orders.inspection_lifecycle_policy`.
- Waiver: `inspection_waived_at` / `by` / `reason` clears Required closeout gate.
- **Optional / Recommended / waived:** incomplete or reset inspections **do not** create closeout unfinished-work items.
- **Required:** never started or in-progress checklist coverage can still challenge Completed / Ready for Pickup / paid close (existing acknowledgment path).
- Freeform / Other Findings are **not** checklist coverage and do **not** force In Progress after a blank or reset walk.
- Reset clears checklist facts (including selected observations) and `started_at`; freeform findings remain.

## Production RO #1842

After Stage A deploy: Optional shop (default) → Completed / Ready for Pickup without an unfinished-inspection acknowledgment, even with a blank reset walk + Bad Battery freeform finding. Do not mutate the RO in verification.
