# Autosave / false dirty — RCA

## Symptom

Closing ordinary workspaces shows: `This workspace has unsaved changes. Close without saving?`

## Source

`resources/js/ark-workspace-tabs.js` → `confirmClose` → `window.confirm(...)`.

Not browser `beforeunload` (tabs `beforeunload` only persists scroll/context).

## Mechanism

1. Trusted `input`/`change` → `syncDirty()` → `sessionStorage` dirty flag + tab.dirty.
2. Active close/switch: live `syncDirty()` via form `value` vs `defaultValue`.
3. Inactive close: session flag only.
4. Navigation often skipped confirm (`skipDirtyConfirm`) without flushing live dirty first.
5. First paint did not adopt rendered baselines (continuity morph did).

## Fix (this branch)

- Flush departing dirty before `location.assign`.
- Immediate + deferred hydration adopt that **skips touched fields** (race A).
- On successful save, adopt the **submit snapshot**, not live values (race B); ignore stale settle generations (race C).
- On confirmed “Switch without saving?”, clear sticky dirty for the departing key.
- Policy helpers + unit + Chromium race tests — see [RACE_AUDIT.md](./RACE_AUDIT.md).

## Protections retained

Failed autosave stays dirty; modal discard confirm; closed modal ignored for page dirty; open modal drafts still count while open.
