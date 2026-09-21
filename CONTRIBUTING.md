# Contributing

ARK Core is licensed **AGPL-3.0-only**. See `LICENSE` and `NOTICE`.

This repository is shop Core, not ARK Platform and not the client apps. Read [docs/PRODUCT_BOUNDARY.md](docs/PRODUCT_BOUNDARY.md) before adding features.

## Setup

Use Docker Compose from the root README. Native PHP also needs `npm run build`.

## Tests

```bash
composer test:parallel
```

That is the default check for behavior changes. Documentation-only changes do not require the full suite.

## Do not

- Commit `.env`, credentials, shop data, or licensed labor-guide CSVs
- Paste Twilio, Square, Postmark, or model-provider tokens into Core
- Advertise a Core feature that only works with Platform
- Treat leftover Twilio/Square names in code as a supported self-host integration

## Pull requests

One topic. Describe the operational change. Do not present forks as official ARK (see `TRADEMARKS.md`).
