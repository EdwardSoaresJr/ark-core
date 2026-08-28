#[cfg(windows)]
use anyhow::{Context, Result};

#[cfg(windows)]
pub fn set_autostart(enabled: bool) -> Result<()> {
    use winreg::enums::HKEY_CURRENT_USER;
    use winreg::RegKey;

    let hkcu = RegKey::predef(HKEY_CURRENT_USER);
    let run = hkcu.open_subkey_with_flags(
        r"Software\Microsoft\Windows\CurrentVersion\Run",
        winreg::enums::KEY_SET_VALUE | winreg::enums::KEY_QUERY_VALUE,
    )?;

    if enabled {
        let exe = std::env::current_exe().context("resolve ARK Bridge executable")?;
        run.set_value("ARK Bridge", &exe.to_string_lossy().to_string())?;
    } else {
        let _ = run.delete_value("ARK Bridge");
    }

    Ok(())
}

#[cfg(windows)]
pub fn apply_saved_autostart(config: &crate::config::BridgeConfig) -> Result<()> {
    set_autostart(config.autostart_with_windows)
}

#[cfg(not(windows))]
pub fn set_autostart(_enabled: bool) -> anyhow::Result<()> {
    Ok(())
}

#[cfg(not(windows))]
pub fn apply_saved_autostart(_config: &crate::config::BridgeConfig) -> anyhow::Result<()> {
    Ok(())
}
