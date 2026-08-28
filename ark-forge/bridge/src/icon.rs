use tray_icon::Icon;

/// ARK cerulean tray mark — works even when no .ico is bundled beside the exe.
pub fn default_tray_icon() -> Icon {
    const SIZE: u32 = 32;
    let mut rgba = Vec::with_capacity((SIZE * SIZE * 4) as usize);

    for y in 0..SIZE {
        for x in 0..SIZE {
            let dx = x as f32 - (SIZE as f32 / 2.0 - 0.5);
            let dy = y as f32 - (SIZE as f32 / 2.0 - 0.5);
            let distance = (dx * dx + dy * dy).sqrt();

            if distance <= 14.0 {
                let (r, g, b) = if distance <= 5.5 {
                    (102, 204, 255)
                } else {
                    (0, 153, 204)
                };
                rgba.extend_from_slice(&[r, g, b, 255]);
            } else {
                rgba.extend_from_slice(&[0, 0, 0, 0]);
            }
        }
    }

    Icon::from_rgba(rgba, SIZE, SIZE).expect("valid tray icon rgba")
}

pub fn load_tray_icon() -> Icon {
    if let Ok(exe) = std::env::current_exe() {
        if let Some(dir) = exe.parent() {
            for name in ["bridge.ico", "ARK Bridge.ico"] {
                let path = dir.join(name);
                if path.is_file() {
                    if let Ok(image) = image::open(&path) {
                        let rgba = image.to_rgba8();
                        let (width, height) = rgba.dimensions();
                        if let Ok(icon) = Icon::from_rgba(rgba.into_raw(), width, height) {
                            return icon;
                        }
                    }
                }
            }
        }
    }

    default_tray_icon()
}
