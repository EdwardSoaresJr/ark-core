# ADR-0007 — Stock Core voice transport boundary

- Status: Accepted
- Date: 2026-08-31
- Supersedes: ADR-0005 (Twilio-native voice transport as stock Core path)

## Context

Public ARK Core must remain a complete shop operating system without shipping our turnkey carrier voice implementation.

## Decision

Stock Core retains call-session domain, ring-group intent, and provider-neutral telephony contracts.
Stock Core does not ship Twilio (or other carrier) SDK adapters, webhook stacks, or paste-credential Settings for voice/SMS.

## Consequences

- Live PSTN/SMS requires a transport implementation or managed ARK service.
- ADR-0005 remains historical for private foundry context; it is not the public Core shipping model.
