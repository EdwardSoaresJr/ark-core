# Job Board action preservation (Kanban → table)

Kanban columns and the Board/List toggle are removed from the Job Board screen.
Shared home-card / triage projections remain for other surfaces.

| Former card affordance | Was clickable on card? | Table-first replacement |
| --- | --- | --- |
| Open RO (`#builder`) | Yes (vehicle/status/RO) | Row RO # / vehicle / status / chevron links |
| Customer hub link | Yes | Customer name link in Customer column |
| Status badge | Yes → RO | Status column badge → RO |
| Money total / due | No (display) | Total column |
| Exception / attention | No (display; +N dropdown list only) | Attention column |
| Clock / age | No (display) | Updated column |
| Activity marks (estimate, SMS, email, phone, photo, calendar) | **No** — informational `role="img"` + tooltip only on cards | Activity column shows **active** marks only (tooltips). Idle marks collapse to `—` so the row stays dense. |
| Promise / due time | No (display) | Promised column |
| Parts pressure | No (display) | Parts column |
| Technician / owner | No (display) | Tech column |
| Concern headline | No (display) | Concern / Job column |
| Check In | Toolbar | Toolbar Check In button |
| Search / employee filter | Toolbar | Toolbar search form |
| Lane inventory / overflow | Lane headers | Lane tabs + pagination; Repair Orders inventory unchanged elsewhere |
| Recommended / decision flags | Data attrs | `data-workboard-attention` / `data-workboard-decision` retained on rows |

## Messaging / call / calendar operations

Card activity marks were **never** action buttons. Real operations stay on the RO workspace after row drill-through:

| Operation | Where it lives |
| --- | --- |
| SMS / email / messaging | RO workspace → Communications tab / Message affordances |
| Phone / call context | Customer phone on row (xxl+) and RO customer panel / Communications |
| Estimate send / portal | RO Estimate / Customer Portal panels |
| Calendar / appointment | RO visit / schedule surfaces (unchanged) |
| Photos / DVI | RO Inspection / Add Photo |

Legacy `?view=board`, `?view=list`, and cookie `ark_job_board_layout` normalize to the unified table Job Board.
