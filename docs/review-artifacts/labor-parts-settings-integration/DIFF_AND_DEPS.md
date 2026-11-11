# Integration diff + dependency analysis

## SHAs

| Ref | SHA | Role |
|---|---|---|
| `main` (base) | `f7cb79c` | Includes auth #185; **old** Labor tabs |
| Labor redesign tip | `216739a` | Approved Labor/Billing Classes settings |
| PR #186 tip (frozen) | `d96154c`…`7888183` | Parts UI only; still old Labor |
| **Integration tip** | `7d0c9d8` | Labor redesign + Parts on one pin |

## Integration commits (on top of main)

1. `c91550b` — merge Labor redesign onto main+#185  
2. `293df7c` — merge Parts Matrix Labor-aligned UI onto that pin  
3. `7d0c9d8` — retarget tests + owner review artifacts  

Diff vs main: **128 files**, ~+11.7k / −1.3k (before evidence screenshots).

## Why Labor never integrated

- Feature branch `settings/labor-matrix-billing-minimums` was staging-pinned for review.
- **No PR** from that branch to `main` (empty `gh pr list --head settings/labor-matrix-billing-minimums`).
- #185 and #186 continued from `main`, inheriting obsolete Labor navigation.

## Dependencies retained (not overwritten)

- Authorization #185 / approved-scope guard (main base)
- `RepairOrderPricingContext` + commercial / labor / tax / shop-fee profiles (Labor tip)
- Billing minimums + forward-only stamps (Labor tip)
- `LaborRateType::Standard` with stamped-cents preference; null → shop default
- Parts JSON autosave `{ok,committed}` + all-matrix FormData + serialized saves (#186)
- SaaS staging `eb859c21` — Core detached checkout only; **no tenant migration**

## Conflict policy used

| Area | Kept |
|---|---|
| Pricing / RO stamps / billing classes authority | Labor redesign |
| Auth #185 behavior | main |
| Parts settings UI + JSON commit contract | #186 |
| Financial tab strip | Labor redesign set + Parts panel |

## Freeze

- PR #186 remains draft / unmerged.
- Integration review: https://github.com/EdwardSoaresJr/ark-core/pull/187  
- Production / Landlord shop-release: not authorized.
