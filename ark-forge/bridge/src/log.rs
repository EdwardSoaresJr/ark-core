use std::fs;
use std::path::PathBuf;

use anyhow::Result;
use tracing_subscriber::{fmt, layer::SubscriberExt, util::SubscriberInitExt, EnvFilter};

pub fn init_logging(service: &str) -> Result<()> {
    let log_dir = log_dir();
    fs::create_dir_all(&log_dir)?;
    let file_appender = tracing_appender::rolling::never(&log_dir, format!("{service}.log"));
    let (non_blocking, _guard) = tracing_appender::non_blocking(file_appender);

    // Leak guard so logs flush for process lifetime.
    Box::leak(Box::new(_guard));

    tracing_subscriber::registry()
        .with(EnvFilter::try_from_default_env().unwrap_or_else(|_| EnvFilter::new("info")))
        .with(fmt::layer().with_writer(non_blocking))
        .with(fmt::layer().with_writer(std::io::stderr))
        .init();

    Ok(())
}

pub fn log_dir() -> PathBuf {
    if let Ok(path) = std::env::var("ARK_LOG_DIR") {
        return PathBuf::from(path);
    }
    let home = std::env::var("USERPROFILE")
        .or_else(|_| std::env::var("HOME"))
        .unwrap_or_else(|_| ".".into());
    PathBuf::from(home).join(".ark").join("logs")
}

pub fn open_logs_in_explorer() {
    let dir = log_dir();
    let _ = fs::create_dir_all(&dir);
    #[cfg(windows)]
    {
        let _ = std::process::Command::new("explorer")
            .arg(dir)
            .spawn();
    }
}
