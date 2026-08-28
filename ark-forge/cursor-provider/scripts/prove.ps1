param(
    [string]$Capability = "cursor.editor.selection.read",
    [int]$Port = 9472
)

$ErrorActionPreference = "Stop"

$configPath = Join-Path $env:USERPROFILE ".ark\bridge.json"
if (-not (Test-Path $configPath)) {
    throw "Bridge config not found at $configPath"
}

$config = Get-Content $configPath -Raw | ConvertFrom-Json
$token = $config.bridge_secret
$baseUrl = "http://127.0.0.1:$Port"

Write-Host "Health"
Invoke-RestMethod -Uri "$baseUrl/health" | ConvertTo-Json -Depth 6

Write-Host "`nManifest"
$headers = @{ Authorization = "Bearer $token" }
Invoke-RestMethod -Uri "$baseUrl/manifest" -Headers $headers | ConvertTo-Json -Depth 8

Write-Host "`nObserve $Capability"
$body = @{ capability = $Capability } | ConvertTo-Json
Invoke-RestMethod `
    -Method Post `
    -Uri "$baseUrl/observe" `
    -Headers $headers `
    -ContentType "application/json" `
    -Body $body | ConvertTo-Json -Depth 8
