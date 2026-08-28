//! Browser invoke — generic URL open only. No product-domain URLs.

use anyhow::{Context, Result, bail};

pub fn open_url(url: &str) -> Result<()> {
    let url = url.trim();
    if url.is_empty() {
        bail!("url argument must not be empty");
    }
    if !(url.starts_with("http://") || url.starts_with("https://")) {
        bail!("url argument must be an http or https URL");
    }
    open::that(url).context("open_url failed")
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn rejects_empty_url() {
        assert!(open_url("").is_err());
        assert!(open_url("   ").is_err());
    }

    #[test]
    fn rejects_non_http_scheme() {
        assert!(open_url("file:///tmp").is_err());
        assert!(open_url("javascript:alert(1)").is_err());
    }
}
