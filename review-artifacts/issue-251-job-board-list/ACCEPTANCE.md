# Job Board — Owner / Carlos visual acceptance package

**PR:** [#252](https://github.com/EdwardSoaresJr/ark-core/pull/252) (draft)  
**Accepted tip SHA:** `6c2529dba4a8682f45dc4009e52a0486ed679490`  
**Base:** `main`  
**Issue:** #251  

**Stop state:** Feature development stopped after Activity icon refinement. No merge. No deploy. No Core pin / Sites release coordination. No migrations or production data changes.

---

## Activity presentation (this pass)

| Rule | Behavior |
| --- | --- |
| Idle | Neutral `—` |
| Active only | Inactive categories omitted |
| Cap | **None** — all active categories render (up to six) |
| Overflow | **Removed** — no `+N` |
| Order | Fixed: estimate → sms → email → phone → dvi → scheduled |
| Priority | Via color / badge styling only (not reorder) |
| Chrome | Standalone ~16px icons; no avatar chips / permanent colored backgrounds |
| States | Ready/Sent = muted gray; Engaged = subtle blue; Attention = orange/red by semantics |
| Deep links | estimate → `#builder`; sms/email/phone → `#comms`; dvi → `#inspect`; scheduled informational |
| Tooltips | Native `title` on links; Bootstrap tooltip on informational marks only |

---

## Synthetic fixtures to walk

| Fixture | Expect |
| --- | --- |
| SYN Quiet None | Activity `—` |
| SYN Brakes Demo | Single estimate |
| SYN Comms Pair | SMS + Email (+ estimate if priced) |
| SYN Wip Bay | Estimate + DVI |
| SYN Parts Hold | Phone attention (+ estimate) |
| SYN Full Strip | **Six** icons, no `+N` |
| SYN Ready Pickup | Estimate + SMS attention |

Local seed helper only: `review-artifacts/issue-251-job-board-list/seed-syn.php` — do **not** run against production.

---

## Viewport measures (Playwright, local `ark-core.test`)

| Viewport | Table vs wrap | Full Strip marks | Overflow UI |
| --- | --- | --- | --- |
| 1366×900 | 1076 === 1076 (no table scroll) | 6 icons, fixed order | none |
| 1440×900 | 1150 === 1150 | 6 icons | none |
| 1920×1080 | 1630 === 1630 | 6 icons | none |
| 390×844 | Mobile cards unchanged | n/a | n/a |

---

## Screenshot index

Path: `review-artifacts/issue-251-job-board-list/screenshots/`

| File | Focus |
| --- | --- |
| `list-desktop-1366.png` | Full board @ 1366 |
| `list-desktop-1440.png` | Full board @ 1440 |
| `list-desktop-1920.png` | Full board @ 1920 |
| `list-mobile-390.png` | Mobile cards |
| `activity-heavy-row.png` | Six active icons, no overflow |
| `activity-attention-row.png` | Missed-call phone attention |
| `activity-empty-row.png` | Quiet None `—` |

Also: `ACTION_PRESERVATION.md`.

---

## Remaining visual / product items (owner decision)

1. Appointment / scheduled marks stay tooltip-only (no deep link).
2. Mobile cards still omit Activity strip by design.
3. Default Watch volume / promise-overdue / parts-needed Attention dual signals unchanged.
4. Small status dots on engaged/attention marks remain for non-color-only cues — confirm they are restrained enough.

---

## Explicitly out of scope

Merge, deploy, SaaS pin, Sites release, Attention rewrite, migrations, production data, unrelated RO index redesign.

---

## Sign-off

| Role | Accept? | Notes / blockers | Date |
| --- | --- | --- | --- |
| Owner | | | |
| Carlos (visual) | | | |
