# Square sandbox — supervised human checklist (owner)

**Why stopped here:** Cross-origin Square Web Payments iframe cannot be filled reliably by the browser automation harness. That is an **automation limitation**, not proof the form is broken.

**Staging pin after DEF-G0-05:** Core `e9b4268` / SaaS `c60f2c37` on `157.230.172.143`.

**Pre-checks already done (agent):**

- Payment workspace opens; Keyed card loads Square sandbox iframe (`cardReady=true`).
- Amount defaults to **$259.50**.
- With iframe focused, digit keydown no longer changes amount (**DEF-G0-05 Pass** in-browser).
- RO #20 invoice id 7 remains **25950¢**, ledger payments **0**, balance due **25950¢**.

## Owner steps (sandbox only)

Use Square sandbox test cards only. Do not use real cards.

1. Open `https://alpha.staging.arksms.com/app/repair-orders/20` → **Take payment** → **Keyed card**.
2. Confirm amount **259.50** and Square card fields visible.
3. **Decline:** enter Square sandbox decline PAN (e.g. `4000 0000 0000 0002`), any future expiry, any CVV, postal if prompted → **Take $259.50**.
4. Capture on-screen error; confirm **no** ledger payment row; amount still unpaid.
5. **Fresh-page retry:** hard refresh RO #20, reopen Take payment, confirm form clean and amount 259.50.
6. **Success:** sandbox success PAN (e.g. `4111 1111 1111 1111`) → Take $259.50.
7. Confirm one payment **25950¢**, balance **0**, provider transaction id recorded on the ledger entry / payment metadata.
8. Do **not** double-submit; if webhook/callback retries are visible in logs, confirm still **one** ledger payment (idempotency).
9. Reply in-thread with screenshots + provider ids (no PAN/CVV).

Agent will then finish closeout + immutability on RO #20 without DB force-close.
