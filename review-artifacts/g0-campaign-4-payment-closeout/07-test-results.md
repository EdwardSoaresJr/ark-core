# Regression results

## Focused JS (DEF-G0-05)

`node --test resources/js/ark-payment-capture.test.js` → **19/19 pass** (includes iframe/foreign-focus shortcut guard).

## Focused financial failures (still open as test debt)

Filter:

`issued invoice snapshot stays frozen when estimate changes after partial payment|presenter with preloaded balance projection does not add BalanceDueCalculator queries`

→ **0/2 pass** (same Fail A / Fail B as Campaign 3). Classification in `01-` / `02-`. **No calculation change applied.**

## Broader suite

Campaign 3 baseline remains **129/131** on the same two failures. Broader suite not re-run end-to-end in Campaign 4 after the isolated JS-only pin (no PHP financial logic changed).
