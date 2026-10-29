---
title: HTTP surfaces
description: Route files and API prefixes in ARK Core.
---

# HTTP surfaces

<!-- source: bootstrap/app.php -->

| Path prefix | Route file | Client |
| --- | --- | --- |
| `/` staff UI | `routes/web.php`, `routes/auth.php` | Browser session |
| `/setup` | `routes/install.php` | Browser, until installed |
| `/portal` | `routes/portal.php` | Customer browser |
| `/api/mobile` | `routes/api.php` | Staff bearer token |
| `/api/desk` | `routes/api.php` | Advisor or admin bearer token |
| `/api/tech` | `routes/api.php` | Technician bearer token |
| `/api/station` | `routes/api.php` | Station device token |
| `/api/dragon-agent` | `routes/api.php` | Staff bearer token |
| `/api/voice/lab/utterance` | `routes/api.php` | Throttled lab route, not a shop API |
| `/webhooks/cloud` | `routes/cloud-ingress.php` | Cloud ingress. Internal. |
| `/up` | Framework health | Process up, not dependency health |
| OIDC paths | `routes/oidc.php` | Only when the issuer middleware allows them |

The authoritative mobile, desk, tech, and station route list is `routes/api.php`. This table is the map. It is not a second copy of every method. When they disagree, the route file wins.

Staff web routes are not a public API. See [Building integrations](/integrations/).
