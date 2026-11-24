# Inspection coverage vs evidence — Owner review

**Stop for merge authorization. No production cutover without an explicit Deploy command naming artifact + target.**

## Problem (production RO #716)

Technician completed Standard Vehicle Inspection with four Yellow brake-pad points (~5 mm), notes, and measurements. ARK showed **43 of 47** because supporting photos (template `when_not_green`) were folded into coverage / closeout unfinished work.

## Contract

See `docs/inspection/inspection-coverage-evidence-contract.md`.

| Layer | RO #716 expected after fix |
| --- | --- |
| Coverage | **47 of 47** (Needs Review — Yellow findings) |
| Evidence | 4 documentation warnings: `Photo missing — Yellow finding recorded` |
| Closeout | No `inspection_in_progress` outstanding item; Completed / Ready for Pickup without unfinished-inspection acknowledgment |
| Publication | Not ready for customer publication until photos (or shop policy change) |

Yellow findings remain Yellow. No fake photos. Production RO not modified by this work.

## Template policy (owner decision)

Existing Corner / Standard photo policies are **unchanged**. Supporting photos stay required for documentation / publication readiness.

Opt-in only: `builder_meta.coverage_requires_photo_when` makes a photo mandatory for **coverage**. No existing template sets this.

**Owner question:** Keep supporting photos as documentation (current fix), or mark specific procedures as coverage-blocking via `coverage_requires_photo_when`?

## Tests

`php bin/pest tests/Feature/Operations/InspectionCoverageEvidenceContractTest.php` — 13 passed  
Also: Corner, Posture, Closeout acknowledgment, Section walk, AuthorizationIntegrity — passed on this tip.

Pre-existing flake: `InspectionTemplatesV1Test` GVI archive assertion fails on `origin/main` baseline as well (not introduced here).

## Production baseline (live Tabler UX — do not roll back)

| Layer | SHA |
| --- | --- |
| SaaS | `5eaf8f0c04aa043ca4eccbc53f13a37868facc34` |
| Core pin | `960b0080917fe06b20766e7e793113e18883c111` |

This branch is from Core `origin/main` (`960b0080…`). Customer publication is enforced via `GuardInspectionCustomerPublication` on portal-link mint and send-inspection. Staff preview remains ungated. Existing portal tokens stay resolvable.

## Rollback

Revert the Core merge / redeploy previous SaaS pin. No migrations. Prior inspection rows unchanged.
