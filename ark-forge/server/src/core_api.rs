use std::sync::Arc;

use axum::extract::{Path, State};
use axum::http::StatusCode;
use axum::routing::{get, post};
use axum::{Json, Router};
use forge_core::capabilities::{CapabilityRegistry, InvokeRequest, InvokeResponse};

use crate::AppState;

pub fn routes() -> Router<Arc<AppState>> {
    Router::new()
        .route("/api/v1/core/capabilities", get(get_capability_registry))
        .route(
            "/api/v1/core/capabilities/{id}/invoke",
            post(post_invoke_capability),
        )
        .route("/health", get(|| async { "ok" }))
}

async fn get_capability_registry() -> Json<CapabilityRegistry> {
    Json(CapabilityRegistry::build())
}

async fn post_invoke_capability(
    State(state): State<Arc<AppState>>,
    Path(id): Path<String>,
    Json(body): Json<InvokeRequest>,
) -> Result<Json<InvokeResponse>, (StatusCode, String)> {
    forge_core::capabilities::invoke::invoke(&state.config.repo_path, &id, body)
        .map(Json)
        .map_err(|e| (StatusCode::BAD_REQUEST, e.to_string()))
}
