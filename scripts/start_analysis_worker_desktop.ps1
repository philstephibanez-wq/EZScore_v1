$ErrorActionPreference = "Stop"

$Project = Split-Path -Parent $PSScriptRoot
Set-Location $Project

$Python = & (Join-Path $PSScriptRoot "find_analysis_python.ps1")
if (-not $Python) {
    throw "No compatible EZScore_v1 STEM Python found. Run scripts\prepare_analysis_runtime.ps1 first."
}

$Pythonw = Join-Path (Split-Path -Parent $Python) "pythonw.exe"
if (-not (Test-Path $Pythonw)) {
    $Pythonw = $Python
}

$App = Join-Path $Project "worker_app\ezscore_analysis_worker.pyw"
if (-not (Test-Path $App)) {
    throw "Desktop worker app missing: $App"
}

$Process = Start-Process `
    -FilePath $Pythonw `
    -ArgumentList @("`"$App`"") `
    -WorkingDirectory $Project `
    -PassThru

Start-Sleep -Milliseconds 700

if ($Process.HasExited) {
    throw "EZScore Analysis Worker exited immediately with code $($Process.ExitCode)."
}

Write-Host "[OK] EZScore Analysis Worker desktop launched (PID $($Process.Id))."
