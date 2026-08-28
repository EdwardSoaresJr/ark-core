use std::sync::Arc;

use axum::extract::ConnectInfo;
use axum::http::{header, StatusCode};
use axum::response::{Html, IntoResponse, Response};
use axum::extract::State;
use serde::{Deserialize, Serialize};

use crate::api::AppState;
use crate::config::BridgeConfig;
use crate::log;
use crate::runtime::ConnectedClient;

const BRIDGE_VERSION: &str = env!("CARGO_PKG_VERSION");
const FORGE_CORE_VERSION: &str = "0.1.0";

#[derive(Debug, Serialize, Deserialize)]
pub struct DiagnosticsSnapshot {
    pub bridge: String,
    pub forge_core: String,
    pub repo: String,
    pub capabilities: usize,
    pub last_error: String,
    pub bridge_version: String,
    pub forge_version: String,
    pub bridge_id: String,
    pub client: Option<ConnectedClient>,
    pub forge_repo_path: Option<String>,
    pub logs_dir: String,
    pub cursor_runtime: String,
}

pub async fn gather_snapshot(state: &AppState) -> DiagnosticsSnapshot {
    let forge_ok = state.forge.health().await.unwrap_or(false);
    let repo_path = state.config.resolved_repo_path();
    let repo = match (&repo_path, forge_ok) {
        (None, _) => "Not configured".into(),
        (Some(_), false) => "Forge Core offline".into(),
        (Some(_), true) => match state.forge.engineering_state().await {
            Ok(_) => "Connected".into(),
            Err(error) => {
                state.runtime.set_error(error.to_string());
                "Unreachable".into()
            }
        },
    };

    if repo == "Connected" {
        state.runtime.clear_error();
    }

    let capabilities = state.providers.merged_capability_count().await;
    let cursor_runtime = cursor_runtime_status(&state).await;

    DiagnosticsSnapshot {
        bridge: "Running".into(),
        forge_core: if forge_ok {
            "Running".into()
        } else {
            "Offline".into()
        },
        repo,
        capabilities,
        last_error: state
            .runtime
            .last_error()
            .unwrap_or_else(|| "None".into()),
        bridge_version: BRIDGE_VERSION.into(),
        forge_version: FORGE_CORE_VERSION.into(),
        bridge_id: state.config.bridge_id.clone(),
        client: state.runtime.connected_client(),
        forge_repo_path: repo_path.map(|path| path.display().to_string()),
        logs_dir: log::log_dir().display().to_string(),
        cursor_runtime,
    }
}

async fn cursor_runtime_status(state: &AppState) -> String {
    let descriptors = state.providers.provider_descriptors().await;
    match descriptors.iter().find(|provider| provider.id == "cursor") {
        Some(provider) if provider.healthy => "Connected".into(),
        Some(_) => "Offline".into(),
        None => "Not registered".into(),
    }
}

pub fn format_diagnostics_text(snapshot: &DiagnosticsSnapshot) -> String {
    let client_block = match &snapshot.client {
        Some(client) => format!(
            "Client: {}\nConnected since: {}\nClient version: {}",
            client.name,
            client.connected_at,
            client.version.as_deref().unwrap_or("unknown")
        ),
        None => "Client: None".into(),
    };

    format!(
        "ARK Bridge Diagnostics\n\
         ======================\n\
         Bridge: {}\n\
         Forge Core: {}\n\
         Repo: {}\n\
         Capabilities: {}\n\
         Cursor Runtime: {}\n\
         Last Error: {}\n\
         Bridge Version: {}\n\
         Forge Version: {}\n\
         Bridge ID: {}\n\
         {client_block}\n\
         Repo path: {}\n\
         Logs: {}",
        snapshot.bridge,
        snapshot.forge_core,
        snapshot.repo,
        snapshot.capabilities,
        snapshot.cursor_runtime,
        snapshot.last_error,
        snapshot.bridge_version,
        snapshot.forge_version,
        snapshot.bridge_id,
        snapshot
            .forge_repo_path
            .as_deref()
            .unwrap_or("(not set)"),
        snapshot.logs_dir,
    )
}

pub async fn diagnostics_json(
    State(state): State<Arc<AppState>>,
    ConnectInfo(addr): ConnectInfo<std::net::SocketAddr>,
) -> Result<axum::Json<DiagnosticsSnapshot>, StatusCode> {
    if !is_localhost(addr) {
        return Err(StatusCode::FORBIDDEN);
    }
    Ok(axum::Json(gather_snapshot(&state).await))
}

pub async fn diagnostics_html(
    State(state): State<Arc<AppState>>,
    ConnectInfo(addr): ConnectInfo<std::net::SocketAddr>,
) -> Result<Html<String>, StatusCode> {
    if !is_localhost(addr) {
        return Err(StatusCode::FORBIDDEN);
    }

    let snapshot = gather_snapshot(&state).await;
    Ok(Html(render_html(&snapshot)))
}

pub async fn diagnostics_copy(
    State(state): State<Arc<AppState>>,
    ConnectInfo(addr): ConnectInfo<std::net::SocketAddr>,
) -> Result<Response, StatusCode> {
    if !is_localhost(addr) {
        return Err(StatusCode::FORBIDDEN);
    }

    let snapshot = gather_snapshot(&state).await;
    let body = format_diagnostics_text(&snapshot);
    Ok((
        [(header::CONTENT_TYPE, "text/plain; charset=utf-8")],
        body,
    )
        .into_response())
}

pub async fn diagnostics_open_logs(
    ConnectInfo(addr): ConnectInfo<std::net::SocketAddr>,
) -> Result<&'static str, StatusCode> {
    if !is_localhost(addr) {
        return Err(StatusCode::FORBIDDEN);
    }
    log::open_logs_in_explorer();
    Ok("ok")
}

pub fn open_diagnostics_window(config: &BridgeConfig) {
    let url = format!(
        "http://{}:{}/bridge/diagnostics",
        config.listen_host, config.listen_port
    );

    #[cfg(windows)]
    {
        let _ = std::process::Command::new("cmd")
            .args(["/C", "start", "", &url])
            .spawn();
    }

    #[cfg(not(windows))]
    {
        let _ = std::process::Command::new("xdg-open").arg(&url).spawn();
    }
}

fn is_localhost(addr: std::net::SocketAddr) -> bool {
    addr.ip().is_loopback()
}

fn render_html(snapshot: &DiagnosticsSnapshot) -> String {
    let client_name = snapshot
        .client
        .as_ref()
        .map(|client| client.name.as_str())
        .unwrap_or("None");
    let client_since = snapshot
        .client
        .as_ref()
        .map(|client| client.connected_at.as_str())
        .unwrap_or("—");
    let client_version = snapshot
        .client
        .as_ref()
        .and_then(|client| client.version.as_deref())
        .unwrap_or("—");
    let repo_path = snapshot
        .forge_repo_path
        .as_deref()
        .unwrap_or("(not set — open Settings in the tray menu)");

    format!(
        r#"<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>ARK Bridge Status</title>
  <style>
    body {{ font-family: Segoe UI, system-ui, sans-serif; background: #0f1419; color: #e7ecf1; margin: 0; padding: 32px; }}
    .card {{ max-width: 520px; background: #171d24; border: 1px solid #2a3440; border-radius: 8px; padding: 24px; }}
    h1 {{ font-size: 20px; margin: 0 0 4px; }}
    .subtitle {{ color: #8b98a5; font-size: 13px; margin-bottom: 20px; }}
    dl {{ display: grid; grid-template-columns: 140px 1fr; gap: 10px 16px; margin: 0; }}
    dt {{ color: #8b98a5; font-size: 13px; }}
    dd {{ margin: 0; font-size: 14px; }}
    .ok {{ color: #3dd68c; }}
    .warn {{ color: #f5a524; }}
    .actions {{ margin-top: 24px; display: flex; gap: 10px; }}
    button {{ background: #0099cc; color: white; border: 0; border-radius: 6px; padding: 10px 16px; font-size: 13px; cursor: pointer; }}
    button.secondary {{ background: #2a3440; }}
    #copy-status {{ color: #8b98a5; font-size: 12px; margin-top: 10px; min-height: 16px; }}
    .help {{ margin-top: 20px; color: #8b98a5; font-size: 12px; line-height: 1.5; }}
  </style>
</head>
<body>
  <div class="card">
    <h1>Bridge Status</h1>
    <div class="subtitle">ARK Bridge diagnostics — localhost only</div>
    <dl>
      <dt>Bridge</dt><dd class="ok">{bridge}</dd>
      <dt>Forge Core</dt><dd class="{forge_class}">{forge_core}</dd>
      <dt>Repo</dt><dd class="{repo_class}">{repo}</dd>
      <dt>Capabilities</dt><dd>{capabilities}</dd>
      <dt>Cursor Runtime</dt><dd class="{cursor_class}">{cursor_runtime}</dd>
      <dt>Last Error</dt><dd>{last_error}</dd>
      <dt>Bridge Version</dt><dd>{bridge_version}</dd>
      <dt>Forge Version</dt><dd>{forge_version}</dd>
      <dt>Bridge ID</dt><dd>{bridge_id_short}…</dd>
      <dt>Client</dt><dd>{client_name}</dd>
      <dt>Connected since</dt><dd>{client_since}</dd>
      <dt>Client version</dt><dd>{client_version}</dd>
      <dt>Repo path</dt><dd style="word-break:break-all">{repo_path}</dd>
    </dl>
    <div class="actions">
      <button type="button" onclick="openLogs()">Open Logs</button>
      <button type="button" class="secondary" onclick="copyDiagnostics()">Copy Diagnostics</button>
    </div>
    <div id="copy-status"></div>
    <div class="help">
      First time? Set <code>forge_repo_path</code> in the tray Settings file, save, then restart ARK Bridge.
      Clients identify with the <code>X-ARK-Client</code> header when connecting.
    </div>
  </div>
  <script>
    const diagnosticsText = {diagnostics_text_json};
    async function openLogs() {{
      await fetch('/bridge/diagnostics/open-logs', {{ method: 'POST' }});
    }}
    async function copyDiagnostics() {{
      const response = await fetch('/bridge/diagnostics/copy');
      const text = await response.text();
      await navigator.clipboard.writeText(text);
      document.getElementById('copy-status').textContent = 'Diagnostics copied to clipboard.';
    }}
  </script>
</body>
</html>"#,
        bridge = snapshot.bridge,
        forge_core = snapshot.forge_core,
        forge_class = if snapshot.forge_core == "Running" {
            "ok"
        } else {
            "warn"
        },
        repo = snapshot.repo,
        repo_class = if snapshot.repo == "Connected" {
            "ok"
        } else {
            "warn"
        },
        capabilities = snapshot.capabilities,
        cursor_runtime = snapshot.cursor_runtime,
        cursor_class = if snapshot.cursor_runtime == "Connected" {
            "ok"
        } else {
            "warn"
        },
        last_error = snapshot.last_error,
        bridge_version = snapshot.bridge_version,
        forge_version = snapshot.forge_version,
        bridge_id_short = snapshot.bridge_id.chars().take(8).collect::<String>(),
        client_name = client_name,
        client_since = client_since,
        client_version = client_version,
        repo_path = html_escape(repo_path),
        diagnostics_text_json = serde_json::to_string(&format_diagnostics_text(snapshot))
            .unwrap_or_default(),
    )
}

fn html_escape(value: &str) -> String {
    value
        .replace('&', "&amp;")
        .replace('<', "&lt;")
        .replace('>', "&gt;")
        .replace('"', "&quot;")
}
