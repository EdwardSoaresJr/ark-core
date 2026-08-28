# ARK Forge Flutter App

**Views only.** All capabilities go through Forge Runtime.

## Run

```powershell
# Terminal 1 — runtime (required)
cd ark-forge/runtime
$env:ARK_FORGE_REPO_PATH = "C:\path\to\arksmsv2"
cargo run

# Terminal 2 — Flutter
cd ark-forge/app
flutter create --platforms=windows,macos,linux --project-name ark_forge .
flutter pub get
flutter run -d windows
```

## Rules

Flutter must **not**:

- Invoke `git`
- Read engineering files from disk
- Launch Cursor, terminal, or browser
- Inspect OS processes

Flutter calls `ForgeRuntimeClient` → `http://127.0.0.1:9470`.

Override runtime URL:

```powershell
flutter run --dart-define=FORGE_RUNTIME_URL=http://127.0.0.1:9470
```
