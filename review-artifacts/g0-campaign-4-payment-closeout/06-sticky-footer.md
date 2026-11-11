# DEF-G0-03 — sticky footer residual check

**Staging pin:** Core `e9b4268` / SaaS `c60f2c37`.

## Result

**Pass on current pin** at viewport heights **700** and **600** (1280 wide).

- Sticky footer (`Repair Order actions`) measured below the financial rail.
- `Take payment` center hit-tests returned the primary button (no footer interception) after normal scroll into the rail.
- One earlier automation click failed with “Click target intercepted” **before** scrolling the button into view — classified as **automation positioning**, not a confirmed short-desktop obstruction on this pin.

No additional footer/header redesign applied.
