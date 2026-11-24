# Unified Tabler List/Cards — Customers, Vehicles, Repair Orders

**Stopped for owner review.** No merge, deploy, or SaaS Core-pin change.

## Exact revisions

| Role | SHA |
|------|-----|
| Core `origin/main` baseline | `9cc852899b2ab9754185675b7d83d656978b2b33` |
| This branch tip | *(filled after push)* |
| SaaS `origin/main` (unchanged pin) | `df735e4096e94eb8f0440f5124794ea924a88d2a` → Core `9cc85289` |

Related open PRs left alone: #254 List UX, #256 Card hierarchy, #255 DataTables eval (do not merge).

## Environment

| Check | Result |
|-------|--------|
| Branch | `feat/tabler-collection-list-cards` from `origin/main` |
| Job Board files | Not modified |
| `public/hot` | Absent; Vite production build |
| Synthetic data | Local demo customers / vehicles / ROs |
| Collection cookies excepted in Core EncryptCookies | Yes (`ark_collection_layout_*`) |

**SaaS note:** production EncryptCookies must later except the three collection cookies (or adopt a shared except list) before this ships on SaaS. Not done here by design.

## What shipped

Shared:

- `CollectionLayout` / `CollectionLayoutModule` / `CollectionLayoutPreference`
- `CollectionSortState`
- `x-tabler.layout-toggle`, `collection-summary`, `sortable-th`
- `ark-collection-layout.js` + `[data-collection]` CSS
- Inventory: `docs/engineering/TABLER_COLLECTION_COMPONENT_INVENTORY.md`

Per index (same authorized dataset for List + Cards):

- Customers — sort name/phone/email/type/vehicles/updated
- Vehicles — sort vehicle/customer/plate/vin/updated; mileage only when available
- Repair Orders — sort ro/customer/vehicle/status/opened/updated; existing filters preserved; separate from Job Board

Responsive: auto → List md+, Cards below; explicit choice overrides; compact mobile List rows avoid horizontal table overflow.

## Screenshots

`review-artifacts/tabler-collection-list-cards/screenshots/`

- `customers-list-1440.png`
- `customers-cards-1440.png`
- `customers-cards-390.png`
- `vehicles-list-1440.png`
- `repair-orders-list-1440.png`

## Tests

- `CollectionLayoutPreferenceTest` + `CollectionListCardsTest` + `VehicleSearchTest` + `TablerShellTest`: **42 passed**
- RO index pagination assertion updated for Laravel bootstrap-5 (`class="pagination"`)

## Remaining

- Owner visual acceptance (light/dark, desktop/mobile).
- SaaS EncryptCookies except for collection cookies before deploy.
- Job Board still on its own layout cookie/JS (unify later).
- Preference DB sync not implemented (docs only).
