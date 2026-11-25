# Inspection lifecycle policy — Stage B

**Status:** In progress (settings + RO override/waiver shipped on branch)  
**Depends on:** Stage A closeout gate (`docs/inspection/inspection-lifecycle-policy-stage-a.md`)

## Architecture decisions

| Topic | Decision |
| --- | --- |
| Canonical obligation | `InspectionRequirementResolver` only |
| Shop default | Settings → Workflow → Inspections policy radios |
| Visit / job categories | **Do not invent.** Existing models are `RepairOrderVisitMode` (Waiting / Drop Off / Shuttle / Tow / On-site) and `RecommendationIntent` (Maintenance / Diagnostic / Repair Verification). No PPI / “general repair” enum exists. Shop-wide policy only in this slice; per-intent maps deferred until product names a real authority. |
| Historical ROs | Shop policy changes do not rewrite RO overrides or waivers |
| Waiver | Admin only; reason ≥ 8 chars; stored on RO |
| Reset | **Restart checklist walk** supersedes active template points (photos/measurements retained on superseded rows), applies a fresh not_checked checklist, keeps freeform active. Uses existing `superseded_at` history (same authority as template change). |

## Shipped in this slice

- Shop Settings Tabler policy UI (`inspection-lifecycle-policy` partial)
- `InspectionLifecyclePolicySettingsController`
- RO Inspect rail: requirement badge, override form, admin waiver
- Controllers/actions for requirement + waiver
- Non-destructive restart via `superseded_at` + re-apply template
- Regression tests: `InspectionLifecyclePolicyStageBTest` + updated reset coverage

## Remaining Stage B

1. Explicit “Abandon draft” vs “Resume” affordances (restart covers redo; abandon/resume UX still thin)
2. Closeout UI copy for Optional vs Required (no red blocking chrome when Optional)
3. Mobile parity for requirement badge / waiver affordances
4. Optional: per-`RecommendationIntent` policy map if owner confirms product need
5. Recoverability report for production RO #1842 checklist photos deleted **before** this non-destructive restart (backup-only; no restore without auth)

## Migration impact

No new migration in this slice. Uses Stage A columns.
