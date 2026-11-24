# Job Board action preservation (Kanban → table)

Kanban swimlanes are removed from the Job Board screen. A compact **List | Cards**
segmented control switches presentation over the same filtered/paginated result set
(cookie preference; shop default is a follow-up). Shared home-card / triage projections
remain the data source for both layouts.

| Former card affordance | Was clickable on card? | Table-first replacement |
| --- | --- | --- |
| Open RO (`#builder`) | Yes (vehicle/status/RO) | Row RO # / vehicle / status / chevron links |
| Customer hub link | Yes | Customer name link in Customer column |
| Status badge | Yes → RO | Status column badge → RO |
| Money total / due | No (display) | Total column |
| Exception / attention | No (display; +N dropdown list only) | Attention column |
| Clock / age | No (display) | Updated column |
| Activity marks (estimate, SMS, email, phone, photo, calendar) | **No** on cards — tooltip only | All active marks in fixed category order (no overflow). Verified deep links: estimate → `#builder`, sms/email/phone → `#comms`, dvi → `#inspect`. Scheduled remains tooltip-only. |
| Promise / due time | No (display) | Removed from list UI (fields retained elsewhere) |
| Parts pressure | No (display) | Removed from list UI (Waiting Parts status + RO detail retained) |
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
