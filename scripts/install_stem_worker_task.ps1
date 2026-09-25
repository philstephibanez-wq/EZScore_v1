$ErrorActionPreference = "Stop"

$Project = Split-Path -Parent $PSScriptRoot
$WorkerScript = Join-Path $PSScriptRoot "run_stem_worker_permanent.ps1"
$TaskName = "EZScore STEM Worker"

if (-not (Test-Path $WorkerScript)) {
    throw "Worker script not found: $WorkerScript"
}

$CurrentIdentity = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$PowerShell = (Get-Command powershell.exe).Source

$Action = New-ScheduledTaskAction `
    -Execute $PowerShell `
    -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$WorkerScript`""

$Trigger = New-ScheduledTaskTrigger -AtLogOn -User $CurrentIdentity

$Settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -MultipleInstances IgnoreNew `
    -RestartCount 999 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit ([TimeSpan]::Zero)

$Principal = New-ScheduledTaskPrincipal `
    -UserId $CurrentIdentity `
    -LogonType Interactive `
    -RunLevel Limited

$Task = New-ScheduledTask `
    -Action $Action `
    -Trigger $Trigger `
    -Settings $Settings `
    -Principal $Principal `
    -Description "EZScore permanent asynchronous STEM separation worker."

Register-ScheduledTask -TaskName $TaskName -InputObject $Task -Force | Out-Null
Start-ScheduledTask -TaskName $TaskName

Write-Host ""
Write-Host "[OK] Scheduled task installed and started: $TaskName"
Write-Host "Project: $Project"
Write-Host "Worker:  $WorkerScript"
Write-Host ""
Write-Host "Check status:"
Write-Host "  powershell -ExecutionPolicy Bypass -File .\scripts\status_stem_worker_task.ps1"
