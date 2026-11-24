# Inspection coverage vs evidence

**Status:** Canonical projection contract  
**Authority:** [Inspection Authority](inspection-authority.md)  
**Workspace:** [Inspection Workspace](inspection-workspace.md)

## Three questions

| Question | Projection | Blocks RO closeout? |
|----------|------------|---------------------|
| Was the point evaluated with required facts? | **Coverage** (`InspectionPointCompletion::isCovered` / posture checked/total) | Yes, when coverage remains in progress |
| Is supporting documentation complete? | **Evidence** (`evidenceGaps`) | No — documentation warning only |
| Ready for internal review / customer publication? | **Review readiness** (`InspectionReviewReadinessProjection`) | Publication may require evidence; closeout does not |

Do not collapse these into one “incomplete” percentage.

## Coverage (addressed)

Counts when:

- A valid condition is recorded (including N/A when permitted)
- Required measurement slots are filled
- Required structured observations for the finding are selected
- Required notes for the finding are present
- Photo is present **only** when `builder_meta.coverage_requires_photo_when` includes the status (opt-in; existing templates do not set this)

Yellow/Monitor and Red/Failed are not inherently incomplete.

## Evidence

Tracked separately via template photo policy (`when_not_green`, `require_photo_when`, `requires_photo`) and scan evidence:

- Required photograph missing → evidence gap message, e.g. `Photo missing — Yellow finding recorded`
- Scan evidence missing → evidence gap (photo or note)

Evidence gaps do **not** reduce coverage checked/total and do **not** create `inspection_in_progress` outstanding work.

## Review readiness

- **Internal review:** coverage complete (or inspection never started)
- **Customer publication:** coverage complete **and** no required evidence gaps

## Template policy

Do not silently rewrite shop Corner / Standard template photo policies. Supporting photos remain configured as today; they move from coverage into evidence unless a shop opts into `coverage_requires_photo_when`.
