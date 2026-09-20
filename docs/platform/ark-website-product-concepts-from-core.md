# Useful concepts for future ARK Website (from Core extraction)

**Not an implementation plan.** Inventory of product knowledge removed or no longer owned by Core so ARK Website can reuse the **ideas** without Core keeping the runtime.

## Publication consumes Core facts

| Core authority | Website use |
| --- | --- |
| Shop name, address, phone, email, hours, timezone | Footer, contact, NAP, schema.org |
| Logo / operational branding | Header mark (Website may also hold marketing art) |
| `website` URL | Canonical shop site link |
| Policies / warranty facts (when present on Core) | Policy pages — copy owned by Website, facts from Core when operational |
| Appointment **availability** | Booking widget availability; UI/theme is Website |
| `google_reviews_url` | Optional outbound “leave a review” — Core also uses for post-repair SMS |

## Concepts that belonged to Website/CMS (do not rebuild in Core)

| Concept | Notes |
| --- | --- |
| Homepage sections / section titles | Presentation |
| Themes, layout, editorial page chrome | Presentation |
| SEO titles/descriptions, sitemap, redirects | Website |
| Common-problems / DTC content catalog | Content product |
| Featured media galleries / lightbox marketing UX | Content media presentation |
| Financing / RepairPal marketing pages | Marketing |
| Public book/lead **forms** as marketing pages | Website hosts forms; Core receives leads via future ingest → `LeadRecorder` |
| Growth opportunity queue, Search Console/GBP dashboards, attribution explorers | Marketing analytics — not Core ops |
| Website admin “Manage vs Performance” | Website product admin |
| Social brand link strips on customer chrome | Marketing presentation |

## Lead handoff (future)

Core keeps lead **authority**. Website should submit into Core (authenticated install webhook or Platform-mediated ingest) calling the same `LeadRecorder` path advisors/tests use today. Do not recreate a Core-hosted marketing lead form.
