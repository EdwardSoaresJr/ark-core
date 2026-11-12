# Production preservation — GO / NO-GO (pricing + tax release)

**Production deploy: NO-GO.**  
**Core #190 merge: GO for owner approval** (regression-proven tip `bf011171`).

## Actual production (2026-11-12 read-only)

| Item | Value |
|---|---|
| Host | `ark-saas-prod-1` (`165.22.174.183`) |
| URL | `https://lugsnplugs.arksms.com/up` = 200 |
| SaaS (`ark-stancl`) | `7b5f18059863824aba7e3358d6507275bc29d073` |
| Core | `affb1869e8fff581dfa874dda51ce9d08e98b352` |
| Gap to #190 tip | 91 commits / 8 migrations (see `RELEASE_MANIFEST.md`) |

LugsNPlugs: tax on @ 8.200%, no tax_profiles catalog yet, shop fees enabled, 678 ROs / 165 invoices / 397 estimates observed.

Nightly encrypted backups present through `20261112T033001Z` with restore-verified sidecars. **Isolated restore + upgrade rehearsal not executed.**

## Owner gates before production

1. Explicit merge of #190.
2. Isolated restore of a verified production backup.
3. Apply exact Core (+ agreed Stancl) candidate; pre/post financial/config compare.
4. Smoke booking/RO/portal/invoice/email-preview/payments without live sends or capture.
5. Explicit deploy authorization naming artifact + target.

## Recommendation

| Target | Verdict |
|---|---|
| Merge #190 | **GO** (await explicit owner merge message) |
| Production | **NO-GO** until gates 2–5 |
