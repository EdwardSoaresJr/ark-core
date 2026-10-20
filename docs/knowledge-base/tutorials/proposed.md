---
title: Proposed, not implemented
description: Extension ideas that need new ARK Core hooks and are not current capabilities.
---

# Proposed, not implemented

<!-- source: app/Ark/Operations/Commands/OperationsCommandRegistry.php -->

The items below are requests people make of a shop platform. They are not tutorials, because the hook is not in the source. Building any of them means changing Core, then documenting the new contract. Until that change lands, do not advertise them as ARK Core features.

| Proposal | What is missing |
| --- | --- |
| Custom shop dashboard widget | No view region or widget registry. `OperationsCommandRegistry` is an internal navigation list populated by one class. |
| A report added from a package | Report catalogs live in application code under `app/Ark/Operations/Reports`. There is no registrar a package can append to. |
| An external-service settings screen contributed by a package | Settings routes and Blade are Core views. A package can add its own route. It cannot inject a card into the existing settings page without editing that view. |
| A theme | No theme loader. Blade and Vite are the UI. |
| An inspection workflow step injected by a package | Checklist templates are data. The Tech and mobile controllers validate a fixed set of fields. |
| A notification handler registry | You can listen for `ConversationMessageReceived` and `ApprovalEvent` being created. You cannot register a named channel that Core will fan out to. |
| Outbound webhooks | No subscription table, signing secret, or dispatcher. |
| A customer mobile API | `/api/mobile` rejects the customer role. |
| Rebinding `PartsCatalogLauncher` and calling that PartsTech | The interface is three methods. The partner catalog is ARK Shop and is not documented in this repository. |

If you implement one of these inside Core, add a page under Extending or Integrations in the same pull request and mark the proposal removed. That policy is [Maintaining this knowledge base](/maintenance).
