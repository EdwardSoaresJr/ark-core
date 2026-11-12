# Pricing release manifest — Labor / Parts / Shop Fees / Tax

**Date:** 2026-11-12  
**Core candidate tip (PR #190):** `bf011171e4dd843d2897d7dfab1770e61b46573b`  
**Core main (includes #189):** `018ef6aaf6debfd00e502a0decec25c39477d962`  
**#190 merge:** NOT performed (awaiting owner approval)  
**Production deploy:** NOT authorized

## Actual production (read-only discovery)

| Item | Value |
|---|---|
| Public shop | `https://lugsnplugs.arksms.com/up` → HTTP 200 |
| DNS A | `165.22.174.183` (`ark-saas-prod-1`) — **not** the older Vultr/DO IPs in RELEASE_PROTOCOL |
| Runtime | nginx/php-fpm multi-tenant Stancl (no Docker on this host) |
| SaaS repo | `EdwardSoaresJr/ark-stancl` |
| **Production SaaS SHA** | `7b5f18059863824aba7e3358d6507275bc29d073` (`fix/signup-password-field-height`) |
| **Production Core SHA** | `affb1869e8fff581dfa874dda51ce9d08e98b352` (`customer/public-surface-chrome` / #170) |
| APP_ENV | `production` |
| Tenants observed | LugsNPlugs, Molly Auto, Amaya, Demo (domains listed via `tenants:list`) |

### LugsNPlugs tenant inventory (no secrets)

| Setting / count | Observed |
|---|---|
| `tax_enabled` | true |
| `default_tax_rate` | `8.200` |
| `tax_profiles` count | **0** (legacy single-rate; schema-2 catalog not yet live) |
| `shop_fee_enabled` | true |
| `shop_fee_mode` | null (legacy percent posture until flat/applies_to migrations) |
| Labor categories | 8 |
| Parts matrices | 3 |
| Default labor rate | 16500¢ |
| Repair orders | 678 |
| Estimate documents | 397 |
| Invoice documents | 165 |
| Approval events | 24 |

## Alpha staging (reference)

| Item | Value |
|---|---|
| Host | `157.230.172.143` / `alpha.staging.arksms.com` `/up` 200 |
| SaaS | `eb859c2159a1821d87e51721698d4608c3bb20c1` |
| Core vendor | `a13872147df1e7d3670878e4ea448894da37708b` (**behind** #190 tip `bf011171`) |

## Code delta: production Core → candidate tip

| Metric | Value |
|---|---|
| Commits | **91** (`affb186` → `bf011171`) |
| Migrations | **8** (listed below) |
| #190-only migrations (vs main `018ef6a`) | **0** |

### Migrations that would run on production tenants

1. `2026_11_10_010000_mark_standard_labor_policies.php` — updates `labor_policies` rows (metadata)
2. `2026_11_10_120000_add_labor_pricing_profile_and_billing_minimums.php` — additive JSON columns
3. `2026_11_10_180000_labor_matrix_profile_v2_and_programming_split.php` — additive columns + **backfill** of `system_line_kind` on existing billing-minimum fee lines; may insert programming operation class / labor policies
4. `2026_11_10_210000_shop_fee_flat_mode_and_profile.php` — additive
5. `2026_11_10_220000_commercial_pricing_profile_on_repair_orders.php` — additive
6. `2026_11_10_230000_tax_profile_on_repair_orders.php` — additive nullable `tax_profile` on ROs
7. `2026_11_12_160000_tax_profiles_catalog_on_shop_settings.php` — additive catalog columns
8. `2026_11_12_200000_shop_fee_applies_to_on_shop_settings.php` — additive

**No #190 Slice B migration.** Schema-2 tax stamps are JSON on existing `tax_profile` column.

### SaaS delta: production → Alpha staging pin

`ark-stancl` `7b5f180` → `eb859c21`: **23 commits ahead**, 0 behind (mostly Core pin bumps for Labor/Parts/Fees staging). Production SaaS must be advanced carefully with the Core pin; local `ark-saas` checkout is **not** this lineage.

## Backups / recovery (observed)

| Item | Status |
|---|---|
| Nightly archives | Present under `/var/backups/ark-stancl/` through `20261112T033001Z` with `.sha256`, `.tenants`, `.binlog`, `.restore-verified` sidecars |
| Disk headroom | ~138G free on `/` |
| Isolated clone restore of this release | **NOT EXECUTED** in this session |
| PITR / forward-fix rehearsal | **NOT EXECUTED** |

## Feature flags / deferred (safe unused)

- Itemized component tax presentation (combined remains default)
- Customer tax exemption ≠ Billing Class
- SaaS automated jurisdiction lookup
- Slice C scope

## Core #190 quality gates

| Gate | Status |
|---|---|
| Tip equals `bf011171` | YES |
| Required PR checks (focused/lint/security/targeted/comms/gitleaks) | PASS |
| Full Regression vs known-failure baseline | **PASS** — 4589 tests, 205 failing, baseline 207, **0 new regressions** ([run 43309820708](https://github.com/EdwardSoaresJr/ark-core/actions/runs/43309820708) on `bf011171`) |
| Local focused financial subset | 98 passed (prior session) |
| Affirmative jurisdiction confirmation | PASS |
| Supplemental tax-after-auth + invoice ceiling | PASS |
| E2E cent reconciliation | PASS |

## GO / NO-GO

| Decision | Verdict | Why |
|---|---|---|
| Owner merge of Core #190 @ regression-proven `bf011171` (or docs follow-up tip if Checks green) | **GO for merge approval** | 0 new Regression failures; required Checks green; stop for explicit merge |
| Alpha re-pin to `bf011171` | Optional GO | Currently on `a138721` |
| Production deploy of pricing release | **NO-GO** | Clone upgrade rehearsal not done; 91-commit / 8-migration gap; SaaS pin path not rehearsed; production preservation not proven on restored data |
| Restore rehearsal / PITR drill | **REQUIRED before any GO** | Backups exist; isolated restore not yet performed |

## Owner approval checklist (production — later)

- [ ] Merge #190 explicitly
- [ ] Publish/pin Core image or Stancl vendor Core to merged SHA
- [ ] Advance `ark-stancl` to agreed SaaS SHA with Core pin
- [ ] Isolated restore of `ark-stancl-20261112T033001Z` (or newer) + apply exact candidate
- [ ] Pre/post compare: settings, RO stamps, approvals, invoices, payments, row counts
- [ ] Smoke: booking, RO, portal, invoice, email preview (no live sends), payments (no capture)
- [ ] Confirm worker/Horizon/cron compatibility
- [ ] Explicit “Deploy the approved release” naming artifact + `lugsnplugs.arksms.com`
