use std::sync::Mutex;

use chrono::Local;

#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
pub struct ConnectedClient {
    pub name: String,
    pub version: Option<String>,
    pub connected_at: String,
}

#[derive(Default)]
pub struct BridgeRuntime {
    last_error: Mutex<Option<String>>,
    connected_client: Mutex<Option<ConnectedClient>>,
}

impl BridgeRuntime {
    pub fn new() -> Self {
        Self::default()
    }

    pub fn record_client(&self, name: &str, version: Option<String>) {
        let client = ConnectedClient {
            name: name.to_string(),
            version,
            connected_at: Local::now().format("%H:%M:%S").to_string(),
        };
        *self.connected_client.lock().unwrap() = Some(client);
    }

    pub fn connected_client(&self) -> Option<ConnectedClient> {
        self.connected_client.lock().unwrap().clone()
    }

    pub fn set_error(&self, message: impl Into<String>) {
        *self.last_error.lock().unwrap() = Some(message.into());
    }

    pub fn clear_error(&self) {
        *self.last_error.lock().unwrap() = None;
    }

    pub fn last_error(&self) -> Option<String> {
        self.last_error.lock().unwrap().clone()
    }
}

pub fn client_from_headers(headers: &axum::http::HeaderMap) -> Option<(&str, Option<String>)> {
    let name = headers
        .get("x-ark-client")
        .or_else(|| headers.get("X-ARK-Client"))
        .and_then(|value| value.to_str().ok())
        .map(str::trim)
        .filter(|value| !value.is_empty())?;

    let version = headers
        .get("x-ark-client-version")
        .or_else(|| headers.get("X-ARK-Client-Version"))
        .and_then(|value| value.to_str().ok())
        .map(str::trim)
        .filter(|value| !value.is_empty())
        .map(str::to_string);

    Some((name, version))
}

pub fn touch_client(state: &crate::api::AppState, headers: &axum::http::HeaderMap) {
    if let Some((name, version)) = client_from_headers(headers) {
        state.runtime.record_client(name, version);
    }
}
