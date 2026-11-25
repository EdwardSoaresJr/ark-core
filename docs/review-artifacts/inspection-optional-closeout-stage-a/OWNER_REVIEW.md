# Stage A — Optional inspection closeout — Owner review

**Production deploy: not authorized by this document.** Staging verified. RO #1842 / #716 not mutated.

## 1. Root cause (RO #1842)

Offending branches:

1. `RepairOrderOperationalOutstandingWork::inspectionItem` treated any **In Progress** posture as unfinished work for Completed / Ready for Pickup / paid close — no Optional policy existed.
2. `InspectionPostureProjection` counted **any** non-`not_checked` item (including freeform **Bad Battery**) as inspection “started,” so a reset walk with 47 `not_checked` template points stayed In Progress at 0/47.
3. Freeform findings were interchangeable with checklist coverage for closeout gating.

## 2. Minimal correction

| Layer | SHA |
| --- | --- |
| Core merge (#262) | `91b88daac5042f7a506fed7962f06cd9aeed735f` |
| Feature tip before merge | `c36ad829` (baseline refresh) / `96145550` (fix) |
| SaaS pin merge (#353) | `a13233a8907f59f584bfa1fa67b25b3b85e93130` |
| Core pin inside SaaS | `91b88daac5042f7a506fed7962f06cd9aeed735f` |
| Prior production/staging tip (rollback) | SaaS `d56a9f5eebc20beba8f6699cda97675f2672c7ba` / Core `982e84a8` |

Behavior:

- Default `InspectionRequirement::Optional`
- Outstanding inspection item **only** when Required and not waived
- Checklist coverage ignores freeform findings
- Reset clears checklist + `started_at`; keeps freeform

PR: https://github.com/EdwardSoaresJr/ark-core/pull/262  
SaaS pin: https://github.com/EdwardSoaresJr/ark-stancl/pull/353

## 3. Test evidence

Local (pre-merge): Stage A matrix + closeout/coverage/posture 52; AuthorizationIntegrity 31.

CI on `c36ad829`: lint, security, targeted, communications-boundary, gitleaks, **focused** — all SUCCESS.

Staging synthetic probe on `demo` tenant (`74b28b56-…`), RO id 805:

| Check | Result |
| --- | --- |
| Shop policy | `optional` |
| Post-reset posture | `not_started`, `started=false` |
| Template points | 0 checked / 49 not_checked |
| Freeform Bad Battery | retained (1) |
| `inspection_in_progress` outstanding | **absent** |
| Required RO still gates | **pass** (`inspection_in_progress` present) |
| `https://demo.staging.arksms.com/up` | 200 |

## 4. Staging acceptance

Deployed `a13233a8907f59f584bfa1fa67b25b3b85e93130` to `ark-stancl-staging` (`157.230.172.143`) via `deploy/deploy.sh`. Migration `2026_11_24_230000_inspection_lifecycle_policy` applied to opened tenants. Health / Horizon / Reverb / asset probes OK. Pre-deploy backup: `ark-stancl-20261125T010629Z.tar.gz.gpg`.

## 5. Production rollback

1. Redeploy SaaS `d56a9f5eebc20beba8f6699cda97675f2672c7ba` (`deploy/deploy.sh d56a9f5eebc20beba8f6699cda97675f2672c7ba`).
2. Policy columns are additive; leaving them in place is safe. Do not reverse-migrate production shops unless separately authorized.

## 6. Production readiness verdict

**READY FOR DEPLOYMENT** to `lugsnplugs.arksms.com` pending explicit owner Deploy command naming:

- Artifact: SaaS `a13233a8907f59f584bfa1fa67b25b3b85e93130` (Core pin `91b88daa…`)
- Target: production Stancl (`ark-saas-prod-1`)
- Rollback: SaaS `d56a9f5e…`

After production deploy (when authorized): RO #1842 should close to Completed / Ready for Pickup **without** unfinished-inspection acknowledgment when shop policy remains Optional. Do not auto-mutate that RO.

## Stage B (not in this artifact)

Shop Settings Tabler UI, visit-type policy, authorized RO override/waiver UI, safer reset/history redesign — separate delivery after Stage A.
