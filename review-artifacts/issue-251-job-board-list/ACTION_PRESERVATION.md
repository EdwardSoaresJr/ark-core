# Job Board action preservation (Kanban → table)

Kanban columns and the Board/List toggle are removed from the Job Board screen.
Shared home-card / triage projections remain for other surfaces.

| Former card affordance | Table-first replacement |
| --- | --- |
| Open RO (`#builder`) | Row RO # / vehicle / status / chevron links |
| Customer hub link | Customer name link in Customer column |
| Status badge | Status column badge |
| Money total / due | Total column |
| Exception / attention | Attention column |
| Clock / age | Updated column |
| Activity marks (estimate, SMS, email, phone, photo, calendar) | Activity column (same marks + tooltips) |
| Promise / due time | Promised column |
| Parts pressure | Parts column |
| Technician / owner | Tech column |
| Concern headline | Concern / Job column |
| Check In | Toolbar Check In button |
| Search / employee filter | Toolbar search form |
| Lane inventory / overflow | Lane tabs + pagination + Repair Orders inventory links unchanged elsewhere |
| Recommended / decision flags | `data-workboard-attention` / `data-workboard-decision` retained on rows |

Legacy `?view=board`, `?view=list`, and cookie `ark_job_board_layout` normalize to the unified table Job Board.
