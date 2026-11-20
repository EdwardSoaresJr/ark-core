# AR #224 — reconciled integration sequence (2026-11-20)

**No merges by non-integration agent. No production deploy.**

## Reconciled PR tips

| PR | Tip | Base | Status |
| --- | --- | --- | --- |
| Core [#226](https://github.com/EdwardSoaresJr/ark-core/pull/226) | `88692e647d2661b50a68085f3b7096073950a91d` (includes merge `b6aee836` of `main` `1b63d6d0`) | `main` | OPEN, mergeable — ready for **integration-owner** merge review |
| Stancl [#310](https://github.com/EdwardSoaresJr/ark-stancl/pull/310) | `1703725960e1b0d6831444df3d14345010a0543b` | `pin/core-2ea00e27-exact-manual-labor-prod` @ **`83cb9793`** | OPEN — production-descended; diff = mail only |
| Shop [#29](https://github.com/EdwardSoaresJr/ark-shop/pull/29) | `942cc63bbd3bca0cf85f8b34df429428d494cc3e` | `main` | OPEN — **overlay lineage only**; not LugsNPlugs Stancl package |

Product freeze tip `8b41f5f6` remains the last AR presentation change; later Core commits are integration docs + merge of `main` (#228).

## Why Stancl base changed

Deployed production tip is **`83cb9793`** (merge of pin PR #308, Core pin `1b63d6d0`).  
Stancl `main` @ `0d887435` is **18 commits behind** that tip. Building AR from `main` would drop production pin-lineage functionality. #310 was rewritten onto `83cb9793` and retargeted to the pin branch.

## Final integration sequence (owner gates)

1. **Integration owner merges Core #226** at `b6aee836` (or newer green tip on the same branch). Record **`MERGED_CORE_SHA`** = the merge commit on `main`.
2. Integration owner merges **Stancl #310** into `pin/core-2ea00e27-exact-manual-labor-prod` (or the then-current production pin branch tip). Result tip still contains `83cb9793` + `statement.send`.
3. **Dedicated Stancl Composer-pin PR** (new; not part of #310): from that production tip, set `ark/core` → `MERGED_CORE_SHA`, update lockfile, open PR. No other changes.
4. **Staging deploy** of the pin-PR SHA via existing `deploy/deploy.sh <sha>` on `ark-stancl-staging` (`157.230.172.143`). Not production.
5. Staging verification (synthetic only):
   - `stancl:opened-shops` count + migrate only those tenants
   - Confirm all six `2026_11_20_0{4..9}*` migrations applied per opened shop
   - On-account close → multi-invoice checks → retained credit → manual return
   - Statement PDF + **live** `statement.send` to synthetic address
   - Platform readiness: keyed/portal disabled or sandbox; saved cards off; processor refund settle fail-closed
   - Code rollback rehearsal: redeploy prior staging SHA with additive schema left in place (no `migrate:rollback`, no prod restore)
6. Owner records production: opened-tenant count, current SHA, backup completeness, rollback SHA.
7. Owner authorizes: **Deploy the approved release** naming Stancl SHA + `ark-saas-prod-1` / `lugsnplugs.arksms.com`.
8. Production: `deploy.sh <sha>` (backup → migrate → tenants:migrate opened).

## Test evidence (this session)

| Evidence | Result |
| --- | --- |
| Core contains `origin/main` (`1b63d6d0`) | Yes (`b6aee836`) |
| Focused AR feature/unit tests | **35 passed** (foundation, statement, allocation, card, label) |
| Processor-refund / fail-closed filter | **4 passed** |
| Local seed + scenarios 1–4 | **ALL PASSED** at `b6aee836` |
| Stancl #310 mail proof | **PASSED**; diff vs `83cb9793` = 2 files |
| Shop #29 mail proof after rebase | **PASSED** at `942cc63` |
| Herd migrate status — six AR migrations | Ran |
| Live staging deploy / live statement email | **Blocked** — `cursor-comms` key present locally but SSH to staging/prod returned `Permission denied` |
| Live production opened-tenant recount | **Blocked** — same SSH denial; use owner-stated tip `83cb9793` until diagnostics succeed |

## Production facts (as of this write-up)

| Item | Value | Confidence |
| --- | --- | --- |
| Current production Stancl tip (owner + git) | `83cb9793` on `pin/core-2ea00e27-exact-manual-labor-prod` | High (owner verified; remote branch tip matches) |
| Core pin on that tip | `1b63d6d0` | High (`composer.lock` on that commit) |
| Opened production tenant count | **Not live-verified this session** | Prior docs: LugsNPlugs + Demo (+ others historically). **Must recount with `stancl:opened-shops` before cutover** |
| Fresh backup | Required by `deploy.sh` before migrate; incomplete backup needs owner override env | Procedure |
| Rollback target | Redeploy previous healthy Stancl SHA (`83cb9793` if that is still live at cutover) | Code; DB stays forward |

## Blast radius

One Stancl app tree serves all hostnames on the host. Deploy updates **every opened tenant’s schema** via `tenants:migrate --tenants=<opened>`. Not-opened shops are listed and skipped. Demo and LugsNPlugs share the same PHP release when both are opened.

## Ship scope (unchanged)

**Ship:** manual AR money path, statements/PDF/labels, statement email after #310 + mail proof, keyed/portal only when Platform readiness connected.  
**Stay disabled:** saved cards; processor refund settlement (no Platform routes).

## Remaining blockers

1. Integration-owner **merge** of Core #226 (this agent will not merge).
2. Integration-owner **merge** of Stancl #310 onto production pin lineage.
3. Create/merge **Composer pin PR** only after step 1 (`MERGED_CORE_SHA`).
4. Restore working **cursor-comms** (or owner) SSH for staging deploy + production `stancl:opened-shops` recount.
5. Staging E2E including live statement email.
6. Explicit production deploy authorization naming artifact + target.

## Pin PR template (after Core merge — do not open empty)

```
Branch from: current production tip (83cb9793 + merged #310)
Change: composer.json / composer.lock ark/core → <MERGED_CORE_SHA>
Title: Pin Core <MERGED_CORE_SHA> for AR #224
Body: Depends on merged Core #226 and Stancl #310. Staging first. Blast radius = all opened tenants.
```
