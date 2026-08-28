# Engineering Roadmap

High-level engineering milestones for ARK Voice endpoint infrastructure. No implementation details — see [CURRENT_MILESTONE.md](CURRENT_MILESTONE.md) and [ACTIVE_PR.md](ACTIVE_PR.md) for active work.

---

## Phase 1 — Device Identity

Establish the authority layer for communication devices and workstations. Hardware identity lives on devices; business identity lives on workstations and telephony extensions. Schema, models, and the provisioning namespace scaffold so future work has a single truth source.

---

## Phase 2 — Endpoint Management

Operational surfaces and actions for registering, linking, and lifecycle-managing communication devices against workstations. Device appears in ARK, links to workstation, and is ready for configuration projection — without manual firmware or vendor tooling.

---

## Phase 3 — Telephony Projection

Wire endpoint configuration projection to telephony authority. Known devices receive generated provisioning output from `EndpointConfigurationProjection` via vendor builders. Asterisk and SIP registration consume projections; they do not own identity.

---

## Phase 4 — Communications Engine

Full shop voice fabric: call routing, BLF, paging, presence, and transport integration — all reading from ARK authority and projecting onto Asterisk and devices. Built only after Phases 1–3 prove the authority/projection boundary on the floor.
