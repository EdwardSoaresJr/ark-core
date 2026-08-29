# Domain Contract v1

**Status:** Locked — stancl / Shop provisioning prep  
**Date:** 2026-07-19  
**Companions:** [shop-authority-v1.md](shop-authority-v1.md) · [shop-status-authority-v1.md](shop-status-authority-v1.md) · [shop-identity-v1.md](shop-identity-v1.md) (HTTP vs SIP; deployment autonomy) · ark-surfaces.mdc

This contract defines **who each hostname is for**. It does not prescribe Coolify topology or SIP registrars.

## First-class concept: Shop

Outside the Stancl adapter, ARK speaks **Shop**.

| Product language | Infrastructure adapter (Stancl only) |
| --- | --- |
| Shop | Tenant |
| Operations Domain | Tenant primary domain |
| Public Domain | Domain alias (Stancl) |
| Deployment Profile | Cluster / host binding |

Provisioning provisions a Shop. Billing bills a Shop. Communications and operations belong to a Shop. Only the Stancl integration layer thinks in “tenant.”

---

## Audiences (three hosts, three jobs)

```text
I want ARK          →  autorepairkeeper.com          (ARK Cloud — the product)
I manage ARK        →  app.autorepairkeeper.com      (Auth + Cloud dashboard — Phase 2+)
I work here         →  {shop}.arksms.com             (shop workspace)
I'm a customer      →  {custom domain}  (or trial preview)
```

**Phase 1 (now):** Company host *is* the product. Marketing site posture ends — Cloud Funnel at apex.

---

## 1. Company — never tenant-aware

| | |
| --- | --- |
| **Domain** | `autorepairkeeper.com` (+ `www` → apex) |
| **Audience** | Prospective customers + new owners |
| **Owns** | ARK Cloud product — Home · Features · Pricing · Resources · Login · Trial · Become · Arrive · Cloud dashboard (Phase 1) |
| **Stancl** | Central domain — not a Shop |

No Shop routing. No operations. No customer portal.

**Target split (Phase 2+):** trial/marketing stay on company apex; Auth + Cloud dashboard move to `app.autorepairkeeper.com`. Until then, dashboard may live on the company host.

---

## 2. Platform — internal operations

| | |
| --- | --- |
| **Domain** | `platform.autorepairkeeper.com` |
| **Audience** | ARK operators (internal) |
| **Owns** | Provisioning · Deployment · Hosting · Monitoring · Billing · Support tooling |
| **Stancl** | Central domain |

No public login. No Shop staff login. If an internal admin UI is needed later, it is a **route on this host** — not a separate `admin.` product hostname.

---

## 3. Platform Runtime — Operations Domain (Stancl tenant)

| | |
| --- | --- |
| **Domain** | `{shop}.arksms.com` |
| **Audience** | Shop staff |
| **Owns** | Operations · Repair Orders · Communications · Customers · Vehicles · Scheduling · APIs · Mobile · Reverb · Authentication |
| **Stancl** | Primary tenant domain |

Examples: `demo-auto.arksms.com`, `joesauto.arksms.com`.

There is no required `app.` prefix. The Shop slug **is** the operations host.

`SHOP_BASE_URL` / voice HTTP capabilities resolve to this host (SIP registrar remains deployment config — see shop-identity).

---

## 4. Public Shop — Public Domain

| | |
| --- | --- |
| **Domain** | Custom domain (e.g. `demo-auto.test`) |
| **Audience** | Customers |
| **Owns** | Website · Customer Portal · Appointment requests · SEO · Reviews · Public repair pages |
| **Stancl** | **Public Domain** on the same Shop — not another tenant |

Product vocabulary: **Public Domain** (not “alias”). Alias is Stancl’s implementation detail.

Staff traffic and customer traffic are never the same hostname.

---

## 5. Trial public (deliberate compromise)

Trials must not require DNS before evaluation.

| | |
| --- | --- |
| **Preferred** | `{shop}-preview.arksms.com` |
| **Fallback** | `preview.arksms.com/{shop}` (path model — secondary) |
| **Role** | Temporary public surface — clearly distinct from Operations Domain |

Once the shop connects a Public Domain (`joesauto.com`):

- Preview may **301 → Public Domain**, or
- Remain available only to staff for QA

Preview must **not** live under `autorepairkeeper.com` (keeps shop public identity off the company brand).

---

## 6. Learn — central

| | |
| --- | --- |
| **Domain** | `learn.autorepairkeeper.com` |
| **Owns** | One academy · one docs/KB surface |
| **Stancl** | Not tenant-routed until a concrete Shop reason exists |

---

## Shop record (provisioning spine)

Everything hangs off one Shop:

```text
Create Shop
  → Slug                  demo-auto
  → Operations Domain     demo-auto.arksms.com
  → Public Domain         (optional — custom or trial preview)
  → Deployment Profile    Shared | Dedicated | …
  → Provision
```

Conceptual model:

```text
Shop
├── Operations Domain     demo-auto.arksms.com
├── Public Domain         demo-auto.test  (or demo-auto-preview.arksms.com)
└── Deployment Profile    Shared Cluster A
```

---

## Stancl mapping (adapter only)

| Contract term | Stancl |
| --- | --- |
| Company + Platform + Learn | `central_domains` |
| Operations Domain `{shop}.arksms.com` | Tenant primary domain |
| Public Domain + trial preview | Tenant domains (product: Public Domains) |

Central (v1):

- `autorepairkeeper.com`
- `platform.autorepairkeeper.com`
- `learn.autorepairkeeper.com`

Tenant wildcard:

- `*.arksms.com` (operations; trial preview uses `-preview` suffix convention)

---

## Explicit non-goals (v1)

- `admin.autorepairkeeper.com` as a separate product host
- Public website on the Operations Domain
- Public trial/marketing under `autorepairkeeper.com/{shop}`
- Per-Shop Learn routing
- Product code using “tenant” outside the Stancl adapter
- Coupling SIP registrar hostname to this HTTP contract

---

## Migration note (Demo Auto Repair today → v1)

| Today | Domain Contract v1 |
| --- | --- |
| `app.demo-auto.test` | `demo-auto.arksms.com` (Operations Domain) |
| `demo-auto.test` | Public Domain (unchanged role) |
| `portal.demo-auto.test` | Collapse into Public Domain (redirect period OK) |
| `learn.demo-auto.test` | Move toward `learn.autorepairkeeper.com` (central) |
| `platform.autorepairkeeper.com` | Unchanged (Platform) |

`SurfaceRouting` / `SURFACE_DOMAINS_*` should eventually express Operations Domain + Public Domain per Shop rather than hard-coded `app.` / `portal.` env pairs.

---

## Relation to shop-identity-v1

[shop-identity-v1.md](shop-identity-v1.md) remains authority for:

- Shop as deployment unit
- `SHOP_BASE_URL` for HTTP voice capabilities
- SIP registrar ≠ product hostname

Domain Contract v1 **narrows** the public product model: portal and customer website belong on the **Public Domain**, not under the Operations Domain path tree. Operations Domain owns staff + APIs + Reverb + auth.
