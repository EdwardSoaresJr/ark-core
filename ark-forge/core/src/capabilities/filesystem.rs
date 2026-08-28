use std::path::Path;

use anyhow::Result;

use crate::config::read_file;

pub fn read(path: &Path) -> Result<String> {
    read_file(path)
}

pub fn exists(path: &Path) -> bool {
    path.is_file()
}
