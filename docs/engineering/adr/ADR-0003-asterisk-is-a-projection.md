# ADR-0003: Asterisk Is a Projection, Not an Authority

**Status:** Accepted

## Context

Asterisk/FreePBX can host extensions, dial plans, and SIP endpoints. If ARK defers identity or call truth to Asterisk, the system splits authority and becomes undebuggable.

## Decision

**ARK is the authority.** Asterisk is transport and execution — a projection target for SIP registration, dial policy, and call legs. Ingress normalizes to `SessionEvent` → `CallSession`. Asterisk does not own extensions, workstations, or endpoint business identity.

## Consequences

- Extension registry lives in ARK (`telephony_extensions`), not FreePBX admin as source of truth.
- Asterisk config is generated or synced from ARK projections — not edited as authority.
- Dynamic Asterisk (ARI, realtime tables as authority) is out of scope until explicitly earned.
- See [docs/communications/ark-voice-endpoint-architecture-v1.md](../../communications/ark-voice-endpoint-architecture-v1.md) for frozen baseline.
