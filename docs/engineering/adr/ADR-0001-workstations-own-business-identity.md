# ADR-0001: Workstations Own Business Identity

**Status:** Accepted

## Context

Communication devices (desk phones, softphones) have MAC addresses and firmware. They do not have extensions, customer context, or operational role. Business identity — extension, workstation assignment, operator presence — is shop workflow truth.

## Decision

Business identity belongs to the **workstation** through **telephony authority**. Communication devices hold hardware identity only. Provisioning projects workstation/telephony authority onto the device at read time.

## Consequences

- `CommunicationDevice` stores MAC, model, firmware — not extension number as authority.
- Extension assignment flows through `TelephonyExtension` → `Workstation`, not device tables.
- Replacing a phone does not move business identity; only hardware identity changes.
- See [ADR-0004](ADR-0004-endpoints-are-disposable.md) for device replacement posture.
