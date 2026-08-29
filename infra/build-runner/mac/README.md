# Mac Build Runner — Operator Guide

**Runner:** `ark-build-01` · **Architecture v1 frozen**

## One command

```bash
ark-build up          # morning — Docker, Buildx, runner, ARK_BUILDER_ENABLED=true
ark-build down        # evening — stop runner, disable builds
ark-build down --docker   # also quit Docker Desktop
ark-build status
```

Add to PATH (once, via `install-prerequisites.sh`):

```bash
export PATH="$HOME/path/to/arksms/infra/build-runner/mac:$PATH"
```

## Workflow files (never rename production early)

| File | When |
|------|------|
| `docker-publish.yml` | **Now** — GitHub-hosted production |
| `docker-publish-mac-validation.yml` | Phase A |
| `docker-publish-mac-shadow.yml` | Phase B |
| `docker-publish.self-hosted.yml` | Phase C template only |

Phase C rollback = `git revert` on `docker-publish.yml`.

## Layout

```
~/ARK/
  ark-builder.env
  builder-enabled.env      # ARK_BUILDER_ENABLED=true|false
  build-cache/
  build-history.log        # append-only metrics
  github-runner/ark-build-01/
```

## Install (interactive steps)

```bash
# 1. Docker Desktop (needs your password in Terminal)
brew install --cask docker
open -a Docker
# Settings → docker-desktop-settings.md (12–16 GiB RAM, 6–8 CPU)

# 2. Buildx
./ensure-buildx.sh

# 3. Runner (GitHub → Settings → Actions → Runners → token)
export RUNNER_REGISTRATION_TOKEN='…'
./register-runner.sh

# 4. GHCR — see ghcr-credentials.md (scoped PAT only)
export GHCR_TOKEN='…'
./configure-ghcr-login.sh

# 5. Push repo workflows to GitHub, then:
ark-build up

# 6. GitHub Actions → Validate Mac build runner
```

## Session guard

Builds refuse unless `ark-build up` ran first (`ARK_BUILDER_ENABLED=true`).

Docker auto-start after reboot without `ark-build up` → runner may be online but **builds blocked**.

## Metrics

Every Mac build appends to `~/ARK/build-history.log`:

```
2026-06-29T… | workflow=validate-mac-build | Build: 6m 42s | Image: 1.1GB | Platform: amd64 | …
```

Full plan: [docs/deployment/mac-pro-build-runner-v1.md](../../../docs/deployment/mac-pro-build-runner-v1.md)
