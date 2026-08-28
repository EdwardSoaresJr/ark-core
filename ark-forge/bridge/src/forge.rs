use std::collections::HashMap;

use anyhow::{Context, Result};
use reqwest::Client;
use serde::{Deserialize, Serialize};
use serde_json::{Map, Value};

#[derive(Clone)]
pub struct ForgeClient {
    base_url: String,
    http: Client,
}

#[derive(Debug, Deserialize, Serialize)]
pub struct ForgeInvokeResponse {
    pub ok: bool,
    pub capability_id: String,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub action: Option<String>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub result: Option<Value>,
}

impl ForgeClient {
    pub fn new(base_url: impl Into<String>) -> Self {
        Self {
            base_url: base_url.into().trim_end_matches('/').to_string(),
            http: Client::new(),
        }
    }

    pub async fn health(&self) -> Result<bool> {
        let url = format!("{}/health", self.base_url);
        let response = self.http.get(url).send().await?;
        Ok(response.status().is_success())
    }

    pub async fn capability_registry(&self) -> Result<Value> {
        let url = format!("{}/api/v1/core/capabilities", self.base_url);
        let response = self.http.get(url).send().await?;
        response
            .error_for_status()?
            .json()
            .await
            .context("decode forge capability registry")
    }

    pub async fn engineering_state(&self) -> Result<()> {
        let url = format!("{}/api/v1/engineering/state", self.base_url);
        let response = self.http.get(url).send().await?;
        response.error_for_status()?;
        Ok(())
    }

    pub async fn invoke(
        &self,
        capability_id: &str,
        arguments: HashMap<String, Value>,
    ) -> Result<ForgeInvokeResponse> {
        let url = format!(
            "{}/api/v1/core/capabilities/{}/invoke",
            self.base_url, capability_id
        );
        let body = Value::Object(arguments.into_iter().collect::<Map<String, Value>>());
        let response = self.http.post(url).json(&body).send().await?;
        if !response.status().is_success() {
            let body = response.text().await.unwrap_or_default();
            anyhow::bail!("forge invoke failed: {body}");
        }
        response
            .json()
            .await
            .context("decode forge invoke response")
    }
}
