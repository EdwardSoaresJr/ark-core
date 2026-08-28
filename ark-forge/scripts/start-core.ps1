$ForgeRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
$RepoRoot = Resolve-Path (Join-Path $ForgeRoot "..")

Write-Host "Forge Core — repo: $RepoRoot"
$env:FORGE_REPO_PATH = $RepoRoot

Push-Location $ForgeRoot
try {
    if (-not (Get-Command cargo -ErrorAction SilentlyContinue)) {
        Write-Error "Rust not installed. Install from https://rustup.rs/"
        exit 1
    }
    cargo run -p forge-server
} finally {
    Pop-Location
}
