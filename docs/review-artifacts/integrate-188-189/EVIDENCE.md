# Integration gate — PR #188 into main + rebased PR #189

**Do not merge #189 until owner accepts this candidate.**  
No production deploy / production migrations / tenant cutover.  
Do not start Tax Slice B until this candidate is accepted.

## Main after #188

| Item | Value |
|---|---|
| Merge strategy | Merge commit (`gh pr merge 188 --merge`) — same pattern as #187 |
| Main SHA | `17cd34046a669e3f082d04fd5ee9e58566f40d47` |
| Merged PR | https://github.com/EdwardSoaresJr/ark-core/pull/188 |
| Merged at | 2026-11-12T18:09:04Z |
| Pre-merge checks | Tests focused/targeted/lint/security/comms-boundary **SUCCESS**; Regression pest **SKIPPED** (not counted as pass) |

## Integrated candidate (#189 rebased onto main)

| Item | Value |
|---|---|
| Branch | `feat/tax-profiles-slice-a` |
| Tip | `f31d62a7844e3fb47290ce7763f21ff4dacc811d` |
| Base | `origin/main` @ `17cd340` |
| PR | https://github.com/EdwardSoaresJr/ark-core/pull/189 |
| Rebase | Clean (no conflict hunks). Verified both fee + tax surfaces remain. |

## Overlap inventory (both kept)

- `ShopFinancialSettingsController` — `updateFees` (`percent`/`flat`/`per_concern`) **and** tax profile store/component/active
- `ShopSettings` — fee mode helpers **and** `taxProfiles()` / presentation
- `shop.blade.php` — Shop Fees three-method UI + conditional cap **and** Tax panel + shared 240px master-detail styles

## Focused tests

Local (tip `f31d62a`): **84 passed** / 834 assertions  
(`ShopFee*` + tax Slice A + Labor/Parts/Tabler + forward-only + ShopSettings tax cases)

GitHub Focused Tests: **success** — https://github.com/EdwardSoaresJr/ark-core/actions/runs/43276891172

## Full Regression

See [REGRESSION.md](./REGRESSION.md).

- GitHub: https://github.com/EdwardSoaresJr/ark-core/actions/runs/43277230314 — **success**
- 4553 tests, **0 new regressions** vs known-failure baseline (205 known still failing)

## Staging pins (Alpha)

| Pin | SHA |
|---|---|
| Alpha Core | `f31d62a7844e3fb47290ce7763f21ff4dacc811d` |
| Alpha SaaS | `eb859c2159a1821d87e51721698d4608c3bb20c1` |

Staging only. `tenants:migrate --tenants=alpha` → nothing to migrate (fee + tax columns already present).

## Staging verification (Alpha @ `f31d62a`)

| Check | Result |
|---|---|
| Shop Fees percent + cap | Pass — `fees-percent-desktop.png` (8% / $45 cap) |
| Shop Fees flat-per-RO | Pass — `fees-flat-desktop.png`; **cap hidden** (`display: none`) |
| Shop Fees per-concern + cap | Pass — `fees-per-concern-desktop.png` ($15 / concern, $60 cap) |
| Tax Profiles add | Pass — added `New tax profile` (`profile-tkfbxqxr`); toast “Tax profile added.” |
| Tax Profiles deactivate | Pass — badge **Inactive** after deactivate |
| Labor / Parts / Tax nav width | Pass — each measured **240px** |
| Existing RO line totals | Pass — before/after snapshot identical for ROs 39–43 (tax/fee/total cents unchanged) |

Fee + tax **toggles restored off** after UI checks. Inactive “New tax profile” left in catalog as proof of add/deactivate.

## Screenshots

- `fees-percent-desktop.png`
- `fees-flat-desktop.png`
- `fees-per-concern-desktop.png`
- `tax-profiles-desktop.png`
- `labor-nav-240-desktop.png`
- `parts-nav-240-desktop.png`

## Follow-ups (tracked, not implemented)

See [FOLLOWUPS.md](./FOLLOWUPS.md):

1. Configurable Shop Fees eligibility (labor / parts / both)
2. Tax Slice B — RO jurisdiction + service address (after this candidate is accepted)
