use std::fs;
use std::path::PathBuf;

use anyhow::{Context, Result};
use serde::{Deserialize, Serialize};

use super::RegisterProviderRequest;

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct PersistedProvider {
    pub id: String,
    pub base_url: String,
}

#[derive(Debug, Clone, Default, Serialize, Deserialize)]
pub struct ProvidersStore {
    pub providers: Vec<PersistedProvider>,
}

pub fn providers_store_path() -> PathBuf {
    if let Ok(path) = std::env::var("ARK_PROVIDERS_STORE") {
        return PathBuf::from(path);
    }
    let home = std::env::var("USERPROFILE")
        .or_else(|_| std::env::var("HOME"))
        .unwrap_or_else(|_| ".".into());
    PathBuf::from(home).join(".ark").join("providers.json")
}

pub fn load_providers_store() -> Result<ProvidersStore> {
    let path = providers_store_path();
    if !path.exists() {
        let store = default_store();
        save_providers_store(&store)?;
        return Ok(store);
    }

    let raw = fs::read_to_string(&path).context("read providers store")?;
    let mut store: ProvidersStore = serde_json::from_str(&raw).context("parse providers store")?;

    if !store.providers.iter().any(|entry| entry.id == "cursor") {
        store.providers.push(PersistedProvider {
            id: "cursor".into(),
            base_url: "http://127.0.0.1:9472".into(),
        });
        save_providers_store(&store)?;
    }

    Ok(store)
}

pub fn save_provider_registration(request: &RegisterProviderRequest) -> Result<()> {
    let mut store = load_providers_store().unwrap_or_default();
    let base_url = request.base_url.trim_end_matches('/').to_string();

    if let Some(existing) = store.providers.iter_mut().find(|entry| entry.id == request.id) {
        existing.base_url = base_url;
    } else {
        store.providers.push(PersistedProvider {
            id: request.id.clone(),
            base_url,
        });
    }

    save_providers_store(&store)
}

fn save_providers_store(store: &ProvidersStore) -> Result<()> {
    let path = providers_store_path();
    if let Some(parent) = path.parent() {
        fs::create_dir_all(parent)?;
    }
    fs::write(path, serde_json::to_string_pretty(store)?)?;
    Ok(())
}

/// Default local runtimes Bridge should attempt to discover on this workstation.
fn default_store() -> ProvidersStore {
    ProvidersStore {
        providers: vec![PersistedProvider {
            id: "cursor".into(),
            base_url: "http://127.0.0.1:9472".into(),
        }],
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn default_store_includes_cursor_runtime() {
        let store = default_store();
        assert_eq!(store.providers.len(), 1);
        assert_eq!(store.providers[0].id, "cursor");
    }
}
