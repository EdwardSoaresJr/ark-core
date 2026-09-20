# ARK SMS Naming v1

**Status:** Accepted  
**Replaces:** “ARK V2” / `arksmsv2` product vocabulary (legacy OG ARK-SMS is retired)

## Principle

One product name in conversation and docs: **ARK SMS** — same pattern as **ARK-WEB** (`arkweb` repo).

| Layer | Old | New (target) | Notes |
|-------|-----|--------------|-------|
| Product / UX | ARK V2 | **ARK SMS** | Tab title already `ARK-SMS` via `Branding::tabTitle()` |
| OIDC / identity slug | `ark_v2` | **`ark_sms`** | Migration + legacy alias until data migrated |
| GitHub repo | `EdwardSoaresJr/arksmsv2` | **`arksms`** | Phase 2 — rename in GitHub settings, update remotes |
| GHCR image | `ghcr.io/.../arksmsv2` | **`ghcr.io/.../arksms`** | Phase 2 — dual-tag or cutover in Coolify |
| Coolify app | `arksmsv2` | **`arksms`** | Phase 2 — UI rename or recreate app |
| MySQL schema | `arkv2` | **`arksms`** (optional) | Phase 3 — high risk; schema name may stay `arkv2` indefinitely |
| Host paths / env files | `arksmsv2.env` | **`arksms.env`** | Phase 2 — copy-on-host, do not break prod mid-deploy |

**Do not rename** shop-facing domains (`app.demo-auto.test`) — those are topology, not product generation labels.

## Phase 1 (repo — safe)

- [x] `OidcProduct::ArkSms` slug `ark_sms` (+ legacy `ark_v2` normalization)
- [x] Data migration: `user_product_access`, `oidc_clients`
- [x] Docs / rules: “ARK SMS” instead of “ARK V2” where meaning is the product

## Phase 2 (infra — coordinated)

1. Publish GHCR image as `arksms` (keep `arksmsv2` tag alias during transition)
2. GitHub: Settings → rename repository to `arksms`
3. Coolify: update git repo URL + optional app display name
4. Server: `/data/ark-shared/staging/arksms.env` (copy from `arksmsv2.env`)

## Phase 3 (optional)

- MySQL `RENAME DATABASE arkv2 TO arksms` only with maintenance window and full backup
- Redis key prefix audit if any hardcoded `arksmsv2`

## Out of scope

- **arkweb** repo name (already aligned)
- **ARKademy**, **Arkify**, **Portal** — unchanged
- Historical import docs referencing “legacy ARK-SMS” source system
