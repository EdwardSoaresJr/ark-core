# Before / after estimate rate proof

Verified by `tests/Feature/Operations/StandardLaborRateSettingsTest.php` (tenant-scoped sqlite):

| Step | Customer Pay · general_repair | Default category Mechanical | Notes |
|------|-------------------------------|-----------------------------|-------|
| Before save | $165.00/hr | $165.00 | Seeded standard |
| Save Standard hourly rate `$185.00` | **$185.00/hr** | **$185.00** | All matching Customer Pay classes synced |
| Specialty diagnostics was $210, then standard → $190 | general_repair **$190**; diagnostics **$210** | $190 | Specialty preserved |
| Existing line after standard → $200 | new policy $200; **line stays $165** | $200 | Snapshot immutability |
| Internal / Comeback | **$0.00** | n/a | Unchanged |

Effective estimate rate shown on Labor settings is resolved via `LaborPolicyResolver` (Customer Pay × default operation class), not a display-only field.
