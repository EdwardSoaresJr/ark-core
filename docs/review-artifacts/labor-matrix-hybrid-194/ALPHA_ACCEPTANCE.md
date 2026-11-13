# Labor Matrix Hybrid UX (#194) — Alpha acceptance

**Stop here for owner Alpha acceptance. No production cutover without explicit authorization.**

## Candidate pins (Alpha)

| Layer | SHA | Notes |
| --- | --- | --- |
| Core | `0423ab03e480d8520f60ed49a1544df40ddc123b` | PR https://github.com/EdwardSoaresJr/ark-core/pull/195 |
| Stancl / SaaS | `0079ab0a38cf4c38fc9a69990ab72692c817b351` | PR https://github.com/EdwardSoaresJr/ark-stancl/pull/226 |

## Alpha host

| Item | Value |
| --- | --- |
| Host | `157.230.172.143` / `https://alpha.staging.arksms.com` |
| Deploy | `ARK_STANCL_BACKUP_VERIFY=integrity bash deploy/deploy.sh 0079ab0a…` |
| Health | `/up` → 200; APP_ENV staging |
| Production | Untouched (`lugsnplugs.arksms.com` still prior pin) |

## What to accept

1. Labor settings hybrid table: Billing Class · Rate Type · Hourly Rate · Min Hrs · Round To · Class Settings.
2. No Adjust Labor / Allow Modifiers column.
3. Work Type nav + Default Labor Rate + Manage Billing Classes preserved.
4. Min Hrs edits save via existing billing-class minimums (all work types).
5. Round To edits save via work-type `labor_categories.rounding_rule` (shared across class rows).
6. Existing shop rates / open RO stamps unchanged by UI-only visit.

## Browser proof (this session)

- Desktop hybrid layout captured: `screenshots/desktop-labor-matrix-hybrid.png`
- Inline Round To autosave: UI → `quarter` persisted on `/labor-matrix` JSON (`work_type_settings.mechanical.rounding_rule`), then restored to `exact` (profile version advanced).
- Mobile 390×844: work types, rate columns, Min Hrs / Round To / Class (`screenshots/mobile-*.png`).
- Page text contains neither `Adjust Labor` nor `Allow Modifiers`.

## Owner decision

- [ ] **ACCEPT Alpha** — authorize production prep / deploy naming these SHAs
- [ ] **CHANGES REQUIRED** — list UI or behavior notes
- [ ] **HOLD** — leave Alpha pin; do not promote
