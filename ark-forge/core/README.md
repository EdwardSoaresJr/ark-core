# Forge Core

**Authority for local workstation capabilities.**

Not engineering. Not AI. Not product domain.

## v0.1 API

| Method | Path |
|--------|------|
| GET | `/api/v1/core/capabilities` |
| POST | `/api/v1/core/capabilities/{id}/invoke` |
| GET | `/health` |

Engineering routes (`/api/v1/engineering/*`) are served by **Projection** in the same process — Core does not parse milestones, PRs, or customers.

## Generic verbs only

Capabilities are domains (`filesystem`, `git`, `shell`). Actions are generic verbs: `read_file`, `open_application`, `git_status`, etc.

See [../docs/v0.1-capability-proof.md](../docs/v0.1-capability-proof.md).

## Run

```powershell
$env:FORGE_REPO_PATH = "C:\path\to\arksmsv2"
cd ark-forge
cargo run -p forge-server
```

Default: `http://127.0.0.1:9470`

HTTP routes live in `server/` — Core library has no dependency on Projection.

## Capability quality

Before a capability is registered as `stable`, it must have at least one invoke test or observe test in `forge-core`. `experimental` capabilities may ship without tests; promotion to `stable` requires a test.

Every **Observe** snapshot must include `observed_at` (RFC3339).

## Doctrine

[../docs/runtime/runtime-authority-v1.md](../docs/runtime/runtime-authority-v1.md)
