# Canonical Core repository

**ARK Core** (`https://github.com/EdwardSoaresJr/ark-core.git`) is the canonical Core development repository.

What Core is, versus Platform and separate products: [docs/PRODUCT_BOUNDARY.md](../PRODUCT_BOUNDARY.md).

ARK Platform remains a separate repository (`https://github.com/EdwardSoaresJr/ark-platform.git`).

`arksmsv2` is a legacy private tree. It must receive no new Core development. LugsNPlugs production may keep running from its current Core image until an explicit public-ARK cutover.

## Identify this tree

```bash
./scripts/assert-canonical-core-repo.sh
```

The check uses the Git root and `origin` remote URL. Directory names are not identity.

## Where to work

| Work | Repository |
| --- | --- |
| Shop Core (repair orders, estimates, inspections, scheduling, documents, Core website records) | This repo (`EdwardSoaresJr/ark-core`) |
| Control plane, managed services, website editor UI | `EdwardSoaresJr/ark-platform` |
| Legacy LugsNPlugs production image only | `arksmsv2` — no new Core features |

Do not create a private Core fork. If the commercial cloud is later shut down, Platform can relocate into Docker beside Core.
