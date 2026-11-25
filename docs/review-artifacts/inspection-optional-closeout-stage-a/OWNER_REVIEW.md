# Stage A — Optional inspection closeout — Owner review

## Root cause (RO #1842)

1. `RepairOrderOperationalOutstandingWork` treated any **In Progress** checklist posture as unfinished work for Completed / Ready for Pickup / paid close.
2. `InspectionPostureProjection` treated **any** non-`not_checked` item (including freeform **Bad Battery**) as inspection “started,” so a reset walk stayed In Progress at 0/47.
3. There was **no Optional policy** — every incomplete inspection acted like a soft mandatory gate.

## Minimal correction

Core tip: `96145550379404ac05385dc5729c40a9abab12d8` (PR #262)  
Base: production-descended `origin/main` @ `982e84a8` (coverage/evidence already live).

- Default `InspectionRequirement::Optional`
- Outstanding inspection item only when **Required** (and not waived)
- Checklist coverage ignores freeform findings
- Reset clears checklist + `started_at`; keeps freeform

## Tests

- Stage A matrix: 8 passed  
- Closeout + coverage evidence + posture: 52 passed  
- AuthorizationIntegrity: 31 passed  

## Staging / production

| Item | Value |
| --- | --- |
| Migration | Additive policy/waiver columns; default Optional |
| Rollback Core | previous main tip before merge |
| Rollback SaaS | prior pin SHA at cutover |
| Production | **Not authorized by this document** |

## RO #1842 after deploy (expected)

Closable to Completed / Ready for Pickup **without** unfinished-inspection acknowledgment (other gates unchanged). Do not auto-mutate the RO.
