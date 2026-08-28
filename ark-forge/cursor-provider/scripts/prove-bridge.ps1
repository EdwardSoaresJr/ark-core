param(
    [string]$Capability = "cursor.editor.selection.read",
    [int]$BridgePort = 9471
)

$ErrorActionPreference = "Stop"

$configPath = Join-Path $env:USERPROFILE ".ark\bridge.json"
if (-not (Test-Path $configPath)) {
    throw "Bridge config not found at $configPath"
}

$config = Get-Content $configPath -Raw | ConvertFrom-Json
$token = $config.bridge_secret
$headers = @{
    Authorization = "Bearer $token"
    "Content-Type" = "application/json"
}
$bridgeUrl = "http://127.0.0.1:$BridgePort"

Write-Host "Providers"
Invoke-RestMethod -Uri "$bridgeUrl/bridge/providers" -Headers $headers | ConvertTo-Json -Depth 6

Write-Host "`nObserve via Bridge: $Capability"
$body = @{ capability = $Capability } | ConvertTo-Json
Invoke-RestMethod `
    -Method Post `
    -Uri "$bridgeUrl/bridge/observe" `
    -Headers $headers `
    -Body $body | ConvertTo-Json -Depth 8
