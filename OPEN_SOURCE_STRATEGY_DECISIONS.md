# Open-source release posture (locked)

**Date:** 2026-08-28  
**Tree:** `ark-public-staging`  
**Status:** **Release posture locked. LICENSE + NOTICE written.**  
**Remaining (human):** review diff → personal commit → create public GitHub repo → push.

---

## Locked decisions

| Layer | Posture |
| --- | --- |
| **ARK core license** | **AGPL-3.0-only** (reciprocal / anti-closed-SaaS-fork). See root `LICENSE`. Not “or later.” |
| **Dragon** | Open engine, empty tanks. Self-hosters get real runtime (tools, memory model, provider abstraction, controlled learning). No private shop knowledge, licensed training packs, proprietary datasets, or hosted intelligence credentials in the public tree. |
| **Square** | Optional adapter only (`packages/ark-payments-square` → `composer require ark/payments-square`). Own dependency/license obligations when installed. |
| **Preline** | Retained; published with required MIT + Fair Use notices in `NOTICE`. |
| **Private foundry** | Stays private. May install/use whatever production adapters the proving-ground shop needs (including Square). |
| **Public default install** | No licensed automotive fuel, no private knowledge, **no** `square/square` → **no** `apimatic/*` → **no** OSL-3.0. |

### Why Square separation matters

A **default** ARK install has:

```text
no square/square → no apimatic/* → no OSL-3.0
```

Shops that want Square take an **explicit** install step.

---

## Licensing decision (project decision — not legal advice)

ARK core is released under **AGPL-3.0-only** by **project decision**.

Third-party components (Preline, Composer/npm packages, and optional Square/APIMatic packages when installed) retain **their** licenses. AGPL does **not** relicense them. See `NOTICE`.

**Counsel is not part of the release sequence.** Do not block publication on external legal review.

---

## Known non-blockers (this milestone)

| Item | Record |
| --- | --- |
| Browsershot / PDF / HTTP 423 in staging payment tests | Staging-environment headless Chromium/PDF failures — unrelated to Square adapter separation. |
| Learn/getting-started redirects in some settings tests | Pre-existing training gates — not Square adapter regressions. |

---

## Certification status

**ARK PUBLIC RELEASE CANDIDATE: PASS** (prior gates)  
**LICENSE-ONLY DELTA: PASS** — private foundry `docs/open-source/LICENSE_ONLY_DELTA_CERTIFICATION.md`

Full prior gate table: private foundry `docs/open-source/ARK_PUBLIC_RELEASE_CANDIDATE_CERTIFICATION.md` (not shipped in the public tree).
