# Engineering Projection

Derives `EngineeringState` by reading the repository through **Forge Core capabilities**.

## Knows

- Milestones, active PR, ADRs, reviews, implementation log
- How to parse `docs/engineering/*.md`

## Does not know

- How to open a browser (asks Core)
- How git works on Windows vs macOS (asks Core)

## API (mounted by forge-core binary)

| Method | Path |
|--------|------|
| GET | `/api/v1/engineering/state` |
| GET | `/api/v1/engineering/docs/{slug}` |

Flutter renders `EngineeringState`. Core never parses milestone titles.
