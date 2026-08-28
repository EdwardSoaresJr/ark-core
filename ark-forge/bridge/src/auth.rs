use axum::http::{HeaderMap, StatusCode};
use axum::response::{IntoResponse, Response};

use crate::config::BridgeConfig;

pub fn authorize(headers: &HeaderMap, config: &BridgeConfig) -> Result<(), Response> {
    let token = extract_bearer(headers).ok_or_else(unauthorized)?;
    if constant_time_eq(token.as_bytes(), config.bridge_secret.as_bytes()) {
        Ok(())
    } else {
        Err(unauthorized())
    }
}

fn extract_bearer(headers: &HeaderMap) -> Option<&str> {
    headers
        .get(axum::http::header::AUTHORIZATION)?
        .to_str()
        .ok()?
        .strip_prefix("Bearer ")
        .map(str::trim)
}

fn unauthorized() -> Response {
    (StatusCode::UNAUTHORIZED, "invalid or missing bearer token").into_response()
}

fn constant_time_eq(a: &[u8], b: &[u8]) -> bool {
    if a.len() != b.len() {
        return false;
    }
    a.iter()
        .zip(b.iter())
        .fold(0u8, |acc, (x, y)| acc | (x ^ y))
        == 0
}

#[cfg(test)]
mod tests {
    use super::*;
    use axum::http::HeaderValue;

    #[test]
    fn accepts_valid_bearer() {
        let config = BridgeConfig {
            bridge_id: "id".into(),
            bridge_secret: "secret".into(),
            forge_core_url: "http://127.0.0.1:9470".into(),
            listen_host: "127.0.0.1".into(),
            listen_port: 9471,
            forge_repo_path: None,
            autostart_forge_core: true,
            autostart_with_windows: false,
        };
        let mut headers = HeaderMap::new();
        headers.insert(
            axum::http::header::AUTHORIZATION,
            HeaderValue::from_static("Bearer secret"),
        );
        assert!(authorize(&headers, &config).is_ok());
    }

    #[test]
    fn rejects_missing_bearer() {
        let config = BridgeConfig {
            bridge_id: "id".into(),
            bridge_secret: "secret".into(),
            forge_core_url: "http://127.0.0.1:9470".into(),
            listen_host: "127.0.0.1".into(),
            listen_port: 9471,
            forge_repo_path: None,
            autostart_forge_core: true,
            autostart_with_windows: false,
        };
        assert!(authorize(&HeaderMap::new(), &config).is_err());
    }
}
