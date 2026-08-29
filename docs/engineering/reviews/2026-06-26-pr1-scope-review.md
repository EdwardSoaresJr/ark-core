# PR1 Scope Review

**Date:** 2026-06-26  
**Status:** Approved  
**Reviewer:** Human scope audit  
**Related:** PR1, PR2, Milestone 1 First Contact, [ark-voice-endpoint-architecture-v1.md](../../communications/ark-voice-endpoint-architecture-v1.md)

## Reason

PR1 delivers schema, authority, and projection persistence. PR2 delivers execution (regenerate, Poly builder, GET serve). Policy enums (`EndpointProvisionBuilder`, `EndpointProvisionFormat`) belong to PR1 because they are **schema vocabulary** on `communication_device_models` and `endpoint_configuration_projections` — not execution scaffolding.

Execution classes (`ProvisionBuilder`, `PolyProvisionBuilder`, `RegenerateEndpointConfigurationAction`, `InvalidateEndpointConfigurationAction`) belong to PR2 because nothing in PR1 calls them.

## PR1 scope (committed)

- CommunicationDevice hardware identity fields (MAC, model FK, firmware, `is_active`)
- `communication_device_models` table + seeder
- `endpoint_configuration_projections` table + Eloquent read model (immutable history via `superseded_at`)
- `CommunicationDeviceMacAddress`, policy enums
- `telephony_extensions.workstation_id`, `communication_device_id`, `secret`
- `AssignExtensionToWorkstationAction` (telephony authority)
- Migration reorder: `2026_06_26_140001` → `2026_06_30_105000` (documented)

## PR2 scope (approved)

- `RegenerateEndpointConfigurationAction` / `InvalidateEndpointConfigurationAction`
- `ProvisionBuilder` → `PolyProvisionBuilder`
- `GET /provision/{mac}.cfg` with serve gates
- Structured `endpoint.provision.request` logging
- GET path is read-only (no `ensureMicrobrowserToken` on serve)

## PR3A scope (in progress)

Observability only — MAC, model, provision URL, projection fingerprint/timestamp, admin projection body preview. No assignment UX.

## Pre-existing debt

Tracked in [TECHNICAL_DEBT.md](../TECHNICAL_DEBT.md). Do not build on legacy `CommunicationDeviceProvisionConfigBuilder` or `assigned_user_id` identity.

## Doctrine alignment

| Area | Verdict |
|------|---------|
| Workstation owns business identity | ✅ |
| GET provision read-only | ✅ |
| Projection invalidation on authority writes | ✅ |
| Immutable projection history | ✅ (`superseded_at`) |
| MAC = identity, gates = authorization | ✅ |
