---
title: Authentication
description: Sanctum staff tokens and station device tokens for ARK Core APIs.
---

# Authentication

<!-- source: app/Ark/Mobile/Http/MobileAuthLoginController.php -->

## Staff tokens

Mobile, Desk, and Tech each have a login route that checks the email and password, rejects inactive users, rejects the customer role, and checks a product gate. A successful login returns a Sanctum plain-text token once. Store it on the device. Core stores only a hash.

Mobile login requires `device_name`. The token name is that device name, and logout uses it to find the registered device.

```bash
curl -s -X POST https://shop.example.test/api/mobile/auth/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"advisor@example.test","password":"not-a-real-password","device_name":"advisor-phone"}'
```

A successful body includes:

```json
{
  "token": "example-token-value-not-real",
  "token_type": "Bearer"
}
```

Additional keys come from `MobileUserPresenter::presentShell()`. Do not assume a fixed list beyond `token` and `token_type` without reading that presenter. Desk adds `"product": "ark_desk"`. Tech adds `"product": "ark_tech"`.

Call protected routes with:

```bash
curl -s https://shop.example.test/api/mobile/me \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer example-token-value-not-real'
```

Logout:

```bash
curl -s -X POST https://shop.example.test/api/mobile/auth/logout \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer example-token-value-not-real'
```

The response is `{"message":"Signed out."}` and the current token row is deleted.

Wrong passwords produce Laravel's validation response, HTTP 422, with an `errors.email` message. Disabled accounts and role mismatches use the same shape so a client cannot distinguish "no such user" from "wrong password" on the mobile login. The message text does differ for disabled accounts and for role gates. Do not branch product logic on that English sentence. Branch on HTTP status.

Sanctum `expiration` in `config/sanctum.php` is `null`. Tokens do not time out on their own. Revoke them with logout or by deleting the token row.

## Who may log in

| Surface | Login rule in source |
| --- | --- |
| Mobile | Active staff. `MobileStaffAccess::canUseMobile()` allows admin, advisor, and technician. Customer role is rejected. |
| Desk | Active admin or advisor only. |
| Tech | Active user who passes `TechStaffGate`. The failure message states Tech is for technicians. |

Technicians who use mobile receive a reduced shell. The repair-order projection omits estimate money for that profile.

## Station tokens

Station tokens are not staff passwords. Issue one on the server:

```bash
php artisan station:token-issue front-counter-glass
```

The command prints the plaintext once. Send it as `Authorization: Bearer` to `/api/station/*` only. There is no station login route. Revoke with `php artisan station:token-revoke {id}`, where `{id}` is the `station_device_tokens` id printed when the token was issued.

A station token that belongs to a different `shop.identity` than the running install is rejected with 401.

## Authorization after login

Authentication is not permission. Mobile evidence upload checks `repair_orders.manage`. Tech finding updates check `TechStaffGate::canRecordFinding()`. A 403 body is an authorization failure. Do not retry it with the same token.

There are no OAuth scopes on these tokens. The token is the user. The user's roles and capabilities are the scope.
