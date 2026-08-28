//! Engineering Projection — derives EngineeringState from Forge Core capabilities.
//! Knows engineering vocabulary. Forge Core does not.

use std::fs;
use std::path::{Path, PathBuf};

use anyhow::Result;
use chrono::Utc;
use forge_core::capabilities::{filesystem, git, processes, GitStatusSnapshot};
use forge_core::ForgeConfig;
use regex::Regex;
use serde::Serialize;

#[derive(Debug, Serialize)]
pub struct EngineeringState {
    pub repo_path: String,
    pub generated_at: String,
    pub milestone: MilestoneProjection,
    pub active_pr: ActivePrProjection,
    pub git: GitProjection,
    pub workstation: WorkstationProjection,
    pub architecture: ArchitectureProjection,
    pub recent_review: Option<ReviewProjection>,
    /// HTTPS URL for repository remote — Workbench intent context only.
    #[serde(skip_serializing_if = "Option::is_none")]
    pub repository_web_url: Option<String>,
}

#[derive(Debug, Serialize)]
pub struct GitProjection {
    pub branch: String,
    pub clean: bool,
    pub repository_health: String,
    pub modified_count: usize,
    pub ahead: i32,
    pub behind: i32,
    pub modified_files: Vec<String>,
    pub observed_at: String,
}

fn project_git(snapshot: GitStatusSnapshot) -> GitProjection {
    GitProjection {
        branch: snapshot.branch,
        clean: !snapshot.dirty,
        repository_health: if snapshot.dirty {
            "Modified".into()
        } else {
            "Clean".into()
        },
        modified_count: snapshot.modified_count,
        ahead: snapshot.ahead,
        behind: snapshot.behind,
        modified_files: snapshot.modified_files,
        observed_at: snapshot.observed_at,
    }
}

#[derive(Debug, Serialize)]
pub struct MilestoneProjection {
    pub title: String,
    pub doc_slug: String,
}

#[derive(Debug, Serialize)]
pub struct ActivePrProjection {
    pub id: String,
    pub status: String,
}

#[derive(Debug, Serialize)]
pub struct WorkstationProjection {
    pub cursor_running: bool,
}

#[derive(Debug, Serialize)]
pub struct ArchitectureProjection {
    pub status: String,
}

#[derive(Debug, Serialize)]
pub struct ReviewProjection {
    pub title: String,
    pub status: String,
    pub date: String,
    pub filename: String,
}

pub fn build_engineering_state(config: &ForgeConfig) -> Result<EngineeringState> {
    let repo = &config.repo_path;
    let engineering = repo.join("docs").join("engineering");

    let milestone_md = read_repo_relative(repo, "docs/engineering/CURRENT_MILESTONE.md")?;
    let active_pr_md = read_repo_relative(repo, "docs/engineering/ACTIVE_PR.md")?;

    Ok(EngineeringState {
        repo_path: repo.display().to_string(),
        generated_at: Utc::now().to_rfc3339(),
        milestone: parse_milestone(&milestone_md),
        active_pr: parse_active_pr(&active_pr_md),
        git: project_git(git::observe(repo)?),
        workstation: WorkstationProjection {
            cursor_running: processes::cursor_running(),
        },
        architecture: ArchitectureProjection {
            status: if engineering.join("ARCHITECTURE.md").is_file() {
                "Frozen".into()
            } else {
                "Unknown".into()
            },
        },
        recent_review: parse_recent_review(&engineering.join("reviews"))?,
        repository_web_url: git::origin_web_url(repo).ok(),
    })
}

pub fn read_engineering_doc(repo: &Path, slug: &str) -> Result<(String, String)> {
    let relative = match slug {
        "current-milestone" => "docs/engineering/CURRENT_MILESTONE.md",
        "active-pr" => "docs/engineering/ACTIVE_PR.md",
        "implementation-log" => "docs/engineering/IMPLEMENTATION_LOG.md",
        "roadmap" => "docs/engineering/ROADMAP.md",
        "standards" => "docs/engineering/STANDARDS.md",
        "architecture" => "docs/engineering/ARCHITECTURE.md",
        other => anyhow::bail!("unknown engineering doc slug: {other}"),
    };

    let path = repo.join(relative);
    let content = filesystem::read(&path)?;
    Ok((relative.to_string(), content))
}

fn read_repo_relative(repo: &Path, relative: &str) -> Result<String> {
    filesystem::read(&repo.join(relative))
}

fn parse_milestone(content: &str) -> MilestoneProjection {
    MilestoneProjection {
        title: extract_bold(content).unwrap_or_else(|| "Unknown".into()),
        doc_slug: "current-milestone".into(),
    }
}

fn parse_active_pr(content: &str) -> ActivePrProjection {
    ActivePrProjection {
        id: extract_after_heading(content, "Current PR")
            .and_then(|b| extract_bold(&b))
            .unwrap_or_else(|| "Unknown".into()),
        status: extract_after_heading(content, "Status")
            .and_then(|b| extract_bold(&b))
            .unwrap_or_else(|| "Unknown".into()),
    }
}

fn extract_bold(content: &str) -> Option<String> {
    static RE: std::sync::OnceLock<Regex> = std::sync::OnceLock::new();
    RE.get_or_init(|| Regex::new(r"\*\*([^*]+)\*\*").expect("regex"))
        .captures(content)
        .and_then(|c| c.get(1))
        .map(|m| m.as_str().trim().to_string())
}

fn extract_after_heading(content: &str, heading: &str) -> Option<String> {
    let needle = format!("# {heading}");
    let mut in_section = false;
    let mut lines = Vec::new();
    for line in content.lines() {
        if line.starts_with("# ") {
            if in_section {
                break;
            }
            if line.trim_end() == needle {
                in_section = true;
            }
            continue;
        }
        if in_section {
            if line.starts_with("## ") {
                break;
            }
            lines.push(line);
        }
    }
    if lines.is_empty() {
        None
    } else {
        Some(lines.join("\n"))
    }
}

fn parse_recent_review(reviews_dir: &Path) -> Result<Option<ReviewProjection>> {
    if !reviews_dir.is_dir() {
        return Ok(None);
    }

    let mut files: Vec<PathBuf> = fs::read_dir(reviews_dir)?
        .filter_map(|e| e.ok())
        .map(|e| e.path())
        .filter(|p| {
            p.extension().is_some_and(|e| e == "md")
                && !p.file_name().is_some_and(|n| n == "README.md")
        })
        .collect();

    files.sort_by(|a, b| b.cmp(a));

    let Some(path) = files.first() else {
        return Ok(None);
    };

    let content = filesystem::read(path)?;
    let filename = path.file_name().unwrap().to_string_lossy().to_string();
    let title = content
        .lines()
        .find(|l| l.starts_with("# "))
        .map(|l| l.trim_start_matches("# ").trim().to_string())
        .unwrap_or(filename.clone());

    Ok(Some(ReviewProjection {
        title,
        status: parse_review_field(&content, "Status").unwrap_or_else(|| "Unknown".into()),
        date: parse_review_field(&content, "Date").unwrap_or_else(|| "Unknown".into()),
        filename,
    }))
}

fn parse_review_field(content: &str, field: &str) -> Option<String> {
    let prefix = format!("**{field}:**");
    content.lines().find_map(|line| {
        line.trim()
            .strip_prefix(&prefix)
            .map(|v| v.trim().to_string())
    })
}
