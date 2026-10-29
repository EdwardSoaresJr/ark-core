---
title: A read-only client
description: Read one repair order from ARK Core with a staff token.
---

# A read-only client

<!-- source: app/Ark/Mobile/Http/MobileRepairOrderShowController.php -->

A read-only integration signs in once, stores a bearer token, and calls GET routes. It does not post payments, lines, or messages. That keeps the blast radius small while you learn the payload.

## 1. Create a staff user

Use `/setup` or Settings → Staff on a shop you control. Use a fictional mailbox on a development shop, such as `readonly@example.test`. Do not use a customer password from a live shop. Give the user the advisor or admin role if you need to see estimate money. A technician token will return `"estimate": null`.

## 2. Log in

```bash
curl -s -X POST https://shop.example.test/api/mobile/auth/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"readonly@example.test","password":"not-a-real-password","device_name":"read-only-script"}'
```

Keep the `token` value in a secret store. Do not commit it.

## 3. Read a repair order

`1001` stands in for an integer `repair_order_id` that exists on your shop.

```bash
curl -s https://shop.example.test/api/mobile/repair-orders/1001 \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer example-token-value-not-real'
```

The body is `repair_order` and `workspace`. Read `repair_order.status` and `repair_order.customer.name`. Ignore keys you do not recognize. They can appear or disappear.

## 4. Stop there

A read-only client does not:

- PATCH status or payments
- Upload files
- Send conversation messages
- Call `/api/station` with this token

When you are ready to write, use the [capability matrix](/integrations/capability-matrix) and handle 403, 409, and 422 as on the [mobile workflow page](/mobile/workflows).

## Revoke

```bash
curl -s -X POST https://shop.example.test/api/mobile/auth/logout \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer example-token-value-not-real'
```
