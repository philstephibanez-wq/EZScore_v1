$ErrorActionPreference = "Stop"

$Project = Split-Path -Parent $PSScriptRoot
$EnvFile = Join-Path $Project ".env.local"

if (-not (Test-Path $EnvFile)) {
    New-Item -ItemType File -Path $EnvFile -Force | Out-Null
}

$Content = Get-Content $EnvFile -Raw -ErrorAction SilentlyContinue
if ($Content -match '(?m)^\s*ANALYSIS_WORKER_TOKEN\s*=\s*(.+?)\s*$') {
    Write-Host "[OK] ANALYSIS_WORKER_TOKEN already configured."
    exit 0
}

$Bytes = New-Object byte[] 32
$Rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
try {
    $Rng.GetBytes($Bytes)
}
finally {
    $Rng.Dispose()
}

$Token = ([System.BitConverter]::ToString($Bytes)).Replace("-", "").ToLowerInvariant()

Add-Content -Path $EnvFile -Value "`r`n# EZScore Analysis Worker`r`nANALYSIS_WORKER_TOKEN=$Token" -Encoding UTF8

Write-Host "[OK] ANALYSIS_WORKER_TOKEN created in .env.local."
