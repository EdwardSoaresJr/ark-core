# Production preservation — GO / NO-GO (pricing + tax release)

**Status: NO-GO for production.**  
#190 is not merged. Production was not probed, cloned, or migrated under this instruction.

## Owner gates (all required before any production deploy)

1. Owner accepts and merges Core #190 after CI stays green on the tip under review.
2. Fresh verification of **currently running** production SaaS + pinned Core SHAs (do not assume main/staging).
3. Production→candidate delta ledger (ancestry, hotfixes, cherry-picks) with feature-preservation PASS evidence.
4. Isolated production-shaped clone restore + candidate apply + pre/post row/config/financial-cent compare.
5. Migration rehearsal on that clone (this Slice B tip adds **no** DB migrations; bundled release may still include earlier additive migrations from #189 / Shop Fees).
6. Explicit subsequent owner message authorizing production deploy naming artifact + target.

## What is proven on Core #190 (staging / test only)

| Gate | Result |
|---|---|
| Branch tip under review | (fill at push) |
| Mergeable vs main `018ef6a` | MERGEABLE / CLEAN at last check |
| Focused Tests / lint / security / communications-boundary | PASS on prior tip; re-run after this commit |
| Regression pest on PR | Often SKIPPED by workflow policy — local financial subset **98 passed** (`TaxJurisdictionSliceB`, Slice A tax, Shop Fees, PricingEngineSafety, AuthorizationIntegrity, ApprovedWorkChangesAfterAuthorization) |
| Affirmative jurisdiction confirmation | PASS (`accepted` + checkbox) |
| Post-auth tax increase → supplemental → invoice ceiling | PASS |
| E2E cent reconciliation (builder→estimate→PDF→email→portal→invoice→PDF) | PASS |
| Schema-1 tax + Shop Fees schemas + approved ceilings | Preserved in focused suite |
| New Core migrations in #190 | **None** (JSON stamp / catalog only) |
| Slice C / SaaS lookup / itemized components | Explicitly DEFERRED; safe unused |

## Production pins — not freshly verified

Documented historical pins (stale; **must re-verify** before cutover):

| Source | Claim |
|---|---|
| `docs/engineering/RELEASE_PROTOCOL.md` (20 Oct 2026) | `lugsnplugs.arksms.com` Core `d926813`, Shop `04f31a7` |
| `RELEASE-COORDINATION-lnp-2026-10-22.md` | Alternate recorded pins (`7905d34` / `803ee5a`, later `aa1ec81` / `2215166`) |
| Local `ark-saas` checkout HEAD | `04f31a7…` (workspace only — **not** proof of live production) |

**FAIL evidence:** actual live production SHA/runtime/features/migrations were **not** established in this session (production hosts untouched by design).

## Feature-preservation ledger (candidate intent)

| Production behavior | Candidate | Risk |
|---|---|---|
| Labor / Parts matrices | Unchanged by #190 | Low if #189 already on path; verify ancestry includes prod hotfixes |
| Shop Fees (incl. applies_to) | From #189 on main | Confirm prod already has or will receive #189 safely |
| Tax settings | Additive schema-2 stamp + catalog fields | Existing schema-1 ROs remain readable |
| Billing Classes | Untouched | — |
| Approval ceilings / supplemental auth | Extended coverage for tax jurisdiction change | Covered by tests |
| Issued invoice immutability | Untouched path; invoice uses approved work | Covered by supplemental invoice test |
| Booking 2.0 / payments / comms | Out of #190 scope | Must re-smoke on SaaS RC after Core pin |
| Tenant financial config | No seeder/reset in #190 | Still require clone rehearsal for bundled release |

## Reversibility

- Code rollback of #190 alone: feasible (no Slice B migrations).
- Bundled release with earlier additive migrations (`shop_fee_applies_to`, tax catalog JSON shape): **code rollback ≠ DB rollback**. Forward-fix plan required; do not restore a stale backup over new financial transactions without explicit recovery authorization.

## SaaS Phase 3 (blocked until #190 merge)

After merge only:

- Pin SaaS staging to merged Core SHA.
- Audit SaaS prod vs RC.
- Clone rehearsal, flags, rollback plan, GO/NO-GO with exact pins.

## Recommendation

| Target | Verdict |
|---|---|
| Merge #190 after owner review of this tip | **CONDITIONAL GO** (Core-only; CI green; no prod) |
| Alpha staging pin of this tip | **GO** if owner wants staging evidence refresh |
| Production deploy / migration / tenant cutover | **NO-GO** until gates 1–6 above |
