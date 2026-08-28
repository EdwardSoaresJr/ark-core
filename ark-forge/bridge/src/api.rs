use std::collections::HashMap;
use std::sync::Arc;

use axum::extract::ws::{Message, WebSocket, WebSocketUpgrade};
use axum::extract::State;
use axum::http::{HeaderMap, StatusCode};
use axum::response::IntoResponse;
use axum::routing::{get, post};
use axum::{Json, Router};
use serde::{Deserialize, Serialize};
use serde_json::{json, Value};
use tokio::time::{interval, Duration};

use crate::auth;
use crate::config::BridgeConfig;
use crate::diagnostics;
use crate::forge::ForgeClient;
use crate::providers::{ProviderRegistry, RegisterProviderRequest};
use crate::runtime;

const BRIDGE_VERSION: &str = env!("CARGO_PKG_VERSION");

#[derive(Clone)]
pub struct AppState {
    pub config: BridgeConfig,
    pub forge: ForgeClient,
    pub providers: ProviderRegistry,
    pub runtime: Arc<runtime::BridgeRuntime>,
}

pub fn routes(state: Arc<AppState>) -> Router {
    Router::new()
        .route("/bridge/ping", get(ping))
        .route("/bridge/version", get(version))
        .route("/bridge/state", get(state_handler))
        .route("/bridge/providers", get(providers_handler))
        .route("/bridge/providers/register", post(register_provider_handler))
        .route("/bridge/invoke", post(invoke_handler))
        .route("/bridge/observe", post(observe_handler))
        .route("/bridge/events", get(events_ws))
        .route("/bridge/diagnostics", get(diagnostics::diagnostics_html))
        .route("/bridge/diagnostics.json", get(diagnostics::diagnostics_json))
        .route("/bridge/diagnostics/copy", get(diagnostics::diagnostics_copy))
        .route(
            "/bridge/diagnostics/open-logs",
            post(diagnostics::diagnostics_open_logs),
        )
        .with_state(state)
}

async fn ping(
    State(state): State<Arc<AppState>>,
    headers: HeaderMap,
) -> Result<Json<Value>, (StatusCode, String)> {
    auth::authorize(&headers, &state.config)
        .map_err(|_| (StatusCode::UNAUTHORIZED, "unauthorized".into()))?;
    runtime::touch_client(&state, &headers);
    Ok(Json(json!({ "ok": true, "bridge": "ark-bridge" })))
}

async fn version(
    State(state): State<Arc<AppState>>,
    headers: HeaderMap,
) -> Result<Json<Value>, (StatusCode, String)> {
    auth::authorize(&headers, &state.config)
        .map_err(|_| (StatusCode::UNAUTHORIZED, "unauthorized".into()))?;
    runtime::touch_client(&state, &headers);
    Ok(Json(json!({
        "bridge_version": BRIDGE_VERSION,
        "bridge_id": state.config.bridge_id,
    })))
}

async fn state_handler(
    State(state): State<Arc<AppState>>,
    headers: HeaderMap,
) -> Result<Json<Value>, (StatusCode, String)> {
    auth::authorize(&headers, &state.config)
        .map_err(|_| (StatusCode::UNAUTHORIZED, "unauthorized".into()))?;
    runtime::touch_client(&state, &headers);

    let forge_available = state.forge.health().await.unwrap_or(false);
    let providers = state.providers.provider_statuses().await;
    let capabilities = state
        .providers
        .merged_capabilities()
        .await
        .map_err(|e| (StatusCode::BAD_GATEWAY, e.to_string()))?;

    let client = state.runtime.connected_client();

    Ok(Json(json!({
        "bridge_id": state.config.bridge_id,
        "bridge_version": BRIDGE_VERSION,
        "forge_core_url": state.config.forge_core_url,
        "forge_available": forge_available,
        "healthy": forge_available || providers.iter().any(|p| p.healthy),
        "providers": providers,
        "machine_name": hostname::get()
            .ok()
            .and_then(|h| h.into_string().ok())
            .unwrap_or_else(|| "unknown".into()),
        "capabilities": capabilities,
        "client": client.as_ref().map(|entry| json!({
            "name": entry.name,
            "version": entry.version,
            "connected_at": entry.connected_at,
        })),
    })))
}

async fn providers_handler(
    State(state): State<Arc<AppState>>,
    headers: HeaderMap,
) -> Result<Json<Vec<crate::providers::ProviderDescriptor>>, (StatusCode, String)> {
    auth::authorize(&headers, &state.config)
        .map_err(|_| (StatusCode::UNAUTHORIZED, "unauthorized".into()))?;
    runtime::touch_client(&state, &headers);

    Ok(Json(state.providers.provider_descriptors().await))
}

async fn register_provider_handler(
    State(state): State<Arc<AppState>>,
    headers: HeaderMap,
    Json(body): Json<RegisterProviderRequest>,
) -> Result<Json<Value>, (StatusCode, String)> {
    auth::authorize(&headers, &state.config)
        .map_err(|_| (StatusCode::UNAUTHORIZED, "unauthorized".into()))?;
    runtime::touch_client(&state, &headers);

    state
        .providers
        .register_provider(body)
        .await
        .map_err(|error| (StatusCode::BAD_GATEWAY, error.to_string()))?;

    Ok(Json(json!({ "ok": true })))
}

#[derive(Debug, Deserialize)]
struct BridgeCapabilityRequest {
    capability: String,
    #[serde(default)]
    arguments: HashMap<String, Value>,
}

async fn invoke_handler(
    State(state): State<Arc<AppState>>,
    headers: HeaderMap,
    Json(body): Json<BridgeCapabilityRequest>,
) -> Result<Json<ForgeBridgeResponse>, (StatusCode, String)> {
    auth::authorize(&headers, &state.config)
        .map_err(|_| (StatusCode::UNAUTHORIZED, "unauthorized".into()))?;
    runtime::touch_client(&state, &headers);

    forward(&state, body).await
}

async fn observe_handler(
    State(state): State<Arc<AppState>>,
    headers: HeaderMap,
    Json(body): Json<BridgeCapabilityRequest>,
) -> Result<Json<ForgeBridgeResponse>, (StatusCode, String)> {
    auth::authorize(&headers, &state.config)
        .map_err(|_| (StatusCode::UNAUTHORIZED, "unauthorized".into()))?;
    runtime::touch_client(&state, &headers);

    forward(&state, body).await
}

#[derive(Debug, Serialize)]
struct ForgeBridgeResponse {
    ok: bool,
    capability: String,
    result: Option<Value>,
}

async fn forward(
    state: &AppState,
    body: BridgeCapabilityRequest,
) -> Result<Json<ForgeBridgeResponse>, (StatusCode, String)> {
    let response = state
        .providers
        .route(&body.capability, body.arguments)
        .await
        .map_err(|error| {
            state.runtime.set_error(error.to_string());
            (StatusCode::BAD_GATEWAY, error.to_string())
        })?;

    state.runtime.clear_error();

    Ok(Json(ForgeBridgeResponse {
        ok: response.ok,
        capability: response.capability_id,
        result: response.result,
    }))
}

async fn events_ws(
    ws: WebSocketUpgrade,
    State(state): State<Arc<AppState>>,
    headers: HeaderMap,
) -> Result<impl IntoResponse, (StatusCode, String)> {
    auth::authorize(&headers, &state.config)
        .map_err(|_| (StatusCode::UNAUTHORIZED, "unauthorized".into()))?;
    runtime::touch_client(&state, &headers);

    Ok(ws.on_upgrade(move |socket| handle_events(socket, state)))
}

async fn handle_events(mut socket: WebSocket, state: Arc<AppState>) {
    let client = state.runtime.connected_client();
    let _ = socket
        .send(Message::Text(
            json!({
                "type": "bridge.connected",
                "bridge_id": state.config.bridge_id,
                "bridge_version": BRIDGE_VERSION,
                "client": client.as_ref().map(|entry| json!({
                    "name": entry.name,
                    "version": entry.version,
                    "connected_at": entry.connected_at,
                })),
            })
            .to_string()
            .into(),
        ))
        .await;

    let mut ticker = interval(Duration::from_secs(30));
    loop {
        tokio::select! {
            incoming = socket.recv() => {
                match incoming {
                    Some(Ok(Message::Close(_))) | None => break,
                    Some(Ok(Message::Ping(payload))) => {
                        if socket.send(Message::Pong(payload)).await.is_err() {
                            break;
                        }
                    }
                    Some(Ok(_)) => {}
                    Some(Err(_)) => break,
                }
            }
            _ = ticker.tick() => {
                let forge_available = state.forge.health().await.unwrap_or(false);
                let event = json!({
                    "type": "bridge.heartbeat",
                    "forge_available": forge_available,
                    "observed_at": chrono_now(),
                });
                if socket.send(Message::Text(event.to_string().into())).await.is_err() {
                    break;
                }
            }
        }
    }
}

fn chrono_now() -> String {
    chrono::Utc::now().to_rfc3339()
}
