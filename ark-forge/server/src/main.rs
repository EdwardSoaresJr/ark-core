mod core_api;
mod projection_api;

use std::net::SocketAddr;
use std::sync::Arc;

use axum::Router;
use forge_core::ForgeConfig;
use tokio::net::TcpListener;
use tower_http::cors::{Any, CorsLayer};
use tracing_subscriber::EnvFilter;

#[derive(Clone)]
pub struct AppState {
    pub config: ForgeConfig,
}

#[tokio::main]
async fn main() -> anyhow::Result<()> {
    tracing_subscriber::fmt()
        .with_env_filter(
            EnvFilter::try_from_default_env().unwrap_or_else(|_| EnvFilter::new("info")),
        )
        .init();

    let config = ForgeConfig::from_env()?;
    tracing::info!(repo = %config.repo_path.display(), "Forge Core starting");

    let addr: SocketAddr = format!("{}:{}", config.host, config.port).parse()?;

    let state = Arc::new(AppState { config });

    let cors = CorsLayer::new()
        .allow_origin(Any)
        .allow_methods(Any)
        .allow_headers(Any);

    let app = Router::new()
        .merge(core_api::routes())
        .merge(projection_api::routes())
        .layer(cors)
        .with_state(state);

    let listener = TcpListener::bind(addr).await?;
    tracing::info!(%addr, "Forge Core listening");

    axum::serve(listener, app).await?;
    Ok(())
}
