# Daily Coaching Digest

Operational coaching email for shop leadership — **not** a winner/loser scoreboard.

## Framing (non-negotiable)

| Avoid | Use |
| --- | --- |
| Best call / Worst call | **Strongest Call** / **Highest Coaching Opportunity** |
| Call to celebrate / Call to improve | Acceptable alternate labels |
| Advisor rankings | Daily digest highlights only |

Advisors get defensive when AI picks "losers." ARK surfaces **coaching opportunities** and **strengths** for owner-led development.

## Phases

### Phase 1 (implemented)

- `communication_reviews` authority — one row per analyzed call
- Sync from call AI analysis on `CallSession` (`RecordCommunicationReviewFromCallAction`)
- Evening job: `communications:daily-coaching-digest`
- Email sections: Strongest Call, Highest Coaching Opportunity, transcript links
- Recipients: active admins + optional extra emails in `shop_excellence_targets`

### Phase 2

Dimension scoring scaffold in `dimension_scores` JSON:

- Acknowledge · Reassure · Gather Information (Demo Auto Repair call doctrine)
- Appointment conversion · Customer experience

### Phase 3

Outcome-aware scoring — conversation quality **and** operational result (appointment, estimate, approval, revenue).

## Data model

```
communication_reviews
├── call_session_id (unique)
├── advisor_user_id
├── composite_score (1–100 from empathy/ownership/clarity)
├── coaching_opportunity_weight (urgency, not "badness")
├── strengths (json)
├── opportunities (json)
├── dimension_scores (json)
├── reviewed_at
└── source (ai_analysis | manual)
```

Call analysis JSON on `call_sessions` remains transport; `communication_reviews` is the digest/query authority.

## Configuration

`shop_settings.shop_excellence_targets`:

- `coaching_digest_enabled` (default `false`)
- `coaching_digest_time` (default `19:00` shop TZ)
- `coaching_digest_recipient_emails` — when set, **only** these addresses (Edward-only pilot). When empty, active admins + extra emails.
- `coaching_digest_extra_emails` — additional recipients when using admin default list

## Commands

```bash
php artisan communications:daily-coaching-digest
php artisan communications:daily-coaching-digest --date=2026-06-14 --email=you@example.com
```

## ARKademy bridge (future)

Eventually combine score with operational outcome (Phase 3) — not purely composite_score forever.

## Roadmap notes

- **Most Improved** (weekly) — third digest category for reinforcement; not Phase 1.
- **ARKademy bridge** — coaching opportunity topic → recommended SOP article.

