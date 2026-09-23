# Core ↔ Website boundary inventory (2026-09-05)

**Baseline:** SEALED PASS at `7c24ce2`. Companion: [ark-core-website-boundary.md](./ark-core-website-boundary.md).

Classification for public Core extraction.

## KEEP

| Item | Why |
| --- | --- |
| `ShopSettings` identity (name, address, phone, email, `scheduling_hours`, `shop_timezone`, logo, `website`) | Operational shop truth; documents/portal |
| `google_reviews_url` + messaging settings | Post-repair review **destination** - not SEO |
| Customer portal (`routes/portal.php`, portal views, customer shell) | Authenticated customer application |
| `Lead` / `LeadRecorder` / `LeadSource::Website` | Operational lead authority |
| Advisor intake website-lead store | Staff captures website-sourced leads |
| Website-lead interrupts + confirmation mail/SMS | Ops continuity after lead exists |
| `LeadIngressContext` / `LeadIngressHygiene` | Spam observation on lead authority |
| `PUBLIC_DOMAIN` / `surfaces.public` | Host routing seam only |
| Appointment availability settings | When shop can take requests |
| Platform SaaS funnel views (`resources/views/cloud/**`) | ARK Platform product - not shop Website CMS |
| `BOOKING_SURFACE_BASE_URL` + `/book` redirect-away (unnamed) | Cutover safety: keep marketing `/book` off Core; see [lnp-book-cutover-survival.md](./lnp-book-cutover-survival.md) |

## REMOVE (done or enforced)

| Item | Action |
| --- | --- |
| Growth / Website product apps & routes | Already absent from public Core |
| Marketing `public.home` / `public.book` / SEO pages | Absent; boundary tests assert |
| `public_surface_settings`, `growth_integrations`, `growth_google_service_account` | Dropped via migration + removed from `ShopSettings` fillable |
| Growth CMS tables (`growth_*`, `public_surface_events`) | Dropped via `2026_09_05_140000_drop_website_growth_schema_from_core.php` |
| `route('public.book')` in portal access | Removed |
| Tests posting `public.leads.*` | Retargeted to `LeadRecorder` |
| `ark-featured-media-gallery.js` + Alpine registration | Deleted |
| Post-deploy `ark:public-surface:optimize-common-problem-media` | Removed |
| Social / “connect with us” marketing partials | Deleted in this pass |
| Marketing featured-media CSS block | Stripped |

## CONSOLIDATE

| Item | Action |
| --- | --- |
| Portal chrome still using `public-hero` / `public-panel` class names | Keep styles for portal UX; vocabulary is presentation chrome for customer app, not CMS product. Optional rename later - not required for boundary PASS |
| `CustomerSurfaceUrls::publicHome()` | Alias to portal access (no marketing homepage) |
| Appointment settings copy referencing `/book` | Reworded - availability stays Core; booking UI is Website |
| Stale docs mentioning Growth rails / thin public marketing surface | Point to this boundary; do not resurrect Growth |

## AMBIGUOUS → decision

| Item | Decision |
| --- | --- |
| `ShopSettings.website` URL field | **KEEP** - shop’s public URL on documents; not CMS config |
| Orphan `growth_session_id` columns | **REMOVE** - dropped with Growth schema; parent product gone |
| Residual `.public-cp-*` CSS in `app.css` | **REMOVE later / non-blocking** - pages gone; dead CSS does not restore Website ownership. Not required to block PASS |
| Events kiosk `marketing_opt_in` (if any remain) | **KEEP if Events ships**; else drop with Events product - out of Website CMS scope |
| External Website → Core lead HTTP ingest | **Future** - authority stays `LeadRecorder`; no marketing form in Core |

## Schema impact

| Change | Migration |
| --- | --- |
| Drop Growth/CMS tables + marketing `shop_settings` blobs + `growth_session_id` FKs | `2026_09_05_140000_drop_website_growth_schema_from_core.php` |
| `google_reviews_url` Core column | Kept (`2026_09_04_180000_…`) |

Fresh installs: drop migration is a no-op when tables/columns never existed. Upgrades from older Core: marketing schema removed.
