# RO Operational Closeout Exceptions — Owner Review

**Stop here for owner Alpha acceptance. No production cutover without explicit authorization.**

## Candidate pins (Alpha)

| Layer | SHA | Notes |
| --- | --- | --- |
| Core (code-bearing / Alpha) | `00012cda276f1b4e1093ee702b28b3ec51848f8f` | Fingerprint-required acknowledgment contract |
| Stancl / SaaS | `502096e3e42944bfc7ef480a1d983163daecf1e5` | Pins Core `00012cda` |

## Baseline SHAs (before this work)

| Layer | SHA |
| --- | --- |
| Core `main` / production pin | `09aa7ba3eb1fa93abac51ee50b838a8d03a41b6f` |
| Production SaaS (`lugsnplugs.arksms.com`) | `b972410d47dcda7ebb1981c80faff0234169af12` |

## Alpha host

| Item | Value |
| --- | --- |
| Host | `157.230.172.143` / `https://alpha.staging.arksms.com` |
| Deploy | `ARK_STANCL_BACKUP_VERIFY=integrity bash deploy/deploy.sh ab566134…` |
| Health | `/up` → 200; `APP_ENV=staging` |
| Production | Untouched |

## Policy implemented

When an authorized user marks an RO **Completed**, **Ready for Pickup**, or **Closed (paid)**:

1. Evaluate outstanding operational work.
2. Ignore inspections that were never started.
3. Detect started-but-unfinished inspections.
4. Detect unresolved approved parts and approved labor.
5. Exclude declined / deferred / cancelled / removed work.
6. If none outstanding → continue normally.
7. If outstanding → one clear panel listing items.
8. Require a meaningful reason (≥ 8 characters).
9. Persist auditable `repair_order_operational_closeout_acknowledged` with actor, timestamp, items, reason, transition, fingerprint.
10. Same rules on web lifecycle + mobile lifecycle API.

Fingerprint reuse avoids repeated prompts for the same outstanding set; a changed set requires a new reason. Exception recording does **not** rewrite inspection, labor, or procurement state. Customer approval, payment, and lost-deal closeout remain separate.

Quality Check is intentionally **not** gated (in-bay / authorization integrity flow).

## Verification (automated)

`php artisan test --filter=RepairOrderOperationalCloseoutAcknowledgmentTest` — **17 passed**.

Covers: clean close, never-started inspection, incomplete inspection override, unreceived/uninstalled parts, unfinished labor, declined/deferred/cancelled exclusions, short reason rejection, paid close API rejection, override preserves states, fingerprint reuse/renewal, lost close without acknowledgment, QC ungated, mobile API challenge, select dropdown stays clickable for the reason panel.

AuthorizationIntegrity Quality Check regressions rechecked — pass with QC ungated.

## Browser proof (Alpha)

Synthetic RO `#41` on `alpha.staging.arksms.com` seeded with approved labor in progress + ordered part (not received). Inspection never started (correctly omitted from outstanding list).

| Screenshot | Viewport | State |
| --- | --- | --- |
| `screenshots/desktop-closeout-exception-panel.png` | Desktop ~1440 | Completed challenge panel listing unreceived part + unfinished labor |
| `screenshots/mobile-closeout-exception-panel.png` | 390×844 | Same unfinished-work challenge on phone width |

Alpha SaaS `ab566134…` / Core `e5f082eb…` confirmed after deploy (`/up` 200).

## Owner decision

- [ ] **ACCEPT Alpha** — authorize production prep / deploy naming these SHAs
- [ ] **CHANGES REQUIRED** — list notes
- [ ] **HOLD** — leave Alpha pin; do not promote


## Fingerprint contract (final correction)

New acknowledgments require a fingerprint matching the current outstanding set. Missing/invalid/stale fingerprints re-challenge. Prior matching acknowledgment reuse is unchanged.

**Tested SHAs:** Core `00012cda…` · SaaS `502096e3…`
