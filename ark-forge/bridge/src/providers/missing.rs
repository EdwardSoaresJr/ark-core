use serde_json::{json, Value};

use crate::forge::ForgeInvokeResponse;

/// Fallback when a namespace has no registered runtime provider.
pub struct MissingProvider;

impl MissingProvider {
    pub fn known_namespaces() -> &'static [&'static str] {
        &["cursor"]
    }

    pub fn is_known_namespace(namespace: &str) -> bool {
        Self::known_namespaces().contains(&namespace)
    }

    pub fn runtime_label(namespace: &str) -> &'static str {
        match namespace {
            "cursor" => "Cursor Runtime Provider",
            _ => "Runtime Provider",
        }
    }

    pub fn unavailable_reason(namespace: &str) -> String {
        format!(
            "{} is not connected.",
            Self::runtime_label(namespace)
        )
    }

    pub fn unavailable_capabilities(namespace: &str) -> Vec<Value> {
        match namespace {
            "cursor" => cursor_unavailable_capabilities(),
            _ => vec![],
        }
    }

    pub fn unavailable_observe(
        namespace: &str,
        capability_id: &str,
    ) -> ForgeInvokeResponse {
        ForgeInvokeResponse {
            ok: true,
            capability_id: capability_id.to_string(),
            action: None,
            result: Some(json!({
                "available": false,
                "reason": Self::unavailable_reason(namespace),
            })),
        }
    }

    pub fn handles_capability(capability_id: &str) -> Option<String> {
        let namespace = capability_id.split('.').next()?;
        if Self::is_known_namespace(namespace) {
            Some(namespace.to_string())
        } else {
            None
        }
    }
}

fn cursor_unavailable_capabilities() -> Vec<Value> {
    vec![
        unavailable_capability(
            "cursor.workspace.folders.read",
            "Workspace Folders",
            "Read workspace folder roots visible to Cursor.",
            "workspace",
            "folders",
            "read",
        ),
        unavailable_capability(
            "cursor.editor.active.read",
            "Active Editor",
            "Read the currently focused editor file and language.",
            "editor",
            "active",
            "read",
        ),
        unavailable_capability(
            "cursor.editor.selection.read",
            "Editor Selection",
            "Read the current text selection in the active editor.",
            "editor",
            "selection",
            "read",
        ),
    ]
}

fn unavailable_capability(
    id: &str,
    display_name: &str,
    description: &str,
    domain: &str,
    resource: &str,
    operation: &str,
) -> Value {
    json!({
        "id": id,
        "display_name": display_name,
        "description": description,
        "domain": domain,
        "resource": resource,
        "operation": operation,
        "mode": "observe",
        "version": 1,
        "stability": "experimental",
        "permissions": [],
        "arguments": [],
        "result": {
            "type": "object",
            "description": "Unavailable until the runtime provider connects."
        },
        "available": false,
        "provider": "cursor"
    })
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn cursor_selection_unavailable_when_not_connected() {
        let response = MissingProvider::unavailable_observe(
            "cursor",
            "cursor.editor.selection.read",
        );
        assert!(response.ok);
        let result = response.result.expect("result");
        assert_eq!(result["available"], false);
        assert!(result["reason"]
            .as_str()
            .unwrap()
            .contains("Cursor Runtime Provider"));
    }

    #[test]
    fn exposes_three_cursor_capabilities_as_unavailable() {
        let caps = MissingProvider::unavailable_capabilities("cursor");
        assert_eq!(caps.len(), 3);
        assert!(caps.iter().all(|cap| cap["available"] == false));
    }
}
