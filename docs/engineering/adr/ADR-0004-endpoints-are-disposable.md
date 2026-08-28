# ADR-0004: Endpoints Are Disposable

**Status:** Accepted

## Context

Desk phones fail, get replaced, or move between desks. If device records carry business identity, every replacement becomes an extension move, history migration, or manual reconfiguration event.

## Decision

An **endpoint (communication device) is disposable.** A **workstation is persistent.** Dropping a VVX350 off a ladder: remove old device, register new MAC, reprovision. Workstation, extension, and operational context unchanged.

## Consequences

- Device lifecycle is link/unlink/reprovision — not "move extension."
- `EndpointConfigurationProjection` is keyed to device hardware identity; regeneration is cheap.
- UI and APIs must not treat device replacement as telephony reassignment.
- See [ADR-0001](ADR-0001-workstations-own-business-identity.md) for identity ownership.
