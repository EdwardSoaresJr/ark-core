# Repair Order — Builder retirement + legacy surface bridges

**Status:** Canonical rename in progress (uncommitted with Presentation Reset)  
**Date:** 2026-08-06  
**Removal gate:** 2026-09-01 + zero access-log hits on legacy GET paths

## Canonical identity

| Item | Value |
| --- | --- |
| Path | `GET /app/repair-orders/{repairOrder}` |
| Route name | `operations.repair-orders.show` |
| Controller | `RepairOrderShowController` |
| View | `operations.repair-orders.show` |

Presentation is permanent. Authoring is temporary (Workspace Modal). No Edit Mode. No Builder product concept.

## Temporary GET bridges (redirect only)

| Path | Behavior |
| --- | --- |
| `/app/repair-orders/{id}/edit` | → canonical show |
| `/app/repair-orders/{id}/builder` | → canonical show |
| `/app/repair-orders/{id}/estimate-review` | → canonical show (`operations.repair-orders.estimate-review` name retained as redirect) |

Controller: `RepairOrderLegacySurfaceRedirectController`  
Deprecated shell: ~~`RepairOrderEstimateReviewController`~~ **deleted** (unused; routes use `RepairOrderLegacySurfaceRedirectController` only)

**Do not** restore page variants on these paths.

## Still product-semantic debt

| Item | Classification | Action |
| --- | --- | --- |
| `ops-builder-present-*` CSS classes | Rename later | Cosmetic; means presentation chips |
| `RepairOrderBuilder*Test` filenames | Rename later | After green suite |
| `worksheetSurface: repair_order` | Done | Was `builder` |
| `estimate-review.blade.php` | Dead view | Delete after bridge removal |
| `ark-ro-mode-control.js` | Orphan | Keep until unused proof |
| Query/Schema `*Builder` classes | Legitimate | Not RO product language |

## Footer

`RepairOrderFooterProjection` — Workflow · Present · Utilities  
Blade: `repair-order-footer.blade.php`

| Present action | Status |
| --- | --- |
| Customer Display | Wired → `portal-preview` (second monitor / browser) |
| Tablet | **Hidden** — no Flutter customer presentation/signature deep link exists; do not substitute inspection `surface=tablet` |

Commit carve-out (exclude Financial F1): `docs/operations/ro-presentation-reset-commit-carve-out.md`

## Commit gate

Edward floor observation + explicit approve. No push/deploy until then.  
Stage Presentation Reset only — leave F1 files unstaged per carve-out doc.
