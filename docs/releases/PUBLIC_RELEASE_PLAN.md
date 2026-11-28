# Formal public release plan (Gate H)

## Current version metadata

| Source | Finding |
| --- | --- |
| Git tags | No `v*` release tags on `main` at plan time — first formal release is greenfield |
| `config/app.php` | `version` ← `APP_VERSION` (optional env) |
| Compose / images | Commit identity via `.ark-source-commit` / `ARK_SOURCE_COMMIT_FILE` |

**Approved version line (do not publish tags until authorized):** `v1.0.0-rc.1` → owner clean-room acceptance → `v1.0.0` stable.

Do not publish either tag or a stable GitHub Release before owner acceptance of independent installation tests. Checklist: `PUBLIC_INSTALL_ACCEPTANCE_CHECKLIST.md`.

## Feature freeze

1. Freeze scope for the RC: shop-essential Core only (see PRODUCT_BOUNDARY). No new commercial integrations in the RC window.
2. RC branch or tagged commit from `main` after Gate G hosting readiness criteria pass.
3. Only release-blocking fixes land after freeze; everything else waits.

## Release candidate process

1. Tag `vX.Y.Z-rc.N` on the freeze commit.
2. CI builds the **Release ZIP** artifact + checksums; Docker image publish remains the existing GHCR path when needed.
3. Publish a **pre-release** on GitHub with ZIP, checksums, and install docs links.
4. Run independent installation acceptance (below).
5. Promote to `vX.Y.Z` only after owner sign-off.

## Upgrade policy

- Semantic versioning for public tags.
- Database migrations run via `/setup` upgrade path or `php artisan migrate` on VPS/Docker.
- Document breaking changes in the GitHub Release notes.
- Supported upgrade: previous stable → current stable (N-1). Older jumps are best-effort until stated otherwise.

## Supported hosting requirements (public)

| Posture | Requirements |
| --- | --- |
| Docker Compose | Docker Engine + Compose; published compose files |
| Native VPS | PHP **8.4.1+**, MySQL 8, Composer, Node 22 for build **or** deploy from Release ZIP; cron; optional Redis/Horizon/Reverb/Chromium |
| Shared hosting | PHP **8.4.1+** with required extensions (`gd`, `zip`, …); MySQL; docroot `public/`; writable `storage/` + `bootstrap/cache`; Release ZIP only; cron preferred |

Honest limits (labels/mm print, WebSockets, Horizon) are documented per posture — not hidden.

## Release artifact workflow

1. `scripts/build-release-zip.sh` / `release-zip.yml`
2. Optional GHCR image for Docker posture (existing publish scripts)
3. Checksums + `RELEASE.json` commit/version stamp
4. GitHub Release attaches artifacts; AGPL license text remains in tree

## Independent installation acceptance (required before stable)

Clean-room only: public artifacts + published instructions. No undocumented commands, private repos, developer `.env`, or source edits.

| Matrix | Pass criteria |
| --- | --- |
| Fresh shared hosting | ZIP upload → docroot `public` → `/setup` → shop usable |
| Fresh native VPS | Published PHP or ZIP path → `/setup` → cron documented |
| Fresh Docker | Published compose → `/setup` |
| Upgrade | Prior supported install → current artifact → migrate → shop usable |

Post-install realistic workflows: estimate authorization, inspection, invoicing, email, payment recording, backup and restore.

**Any undocumented workaround is an acceptance failure** and blocks stable publication.

Owner must be able to repeat the installation from public docs alone.
