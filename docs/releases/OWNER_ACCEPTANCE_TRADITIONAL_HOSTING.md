# Owner acceptance — Traditional hosting (Core #292)

**Status:** ready for independent owner review. Do **not** merge #292, publish a GitHub Release, create a tag, or advance the SaaS Core pin without separate authorization.

Feature freeze: `v1.0.0` train. No unrelated feature expansion.

## Final candidate

| Field | Value |
| --- | --- |
| Candidate SHA | `3e9fa5ae505fed278a7bac12702a0162c8050569` (rebuild after CI PHP/fallback fixes may advance tip) |
| Release label | `1.0.0-rc.1` (proposed; not tagged) |
| Artifact | `dist/ark-core-1.0.0-rc.1-3e9fa5ae.zip` (local/CI; not committed) |
| SHA-256 | `1270a6706e0caedcc48ee7b253ecabf78e5fb43f0b7f26d316a80dfa15bf517a` |
| Review pack | `review-artifacts/traditional-hosting-292/` |

## Recommended minimum hosting requirements

- PHP **8.4+** (Composer lock currently requires `>=8.4.1` via Symfony 8.1; `composer.json` still declares `^8.3` aspirationally) with extensions: `mbstring`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `pdo_mysql`, `gd`, `zip`, `tokenizer`, `openssl`
- MySQL **8.0+** (empty database for fresh install)
- HTTPS-capable web server; **document root = `public/`**
- Writable `storage/` and `bootstrap/cache/`
- Cron available (preferred) for `schedule:run` and `ark:queue-work-bounded`
- Outbound SMTP for native customer email (shop-controlled)

## Shared hosting compatibility checklist

- [ ] PHP 8.3+ with required extensions (no need for Redis, Horizon, Reverb, Node, Chromium, or Composer on the server)
- [ ] `exec` / `proc_open` may be disabled — installer finalizes synchronously
- [ ] Document root points at `public/` (not the archive root)
- [ ] `.htaccess` / nginx rules keep `.env`, `vendor/`, `storage/` non-public
- [ ] Empty MySQL database; installer refuses a foreign schema
- [ ] Cron: `* * * * * php /path/to/artisan schedule:run`
- [ ] Cron: `* * * * * php /path/to/artisan ark:queue-work-bounded`
- [ ] SMTP credentials entered in Settings after install

## Installation instructions (Release ZIP)

1. Download the Release ZIP and verify the SHA-256 against the `.sha256` file.
2. Extract above the web root (example: `~/ark-core/`).
3. Point the vhost document root at `…/public/`.
4. Ensure `storage/` and `bootstrap/cache/` are writable by the PHP user.
5. Open `https://your-shop.example/setup` and complete: system → database → shop → admin → install → progress → complete.
6. Configure cron (see checklist).
7. In Settings, configure installation SMTP for customer email.
8. Confirm installer routes are locked after success.

Public references:

- `docs/knowledge-base/getting-started/install-php.md` (ZIP path for hosts without Composer/Node)
- `docs/releases/constrained-hosting-cron.md`
- `docs/releases/traditional-hosting-runtime-profiles.md`
- `docs/releases/PUBLIC_INSTALL_ACCEPTANCE_CHECKLIST.md`

## Upgrade instructions

1. Put the shop in a maintenance window (hosting panel or temporary hold).
2. Backup database + `storage/` + `.env`.
3. Extract the new Release ZIP beside the live tree (or replace code files while preserving `.env` and `storage/`).
4. Keep the existing `.env`; do not reuse a packaged `.env` (none ships).
5. Run migrations via `/setup` upgrade path when offered, or `php artisan migrate --force` on VPS with CLI.
6. Clear caches if applicable (`php artisan optimize:clear` when CLI is available).
7. Smoke-test login, estimate PDF, invoice, and outbound email.
8. Supported jump: previous stable → current stable (N−1).

## Backup and restoration

**Backup**

- MySQL dump of the shop database
- Entire `storage/` tree (uploads, generated PDFs, logs as needed)
- `.env` (secrets — store offline)
- Optional: copy of the installed application tree for rollback

**Restore**

1. Restore application files (or re-extract the same Release ZIP).
2. Restore `.env` and `storage/`.
3. Import the MySQL dump into an empty database matching `.env`.
4. Ensure `storage/` and `bootstrap/cache/` permissions.
5. Open the shop URL; confirm login and a sample document PDF.

## Known limitations

- Chromium/Browsershot PDF is optional. Constrained hosts use the native PHP (mPDF) engine. Presentation is professional and financially equivalent; pixel parity with Chromium is **not** required.
- Without Redis: no Horizon, limited realtime (Reverb). Use database/sync queues + cron.
- Label / QZ Tray printing needs shop-supplied certificates; not part of the ZIP.
- ARK Shop commercial extras (SMS, voice, Square, PartsTech, etc.) are out of Core boundary — not in this artifact.
- `disable_functions` that remove `exec`/`proc_open` forces synchronous install finalize (supported).

## Release artifact download location

Until a GitHub Release is authorized, the candidate ZIP for owner testing is:

- Local/CI: `dist/ark-core-1.0.0-rc.1-<shortsha>.zip` (+ `.sha256`)
- PR packaging workflow artifact: `ark-core-release-zip` on pull requests that touch packaging paths
- Owner review pack: `review-artifacts/traditional-hosting-292/` (PDFs, previews, checksums, this doc)

Do **not** treat `lnp.arksms.com` or SaaS production as the traditional-hosting acceptance target.

## Owner sign-off

- [ ] Independent install from ZIP + these instructions only
- [ ] Estimate / invoice / inspection / statement PDFs reviewed
- [ ] Mail attachments acceptable
- [ ] Constrained cron + native email verified
- [ ] Authorize merge of #292 separately
- [ ] Authorize tag / GitHub Release / SaaS pin separately
