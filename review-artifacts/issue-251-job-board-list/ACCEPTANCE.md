# Job Board — List | Cards view switcher

**PR:** _(new focused Core PR; #252 already merged)_  
**Supersedes Job Board tip:** `38c8d9c9dcb16aa795e0795f9af5ead505145f7e`  
**Base:** `main` (includes merged #252)

**Stop state:** Implement + CI + screenshots done. No merge without integration-owner authorization. No deploy. No SaaS Core pin change. No Sites changes.

---

## What landed

| Area | Behavior |
| --- | --- |
| Selector | Compact Tabler `nav-segmented` **List \| Cards** in the Job Board filter toolbar |
| Default | `auto`: List on desktop (md+), Cards on mobile until the user chooses |
| Persistence | Cookie `ark_job_board_layout` (1 year). No migration. Shop-default storage is a documented follow-up |
| Data | List and Cards render the **same** `JobBoardListPage` / `JobBoardListRow` result set |
| Cards | Compact cards with RO #, customer, vehicle, concern, status, tech, total, attention, activity deep links, updated |
| Switch | Client-side toggle (no full reload); `?view=list\|cards` also works and sets the cookie |
| Legacy | `?view=board` maps to Cards |

---

## Screenshots

| File | Focus |
| --- | --- |
| `screenshots/switcher-list-1366.png` | List @ 1366 |
| `screenshots/switcher-cards-1366.png` | Cards @ 1366 |
| `screenshots/switcher-cards-1440.png` | Cards @ 1440 |
| `screenshots/switcher-cards-1920.png` | Cards @ 1920 |
| `screenshots/switcher-cards-390.png` | Cards @ 390 |
| `screenshots/switcher-list-390.png` | Explicit List on mobile |

---

## Follow-ups (not in this PR)

1. Shop-default Job Board layout (requires settings/storage decision).
2. Optional per-user DB column if cookie/device sync is insufficient.
3. Appointment activity deep link (still tooltip-only).

---

## Integration note

Previous Job Board SHA `38c8d9c9` is superseded by this PR tip once accepted. Integration owner should pin the new Core SHA for the combined Sites + Job Board release — do **not** change the SaaS pin from this session.
