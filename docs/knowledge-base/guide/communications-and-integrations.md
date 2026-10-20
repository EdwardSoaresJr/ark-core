---
title: Communications and connected services
description: Which communication and catalog screens exist in Core, and which services do not.
---

# Communications and connected services

<!-- source: app/Providers/AppServiceProvider.php -->

## The rule

A screen is not the service. Core includes communications, conversation, call, parts-catalog, and payment screens so the shop workflow has a place to live. On a stock install those services are not connected. Core is written to say so and keep the rest of the shop running.

| You see this in Core | Stock install actually does |
| --- | --- |
| Conversation workspace | Stores and shows conversations the shop already has. Sending customer SMS uses `OutboundSmsTransport`, bound to the platform transport. That binding is not a self-hosted SMS gateway. |
| Phone and call screens | `TelephonyProvider` is bound to `NotConfiguredTelephonyProvider`. Hosted phone service is ARK Shop. |
| Parts catalog button | `PartsCatalogLauncher` is the not-configured launcher unless `services.parts_catalog.driver` is `platform`. PartsTech shop catalog access is ARK Shop. This repository does not publish the partner API. |
| AllData and ProDemand buttons | Open the vendor site. The shop signs in there. URLs default in `.env.example`. |
| Labor-guide import | The import interface and `php artisan` RTE commands exist. The licensed dataset does not. |
| Dragon | `DRAGON_PROVIDER=none` until you configure a provider you run. |
| Square or card settings | Not a self-hosted card product. Record the payment on the ledger after the shop has taken it. |

## Staff communications permissions

`communications.internal.view` and `communications.internal.manage` gate internal communications. Technicians receive view, not manage, in the default map.

## Webhooks that exist

`routes/cloud-ingress.php` accepts cloud fabric and website-management callbacks under `/webhooks/cloud/...`. Those routes are ingress for ARK-operated cloud surfaces. They are not a general outbound webhook product you can subscribe to. CSRF is disabled for `webhooks/*` because those callers do not have a browser session. That exception is not an invitation to add unauthenticated shop mutations.

There is no shop-configurable webhook registry in Core.

## What you can connect yourself

- SMTP, or another Laravel mailer, for mail your server sends
- A Chromium binary for PDFs
- QZ Tray certificates you generate
- NHTSA VIN decode, which needs no key
- A Dragon provider you operate, if you set `DRAGON_PROVIDER` to an implemented value in your build

Replacing `TelephonyProvider` or `OutboundSmsTransport` in code is an application extension you would maintain. It is not a documented turnkey integration, and it is not ARK Shop. The [extension guide](/extending/mechanisms) explains the bindings and the update risk.
