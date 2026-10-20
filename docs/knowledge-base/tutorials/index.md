---
title: Working examples
description: Tutorials that match ARK Core as it exists, and a separate list of ideas that do not.
---

# Working examples

<!-- source: app/Providers/AppServiceProvider.php -->

These tutorials use mechanisms that are already in the repository. Each one says where the code lives. If an idea needs a hook Core does not have, it is on [Proposed, not implemented](/tutorials/proposed) and it is not written as a working guide.

| Tutorial | What it uses |
| --- | --- |
| [Listen for a domain event](/tutorials/listen-for-a-domain-event) | `ConversationMessageReceived` |
| [Add an Artisan command](/tutorials/add-an-artisan-command) | `app/Console/Commands` |
| [A Composer package](/tutorials/composer-package) | Laravel package discovery |
| [A read-only client](/tutorials/read-only-client) | `GET /api/mobile/repair-orders/{repairOrder}` |

Examples use fictional shop data. They do not call a live server.
