# ARK Website Admin — Owner Mental Model

**Status:** Canonical product direction  
**Scope:** Admin Platform — how owners manage and read the customer-facing website  
**Companion:** [ark-website-doctrine-v1.md](ark-website-doctrine-v1.md) (customer application) · [docs/growth/DOCTRINE.md](../growth/DOCTRINE.md) (attribution)

## The sentence

**Owners manage their website. Growth explains why it performs and what to improve next.**

This is a **UX product**, not a fourth application. The customer application stays one shell (`x-customer.shell`). Authority stays split internally. Presentation is unified externally.

---

## Admin Platform navigation (target)

```
Admin Platform
├── Website      ← owner mental home for customer-facing web
├── Growth       ← attribution, measurement, optimization
├── Voice        ← communications transport + stations
└── Settings     ← shop operations configuration (non-website)
```

Website is **not** Growth. Website is **not** buried in Shop Settings. Website is where an owner thinks: *I need to work on my website.*

---

## Two questions, two surfaces

| Owner question | Product | Tab | Authority |
| --- | --- | --- | --- |
| *What do customers see?* | **Website → Manage** | Presentation + publishing | `PublicSurfaceSettings`, content registry (edit paths), common problems, media |
| *How is my website doing?* | **Website → Performance** | Summary dashboard | **Projections only** — deep-link to Growth for detail |

### Website asks

> **How is my website doing?**

### Growth asks

> **Why is it doing that, and what should we improve?**

**Website never explains why.** Website tells the owner what they can manage and how the website is performing. Growth explains why performance changed and what should be improved.

**Do not duplicate Growth inside Website.** Every Performance card deep-links into the relevant Growth surface (sessions, opportunities, revenue explorer, content registry, audit).

---

## Website → Manage

Everything about what customers see on demo-auto.test (and authenticated customer routes on the same host).

| Area | Today (interim) | Target |
| --- | --- | --- |
| Homepage | Settings → Shop → Public Website | Website → Manage → Homepage |
| Hero / headline | Public surface settings | Same |
| Trust strip | Derived from shop + public surface copy | Editable labels or read-only derived — TBD on floor |
| Photos | Public surface uploads | Website → Manage → Photos (or Media) |
| Reviews / Google proof | Public surface settings | Website → Manage → Reviews |
| Common Problems | `config/common_problems.php` + Growth content | Website → Manage → Common Problems |
| Service / landing pages | Growth content registry | Website → Content / Pages |
| Navigation / footer | Customer shell partials + settings | Website → Manage → Navigation / Footer |
| Contact (phone, hours) | Shop settings + comms hours | Website → Manage → Contact (hours sync from comms — link, do not duplicate) |
| SEO defaults | Growth `SeoEngine` / content registry | Website → Manage → SEO defaults |

**Implementation rule:** Manage **routes to or embeds** existing settings forms initially. Move UI; do not fork authority stores on day one.

---

## Website → Performance

Read-first owner pulse. **No new measurement authority.**

Example cards (this week):

| Card | Source projection | Deep link |
| --- | --- | --- |
| Leads this week | `PublicLeadFunnelSummary` + Growth sessions | Growth → sessions / leads |
| Top landing page | Growth touchpoints / content registry | Growth → content / sessions |
| Top-performing service page | Content registry + attribution | Growth → revenue explorer |
| Form conversion | Funnel step rates | Growth → sessions |
| Organic search trend | Search Console ingest (when live) | Growth → opportunities |
| Top opportunity | Opportunity queue head | Growth → opportunities → build |

Footer on Performance: **Open Growth →** (primary escape hatch to optimization work).

---

## Website sub-nav (target)

```
Website
├── Manage        — presentation (homepage, photos, trust, contact)
├── Performance   — summarized pulse (links to Growth)
├── Content       — pages registry (may deep-link Growth content UI initially)
├── Media         — shop photos, future assets
└── Pages         — common problems + service pages index
```

Phase 1 may ship **Manage + Performance** only; Content/Media/Pages can alias Growth content surfaces until consolidated.

---

## Authority split (non-negotiable)

| Layer | Owns |
| --- | --- |
| **Website** | Presentation, publishing, owner-facing "what customers see" |
| **Growth** | Sessions, touchpoints, attribution, SEO intelligence, opportunity queue, optimization |
| **Settings** | Shop operations (financial, workflow, comms, staff) — not the owner's website mental home |

**Website owns presentation and publishing.**  
**Growth owns attribution, measurement, and optimization.**

Never move Growth into Website. Never move website presentation into Growth. **Deep-link between them.**

---

## Anti-pattern: CMS clone

Website must **not** become WordPress.

Before adding any Manage field, ask:

> **Does this help the shop present itself better or convert more visitors?**

If no → reject.

Forbidden drift:

- Generic page builder, widget library, theme marketplace
- Blog / news CMS without operational evidence
- Per-page arbitrary HTML without content registry discipline
- Duplicated analytics dashboards that compete with Growth

---

## Engineering mapping (today → target)

| Capability | Code today | Website home (target) |
| --- | --- | --- |
| Homepage settings | `PublicSurfaceSettings` · `/app/settings/shop?section=public-surface` | `/app/website/manage` |
| Funnel metrics | `PublicLeadFunnelSummary` (CLI) | `/app/website/performance` |
| Session detail | `/app/growth/sessions` | Linked from Performance |
| SEO opportunities | `/app/growth/opportunities` | Linked from Performance |
| Content / SEO | `/app/growth/content` | Website → Content (link or embed) |

Routes may live under `/app/website/*` as a **presentation layer** over existing controllers and projections.

---

## Phased delivery

### Phase 1 — Mental model fix (MVP)

- Add **Website** to admin rail (alongside Growth, Voice, Settings)
- **Manage:** re-home Public Website settings (redirect or embed existing form)
- **Performance:** read-only cards from existing summaries + deep links to Growth
- Shop Settings → Public Website tab redirects to Website → Manage with notice

### Phase 2 — Content home

- Common Problems index in Website → Pages
- Content/Media aliases into Growth content registry with Website chrome

### Phase 3 — Measurement polish

- Search Console / GBP trend cards when ingest is live
- Weekly owner pulse email optional (future)

---

## PR gate

Website PRs must state:

1. Which owner sentence they serve (*manage* vs *how is it doing*)
2. Which authority store is touched (if any)
3. Which Growth surface receives the deep link (Performance only)
4. CMS litmus: *present better or convert more?*

---

## Companions

| Document | Relationship |
| --- | --- |
| [ark-website-doctrine-v1.md](ark-website-doctrine-v1.md) | Customer application — one shell, two auth states |
| [ark-authority-vs-configuration.mdc](../../.cursor/rules/ark-authority-vs-configuration.mdc) | Settings vs authority |
| [ark-projection-rule.mdc](../../.cursor/rules/ark-projection-rule.mdc) | Performance = projections |
| [docs/growth/DOCTRINE.md](../growth/DOCTRINE.md) | Growth owns optimization |
