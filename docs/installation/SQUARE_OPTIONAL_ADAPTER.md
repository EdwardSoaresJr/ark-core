# Optional Square payments (self-host)

Default ARK **does not** depend on Square’s PHP SDK.

That keeps the core Composer dependency graph free of `apimatic/jsonmapper` (OSL-3.0), which Square pulls transitively.

## Enable Square

On the server (or in your deploy image build):

```bash
composer require ark/payments-square
```

Then configure credentials under **Settings → Payments** (or env fallbacks documented there).

## What you get

| Without adapter | With `ark/payments-square` |
| --- | --- |
| Core install / RO workflow / Fake or missing-adapter guards | Live Terminal, keyed, portal/email pay via Square |
| No OSL transitive in default `composer.lock` | Installs `square/square` + OSL transitive — **opt-in** |

## Docker / Compose

The stock self-host image runs `composer install` from the root lockfile **without** the adapter. Bake Square into a custom image only if you add the require and regenerate the lock intentionally.

## License note

Default ARK (`composer.lock` without this adapter) does **not** include Square
or `apimatic/jsonmapper` (OSL-3.0).

Installing `ark/payments-square` adds third-party packages under **their**
licenses. ARK’s AGPL (`LICENSE`) does **not** relicense those packages. See
repository root `NOTICE`.
