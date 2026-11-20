# AR #224 / Core #226 — release integration candidate

**Date:** 2026-11-20  
**Disposition:** release candidate prepared for owner review — **not merged, not deployed**  
**Authority required before cutover:** integration-owner merge approval + explicit production deploy authorization naming artifact + target

## Exact candidate commits

| Layer | Candidate | State |
| --- | --- | --- |
| Core AR tip | `8b41f5f6b9e57898b389e29c70b0bdff9bc06038` | PR [#226](https://github.com/EdwardSoaresJr/ark-core/pull/226) **OPEN**, mergeable, CI green on tip |
| Core `main` today | `1b63d6d046afb95b0176d54bcd171b80dfe0b644` | Includes #227 + #228; **does not** include #226 |
| Shop (legacy overlay) `statement.send` | `bf1074e9f88dfae5a57103db26d7f41801f67ee8` | ark-shop PR [#29](https://github.com/EdwardSoaresJr/ark-shop/pull/29) **OPEN** (rebase onto current Shop `main` before merge) |
| Stancl shop `statement.send` | `83a3c569` on `feature/statement-send-224` | ark-stancl PR [#310](https://github.com/EdwardSoaresJr/ark-stancl/pull/310) **OPEN** — required for LugsNPlugs |
| Platform payments | `ark-cloud` `00b2388` (local main tip at audit) | Readiness + keyed/portal capture present; **no refunds API**; **no `supports_saved_cards`** |
| Merge SHA for pin | *does not exist yet* | Artifact/pin must use the Core merge commit after #226 lands on `main` (and preferably after #226 is rebased onto current `main` so #228 is included) |

Product tip inside #226 remains `ddc95027…` for money-path behavior; later commits are acceptance fixtures + RO labeling only.

## Production topology (blast radius)

LugsNPlugs production is on **Stancl** (`ark-saas-prod-1` `165.22.174.183`), not the legacy hosted Docker pool.

| Fact | Detail |
| --- | --- |
| Target host | Stancl production app host |
| Deploy tool | `deploy/deploy.sh <stancl-sha>` (documented Stancl path) |
| Blast radius | **Every opened tenant** on that installation gets the new code; `tenants:migrate` runs for opened shops |
| Known opened shops (docs) | At least LugsNPlugs (+ others such as Amaya in prior deploy records — confirm live tenant list before cutover) |
| Legacy `143.110.199.236` / `ark-lugs-standard` | Historical; not the current LugsNPlugs target |
| `#227` | Already on Core `main`; Stancl pin branch `release/pin-core-1b63d6d0-ro-modal` pins `1b63d6d0`. Keep operationally separate from AR go-live decisions, but any pin that includes merged #226 will also include #227/#228 |

## Shop #29 / Stancl #310 integration

Core already sends Platform mail with operation `statement.send` and variables `shop_name`, `customer_name`, `balance_due`, `as_of` (`CustomerAccountStatementEmailDelivery`). No Core redesign required.

| Surface | `statement.send` |
| --- | --- |
| ark-shop (#29) | Implemented — for hosted overlay lineage |
| ark-stancl packages/ark-shop (#310) | Implemented — **this is the catalog production Stancl serves** |
| Proof | `php packages/ark-shop/tests/mail.php` / `php saas/tests/mail.php` → Mail proof passed |

Live email still needs: merge #310 (and #29 if that lineage is used), Platform/Postmark connected for the shop, then a synthetic recipient test on staging before production.

## Platform payment readiness (code truth — not assumed)

Core clients call:

- `GET /api/v1/services/payments/readiness`
- `POST/GET /api/v1/services/payments/captures…`
- `POST/GET /api/v1/services/payments/refunds…` ← **no Platform route exists**

| Capability | Platform | Core behavior | Ship now? |
| --- | --- | --- | --- |
| Manual check / cash / allocate / store credit / manual return confirm | N/A (Core ledger) | Owner acceptance 1–4 PASS | **Yes** |
| Statement preview / PDF / address label | N/A | PASS | **Yes** |
| Statement email (`statement.send`) | Catalog + managed mail | Self-fails if mail/catalog missing | **Yes after Stancl #310 (+ staging email proof)** |
| Keyed card capture | Implemented for connected Square | UI gated by readiness `supports_keyed` | **Conditional** — only when shop Platform status=`connected` and Square ready; otherwise correctly disabled |
| Portal payments | `supports_portal` for Square connected | Gated similarly | **Conditional** same as keyed |
| Saved cards | **Not advertised** anywhere in Platform | Stays off (`supports_saved_cards` default false) | **No — remain disabled** |
| Processor refund settle | **No `/payments/refunds` routes** | Fail-closed; liability stays pending | **No — remain unsupported** (button can appear but cannot clear liability) |

Local AR Herd env had empty Platform URL — live staging/production readiness HTTP probe was **not** performed from this session (no shop Platform credential used). Square webhook docs show LugsNPlugs merchant subscription on `lugsnplugs.arksms.com`; treat keyed/portal as **provider-gated**, not guaranteed on every tenant.

## Focused verification completed this session

| Check | Result |
| --- | --- |
| Shop #29 mail proof | PASS |
| Stancl #310 mail proof | PASS |
| Core statement + foundation tests | PASS (9) |
| Core card capture + payment allocation tests | PASS (25) |
| Owner money-path scenarios 1–4 | Previously PASS (acceptance results) |
| Live statement.send on staging/prod | **Not run** (requires #310 deployed + mail) |
| Live keyed/portal/processor on staging | **Not run** (Platform credential / sandbox not exercised) |

## Migrations (Core #226) — tenant impact

All additive on each shop DB when Stancl runs tenant migrations:

1. `2026_11_20_040000_add_accounts_receivable_fields_to_estimate_documents.php`
2. `2026_11_20_050000_create_customer_payment_receipts_table.php`
3. `2026_11_20_060000_add_ar_allocation_plan_to_payment_capture_attempts.php`
4. `2026_11_20_070000_add_ar_capture_reconciliation_fields.php`
5. `2026_11_20_080000_add_refund_pending_cents_to_customer_payment_receipts.php`
6. `2026_11_20_090000_add_refund_confirmation_audit_to_customer_payment_receipts.php`

Shop/Stancl statement PRs add **no** migrations.

Backward compatibility: shops still on previous Core keep working until cutover; after migrate, rolling Core back without schema undo leaves unused columns/tables (safe). Schema undo requires backup restore assessment — deploy backups are mandatory.

## Recommended ship scope (first production cut)

**Include**

- AR index/account, multi-invoice allocation, store credit, pending manual refund confirm
- Statements (preview, PDF, print, address label)
- Statement email once Stancl #310 is in the release SHA and staging email is proven
- Keyed/portal **only as readiness-gated** (disabled until Platform reports connected)

**Exclude / keep unsupported**

- Saved cards
- Processor refund settlement success path
- Any redesign of invoice `document_number`

## Remaining blockers before merge/deploy

1. Owner merge approval for Core #226 (rebase/merge onto current `main` so pin includes #228).
2. Merge Stancl #310 (`statement.send`) — required for production email catalog.
3. Rebase/merge Shop #29 if hosted overlay lineage still matters.
4. Stancl pin PR: set `ark/core` to the **merged** Core SHA that contains #226; open as its own PR (do not fold into #310).
5. Staging rehearsal on `alpha.staging.arksms.com` / Stancl staging: migrate one shop DB, money-path smoke, statement email to a synthetic address, confirm keyed disabled or Square sandbox as appropriate.
6. Confirm live opened-tenant list (blast radius) and backup coverage before `deploy.sh`.
7. Explicit production authorization naming **Stancl SHA + target host**.

Until those complete: **no merge assumption, no artifact pin as production-ready, no cutover**.

## Proposed merge → pin → deploy sequence (authorization gates)

```
1. Rebase Core #226 onto origin/main → green CI → owner merges #226
2. Merge Stancl #310 (statement.send) onto Stancl main
3. Open Stancl pin PR: composer.lock ark/core = merge SHA of #226
4. Staging deploy of that Stancl SHA → E2E gates
5. Owner: "Deploy the approved release" naming Stancl SHA + ark-saas-prod-1 / lugsnplugs
6. deploy.sh <sha>  (backup → migrate central → tenants:migrate opened shops)
```

This agent will not perform steps 1, 5, or 6 without explicit owner commands.

## Rollback plan

| Layer | Action |
| --- | --- |
| Application | `deploy/deploy.sh <previous-known-good-stancl-sha>` |
| Database | Forward-only migrations; undo = restore pre-deploy backup from deploy lock/archive after owner assessment |
| Feature safety if partial | Manual AR paths remain; card/saved/processor stay fail-closed without Platform support |
| Statement email if catalog missing | Print/PDF still work; email errors closed |

Record previous Stancl SHA from the last healthy production deploy before cutover (current docs cited Core `f84e26c5…` / Stancl `904fca9c…` for an earlier F-FIN-01 event — **re-read live release pointer at cutover time**; do not trust this file as the live pin).

## Dependency chain (Stancl)

```
Core #226 merge SHA (main)
    └─ Stancl composer pin (dedicated pin PR)
         └─ Stancl main also contains #310 statement.send
              └─ deploy.sh <stancl-sha>
                   └─ all opened tenants migrate + serve AR
```

## What was not done

- No GitHub merges
- No production or staging deploy
- No Platform credential use / signed readiness probe against live shops
- No Core pin publish / GHCR image (wrong path for Stancl; Core must be on `main` before Stancl can pin it)
