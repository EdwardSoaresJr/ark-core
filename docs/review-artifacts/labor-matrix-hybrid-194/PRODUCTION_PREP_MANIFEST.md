# Production prep manifest — Labor Matrix Hybrid UX (#194)

**Status:** Draft pending owner Alpha acceptance. Do **not** deploy to `lugsnplugs.arksms.com` until the owner names these artifacts and authorizes cutover.

## Artifacts

| Artifact | SHA / ref |
| --- | --- |
| Core PR | https://github.com/EdwardSoaresJr/ark-core/pull/195 |
| Core commit | `0423ab03e480d8520f60ed49a1544df40ddc123b` |
| Stancl PR | https://github.com/EdwardSoaresJr/ark-stancl/pull/226 |
| Stancl commit (Alpha pin) | `0079ab0a38cf4c38fc9a69990ab72692c817b351` |
| Issue | https://github.com/EdwardSoaresJr/ark-core/issues/194 |

Alpha already runs the Stancl pin above. Production must use a **merged** Stancl main SHA that still pins Core `0423ab03` (or a successor merge commit that contains it), named explicitly in the deploy message.

## Migration assessment

| Check | Result |
| --- | --- |
| New DB migrations | **None** |
| Schema changes | **None** |
| Data backfill | **None** |
| Config / env changes | **None** |
| Pricing engine behavior | Unchanged authorities; UI-only + existing autosave endpoints; new `work-type-rounding` route writes `labor_categories[].rounding_rule` only |
| `allows_modifiers` | Still stored + enforced; control removed from matrix UI |
| Existing ROs / documents | Forward-only profile stamps; settings edits do not reprice open ROs |

## Backup requirements

1. Production deploy must run `deploy/deploy.sh <saas-sha>` which **fails closed** without a verified backup (`ARK_STANCL_BACKUP_VERIFY` per host policy).
2. No special tenant export beyond the standard deploy backup.
3. Confirm pre-cutover Core pin on production for rollback target (record at authorization time).

## Rollback strategy

1. Redeploy previous known-good Stancl SHA (pre-#194 pin) via the same `deploy/deploy.sh` path.
2. No migration reverse required.
3. Shop settings written during Alpha/prod use of the new UI remain valid under the old UI (rates, minimums, rounding, `allows_modifiers`).
4. If a mistaken rate/minimum/rounding edit occurred after cutover, restore from the deploy backup or re-enter via Labor settings — not from RO stamps.

## Out of scope (confirmed)

- Age / rust / brand labor modifier engine
- Sales tax calculation changes
- Payment / authorization redesign
- New VPS / infrastructure
- Production deploy during development (Alpha only until authorized)

## Cutover checklist (after owner ACCEPT)

1. Merge Core PR #195 (if not already on `main`).
2. Merge Stancl PR #226 (or equivalent pin commit on `main`).
3. Owner message: **Deploy the approved release** naming Core + Stancl SHAs and production target `lugsnplugs.arksms.com`.
4. Run production deploy; verify `/up`, Labor settings hybrid columns, sample rate/min/round autosave, open RO stamp unchanged.
5. Record `DEPLOYED AND HEALTHY` only after that verification.
