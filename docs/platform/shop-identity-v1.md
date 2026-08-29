# ARK Platform Doctrine — Shop Identity vs Voice Transport

**Status:** Canonical  
**Scope:** Arkify · Communications · Provisioning · Deployment

## Foundational principle

A **Shop** is the deployment authority.

Each shop receives its own VPS, Coolify instance, database, Asterisk runtime, and domain(s).

ARK scales by provisioning **autonomous shops**, not by sharing operational infrastructure.

## Public identity

Every customer-facing capability belongs to the shop.

```
https://shop1.arksms.com
├── /app
├── /portal
├── /voice
├── /payments
├── /api
└── ...
```

Later, `shop1.com` or `app.shop1.com` may CNAME to the same deployment. The public product model never changes.

There is no `voice.shop1.arksms.com`. There is no shared `voice.arksms.com` SIP edge — each shop has its own Asterisk on its own VPS.

## Voice has two identities

HTTP identity and SIP identity are **related but not the same endpoint**.

### HTTP identity (product)

Everything HTTP belongs beneath the shop. Set via `SHOP_BASE_URL`.

| Capability | Path |
|------------|------|
| Call events | `/voice/call-events` |
| Device registration webhook | `/voice/device-registration` |
| Microbrowser | `/voice/device-screen/{token}` |
| Health | `/voice/health` |

These are product capabilities. Operators never type URLs.

### SIP identity (transport)

SIP is transport. Phones register to a **SIP registrar hostname** defined by deployment configuration — not by the product model.

Examples (all valid, all deployment-specific):

- `voice.demo-auto.test` (production voice cutover today)
- `app.demo-auto.test:5060` (same hostname as shop, different protocol)
- `shop1.arksms.com:5060` (future fleet default)

Operators never see the registrar. Customers never configure it. ARK never presents it in operator UI.

**HTTP is part of the product. SIP is part of the infrastructure. Provisioning is the translator between them.**

## Architectural rule

Never force the product model to mirror transport requirements.

| Layer | Owns |
|-------|------|
| Shop URL | Product capabilities (HTTP) |
| Deployment transport config | SIP registrar, port, outbound proxy, RTP policy |
| Provisioning | Translates shop identity → device config (HTTP URLs + transport values) |

Do **not** derive SIP registrar from `SHOP_BASE_URL`. Read it from deployment transport configuration (`VOICE_SIP_REGISTRAR`).

## Provisioning doctrine

```
Operator → Adds Device
ARK → Generates Provisioning
Provisioning → Reads deployment transport configuration
Device → Learns SIP registrar, credentials, codecs, microbrowser URL
```

The application never asks the operator for transport information.

Generated Poly config contains HTTP URLs from `SHOP_BASE_URL` and SIP values from transport configuration.

## Infrastructure (deployment only)

| Variable | Layer | Purpose |
|----------|-------|---------|
| `SHOP_BASE_URL` | Application | HTTP capability URLs |
| `VOICE_SIP_REGISTRAR` | Deployment | SIP registrar hostname in provisioning |
| `VOICE_SIP_PORT` | Deployment | SIP port (default 5060) |
| `ASTERISK_PROVISIONING_HOST` | Deployment | Poly SIP server in generated configs — locked to `VOICE_SIP_REGISTRAR` per shop |
| `VOICE_HOSTNAME` | Coolify/Traefik | Asterisk stack Traefik/SIP bind — not product model |

## Preferred fleet pattern (one shop, one VPS)

When DNS allows, the simplest deployment uses **one hostname, two protocols**:

| Protocol | Example |
|----------|---------|
| HTTP | `https://shop1.arksms.com/voice/...` |
| SIP | `shop1.arksms.com:5060` |

No extra subdomains. No global SIP proxy. Same VPS, same certificate strategy for HTTP; SIP on port 5060.

production voice cutover may temporarily use a separate `VOICE_SIP_REGISTRAR` until SIP is routed through the shop hostname.

## White label

White labeling changes HTTP identity only (`shop1.com/voice`). Transport configuration updates independently if DNS requires it.

## Identity drift test

**Reject:**

- Exposing SIP registrar in operator UI
- `COMMUNICATIONS_VOICE_RUNTIME_HOST` or app-level voice hostnames
- Deriving SIP registrar from `SHOP_BASE_URL`
- Shared multi-tenant Asterisk or global `voice.arksms.com` without per-shop routing
- Assuming HTTP routing and SIP routing are identical

**Accept:**

```
Shop URL → Product capabilities (HTTP)
Provisioning → Transport configuration → Device config (SIP)
```

## Scale philosophy

Do not optimize for hyperscale cloud architecture. Optimize for **autonomous shop deployments**.
