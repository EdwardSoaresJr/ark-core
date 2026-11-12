# #174 Slice B — Tax jurisdiction (READY FOR OWNER MERGE)

## Final SHA

| Item | Value |
|---|---|
| PR | https://github.com/EdwardSoaresJr/ark-core/pull/190 |
| Tip | `bf011171e4dd843d2897d7dfab1770e61b46573b` |
| Base main | `018ef6aaf6debfd00e502a0decec25c39477d962` (#189 merged) |
| Mergeable | CLEAN / MERGEABLE |
| Required Checks | PASS |
| Full Regression | PASS — 0 new failures vs baseline ([run 43309820708](https://github.com/EdwardSoaresJr/ark-core/actions/runs/43309820708)) |

**HOLD:** Do not merge without explicit owner approval in a subsequent message.

## Phase 1 — #189 merge (complete)

| Item | Value |
|---|---|
| Merged PR | https://github.com/EdwardSoaresJr/ark-core/pull/189 |
| Merge commit / main | `018ef6aaf6debfd00e502a0decec25c39477d962` |

## Phase 2 — #190 deliverables

- TaxProfile schema 2 freeze + schema 1 readability
- Affirmative jurisdiction confirmation (`accepted` + checkbox)
- Matcher suggestions only (never silent ZIP assign)
- Post-auth tax increase → supplemental auth → invoice ceiling
- Stamp-freeze tax labels on estimate/invoice snapshots
- Estimate email body uses document snapshot cents
- E2E cent reconciliation + penny audit (Molly $0.01 not reproduced as stored-cent defect)
- **No #190 DB migrations**

Docs: `ARCHITECTURE.md`, `SURFACE_MATRIX.md`, `PENNY_RECONCILIATION.md`, `REGRESSION.md`, `RELEASE_MANIFEST.md`, `PRODUCTION_PRESERVATION_GO_NOGO.md`

## Production discovery (read-only)

See `RELEASE_MANIFEST.md`.

| Item | Value |
|---|---|
| Host | `ark-saas-prod-1` / `165.22.174.183` |
| Prod SaaS (`ark-stancl`) | `7b5f180…` |
| Prod Core | `affb186…` (#170) |
| Gap to tip | 91 commits / **8 migrations** (none unique to #190) |
| Clone upgrade rehearsal | **NOT DONE** → production **NO-GO** |

## Alpha

| SaaS | Core vendor |
|---|---|
| `eb859c21…` | `a138721…` (behind tip; optional re-pin) |

## Owner next steps

1. Explicitly approve merge of #190 @ `bf011171`.
2. After merge: SaaS pin + isolated backup restore rehearsal before any production deploy authorization.
