use std::sync::Arc;

use axum::extract::{Path, State};
use axum::http::StatusCode;
use axum::routing::get;
use axum::{Json, Router};
use forge_projection::{self, EngineeringState};
use serde::Serialize;

use crate::AppState;

pub fn routes() -> Router<Arc<AppState>> {
    Router::new()
        .route("/api/v1/engineering/state", get(get_engineering_state))
        .route("/api/v1/engineering/docs/{slug}", get(get_engineering_doc))
}

async fn get_engineering_state(
    State(state): State<Arc<AppState>>,
) -> Result<Json<EngineeringState>, (StatusCode, String)> {
    forge_projection::build_engineering_state(&state.config)
        .map(Json)
        .map_err(|e| (StatusCode::INTERNAL_SERVER_ERROR, e.to_string()))
}

#[derive(Serialize)]
struct EngineeringDocResponse {
    slug: String,
    path: String,
    content: String,
}

async fn get_engineering_doc(
    State(state): State<Arc<AppState>>,
    Path(slug): Path<String>,
) -> Result<Json<EngineeringDocResponse>, (StatusCode, String)> {
    forge_projection::read_engineering_doc(&state.config.repo_path, &slug)
        .map(|(path, content)| Json(EngineeringDocResponse {
            slug: slug.clone(),
            path,
            content,
        }))
        .map_err(|e| (StatusCode::NOT_FOUND, e.to_string()))
}
