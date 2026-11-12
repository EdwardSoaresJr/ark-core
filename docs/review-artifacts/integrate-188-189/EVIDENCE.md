# Integration gate — PR #188/#189 + Shop Fees eligibility (HOLD MERGE)

**Do not merge #189 until owner accepts this candidate.**  
No production deploy / production migrations / tenant cutover.  
**Do not start Tax Slice B** until Shop Fees eligibility and Tax Slice A coexist correctly on the same Core pin.

## What changed on this tip (two distinct scopes)

| Scope | What it is | What it is not |
|---|---|---|
| **Tax Slice A** (prior #189 work) | Named tax profiles catalog, Tax UI, shared 240px Financial Rules nav | Automated jurisdiction lookup; RO jurisdiction selection (Slice B) |
| **Shop Fees eligibility** (this pass) | `applies_to` labor \| parts \| both across percent / flat / per_concern; schema-3 stamp | A second fee engine; Tax Slice B |

## Merge strategy (so eligibility is not lost)

1. **Keep eligibility on PR #189** (`feat/tax-profiles-slice-a`), which already sits on `main` after #188 (`17cd340`).
2. **Do not** open a separate eligibility PR unless owner prefers a split after review — a second PR would rebase away from the integrated fee+tax tip and recreate the #188/#189 conflict surface.
3. Land as one merge commit to `main` only after owner acceptance of **both** scopes on the same SHA.
4. If owner wants history split later: cherry-pick the eligibility commit(s) (`624ce35`…) onto a new branch from `main` **after** #189 tax-only lands — not before.

## Pins

| Item | Value |
|---|---|
| Main after #188 | `17cd34046a669e3f082d04fd5ee9e58566f40d47` |
| Eligibility feature SHA | `624ce3529c75137ed362a1b784bc0cd8fa8585e8` |
| Branch / PR | `feat/tax-profiles-slice-a` · https://github.com/EdwardSoaresJr/ark-core/pull/189 |
| Alpha Core pin | `624ce3529c75137ed362a1b784bc0cd8fa8585e8` |
| Alpha SaaS | `eb859c2159a1821d87e51721698d4608c3bb20c1` (unchanged) |

Staging only. Alpha ran additive `shop_fee_applies_to` migration. Temporary fee/tax UI toggles restored off after screenshots.

## Eligibility audit (legacy line kinds)

| Line type | Historical fee-eligible? | Schema 3 behavior |
|---|---|---|
| Labor | Yes | Counts for `labor` and `both` |
| Part | Yes | Counts for `parts` and `both` |
| Package | Yes (with labor) | Counts for `labor` and `both` only |
| Sublet | **Never** | Still excluded |
| Fee | **Never** | Still excluded |
| Note | No | Still excluded |
| Diagnostics | Labor line + category | Same as Labor (not a separate type) |

Schema 1/2 profiles **without** `applies_to` keep Labor+Part+Package. Existing Alpha ROs 39–43 remain schema 1/2 with no `applies_to` key.

## Compatibility risks

- New ROs stamp schema 3 with `applies_to`; open/closed stamped ROs do not reprice when shop settings change.
- Flat-per-RO still ignores/hides the cap (verified on Alpha UI).
- Changing to labor-only / parts-only affects **new** ROs only.
- Additive migration default `both`.

## Focused tests (local @ `624ce35`)

| Suite | Result |
|---|---|
| `ShopFeeAppliesToTest` | **23 passed** |
| Broader `ShopFee*` + commercial | **71 passed** |
| Tax Slice A + parts/tax forward-only | **15 passed** |

GitHub Focused Tests / Regression: see tip SHA after evidence push; prior tip Regression success recorded in [REGRESSION.md](./REGRESSION.md).

## Alpha staging verification (same Core pin `624ce35`)

| Check | Result |
|---|---|
| Shop Fees percent + **Apply shop fees to** | Pass — `fees-applies-to-percent-desktop.png` |
| Shop Fees flat-per-RO + applies_to; **cap hidden** | Pass — `fees-applies-to-flat-desktop.png` |
| Shop Fees per-concern + applies_to + cap field | Pass — `fees-applies-to-per-concern-desktop.png` |
| Mobile 375 Shop Fees applies_to | Pass — `fees-applies-to-mobile-375.png` |
| Labor | Pass — `labor-same-pin-desktop.png`; nav **240px** |
| Parts Matrix | Pass — `parts-same-pin-desktop.png`; nav **240px** |
| Tax Profiles | Pass — `tax-profiles-same-pin-desktop.png`; nav **240px** |
| Existing RO fee stamps (39–43) | Pass — schema 1/2, no `applies_to` key (historical) |

Fee + tax toggles restored off after UI checks.

## Screenshots (this eligibility pass)

- `fees-applies-to-percent-desktop.png`
- `fees-applies-to-flat-desktop.png`
- `fees-applies-to-per-concern-desktop.png`
- `fees-applies-to-mobile-375.png`
- `labor-same-pin-desktop.png`
- `parts-same-pin-desktop.png`
- `tax-profiles-same-pin-desktop.png`

## Follow-ups

See [FOLLOWUPS.md](./FOLLOWUPS.md). Eligibility implemented; Tax Slice B still blocked pending owner acceptance. Core vs SaaS automated tax lookup boundary recorded on #174 and in tax Slice A evidence.
