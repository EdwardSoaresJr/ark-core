# ARKademy Landing IA

**Status:** Recommended structure — placeholders documented; content migration is a separate phase.

## Goal

When staff land on ARKademy, the question answered is:

**“Where do I learn how we run the shop?”**

Not: “I am in a generic wiki.”

## BookStack home today

BookStack default home can show **Shelves**, **Books**, or a **custom HTML** page. ARKademy uses:

- **APP_THEME=arkademy** — ARK header mark, cerulean accents, ecosystem switcher
- **Shelf landing** — `Shop In A Box` shelf as primary entry (`BOOKSTACK_SHELF_SLUG=shop-in-a-box`)
- **OIDC** — staff enter via ARK identity; no parallel BookStack passwords

**Verdict:** BookStack home customization is **sufficient for Phase 1** when the primary shelf is curated and named for shop operations. A dedicated custom HTML landing page is **optional later** if advisors still miss role-based entry.

## Recommended shelf structure

### Shop In A Box (base curriculum)

Distributable ARK content — maps from existing Blade catalogs:

| Book | Role catalog | Audience |
|------|--------------|----------|
| Owner Excellence | `LearnArkOwnerArticles` | Owners |
| Advisor Operations | `LearnArkAdvisorArticles` | Service advisors |
| Technician Operations | `LearnArkTechnicianArticles` | Technicians |
| Admin Setup | `LearnArkAdminArticles` | Shop admin |

Add when ready (not blocking landing):

| Book | Purpose |
|------|---------|
| Operations | Cross-role floor workflow (workboard rhythm, handoffs) |
| ARK V2 Training | Product-specific how-tos tied to ARK UI |

### Demo Auto Repair SOPs (shop shelf)

Shop-private content — not distributed to other shops:

| Book | Purpose |
|------|---------|
| Owner | Shop-specific owner rhythm |
| Service Advisor | Counter and advisor SOPs |
| Technician | Bay and production SOPs |
| Operations | Shop floor conventions |

## Placeholder pages (future SOPs — not final content)

Use BookStack pages with clear **PLACEHOLDER** titles until real SOPs are written:

| Placeholder topic | Suggested book |
|-------------------|----------------|
| Answering the Phone | Demo Auto Repair SOPs · Service Advisor |
| Handling Self-Diagnosed Customers | Demo Auto Repair SOPs · Service Advisor |
| Building an Estimate | Shop In A Box · Advisor Operations |
| Vehicle Check-In | Demo Auto Repair SOPs · Service Advisor |
| Parts Ordering | Shop In A Box · Advisor Operations |
| Warranty Exceptions | Demo Auto Repair SOPs · Operations |

**Do not** treat these as authoritative SOPs until shop review. Registry keys (`legacy-key:{role}:{slug}`) should be assigned when pages are promoted from placeholder to operational truth.

## Implementation path

| Step | Action | Owner |
|------|--------|-------|
| 1 | Ensure `Shop In A Box` shelf exists with role books | `ark:arkademy:import-bookstack --force` |
| 2 | Set BookStack default home to **Shelves** or deep-link shelf URL | BookStack settings / ARK cutover URLs |
| 3 | Create **Demo Auto Repair SOPs** shelf + empty books (manual or API) | Shop admin |
| 4 | Add placeholder pages with visible “Draft” tags | Shop admin |
| 5 | Register URLs in `arkademy_content_registry` when stable | ARK import / manual |

This pass **does not** run bulk placeholder creation — avoids fake authority in the registry.

## Custom landing page — when needed

Consider a custom BookStack home or theme view if:

- Advisors still open ARKademy and ask “where do I start?”
- Role-based entry needs above-the-fold cards (Owner / Advisor / Tech)
- Shop In A Box and Demo Auto Repair SOPs need explicit visual hierarchy

Prefer **shelf descriptions and book ordering** first. Custom landing is **Phase 2 UX**, not a blocker for ecosystem cohesion.

## ARK V2 entry

Staff should reach ARKademy via:

- Left rail **Learn** (training gate aware)
- Ecosystem switcher **ARKademy**
- Deep links from call intelligence / coaching digest

All use `ArkademyUrls` / `EcosystemArkademyBridge` — deterministic legacy keys, not search.

## Success check

Staff can answer without guidance:

1. Where is **base** curriculum? → Shop In A Box shelf
2. Where are **our shop** SOPs? → Demo Auto Repair SOPs shelf
3. Where is **my role** book? → Named book inside the right shelf
