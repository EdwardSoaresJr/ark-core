# Inspection Authority 1.0 Contract

**Status:** Superseded for canonical authority — see [Inspection Authority](inspection-authority.md)  
**Principle:** Store observed facts. Project urgency, customer views, and messaging later.

Same sequence as Identity and Communications:

```
Authority → Projection → Delivery
```

---

## Allowed (Phase 1.0)

| Layer | Records |
|-------|---------|
| Inspection | One per repair order |
| InspectionItem | Category, label, observed state, notes, optional concern link |
| InspectionItemMeasurement | Name, value, unit — **rows, not JSON** |
| InspectionItemPhoto | Storage path, purpose, content type — **rows, not JSON** |
| Categories | Seeded labels for organization only |
| Linkage | `repair_order_id`, optional `repair_order_concern_id` on items |

### Observed states (authority)

- `not_checked`
- `pass`
- `fail`
- `measure`
- `na`

### Photo purpose (authority — not customer portal)

- `internal`
- `customer`
- `before`
- `after`

Severity colors (green / yellow / red) are **forbidden** as persisted truth.

---

## Forbidden (Phase 1.0)

- Customer portal or public inspection URLs
- Send Inspection Link / SMS
- DVI report PDF generation
- Red / yellow / green persistence
- Recommendation engines tied to inspection rows
- AI summaries
- Training gate hooks
- Estimate line generation from inspection
- MPI template rows as authority (templates seed items only)

---

## Success criterion

A technician can record what they observed on a repair order — items, measurements, photos, and notes — with no other workflow required.

---

## Future phases (not 1.0)

| Phase | Delivers |
|-------|----------|
| 1.5 | Finding-first technician workflow — see [Inspection Authority v1.5](inspection-authority-v1.5.md) |
| 2.0 | Customer inspection portal (projection) |
| 2.5 | Send Inspection Link on comms rail (delivery) |

Vehicle history across visits reads **measurement rows**, not concern blobs.
