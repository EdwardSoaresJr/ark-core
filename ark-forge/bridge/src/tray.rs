use std::sync::{Arc, Mutex};
use std::time::{Duration, Instant};

use tao::event_loop::{ControlFlow, EventLoop};
use tao::platform::run_return::EventLoopExtRunReturn;
use tray_icon::menu::{Menu, MenuEvent, MenuId, MenuItem, PredefinedMenuItem};
use tray_icon::{TrayIcon, TrayIconBuilder};

use crate::config::BridgeConfig;
use crate::diagnostics;
use crate::icon;
use crate::lifecycle::AppLifecycle;
use crate::process::{open_ark_console, open_settings_file, show_pairing_dialog, ForgeCoreProcess};

const BRIDGE_VERSION: &str = env!("CARGO_PKG_VERSION");

struct TrayHandles {
    forge_line: MenuItem,
    cursor_line: MenuItem,
    bridge_line: MenuItem,
    bridge_id_line: MenuItem,
    client_line: MenuItem,
    capabilities_line: MenuItem,
}

struct MenuIds {
    status: MenuId,
    pair: MenuId,
    logs: MenuId,
    console: MenuId,
    settings: MenuId,
    updates: MenuId,
    autostart: MenuId,
    quit: MenuId,
}

pub fn run_tray_app(config: BridgeConfig, lifecycle: Arc<AppLifecycle>) -> anyhow::Result<()> {
    let config = Arc::new(Mutex::new(config));

    let header = MenuItem::with_id("header", "ARK Bridge", false, None);
    let forge_line = MenuItem::with_id("forge", "Forge Core: checking…", false, None);
    let cursor_line = MenuItem::with_id("cursor", "Cursor Runtime: checking…", false, None);
    let bridge_line = MenuItem::with_id("bridge", "Bridge: starting…", false, None);
    let bridge_id_line = MenuItem::with_id("bridge_id", "Bridge ID: …", false, None);
    let client_line = MenuItem::with_id("client", "Client: None", false, None);
    let capabilities_line = MenuItem::with_id("caps", "Capabilities: …", false, None);
    let events_line = MenuItem::with_id("events", "Events: 0", false, None);
    let version_line = MenuItem::with_id("version", format!("Version {BRIDGE_VERSION}"), false, None);

    let status_item = MenuItem::with_id("status", "Status…", true, None);
    let pair_item = MenuItem::with_id("pair", "Pair", true, None);
    let logs_item = MenuItem::with_id("logs", "Logs", true, None);
    let console_item = MenuItem::with_id("console", "ARK Console…", true, None);
    let settings_item = MenuItem::with_id("settings", "Settings", true, None);
    let updates_item = MenuItem::with_id("updates", "Check for Updates", true, None);
    let autostart_item = MenuItem::with_id(
        "autostart",
        autostart_label(false),
        true,
        None,
    );
    let quit_item = MenuItem::with_id("quit", "Quit", true, None);

    let menu_ids = MenuIds {
        status: status_item.id().clone(),
        pair: pair_item.id().clone(),
        logs: logs_item.id().clone(),
        console: console_item.id().clone(),
        settings: settings_item.id().clone(),
        updates: updates_item.id().clone(),
        autostart: autostart_item.id().clone(),
        quit: quit_item.id().clone(),
    };

    let menu = Menu::new();
    menu.append(&header)?;
    menu.append(&forge_line)?;
    menu.append(&cursor_line)?;
    menu.append(&bridge_line)?;
    menu.append(&PredefinedMenuItem::separator())?;
    menu.append(&bridge_id_line)?;
    menu.append(&client_line)?;
    menu.append(&capabilities_line)?;
    menu.append(&events_line)?;
    menu.append(&version_line)?;
    menu.append(&PredefinedMenuItem::separator())?;
    menu.append(&status_item)?;
    menu.append(&pair_item)?;
    menu.append(&logs_item)?;
    menu.append(&console_item)?;
    menu.append(&settings_item)?;
    menu.append(&updates_item)?;
    menu.append(&autostart_item)?;
    menu.append(&PredefinedMenuItem::separator())?;
    menu.append(&quit_item)?;

    let handles = TrayHandles {
        forge_line,
        cursor_line,
        bridge_line,
        bridge_id_line,
        client_line,
        capabilities_line,
    };

    {
        let cfg = config.lock().unwrap();
        let _ = handles
            .bridge_id_line
            .set_text(format_short_id(&cfg.bridge_id));
        let _ = autostart_item.set_text(autostart_label(cfg.autostart_with_windows));
    }

    let tray_icon = icon::load_tray_icon();
    let tray: TrayIcon = TrayIconBuilder::new()
        .with_icon(tray_icon)
        .with_menu(Box::new(menu))
        .with_tooltip("ARK Bridge")
        .build()?;

    refresh_status(&config, &handles);

    let menu_channel = MenuEvent::receiver();
    let mut event_loop = EventLoop::new();
    let mut last_refresh = Instant::now();
    let mut exiting = false;

    event_loop.run_return(move |_, _, control_flow| {
        *control_flow = ControlFlow::WaitUntil(Instant::now() + Duration::from_millis(250));

        if !exiting && last_refresh.elapsed() >= Duration::from_secs(3) {
            refresh_status(&config, &handles);
            last_refresh = Instant::now();
        }

        while let Ok(event) = menu_channel.try_recv() {
            if event.id == menu_ids.quit {
                exiting = true;
                lifecycle.quit();
                *control_flow = ControlFlow::Exit;
                return;
            }

            if event.id == menu_ids.status {
                if let Ok(cfg) = BridgeConfig::load() {
                    diagnostics::open_diagnostics_window(&cfg);
                }
            } else if event.id == menu_ids.pair {
                if let Ok(cfg) = BridgeConfig::load() {
                    let body = format!(
                        "Bridge ID:\n{}\n\nBearer token:\n{}\n\nConfig:\n{}",
                        cfg.bridge_id,
                        cfg.bridge_secret,
                        BridgeConfig::config_path_display()
                    );
                    show_pairing_dialog("ARK Bridge — Pairing", &body);
                }
            } else if event.id == menu_ids.logs {
                crate::log::open_logs_in_explorer();
            } else if event.id == menu_ids.console {
                if let Ok(cfg) = BridgeConfig::load() {
                    let bridge_url = format!("http://{}:{}", cfg.listen_host, cfg.listen_port);
                    open_ark_console(
                        cfg.resolved_repo_path().as_deref(),
                        &bridge_url,
                        &cfg.bridge_secret,
                    );
                }
            } else if event.id == menu_ids.settings {
                open_settings_file(&BridgeConfig::config_path_display());
            } else if event.id == menu_ids.updates {
                show_pairing_dialog(
                    "ARK Bridge — Updates",
                    "Auto-update is not configured yet.\n\nYou are running ARK Forge v0.1.",
                );
            } else if event.id == menu_ids.autostart {
                let mut cfg = config.lock().unwrap();
                cfg.autostart_with_windows = !cfg.autostart_with_windows;
                let enabled = cfg.autostart_with_windows;
                let _ = cfg.save();
                let _ = crate::autostart::set_autostart(enabled);
                let _ = autostart_item.set_text(autostart_label(enabled));
            }
        }
    });

    drop(tray);
    Ok(())
}

fn refresh_status(config: &Arc<Mutex<BridgeConfig>>, handles: &TrayHandles) {
    let cfg = config.lock().unwrap().clone();

    if let Some(snapshot) = fetch_diagnostics(&cfg) {
        let _ = handles.forge_line.set_text(if snapshot.forge_core == "Running" {
            "● Forge Core Running"
        } else {
            "○ Forge Core Offline"
        });
        let _ = handles.cursor_line.set_text(cursor_runtime_label(&snapshot.cursor_runtime));
        let _ = handles.bridge_line.set_text("Bridge Connected");
        let _ = handles
            .capabilities_line
            .set_text(format!("Capabilities: {}", snapshot.capabilities));
        let client_text = match snapshot.client {
            Some(client) => format!("Client: {} (since {})", client.name, client.connected_at),
            None => "Client: None".into(),
        };
        let _ = handles.client_line.set_text(client_text);
        return;
    }

    let forge_ok = ForgeCoreProcess::health_check();
    let _ = handles.forge_line.set_text(if forge_ok {
        "● Forge Core Running"
    } else {
        "○ Forge Core Offline"
    });
    let _ = handles.cursor_line.set_text(cursor_runtime_label(
        &fetch_cursor_runtime(&cfg).unwrap_or_else(|| "Not registered".into()),
    ));
    let _ = handles.bridge_line.set_text("Bridge Connected");
    let caps = fetch_capability_count(&cfg);
    let _ = handles
        .capabilities_line
        .set_text(format!("Capabilities: {caps}"));
}

fn fetch_diagnostics(config: &BridgeConfig) -> Option<crate::diagnostics::DiagnosticsSnapshot> {
    let url = format!(
        "http://{}:{}/bridge/diagnostics.json",
        config.listen_host, config.listen_port
    );
    reqwest::blocking::Client::new()
        .get(url)
        .send()
        .ok()?
        .json()
        .ok()
}

fn autostart_label(enabled: bool) -> String {
    if enabled {
        "Start with Windows ✓".into()
    } else {
        "Start with Windows".into()
    }
}

fn format_short_id(id: &str) -> String {
    let short: String = id.chars().take(8).collect();
    format!("Bridge ID: {short}…")
}

fn cursor_runtime_label(status: &str) -> String {
    match status {
        "Connected" => "● Cursor Runtime Connected".into(),
        "Offline" => "○ Cursor Runtime Offline".into(),
        _ => "○ Cursor Runtime Not registered".into(),
    }
}

fn fetch_cursor_runtime(config: &BridgeConfig) -> Option<String> {
    reqwest::blocking::Client::new()
        .get(format!(
            "http://{}:{}/bridge/diagnostics.json",
            config.listen_host, config.listen_port
        ))
        .send()
        .ok()?
        .json::<serde_json::Value>()
        .ok()?
        .get("cursor_runtime")?
        .as_str()
        .map(str::to_string)
}

fn fetch_capability_count(config: &BridgeConfig) -> usize {
    let client = reqwest::blocking::Client::new();
    let url = format!(
        "http://{}:{}/bridge/diagnostics.json",
        config.listen_host, config.listen_port
    );
    client
        .get(url)
        .send()
        .ok()
        .and_then(|r| r.json::<serde_json::Value>().ok())
        .and_then(|v| v.get("capabilities").and_then(|c| c.as_u64()))
        .map(|n| n as usize)
        .unwrap_or(0)
}
