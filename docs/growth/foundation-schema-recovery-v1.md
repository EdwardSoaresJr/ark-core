# Growth foundation schema recovery

**Status:** Open · forensic first · no production DDL in Website Phase 0  
**Discovered:** 2026-09-18 during `/sitemap.xml` 500 investigation on LugsNPlugs (`arkv2` / `lnp-mysql`)

Phase 0 keeps the public sitemap up with a missing-table **read guard**. That guard is not a repair. Do not recreate tables, rerun migrations, or start Growth jobs until this ticket is closed with evidence.

## Cause

Production ran `2026_09_05_140000_drop_website_growth_schema_from_core` (migrations id 315, batch 196). The file exists **only in the live Foundry image** (`lnp-foundry-public:preview`). It is **not** in current git or the Core image.

The migration assumed website/Growth schema did not belong in Core after extraction. `up()` dropped Growth tables (including `growth_contents` and `growth_sessions`), `public_surface_events`, `growth_session_id` on leads/conversations/repair_orders, and `shop_settings` columns `growth_google_service_account`, `growth_integrations`, and `public_surface_settings`. `down()` is empty.

Core and Foundry share `arkv2`. The drop applied to the shop database Foundry still uses. Current Core PHP still contains Growth (`SitemapEngine`, `ContentRegistry`). Schema was removed; the product code was not.

`php artisan migrate` will not recreate the tables: the original create migrations and this drop are both marked Ran.

## Live inventory (2026-09-18)

| Object | Present |
| --- | --- |
| `growth_redirects` | yes, **0 rows** (listed in the drop; table exists empty now) |
| `growth_contents` and remaining `growth_*` product tables | no |
| `shop_settings.public_surface_settings` | no |
| `leads.growth_session_id` | no |

Public sitemap 500: `ContentRegistry::publishedIndexable()` selected from `growth_contents`. Phase 0 returns an empty collection when the table is missing. Botble URL redirects still come from PHP config, not `growth_redirects`.

## What we still must answer before restore

The drop is explained. The **data** is not.

1. Was a MySQL dump taken **before** batch 196 (on or before 2026-09-05)?
2. Do volume snapshots on 149 (or the previous runtime host) still contain `growth_contents` / `growth_sessions` / Search Console snapshot rows?
3. After the drop, was Growth supposed to live in another database that was never attached? There is no second Growth schema on `lnp-mysql` that we have found.

Until a dump is found or ruled out, restoration is either **empty schema so the product can run again** or **data recovery**. Those are different jobs.

## Do not

- `php artisan migrate` / `migrate:fresh` / `migrate:refresh`
- Delete rows from `migrations` and re-run as a shortcut
- `CREATE TABLE` from memory without a dump comparison
- Enable Search Console sync, `growth:sync-public-content`, or other Growth schedule
- Treat the Phase 0 sitemap guard as “Growth is healthy”
- Copy the Foundry-image drop migration into git and run it again

## Forensic sequence (later ticket)

1. Copy the Foundry-image migration file off 149 into this ticket’s evidence folder (it is not in git).
2. Search MySQL dumps and Coolify volume snapshots dated before 2026-09-05 for `growth_contents`.
3. If a dump exists, compare row counts before deciding restore vs empty recreate.
4. Only then write a one-shot repair: restore from dump **or** a **new** migration that creates missing tables with `Schema::hasTable` guards.

## Phase 0 relationship

Website availability no longer depends on these tables existing. Owner Growth UI, Search Console ingest, and content registry remain deferred until this recovery is done.
