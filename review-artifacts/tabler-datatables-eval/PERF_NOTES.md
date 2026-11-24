# Performance notes (eval)

Measured locally against the shared `JobBoardListPageBuilder` path (same work as production List).

## Method

- Environment: local Herd `ark-core.test`, synthetic/demo tenant data (not production customers).
- A: `GET /app?view=list` (PR #254 List)
- B: `GET /app/eval/job-board-datatables/data` DataTables JSON (length=25)
- Larger synthetic set: builder in-memory filter/sort on N loaded open ROs (query still loads the advisor open set once).

## Findings (representative)

Builder micro-benchmark (local, ~21 open ROs, no HTTP): **~67ms** filter/sort/page via `JobBoardListPageBuilder`.

| Surface | Queries (approx) | Server time | Notes |
|---------|------------------|-------------|-------|
| A List HTML page | ~35–45 | ~100–150ms @ ~20 ROs | Full Blade rows + cards dual render |
| B DT JSON | ~35–45 | similar / slightly lower HTML | Same projection; less Blade for unused cards on AJAX redraw |
| B DT page shell | ~35–45 | similar to A | Plus CDN jQuery+DT (~90KB gz combined) client weight |

## Complexity

| | Current List (#254) | DT prototype |
|--|---------------------|--------------|
| Server sort/filter | Custom builder | Same builder |
| Client table lib | None | jQuery + DataTables 2.x (CDN eval) |
| Yajra | Not used | Not installed (protocol-compatible bridge) |
| Custom PHP for list UX | High (sort URLs, aria, pagination) | Lower chrome; still high projection |
| Card coexistence | Dual DOM | Dual mode: DT list + server cards |

## Regressions / costs of DataTables

- jQuery dependency (ARK staff shell is Alpine + Tabler Bootstrap; jQuery is otherwise unused).
- Second request pattern for every sort/page (acceptable) but duplicates auth/query warm-up unless cached.
- Accessible sort UX must be re-tuned; default DT header cues need Tabler CSS overrides (done in prototype).
- Does **not** remove the need for `JobBoardListPageBuilder` semantic sorts (attention rank, total cents, concern search).
- Column visibility / export are unused in this prototype; enabling Buttons adds more JS weight.
