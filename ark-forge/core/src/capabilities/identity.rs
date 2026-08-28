use serde::{Deserialize, Serialize};

#[derive(Debug, Clone, Copy, PartialEq, Eq, Serialize, Deserialize)]
#[serde(rename_all = "lowercase")]
pub enum CapabilityMode {
    Invoke,
    Observe,
}

#[derive(Debug, Clone, Copy, PartialEq, Eq, Serialize, Deserialize)]
#[serde(rename_all = "lowercase")]
pub enum CapabilityStability {
    Stable,
    Experimental,
    Deprecated,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct CapabilityArgument {
    pub name: String,
    #[serde(rename = "type")]
    pub arg_type: String,
    pub required: bool,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub enum_values: Option<Vec<String>>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub description: Option<String>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct CapabilityResultSchema {
    #[serde(rename = "type")]
    pub result_type: String,
    pub description: String,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct CapabilityIdentity {
    pub id: String,
    pub display_name: String,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub description: Option<String>,
    pub domain: String,
    pub resource: String,
    pub operation: String,
    pub mode: CapabilityMode,
    pub version: u32,
    pub stability: CapabilityStability,
    pub permissions: Vec<String>,
    pub arguments: Vec<CapabilityArgument>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub result: Option<CapabilityResultSchema>,
    pub available: bool,
}

/// Permanent ID format: `forge.{domain}.{resource}.{operation}`
pub fn validate_capability_id(id: &str) -> Result<(), String> {
    let parts: Vec<&str> = id.split('.').collect();
    if parts.len() != 4 || parts[0] != "forge" {
        return Err(format!(
            "capability id '{id}' must match forge.{{domain}}.{{resource}}.{{operation}}"
        ));
    }

    for part in &parts[1..] {
        if part.is_empty() || !part.chars().all(|c| c.is_ascii_lowercase() || c == '_') {
            return Err(format!("capability id '{id}' contains invalid segment '{part}'"));
        }
    }

    let forbidden = [
        "ark",
        "lugsnplugs",
        "provisioning",
        "milestone",
        "voice",
        "open_ark",
    ];
    let lower = id.to_lowercase();
    for term in forbidden {
        if lower.contains(term) {
            return Err(format!("capability id '{id}' leaks product domain into Forge Core"));
        }
    }

    Ok(())
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn accepts_valid_capability_ids() {
        assert!(validate_capability_id("forge.browser.url.open").is_ok());
        assert!(validate_capability_id("forge.capability.registry.read").is_ok());
    }

    #[test]
    fn rejects_invalid_format() {
        assert!(validate_capability_id("shell").is_err());
        assert!(validate_capability_id("forge.browser.open_url").is_err());
    }

    #[test]
    fn rejects_product_domain_in_id() {
        assert!(validate_capability_id("forge.ark.voice.open").is_err());
    }
}
