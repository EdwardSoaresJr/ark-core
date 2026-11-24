# Issue #251 / PR #252 — Activity indicators UX refinement

Stop for Carlos/owner visual acceptance. No merge. No deploy. No SaaS pin. No production data changes.

Prior SHA: `25843e57ddac1b21b389e509f6e4678fed343adf`.

## Commit

Recorded on push (see PR comment for exact SHA).

## Root cause (single-icon impression)

1. The table correctly **hides idle marks** (no six-placeholder strip).
2. Most open ROs only have **Estimate → Ready** (priced lines), so one document glyph appears.
3. Marks were wrapped in similar **avatar chips**, so category glyphs were hard to tell apart at a glance.
4. SYN fixtures previously did not seed multi-channel activity, so review screenshots reinforced the “one icon” impression.

Projection categories and `ready` / `sent` / `engaged` / `attention` semantics were already present; the list was under-presenting them.

## Activity column behavior

| Rule | Behavior |
| --- | --- |
| Idle | Neutral `—` |
| Active only | Inactive categories omitted |
| Cap | Max **4** visible icons |
| Overflow | `+N` with tooltip summarizing hidden marks |
| Priority | Attention → Engaged → Sent → Ready; stable category order within a tier |
| Category order | estimate, sms, email, phone, dvi, scheduled |
| Tooltips | Existing projection tooltips (e.g. `SMS - Customer replied 1d ago`) |
| Deep links | estimate → `#builder`; sms/email/phone → `#comms`; dvi → `#inspect`; scheduled informational only |

## Activity-state matrix (existing projection)

| Category | Ready | Sent | Engaged | Attention |
| --- | --- | --- | --- | --- |
| Estimate | Priced estimate on file | Estimate sent | Estimate viewed | — |
| SMS | Outbound sent | Delivered | Customer replied | Delivery failed (no later delivery) |
| Email | — | Sent | Opened / read | — |
| Phone | — | Inbound/outbound call | — | Unworked missed call / voicemail |
| DVI | Inspection on file | Inspection sent | — | — |
| Appointment | Upcoming | Today / confirmed | Checked in | Missed appointment |

## Synthetic scenarios (SYN-*)

| Fixture | Intent |
| --- | --- |
| Quiet None | No activity → `—` |
| Brakes Demo | Single recorded estimate |
| Comms Pair | SMS + Email |
| Wip Bay | Estimate viewed + DVI on file |
| Parts Hold | Unresolved missed call (+ estimate) |
| Full Strip | Six active categories → 4 + `+2` |
| Ready Pickup | Estimate + SMS delivery failed |

## Screenshots

| File | Notes |
| --- | --- |
| `screenshots/list-desktop-1366.png` | Overflow **0**; multi-icon Activity |
| `screenshots/list-desktop-1440.png` | |
| `screenshots/list-desktop-1920.png` | |
| `screenshots/list-mobile-390.png` | Mobile cards unchanged (no activity strip added) |
| `screenshots/activity-heavy-row.png` | Full Strip overflow |
| `screenshots/activity-attention-row.png` | Missed-call attention |
| `screenshots/activity-empty-row.png` | Quiet None |

## Focused tests

- `JobBoardActivityPresentationTest`
- `JobBoardListViewTest`
- `WorkboardCardActivityTest`
- `AdvisorHomeAttentionBoardTest`

**passed 36 / failed 0 / skipped 0**

## Unsupported deep links

- **Appointment / scheduled** — no verified stable RO hash; remains tooltip-only.
- Mobile cards — activity strip not added (preserve primary hierarchy).

## Remaining owner/Carlos items

1. Default Watch volume (Attention classifier unchanged).
2. Promise overdue Attention for shops that rarely use promises.
3. Parts-needed Attention vs Waiting Parts status.
4. Whether appointment marks should later deep-link to a schedule surface.

## Still out of scope

No merge, deploy, SaaS pin, migrations, production data, Sites release change, or Attention classifier rewrite.
