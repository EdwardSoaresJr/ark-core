# Contributing

ARK Core is licensed **AGPL-3.0-only**. See `LICENSE` and `NOTICE`.

This repository is shop Core. It is not ARK Platform and it is not the client apps. Read [docs/PRODUCT_BOUNDARY.md](docs/PRODUCT_BOUNDARY.md) before adding features.

## Setup

Use Docker Compose from the root README. A native PHP install also needs `npm run build` before the shop UI is usable.

## Tests

```bash
composer test:parallel
```

That is the default check for behavior changes. `composer test:serial` is the single-process diagnostic. Documentation-only changes do not require the full suite.

## Do not

- Commit `.env`, credentials, shop data, or licensed labor-guide CSVs
- Paste provider tokens or model-provider secrets into Core
- Advertise a Core feature that only works when ARK Platform is connected
- Treat leftover Twilio or Square names as a supported self-host integration
- Put financial totals, tax, or approval rules in browser code

## Pull requests

One topic. Describe the operational change. Do not present a fork as official ARK (see `TRADEMARKS.md`).
