# PR-005 — Ship Forge (Windows)

Install Forge without Cargo.

## Build release folder

```powershell
cd ark-forge
.\packaging\build-release.ps1 -SkipFlutter
```

Output: `ark-forge/dist/ARK Forge/` plus `BUILD.txt` (timestamp + git sha).

| File | Role |
|------|------|
| `Forge Core.exe` | Capability server (:9470) |
| `ARK Bridge.exe` | Tray app + ARK Runtime bus (:9471) |
| `BUILD.txt` | Build manifest — verify before installing |
| `ARK Forge Workbench.exe` | Optional — omit `-SkipFlutter` when Flutter is installed |

**Do not reinstall `ARK Forge Setup.exe` unless it was rebuilt in the same run.** A stale installer overwrites Program Files with old binaries (missing ARK Console tray item, missing Phase B providers).

## Update installed copy (recommended)

After every release build:

```powershell
.\scripts\sync-installed-bridge.ps1
```

Builds fresh exes and copies into `C:\Program Files\ARK Forge\` — no stale installer required.

## Run (no install)

Double-click **`ARK Bridge.exe`**. Tray menu: Forge Core status, Bridge ID, capabilities, **ARK Console…**, Pair, Logs, Settings, Start with Windows, Quit.

ARK Console runs from `{forge_repo_path}\ark-forge\console` (Python). Set `forge_repo_path` in Settings to your repo root.

First run creates `%USERPROFILE%\.ark\bridge.json`. Set `forge_repo_path` to your repo root (e.g. `C:\\Users\\edwar\\PhpstormProjects\\arksmsv2`).

Logs: `%USERPROFILE%\.ark\logs\bridge.log`

## Build installer

Install [Inno Setup 6](https://jrsoftware.org/isinfo.php), then:

```powershell
.\packaging\build-release.ps1 -SkipFlutter -BuildInstaller
```

Output: `ark-forge/dist/ARK Forge Setup.exe` (rebuilt from staged exes in the same run).

`build-release.ps1` warns when `Setup.exe` is older than staged `ARK Bridge.exe` and auto-rebuilds the installer when Inno Setup is installed.
