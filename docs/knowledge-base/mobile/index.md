---
title: Building a mobile client
description: What an ARK Core mobile client can authenticate as, and which shop workflows the API covers today.
---

# Building a mobile client

<!-- source: app/Ark/Mobile/MobileStaffAccess.php -->
<!-- source: app/Ark/Operations/RepairOrders/RepairOrderEstimateConflictException.php -->

A mobile client is a separate application that calls `/api/mobile` and, for technician inspection capture, `/api/tech`. Core does not ship the iOS or Android app. Companion, Desk, and Tech clients live elsewhere. You can build your own client against the routes that exist. You cannot assume a future "full shop" mobile contract that is not in `routes/api.php`.

## Session model

There is no mobile session cookie to persist. Login returns a bearer token. Send it on every request. Logout deletes that token. The token does not expire by time.

Register the device with `POST /api/mobile/device` after login if you want Core to know the device. Push delivery still depends on the Firebase transport being configured on the server. A stock install binds `PushTransport` to Firebase and does nothing useful until that configuration exists. Do not promise push in a client that cannot handle a silent failure.

## Authorization

The token is a staff user. Admin and advisor tokens see the broader mobile shell. Technician tokens are allowed onto mobile and are restricted: estimate money is omitted from the repair-order projection, and some writes require capabilities the technician role does not have. `repair_orders.manage` is required for evidence upload. The default technician role does not include `repair_orders.manage`. A technician client that needs to upload concern evidence must use the Tech inspection-item photo field or a role that actually has the capability. Do not work around a 403.

Customer accounts cannot log in to mobile.

## Offline

Core does not offer a sync protocol, a change feed, or an ETag convention you can cite as supported. A client may cache JSON locally. The server will not reconcile a queue of offline writes for you. If you queue writes, send them when online, in order, and treat HTTP 409 as an estimate conflict a person must see. `RepairOrderEstimateConflictException` renders JSON status 409.

## What to read next

[Authentication](/integrations/authentication), [HTTP API surfaces](/integrations/http-api), [files](/integrations/files), and [what works today](/mobile/workflows).
