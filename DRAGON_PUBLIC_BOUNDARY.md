# Dragon public / private boundary (recommendation)

**Status:** Product governance recommendation — **not** implemented as a code split in this pass.  
**Tree:** `ark-public-staging`  
**Date:** 2026-08-28  

Aligned with the staging instinct: **open the Dragon runtime; keep proprietary fuel optional and separate.**

---

## Recommendation (one sentence)

Publish the Dragon **engine** (orchestration, tools, memory model, provider abstraction, controlled-learning hooks) with **zero private knowledge packs**; Hosted / paid intelligence remains a separate fuel + service layer.

---

## What should be public (runtime / architecture)

Keep in the public tree (already largely present under `app/Ark/Dragon/`):

| Area | Examples |
| --- | --- |
| Agent loop / orchestration | `DragonAgentLoop`, chat actions, traces |
| Tool contracts | `DragonToolRegistry`, shop/RO/estimate/inspection tools |
| Memory **model** | store / recall / forget / privacy helpers — empty by default |
| Provider abstraction | `DragonModelProvider`, OpenAI adapter, fake provider for tests |
| Service Advisor / assist bridges | rewrite actions, projections — BYO key |
| Import **interface** | e.g. arkai dump importer **code** that can load *customer-provided* dumps |

Self-hosters get a real Dragon: wire `OPENAI_API_KEY` (or another provider), run tools against **their** shop database, grow **their** memory. Not a cardboard stub.

---

## What must stay private / out of the public snapshot

| Fuel / service | Why |
| --- | --- |
| Proving-ground arkai / ARKademy knowledge dumps | Shop-earned sentences, employee context, proprietary procedures |
| Hosted Dragon credentials / proxy keys | Platform secrets |
| Proving-ground–tuned bakeoff corpora as “truth” | Shop-specific evaluation fuel |
| Any pack that answers “how this shop works” without the operator’s own data | Publication without authority |

**Invariant:** public repo ships **engine + empty tanks**. Fuel is optional, brought by the operator or sold as a hosted service later.

---

## What this pass already did / did not do

| Done | Not done |
| --- | --- |
| Scrubbed shop-named strings out of Dragon comments/fixtures where they appeared | No deletion of Dragon runtime architecture |
| Min fixture `tests/fixtures/dragon/arkai-min-dump.json` neutralized to demo voice | No new “Hosted Dragon” product packaging |
| Confirmed full Dragon dump absent from staging | No LICENSE coupling to Dragon |

---

## Commercial boundary (later — not a code mandate today)

Possible future split without hollowing the OSS engine:

1. **OSS Dragon** — runtime + BYO provider + local memory  
2. **Hosted Dragon** — managed models, curated knowledge packs, multi-shop ops — sold separately  

Do **not** remove useful open-source runtime merely to create a paywall. The paywall is **fuel and operations**, not the loop.

---

## Gate

Accept this boundary in writing before squash/publish. Then LICENSE choice can consider Dragon as “engine in tree, fuel out of tree,” which is coherent with ARK’s earned-authority doctrine.
