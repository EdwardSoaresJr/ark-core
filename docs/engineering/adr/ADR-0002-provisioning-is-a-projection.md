# ADR-0002: Provisioning Is a Projection

**Status:** Accepted

## Context

Vendor provisioning files (Poly `.cfg`, etc.) look like configuration authority. Treating them as source of truth would duplicate business logic in firmware formats and static files.

## Decision

**EndpointConfigurationProjection** is a read model derived from authority and configuration policy. Provisioning builders serialize that projection into vendor formats. Provisioning never creates or mutates authority.

## Consequences

- Regenerating a `.cfg` is a projection refresh, not a business event.
- GET provisioning endpoints must not allocate extensions or assign workstations.
- Invalidation/regeneration actions update the projection row, not telephony authority.
- `ProvisionBuilder` and vendor builders are serialization layers only.
