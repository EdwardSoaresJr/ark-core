---
title: Permissions and roles
description: Staff roles and capability strings shipped with ARK Core.
---

# Permissions and roles

<!-- source: app/Ark/Runtime/Authorization/ArkCapability.php -->
<!-- source: config/ark-permissions.php -->

Roles are `ArkRole`: `admin`, `advisor`, `technician`, `customer`.

Capabilities are `ArkCapability`. The default assignment is `config/ark-permissions.php`.

| Capability | Admin | Advisor | Technician | Customer |
| --- | --- | --- | --- | --- |
| `production.access` | yes | yes | yes | |
| `operations.access` | yes | yes | | |
| `customers.manage` | yes | yes | | |
| `vehicles.manage` | yes | yes | | |
| `repair_orders.view` | yes | yes | yes | |
| `repair_orders.manage` | yes | yes | | |
| `repair_orders.lifecycle` | | | yes | |
| `repair_orders.closeout` | yes | yes | | |
| `repair_orders.destructive` | yes | yes | | |
| `estimates.view` | yes | yes | | |
| `estimates.manage` | yes | yes | | |
| `estimate_documents.manage` | yes | yes | | |
| `pricing.override` | yes | yes | | |
| `procurement.cancel` | yes | yes | | |
| `financial.view` | yes | yes | | |
| `financial.manage` | yes | | | |
| `settings.manage` | yes | | | |
| `staff.manage` | yes | | | |
| `portal.access` | | | | yes |
| `communications.internal.view` | yes | yes | yes | |
| `communications.internal.manage` | yes | yes | | |
| `attention.view` | yes | yes | | |
| `attention.manage` | yes | yes | | |
| `scoreboard.operational.view` | yes | yes | | |
| `scoreboard.financial.view` | yes | yes | | |
| `scoreboard.queues.view` | yes | yes | | |
| `scoreboard.follow_up.work` | yes | yes | | |
| `scoreboard.drilldown.view` | yes | yes | | |

A blank cell means the default map in `config/ark-permissions.php` does not grant it. A shop database can diverge if roles were edited after install. The enum is the list of known capability strings. Middleware checks the string, for example `permission:settings.manage`.

`repair_orders.lifecycle` is on the technician role and not on admin or advisor in that file. Admins and advisors hold `repair_orders.manage` instead. Do not infer that a missing cell is a bug without reading the controllers that check the capability.
