# ARK Bridge

**ARK local capability bus** — authentication, provider discovery, and routing. Bridge belongs to **ARK**, not Forge.

Providers register capabilities by namespace (`forge.*`, `cursor.*`, `voice.*`, …). Bridge answers *who can handle this?* — never *what is the answer?*

## Provider bus

| Endpoint | Purpose |
|----------|---------|
| `GET /bridge/providers` | Provider lifecycle (id, version, health, namespace, registered) |
| `POST /bridge/providers/register` | Register runtime provider — payload `{ id, base_url }`; Bridge discovers via `GET /manifest` |
| `GET /bridge/state` | Merged capability catalog + client identity |
| `POST /bridge/observe` | Route observe capability to owning provider |
| `POST /bridge/invoke` | Route invoke capability to owning provider |

Bridge polls registered providers at `GET /health` every 10 seconds. No heartbeat endpoint.

Unregistered namespaces (e.g. `cursor.*` with no runtime provider) return `{ "available": false, "reason": "..." }` — not 404.

**Frozen after Phase B:** no invoke routing, no new Bridge features until the observation notebook earns them.

**No Cargo required** after build:

```
ark-forge/dist/ARK Forge/
  Forge Core.exe
  ARK Bridge.exe
```

Double-click **ARK Bridge.exe**. System tray menu:

- Forge Core status (running / offline)
- Bridge Connected
- Bridge ID, Capabilities count, Version
- **Status…** — diagnostics window (`http://127.0.0.1:9471/bridge/diagnostics`)
- **ARK Console…** — open interactive ARK Console at `ark-forge/console` (Bridge URL + token injected)
- **Pair** — show bearer token
- **Logs** — open `%USERPROFILE%\.ark\logs\`
- **Settings** — open `bridge.json` in Notepad
- **Check for Updates** — placeholder (v0.1)
- **Start with Windows**
- **Quit**

First run creates `%USERPROFILE%\.ark\bridge.json`. Set `forge_repo_path` to your repo:

```json
{
  "forge_repo_path": "C:\\Users\\edwar\\PhpstormProjects\\arksmsv2",
  "autostart_forge_core": true,
  "autostart_with_windows": false
}
```

Bridge spawns **Forge Core.exe** from the same folder when `autostart_forge_core` is true.

### Client identity

Authenticated clients may send:

```
X-ARK-Client: Forge Workbench
X-ARK-Client-Version: 0.1
```

Bridge records the last connected client for tray + diagnostics display.

### Build release

```powershell
cd ark-forge
.\packaging\build-release.ps1 -SkipFlutter
```

### Installer

Install [Inno Setup 6](https://jrsoftware.org/isinfo.php), then:

```powershell
.\packaging\build-release.ps1 -SkipFlutter -BuildInstaller
```

Produces `dist/ARK Forge Setup.exe` → installs to `C:\Program Files\ARK Forge\`.

See `packaging/README.md`.

---

## Developer mode

```powershell
cd ark-forge
cargo run -p ark-bridge
```

CLI helpers:

```powershell
ark-bridge pair
ark-bridge reset-secret
ark-bridge serve          # headless HTTP only
```

## V1 endpoints

All require `Authorization: Bearer {bridge_secret}`.

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/bridge/ping` | Liveness |
| GET | `/bridge/version` | Bridge version |
| GET | `/bridge/state` | Discovery |
| POST | `/bridge/invoke` | Forward invoke |
| POST | `/bridge/observe` | Forward observe |
| GET | `/bridge/events` | WebSocket events |
