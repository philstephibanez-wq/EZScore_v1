param(
    [int]$Port = 8501
)

$ErrorActionPreference = "Stop"
$Project = Split-Path -Parent $PSScriptRoot
Set-Location $Project

Write-Host ""
Write-Host "========================================"
Write-Host " EZScore Launcher"
Write-Host "========================================"
Write-Host ""

# R23.4 legacy worker must not compete with the desktop worker.
$LegacyTask = Get-ScheduledTask -TaskName "EZScore STEM Worker" -ErrorAction SilentlyContinue
if ($LegacyTask) {
    Stop-ScheduledTask -TaskName "EZScore STEM Worker" -ErrorAction SilentlyContinue
    Disable-ScheduledTask -TaskName "EZScore STEM Worker" -ErrorAction SilentlyContinue | Out-Null
    Write-Host "[INFO] Legacy R23 STEM scheduled worker disabled."
}

& (Join-Path $PSScriptRoot "ensure_analysis_worker_token.ps1")

$PrepareRuntime = Join-Path $PSScriptRoot "prepare_analysis_runtime.ps1"
if (Test-Path $PrepareRuntime) {
    & $PrepareRuntime
}
& (Join-Path $PSScriptRoot "start_ezscore_web.ps1") -Port $Port

$env:EZSCORE_WORKER_URL = "http://127.0.0.1:$Port"

& (Join-Path $PSScriptRoot "start_analysis_worker_desktop.ps1")

$Url = "http://127.0.0.1:$Port/fr/login"

for ($i = 0; $i -lt 20; $i++) {
    try {
        $Response = Invoke-WebRequest -UseBasicParsing -Uri $Url -TimeoutSec 2
        if ($Response.StatusCode -ge 200 -and $Response.StatusCode -lt 500) {
            break
        }
    } catch {
        Start-Sleep -Milliseconds 500
    }
}

Start-Process $Url
Write-Host "[OK] Browser opened: $Url"
