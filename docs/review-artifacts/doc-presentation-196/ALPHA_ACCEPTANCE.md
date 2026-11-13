# Billing Class Document Presentation — Alpha acceptance

**STOP for owner visual acceptance. Production remains on hold.**

## Candidate pins (Alpha)

| Layer | SHA | Notes |
| --- | --- | --- |
| Core | `fd7256034d5a5dcc893e38b13bc2f69b33419ed4` | PR https://github.com/EdwardSoaresJr/ark-core/pull/196 |
| SaaS | `0c97c3c3e1a5cd258d93429fc5abc2b15137ec01` | PR https://github.com/EdwardSoaresJr/ark-stancl/pull/228 |

Baseline includes accepted Labor Matrix R1+D2 (`b29bf318` ancestor).

## Alpha host

| Item | Value |
| --- | --- |
| Host | `157.230.172.143` / `https://alpha.staging.arksms.com` |
| Health | `/up` → 200 |
| Production | Untouched (`lugsnplugs.arksms.com`) |

## Authority (verified in tests)

- Class Settings checkboxes are the presentation source of truth per Billing Class.
- Concern billing posture selects the matching Billing Class and uses **that class’s checkboxes** — it does not hard-code Warranty/RepairPal field combinations.
- Legacy profiles migrate: Retail/Fleet → description only; Warranty → number+vendor; RepairPal → number.
- Issued document snapshots remain frozen; not regenerated.

## Screenshots

- `screenshots/alpha-class-settings-warranty-checkboxes.png` — Warranty: part number + vendor checked
- `screenshots/alpha-class-settings-retail-checkboxes.png` — Retail: all optional identifiers unchecked

## Owner decision

- [ ] **ACCEPT Alpha** — authorize merge + combined production prep with Labor Matrix
- [ ] **CHANGES REQUIRED** — list UI/behavior notes
- [ ] **HOLD** — leave Alpha pin; do not promote

---

## Addendum — lifecycle (Archive / Restore / Delete)

| Layer | SHA |
| --- | --- |
| Core | `fc9b9974db8b96581d7b060f715227626b3f3b91` |
| SaaS | `552adc046489ecae378cf17a95dc9bcee843e37a` |

Identity rules: see `LIFECYCLE_IDENTITY.md`. Customer Billing Classes ≠ labor columns.

### Accept
1. Class Settings gear remains primary editor for labor-linked classes; footer Archive/Restore/Delete.
2. “Customer-only & archived” replaces broad Manage list redundancy; Add Billing Class preserved.
3. Work type heading actions menu: Archive/Delete (default protected).
4. Delete refuses with references; Archive preserves historical pricing.

Production remains on hold.
