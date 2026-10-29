---
title: Extending ARK Core
description: How to customize ARK Core with the mechanisms that exist, and which parts are not a plugin system.
---

# Extending ARK Core

<!-- source: bootstrap/providers.php -->

ARK Core does not have a plugin manager, a plugin directory, a theme engine, or a versioned extension SDK. It is a Laravel application. The ways to extend it are the ways you extend Laravel, aimed at the contracts and events this repository actually registers.

That is enough to add a command, listen for a message, bind a different PDF renderer, or ship a Composer package. It is not enough to promise that a package written today will load unchanged after an arbitrary Core upgrade. Read [stability](#stability) before you build a product on a hook.

## Stability

| Tier | Examples | Expectation |
| --- | --- | --- |
| Behavior you can call | HTTP routes in `routes/api.php`, Artisan commands registered as classes | They exist and have tests around the product. They can change in a Core pull. There is no separate semver channel for them in this repository. |
| Contracts the container binds | `TelephonyProvider`, `PartsCatalogLauncher`, `PdfRenderer`, `PushTransport`, `OutboundSmsTransport` | A binding can replace the stock class. The interface methods are the whole contract. They are small on purpose. |
| Laravel mechanisms | Service providers, events, migrations, Blade, middleware | Supported because Core is Laravel. Core does not wrap them in a compatibility layer. |
| Internal UI | Blade partials, the operations command palette, web routes | Editing these is a fork of those files. The next update will conflict. |

Public supported behavior and internal implementation are different things. If this knowledge base does not name a hook, do not invent one in a product brochure.

## A package versus a fork

| Approach | When it fits |
| --- | --- |
| Composer package, discovered service provider | You add behavior beside Core: a listener, a command, a new route file, a rebound interface. |
| Edit files inside `app/Ark` | You are changing Core itself and you will maintain that diff. |
| Fork the repository | You need to ship a modified Core. AGPL obligations apply. Do not present the fork as official ARK. |

The practical package shape is [Composer packages](/extending/packages). The mechanism catalog is [Extension mechanisms](/extending/mechanisms). Gaps that block a real plugin ecosystem are [listed separately](/extending/gaps) so they are not mistaken for features.

## What not to extend by rewriting

- Server-side totals, tax, and authorization
- Ledger entry creation
- Portal token storage
- Installer completion flags

Add a listener after those writes. Do not copy the arithmetic into a Blade view or a mobile client.
