# Dirty-state / autosave race audit (PR #293)

## Scenarios

| ID | Scenario | Result |
|----|----------|--------|
| A | Edit before deferred boot adopt | **Fixed** — touch tracking skips fields already edited; early edit stays dirty |
| B | Submit A, edit to B, response A succeeds | **Fixed** — `adoptSnapshotAsBaseline` uses submit snapshot, not live B |
| C | Overlapping / out-of-order autosaves | **Mitigated** — worksheet `worksheetBusyPending` serializes POSTs; settle ignores stale `_saveGeneration` |
| D | Failed save while still editing | **Preserved** — `dirtyAfterSaveAttempt(ok:false)` + no snapshot adopt on failure |
| E | Inactive while save pending | Flush on navigate + sticky dirty; snapshot adopt skipped if form detached |
| F | Navigate away, return, close inactive | Register clears active; confirmed switch-away clears departing sticky dirty |

## Modal protection

Unchanged: `Discard unsaved changes in this form?`; closed modal ignored for page dirty.

## Tests

- `resources/js/ark-workspace-dirty-races.test.js`
- `resources/js/ark-workspace-dirty.test.js`
- `tests/JavaScript/ark-workspace-dirty-browser.test.mjs` (Chromium)

## Remaining safety notes

- Browser unload mid-request still cannot flush network completion into a new document (existing SPA limitation).
- Concurrent saves of *different* forms remain gated by the single worksheet busy flag (by design).
- Deferred Alpine writes after both adopts that do not fire `input`/`change` could still drift — rare; report if observed.
