use std::path::{Path, PathBuf};

use anyhow::{Context, Result};

#[derive(Clone, Debug)]
pub struct ForgeConfig {
    pub repo_path: PathBuf,
    pub host: String,
    pub port: u16,
}

impl ForgeConfig {
    pub fn from_env() -> Result<Self> {
        let repo_path = resolve_repo_path()?;

        let host = std::env::var("FORGE_REPO_HOST")
            .or_else(|_| std::env::var("FORGE_CORE_HOST"))
            .unwrap_or_else(|_| "127.0.0.1".into());

        let port = std::env::var("FORGE_REPO_PORT")
            .or_else(|_| std::env::var("FORGE_CORE_PORT"))
            .unwrap_or_else(|_| "9470".into())
            .parse()
            .context("FORGE_CORE_PORT must be a valid u16")?;

        Ok(Self {
            repo_path,
            host,
            port,
        })
    }
}

fn resolve_repo_path() -> Result<PathBuf> {
    if let Ok(path) = std::env::var("FORGE_REPO_PATH") {
        return Ok(PathBuf::from(path));
    }

    let manifest = PathBuf::from(env!("CARGO_MANIFEST_DIR"));
    let forge_root = manifest.parent().context("invalid core path")?;
    let monorepo_root = forge_root.parent().context("invalid forge root")?;

    if monorepo_root.join("docs").join("engineering").is_dir() {
        return Ok(monorepo_root.to_path_buf());
    }

    anyhow::bail!("Set FORGE_REPO_PATH to repository root")
}

pub fn read_file(path: &Path) -> Result<String> {
    std::fs::read_to_string(path)
        .with_context(|| format!("read file {}", path.display()))
}
