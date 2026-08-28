# Client realignment — v0.1

Four products. One Laravel ARK. One hosted Dragon.

| Product | Repo / surface | Job |
| --- | --- | --- |
| ARK Web | Laravel `/app` | Full shop management |
| ARK Desk | `apps/ark_desk` + `/api/desk` | Personal Windows advisor command center — **active** |
| Shop Glass | `apps/advisor_station` | Shared 1920×1080 display — **PARKED** |
| ARK Tech | `apps/ark_tech` + `/api/tech` | Technician DVI handheld |
| ARK Mobile | not created | Future advisor/owner phone — do not start |
| Hosted Dragon | ARK agent | Shared brain; client-specific context only |

## Flutter layout (now)

```
apps/
  ark_desk/          # ARK Desk — active Windows advisor product
  advisor_station/   # Shop Glass — parked
  ark_tech/          # ARK Tech v0.1
packages/            # not extracted yet
```

Shared packages (`ark_api`, `ark_auth`, …) wait until duplication hurts.

## Migration

| Piece | Classification |
| --- | --- |
| Shop Glass glance / Open in ARK / Ask Dragon | KEEP IN SHOP GLASS |
| Technician My Work / brake DVI / photo / voice confirm | ARK TECH (new) |
| Staff Sanctum + inspection actions | SHARED BACKEND (reused, not forked) |
| ESP voice lab | R&D / obsolete as tech DVI platform |
| Owner approvals / comms / money | FUTURE ARK MOBILE — not built |
| Universal Flutter shell | DELETE/OBSOLETE (never ship) |
