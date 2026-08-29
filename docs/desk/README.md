# ARK Desk

Personal Windows advisor command center (`apps/ark_desk`).

Not Shop Glass. Not ARK Tech. Not ARK Web.

Staff sign-in uses Sanctum: `POST /api/desk/auth/login`. No `stn_` station tokens.

The Windows app is **not** bound to Demo Auto Repair. On sign-in the advisor enters this shop’s ARK origin (`https://app.yourshop.com`). That host is the tenant. Tokens are stored per last shop; changing shops signs out first.

Location inside a tenant is a **workstation** (Front Counter, Service Office). Desk does not invent a Location domain. If the shop has more than one active station, the advisor picks where they are working (`POST /api/desk/workstation`).

## Deploy / update

1. On a Windows build PC: `cd apps/ark_desk && flutter build windows --release`
2. Copy `build/windows/x64/runner/Release/` to the advisor PC (or wrap with Inno Setup later).
3. Version is `pubspec.yaml` `version`.
4. This pass has no auto-updater. Replace the folder and relaunch.

Launch at Windows sign-in: Desk uses `launch_at_startup` after first Windows run.

## Shop Glass

Parked. Do not continue feature work in `apps/advisor_station`.
