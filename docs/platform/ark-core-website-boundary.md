# ARK Core ↔ Website boundary

**Status:** SEALED - PASS baseline `7c24ce2` (2026-09-05)  
**Doctrine:** We extract Website from Core because **ARK Website is becoming its own automotive CMS product** - not because website capability is abandoned.

**Sealed product boundary:** ARK Website owns the form and customer-facing experience. ARK Core owns the resulting Lead.

A fact appearing on a website does **not** make it Website-owned. `shop_hours` can remain Core authority (operations need it). A future ARK Website **consumes** it. Homepage section titles, layout, styling, and SEO copy belong to Website.

Core is the shop operating system: repair orders, customers, communications, portal, intake, leads, installer, reporting, and shop identity.

Marketing website, SEO, Growth marketing tools, and the website **editor UI** are **not** Core.

Core may hold website **records** (sites, drafts, publications) and a signed read API so Platform and Foundry can use shop identity without a second editor in Core.

## What stays in Core (KEEP)

- Shop identity (`ShopSettings`: name, address, phone, email, `scheduling_hours`, `shop_timezone`, logo, `website` URL for documents)
- Google review destination (`shop_settings.google_reviews_url`) for post-repair review requests - Core messaging, not Website SEO
- Customer portal and tokenized estimate / inspection / pay links
- Leads as operational authority (advisor intake, `LeadSource::Website` for historical/source labeling, `LeadRecorder`)
- Website-lead interrupt presenters for uncontacted leads already in Core
- Ingress hygiene / spam observation on lead authority (no public marketing form required)
- `PUBLIC_DOMAIN` / `surfaces.public` as an optional **host routing** seam for portal co-location - not a marketing CMS
- Appointment **availability** configuration (when the shop can take requests) - presentation of booking UI is Website

## What left Core (REMOVE / left)

- Public marketing pages (home, book, common problems, financing, RepairPal, SEO assets)
- Growth opportunity / attribution / journey explorer product surfaces
- Website admin / performance settings UI
- `public_surface_settings`, `growth_integrations`, `growth_google_service_account`
- Growth CMS / analytics tables (`growth_*`, `public_surface_events`)
- Featured-media marketing gallery JS and related post-deploy artisan
- Broken `public.*` marketing route call sites in Core UI/tests

## Historical columns

`growth_session_id` is **dropped** from `leads`, `conversations`, and `repair_orders` by the Website/Growth schema drop migration. Do not reintroduce Growth attribution into Core.

## Future ARK Website

A future ARK Website product may **consume** Core shop facts (identity, hours, phone, policies) for publication. It does not become shop authority.

Useful concepts captured for migration: [ark-website-product-concepts-from-core.md](./ark-website-product-concepts-from-core.md)

Inventory: [ark-core-website-boundary-inventory.md](./ark-core-website-boundary-inventory.md)

ARK Website is conceptually independent of Connect: a shop may publish a site and receive email notifications without Connect; Connect enables direct Core workflow when that product exists. This document records the boundary only - no Website or Connect implementation claim.
