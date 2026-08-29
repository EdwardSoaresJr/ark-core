# Installation troubleshooting

## Setup redirects forever / blank page

- Confirm `storage/app` and `storage/framework` are writable.
- Run `php artisan ark:install-status`.
- Check `storage/logs/laravel.log` (no secrets should appear from the installer).

## Cannot write environment

Immutable hosts must provide `APP_KEY`, `APP_URL`, and `DB_*` via the platform. The wizard will not chmod the filesystem.

## Database connection failed

- Create an **empty** MySQL database first.
- Verify host/port/user/password.
- The installer never logs the password.

## Database not empty

The installer **fails closed**. It will not migrate or overwrite a populated or foreign schema. Point ARK at an empty database.

## Migration failed

Installation is not marked complete. Fix the error in server logs, then retry. Do **not** run `migrate:fresh` or `db:wipe` against a shop you care about.

## Setup interrupted (IN_PROGRESS)

```bash
php artisan ark:install-recover --force
```

Then revisit `/setup`. This never clears a completed install.

## Setup says already installed

Correct after success. There is no browser unlock. Operator recovery is out of band (restore from backup / new empty database + new install).

## Dragon / Square / telephony / mail unavailable

Expected when skipped. Configure later in Settings. Core RO/customer/vehicle workflows must not require them.

## Labor guide missing

Expected. Licensed fuel is not redistributed. Keep the import interface; supply your own licensed data separately if needed.
