---
title: Security boundaries
description: Authentication, authorization, logging, and what must stay out of public docs and clients.
---

# Security boundaries

<!-- source: config/auth.php -->

## Staff sessions

The staff UI uses the Laravel session guard. Login and logout are `routes/auth.php`. Guests are sent to the login route, except selected portal paths, which go to portal access.

Passwords are hashed. `BCRYPT_ROUNDS` defaults to 12 in `.env.example`. Disabled users are rejected by middleware.

## API tokens

Mobile, Desk, Tech, and the Dragon agent use Laravel Sanctum personal access tokens. The client sends `Authorization: Bearer`. Sanctum's configured expiration is `null`, so a token does not expire by time unless you set an expiry when creating it. Logout deletes the current token. A stolen token works until it is deleted. Treat device storage accordingly.

Station devices use a different token table and `AuthenticateStationDevice`. A staff Sanctum token is rejected there. A station token is rejected on the staff APIs.

Customer portal tokens are hashed at rest. Do not log them.

## Authorization

Capabilities are checked with Spatie's `permission` middleware and with `$user->can(...)` in controllers. A route that forgets the check is a bug, not a feature. API controllers still call `abort_unless` on the capability or a domain gate such as `MobileStaffAccess` and `TechStaffGate`.

OIDC routes exist behind `EnsureOidcIssuerEnabled`. An issuer that is not enabled is not a public identity provider you can assume is on.

## CSRF and webhooks

State-changing staff forms require CSRF. Exceptions in `bootstrap/app.php` are `webhooks/*` and `oauth/token`. Do not add shop mutations to those prefixes.

## Rate limiting

Core does not call Laravel's `throttleApi()` in `bootstrap/app.php`. The framework's API middleware group therefore does not attach an application-wide API limiter unless you add one. These limits do exist:

| Limiter | Where |
| --- | --- |
| `portal-token-read` | 60 per minute per IP |
| `portal-token-write` | 20 per minute per IP |
| `throttle:120,1` | Staff voice routes in `routes/web.php` |
| `throttle:20,1` | `POST /api/voice/lab/utterance` |

If you expose the staff API on the public internet, put a gateway limit in front of it. That is an operator control. It is not implemented as a product setting.

## Logging

The default log channel is the Laravel stack. Do not log passwords, bearer tokens, portal tokens, or `APP_KEY`. Station authentication logs a token audit label on failure and success, not the raw bearer string, in `AuthenticateStationDevice`.

`ERROR_REPORT_EMAIL` receives application exception reports when error reporting is enabled. That mailbox should be the operator's, not a customer address copied from a shop record.

## Public documentation rule

Examples in this knowledge base use fictional addresses such as `advisor@example.test`. They are not shop data. Internal engineering notes under `docs/` are not this site. Do not copy production hostnames, customer rows, or provider secrets into either place.

Report vulnerabilities the way `SECURITY.md` describes: GitHub Security Advisories on `EdwardSoaresJr/ark-core`, privately, without live customer data.
