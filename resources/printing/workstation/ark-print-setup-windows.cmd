<# : ARK Print Setup for Windows. Double-click this file. These first lines start PowerShell.
@echo off
setlocal
set "ARK_SETUP_SELF=%~f0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "& ([ScriptBlock]::Create([IO.File]::ReadAllText($env:ARK_SETUP_SELF))) %*"
set "ARK_EXIT=%ERRORLEVEL%"
echo.
pause
exit /b %ARK_EXIT%
#>
# ARK Print Setup for Windows, version @@ARK_SETUP_VERSION@@, for @@ARK_SHOP_HOST@@.
#
# Lets QZ Tray trust this shop's ARK printing certificate, so printing from ARK needs no Allow prompt.
# It adds this shop's public trust root to QZ Tray's "authcert.override" setting, which is what QZ's own
# "ca" provisioning step does. Nothing else changes: other entries in that setting, other applications'
# certificates, override.crt and every other QZ setting stay as they are. No private key is involved.
#
#   Double-click                         set up (running it again changes nothing)
#   ARK-Print-Setup-....cmd -Status      report only, change nothing
#   ARK-Print-Setup-....cmd -Uninstall   remove ARK's entry for this shop
#
# Exit codes: 0 done, 2 usage, 3 QZ Tray not installed, 4 QZ Tray too old, 5 QZ Tray settings not found,
# 6 certificate check failed, 7 administrator approval declined, 8 settings layout ARK will not change, 1 other.
param(
    [switch] $Status,
    [switch] $Uninstall,
    [switch] $Elevated,
    [string] $UserQzDir = '',
    [string] $ReportPath = ''
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2

$ArkSetupVersion = '@@ARK_SETUP_VERSION@@'
$ArkShopHost = '@@ARK_SHOP_HOST@@'
$ArkRootSha256 = '@@ARK_ROOT_SHA256@@'
$ArkRootSha1 = '@@ARK_ROOT_SHA1@@'
$ArkSetupUrl = '@@ARK_SETUP_URL@@'
$QzMinVersion = [version] '2.1.6'
$ArkRootPem = @'
@@ARK_ROOT_PEM@@
'@

# Locations. The ARK_* environment overrides exist for testing on a copy of QZ Tray.
function Get-Override([string] $name, [string] $default) { $v = [Environment]::GetEnvironmentVariable($name); if ($v) { $v } else { $default } }
$IsWindowsHost = [Environment]::OSVersion.Platform -eq [PlatformID]::Win32NT
$ProgramDataDir = if ($env:ProgramData) { $env:ProgramData } else { '/tmp' }
$TrustDir = Get-Override 'ARK_TRUST_DIR' (Join-Path $ProgramDataDir 'ARK\Printing\trust')
$QzSharedDir = Get-Override 'ARK_QZ_SHARED_DIR' (Join-Path $ProgramDataDir 'qz')
if (-not $UserQzDir) { $UserQzDir = Get-Override 'ARK_QZ_USER_DIR' $(if ($env:APPDATA) { Join-Path $env:APPDATA 'qz' } else { '' }) }
$NoRestart = (Get-Override 'ARK_NO_RESTART' '0') -eq '1'
$RequireAdmin = (Get-Override 'ARK_REQUIRE_ADMIN' '1') -eq '1'
$TrustFileName = "ark-$ArkShopHost-$($ArkRootSha256.Substring(0, 16)).crt"
$TrustFile = Join-Path $TrustDir $TrustFileName
# QZ Tray reads this path from qz-tray.properties; forward slashes need no escaping there.
$Token = $TrustFile -replace '\\', '/'
$TrustDirToken = $TrustDir -replace '\\', '/'
$HostPrefix = "ark-$ArkShopHost-"

function Say([string] $text) { Write-Host $text; if ($ReportPath) { Add-Content -LiteralPath $ReportPath -Value $text } }
function Ok([string] $text) { Say "  OK      $text" }
function Note([string] $text) { Say "  NOTE    $text" }
function Problem([string] $text) { Say "  PROBLEM $text" }
function Stop-Setup([int] $code, [string] $text) { Say ''; Say $text; exit $code }

$Mode = if ($Status) { 'status' } elseif ($Uninstall) { 'uninstall' } else { 'install' }

if (-not $Elevated) {
    Say "ARK Print Setup $ArkSetupVersion for $ArkShopHost"
    Say ''
}

# --- The certificate this setup installs -------------------------------------------------------------
$base64 = ($ArkRootPem -replace '-----(BEGIN|END) CERTIFICATE-----', '') -replace '\s', ''
try {
    $rootBytes = [Convert]::FromBase64String($base64)
    $rootCert = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2 -ArgumentList (, $rootBytes)
} catch {
    Stop-Setup 6 'The certificate inside this setup cannot be read. Download the setup again from ARK.'
}
$sha = [System.Security.Cryptography.SHA256]::Create()
$actualSha256 = (($sha.ComputeHash($rootBytes) | ForEach-Object { $_.ToString('x2') }) -join '')
if ($actualSha256 -ne $ArkRootSha256) {
    Stop-Setup 6 'The certificate inside this setup does not match its expected fingerprint. Download the setup again from ARK.'
}
$isCa = $false
foreach ($ext in $rootCert.Extensions) {
    if ($ext -is [System.Security.Cryptography.X509Certificates.X509BasicConstraintsExtension]) { $isCa = $ext.CertificateAuthority }
}
if (-not $isCa) { Stop-Setup 6 'The certificate inside this setup is not a trust root. Download the setup again from ARK.' }
$now = Get-Date
if ($rootCert.NotBefore -gt $now -or $rootCert.NotAfter -lt $now) {
    Stop-Setup 6 'The certificate inside this setup is outside its validity dates. ARK needs a new printing certificate; contact ARK support.'
}
if (-not $Elevated) {
    Ok "Certificate checked: $($rootCert.Subject)"
    Ok "SHA-256 fingerprint: $ArkRootSha256"
}

# --- QZ Tray -----------------------------------------------------------------------------------------
function Find-QzTray {
    $dir = [Environment]::GetEnvironmentVariable('ARK_QZ_DIR')
    $version = [Environment]::GetEnvironmentVariable('ARK_QZ_VERSION')
    if ($dir) { return @{ Dir = $dir; Version = $version } }
    if ($IsWindowsHost) {
        foreach ($view in @([Microsoft.Win32.RegistryView]::Registry64, [Microsoft.Win32.RegistryView]::Registry32)) {
            try {
                $hklm = [Microsoft.Win32.RegistryKey]::OpenBaseKey([Microsoft.Win32.RegistryHive]::LocalMachine, $view)
                $key = $hklm.OpenSubKey('SOFTWARE\QZ Tray')
                $uninstallKey = $hklm.OpenSubKey('SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\QZ Tray')
                if ($key -and $key.GetValue('')) { $dir = [string] $key.GetValue('') }
                if ($uninstallKey) {
                    $name = [string] $uninstallKey.GetValue('DisplayName')
                    if ($name -match '(\d+(\.\d+){1,3})') { $version = $Matches[1] }
                    if (-not $dir -and $uninstallKey.GetValue('UninstallString')) { $dir = Split-Path -Parent ([string] $uninstallKey.GetValue('UninstallString')).Trim('"') }
                }
                if ($dir) { break }
            } catch { }
        }
        if (-not $dir -and $env:ProgramFiles -and (Test-Path -LiteralPath (Join-Path $env:ProgramFiles 'QZ Tray\qz-tray.exe'))) { $dir = Join-Path $env:ProgramFiles 'QZ Tray' }
    }
    if ($dir -and -not $version -and (Test-Path -LiteralPath (Join-Path $dir 'qz-tray.exe'))) {
        $version = (Get-Item -LiteralPath (Join-Path $dir 'qz-tray.exe')).VersionInfo.ProductVersion
    }
    if ($dir -and (Test-Path -LiteralPath $dir)) { return @{ Dir = $dir; Version = $version } }
    return $null
}

$qz = Find-QzTray
if (-not $qz) {
    Say 'QZ Tray is not installed on this computer.'
    Say "Install it from QZ's official download page (https://qz.io/download/), start it once, then run this setup again."
    if ($Mode -eq 'install' -and -not $Elevated -and $IsWindowsHost -and -not $env:ARK_QZ_DIR) { try { Start-Process 'https://qz.io/download/' } catch { } }
    exit 3
}
$QzDir = $qz.Dir
$parsedVersion = $null
if (-not $qz.Version -or -not [version]::TryParse((($qz.Version -split '[^0-9.]')[0]).Trim('.'), [ref] $parsedVersion)) {
    Stop-Setup 1 "Could not read QZ Tray's version in $QzDir."
}
if ($parsedVersion -lt $QzMinVersion) {
    Stop-Setup 4 "QZ Tray $parsedVersion is too old for this setup (needs $QzMinVersion or newer). Update it from https://qz.io/download/ and run this setup again."
}
if (-not $Elevated) { Ok "QZ Tray $parsedVersion found in $QzDir" }

# QZ Tray reads the first qz-tray.properties it finds in this order (qz.installer.certificate.CertificateManager).
$Props = $null
foreach ($candidate in @((Join-Path $QzDir 'qz-tray.properties'), (Join-Path $QzSharedDir 'qz-tray.properties'), $(if ($UserQzDir) { Join-Path $UserQzDir 'qz-tray.properties' }))) {
    if ($candidate -and (Test-Path -LiteralPath $candidate -PathType Leaf)) { $Props = $candidate; break }
}
if (-not $Props) { Stop-Setup 5 "QZ Tray's settings file (qz-tray.properties) was not found. Start QZ Tray once, quit it, then run this setup again." }
if (-not $Elevated) { Ok "QZ Tray settings: $Props" }

function Get-FileSha256([string] $path) {
    $text = [IO.File]::ReadAllText($path)
    $b64 = ($text -replace '-----(BEGIN|END) CERTIFICATE-----', '') -replace '\s', ''
    try { (($sha.ComputeHash([Convert]::FromBase64String($b64)) | ForEach-Object { $_.ToString('x2') }) -join '') } catch { '' }
}

$overrideCrt = Join-Path $QzDir 'override.crt'
if (-not $Elevated -and (Test-Path -LiteralPath $overrideCrt)) {
    if ((Get-FileSha256 $overrideCrt) -eq $ArkRootSha256) { Note "QZ Tray's override.crt is this shop's root (an earlier manual setup). It is left as it is." }
    else { Note "QZ Tray's override.crt belongs to another application. It is left as it is." }
}

# --- authcert.override editor ------------------------------------------------------------------------
# Rewrites only the authcert.override value. Values are unescaped the way java.util.Properties does,
# then split the way QZ does (";" separates paths, "^" escapes, "|" starts per-path options); kept
# entries are written back exactly as they were.
function Normalize-Path([string] $path) { ($path -replace '\\', '/').Trim() }
function Test-ArkEntry([string] $path) { (Normalize-Path $path).StartsWith("$TrustDirToken/ark-", [StringComparison]::OrdinalIgnoreCase) }
function Test-ThisHost([string] $path) { [IO.Path]::GetFileName((Normalize-Path $path)).StartsWith($HostPrefix, [StringComparison]::OrdinalIgnoreCase) }

function Split-OverrideValue([string] $value) {
    $chars = New-Object System.Collections.Generic.List[object]
    for ($i = 0; $i -lt $value.Length; $i++) {
        $c = $value[$i]
        if ($c -eq '\' -and $i -lt $value.Length - 1) {
            $raw = $value.Substring($i, 2); $i++
            $u = switch ($raw[1]) { 't' { "`t" } 'n' { "`n" } 'r' { "`r" } 'f' { "`f" } default { [string] $raw[1] } }
        } else { $raw = [string] $c; $u = [string] $c }
        $chars.Add(@($u, $raw))
    }
    $tokens = New-Object System.Collections.Generic.List[object]
    $tok = ''; $norm = ''; $esc = $false; $inOpts = $false
    for ($k = 0; $k -le $chars.Count; $k++) {
        if ($k -lt $chars.Count -and -not ($chars[$k][0] -eq ';' -and -not $esc)) {
            $u = $chars[$k][0]; $tok += $chars[$k][1]
            if ($esc) { if (-not $inOpts) { $norm += $u }; $esc = $false }
            elseif ($u -eq '^') { $esc = $true }
            elseif ($u -eq '|') { $inOpts = $true }
            elseif (-not $inOpts) { $norm += $u }
            continue
        }
        if ($tok -ne '') { $tokens.Add(@($tok, $norm.Trim())) }
        $tok = ''; $norm = ''; $esc = $false; $inOpts = $false
    }
    return , $tokens
}

function Test-ContinuedLine([string] $line) { $n = 0; while ($n -lt $line.Length -and $line[$line.Length - 1 - $n] -eq '\') { $n++ }; ($n % 2) -eq 1 }

# Returns @{ Lines; Changes } or exits with code 8 when the layout is ambiguous.
function Edit-Properties([string[]] $lines, [string] $editMode, [string] $scope) {
    $out = New-Object System.Collections.Generic.List[string]
    $changes = New-Object System.Collections.Generic.List[object]
    $seen = $false; $trusted = $false; $continued = $false
    foreach ($original in $lines) {
        $line = $original
        if (-not $continued -and $line -match '^[ \t]*authcert\.override([ \t]*[=:][ \t]*|[ \t]+|$)') {
            if (Test-ContinuedLine $line) { Stop-Setup 8 "QZ Tray's authcert.override setting continues over several lines. ARK will not change it automatically; contact ARK support." }
            if ($seen) { Stop-Setup 8 "QZ Tray's settings contain authcert.override more than once. ARK will not change them automatically; contact ARK support." }
            $seen = $true
            $prefix = $Matches[0]; $value = $line.Substring($prefix.Length)
            if ($prefix -notmatch '[=:][ \t]*$') { $prefix = $prefix.TrimEnd() + '=' }
            $kept = New-Object System.Collections.Generic.List[string]; $written = $false
            foreach ($t in (Split-OverrideValue $value)) {
                $isOurs = (Normalize-Path $t[1]) -ieq $Token
                if ($scope -ne 'none' -and (Test-ArkEntry $t[1]) -and ($scope -eq 'all' -or (Test-ThisHost $t[1])) -and -not ($editMode -eq 'add' -and $isOurs)) {
                    $changes.Add(@('removed', $t[1]))
                } else {
                    if ($isOurs) { $written = $true }
                    $changes.Add(@('kept', $t[1])); $kept.Add($t[0])
                }
            }
            if ($editMode -eq 'add' -and -not $written) { $kept.Add($Token); $changes.Add(@('added', $Token)) }
            if ($kept.Count -gt 0) { $out.Add($prefix + ($kept -join ';')) }
            $continued = $false
            continue
        }
        if (-not $continued -and $line -match '^[ \t]*trustedRootCert([ \t]*[=:]|[ \t]+|$)') { $trusted = $true }
        $out.Add($original)
        $continued = ($line -notmatch '^[ \t]*[#!]') -and (Test-ContinuedLine $line)
    }
    if (-not $seen -and $editMode -eq 'add') {
        if ($trusted) { Stop-Setup 8 "QZ Tray's settings use a trustedRootCert entry. ARK will not change them automatically; contact ARK support." }
        $out.Add("authcert.override=$Token"); $changes.Add(@('added', $Token))
    }
    return @{ Lines = $out; Changes = $changes }
}

$latin1 = [Text.Encoding]::GetEncoding(28591)
function Read-Properties([string] $path) {
    $text = [IO.File]::ReadAllText($path, $latin1)
    $newline = if ($text.Contains("`r`n")) { "`r`n" } else { "`n" }
    $endsWithNewline = $text.EndsWith("`n")
    $lines = $text -split "`r?`n"
    if ($endsWithNewline) { $lines = $lines[0..($lines.Count - 2)] }
    return @{ Lines = [string[]] $lines; Newline = $newline }
}

function Test-QzRunning {
    if (-not $IsWindowsHost) { return $false }
    $p = @(Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object { $_.Name -eq 'qz-tray.exe' -or ($_.CommandLine -and $_.CommandLine -like '*qz-tray.jar*') })
    return $p.Count -gt 0
}

if ($Mode -eq 'status') {
    $file = Read-Properties $Props
    $result = Edit-Properties $file.Lines 'remove' 'none'
    $foundThis = $false
    foreach ($c in $result.Changes) {
        $p = $c[1]
        if ((Normalize-Path $p) -ieq $Token) { $foundThis = $true }
        elseif ((Test-ArkEntry $p) -and (Test-ThisHost $p)) { Note "Older ARK certificate for this shop: $p" }
        elseif (Test-ArkEntry $p) { Note "ARK certificate for another shop: $p" }
        else { Note "Trust root of another application (kept): $p" }
    }
    $code = 1
    if ($foundThis -and (Test-Path -LiteralPath $TrustFile) -and (Get-FileSha256 $TrustFile) -eq $ArkRootSha256) { Ok "QZ Tray is set to trust this shop's ARK certificate."; $code = 0 }
    else { Problem "QZ Tray is not yet set to trust this shop's ARK certificate." }
    if (Test-QzRunning) { Ok 'QZ Tray is running.' } else { Note 'QZ Tray is not running.' }
    exit $code
}

function Test-Admin {
    if (-not $IsWindowsHost) { return $true }
    $principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

# --- Ask Windows for administrator approval, then change the settings in that window -----------------
if ($RequireAdmin -and -not (Test-Admin)) {
    if ($Elevated) { Stop-Setup 7 'Administrator rights are required.' }
    Say ''
    Say 'Windows will ask for administrator approval to change QZ Tray settings...'
    $report = Join-Path ([IO.Path]::GetTempPath()) ("ark-print-setup-" + [Guid]::NewGuid().ToString('N') + '.log')
    $self = $env:ARK_SETUP_SELF -replace "'", "''"
    $command = "& ([ScriptBlock]::Create([IO.File]::ReadAllText('$self'))) -Elevated -UserQzDir '$($UserQzDir -replace "'", "''")' -ReportPath '$($report -replace "'", "''")'"
    if ($Uninstall) { $command += ' -Uninstall' }
    $encoded = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($command))
    try {
        $child = Start-Process -FilePath 'powershell.exe' -Verb RunAs -Wait -PassThru -WindowStyle Hidden -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-EncodedCommand', $encoded)
    } catch {
        Stop-Setup 7 'Administrator approval was declined. Nothing was changed. Run the setup again and choose Yes.'
    }
    $changed = $false
    if (Test-Path -LiteralPath $report) {
        foreach ($reported in Get-Content -LiteralPath $report) { if ($reported -eq '#ark-changed') { $changed = $true } else { Write-Host $reported } }
        Remove-Item -LiteralPath $report -Force -ErrorAction SilentlyContinue
    }
    if ($child.ExitCode -ne 0) { exit $child.ExitCode }
} else {
    if ($Mode -eq 'install') {
        $printingDir = Split-Path -Parent $TrustDir
        New-Item -ItemType Directory -Force -Path $TrustDir | Out-Null
        if ($IsWindowsHost -and $RequireAdmin) {
            # Only SYSTEM and Administrators may change ARK's trust roots; everyone may read them.
            & icacls.exe $printingDir /inheritance:r /grant:r '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' '*S-1-5-32-545:(OI)(CI)RX' /T /Q | Out-Null
            & icacls.exe $printingDir /setowner '*S-1-5-32-544' /T /Q | Out-Null
        }
        $pemText = (($ArkRootPem.Trim()) -replace "`r`n", "`n") + "`n"
        if (-not (Test-Path -LiteralPath $TrustFile) -or ([IO.File]::ReadAllText($TrustFile) -replace "`r`n", "`n") -ne $pemText) {
            [IO.File]::WriteAllText($TrustFile, $pemText, $latin1)
        }
        Ok "Certificate stored: $TrustFile"
    }

    $file = Read-Properties $Props
    $result = Edit-Properties $file.Lines $(if ($Mode -eq 'install') { 'add' } else { 'remove' }) 'host'
    $changed = $false
    foreach ($c in $result.Changes) {
        switch ($c[0]) {
            'added' { $changed = $true; Ok "Added this shop's ARK certificate to QZ Tray's trusted roots." }
            'removed' {
                $changed = $true
                if ($Mode -eq 'install') { Note "Replaced ARK's previous certificate for this shop: $($c[1])" } else { Ok "Removed: $($c[1])" }
                $old = Join-Path $TrustDir ([IO.Path]::GetFileName((Normalize-Path $c[1])))
                if (Test-Path -LiteralPath $old) { Remove-Item -LiteralPath $old -Force }
            }
            'kept' { if ((Normalize-Path $c[1]) -ine $Token) { Note "Kept: $($c[1])" } }
        }
    }

    if ($changed) {
        $backup = "$Props.ark-backup-$((Get-Date).ToUniversalTime().ToString('yyyyMMddTHHmmssZ'))-$PID"
        Copy-Item -LiteralPath $Props -Destination $backup -Force
        Ok "Backup of the previous settings: $backup"
        Get-ChildItem -LiteralPath (Split-Path -Parent $Props) -Filter ((Split-Path -Leaf $Props) + '.ark-backup-*') |
            Sort-Object LastWriteTime -Descending | Select-Object -Skip 5 | Remove-Item -Force -ErrorAction SilentlyContinue
        $text = ($result.Lines -join $file.Newline) + $file.Newline
        # Written in place so the file keeps its owner and permissions.
        [IO.File]::WriteAllText($Props, $text, $latin1)
        Ok 'QZ Tray settings updated.'
    } elseif ($Mode -eq 'install') {
        Ok 'Already set up. Nothing to change.'
    } else {
        Ok "ARK's entry for this shop was not present. Nothing to change."
    }

    if ($Mode -eq 'uninstall' -and (Test-Path -LiteralPath $TrustDir) -and -not (Get-ChildItem -LiteralPath $TrustDir)) { Remove-Item -LiteralPath $TrustDir -Force }
    if ($Elevated) {
        if ($changed -and $ReportPath) { Add-Content -LiteralPath $ReportPath -Value '#ark-changed' }
        exit 0
    }
}

# --- Restart QZ Tray as this user so it reads the change, then confirm it loaded the certificate ----
$qzLog = if ($UserQzDir) { Join-Path $UserQzDir 'debug.log' } else { '' }
if ($NoRestart -or -not $IsWindowsHost) {
    Note 'QZ Tray was not restarted.'
} elseif ($changed -or -not (Test-QzRunning)) {
    $logLines = 0
    if ($qzLog -and (Test-Path -LiteralPath $qzLog)) { $logLines = @(Get-Content -LiteralPath $qzLog).Count }
    if (Test-QzRunning) {
        Say ''; Say 'Restarting QZ Tray...'
        Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'qz-tray.exe' -or ($_.CommandLine -and $_.CommandLine -like '*qz-tray.jar*') } |
            ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
        for ($i = 0; $i -lt 10 -and (Test-QzRunning); $i++) { Start-Sleep -Seconds 1 }
    } else {
        Say ''; Say 'Starting QZ Tray...'
    }
    Start-Process -FilePath (Join-Path $QzDir 'qz-tray.exe') | Out-Null
    if ($Mode -eq 'install' -and $qzLog) {
        $loaded = ''
        for ($i = 0; $i -lt 45 -and -not $loaded; $i++) {
            Start-Sleep -Seconds 1
            if (-not (Test-Path -LiteralPath $qzLog)) { continue }
            $fresh = @(Get-Content -LiteralPath $qzLog | Select-Object -Skip $logLines)
            if ($fresh | Where-Object { $_ -like '*Adding CA certificate*' -and $_ -like "*($ArkRootSha1)*" }) { $loaded = 'yes' }
            elseif ($fresh | Where-Object { $_ -like '*Error loading CA cert*' -and $_ -like "*$TrustFileName*" }) { $loaded = 'error' }
        }
        switch ($loaded) {
            'yes' { Ok "QZ Tray loaded this shop's ARK certificate." }
            'error' { Stop-Setup 1 "QZ Tray could not load the certificate. Run this setup again; if it repeats, contact ARK support with QZ Tray's log ($qzLog)." }
            default { Note 'QZ Tray has not confirmed the certificate yet. Continue in ARK; if printing asks to Allow, run this setup again.' }
        }
    }
}

Say ''
if ($Mode -eq 'install') {
    Say "Done. Return to ARK's Set Up Printing page, click Check connection, then print a test label:"
    Say "  $ArkSetupUrl"
} else {
    Say "Done. ARK's certificate for $ArkShopHost is no longer trusted by QZ Tray on this computer."
}
exit 0
