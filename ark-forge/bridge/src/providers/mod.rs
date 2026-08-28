mod dynamic;
mod forge;
mod missing;
mod store;

use std::collections::HashMap;
use std::sync::Arc;

use anyhow::{anyhow, bail, Result};
use reqwest::Client;
use serde::Deserialize;
use serde::Serialize;
use serde_json::Value;
use tokio::sync::RwLock;

pub use dynamic::{
    capabilities_with_availability, DynamicProvider, DynamicProviderClient,
};
pub use forge::ForgeProvider;
pub use missing::MissingProvider;

use crate::forge::{ForgeClient, ForgeInvokeResponse};

#[derive(Debug, Clone, Serialize)]
pub struct ProviderStatus {
    pub id: String,
    pub healthy: bool,
    pub capabilities: usize,
}

/// Provider lifecycle — separate from the capability catalog.
#[derive(Debug, Clone, Serialize)]
pub struct ProviderDescriptor {
    pub id: String,
    pub version: String,
    pub healthy: bool,
    pub namespace: String,
    pub registered: bool,
}

#[derive(Debug, Deserialize)]
pub struct RegisterProviderRequest {
    pub id: String,
    pub base_url: String,
}

#[derive(Clone)]
pub struct ProviderRegistry {
    forge: ForgeProvider,
    dynamic: Arc<RwLock<HashMap<String, DynamicProvider>>>,
    client: DynamicProviderClient,
}

impl ProviderRegistry {
    pub fn new(forge_client: ForgeClient, bridge_secret: String) -> Self {
        Self {
            forge: ForgeProvider::new(forge_client),
            dynamic: Arc::new(RwLock::new(HashMap::new())),
            client: DynamicProviderClient::new(Client::new(), bridge_secret),
        }
    }

    pub async fn register_provider(&self, request: RegisterProviderRequest) -> Result<()> {
        let base_url = request.base_url.trim_end_matches('/').to_string();
        let manifest = self.client.fetch_manifest(&base_url).await?;

        if manifest.provider_id != request.id {
            bail!(
                "manifest provider id '{}' does not match registration id '{}'",
                manifest.provider_id,
                request.id
            );
        }

        let provider = DynamicProvider {
            id: request.id.clone(),
            base_url,
            manifest,
            last_health_check: None,
            healthy: false,
        };

        let healthy = self.client.poll_health(&provider.base_url).await.unwrap_or(false);
        let mut entry = provider;
        entry.healthy = healthy;
        entry.last_health_check = Some(chrono::Utc::now());

        let mut dynamic = self.dynamic.write().await;
        dynamic.insert(request.id.clone(), entry);

        if let Err(error) = store::save_provider_registration(&request) {
            tracing::warn!(%error, provider = %request.id, "failed to persist provider registration");
        }

        Ok(())
    }

    /// Re-register persisted local runtimes when Bridge restarts or a provider comes back online.
    pub async fn reconnect_persisted_providers(&self) {
        let store = match store::load_providers_store() {
            Ok(store) => store,
            Err(error) => {
                tracing::warn!(%error, "failed to load providers store");
                return;
            }
        };

        for entry in store.providers {
            if self.dynamic.read().await.contains_key(&entry.id) {
                continue;
            }

            let healthy = self
                .client
                .poll_health(&entry.base_url)
                .await
                .unwrap_or(false);

            if !healthy {
                continue;
            }

            let request = RegisterProviderRequest {
                id: entry.id.clone(),
                base_url: entry.base_url.clone(),
            };

            match self.register_provider(request).await {
                Ok(()) => tracing::info!(provider = %entry.id, "restored runtime provider"),
                Err(error) => {
                    tracing::debug!(provider = %entry.id, %error, "runtime provider not ready")
                }
            }
        }
    }

    pub async fn poll_dynamic_health(&self) {
        self.reconnect_persisted_providers().await;

        let ids: Vec<String> = {
            let dynamic = self.dynamic.read().await;
            dynamic.keys().cloned().collect()
        };

        for id in ids {
            let base_url = {
                let dynamic = self.dynamic.read().await;
                dynamic.get(&id).map(|provider| provider.base_url.clone())
            };

            let Some(base_url) = base_url else {
                continue;
            };

            let healthy = self.client.poll_health(&base_url).await.unwrap_or(false);
            let mut dynamic = self.dynamic.write().await;
            if let Some(provider) = dynamic.get_mut(&id) {
                provider.healthy = healthy;
                provider.last_health_check = Some(chrono::Utc::now());
            }
        }
    }

    pub async fn provider_descriptors(&self) -> Vec<ProviderDescriptor> {
        let mut descriptors = vec![ProviderDescriptor {
            id: self.forge.id().to_string(),
            version: self.forge.version().to_string(),
            healthy: self.forge.healthy().await,
            namespace: format!("{}.*", self.forge.id()),
            registered: true,
        }];

        let dynamic = self.dynamic.read().await;
        for provider in dynamic.values() {
            descriptors.push(ProviderDescriptor {
                id: provider.id.clone(),
                version: provider.manifest.version.clone(),
                healthy: provider.healthy,
                namespace: provider.manifest.namespace.clone(),
                registered: true,
            });
        }

        descriptors
    }

    pub async fn provider_statuses(&self) -> Vec<ProviderStatus> {
        let forge_caps = self.forge.capabilities().await.unwrap_or_default();
        let dynamic = self.dynamic.read().await;

        let mut statuses = vec![ProviderStatus {
            id: self.forge.id().to_string(),
            healthy: self.forge.healthy().await,
            capabilities: forge_caps.len(),
        }];

        for provider in dynamic.values() {
            statuses.push(ProviderStatus {
                id: provider.id.clone(),
                healthy: provider.healthy,
                capabilities: provider.manifest.capabilities.len(),
            });
        }

        statuses
    }

    pub async fn merged_capabilities(&self) -> Result<Vec<Value>> {
        let mut merged = self.forge.capabilities().await?;
        let dynamic = self.dynamic.read().await;

        for namespace in MissingProvider::known_namespaces() {
            let registered = dynamic.values().any(|provider| provider.id == *namespace);
            if !registered {
                merged.extend(MissingProvider::unavailable_capabilities(namespace));
            }
        }

        for provider in dynamic.values() {
            merged.extend(capabilities_with_availability(
                &provider.manifest,
                provider.healthy,
            ));
        }

        Ok(merged)
    }

    pub async fn merged_capability_count(&self) -> usize {
        self.merged_capabilities().await.map(|c| c.len()).unwrap_or(0)
    }

    pub async fn route(
        &self,
        capability_id: &str,
        arguments: HashMap<String, Value>,
    ) -> Result<ForgeInvokeResponse> {
        let provider_id = capability_id
            .split('.')
            .next()
            .ok_or_else(|| anyhow!("invalid capability id '{capability_id}'"))?;

        if provider_id == self.forge.id() {
            return self.forge.dispatch(capability_id, arguments).await;
        }

        if let Some(provider) = self.dynamic.read().await.get(provider_id) {
            if !provider.healthy {
                return Ok(MissingProvider::unavailable_observe(
                    provider_id,
                    capability_id,
                ));
            }

            return self
                .client
                .observe(&provider.base_url, capability_id, arguments)
                .await;
        }

        if let Some(namespace) = MissingProvider::handles_capability(capability_id) {
            return Ok(MissingProvider::unavailable_observe(
                &namespace,
                capability_id,
            ));
        }

        bail!("no provider registered for capability '{capability_id}'")
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn routes_capability_to_provider_prefix() {
        assert_eq!(
            "forge.git.status.read".split('.').next(),
            Some("forge")
        );
        assert_eq!(
            "cursor.workspace.folders.read".split('.').next(),
            Some("cursor")
        );
    }

    #[test]
    fn missing_provider_handles_cursor_namespace() {
        let response = MissingProvider::unavailable_observe(
            "cursor",
            "cursor.editor.selection.read",
        );
        assert!(response.ok);
        assert_eq!(response.capability_id, "cursor.editor.selection.read");
    }
}
