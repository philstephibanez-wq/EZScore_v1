$ErrorActionPreference = "Stop"
$Project = Split-Path -Parent $PSScriptRoot
Set-Location $Project

& (Join-Path $PSScriptRoot "prepare_analysis_runtime.ps1")
& (Join-Path $PSScriptRoot "start_analysis_worker_desktop.ps1")

Write-Host "[OK] Desktop smoke test launched. A native Windows window must now be visible."
