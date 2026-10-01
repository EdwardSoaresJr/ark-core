# Engineering Architecture

This document summarizes **permanent engineering doctrines** for ARK Voice endpoint work. It does not duplicate the full architecture specification.

**Canonical endpoint architecture:** [docs/communications/ark-voice-endpoint-architecture-v1.md](../communications/ark-voice-endpoint-architecture-v1.md)

Read that document for projection stack, identity chain, provisioning flow, schema, and frozen baseline details. This file holds only the doctrines every engineering agent must internalize.

---

## Doctrine 1

A communication device never has business identity.

It only has hardware identity.

Business identity belongs to the workstation through telephony authority.

Provisioning projects that authority onto the device.

---

## Doctrine 2

An endpoint is disposable.

A workstation is persistent.

---

## Doctrine 3

Authorities own truth.

Projections exist to answer questions.

---

## Doctrine 4

Provisioning is a read model.

It never creates authority.

---

## Doctrine 5

Asterisk is a projection.

ARK is the authority.

---

## Doctrine 6

Authorities are stable.

Projections are disposable.

No projection may become an authority.

---

## Related ADRs

| ADR | Decision |
|-----|----------|
| [ADR-0001](adr/ADR-0001-workstations-own-business-identity.md) | Workstations own business identity |
| [ADR-0002](adr/ADR-0002-provisioning-is-a-projection.md) | Provisioning is a projection |
| [ADR-0003](adr/ADR-0003-asterisk-is-a-projection.md) | Asterisk is a projection, not an authority |
| [ADR-0004](adr/ADR-0004-endpoints-are-disposable.md) | Endpoints are disposable |
| [ADR-0005](adr/ADR-0005-authorities-are-stable-projections-are-disposable.md) | Authorities are stable; projections are disposable |
