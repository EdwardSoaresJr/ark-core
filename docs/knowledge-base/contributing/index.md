---
title: Contribute
description: How to set up, test, and propose changes to ARK Core.
---

# Contribute

<!-- source: CONTRIBUTING.md -->

ARK Core is AGPL-3.0-only. Read [License](/contributing/license) and `docs/PRODUCT_BOUNDARY.md` before adding a feature. A screen that only works when ARK Shop is connected must not be described as a stock Core feature.

## Setup

Docker Compose from the repository README, or a host PHP install plus `npm run build`. Development details are on [Local development](/getting-started/local-development).

## Pull requests

One topic. Describe the operational change. Do not present a fork as official ARK. `TRADEMARKS.md` is the naming policy.

This repository does not publish a branch-naming standard beyond that. Use a descriptive branch. Open a pull request against the default branch on [EdwardSoaresJr/ark-core](https://github.com/EdwardSoaresJr/ark-core).

## Tests and style

`composer test:parallel` is the check for behavior changes. Documentation-only changes do not need the full suite. PHP style is Pint. Follow the surrounding code in `app/Ark` rather than introducing a new architectural layer for a single call site.

## Migrations

Add a file under `database/migrations`. Prefer forward changes. Do not edit a migration that has already shipped to a shop if you can add a new one. Never commit `migrate:fresh` output or a shop database dump.

## Documentation

Behavior that operators or integrators can see should update `docs/knowledge-base` in the same pull request. Internal design notes can stay under `docs/`. The rule of thumb is on [Maintaining this knowledge base](/maintenance).

## Bug reports

Use GitHub issues for defects that do not include secrets or customer data. Security reports go through `SECURITY.md`, not a public issue.

## Feature proposals

Describe the shop workflow, who is allowed to do it, and whether it belongs in Core or in ARK Shop. A proposal is not a commitment of support, a release date, or a compatibility guarantee.

## Compatibility

There is no long-term support branch documented in this repository. Clients and packages should pin a commit. API paths are unversioned.

## What not to send

- `.env`, credentials, shop data, licensed labor-guide files
- Provider tokens
- A claim that Twilio, Square, or PartsTech is a self-host integration because the name appears in code
