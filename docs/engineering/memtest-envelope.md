# Memory envelope certification

Maintainer tooling for measuring Compose under constrained RAM.

**Not** a stranger VPS install guide. Do not run these overlays on production shops.

| File | Role |
|------|------|
| `docker-compose.memtest-1g.yml` | ~1 GiB envelope (small host + swap) |
| `docker-compose.memtest-2g.yml` | ~2 GiB envelope (starter VPS class) |
| `scripts/memtest-envelope.sh` | Starts the overlay, samples `docker stats`, writes results under `/tmp` |

```bash
./scripts/memtest-envelope.sh 2g
./scripts/memtest-envelope.sh 1g
```

Results land in `/tmp/ark-memtest-*-result.txt` and `/tmp/ark-memtest-*-stats.csv` on the build machine. Those files are temporary and must not be committed.
