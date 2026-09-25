param(
    [int]$Port = 8501
)

$ErrorActionPreference = "Stop"

$Project = Split-Path -Parent $PSScriptRoot
Set-Location $Project

$RuntimeDir = Join-Path $Project "var\runtime"
New-Item -ItemType Directory -Force -Path $RuntimeDir | Out-Null
$StatusFile = Join-Path $RuntimeDir "launcher-status.json"

$LocalBaseUrl = "http://127.0.0.1:$Port"
$BrowserUrl = if ($env:EZSCORE_BROWSER_URL) {
    $env:EZSCORE_BROWSER_URL.Trim()
} else {
    "https://ezscore.logandplay.com/"
}

function Set-LauncherStatus {
    param(
        [Parameter(Mandatory=$true)][string]$Stage,
        [Parameter(Mandatory=$true)][string]$Message,
        [Parameter(Mandatory=$true)][int]$Percent,
        [ValidateSet("running","ready","error")][string]$State = "running",
        [string]$Detail = ""
    )

    $Payload = [ordered]@{
        state = $State
        stage = $Stage
        message = $Message
        percent = [Math]::Max(0, [Math]::Min(100, $Percent))
        detail = $Detail
        updated_at = (Get-Date).ToString("o")
    }

    $Tmp = "$StatusFile.tmp"
    $Payload | ConvertTo-Json -Depth 4 | Set-Content -Path $Tmp -Encoding UTF8
    Move-Item -Path $Tmp -Destination $StatusFile -Force
}

try {
    Set-LauncherStatus "init" "Initialisation..." 5

    Set-LauncherStatus "legacy" "Verification de l'ancien worker…" 12
    $LegacyTask = Get-ScheduledTask -TaskName "EZScore STEM Worker" -ErrorAction SilentlyContinue
    if ($null -ne $LegacyTask) {
        Stop-ScheduledTask -TaskName "EZScore STEM Worker" -ErrorAction SilentlyContinue
        Disable-ScheduledTask -TaskName "EZScore STEM Worker" -ErrorAction SilentlyContinue | Out-Null
    }

    Set-LauncherStatus "token" "Verification du canal Worker…" 20
    & (Join-Path $PSScriptRoot "ensure_analysis_worker_token.ps1") | Out-Null

    Set-LauncherStatus `
        "web-start" `
        "Demarrage du serveur local…" `
        34 `
        "running" `
        "php -S 127.0.0.1:$Port -t $Project\public"

    $WebOutput = & (Join-Path $PSScriptRoot "start_ezscore_web.ps1") -Port $Port
    $WebDetail = ($WebOutput -join "`n")

    Set-LauncherStatus "web-wait" "Attente du serveur local…" 50 "running" $WebDetail

    $HealthUrl = "$LocalBaseUrl/fr/login"
    $Ready = $false

    for ($i = 0; $i -lt 40; $i++) {
        try {
            $Response = Invoke-WebRequest -UseBasicParsing -Uri $HealthUrl -TimeoutSec 1
            if ($Response.StatusCode -ge 200 -and $Response.StatusCode -lt 500) {
                $Ready = $true
                break
            }
        }
        catch {
            Start-Sleep -Milliseconds 250
        }
    }

    if (-not $Ready) {
        throw "Le serveur local ne répond pas sur $HealthUrl. Voir var\log\ezscore-web.err.log."
    }

    Set-LauncherStatus "worker" "Ouverture de EZScore Analysis Worker..." 72 "running" "API locale : $LocalBaseUrl"
    $env:EZSCORE_WORKER_URL = $LocalBaseUrl
    & (Join-Path $PSScriptRoot "start_analysis_worker_desktop.ps1") | Out-Null

    Set-LauncherStatus "browser" "Ouverture de EZScore..." 90 "running" $BrowserUrl
    Start-Process $BrowserUrl

    Set-LauncherStatus `
        "ready" `
        "EZScore est pret." `
        100 `
        "ready" `
        "Serveur local : php -S 127.0.0.1:$Port -t $Project\public`nWorker : $LocalBaseUrl`nNavigateur : $BrowserUrl"
}
catch {
    Set-LauncherStatus "error" "Echec du demarrage." 100 "error" $_.Exception.Message
    exit 1
}
