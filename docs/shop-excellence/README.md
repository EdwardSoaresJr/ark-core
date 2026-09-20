# Shop Excellence Doctrine

Operational knowledge for shop financial discipline and owner rhythm — used by ARK shops and product direction.

## Purpose

This library captures:

- Owner habits and financial discipline that ARK reinforces in product
- KPI definitions and report mapping
- Cursor/AI guardrails (see `.cursor/rules/ark-shop-excellence.mdc`)

## Rules

1. **Principles, not transcripts** — capture what to do, benchmark ranges, and ARK implications. Do not republish paid course material.
2. **Shop targets in Settings** — industry benchmarks may live here; *your* bands live in **Settings → Owner Targets** (`shop_settings.shop_excellence_targets`).
3. **Closed loop** — when ARK gains a metric, update [`ark-mapping/kpi-gap-matrix.md`](ark-mapping/kpi-gap-matrix.md).

## Structure

```
docs/shop-excellence/
  margin-levers.md          # ELR, matrix, ARO, workflow
  owner-rhythm.md           # daily / weekly / Day Review habit
  daily-kpis.md             # preferred management KPIs
  lugs-n-plugs/             # pointer: targets live in Settings
  private/                  # gitignored paid notes
  ark-mapping/              # KPI → ARK implementation
```

## Surfaces in ARK

| Surface | Location |
|---------|----------|
| Owner playbook | ARK Academy → Owner (admin only) |
| Day Review | Operations → Day Review (`/app/owner/day-review`) |
| Daily digest email | `shop-excellence:owner-digest` (scheduled) |
| Report KPIs | Operations → Operational Report |
| Owner targets | Settings → Owner Targets |
| AI builders | `.cursor/rules/ark-shop-excellence.mdc` |
| Private notes | `private/` (gitignored) |
