# Job Board Card View — Tabler-native recommendations

**Status:** concepts only — no Card redesign implemented in this evaluation.

## Why the current cards feel weak

1. **Flat hierarchy** — RO #, money, customer/vehicle, concern, status, and attention compete at similar visual weight in a short card.
2. **Weak workflow cue** — Attention is a text line; Watch/Needs attention do not read as a shop “next move.”
3. **Sparse chrome** — `card-sm` + tight gaps look like a denser list clipped into tiles, not a board surface.
4. **Activity strip is easy to miss** — small marks without a labeled “next” action.
5. **Same projection as List** — correct for parity, but Cards need a *composition* pass (grouping), not only shared fields.

## Recommended Tabler composition (owner/Carlos)

Use Tabler `card` + `card-status-start` (already present for attention) with clearer bands:

```
┌ status strip (attention color) ─────────────────────┐
│ RO #89                          $425                │  ← identity + money
│ SYN Brakes Demo · 2019 Honda CR-V                   │  ← secondary
│                                                     │
│ Customer reports grinding front brakes…             │  ← concern (1–2 lines)
│                                                     │
│ [Waiting Approval]  Alex Rivera           1h ago    │  ← status / tech / clock
│ Next: Send estimate · SMS delivered                 │  ← workflow line
│ 📄 💬 📞                                            │  ← activity marks
└─────────────────────────────────────────────────────┘
```

### Hierarchy rules

| Band | Content | Tabler cues |
|------|---------|-------------|
| 1 Identity | RO # + total | `fw-bold`, `ark-money` |
| 2 Party | Customer · vehicle | `small text-secondary` |
| 3 Concern | Concern/Job fallback (#254) | body text, 2-line clamp |
| 4 State | Status badge + tech + updated | badge + muted |
| 5 Next | Exception / recommended action | `text-warning` / `text-danger` when actionable; omit empty Watch noise |
| 6 Activity | Existing marks | keep compact |

### Layout

- Keep `.ark-card-grid` (already Tabler-native responsive).
- Prefer slightly taller cards (`card` not ultra-`card-sm`) so bands breathe.
- Do not invent a second data system — keep `JobBoardListRow` / surfaces; change Blade composition only.

### Visual concepts

See `concept-a-dense-workflow.html` and `concept-b-status-led.html` in this folder (static HTML, Tabler-like tokens, for review).
