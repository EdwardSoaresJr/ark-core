# Optional Square payments adapter

**Composer:** `ark/payments-square`  
**License (ARK adapter code):** AGPL-3.0-only (same as ARK core — see repository root `LICENSE`)  
**Not part of the default ARK core dependency graph.**

## Install

```bash
composer require ark/payments-square
```

Then configure credentials under **Settings → Payments**.

## What this package is

ARK application code that talks to Square’s PHP SDK for Terminal, keyed, and
portal capture. Without this package, ARK still runs; payments UI explains that
the adapter is missing.

## Third-party dependencies (retain their licenses)

Installing this package pulls Composer dependencies including:

| Package | Upstream license |
| --- | --- |
| `square/square` | MIT |
| `apimatic/jsonmapper` (transitive) | **OSL-3.0** |

**ARK’s AGPL does not relicense those packages.** They remain under their
upstream terms. Default ARK (without this require) stays free of
`apimatic/jsonmapper` / OSL-3.0 in `composer.lock`.

See also: repository root `NOTICE`, `docs/installation/SQUARE_OPTIONAL_ADAPTER.md`.
