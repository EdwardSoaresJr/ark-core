use std::collections::HashMap;

use anyhow::{Context, Result};
use serde_json::Value;

use crate::forge::{ForgeClient, ForgeInvokeResponse};

#[derive(Clone)]
pub struct ForgeProvider {
    client: ForgeClient,
}

impl ForgeProvider {
    pub fn new(client: ForgeClient) -> Self {
        Self { client }
    }

    pub fn id(&self) -> &'static str {
        "forge"
    }

    pub fn version(&self) -> &'static str {
        "0.1.0"
    }

    pub async fn healthy(&self) -> bool {
        self.client.health().await.unwrap_or(false)
    }

    pub async fn capabilities(&self) -> Result<Vec<Value>> {
        let registry = self
            .client
            .capability_registry()
            .await
            .context("forge capability registry")?;

        Ok(registry
            .get("capabilities")
            .and_then(|items| items.as_array())
            .cloned()
            .unwrap_or_default())
    }

    pub async fn dispatch(
        &self,
        capability_id: &str,
        arguments: HashMap<String, Value>,
    ) -> Result<ForgeInvokeResponse> {
        if !capability_id.starts_with("forge.") {
            anyhow::bail!("forge provider cannot route '{capability_id}'");
        }

        self.client.invoke(capability_id, arguments).await
    }
}
