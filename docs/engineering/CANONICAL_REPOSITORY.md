# Canonical Core repository

**Public ARK** (`https://github.com/EdwardSoaresJr/ark.git`) is the canonical Core development repository. Production runs public Core.

What Core is, versus Platform and separate products: [docs/PRODUCT_BOUNDARY.md](../PRODUCT_BOUNDARY.md).

ARK Platform remains a separate repository (`https://github.com/EdwardSoaresJr/ark-platform.git`, local checkout `ark-cloud`).

`arksmsv2` is a preserved private fallback. It must receive no new Core development. Keep it for rollback and possible future licensing, not everyday work.

## Identify this tree

```bash
./scripts/assert-canonical-core-repo.sh
```

The check uses the Git root and `origin` remote URL. Directory names are not identity. Any branch in this repository is valid.

## Where to work

| Work | Repository |
| --- | --- |
| Shop Core (repair orders, estimates, inspections, scheduling, documents, Core website records) | This repo (`EdwardSoaresJr/ark`) |
| Control plane, managed services, website editor UI | `EdwardSoaresJr/ark-platform` |
| Emergency restore / private fallback only | `arksmsv2` — no new Core features |

Do not create a private Core fork. If the commercial cloud is later shut down, Platform can relocate into Docker beside Core.

## Production images

Publish only from this repository, with an explicit commit:

```bash
SOURCE_COMMIT=$(git rev-parse HEAD) ./infra/build-runner/mac/publish-ghcr-ark.sh
```

The image records that commit (`org.opencontainers.image.revision` and `/app/.ark-source-commit`). Builds from `arksmsv2` are rejected.

## Private fallback clones

The `arksmsv2` pre-commit hook is local Git config. After cloning that tree, run:

```bash
./scripts/install-legacy-commit-guard.sh
```

The hook is a safety net and can be bypassed (`git commit --no-verify`). Origin checks, protected public `main`, and image-publish identity still apply.
