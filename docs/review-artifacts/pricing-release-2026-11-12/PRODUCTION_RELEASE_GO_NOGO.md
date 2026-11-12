# Production release — GO / NO-GO (Labor / Parts / Shop Fees / Tax)

**Date:** 2026-11-12 (UTC)  
**Decision: CONDITIONAL GO for production — financial preservation PASS; full HTTP/payment smoke still thin.**  
**Production deploy: NOT authorized by this report.**

---

## Phase 1 — Core #190 merge (DONE)

| Item | Value |
|---|---|
| PR | https://github.com/EdwardSoaresJr/ark-core/pull/190 |
| Final PR head | `97bdbad653c80ca9f71ab1e36022349b07be0e9a` (docs after regression-proven `bf011171`) |
| Required Checks on final head | PASS (focused/lint/security/targeted/comms/gitleaks) |
| Code vs `bf011171` | Docs-only (4 files under `docs/review-artifacts/tax-jurisdiction-174-slice-b/`) |
| **Merge commit (Core main)** | `5dda85ece5c4a2e328e7a37a1344dada5c4b5d62` |
| Merged at | 2026-11-12T22:25:34Z |

No production deploy was performed.

---

## Exact pins

### Production (re-verified read-only 2026-11-12)

| Item | Value |
|---|---|
| Host | `ark-saas-prod-1` · `165.22.174.183` |
| Public | `https://lugsnplugs.arksms.com/up` = 200 |
| SaaS (`ark-stancl`) | `7b5f18059863824aba7e3358d6507275bc29d073` |
| Core | `affb1869e8fff581dfa874dda51ce9d08e98b352` |
| Backup used for rehearsal | `ark-stancl-20261112T033001Z.tar.gz.gpg` (checksum OK, `tenants_complete=yes`) |

### Release candidate (frozen)

| Item | Value |
|---|---|
| Core | `5dda85ece5c4a2e328e7a37a1344dada5c4b5d62` (main after #190) |
| SaaS branch | `release/pricing-core-5dda85ec` |
| SaaS SHA | `a718b2c42f19dfba9a8961c2811d4d07e167bd0b` |
| Base SaaS lineage | `eb859c21` (23 commits ahead of prod `7b5f180`; **0 behind** — no production SaaS commits dropped) |
| Core pin in composer | `5dda85ece5c4a2e328e7a37a1344dada5c4b5d62` |

### Ancestry / overwrite check

| Check | Result |
|---|---|
| Prod Core is ancestor of candidate | **YES** (`affb186` → `5dda85e`, 91 commits) |
| Prod SaaS is ancestor of RC base | **YES** (`7b5f180` → `eb859c21`) |
| Production-only Core hotfixes not in candidate | **None found** |
| Production-only SaaS commits not in RC | **None found** |

---

## Migration inventory (Core → all ready tenants)

| Migration | Effect on existing data |
|---|---|
| `2026_11_10_010000_mark_standard_labor_policies` | Metadata update on `labor_policies` |
| `2026_11_10_120000_add_labor_pricing_profile_and_billing_minimums` | Additive JSON columns |
| `2026_11_10_180000_labor_matrix_profile_v2_and_programming_split` | Additive columns; backfill `system_line_kind` on existing billing-minimum fee lines; may add programming class |
| `2026_11_10_210000_shop_fee_flat_mode_and_profile` | Additive; defaults `shop_fee_mode=percent` |
| `2026_11_10_220000_commercial_pricing_profile_on_repair_orders` | Additive nullable RO stamp |
| `2026_11_10_230000_tax_profile_on_repair_orders` | Additive nullable RO stamp |
| `2026_11_12_160000_tax_profiles_catalog_on_shop_settings` | Additive catalog columns (empty until configured) |
| `2026_11_12_200000_shop_fee_applies_to_on_shop_settings` | Additive; default `both` |

**Rehearsal execution:** all 8 ran **DONE** on LugsNPlugs + Amaya. Demo also received earlier pending migrations then the same 8. Central: nothing to migrate.

---

## Isolated production-clone rehearsal

| Item | Value |
|---|---|
| Method | Decrypt prod nightly archive → load into disposable Docker MySQL 8.4 (`ark-pricing-reh-mysql` :3310) |
| App | Local `ark-stancl` @ `a718b2c` with Core `5dda85e` |
| Side effects | `MAIL_MAILER=array`, `ARK_SAAS_MAIL_DELIVERY=record`, invalid mail host, `QUEUE_CONNECTION=sync`, array cache/session — **no live SMS/email/payment/webhook** |
| Work dir | `~/ARK-Restore-Tests/pricing-rc-20261112T222702Z` (mode 700; not in git) |

### Tenant map in clone

| Tenant | DB | State | Migrated |
|---|---|---|---|
| LugsNPlugs | `…_import_261102041929_fb` | ready | **YES** (8 migrations) |
| Amaya | present | ready | **YES** |
| Demo | present | ready | **YES** (+ earlier backlog migrations) |
| Molly Auto | **DB MISSING** in backup | `preparing` | Skipped (pre-existing; opened=3 in backup report) |

Orphan schema also present: legacy `ark_saas_shop_5a2384d9…46262` (not the live Lugs DB).

### LugsNPlugs before → after (exact match)

| Metric | Before | After |
|---|---|---|
| Repair orders | 678 | 678 |
| Estimate docs | 397 | 397 |
| Invoice docs | 165 | 165 |
| Approval events | 24 | 24 |
| Ledger entries | 232 | 232 |
| Lines | 4609 | 4609 |
| Customers | 475 | 475 |
| Σ line `tax_cents` | 3336662 | 3336662 |
| Σ line `total_cents` | 88703603 | 88703603 |
| Σ invoice snapshot tax | 514093 | 514093 |
| Σ invoice snapshot total | 15765049 | 15765049 |
| Σ estimate snapshot tax | 1517398 | 1517398 |
| Σ estimate snapshot total | 42613286 | 42613286 |
| Invoice snapshot aggregate hash | `e490c9ce…` | **identical** |
| Estimate snapshot aggregate hash | `f0272bfa…` | **identical** |
| Approval aggregate hash | `66d6b6e4…` | **identical** |

### Tax / pricing preservation (LugsNPlugs)

| Setting | After migrate |
|---|---|
| `tax_enabled` | true |
| `default_tax_rate` | **8.200** (unchanged) |
| `tax_label` | `C/S Tax` |
| `tax_profiles` count | **0** (catalog not auto-filled — correct) |
| ROs with `tax_profile` stamp | **0** (no silent stamping of historical ROs) |
| Shop fees | enabled, rate 3.500, mode defaulted `percent`, applies_to defaulted `both` |
| Labor categories / parts matrices | 8 / 3 unchanged |
| Default labor rate | 16500¢ unchanged |

### Calculator smoke

Living RO `#1831` (`waiting_approval`): stored line tax/total **equal** recalculated totals at 8.200% (`23257` / `572974`). No tax stamp present.

---

## Feature-preservation ledger

| Production behavior | Candidate | Evidence |
|---|---|---|
| Legacy single-rate tax 8.200% | Preserved | PASS |
| Historical invoice/estimate snapshots | Unchanged bytes/hash | PASS |
| Approvals / ledger | Unchanged | PASS |
| Labor categories / parts matrices | Unchanged counts | PASS |
| Shop fee rate | Unchanged; mode/applies_to columns added with defaults | PASS (additive) |
| New RO tax stamps | Only when created/selected after feature on | PASS (0 backfill) |
| Booking / portal / payments HTTP | Not fully exercised in this clone | **DEFERRED depth** |
| deploy.sh cutover path | Not re-run (migrate path verified) | Note for cutover runbook |
| Molly preparing tenant | Missing DB pre-exists | Non-regression; monitor |

---

## Recovery / cutover notes

| Topic | Status |
|---|---|
| Nightly archives + `.restore-verified` | Present on prod through 20261112 |
| Binlog ship / PITR tooling | Present (`backup-binlogs.sh`); not re-drilled this session beyond tooling existence |
| Code rollback | Reversible for SaaS/Core pin |
| DB rollback | **Not** automatic — 8 additive migrations; do **not** restore stale backup over new transactions |
| Forward-fix | Preferred if post-cutover defect; keep backup taken immediately pre-deploy |

### Suggested cutover sequence (NOT executed)

1. Pre-deploy verified backup + confirm binlog continuity.  
2. Deploy SaaS `a718b2c` (Core `5dda85e`) via Stancl `deploy.sh`.  
3. Confirm `tenants:migrate` for ready tenants only; Molly remains preparing.  
4. Smoke Lugs: settings tax 8.200, open RO totals, issue estimate PDF preview (no customer send), portal token on synthetic, payment UI without capture.  
5. Watch Horizon/queue/cron for errors.  

---

## Remaining blockers / thin spots

1. **Full product smoke** (booking UI, portal authorize, payment capture path, email preview rendering) not run against the clone HTTP stack.  
2. **Molly** tenant has no shop DB in the backup (`preparing`) — confirm expected before cutover messaging.  
3. **deploy.sh** production-shaped rehearsal container (`deploy/rehearsal/local-mysql.sh`) not executed for this pin (financial migrate path was).  
4. Owner must still send **“Deploy the approved release”** naming artifact + `lugsnplugs.arksms.com`.

---

## Verdict

| Gate | Result |
|---|---|
| Merge #190 | **DONE** (`5dda85e`) |
| SaaS/Core RC frozen | **DONE** (`a718b2c` / `5dda85e`) |
| Prod data financial preservation on clone migrate | **PASS** |
| Production deploy now | **NO-GO until owner deploy authorization** (+ recommended HTTP smoke on Alpha pin of RC) |

**Release recommendation:** Candidate is **financially safe to proceed** after Alpha pin of `a718b2c` and a short HTTP smoke. Do not deploy to production without the separate explicit owner deploy message.
