# ARK Forge Protocol

Contracts between **Forge Core**, **Engineering Projection**, and **Workbench** (Flutter).

## Engineering State

[engineering-state.schema.json](engineering-state.schema.json) — projection shape served at `GET /api/v1/engineering/state`.

Built by Projection from Git + `docs/engineering/`. Never stored as authority.

## Capability Identity (ADR-0001)

[capability-identity.schema.json](capability-identity.schema.json) — stable hierarchical IDs (`core.browser.url.open`), typed arguments, stability, permissions.

Target registry shape. v0.1 transitional registry: [capability-registry.schema.json](capability-registry.schema.json).

## Core API (v0.1)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/core/capabilities` | Registry — generic verbs only |
| POST | `/api/v1/core/capabilities/{id}/invoke` | Execute generic verb |
| GET | `/health` | Liveness |

### Invoke example (Open Cursor)

```json
POST /api/v1/core/capabilities/shell/invoke
{
  "action": "open_application",
  "application": "cursor",
  "path": "C:\\path\\to\\arksmsv2"
}
```

Workbench maps user intent → generic verb. Core never exposes `open_cursor`, `review_pr1`, etc.

## Projection API

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/engineering/state` | Engineering workbench dashboard |
| GET | `/api/v1/engineering/docs/{slug}` | Read authority markdown |

## Future clients

Cursor, MCP, and other agents speak the same Core + Projection API. No product-specific Core endpoints.
