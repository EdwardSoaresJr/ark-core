use std::path::{Path, PathBuf};
use std::sync::{Arc, Mutex};
use std::time::Duration;

use tokio::sync::watch;
use tracing::info;

use crate::process::ForgeCoreProcess;

pub struct AppLifecycle {
    shutdown: watch::Sender<bool>,
    forge_core: Mutex<Option<ForgeCoreProcess>>,
}

impl AppLifecycle {
    pub fn new() -> (Arc<Self>, watch::Receiver<bool>) {
        let (shutdown_tx, shutdown_rx) = watch::channel(false);
        (
            Arc::new(Self {
                shutdown: shutdown_tx,
                forge_core: Mutex::new(None),
            }),
            shutdown_rx,
        )
    }

    pub fn set_forge_core(&self, process: ForgeCoreProcess) {
        *self.forge_core.lock().unwrap() = Some(process);
    }

    pub fn quit(&self) {
        info!("ARK Bridge quitting");
        if let Some(process) = self.forge_core.lock().unwrap().take() {
            drop(process);
        }
        let _ = self.shutdown.send(true);

        std::thread::spawn(|| {
            std::thread::sleep(Duration::from_millis(300));
            std::process::exit(0);
        });
    }
}

pub fn detect_default_repo_path() -> Option<String> {
    if let Ok(path) = std::env::var("FORGE_REPO_PATH") {
        if is_valid_repo(Path::new(&path)) {
            return Some(path);
        }
    }

    let home = std::env::var("USERPROFILE")
        .or_else(|_| std::env::var("HOME"))
        .ok()?;

    let candidates = [
        PathBuf::from(&home)
            .join("PhpstormProjects")
            .join("arksmsv2"),
        PathBuf::from(&home).join("Projects").join("arksmsv2"),
        PathBuf::from(&home)
            .join("source")
            .join("repos")
            .join("arksmsv2"),
    ];

    for candidate in candidates {
        if is_valid_repo(&candidate) {
            return Some(candidate.display().to_string());
        }
    }

    None
}

pub fn is_valid_repo(path: &Path) -> bool {
    path.is_dir()
        && (path.join(".git").exists()
            || path.join("docs").join("engineering").exists()
            || path.join("ark-forge").is_dir())
}
