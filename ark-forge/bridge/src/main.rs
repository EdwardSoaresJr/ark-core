#![cfg_attr(all(windows, not(debug_assertions)), windows_subsystem = "windows")]

mod api;
mod auth;
mod autostart;
mod config;
mod diagnostics;
mod forge;
mod icon;
mod lifecycle;
mod log;
mod process;
mod providers;
mod runtime;
#[cfg(windows)]
mod tray;

use std::net::SocketAddr;
use std::sync::Arc;

use axum::Router;
use tokio::net::TcpListener;
use tokio::time::{interval, Duration};
use tower_http::cors::{Any, CorsLayer};

use crate::api::AppState;
use crate::config::{print_pairing, BridgeConfig};
use crate::forge::ForgeClient;
use crate::lifecycle::AppLifecycle;
use crate::providers::ProviderRegistry;
use crate::runtime::BridgeRuntime;
use std::sync::Arc as StdArc;

fn main() -> anyhow::Result<()> {
    let args: Vec<String> = std::env::args().collect();
    let command = args.get(1).map(String::as_str);

    match command {
        Some("pair") => {
            let config = BridgeConfig::load()?;
            print_pairing(&config);
            Ok(())
        }
        Some("reset-secret") => {
            let mut config = BridgeConfig::load()?;
            config.reset_secret()?;
            eprintln!("Bridge secret rotated. Save this bearer token:");
            print_pairing(&config);
            Ok(())
        }
        Some("serve") | Some("--headless") => {
            let rt = tokio::runtime::Runtime::new()?;
            rt.block_on(run_server(false))
        }
        Some(other) if other.starts_with('-') => {
            let rt = tokio::runtime::Runtime::new()?;
            rt.block_on(run_server(other == "--headless"))
        }
        None => run_product(),
        Some(cmd) => {
            eprintln!("Unknown command: {cmd}");
            eprintln!("Usage: ARK Bridge [serve|--headless|pair|reset-secret]");
            std::process::exit(1);
        }
    }
}

/// Installed product entry: tray + Forge Core + Bridge server.
fn run_product() -> anyhow::Result<()> {
    log::init_logging("bridge")?;

    let (config, created) = BridgeConfig::load_or_create()?;
    tracing::info!(
        config = %BridgeConfig::config_path_display(),
        bridge_id = %config.bridge_id,
        "ARK Bridge starting"
    );

    if created {
        process::show_pairing_dialog(
            "ARK Bridge — Welcome",
            &format!(
                "ARK Bridge is running in the system tray.\n\n\
                 Next steps:\n\
                 1. Right-click the tray icon → Settings\n\
                 2. Set forge_repo_path to your repo folder\n\
                 3. Save, then quit and reopen ARK Bridge\n\
                 4. Open Status to verify Forge Core + Repo\n\
                 5. Use Pair when connecting a client\n\n\
                 Config file:\n{}",
                BridgeConfig::config_path_display()
            ),
        );
    }

    autostart::apply_saved_autostart(&config)?;

    let (lifecycle, shutdown_rx) = AppLifecycle::new();

    let mut forge_core = None;
    if config.autostart_forge_core {
        if let Some(repo) = config.resolved_repo_path() {
            match process::ForgeCoreProcess::ensure_running(&repo) {
                Ok(child) => forge_core = Some(child),
                Err(error) => {
                    tracing::warn!(%error, "Could not start Forge Core");
                    process::show_pairing_dialog(
                        "ARK Bridge — Forge Core",
                        &format!(
                            "Forge Core did not start.\n\n{error}\n\nCheck forge_repo_path in:\n{}",
                            BridgeConfig::config_path_display()
                        ),
                    );
                }
            }
        } else if created {
            process::show_pairing_dialog(
                "ARK Bridge — Repository Required",
                &format!(
                    "Could not detect your repo automatically.\n\n\
                     Open Settings in the tray menu and set forge_repo_path.\n\n\
                     Config:\n{}",
                    BridgeConfig::config_path_display()
                ),
            );
        }
    }

    if let Some(child) = forge_core {
        lifecycle.set_forge_core(child);
    }

    let addr: SocketAddr = format!("{}:{}", config.listen_host, config.listen_port).parse()?;
    let config_for_server = config.clone();

    std::thread::spawn(move || {
        let rt = tokio::runtime::Runtime::new().expect("tokio runtime");
        if let Err(error) = rt.block_on(run_server_internal(
            config_for_server,
            addr,
            shutdown_rx,
        )) {
            tracing::error!(%error, "ARK Bridge server stopped");
        }
    });

    // Give the HTTP server a moment to bind.
    std::thread::sleep(std::time::Duration::from_millis(400));

    #[cfg(windows)]
    {
        return tray::run_tray_app(
            BridgeConfig::load().unwrap_or_else(|_| BridgeConfig::load_or_create().unwrap().0),
            lifecycle,
        );
    }

    #[cfg(not(windows))]
    {
        tracing::info!("Non-Windows product mode — running headless server");
        let rt = tokio::runtime::Runtime::new()?;
        rt.block_on(async {
            loop {
                tokio::time::sleep(std::time::Duration::from_secs(3600)).await;
            }
        })
    }
}

async fn run_server(headless: bool) -> anyhow::Result<()> {
    log::init_logging("bridge")?;
    let (config, created) = BridgeConfig::load_or_create()?;
    if created {
        eprintln!("ARK Bridge initialized. Save this bearer token (shown once):");
        print_pairing(&config);
    }
    let addr: SocketAddr = format!("{}:{}", config.listen_host, config.listen_port).parse()?;
    let (_, shutdown_rx) = tokio::sync::watch::channel(false);
    if headless {
        run_server_internal(config, addr, shutdown_rx).await
    } else {
        #[cfg(windows)]
        {
            let (lifecycle, shutdown_rx) = AppLifecycle::new();
            let config_for_tray = config.clone();
            std::thread::spawn(move || {
                let rt = tokio::runtime::Runtime::new().expect("tokio runtime");
                let _ = rt.block_on(run_server_internal(config, addr, shutdown_rx));
            });
            tray::run_tray_app(config_for_tray, lifecycle)
        }
        #[cfg(not(windows))]
        {
            run_server_internal(config, addr).await
        }
    }
}

async fn run_server_internal(
    config: BridgeConfig,
    addr: SocketAddr,
    mut shutdown_rx: tokio::sync::watch::Receiver<bool>,
) -> anyhow::Result<()> {
    let forge = ForgeClient::new(config.forge_core_url.clone());
    let state = Arc::new(AppState {
        forge: forge.clone(),
        providers: ProviderRegistry::new(forge, config.bridge_secret.clone()),
        config: config.clone(),
        runtime: StdArc::new(BridgeRuntime::new()),
    });

    let providers_for_health = state.providers.clone();
    tokio::spawn(async move {
        providers_for_health.reconnect_persisted_providers().await;
        let mut ticker = interval(Duration::from_secs(10));
        loop {
            ticker.tick().await;
            providers_for_health.poll_dynamic_health().await;
        }
    });

    let cors = CorsLayer::new()
        .allow_origin(Any)
        .allow_methods(Any)
        .allow_headers(Any);

    let app = Router::new()
        .merge(api::routes(state))
        .layer(cors);

    let listener = TcpListener::bind(addr).await?;
    tracing::info!(%addr, "ARK Bridge listening");

    axum::serve(
        listener,
        app.into_make_service_with_connect_info::<SocketAddr>(),
    )
    .with_graceful_shutdown(async move {
        loop {
            if *shutdown_rx.borrow() {
                break;
            }
            if shutdown_rx.changed().await.is_err() {
                break;
            }
        }
        tracing::info!("ARK Bridge HTTP server shutting down");
    })
    .await?;
    Ok(())
}
