# ARK Cursor Runtime Provider

Observe-only **Cursor runtime** provider for ARK Runtime. Exposes editor state — not Cursor AI.

Phase A proves the provider independently of Bridge:

```
Prove the endpoint → prove the routing → prove the system
```

## What it does

On activation, the extension:

1. Reads `%USERPROFILE%\.ark\bridge.json` for the Bridge bearer token
2. Starts `http://127.0.0.1:9472`
3. Exposes three observe capabilities via VS Code API

| Capability | Returns |
|------------|---------|
| `cursor.workspace.folders.read` | Workspace folder names and paths |
| `cursor.editor.active.read` | Active file path and language |
| `cursor.editor.selection.read` | Selected text and 1-based line range |

No invoke. No AI. No Bridge registration yet — that is Phase B.

## Endpoints

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET | `/health` | No | Liveness + protocol version (Bridge polls this) |
| GET | `/manifest` | Bearer | Provider identity + capability catalog |
| GET | `/capabilities` | Bearer | Legacy alias — prefer `/manifest` |
| POST | `/observe` | Bearer | `{ "capability": "cursor.editor.selection.read" }` |
| GET | `/observe?capability=...` | Bearer | Same as POST for quick curl |

### `/health`

```json
{
  "healthy": true,
  "provider_version": "0.1.0",
  "bridge_protocol": 1
}
```

### `/manifest`

Bridge discovers everything here. Registration payload is only `{ "id", "base_url" }`.

```json
{
  "provider": {
    "id": "cursor",
    "version": "0.1.0",
    "manifest_version": 1,
    "namespace": "cursor.*",
    "healthy": true
  },
  "capabilities": [ ... ]
}
```

### `/observe`

Response shape matches Bridge observe:

```json
{
  "ok": true,
  "capability": "cursor.editor.selection.read",
  "result": {
    "file": "C:\\...\\file.php",
    "selection": "selected text",
    "startLine": 42,
    "endLine": 48
  }
}
```

## Develop

```powershell
cd ark-forge/cursor-provider
npm install
npm run compile
```

In Cursor/VS Code: **Run Extension** (F5) from this folder.

## Phase A proof

With the extension running and text selected in an editor:

```powershell
.\scripts\prove.ps1
```

Success = real file path and selected text, not stub data.

## Next (Phase B — complete)

Bridge registration:

```json
{ "id": "cursor", "base_url": "http://127.0.0.1:9472" }
```

Bridge `GET /manifest`, polls `GET /health` every 10s. Unregistered namespaces return `{ "available": false, "reason": "..." }` via `MissingProvider` — not 404.

```
            ARK Runtime
         ┌──────┴──────┐
 Forge Runtime     Cursor Runtime
```

Both are workstation reality. Neither is an AI.
