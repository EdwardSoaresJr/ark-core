# Public installation & upgrade acceptance checklist

Version line (approved, **not published yet**):

- `v1.0.0-rc.1`
- `v1.0.0` only after owner acceptance

Feature freeze remains in effect for the release train.

## Rules

- Use **only** the published Release ZIP (or published Docker/VPS docs) and public documentation.
- No undocumented commands, private repositories, developer `.env` files, or source edits.
- Any undocumented workaround is an **acceptance failure**.

## Fresh shared hosting (Release ZIP)

- [ ] Upload ZIP; extract above the web root
- [ ] Point document root at `public/`
- [ ] PHP 8.4.1+ with required extensions; MySQL empty database
- [ ] `storage/` and `bootstrap/cache/` writable
- [ ] Open `/setup` over HTTPS
- [ ] Complete system → database → shop → admin → install → progress → complete
- [ ] Installer routes locked after success
- [ ] Cron minimum configured (see below)

## Fresh native VPS

- [ ] Follow `docs/knowledge-base/getting-started/install-php.md` **or** deploy Release ZIP
- [ ] Document root `public/`; HTTPS; cron
- [ ] Optional Redis/Horizon/Reverb/Chromium only when available

## Fresh Docker

- [ ] Published Compose path only
- [ ] `/setup` completes; workers healthy

## Upgrade

- [ ] Prior supported install → current artifact
- [ ] Migrations complete; shop remains usable
- [ ] No manual SQL required

## Post-install shop workflows

- [ ] Create repair order
- [ ] Estimate authorization (customer-facing PDF attached/downloadable)
- [ ] Digital vehicle inspection PDF
- [ ] Invoice + payment recording
- [ ] Customer statement PDF
- [ ] Native email (appointment + document) via Settings SMTP on standalone
- [ ] Staff authentication + password recovery
- [ ] Backup and restore per published docs

## Minimum cron (constrained / shared / VPS without Horizon)

```
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
* * * * * php /path/to/artisan ark:queue-work-bounded >> /dev/null 2>&1
```

When `QUEUE_CONNECTION=sync`, the bounded worker is a no-op; keep `schedule:run`.

## Owner sign-off

- [ ] Owner independently repeated installation from public docs alone
- [ ] RC accepted → authorize `v1.0.0` tag/publish separately
