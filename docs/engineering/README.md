# Engineering

Maintainer notes for people working in this repository. **Not** the public product manual.

Operators: start at the [root README](../../README.md) and [PRODUCT_BOUNDARY.md](../PRODUCT_BOUNDARY.md).

## Public / stable

| File | Role |
| --- | --- |
| [CANONICAL_REPOSITORY.md](CANONICAL_REPOSITORY.md) | This git tree is public Core; `arksmsv2` is fallback only |
| [adr/](adr/) | Accepted decisions — do not edit; supersede |
| [STANDARDS.md](STANDARDS.md) | Engineering standards |
| [TECHNICAL_DEBT.md](TECHNICAL_DEBT.md) | Known residue, including leftover provider names/schema |

[ARCHITECTURE.md](ARCHITECTURE.md) is **Voice endpoint doctrine**, not a Core product overview.

## Working (maintainers)

| File | Role |
| --- | --- |
| [CURRENT_MILESTONE.md](CURRENT_MILESTONE.md) | What maintainers are building now |
| [ACTIVE_PR.md](ACTIVE_PR.md) | Current PR scope, if any |
| [ROADMAP.md](ROADMAP.md) | Longer phases |

These files can describe a live shop. They are not install instructions.

## Domain docs

Communications, operations, and platform files under `docs/` remain the detailed notes for those areas. Do not treat every notebook as a shipped feature.

Confirm this tree with `./scripts/assert-canonical-core-repo.sh`. Production images require `SOURCE_COMMIT`.
