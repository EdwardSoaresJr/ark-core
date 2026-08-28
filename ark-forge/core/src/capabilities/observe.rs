use chrono::Utc;

/// RFC3339 timestamp for every Observe snapshot.
pub fn observed_at() -> String {
    Utc::now().to_rfc3339()
}
