# Build fresh release artifacts and sync into C:\Program Files\ARK Forge\
# Use this instead of re-running an old ARK Forge Setup.exe.

$ErrorActionPreference = "Stop"

$ForgeRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
$Dist = Join-Path $ForgeRoot "dist\ARK Forge"
$Install = Join-Path ${env:ProgramFiles} "ARK Forge"
$BuildScript = Join-Path $ForgeRoot "packaging\build-release.ps1"

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator
)

if (-not $isAdmin) {
    Write-Host "Elevating to build + update Program Files..." -ForegroundColor Yellow
    Start-Process powershell.exe -Verb RunAs -ArgumentList @(
        "-NoProfile", "-ExecutionPolicy", "Bypass", "-File", "`"$PSCommandPath`""
    ) | Out-Null
    exit 0
}

Write-Host "==> Release build (Forge Core + ARK Bridge)" -ForegroundColor Cyan
& $BuildScript -SkipFlutter
if ($LASTEXITCODE -ne 0) { throw "build-release.ps1 failed" }

if (-not (Test-Path (Join-Path $Dist "ARK Bridge.exe"))) {
    throw "Missing dist build after build-release.ps1"
}

if (-not (Test-Path $Install)) {
    throw "ARK Forge is not installed at $Install. Run a fresh dist/ARK Forge Setup.exe once, then use this script."
}

$buildInfo = Join-Path $Dist "BUILD.txt"
if (Test-Path $buildInfo) {
    Write-Host ""
    Get-Content $buildInfo
    Write-Host ""
}

Stop-Process -Name "ARK Bridge" -Force -ErrorAction SilentlyContinue
Stop-Process -Name "ark-bridge" -Force -ErrorAction SilentlyContinue
Stop-Process -Name "Forge Core" -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 1

foreach ($name in @("ARK Bridge.exe", "Forge Core.exe", "ARK Forge Workbench.exe", "BUILD.txt")) {
    $src = Join-Path $Dist $name
    if (Test-Path $src) {
        Copy-Item $src (Join-Path $Install $name) -Force
        Write-Host "Updated $name" -ForegroundColor Green
    }
}

$icon = Join-Path $Dist "bridge.ico"
if (Test-Path $icon) {
    Copy-Item $icon (Join-Path $Install "bridge.ico") -Force
}

Write-Host ""
Write-Host "Program Files ARK Forge synced." -ForegroundColor Green
Write-Host "Tray should include: Status, Pair, Logs, ARK Console, Settings, ..." -ForegroundColor Cyan
Start-Process (Join-Path $Install "ARK Bridge.exe")
