use std::path::Path;
use std::process::Command;

use anyhow::{Context, Result};
use git2::{BranchType, Repository, Status, StatusOptions};
use serde::Serialize;

use super::observe;

/// Core Observe snapshot — workstation facts only. No engineering interpretation.
#[derive(Debug, Clone, Serialize, PartialEq, Eq)]
pub struct GitStatusSnapshot {
    pub branch: String,
    pub dirty: bool,
    pub modified_count: usize,
    pub ahead: i32,
    pub behind: i32,
    pub modified_files: Vec<String>,
    pub observed_at: String,
}

pub fn observe(repo_path: &Path) -> Result<GitStatusSnapshot> {
    let repo = Repository::open(repo_path)
        .with_context(|| format!("open git repo at {}", repo_path.display()))?;

    let branch = repo
        .head()
        .ok()
        .and_then(|h| h.shorthand().map(str::to_string))
        .unwrap_or_else(|| "detached".into());

    let mut opts = StatusOptions::new();
    opts.include_untracked(true);

    let statuses = repo.statuses(Some(&mut opts))?;
    let mut modified_files = Vec::new();

    for entry in statuses.iter() {
        if entry.status() == Status::CURRENT {
            continue;
        }
        if let Some(path) = entry.path() {
            modified_files.push(path.to_string());
        }
    }

    modified_files.sort();
    modified_files.dedup();

    let (ahead, behind) = upstream_ahead_behind(&repo).unwrap_or((0, 0));
    let dirty = !modified_files.is_empty();

    Ok(GitStatusSnapshot {
        branch,
        dirty,
        modified_count: modified_files.len(),
        ahead,
        behind,
        modified_files,
        observed_at: observe::observed_at(),
    })
}

fn upstream_ahead_behind(repo: &Repository) -> Result<(i32, i32)> {
    let head = repo.head()?.peel_to_commit()?;
    let branch = repo.find_branch(
        repo.head()?.shorthand().unwrap_or("main"),
        BranchType::Local,
    )?;
    let upstream = branch.upstream()?.into_reference();
    let upstream_commit = upstream.peel_to_commit()?;
    let (ahead, behind) = repo.graph_ahead_behind(head.id(), upstream_commit.id())?;
    Ok((ahead as i32, behind as i32))
}

pub fn origin_web_url(repo_path: &Path) -> Result<String> {
    let repo = Repository::open(repo_path)?;
    let remote = repo.find_remote("origin")?;
    let url = remote.url().context("origin has no url")?.trim_end_matches(".git");

    Ok(if url.starts_with("git@github.com:") {
        url.replacen("git@github.com:", "https://github.com/", 1)
    } else {
        url.to_string()
    })
}

pub fn tool_available(name: &str) -> bool {
    Command::new(name)
        .arg("--version")
        .output()
        .map(|o| o.status.success())
        .unwrap_or(false)
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::path::PathBuf;

    fn repo_root() -> PathBuf {
        PathBuf::from(env!("CARGO_MANIFEST_DIR"))
            .parent()
            .unwrap()
            .parent()
            .unwrap()
            .to_path_buf()
    }

    #[test]
    fn observe_includes_observed_at_and_branch() {
        let snapshot = observe(&repo_root()).expect("arksmsv2 is a git repo");
        assert!(!snapshot.observed_at.is_empty());
        assert!(!snapshot.branch.is_empty());
        assert_eq!(snapshot.dirty, snapshot.modified_count > 0);
    }
}
