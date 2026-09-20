# Docker Desktop — recommended settings for ARK builds

**Machine class:** Apple Silicon Mac build workstation (16+ GiB RAM recommended)  
**Role:** Build worker only — never run production containers here.

Open **Docker Desktop → Settings → Resources**:

| Setting | Recommended | Why |
|---------|-------------|-----|
| **CPUs** | 6–8 | Parallel BuildKit layers without starving macOS |
| **Memory** | 12–16 GiB | Composer + npm + Vite + BuildKit headroom |
| **Disk image size** | 100+ GiB | Layer cache grows over weeks; predictable cleanup |
| **VirtioFS** | On (default) | Faster bind mounts if used |

## Where data lives

| Path | Purpose |
|------|---------|
| `~/ARK/build-cache/` | BuildKit local cache (workflow + manual builds) |
| `~/ARK/github-runner/ark-build-01/` | GitHub Actions runner install (**disposable**) |
| `~/ARK/ark-builder.env` | Paths and labels (copy from `ark-builder.env.example`) |
| Docker Desktop VM | Internal image/layer storage — prune via Docker Desktop or `docker system df` |

**Convention:** Everything ARK-build-related under `~/ARK/`. No hidden magic in `~/actions-runner`.

To relocate Docker Desktop's disk image (optional): Docker Desktop → Settings → Resources → Advanced → Disk image location — point at a folder under `~/ARK/` if you want one tree for cleanup.

## Builder only — not runtime

| Build Mac | Production VPS |
|-------------|----------------|
| GitHub runner | Pull GHCR image |
| Docker build + push | Run containers |
| BuildKit cache | MySQL, Redis, Traefik |
| | Serve customers |

## Monthly hygiene

```bash
docker system df
du -sh ~/ARK/build-cache
docker builder prune -f   # when cache > ~20 GiB
```

Do not back up the runner binary — back up repo, workflows, and `~/ARK/ark-builder.env`.
