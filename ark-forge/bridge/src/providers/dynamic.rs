use std::collections::HashMap;

use anyhow::{bail, Context, Result};
use chrono::{DateTime, Utc};
use reqwest::Client;
use serde::Deserialize;
use serde_json::Value;

use crate::forge::ForgeInvokeResponse;

pub const SUPPORTED_MANIFEST_VERSION: u32 = 1;

#[derive(Debug, Clone)]
pub struct DynamicProvider {
    pub id: String,
    pub base_url: String,
    pub manifest: ProviderManifest,
    pub last_health_check: Option<DateTime<Utc>>,
    pub healthy: bool,
}

#[derive(Debug, Clone)]
pub struct ProviderManifest {
    pub provider_id: String,
    pub version: String,
    pub manifest_version: u32,
    pub namespace: String,
    pub capabilities: Vec<Value>,
}

#[derive(Debug, Deserialize)]
struct ManifestResponse {
    provider: ManifestProvider,
    capabilities: Vec<Value>,
}

#[derive(Debug, Deserialize)]
struct ManifestProvider {
    id: String,
    version: String,
    manifest_version: u32,
    namespace: String,
    #[serde(default)]
    healthy: bool,
}

#[derive(Debug, Deserialize)]
struct HealthResponse {
    #[serde(default)]
    healthy: bool,
    #[serde(default)]
    bridge_protocol: Option<u32>,
}

#[derive(Debug, Deserialize)]
struct ObserveResponse {
    ok: bool,
    capability: String,
    #[serde(default)]
    result: Option<Value>,
}

#[derive(Clone)]
pub struct DynamicProviderClient {
    http: Client,
    bridge_secret: String,
}

impl DynamicProviderClient {
    pub fn new(http: Client, bridge_secret: String) -> Self {
        Self {
            http,
            bridge_secret,
        }
    }

    pub async fn fetch_manifest(&self, base_url: &str) -> Result<ProviderManifest> {
        let url = format!("{}/manifest", base_url.trim_end_matches('/'));
        let response = self
            .http
            .get(url)
            .bearer_auth(&self.bridge_secret)
            .send()
            .await
            .context("fetch provider manifest")?;

        if !response.status().is_success() {
            let body = response.text().await.unwrap_or_default();
            bail!("provider manifest failed: {body}");
        }

        let manifest: ManifestResponse = response
            .json()
            .await
            .context("decode provider manifest")?;

        if manifest.provider.manifest_version != SUPPORTED_MANIFEST_VERSION {
            bail!(
                "unsupported manifest_version {} (bridge supports v{SUPPORTED_MANIFEST_VERSION})",
                manifest.provider.manifest_version
            );
        }

        Ok(ProviderManifest {
            provider_id: manifest.provider.id,
            version: manifest.provider.version,
            manifest_version: manifest.provider.manifest_version,
            namespace: manifest.provider.namespace,
            capabilities: manifest.capabilities,
        })
    }

    pub async fn poll_health(&self, base_url: &str) -> Result<bool> {
        let url = format!("{}/health", base_url.trim_end_matches('/'));
        let response = self
            .http
            .get(url)
            .send()
            .await
            .context("fetch provider health")?;

        if !response.status().is_success() {
            return Ok(false);
        }

        let health: HealthResponse = response
            .json()
            .await
            .context("decode provider health")?;

        if let Some(protocol) = health.bridge_protocol {
            if protocol != SUPPORTED_MANIFEST_VERSION {
                return Ok(false);
            }
        }

        Ok(health.healthy)
    }

    pub async fn observe(
        &self,
        base_url: &str,
        capability_id: &str,
        arguments: HashMap<String, Value>,
    ) -> Result<ForgeInvokeResponse> {
        let url = format!("{}/observe", base_url.trim_end_matches('/'));
        let body = serde_json::json!({
            "capability": capability_id,
            "arguments": arguments,
        });

        let response = self
            .http
            .post(url)
            .bearer_auth(&self.bridge_secret)
            .json(&body)
            .send()
            .await
            .context("forward observe to provider")?;

        if !response.status().is_success() {
            let body = response.text().await.unwrap_or_default();
            bail!("provider observe failed: {body}");
        }

        let observe: ObserveResponse = response
            .json()
            .await
            .context("decode provider observe response")?;

        Ok(ForgeInvokeResponse {
            ok: observe.ok,
            capability_id: observe.capability,
            action: None,
            result: observe.result,
        })
    }
}

pub fn capabilities_with_availability(
    manifest: &ProviderManifest,
    available: bool,
) -> Vec<Value> {
    manifest
        .capabilities
        .iter()
        .cloned()
        .map(|mut capability| {
            if let Some(object) = capability.as_object_mut() {
                object.insert("available".into(), Value::Bool(available));
            }
            capability
        })
        .collect()
}
