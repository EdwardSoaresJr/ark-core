use serde::Serialize;

use super::git;
use super::identity::{
    CapabilityArgument, CapabilityIdentity, CapabilityMode, CapabilityResultSchema,
    CapabilityStability, validate_capability_id,
};

pub const REGISTRY_CAPABILITY_ID: &str = "forge.capability.registry.read";

#[derive(Debug, Serialize)]
pub struct CapabilityRegistry {
    pub registry_capability_id: String,
    pub capabilities: Vec<CapabilityIdentity>,
}

impl CapabilityRegistry {
    pub fn build() -> Self {
        let capabilities = catalog();
        for capability in &capabilities {
            debug_assert!(
                validate_capability_id(&capability.id).is_ok(),
                "invalid capability id: {}",
                capability.id
            );
        }

        Self {
            registry_capability_id: REGISTRY_CAPABILITY_ID.into(),
            capabilities,
        }
    }
}

fn catalog() -> Vec<CapabilityIdentity> {
    vec![
        registry_read_capability(),
        invoke_capability(
            "forge.filesystem.file.read",
            "Read File",
            "Read file contents at a path relative to the repository root.",
            "filesystem",
            "file",
            "read",
            vec![CapabilityArgument {
                name: "path".into(),
                arg_type: "file".into(),
                required: true,
                enum_values: None,
                description: Some("Path relative to repository root".into()),
            }],
            vec!["filesystem.read".into()],
            true,
        ),
        observe_capability(
            "forge.git.status.read",
            "Git Status",
            "Read branch, clean/dirty, and ahead/behind for a repository.",
            "git",
            "status",
            "read",
            vec![CapabilityArgument {
                name: "repo_path".into(),
                arg_type: "directory".into(),
                required: false,
                enum_values: None,
                description: Some("Repository path; defaults to configured repo".into()),
            }],
            vec!["filesystem.read".into()],
            git::tool_available("git"),
            CapabilityResultSchema {
                result_type: "object".into(),
                description: "Branch, clean/dirty, ahead/behind".into(),
            },
        ),
        observe_capability(
            "forge.processes.list.read",
            "Process List",
            "Observe selected workstation processes.",
            "processes",
            "list",
            "read",
            vec![],
            vec![],
            true,
            CapabilityResultSchema {
                result_type: "object".into(),
                description: "Process presence signals (e.g. cursor_running)".into(),
            },
        ),
        invoke_capability(
            "forge.browser.url.open",
            "Open URL",
            "Open a URL in the system default browser.",
            "browser",
            "url",
            "open",
            vec![CapabilityArgument {
                name: "url".into(),
                arg_type: "url".into(),
                required: true,
                enum_values: None,
                description: None,
            }],
            vec!["network".into()],
            true,
        ),
        invoke_capability(
            "forge.shell.application.open",
            "Open Application",
            "Launch an application with an optional working path.",
            "shell",
            "application",
            "open",
            vec![
                CapabilityArgument {
                    name: "application".into(),
                    arg_type: "enum".into(),
                    required: true,
                    enum_values: Some(vec!["cursor".into()]),
                    description: None,
                },
                CapabilityArgument {
                    name: "path".into(),
                    arg_type: "directory".into(),
                    required: false,
                    enum_values: None,
                    description: None,
                },
            ],
            vec!["process.spawn".into()],
            true,
        ),
        invoke_capability(
            "forge.shell.command.run",
            "Run Command",
            "Execute a shell command on the workstation.",
            "shell",
            "command",
            "run",
            vec![CapabilityArgument {
                name: "command".into(),
                arg_type: "string".into(),
                required: true,
                enum_values: None,
                description: None,
            }],
            vec!["process.spawn".into()],
            true,
        ),
        CapabilityIdentity {
            id: "forge.shell.session.open".into(),
            display_name: "Open Shell Session".into(),
            description: Some(
                "Open or focus a terminal session scoped to a project. Not implemented.".into(),
            ),
            domain: "shell".into(),
            resource: "session".into(),
            operation: "open".into(),
            mode: CapabilityMode::Invoke,
            version: 1,
            stability: CapabilityStability::Experimental,
            permissions: vec!["process.spawn".into()],
            arguments: vec![CapabilityArgument {
                name: "path".into(),
                arg_type: "directory".into(),
                required: false,
                enum_values: None,
                description: None,
            }],
            result: None,
            available: false,
        },
    ]
}

fn registry_read_capability() -> CapabilityIdentity {
    observe_capability(
        REGISTRY_CAPABILITY_ID,
        "Capability Registry",
        "Describe all capability identities Core exposes on this workstation.",
        "capability",
        "registry",
        "read",
        vec![],
        vec![],
        true,
        CapabilityResultSchema {
            result_type: "object".into(),
            description: "Full capability registry catalog".into(),
        },
    )
}

fn invoke_capability(
    id: &str,
    display_name: &str,
    description: &str,
    domain: &str,
    resource: &str,
    operation: &str,
    arguments: Vec<CapabilityArgument>,
    permissions: Vec<String>,
    available: bool,
) -> CapabilityIdentity {
    CapabilityIdentity {
        id: id.into(),
        display_name: display_name.into(),
        description: Some(description.into()),
        domain: domain.into(),
        resource: resource.into(),
        operation: operation.into(),
        mode: CapabilityMode::Invoke,
        version: 1,
        stability: CapabilityStability::Stable,
        permissions,
        arguments,
        result: None,
        available,
    }
}

fn observe_capability(
    id: &str,
    display_name: &str,
    description: &str,
    domain: &str,
    resource: &str,
    operation: &str,
    arguments: Vec<CapabilityArgument>,
    permissions: Vec<String>,
    available: bool,
    result: CapabilityResultSchema,
) -> CapabilityIdentity {
    CapabilityIdentity {
        id: id.into(),
        display_name: display_name.into(),
        description: Some(description.into()),
        domain: domain.into(),
        resource: resource.into(),
        operation: operation.into(),
        mode: CapabilityMode::Observe,
        version: 1,
        stability: CapabilityStability::Stable,
        permissions,
        arguments,
        result: Some(result),
        available,
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn all_capability_ids_use_hierarchical_format() {
        let registry = CapabilityRegistry::build();
        assert_eq!(registry.registry_capability_id, REGISTRY_CAPABILITY_ID);

        for capability in &registry.capabilities {
            assert!(
                validate_capability_id(&capability.id).is_ok(),
                "invalid id: {}",
                capability.id
            );
        }
    }

    #[test]
    fn registry_includes_itself() {
        let registry = CapabilityRegistry::build();
        assert!(registry
            .capabilities
            .iter()
            .any(|c| c.id == REGISTRY_CAPABILITY_ID));
    }

    #[test]
    fn invoke_and_observe_modes_are_present() {
        let registry = CapabilityRegistry::build();
        assert!(registry
            .capabilities
            .iter()
            .any(|c| c.mode == CapabilityMode::Invoke));
        assert!(registry
            .capabilities
            .iter()
            .any(|c| c.mode == CapabilityMode::Observe));
    }
}
