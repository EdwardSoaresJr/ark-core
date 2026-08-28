$ForgeRoot = Resolve-Path (Join-Path $PSScriptRoot "..")

Write-Host "ARK Bridge"
Push-Location $ForgeRoot
try {
    cargo run -p ark-bridge @args
} finally {
    Pop-Location
}
