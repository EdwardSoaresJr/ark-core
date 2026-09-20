# Tekmetric + AutoVitals Estimate / Inspection Flow

Source: https://www.youtube.com/watch?v=YtxK6oCebhs

Note: This video includes an integration workflow. Use only the core daily shop operation patterns: estimate creation, technician assignment, inspection handoff, review, and sync-back.

## Workflow Sequence

- Create a new estimate.
- Assign a technician from the estimate/RO context.
- Technician performs the inspection.
- Advisor reviews and transfers inspection/service recommendation information.
- Estimate data syncs back into the repair-order workflow.

## Pacing Observations

- The workflow moves between advisor and technician responsibilities without treating them as unrelated CRUD screens.
- The advisor flow depends on handoff clarity: build estimate context, assign technician, receive inspection/recommendation output, review before committing.
- Review is an explicit step before transfer/sync, which protects estimate authority.

## Review/Edit Posture

- Editing is task-driven: add estimate, assign tech, review findings, transfer recommendations.
- Review posture appears before data becomes part of the authoritative estimate.
- The pacing reinforces that technician findings support the estimate but do not replace advisor review.

## Language

- Creating a new estimate
- Assigning a tech
- Perform the inspection
- Review and transfer
- Jobs / service recommendations
- Sync back

## ARK Implications

- ARK should preserve advisor authority over estimate composition while making technician findings easy to pull forward.
- Inspection findings and customer concerns should be reviewed before becoming sellable estimate lines.
- Assignment, inspection, recommendation, and estimate mutation should feel like one workflow sequence.
