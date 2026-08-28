use sysinfo::{ProcessRefreshKind, RefreshKind, System};

pub fn cursor_running() -> bool {
    let mut system = System::new_with_specifics(
        RefreshKind::nothing().with_processes(ProcessRefreshKind::everything()),
    );
    system.refresh_processes(sysinfo::ProcessesToUpdate::All, true);

    system.processes().values().any(|p| {
        let name = p.name().to_string_lossy().to_ascii_lowercase();
        name.contains("cursor")
    })
}
