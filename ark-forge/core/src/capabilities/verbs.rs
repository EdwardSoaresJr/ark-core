//! Generic capability verbs — Forge Core must never expose product-domain actions.

pub const GENERIC_VERBS: &[&str] = &[
    "read_file",
    "open_url",
    "open_application",
    "run_command",
    "git_status",
    "list_processes",
];

pub fn is_generic_verb(action: &str) -> bool {
    GENERIC_VERBS.contains(&action)
}

/// Rejects product-domain or engineering-specific action names at the Core boundary.
pub fn validate_action(action: &str) -> Result<(), String> {
    if !is_generic_verb(action) {
        return Err(format!(
            "action '{action}' is not a generic Core verb; allowed: {}",
            GENERIC_VERBS.join(", ")
        ));
    }

    let forbidden = [
        "open_ark_voice",
        "test_provisioning",
        "review_pr",
        "deploy_",
        "provisioning",
        "milestone",
        "open_cursor", // use open_application + application param
    ];

    let lower = action.to_lowercase();
    for term in forbidden {
        if lower.contains(term) {
            return Err(format!(
                "action '{action}' leaks product domain into Forge Core"
            ));
        }
    }

    Ok(())
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn accepts_generic_verbs() {
        for verb in GENERIC_VERBS {
            assert!(validate_action(verb).is_ok(), "verb {verb}");
        }
    }

    #[test]
    fn rejects_unknown_action() {
        assert!(validate_action("open_ark_voice").is_err());
        assert!(validate_action("test_provisioning").is_err());
        assert!(validate_action("review_pr1").is_err());
    }

    #[test]
    fn rejects_domain_leak_in_generic_verb_slot() {
        // open_cursor is a product shortcut — use open_application + application param
        assert!(validate_action("open_cursor").is_err());
    }
}
