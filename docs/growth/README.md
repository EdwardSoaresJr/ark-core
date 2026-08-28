# ARK Growth

ARK Growth is the acquisition and revenue attribution layer. It measures how the public web becomes completed repair orders — not page views.

**Doctrine:** [DOCTRINE.md](DOCTRINE.md)

## Phase 1 + 1.25 (foundation)

| Surface | Route | Purpose |
| --- | --- | --- |
| Dashboard | `/app/growth` | Health cards + **26×16 revenue heatmap** |
| Revenue Explorer | `/app/growth/revenue-explorer` | Owner decision queries tied to closed work |
| Content registry | `/app/growth/content` | Public page authority |
| SEO audit | `/app/growth/audit` | Explainable on-page findings |
| Redirects | `/app/growth/redirects` | Database-backed 301/302/410 rules |
| Landing sessions | `/app/growth/sessions` | Debug viewer — session → touchpoint → lead → RO |

## Phase 2A — Operational Journey (projection-first)

**North star:** *How did this repair order come into existence?*

Build order: **projection before UI**. The UI never becomes authority.

```
GrowthSession → Touchpoints → GrowthEvents
    ↓ Operations events (comms, approvals, appointments, posted)
    ↓ Voice events (CallSession)
OperationalJourneyProjection
    ↓ RO workspace card · Customer Hub · Journey Explorer
```

| Surface | Route / location | Purpose |
| --- | --- | --- |
| Operational Journey card | RO review/builder rail, Customer Hub | Story milestones — not an event log |
| Journey Comparison | Embedded in journey card | Average shop path vs this customer |
| Journey Explorer | `/app/growth/journey-explorer` | Internal query surface (Revenue Explorer sibling) |

**Identity confidence** on `growth_sessions`: `score`, `reason`, `evidence` JSON — always answers *why* ARK linked this session to this customer.

**Journey Evidence:** each milestone is expandable — story is the summary; immutable source rows are one click away. Platform doctrine: [Truth Stack](../ecosystem/ark-truth-stack-v1.md) · [Explainability](../../.cursor/rules/ark-explainability-doctrine.mdc).

## Phase 2 — Public Surface Intelligence

- Every public **GET** records a server-side `page_viewed` touchpoint (non-blocking middleware).
- Client surface events bridge through `PublicSurfaceActivityRecorded` (fail-safe dispatch).
- Content registry binds to touchpoints via path; first content is immutable on the session.
- Last touch updates on every activity; first touch never overwritten.

## Attribution spine

```
GrowthSession (first touch immutable)
    ↓ touchpoints
    ↓ last touch (separate row)
    ↓ Lead · Conversation · Repair Order
    ↓ RepairOrderClosedForGrowth
    ↓ GrowthAttribution + revenue on content registry
```

## Architecture

- **Growth owns:** sessions, touchpoints, events, content registry, attribution, SEO/sitemap/redirects.
- **Operations owns:** customers, repair orders, leads, public surface events.
- **Coupling (contracts only):**
  - `PublicSurfaceActivityRecorded` — every public surface event
  - `LeadConvertedForGrowth` — session → repair order link
  - `RepairOrderClosedForGrowth` — paid post closes revenue loop

## Commands

```bash
php artisan migrate
php artisan growth:sync-public-content
```

## Configuration

Shop behavior for Growth integrations (public sitemap, Google Business Profile) lives in **Settings → Growth → Integrations** (`shop_settings.growth_integrations`), not `.env`.

- `GROWTH_ENABLED` — master switch (default true)
- `GROWTH_REDIRECTS_ENABLED` — apply redirect middleware (default true)
- `GROWTH_EVENT_COLLECTION` — accept public event POSTs (default true)

## Next

1. **Phase 2:** Customer Growth Timeline (Customer Hub / RO context)
2. Public Blade → `SeoEngine` cutover
3. Google Search Console / GA4 sync when credentials exist
