use std::path::{Path, PathBuf};
use std::process::{Child, Command, Stdio};

use anyhow::{Context, Result};
use tracing::info;

pub struct ForgeCoreProcess {
    child: Option<Child>,
}

impl ForgeCoreProcess {
    pub fn ensure_running(repo_path: &Path) -> Result<Self> {
        if Self::health_check() {
            info!("Forge Core already running");
            return Ok(Self { child: None });
        }

        let exe = locate_forge_core_exe();
        info!(path = %exe.display(), "Starting Forge Core");

        let mut command = Command::new(&exe);
        command
            .env("FORGE_REPO_PATH", repo_path)
            .stdout(Stdio::null())
            .stderr(Stdio::null());

        let child = command
            .spawn()
            .with_context(|| format!("spawn Forge Core at {}", exe.display()))?;

        Ok(Self {
            child: Some(child),
        })
    }

    pub fn health_check() -> bool {
        reqwest::blocking::Client::new()
            .get(format!(
                "{}/health",
                std::env::var("FORGE_CORE_URL").unwrap_or_else(|_| "http://127.0.0.1:9470".into())
            ))
            .send()
            .map(|r| r.status().is_success())
            .unwrap_or(false)
    }
}

impl Drop for ForgeCoreProcess {
    fn drop(&mut self) {
        if let Some(mut child) = self.child.take() {
            let _ = child.kill();
        }
    }
}

pub fn locate_forge_core_exe() -> PathBuf {
    if let Ok(path) = std::env::var("FORGE_CORE_EXE") {
        return PathBuf::from(path);
    }

    if let Ok(current) = std::env::current_exe() {
        if let Some(dir) = current.parent() {
            for name in ["Forge Core.exe", "forge-core.exe"] {
                let candidate = dir.join(name);
                if candidate.is_file() {
                    return candidate;
                }
            }
        }
    }

    PathBuf::from("forge-core.exe")
}

pub fn show_pairing_dialog(title: &str, body: &str) {
    #[cfg(windows)]
    {
        use std::ffi::OsStr;
        use std::os::windows::ffi::OsStrExt;

        let wide: Vec<u16> = OsStr::new(body)
            .encode_wide()
            .chain(std::iter::once(0))
            .collect();
        let title_wide: Vec<u16> = OsStr::new(title)
            .encode_wide()
            .chain(std::iter::once(0))
            .collect();

        unsafe {
            windows_sys::Win32::UI::WindowsAndMessaging::MessageBoxW(
                std::ptr::null_mut(),
                wide.as_ptr(),
                title_wide.as_ptr(),
                0,
            );
        }
    }

    #[cfg(not(windows))]
    {
        println!("{title}\n{body}");
    }
}

pub fn open_settings_file(path: &str) {
    #[cfg(windows)]
    {
        let _ = Command::new("notepad").arg(path).spawn();
    }
    #[cfg(not(windows))]
    {
        println!("Open settings: {path}");
    }
}

/// Open ARK Console in an interactive PowerShell window.
pub fn open_ark_console(
    repo_path: Option<&Path>,
    bridge_url: &str,
    bridge_token: &str,
) {
    #[cfg(windows)]
    {
        let Some(repo) = repo_path.filter(|path| path.is_dir()) else {
            show_pairing_dialog(
                "ARK Bridge — ARK Console",
                "Set forge_repo_path in Settings before opening ARK Console.",
            );
            return;
        };

        let console_dir = repo.join("ark-forge").join("console");
        if !console_dir.is_dir() {
            show_pairing_dialog(
                "ARK Bridge — ARK Console",
                &format!(
                    "ARK Console folder not found:\n{}\n\nCheck forge_repo_path in Settings.",
                    console_dir.display()
                ),
            );
            return;
        }

        let dir = console_dir.to_string_lossy().replace('\'', "''");
        let url = bridge_url.replace('\'', "''");
        let token = bridge_token.replace('\'', "''");
        let venv_py = console_dir.join(".venv").join("Scripts").join("python.exe");
        let py_invoke = if venv_py.is_file() {
            "& '.\\.venv\\Scripts\\python.exe'"
        } else {
            "& python"
        };

        let script = format!(
            "Set-Location -LiteralPath '{dir}'; \
             $env:ARK_BRIDGE_URL = '{url}'; \
             $env:ARK_BRIDGE_TOKEN = '{token}'; \
             Write-Host 'ARK Console — type your question at the you> prompt.' -ForegroundColor Cyan; \
             if (-not (Test-Path '.env')) {{ \
               Write-Host 'Tip: copy console.env.example to .env and set OPENAI_API_KEY' -ForegroundColor Yellow \
             }}; \
             {py_invoke} -m ark_console; \
             if ($LASTEXITCODE -ne 0) {{ Write-Host ''; Read-Host 'Press Enter to close' }}"
        );

        let _ = Command::new("cmd")
            .args(["/C", "start", "", "powershell.exe", "-Command", &script])
            .spawn();
    }

    #[cfg(not(windows))]
    {
        let _ = (repo_path, bridge_url, bridge_token);
        eprintln!("ARK Console launcher is only available on Windows.");
    }
}
