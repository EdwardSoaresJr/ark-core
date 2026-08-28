# ARK Forge release build (Windows)
# Produces dist/ARK Forge/ with shippable executables + fresh installer.

param(
    [switch]$SkipFlutter,
    [switch]$BuildInstaller,
    [switch]$SkipBuild
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$Dist = Join-Path $Root "dist\ARK Forge"
$Packaging = Join-Path $Root "packaging"
$SetupExe = Join-Path $Root "dist\ARK Forge Setup.exe"
$Cargo = Join-Path $env:USERPROFILE ".cargo\bin\cargo.exe"
if (-not (Test-Path $Cargo)) { $Cargo = "cargo" }

function Get-GitSha {
    try {
        Push-Location $Root
        $sha = git rev-parse --short HEAD 2>$null
        if ($LASTEXITCODE -eq 0 -and $sha) { return $sha.Trim() }
    } finally {
        Pop-Location
    }
    return "unknown"
}

if (-not $SkipBuild) {
    Write-Host "==> Building Forge Core + ARK Bridge (release)" -ForegroundColor Cyan
    Push-Location $Root
    try {
        & $Cargo build --release -p forge-server -p ark-bridge
        if ($LASTEXITCODE -ne 0) { throw "cargo build failed" }
    } finally {
        Pop-Location
    }
}

Write-Host "==> Staging $Dist" -ForegroundColor Cyan
if (Test-Path $Dist) {
    Remove-Item -Recurse -Force $Dist
}
New-Item -ItemType Directory -Path $Dist -Force | Out-Null

$CoreSrc = Join-Path $Root "target\release\forge-core.exe"
$BridgeSrc = Join-Path $Root "target\release\ark-bridge.exe"

if (-not (Test-Path $CoreSrc)) { throw "Missing $CoreSrc - run without -SkipBuild" }
if (-not (Test-Path $BridgeSrc)) { throw "Missing $BridgeSrc - run without -SkipBuild" }

Copy-Item $CoreSrc (Join-Path $Dist "Forge Core.exe")
Copy-Item $BridgeSrc (Join-Path $Dist "ARK Bridge.exe")

$iconSrc = Join-Path $Packaging "assets\bridge.ico"
if (Test-Path $iconSrc) {
    Copy-Item $iconSrc (Join-Path $Dist "bridge.ico")
}

if (-not $SkipFlutter) {
    $AppDir = Join-Path $Root "app"
    if (Test-Path (Join-Path $AppDir "pubspec.yaml")) {
        Write-Host "==> Building ARK Forge Workbench (Flutter Windows)" -ForegroundColor Cyan
        Push-Location $AppDir
        try {
            flutter build windows --release 2>$null
            if ($LASTEXITCODE -eq 0) {
                $WorkbenchSrc = Join-Path $AppDir "build\windows\x64\runner\Release\ark_forge.exe"
                if (-not (Test-Path $WorkbenchSrc)) {
                    $WorkbenchSrc = Join-Path $AppDir "build\windows\x64\runner\Release\forge_app.exe"
                }
                if (Test-Path $WorkbenchSrc) {
                    Copy-Item $WorkbenchSrc (Join-Path $Dist "ARK Forge Workbench.exe")
                }
            } else {
                Write-Warning "Flutter build skipped or failed. Installer will ship Core + Bridge only."
            }
        } finally {
            Pop-Location
        }
    }
}

$builtAt = Get-Date -Format "yyyy-MM-dd HH:mm:ss K"
$gitSha = Get-GitSha
$manifest = @"
built_at=$builtAt
git_sha=$gitSha
forge_core=$((Get-Item (Join-Path $Dist 'Forge Core.exe')).LastWriteTimeUtc.ToString('o'))
ark_bridge=$((Get-Item (Join-Path $Dist 'ARK Bridge.exe')).LastWriteTimeUtc.ToString('o'))
features=phase-b-providers,ark-console-tray,cursor-reconnect
"@
Set-Content -Path (Join-Path $Dist "BUILD.txt") -Value $manifest -Encoding UTF8

Write-Host ""
Write-Host "Release staged ($gitSha @ $builtAt):" -ForegroundColor Green
Get-ChildItem $Dist | ForEach-Object { Write-Host "  $($_.Name)" }

$BridgeExe = Join-Path $Dist "ARK Bridge.exe"
Write-Host ""
Write-Host "Update installed copy (recommended after every build):" -ForegroundColor Yellow
Write-Host "  .\scripts\sync-installed-bridge.ps1"

$setupStale = $true
if (Test-Path $SetupExe) {
    $setupStale = (Get-Item $SetupExe).LastWriteTime -lt (Get-Item $BridgeExe).LastWriteTime
    if ($setupStale) {
        Write-Warning "dist/ARK Forge Setup.exe is OLDER than staged ARK Bridge.exe."
        Write-Warning "Do not reinstall Setup.exe until the installer is rebuilt."
    }
}

if ($BuildInstaller -or $setupStale) {
    $Iscc = @(
        "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
        "$env:ProgramFiles\Inno Setup 6\ISCC.exe"
    ) | Where-Object { Test-Path $_ } | Select-Object -First 1

    if (-not $Iscc) {
        if ($BuildInstaller) {
            Write-Warning "Inno Setup not found. Install from https://jrsoftware.org/isinfo.php"
        } elseif ($setupStale) {
            Write-Warning "Inno Setup not found - cannot auto-rebuild stale installer. Use -BuildInstaller after installing Inno Setup."
        }
    } else {
        Write-Host "==> Building installer" -ForegroundColor Cyan
        & $Iscc (Join-Path $Packaging "ark-forge-setup.iss")
        if (Test-Path $SetupExe) {
            Write-Host "Installer ready:" -ForegroundColor Green
            Write-Host "  $SetupExe"
        }
    }
}

Write-Host ""
Write-Host "Run product:" -ForegroundColor Yellow
Write-Host "  Start-Process `"$BridgeExe`""
