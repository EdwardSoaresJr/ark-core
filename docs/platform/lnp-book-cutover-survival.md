# LNP `/book` cutover survival (Option 1)

**Decision:** Keep the marketing / booking surface **independent** of Public Core.  
Do **not** move Growth/CMS into Core. Do **not** replace `lugsnplugs.com` with Website product yet.

## Topology (binding)

| Host | After Public Core ops cutover | Owns `/book`? |
| --- | --- | --- |
| `lugsnplugs.com` / `www` | **Stay** on booking-capable runtime (today: arksmsv2 public surface) | **Yes** — `PublicBookController` → `POST /leads` → Lead |
| `app.lugsnplugs.com` | Public Core image | No |
| Shadow `lugsnplugs.arksms.com` | Public Core rehearsal | No (404 expected) |

`arkweb` AptBook (`arksms_apt_book_base_url`) must keep pointing at the **booking** origin (`…/book`), never at Core `APP_URL` alone.

## Coolify / Traefik cutover rules

When swapping staff ops to Public Core:

1. Attach **only** ops FQDNs to the Core Coolify application (`app.lugsnplugs.com`, and portal only if portal moves with Core).
2. **Do not** remove `lugsnplugs.com` / `www.lugsnplugs.com` from the booking runtime Coolify app.
3. **Do not** add `lugsnplugs.com` to the Public Core Coolify FQDN list unless `BOOKING_SURFACE_BASE_URL` is set to the booking origin.

## Core hooks (this repo)

| Piece | Role |
| --- | --- |
| `BOOKING_SURFACE_BASE_URL` | External origin that still serves `/book` |
| `BOOKING_SURFACE_ENFORCE` | Production default on — boot fails if protected hosts claimed without base URL |
| `BOOKING_SURFACE_PROTECTED_HOSTS` | Default `lugsnplugs.com,www.lugsnplugs.com` |
| `GET /book` | Redirect-away **only** when base URL is set (no `public.book` route name) |
| `php artisan ark:booking-surface:check` | Pre-cutover verification |

## Proof commands (non-mutating)

```bash
curl -sI https://lugsnplugs.com/ | head -1          # expect 200
curl -sI https://lugsnplugs.com/book | head -1     # expect 200
curl -sI https://lugsnplugs.arksms.com/book | head -1  # expect 404 on Shadow Core
php artisan ark:booking-surface:check
php artisan ark:booking-surface:check --probe      # only when BOOKING_SURFACE_BASE_URL set
```

Lead submission stays on the booking host (`POST https://lugsnplugs.com/leads`). Do not POST against production from scripts.

## Non-goals

- ARK Website product migration
- Growth CMS return into Core
- Hiding booking CTAs
- Mutating LNP production DB / DNS / Twilio for this bridge
