# ARK Runtime Authority

**Purpose:** One page per subsystem where **reality is written down** — not ceremony, not encyclopedia.

If a page stops helping decisions, delete or rewrite it.

**Companion rules:** ark-subsystem-lifecycle.mdc · ark-cleanup-sprint-discipline.mdc · ark-two-implementations.mdc

---

## Lifecycle (Pressure → Evolution)

What replaced the old **Pressure → Code** trap:

```text
Pressure
    ↓
Authority
    ↓
Inventory
    ↓
Runtime              ← this catalog (one page per subsystem)
    ↓
Cleanup
    ↓
Observation
    ↓
Stabilize
    ↓
Baseline
    ↓
Evolution
```

**Production earns the right to evolve.**

---

## Three states

| State | Meaning | Voice (2026-07-04) |
| --- | --- | --- |
| **Converging** | Inventory · cleanup · architecture OK | Backend PV still loaded (Phase D) |
| **Observing** | Frozen · logs · bug fixes only | **Mobile + floor certification** |
| **Evolving** | Features after baseline trust | Not yet |

See ark-subsystem-lifecycle.mdc.

---

## One-page template

Every subsystem runtime page includes:

1. **Authority** — who owns truth  
2. **Runtime** — production path (30-second test)  
3. **Baseline** — link to operations snapshot  
4. **Known defects** — missing capabilities, not instability  
5. **Observation gate** — what must pass before evolution  

---

## Catalog

| Page | State | Scope |
| --- | --- | --- |
| [voice-runtime-authority.md](./voice-runtime-authority.md) | Observing | Voice + ARK Phone + PBX |
| [voice-baseline-v1.md](./voice-baseline-v1.md) | Observing | Operations snapshot + log |
| [scheduling-runtime-authority.md](./scheduling-runtime-authority.md) | Converging | Appointments · Schedule · Operational Capacity · Floor Proof pending |
| [shop-memory-runtime-authority.md](./shop-memory-runtime-authority.md) | Observing (v1 COMPLETE) | Shop Memory · Labor + Add Concern popup ON · providers gated · observation before enable |
| [operator-adoption-pass-v1.md](./operator-adoption-pass-v1.md) | Converging | Track E — another shop without tribal knowledge; H blocked until exit criterion |
| [payments-runtime-authority.md](./payments-runtime-authority.md) | Observing | Ledger · external/manual · portal balance links |
| `communications-runtime-authority.md` | — | Conversation · SMS · timeline |
| `identity-runtime-authority.md` | — | Customer · vehicle · extension |
| `inspection-runtime-authority.md` | — | Findings · evidence |
| `pricing-runtime-authority.md` | — | Matrix · `EstimateTotalsCalculator` |

Add a page when a subsystem **completes convergence** — not when someone wants a doc.

Sprint evidence stays in sprint/archive docs — not duplicated here.

---

## Archive

Post-sprint ship artifacts → [`docs/archive/`](../archive/). Permanent truth stays here.

---

## Related (voice sprint)

- [phase-b-voice-cleanup-mission-v1.md](../communications/phase-b-voice-cleanup-mission-v1.md)
- [communications-voice-cleanup-sprint-v1.md](../communications/communications-voice-cleanup-sprint-v1.md)
- [ark-mobile-voice-cleanup-inventory-v1.md](../mobile/ark-mobile-voice-cleanup-inventory-v1.md)
