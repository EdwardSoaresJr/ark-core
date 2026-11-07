# Contributing

ARK Core is licensed **AGPL-3.0-only**. See `LICENSE` and `NOTICE`.

This repository is the free shop product. It is not ARK Shop and it is not the client apps. Read [docs/PRODUCT_BOUNDARY.md](docs/PRODUCT_BOUNDARY.md) before adding features.

## Setup

Use Docker Compose from the root README. A native PHP install also needs `npm run build` before the shop UI is usable.

## Tests

```bash
composer test:parallel
```

That runs the full suite. During development run what the change touches instead: syntax and lint, the communications boundary check when relevant, and the focused tests (`bash scripts/ci-focused-tests.sh origin/main` lists them). `composer test:serial` is the single-process diagnostic.

CI follows the same split:

- **Every pull request and push to `main`** (`.github/workflows/tests.yml`):
  - `lint`: syntax of changed PHP and JavaScript, Blade compile, advisory Pint;
  - `security`: authentication, authorization, sessions, signed access and isolation, plus payment tests when payments or billing change;
  - `targeted`: the existing repair-order and attention regressions;
  - `focused`: tests that name the changed files, checked against the same known-failure baseline as the nightly run. A failure the baseline does not list, a crash, a stopped run, or a selected test file that reported no test fails it;
  - `communications-boundary`.
- **The full suite** (`.github/workflows/regression.yml`):
  - nightly on `main` at 09:00 UTC (02:00 Mountain in winter, 03:00 during daylight saving time);
  - checked against the committed known-failure baseline, `.github/regression-known-failures.txt` (#147). Any other failing test is a regression, however many nights it has failed, until it is fixed or added to the baseline in a reviewed change with its issue. A run that crashes, times out, leaves no report, or runs far fewer tests than the baseline expects also fails. Remove baseline entries whose tests pass;
  - on demand from the Actions tab;
  - on a pull request labeled `full-regression`. Use it for major or high-risk changes, and for changes the focused job reports as broad (harness, dependencies, migrations, routes, config).

See [tests/README.md](tests/README.md) for database and filesystem isolation rules and the explicitly opt-in MySQL integration suite.

## Do not

- Commit `.env`, credentials, shop data, or licensed labor-guide CSVs. Local environment files and injected environment variables are how secrets are supplied. Git is not.
- Commit Cursor rules, agent transcripts, private planning notes, or machine-local paths
- Paste provider tokens or model-provider secrets into Core
- Advertise a Core feature that only works in ARK Shop
- Treat a Core screen (communications, parts catalogs, payments) as proof that the matching ARK Shop service ships with Core
- Treat Twilio, Square, or PartsTech names as a supported self-host integration
- Put financial totals, tax, or approval rules in browser code

## Pull requests

One topic. Describe the operational change. Do not present a fork as official ARK (see `TRADEMARKS.md`).
