# Tabler collection List/Cards — component inventory

**Branch:** `feat/tabler-collection-list-cards`  
**Consumers:** Customers search, Vehicles search, Repair Orders index  
**Out of scope:** Job Board (keeps its own layout JS/cookie until a later unify pass)

## Shared PHP

| Type | Symbol | Role |
|------|--------|------|
| Enum | `CollectionLayout` | `list` \| `cards` |
| Enum | `CollectionLayoutModule` | `customers` \| `vehicles` \| `repair_orders` + cookie names |
| Preference | `CollectionLayoutPreference` | `?view=` → module cookie → `auto` |
| Sort | `CollectionSortState` | Server-side sort URL / aria-sort / apply |

Cookie names (EncryptCookies excepted in Core):

- `ark_collection_layout_customers`
- `ark_collection_layout_vehicles`
- `ark_collection_layout_repair_orders`

## Shared Blade (`x-tabler.*`)

| Component | Responsibility |
|-----------|----------------|
| `layout-toggle` | Segmented List \| Cards control |
| `collection-summary` | Title + Showing X–Y of Z + optional clear |
| `sortable-th` | Sortable header with `aria-sort` |
| Existing `search-filters` | Search + filter columns |
| Existing `record-card` | Card projection |
| Existing `empty-state` | Empty / no-results |

## Shared JS / CSS

| Asset | Role |
|-------|------|
| `resources/js/ark-collection-layout.js` | Client toggle, cookie, viewport auto, hidden `view` input |
| `tabler.css` `[data-collection]…` rules | Show/hide List vs Cards; auto md breakpoint |

## Module projections

Each index keeps one dataset and three projections:

1. Desktop List table (`d-none d-md-block`)
2. Compact mobile List (`d-md-none`) when List is selected
3. Cards grid (existing `ark-card-grid` / `record-card`)

## Job Board boundary

No Job Board PHP/Blade/JS files were modified in this slice.
