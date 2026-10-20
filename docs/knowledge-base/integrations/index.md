---
title: Building integrations
description: How third-party software can talk to ARK Core, and which routes are not a public API.
---

# Building integrations

<!-- source: routes/api.php -->

Third-party software talks to a running Core shop over HTTP. The surfaces that exist are the route groups in `routes/api.php`, plus the staff web UI and the customer portal. Only the API groups are reasonable machine clients. Staff Blade routes are session pages. They change with the UI and they expect CSRF cookies. Do not scrape them and call that an API.

Laravel prefixes `routes/api.php` with `/api`.

| Prefix | Who it is for | Auth |
| --- | --- | --- |
| `/api/mobile` | Staff mobile clients. The broadest shop workflow. | Sanctum bearer, active staff |
| `/api/desk` | Advisor and admin desk clients | Sanctum bearer, advisor or admin at login |
| `/api/tech` | Technician clients | Sanctum bearer, tech gate |
| `/api/station` | A front-counter device, not a person | Station device token |
| `/api/dragon-agent` | Dragon agent chat for a signed-in staff token | Sanctum bearer |

The apps themselves are not in this repository. Core is the server.

## What is not an integration API

- `routes/web.php` staff pages
- `/setup`
- Customer portal pages, except as a browser flow you do not automate with stolen tokens
- `/webhooks/cloud/*`, which is ingress for ARK cloud surfaces
- OIDC, unless that issuer is enabled on the install you operate
- Any PartsTech, Twilio, or Square partner protocol. Those are not published here.

## Stability

These routes are implemented and used by first-party clients that live in other repositories. They do not carry `/v1`. A response key can change in a Core release. Pin the Core commit you tested. The [capability matrix](/integrations/capability-matrix) separates routes that are usable from workflows that are still missing.

Read [Authentication](/integrations/authentication) before storing a token, then [HTTP API surfaces](/integrations/http-api) for the route map.
