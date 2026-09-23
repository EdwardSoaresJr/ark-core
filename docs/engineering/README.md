# Engineering

| File | Role |
| --- | --- |
| [CURRENT_MILESTONE.md](CURRENT_MILESTONE.md) | What we are working on now |
| [ACTIVE_PR.md](ACTIVE_PR.md) | Current PR scope, if any |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Stable architecture notes |
| [STANDARDS.md](STANDARDS.md) | Engineering standards |
| [adr/](adr/) | Accepted decisions - do not edit; supersede |
| [TECHNICAL_DEBT.md](TECHNICAL_DEBT.md) | Known debt to retire |
| [CANONICAL_REPOSITORY.md](CANONICAL_REPOSITORY.md) | Public ARK is canonical Core; production runs it; `arksmsv2` is a fallback |
| [RELEASE_DISTRIBUTION.md](RELEASE_DISTRIBUTION.md) | Core release targets, sequence, and safety. Fleet deploy is disabled. |

Do not develop Core in `arksmsv2`. Confirm this tree with `./scripts/assert-canonical-core-repo.sh`. Production images require `SOURCE_COMMIT`.
