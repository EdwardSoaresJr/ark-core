# ADR-0001: Capability Identity

**Status:** Accepted  
**Date:** 2026-06-26  
**Supersedes:** v0.1 string-based registry (`domain` + `action` verbs)

## Context

Forge Core v0.1 proved the architectural boundary: Workbench maps intent → generic invoke → workstation. Capabilities were registered as domain strings (`shell`, `browser`) with action verbs (`open_application`, `open_url`).

That proof was sufficient for one button. It is not sufficient for a platform.

Every future client — Workbench, Cursor, MCP, plugins — would otherwise rediscover capabilities ad hoc. Replacing Rust Core or changing Flutter would fracture the contract unless capability names are **stable, hierarchical, and implementation-independent**.

## Decision

Every Forge Core capability has a **permanent Capability Identity** with seven attributes:

| Attribute | Purpose |
|-----------|---------|
| **Identity** | Stable hierarchical ID — never changes across Core implementations |
| **Domain** | Capability family (`browser`, `git`, `shell`, …) |
| **Operation** | What the capability does (`open`, `read`, `status`, …) |
| **Arguments** | Typed inputs the capability accepts |
| **Version** | Schema version for arguments and result shape |
| **Permissions** | What the capability may access (filesystem path, network, spawn, …) |
| **Stability** | Contract promise: `stable`, `experimental`, or `deprecated` |

### Identity format

```
core.{domain}.{resource}.{operation}
```

Examples:

| Identity | Domain | Operation | Stability |
|----------|--------|-----------|-----------|
| `core.browser.url.open` | browser | open | stable |
| `core.filesystem.file.read` | filesystem | read | stable |
| `core.filesystem.watch.start` | filesystem | start | experimental |
| `core.git.status.read` | git | read | stable |
| `core.shell.application.open` | shell | open | stable |
| `core.shell.session.open` | shell | open | experimental |
| `core.processes.list.read` | processes | read | stable |

Rules:

- IDs are **lowercase**, dot-separated, **no product vocabulary** (`ark`, `lugsnplugs`, `provisioning`, `milestone`).
- IDs are **never reused** for different semantics. Deprecate; do not recycle.
- Implementation details (Rust module, Windows API, Flutter widget) **never appear** in IDs.

### Capability IDs are immutable

Once a capability ID is published, its meaning is frozen — **treat IDs exactly like public APIs**.

| Allowed | Forbidden |
|---------|-----------|
| Publish `core.browser.url.open` and keep it forever | Rename `core.browser.url.open` → `core.browser.open_url` |
| Deprecate and document replacement | Change semantics under the same ID |
| Introduce `core.browser.url.open` v2 via **version** field or a new ID | Recycle an ID for unrelated behavior |

If a capability fundamentally changes:

- Bump the **version** field on the capability record (arguments / result schema), or
- Publish a **new capability ID** and mark the old one `deprecated`.

Clients depend on IDs. Renaming breaks Workbench, MCP, plugins, and scripts silently.

```
core.browser.url.open          ✓  published, stable
core.browser.open_url          ✗  rename — never do this
core.browser.url.open (v2)     ✓  version field or new ID if contract breaks
```

### Example capability record

```json
{
  "id": "core.browser.url.open",
  "display_name": "Open URL",
  "description": "Open a URL in the system default browser.",
  "domain": "browser",
  "resource": "url",
  "operation": "open",
  "version": 1,
  "stability": "stable",
  "permissions": ["network"],
  "arguments": [
    {
      "name": "url",
      "type": "url",
      "required": true
    }
  ],
  "result": null
}
```

Observable capabilities (Git status, process list) declare a **result schema** instead of performing a side effect:

```json
{
  "id": "core.git.status.read",
  "display_name": "Git Status",
  "domain": "git",
  "resource": "status",
  "operation": "read",
  "version": 1,
  "stability": "stable",
  "permissions": ["filesystem.read"],
  "arguments": [
    {
      "name": "repo_path",
      "type": "directory",
      "required": false
    }
  ],
  "result": {
    "type": "object",
    "description": "Branch, clean/dirty, ahead/behind"
  }
}
```

### Invoke contract

```
POST /api/v1/core/capabilities/{capability_id}/invoke
```

Body: **arguments only** — no `action` string.

```json
{
  "url": "https://github.com/edwardsoaresjr/arksmsv2"
}
```

Response:

```json
{
  "ok": true,
  "capability_id": "core.browser.url.open",
  "result": null
}
```

For observable capabilities, `result` carries data. Workbench and Projection render it; Core does not interpret engineering meaning.

### Registry contract

```
GET /api/v1/core/capabilities
```

Returns the full capability catalog with identity metadata so clients can:

- Build command palettes without hardcoded forms
- Validate arguments before invoke
- Display stability and permissions to the operator

v0.1 transitional registry (domain + verb) remains supported until v0.2 migrates all entries to Capability Identity.

### Core API modes

Forge Core exposes two complementary operations:

| Mode | Purpose | Example |
|------|---------|---------|
| **invoke** | Cause a side effect | `core.browser.url.open` |
| **observe** | Read workstation state | `core.git.status.read` |

Both use the same identity system. The difference is whether the capability returns operational data.

## Consequences

### Positive

- Flutter, Cursor, MCP, and future plugins speak one language
- Rust Core can be replaced without changing Workbench or capability IDs
- Registry metadata enables auto-generated UI (command palette, argument forms)
- Runtime verb rejection (`verbs.rs`) remains the enforcement layer for forbidden product-domain strings during migration

### Negative / migration

- v0.1 `POST .../shell/invoke` + `{ "action": "open_application" }` is transitional
- Registry schema must gain identity fields before Open GitHub ships
- Workbench moves from hardcoded buttons to **Commands** (command palette)

### Explicit non-goals (this ADR)

- Session authority implementation — see [sessions-bounded-context-v1.md](../sessions-bounded-context-v1.md)
- AI, MCP transport, plugin loading
- Engineering milestone or PR vocabulary in Core

## Roadmap alignment

After this ADR:

1. Migrate registry to Capability Identity metadata
2. **Open GitHub** → Workbench command → `core.browser.url.open`
3. **Git Status** → first observable capability → `core.git.status.read`
4. Document Sessions (no implementation)
5. **Open Terminal** → `core.shell.session.open` (not `open_terminal`)

## References

- [runtime-authority-v1.md](../runtime/runtime-authority-v1.md)
- [doctrine.md](../doctrine.md)
- [glossary.md](../glossary.md)
- [capability-identity.schema.json](../../protocol/capability-identity.schema.json)
