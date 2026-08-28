use std::fs;
use std::path::PathBuf;

use anyhow::{bail, Result};
use serde::{Deserialize, Serialize};
use uuid::Uuid;

use crate::lifecycle::detect_default_repo_path;

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct BridgeConfig {
    pub bridge_id: String,
    pub bridge_secret: String,
    pub forge_core_url: String,
    pub listen_host: String,
    pub listen_port: u16,
    /// Repository Forge Core should use (FORGE_REPO_PATH).
    #[serde(default)]
    pub forge_repo_path: Option<String>,
    /// Spawn Forge Core when Bridge starts (installed product default).
    #[serde(default = "default_true")]
    pub autostart_forge_core: bool,
    /// Register ARK Bridge in HKCU Run key.
    #[serde(default)]
    pub autostart_with_windows: bool,
}

fn default_true() -> bool {
    true
}

impl BridgeConfig {
    pub fn load_or_create() -> Result<(Self, bool)> {
        let path = config_path();
        if path.exists() {
            let raw = fs::read_to_string(&path)?;
            let mut config: Self = serde_json::from_str(&raw)?;
            if config.forge_repo_path.is_none() {
                if let Some(repo) = detect_default_repo_path() {
                    config.forge_repo_path = Some(repo);
                    config.save()?;
                }
            }
            return Ok((config, false));
        }

        let config = Self::new_defaults();
        config.save()?;
        Ok((config, true))
    }

    pub fn load() -> Result<Self> {
        let path = config_path();
        if !path.exists() {
            bail!(
                "bridge config not found at {}; run ark-bridge once to initialize",
                path.display()
            );
        }
        let raw = fs::read_to_string(&path)?;
        Ok(serde_json::from_str(&raw)?)
    }

    pub fn reset_secret(&mut self) -> Result<()> {
        self.bridge_secret = generate_secret();
        self.save()
    }

    pub fn save(&self) -> Result<()> {
        let path = config_path();
        if let Some(parent) = path.parent() {
            fs::create_dir_all(parent)?;
        }
        fs::write(&path, serde_json::to_string_pretty(self)?)?;
        Ok(())
    }

    pub fn config_path_display() -> String {
        config_path().display().to_string()
    }

    pub fn resolved_repo_path(&self) -> Option<std::path::PathBuf> {
        if let Some(path) = &self.forge_repo_path {
            return Some(std::path::PathBuf::from(path));
        }
        std::env::var("FORGE_REPO_PATH")
            .ok()
            .map(std::path::PathBuf::from)
    }

    fn new_defaults() -> Self {
        Self {
            bridge_id: Uuid::new_v4().to_string(),
            bridge_secret: generate_secret(),
            forge_core_url: std::env::var("FORGE_CORE_URL")
                .unwrap_or_else(|_| "http://127.0.0.1:9470".into()),
            listen_host: std::env::var("ARK_BRIDGE_HOST").unwrap_or_else(|_| "127.0.0.1".into()),
            listen_port: std::env::var("ARK_BRIDGE_PORT")
                .unwrap_or_else(|_| "9471".into())
                .parse()
                .unwrap_or(9471),
            forge_repo_path: detect_default_repo_path(),
            autostart_forge_core: true,
            autostart_with_windows: false,
        }
    }
}

pub fn print_pairing(config: &BridgeConfig) {
    println!("ARK Bridge pairing");
    println!("  config: {}", BridgeConfig::config_path_display());
    println!("  bridge_id: {}", config.bridge_id);
    println!("  bearer: {}", config.bridge_secret);
    println!();
    println!("Authorization: Bearer {}", config.bridge_secret);
}

fn config_path() -> PathBuf {
    if let Ok(path) = std::env::var("ARK_BRIDGE_CONFIG") {
        return PathBuf::from(path);
    }
    let home = std::env::var("USERPROFILE")
        .or_else(|_| std::env::var("HOME"))
        .unwrap_or_else(|_| ".".into());
    PathBuf::from(home).join(".ark").join("bridge.json")
}

fn generate_secret() -> String {
    format!("{}{}", Uuid::new_v4(), Uuid::new_v4()).replace('-', "")
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn generates_non_empty_secret() {
        assert!(!generate_secret().is_empty());
    }
}
