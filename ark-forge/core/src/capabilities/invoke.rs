use std::path::Path;
use std::process::Command;

use anyhow::{Context, Result, bail};
use serde::{Deserialize, Serialize};

use super::browser;
use super::filesystem;
use super::git;
use super::identity::validate_capability_id;
use super::observe;
use super::processes;
use super::registry::REGISTRY_CAPABILITY_ID;
use super::registry::CapabilityRegistry;
use super::verbs::validate_action;

#[derive(Debug, Deserialize)]
pub struct InvokeRequest {
    /// v0.1 transitional — forbidden when `capability_id` uses ADR-0001 identity format.
    #[serde(default)]
    pub action: Option<String>,
    #[serde(default)]
    pub path: Option<String>,
    #[serde(default)]
    pub url: Option<String>,
    #[serde(default)]
    pub application: Option<String>,
    #[serde(default)]
    pub command: Option<String>,
}

#[derive(Debug, Serialize)]
pub struct InvokeResponse {
    pub ok: bool,
    pub capability_id: String,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub action: Option<String>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub result: Option<serde_json::Value>,
}

pub fn invoke(
    repo_path: &Path,
    capability_id: &str,
    request: InvokeRequest,
) -> Result<InvokeResponse> {
    if capability_id.starts_with("forge.") {
        validate_capability_id(capability_id).map_err(|e| anyhow::anyhow!(e))?;
        if request.action.is_some() {
            bail!("capability identity invoke does not accept action; pass typed arguments only");
        }
        return invoke_by_identity(repo_path, capability_id, request);
    }

    invoke_legacy(repo_path, capability_id, request)
}

fn invoke_by_identity(
    repo_path: &Path,
    capability_id: &str,
    request: InvokeRequest,
) -> Result<InvokeResponse> {
    let result = match capability_id {
        REGISTRY_CAPABILITY_ID => Some(serde_json::to_value(CapabilityRegistry::build())?),
        "forge.filesystem.file.read" => {
            let rel = request
                .path
                .as_deref()
                .context("path argument required")?;
            let content = filesystem::read(&repo_path.join(rel))?;
            Some(serde_json::json!({ "bytes": content.len() }))
        }
        "forge.git.status.read" => {
            let path = request
                .path
                .as_deref()
                .map(Path::new)
                .unwrap_or(repo_path);
            let snapshot = git::observe(path)?;
            Some(serde_json::to_value(snapshot)?)
        }
        "forge.processes.list.read" => Some(serde_json::json!({
            "cursor_running": processes::cursor_running(),
            "observed_at": observe::observed_at(),
        })),
        "forge.browser.url.open" => {
            let url = request.url.as_deref().context("url argument required")?;
            browser::open_url(url)?;
            None
        }
        "forge.shell.application.open" => {
            let app = request
                .application
                .as_deref()
                .context("application argument required")?;
            let path = request.path.as_deref().map(Path::new).unwrap_or(repo_path);
            open_application(app, path)?;
            None
        }
        "forge.shell.command.run" => {
            let command = request
                .command
                .as_deref()
                .context("command argument required")?;
            run_command(command)?;
            None
        }
        "forge.shell.session.open" => {
            bail!("capability '{capability_id}' is not implemented")
        }
        _ => bail!("capability '{capability_id}' is not registered for invoke/observe"),
    };

    Ok(InvokeResponse {
        ok: true,
        capability_id: capability_id.to_string(),
        action: None,
        result,
    })
}

fn invoke_legacy(
    repo_path: &Path,
    capability_id: &str,
    request: InvokeRequest,
) -> Result<InvokeResponse> {
    let action = request
        .action
        .as_deref()
        .context("legacy invoke requires action")?;
    validate_action(action).map_err(|e| anyhow::anyhow!(e))?;

    let mapped_id = legacy_capability_id(capability_id, action);
    let result = match (capability_id, action) {
        ("filesystem", "read_file") => {
            let rel = request
                .path
                .as_deref()
                .context("read_file requires path")?;
            let content = filesystem::read(&repo_path.join(rel))?;
            Some(serde_json::json!({ "bytes": content.len() }))
        }
        ("git", "git_status") => {
            let path = request.path.as_deref().map(Path::new).unwrap_or(repo_path);
            let snapshot = git::observe(path)?;
            Some(serde_json::to_value(snapshot)?)
        }
        ("processes", "list_processes") => Some(serde_json::json!({
            "cursor_running": processes::cursor_running(),
        })),
        ("browser", "open_url") => {
            let url = request.url.as_deref().context("open_url requires url")?;
            browser::open_url(url)?;
            None
        }
        ("shell", "open_application") => {
            let app = request
                .application
                .as_deref()
                .context("open_application requires application")?;
            let path = request.path.as_deref().map(Path::new).unwrap_or(repo_path);
            open_application(app, path)?;
            None
        }
        ("shell", "run_command") => {
            let command = request
                .command
                .as_deref()
                .context("run_command requires command")?;
            run_command(command)?;
            None
        }
        _ => bail!("capability '{capability_id}' does not support action '{action}'"),
    };

    Ok(InvokeResponse {
        ok: true,
        capability_id: mapped_id,
        action: Some(action.to_string()),
        result,
    })
}

fn legacy_capability_id(domain: &str, action: &str) -> String {
    match (domain, action) {
        ("filesystem", "read_file") => "forge.filesystem.file.read".into(),
        ("git", "git_status") => "forge.git.status.read".into(),
        ("processes", "list_processes") => "forge.processes.list.read".into(),
        ("browser", "open_url") => "forge.browser.url.open".into(),
        ("shell", "open_application") => "forge.shell.application.open".into(),
        ("shell", "run_command") => "forge.shell.command.run".into(),
        _ => domain.to_string(),
    }
}

fn open_application(application: &str, path: &Path) -> Result<()> {
    let command = match application {
        "cursor" => {
            if cfg!(windows) {
                format!("cursor \"{}\"", path.display())
            } else {
                format!("cursor '{}'", path.display())
            }
        }
        other => bail!("unsupported application '{other}' — Workbench supplies generic name only"),
    };
    spawn_shell(&command)
}

fn run_command(command: &str) -> Result<()> {
    spawn_shell(command)
}

fn spawn_shell(command: &str) -> Result<()> {
    if cfg!(windows) {
        Command::new("cmd").args(["/C", command]).spawn()?;
    } else {
        Command::new("sh").args(["-c", command]).spawn()?;
    }
    Ok(())
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::path::Path;

    #[test]
    fn identity_invoke_rejects_action_field() {
        let err = invoke(
            Path::new("."),
            "forge.shell.application.open",
            InvokeRequest {
                action: Some("open_application".into()),
                path: None,
                url: None,
                application: Some("cursor".into()),
                command: None,
            },
        )
        .unwrap_err();

        assert!(err.to_string().contains("does not accept action"));
    }

    #[test]
    fn git_status_read_returns_observed_at() {
        let repo = Path::new(env!("CARGO_MANIFEST_DIR"))
            .parent()
            .unwrap()
            .parent()
            .unwrap();
        let response = invoke(
            repo,
            "forge.git.status.read",
            InvokeRequest {
                action: None,
                path: None,
                url: None,
                application: None,
                command: None,
            },
        )
        .expect("git status observe");

        let result = response.result.expect("observe result");
        assert!(result.get("observed_at").and_then(|v| v.as_str()).is_some());
        assert!(result.get("branch").and_then(|v| v.as_str()).is_some());
        assert!(result.get("dirty").and_then(|v| v.as_bool()).is_some());
    }

    #[test]
    fn browser_url_open_requires_url() {
        let err = invoke(
            Path::new("."),
            "forge.browser.url.open",
            InvokeRequest {
                action: None,
                path: None,
                url: None,
                application: None,
                command: None,
            },
        )
        .unwrap_err();

        assert!(err.to_string().contains("url"));
    }
}
