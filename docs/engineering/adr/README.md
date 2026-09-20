# Architecture Decision Records

Short, frozen decisions that constrain engineering work.

## Immutability

**Never edit an accepted ADR.** If a decision changes later, write a new ADR:

```
ADR-0005
Supersedes ADR-0003
```

History is preserved. Superseded ADRs remain in the directory with their status updated to *Superseded by ADR-NNNN* — the body is not rewritten.

## Index

| ADR | Title |
|-----|-------|
| [ADR-0001](ADR-0001-workstations-own-business-identity.md) | Workstations own business identity |
| [ADR-0002](ADR-0002-provisioning-is-a-projection.md) | Provisioning is a projection |
| [ADR-0003](ADR-0003-asterisk-is-a-projection.md) | Asterisk is a projection, not an authority |
| [ADR-0004](ADR-0004-endpoints-are-disposable.md) | Endpoints are disposable |
| [ADR-0005](ADR-0005-authorities-are-stable-projections-are-disposable.md) | Authorities are stable; projections are disposable |
| [ADR-0005](ADR-0005-twilio-native-voice-transport.md) | Twilio native voice transport (see ADR-0006 for SIP naming) |
| [ADR-0006](ADR-0006-programmable-voice-sip-domain-not-elastic-sip-trunking.md) | Programmable Voice SIP domain — not Elastic SIP Trunking |

**Canonical architecture:** [docs/communications/ark-voice-endpoint-architecture-v1.md](../../communications/ark-voice-endpoint-architecture-v1.md)
