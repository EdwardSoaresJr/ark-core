# Shop CSV import

**Authority:** `Customer` · `Vehicle`  
**Surface:** Settings → Operations → Shop import  
**Not authority:** staging tables, import batches, competitor adapters

## Flow

```text
CSV upload → Preview (no writes) → Confirm → Customer / Vehicle rows
```

Match order: **phone**, then **email**. Vehicles attach when year/make/model, VIN, or plate columns are present.

Template: `GET /app/settings/shop/import/template`

## Out of scope (v1)

Repair orders, payments, SMS consent, VIN decode, async queues. Full ARK v1 MySQL history remains `ark:import-legacy-arksms`.
