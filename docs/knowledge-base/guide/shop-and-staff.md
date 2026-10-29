---
title: Shop setup and staff
description: Shop settings, staff roles, and workstations in ARK Core.
---

# Shop setup and staff

<!-- source: app/Ark/Runtime/Authorization/ArkRole.php -->

After `/setup`, shop facts live in shop settings, not in `.env`. Settings routes that change the shop require the `settings.manage` capability. Staff management routes also require `staff.manage`.

## Roles

Core ships four roles:

| Role | Who it is |
| --- | --- |
| `admin` | Shop administrator. Settings and staff, plus the operational capabilities. |
| `advisor` | Front-counter staff. Repair orders, estimates, financial view, communications. No `settings.manage` or `staff.manage` in the default map. |
| `technician` | Production staff. Repair-order view and lifecycle, production access, internal communications view. |
| `customer` | Portal capability only. Not a staff login. |

The default capability list for each role is `config/ark-permissions.php`. Changing that file changes the map shipped in source. A running shop stores role assignments in the database. Editing the config file does not rewrite assignments that were already saved unless an installer or command syncs them.

Assignable staff roles in the product enum are admin, advisor, and technician. Customer is not a role you assign from the staff screen.

## What the capabilities gate

Examples that matter day to day:

- `repair_orders.manage` is required to attach evidence through the mobile API.
- `repair_orders.lifecycle` is how technicians move work without the full advisor set.
- `financial.manage` is separate from `financial.view`. Advisors can view. The default advisor map does not include `financial.manage`.
- `pricing.override` is its own capability. Advisors and admins have it by default. Technicians do not.

The full list is [Permissions and roles](/reference/permissions).

## Workstations

A workstation is a place in the shop: a desk, a bay, a front-counter display. Station API tokens are issued per device with `php artisan station:token-issue`. That token is not a staff password. It authenticates `/api/station/*` only. Staff personal access tokens cannot call that surface.

Inactive staff fail the API staff middleware and the web `EnsureUserIsActive` middleware. A disabled account cannot sign in.

## Limitations

- There is no directory of arbitrary custom roles in the `ArkRole` enum. Extra roles would be a code change, not a settings toggle this guide can promise.
- Dev-only role pretend middleware exists for local development. It is not a production feature to enable on a shop.
