# ARK Forge

**The Engineering Operating System.**

Engineering knowledge is permanent. Agents are disposable. **Git remains the source of truth.**

---

## Four bounded contexts

| Context | Authority | Owns |
|---------|-----------|------|
| **Repository** | Git + `docs/engineering/` | Milestone, PR, ADR, ship record |
| **Forge Core** | Local capabilities | What this workstation can do right now |
| **Engineering Projection** | Interpreted state | Engineering dashboard from repo truth |
| **Workbench** | Flutter UI | User intent and command interaction |
| **Sessions** (future) | Workspace grouping | Open repos, terminals, tabs — [named, not built](docs/sessions-bounded-context-v1.md) |

```
Forge Workbench (Flutter)
        │
        ▼
Engineering Projection        ← milestones, ADRs, reviews
        │
        ▼
Forge Core                    ← capability graph
        │
        ▼
Windows / macOS / Linux
```

**Forge Core must never know ARK Voice.** No provisioning, customers, or milestones as domain concepts.

**Doctrine:** [docs/doctrine.md](docs/doctrine.md) · **Glossary:** [docs/glossary.md](docs/glossary.md) · **ADR-0001:** [Capability Identity](docs/adr/ADR-0001-capability-identity.md) · **Roadmap:** [docs/ROADMAP.md](docs/ROADMAP.md)

---

## Layout

```
ark-forge/
├── app/           Workbench — views only
├── bridge/        ARK Bridge — transport + tray product
├── console/       ARK Console — local OpenAI client (v0.1)
├── core/          Forge Core — capabilities
├── projection/    Engineering Projection — EngineeringState
├── packaging/     Windows installer + release scripts
├── protocol/      JSON contracts
└── docs/
```

## Quick start

```powershell
# Forge Core (required)
cd ark-forge/core
$env:FORGE_REPO_PATH = "C:\path\to\arksmsv2"
cargo run

# Workbench
cd ark-forge/app
flutter create --platforms=windows,macos,linux --project-name ark_forge .
flutter pub get
flutter run -d windows
```

## ARK Console (v0.1 — local OpenAI client)

See [console/README.md](console/README.md). Requires ARK Bridge running.

```powershell
cd ark-forge/console
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
copy console.env.example .env   # add OPENAI_API_KEY + ARK_BRIDGE_TOKEN
python -m ark_console ask "What's my git status?"
```

## Capability invoke (v0.1 proof)

Workbench maps intent → generic Core verb:

```
GET  /api/v1/core/capabilities
POST /api/v1/core/capabilities/shell/invoke   { "action": "open_application", "application": "cursor", "path": "..." }
GET  /api/v1/engineering/state                ← projection layer
```

See [docs/v0.1-capability-proof.md](docs/v0.1-capability-proof.md).

v0.2 migrates to stable capability IDs per [ADR-0001](docs/adr/ADR-0001-capability-identity.md) (`core.shell.application.open`, etc.).

## Roadmap

See [docs/ROADMAP.md](docs/ROADMAP.md). Architectural Baseline v1 is committed; Capability Identity ADR is the current foundation pass.
