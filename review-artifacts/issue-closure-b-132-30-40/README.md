# Issue-closure B — #132 / #30 / #40

## #132 RO dock VIN
- `ro132-desktop-hover.png` — sticky footer order RO → VIN → customer → vehicle; phonetic popover on hover
- `ro132-mobile-pinned.png` — narrow viewport; inline popover above footer controls (not a covering sheet)

## Tests
- `RepairOrderWorkspaceStripTest` dock summary cases
- `MobileApiTest` appointment clocks (UTC boundary dataset) + closed-day refusal
- `SoftCapacitySchedulingTest` / `StaffInvitationTest` agenda + invitation landing (removed from known-failures)
