//! Capability graph — Forge Core authority surface.
//! Generic verbs only. No product domain.

pub mod browser;
pub mod filesystem;
pub mod git;
pub mod identity;
pub mod invoke;
pub mod observe;
pub mod processes;
pub mod registry;
pub mod verbs;

pub use git::GitStatusSnapshot;
pub use identity::CapabilityIdentity;
pub use invoke::{InvokeRequest, InvokeResponse};
pub use registry::{CapabilityRegistry, REGISTRY_CAPABILITY_ID};
