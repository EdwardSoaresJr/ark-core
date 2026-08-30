# Coolify / container runtime packaging

Generic files required by the root `Dockerfile` for pull-based production and self-host images:

- `bootstrap-app-key.sh` — durable APP_KEY generate/restore before supervisord
- `entrypoint.sh` — storage layout, cache clears, supervisord
- `supervisord.conf` — php-fpm, nginx, horizon, reverb, scheduler
- `php-fpm-www.conf` — small-host php-fpm pool profile (applied via Dockerfile sed)
- `ark-post-deploy.sh` — migrate + non-destructive post-start tasks

Shop-specific Coolify profiles, backup scripts, and secrets do **not** belong here.
